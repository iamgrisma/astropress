<?php
/**
 * AstroPress Cache Invalidation, Cloudflare Direct Purge & Webhook Revalidation
 *
 * Provides automatic, debounced Cloudflare Edge cache invalidation on content changes,
 * taxonomy updates, navigation menu modifications, and site branding edits.
 * Supports:
 * - Batched webhook dispatches to Astro's /api/revalidate endpoint (shutdown micro-queue).
 * - Direct Cloudflare Zone Cache API purge (bypass Astro worker / direct edge invalidation).
 * - Cloudflare Pages & Workers Deploy Hook triggers.
 * - Comprehensive webhook audit log & delivery history.
 * - Automated retry queue with exponential backoff on cron.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Cache {
    /**
     * Master Plugin Instance
     *
     * @var AstroPress
     */
    protected $core;

    /**
     * Option key for webhook delivery logs
     */
    const OPTION_WEBHOOK_LOGS = 'astropress_webhook_logs';

    /**
     * Option key for failed webhooks retry queue
     */
    const OPTION_RETRY_QUEUE = 'astropress_failed_webhooks';

    /**
     * In-memory queue for debounced invalidation requests within the current request
     *
     * @var array
     */
    protected $pending_queue = array(
        'slugs'  => array(),
        'urls'   => array(),
        'events' => array(),
    );

    /**
     * Constructor
     *
     * @param AstroPress $core
     */
    public function __construct(AstroPress $core) {
        $this->core = $core;

        // Content Lifecycle Hooks: Granular automatic cache purging
        add_action('save_post', array($this, 'on_save_post'), 10, 3);
        add_action('wp_trash_post', array($this, 'on_trash_post'));
        add_action('untrash_post', array($this, 'on_trash_post'));

        // Taxonomy Lifecycle Hooks
        add_action('create_category', array($this, 'on_taxonomy_change'));
        add_action('edited_category', array($this, 'on_taxonomy_change'));
        add_action('delete_category', array($this, 'on_taxonomy_change'));
        add_action('create_post_tag', array($this, 'on_taxonomy_change'));
        add_action('edited_post_tag', array($this, 'on_taxonomy_change'));
        add_action('delete_post_tag', array($this, 'on_taxonomy_change'));

        // Navigation Menu Updates
        add_action('wp_update_nav_menu', array($this, 'on_menu_change'));

        // Extended Site Identity & Core Option Hooks
        add_action('update_option_blogname', array($this, 'on_site_info_change'));
        add_action('update_option_blogdescription', array($this, 'on_site_info_change'));
        add_action('update_option_site_icon', array($this, 'on_site_info_change'));
        add_action('update_option_page_on_front', array($this, 'on_site_info_change'));
        add_action('update_option_page_for_posts', array($this, 'on_site_info_change'));
        add_action('update_option_show_on_front', array($this, 'on_site_info_change'));
        add_action('customize_save_after', array($this, 'on_site_info_change'));

        // Debounced micro-queue execution on PHP shutdown
        add_action('shutdown', array($this, 'flush_invalidation_queue'));
    }

    /**
     * Compute canonical stripped URL path for taxonomy term
     *
     * @param WP_Term|int $term
     * @return string
     */
    public function get_term_path($term) {
        if (is_numeric($term)) {
            $term = get_term($term);
        }
        if (!$term || is_wp_error($term)) {
            return '';
        }

        $link = get_term_link($term);
        if (!is_wp_error($link)) {
            $path = wp_parse_url($link, PHP_URL_PATH);
            if ($path) {
                return '/' . trim($path, '/');
            }
        }
        return ($term->taxonomy === 'category') ? '/' . $term->slug : '/tag/' . $term->slug;
    }

    /**
     * Queue an invalidation event for debounced dispatch on shutdown
     *
     * @param string|null $slug
     * @param string $type
     * @param string $action
     * @param array $extra_urls
     * @return bool
     */
    public function queue_invalidation($slug = null, $type = 'post', $action = 'update', $extra_urls = array()) {
        // If debouncing is disabled, dispatch immediately
        if (!$this->core->should_debounce_webhooks()) {
            return $this->dispatch_revalidation($slug, $type, $action, $extra_urls);
        }

        if (!empty($slug)) {
            $this->pending_queue['slugs'][] = $slug;
        }

        if (!empty($extra_urls)) {
            foreach ((array) $extra_urls as $url) {
                if (!empty($url) && is_string($url)) {
                    $this->pending_queue['urls'][] = $url;
                }
            }
        }

        $this->pending_queue['events'][] = array(
            'slug'   => $slug,
            'type'   => $type,
            'action' => $action,
        );

        return true;
    }

    /**
     * Flush and execute pending invalidation queue on PHP shutdown
     */
    public function flush_invalidation_queue() {
        if (empty($this->pending_queue['events']) && empty($this->pending_queue['urls']) && empty($this->pending_queue['slugs'])) {
            return;
        }

        $unique_urls  = array_values(array_unique(array_filter($this->pending_queue['urls'])));
        $unique_slugs = array_values(array_unique(array_filter($this->pending_queue['slugs'])));
        $events       = $this->pending_queue['events'];

        // Reset memory state immediately to prevent recursion
        $this->pending_queue = array('slugs' => array(), 'urls' => array(), 'events' => array());

        $type   = (count($events) === 1) ? $events[0]['type'] : 'batch';
        $action = (count($events) === 1) ? $events[0]['action'] : 'update';
        $slug   = (count($unique_slugs) === 1) ? $unique_slugs[0] : null;

        $this->dispatch_revalidation($slug, $type, $action, $unique_urls);
    }

    /**
     * Dispatch revalidation request to Astro Cloudflare worker and optional direct purge
     *
     * @param string|null $slug
     * @param string $type
     * @param string $action
     * @param array $extra_urls
     * @param bool $blocking Whether to wait for HTTP response (useful for test/resend)
     * @return array Result summary with status and response info
     */
    public function dispatch_revalidation($slug = null, $type = 'post', $action = 'update', $extra_urls = array(), $blocking = false) {
        $frontend_url = $this->core->get_frontend_url();
        $secret       = $this->core->get_secret();

        $clean_urls = array_values(array_unique(array_filter((array)$extra_urls)));

        $payload = array(
            'slug'   => $slug,
            'type'   => $type,
            'action' => $action,
            'urls'   => $clean_urls,
            'time'   => current_time('mysql'),
            'warm'   => true,
        );

        $results = array(
            'astro_webhook'  => null,
            'cloudflare_api' => null,
            'deploy_hook'    => null,
        );

        // 1. Dispatch Webhook to Astro Frontend
        if (!empty($frontend_url) && !empty($secret)) {
            $endpoint   = rtrim($frontend_url, '/') . '/api/revalidate';
            $start_time = microtime(true);

            $response = wp_remote_post($endpoint, array(
                'method'      => 'POST',
                'timeout'     => $blocking ? 10 : 5,
                'blocking'    => $blocking,
                'sslverify'   => true,
                'headers'     => array(
                    'Content-Type'        => 'application/json',
                    'Origin'              => $frontend_url,
                    'Referer'             => $frontend_url,
                    'x-revalidate-secret' => $secret,
                ),
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
            ));

            $latency = round((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $this->log_webhook_event(array(
                    'trigger'     => "{$type}:{$action}",
                    'target'      => $endpoint,
                    'type'        => 'astro_webhook',
                    'urls_count'  => count($clean_urls) + ($slug ? 1 : 0),
                    'urls'        => array_slice($clean_urls, 0, 10),
                    'status'      => 'failed',
                    'http_code'   => 0,
                    'duration_ms' => $latency,
                    'message'     => 'Request Error: ' . $error_msg,
                    'payload'     => $payload,
                ));
                $this->enqueue_failed_webhook($payload, $endpoint, $error_msg);
                $results['astro_webhook'] = false;
            } else {
                $code = $blocking ? wp_remote_retrieve_response_code($response) : 202;
                $body = $blocking ? wp_remote_retrieve_body($response) : 'Dispatched asynchronously';
                $is_ok = ($code >= 200 && $code < 300);

                $this->log_webhook_event(array(
                    'trigger'     => "{$type}:{$action}",
                    'target'      => $endpoint,
                    'type'        => 'astro_webhook',
                    'urls_count'  => count($clean_urls) + ($slug ? 1 : 0),
                    'urls'        => array_slice($clean_urls, 0, 10),
                    'status'      => $is_ok ? ($blocking ? 'success' : 'dispatched') : 'failed',
                    'http_code'   => $code,
                    'duration_ms' => $latency,
                    'message'     => is_string($body) ? substr($body, 0, 200) : 'OK',
                    'payload'     => $payload,
                ));

                if (!$is_ok && $blocking) {
                    $this->enqueue_failed_webhook($payload, $endpoint, "HTTP {$code}");
                }
                $results['astro_webhook'] = $is_ok;
            }
        }

        // 2. Direct Cloudflare Zone Cache API Purge (if enabled and configured)
        if ($this->core->should_direct_purge_cloudflare()) {
            $is_purge_all = ($type === 'all' || in_array('/*', $clean_urls, true));
            if ($is_purge_all) {
                $results['cloudflare_api'] = $this->purge_cloudflare_zone(array(), true);
            } else {
                // Build full absolute URLs for Cloudflare API files array
                $full_urls = array();
                $base = !empty($frontend_url) ? rtrim($frontend_url, '/') : rtrim(home_url(), '/');

                if (!empty($slug)) {
                    $full_urls[] = $base . '/' . ltrim($slug, '/');
                }
                foreach ($clean_urls as $u) {
                    if (strpos($u, 'http') === 0) {
                        $full_urls[] = $u;
                    } else {
                        $full_urls[] = $base . '/' . ltrim($u, '/');
                    }
                }
                $full_urls = array_values(array_unique(array_filter($full_urls)));
                if (!empty($full_urls)) {
                    $results['cloudflare_api'] = $this->purge_cloudflare_zone($full_urls, false);
                }
            }
        }

        // 3. Trigger Cloudflare Deploy Hook on full site cache purge
        if ($type === 'all' && $action === 'purge') {
            $deploy_hook = $this->core->get_cf_deploy_hook();
            if (!empty($deploy_hook)) {
                $results['deploy_hook'] = $this->trigger_deploy_hook('Full Purge & Rebuild');
            }
        }

        // 4. LiteSpeed Cache synchronization
        if ($this->core->should_sync_litespeed() && defined('LSCWP_V')) {
            do_action('litespeed_purge_all');
        }

        return $results;
    }

    /**
     * Direct Cloudflare Zone Cache Purge via official Cloudflare API
     *
     * @param array $urls Array of full URLs to purge (max 30 per call per Cloudflare specs)
     * @param bool $purge_everything Whether to purge the entire zone cache
     * @return bool True on success, false on error
     */
    public function purge_cloudflare_zone($urls = array(), $purge_everything = false) {
        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->core->get_cf_zone_id();

        if (empty($token) || empty($zone_id)) {
            return false;
        }

        $endpoint = 'https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/purge_cache';

        if ($purge_everything) {
            $start_time = microtime(true);
            $res = wp_remote_post($endpoint, array(
                'method'      => 'POST',
                'timeout'     => 15,
                'sslverify'   => true,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'        => wp_json_encode(array('purge_everything' => true)),
                'data_format' => 'body',
            ));
            $latency = round((microtime(true) - $start_time) * 1000);

            $code    = is_wp_error($res) ? 0 : wp_remote_retrieve_response_code($res);
            $body    = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
            $data    = json_decode($body, true);
            $success = (!is_wp_error($res) && !empty($data['success']));

            $this->log_webhook_event(array(
                'trigger'     => 'zone:purge_everything',
                'target'      => 'Cloudflare Zone API (' . substr($zone_id, 0, 8) . '...)',
                'type'        => 'cloudflare_api',
                'urls_count'  => 0,
                'urls'        => array('/*'),
                'status'      => $success ? 'success' : 'failed',
                'http_code'   => $code,
                'duration_ms' => $latency,
                'message'     => $success ? 'All Edge Cache Purged' : ($data['errors'][0]['message'] ?? 'Purge failed'),
                'payload'     => array('purge_everything' => true),
            ));

            return $success;
        }

        // Chunk URLs into groups of 30 (Cloudflare API restriction)
        $url_chunks = array_chunk(array_values(array_unique(array_filter($urls))), 30);
        $all_success = true;

        foreach ($url_chunks as $chunk) {
            $start_time = microtime(true);
            $res = wp_remote_post($endpoint, array(
                'method'      => 'POST',
                'timeout'     => 15,
                'sslverify'   => true,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'        => wp_json_encode(array('files' => $chunk)),
                'data_format' => 'body',
            ));
            $latency = round((microtime(true) - $start_time) * 1000);

            $code    = is_wp_error($res) ? 0 : wp_remote_retrieve_response_code($res);
            $body    = is_wp_error($res) ? '' : wp_remote_retrieve_body($res);
            $data    = json_decode($body, true);
            $success = (!is_wp_error($res) && !empty($data['success']));

            if (!$success) {
                $all_success = false;
            }

            $this->log_webhook_event(array(
                'trigger'     => 'zone:purge_files',
                'target'      => 'Cloudflare Zone API (' . substr($zone_id, 0, 8) . '...)',
                'type'        => 'cloudflare_api',
                'urls_count'  => count($chunk),
                'urls'        => array_slice($chunk, 0, 10),
                'status'      => $success ? 'success' : 'failed',
                'http_code'   => $code,
                'duration_ms' => $latency,
                'message'     => $success ? count($chunk) . ' files purged from edge' : ($data['errors'][0]['message'] ?? 'File purge failed'),
                'payload'     => array('files' => $chunk),
            ));
        }

        return $all_success;
    }

    /**
     * Trigger Cloudflare Pages / Workers Deploy Hook
     *
     * @param string $reason Deploy trigger note
     * @return bool
     */
    public function trigger_deploy_hook($reason = 'Manual Deploy') {
        $deploy_hook = $this->core->get_cf_deploy_hook();
        if (empty($deploy_hook)) {
            return false;
        }

        $start_time = microtime(true);
        $res = wp_remote_post($deploy_hook, array(
            'method'    => 'POST',
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => array('Content-Type' => 'application/json'),
            'body'      => wp_json_encode(array('reason' => $reason, 'site' => home_url())),
        ));
        $latency = round((microtime(true) - $start_time) * 1000);

        $code    = is_wp_error($res) ? 0 : wp_remote_retrieve_response_code($res);
        $success = (!is_wp_error($res) && $code >= 200 && $code < 300);

        $this->log_webhook_event(array(
            'trigger'     => 'deploy_hook',
            'target'      => 'Cloudflare Deploy Hook',
            'type'        => 'deploy_hook',
            'urls_count'  => 0,
            'urls'        => array(),
            'status'      => $success ? 'success' : 'failed',
            'http_code'   => $code,
            'duration_ms' => $latency,
            'message'     => $success ? 'Deploy Hook Triggered: ' . $reason : 'Deploy Hook Failed',
            'payload'     => array('reason' => $reason),
        ));

        return $success;
    }

    /**
     * Purge entire website edge cache on Astro and Cloudflare
     *
     * @return array
     */
    public function purge_entire_cache() {
        return $this->dispatch_revalidation(null, 'all', 'purge', array('/*'), true);
    }

    /**
     * Hook: On Post / Page Save & Publish (Granular Purging)
     */
    public function on_save_post($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Invalidate cached Rank Math head so changes take effect immediately
        delete_post_meta($post_id, '_astropress_rm_head');
        delete_post_meta($post_id, '_headless_rm_head');

        if ($post->post_status !== 'publish') return;
        if (!in_array($post->post_type, array('post', 'page'))) return;

        if (!$this->core->should_purge_on_save()) return;

        $extra_urls = array();
        if ($post->post_type === 'post') {
            // Category archives
            $categories = get_the_category($post_id);
            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $extra_urls[] = $this->get_term_path($cat);
                    if ($cat->parent) {
                        $parent = get_category($cat->parent);
                        if ($parent && !is_wp_error($parent)) {
                            $extra_urls[] = $this->get_term_path($parent);
                        }
                    }
                }
            }
            // Tag archives
            $tags = get_the_tags($post_id);
            if (!empty($tags) && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $extra_urls[] = '/tag/' . $tag->slug;
                }
            }
        }

        // Purge RSS feeds and sitemaps so feed readers & search engines see updates immediately
        if ($this->core->should_purge_feeds()) {
            $extra_urls[] = '/sitemap.xml';
            $extra_urls[] = '/sitemap_index.xml';
            $extra_urls[] = '/rss.xml';
            $extra_urls[] = '/feed.xml';
            $extra_urls[] = '/blogs';
            $extra_urls[] = '/blog';
        }

        $this->queue_invalidation($post->post_name, $post->post_type, $update ? 'update' : 'publish', array_unique(array_filter($extra_urls)));
    }

    /**
     * Hook: On Trash / Delete Post
     */
    public function on_trash_post($post_id) {
        delete_post_meta($post_id, '_astropress_rm_head');
        delete_post_meta($post_id, '_headless_rm_head');

        $post = get_post($post_id);
        if (!$post) return;
        if (!$this->core->should_purge_on_save()) return;

        $extra_urls = array();
        if ($post->post_type === 'post') {
            $categories = get_the_category($post_id);
            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $extra_urls[] = $this->get_term_path($cat);
                }
            }
            $tags = get_the_tags($post_id);
            if (!empty($tags) && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $extra_urls[] = '/tag/' . $tag->slug;
                }
            }
        }

        if ($this->core->should_purge_feeds()) {
            $extra_urls[] = '/sitemap.xml';
            $extra_urls[] = '/sitemap_index.xml';
            $extra_urls[] = '/rss.xml';
            $extra_urls[] = '/feed.xml';
            $extra_urls[] = '/blogs';
            $extra_urls[] = '/blog';
        }

        $this->queue_invalidation($post->post_name, $post->post_type, 'delete', array_unique(array_filter($extra_urls)));
    }

    /**
     * Hook: On Taxonomy (Category/Tag) Create, Edit, or Delete
     */
    public function on_taxonomy_change($term_id) {
        if (!$this->core->should_purge_taxonomy()) return;

        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) return;

        $path = $this->get_term_path($term);
        $this->queue_invalidation(null, 'taxonomy', 'update', array_filter(array(
            $path,
            '/blogs',
            '/blog',
            '/sitemap.xml',
            '/sitemap_index.xml',
            '/rss.xml',
            '/feed.xml',
        )));
    }

    /**
     * Hook: On Navigation Menu Change
     */
    public function on_menu_change($menu_id) {
        if (!$this->core->should_purge_menu()) return;
        $this->queue_invalidation(null, 'menu', 'update', array('/', '/blogs', '/blog', '/rss.xml'));
    }

    /**
     * Hook: On Site Info / Branding Change
     */
    public function on_site_info_change() {
        $this->queue_invalidation(null, 'site_info', 'update', array('/', '/blogs', '/blog', '/rss.xml', '/sitemap.xml'));
    }

    /**
     * Append entry to Webhook Delivery Audit Log
     *
     * @param array $entry Log attributes
     */
    public function log_webhook_event($entry) {
        $logs = (array) get_option(self::OPTION_WEBHOOK_LOGS, array());

        $item = array_merge(array(
            'id'          => 'wh_' . substr(md5(uniqid(wp_rand(), true)), 0, 10),
            'time'        => current_time('mysql'),
            'trigger'     => 'manual',
            'target'      => '',
            'type'        => 'astro_webhook',
            'urls_count'  => 0,
            'urls'        => array(),
            'status'      => 'success',
            'http_code'   => 200,
            'duration_ms' => 0,
            'message'     => '',
            'payload'     => array(),
        ), $entry);

        array_unshift($logs, $item);

        // Retain max 50 recent entries to keep database lean
        if (count($logs) > 50) {
            $logs = array_slice($logs, 0, 50);
        }

        update_option(self::OPTION_WEBHOOK_LOGS, $logs, false);
    }

    /**
     * Get Webhook Delivery Audit Logs
     *
     * @param int $limit Maximum entries to return
     * @return array
     */
    public function get_webhook_logs($limit = 50) {
        $logs = (array) get_option(self::OPTION_WEBHOOK_LOGS, array());
        return array_slice($logs, 0, max(1, min(100, (int)$limit)));
    }

    /**
     * Clear all Webhook Delivery Audit Logs
     *
     * @return bool
     */
    public function clear_webhook_logs() {
        return delete_option(self::OPTION_WEBHOOK_LOGS);
    }

    /**
     * Resend an existing logged webhook
     *
     * @param string $log_id
     * @return array Result information
     */
    public function resend_webhook($log_id) {
        $logs = (array) get_option(self::OPTION_WEBHOOK_LOGS, array());
        $target_entry = null;

        foreach ($logs as $entry) {
            if (isset($entry['id']) && $entry['id'] === $log_id) {
                $target_entry = $entry;
                break;
            }
        }

        if (!$target_entry || empty($target_entry['payload'])) {
            return array('success' => false, 'message' => 'Log entry or payload not found.');
        }

        $payload = $target_entry['payload'];
        $type    = $payload['type'] ?? 'post';
        $action  = $payload['action'] ?? 'update';
        $slug    = $payload['slug'] ?? null;
        $urls    = $payload['urls'] ?? array();

        // Perform blocking revalidation
        $result = $this->dispatch_revalidation($slug, $type, $action, $urls, true);

        return array(
            'success' => (!empty($result['astro_webhook']) || !empty($result['cloudflare_api'])),
            'result'  => $result,
        );
    }

    /**
     * Enqueue a failed webhook for automatic cron retry
     *
     * @param array $payload Webhook payload
     * @param string $endpoint Destination URL
     * @param string $error_msg Error message
     */
    public function enqueue_failed_webhook($payload, $endpoint, $error_msg = '') {
        $queue = (array) get_option(self::OPTION_RETRY_QUEUE, array());

        $item = array(
            'id'         => 'retry_' . substr(md5(uniqid(wp_rand(), true)), 0, 8),
            'payload'    => $payload,
            'endpoint'   => $endpoint,
            'attempts'   => 0,
            'last_error' => $error_msg,
            'queued_at'  => time(),
        );

        $queue[] = $item;

        // Limit retry queue size to 30 items
        if (count($queue) > 30) {
            $queue = array_slice($queue, -30);
        }

        update_option(self::OPTION_RETRY_QUEUE, $queue, false);

        // Schedule one-off or recurring retry cron if not scheduled
        if (!wp_next_scheduled('astropress_retry_webhooks')) {
            wp_schedule_event(time() + 300, 'hourly', 'astropress_retry_webhooks');
        }
    }

    /**
     * Cron Callback: Retry failed webhooks with exponential backoff
     */
    public function retry_failed_webhooks() {
        $queue = (array) get_option(self::OPTION_RETRY_QUEUE, array());
        if (empty($queue)) {
            wp_clear_scheduled_hook('astropress_retry_webhooks');
            return;
        }

        $remaining = array();
        $frontend_url = $this->core->get_frontend_url();
        $secret       = $this->core->get_secret();

        foreach ($queue as $item) {
            $attempts = (int) ($item['attempts'] ?? 0) + 1;
            $payload  = $item['payload'] ?? array();
            $endpoint = $item['endpoint'] ?? '';

            if (empty($endpoint) || empty($secret)) {
                continue;
            }

            $start_time = microtime(true);
            $res = wp_remote_post($endpoint, array(
                'method'      => 'POST',
                'timeout'     => 10,
                'blocking'    => true,
                'sslverify'   => true,
                'headers'     => array(
                    'Content-Type'        => 'application/json',
                    'Origin'              => $frontend_url,
                    'Referer'             => $frontend_url,
                    'x-revalidate-secret' => $secret,
                ),
                'body'        => wp_json_encode($payload),
                'data_format' => 'body',
            ));
            $latency = round((microtime(true) - $start_time) * 1000);

            $code = is_wp_error($res) ? 0 : wp_remote_retrieve_response_code($res);
            $is_success = (!is_wp_error($res) && $code >= 200 && $code < 300);

            if ($is_success) {
                $this->log_webhook_event(array(
                    'trigger'     => 'retry:cron',
                    'target'      => $endpoint,
                    'type'        => 'astro_webhook',
                    'urls_count'  => count($payload['urls'] ?? array()) + (!empty($payload['slug']) ? 1 : 0),
                    'urls'        => array_slice($payload['urls'] ?? array(), 0, 10),
                    'status'      => 'success',
                    'http_code'   => $code,
                    'duration_ms' => $latency,
                    'message'     => "Retry succeeded after {$attempts} attempt(s)",
                    'payload'     => $payload,
                ));
            } else {
                $err = is_wp_error($res) ? $res->get_error_message() : "HTTP {$code}";
                if ($attempts < 3) {
                    $item['attempts']   = $attempts;
                    $item['last_error'] = $err;
                    $remaining[]        = $item;
                } else {
                    $this->log_webhook_event(array(
                        'trigger'     => 'retry:exhausted',
                        'target'      => $endpoint,
                        'type'        => 'astro_webhook',
                        'urls_count'  => count($payload['urls'] ?? array()) + (!empty($payload['slug']) ? 1 : 0),
                        'urls'        => array_slice($payload['urls'] ?? array(), 0, 10),
                        'status'      => 'failed',
                        'http_code'   => $code,
                        'duration_ms' => $latency,
                        'message'     => "Retry discarded after {$attempts} failed attempts: {$err}",
                        'payload'     => $payload,
                    ));
                }
            }
        }

        if (empty($remaining)) {
            delete_option(self::OPTION_RETRY_QUEUE);
            wp_clear_scheduled_hook('astropress_retry_webhooks');
        } else {
            update_option(self::OPTION_RETRY_QUEUE, $remaining, false);
        }
    }

    /**
     * Warmer Delegation for Backward Compatibility
     */
    public function get_warmup_urls($preset = 'standard') {
        return isset($this->core->warmer) ? $this->core->warmer->get_warmup_urls($preset) : array();
    }

    public function warm_url_batch($urls = array()) {
        return isset($this->core->warmer) ? $this->core->warmer->warm_url_batch($urls) : array();
    }

    public function start_background_job($preset = 'standard', $delay_ms = 1000, $batch_size = 2) {
        return isset($this->core->warmer) ? $this->core->warmer->start_background_job($preset, $delay_ms, $batch_size) : false;
    }

    public function run_background_worker() {
        return isset($this->core->warmer) ? $this->core->warmer->run_background_worker() : null;
    }

    public function get_background_job_status() {
        return isset($this->core->warmer) ? $this->core->warmer->get_background_job_status() : array();
    }

    public function pause_background_job() {
        return isset($this->core->warmer) ? $this->core->warmer->pause_background_job() : false;
    }

    public function resume_background_job() {
        return isset($this->core->warmer) ? $this->core->warmer->resume_background_job() : false;
    }

    public function cancel_background_job() {
        return isset($this->core->warmer) ? $this->core->warmer->cancel_background_job() : false;
    }

    public function clear_background_job() {
        return isset($this->core->warmer) ? $this->core->warmer->clear_background_job() : false;
    }

    public function warm_recent_content() {
        $recent = $this->get_warmup_urls('recent');
        if (!empty($recent)) {
            $this->warm_url_batch(array_slice($recent, 0, 10));
        }
    }

    public function __call($name, $arguments) {
        if (isset($this->core->warmer) && method_exists($this->core->warmer, $name)) {
            return call_user_func_array(array($this->core->warmer, $name), $arguments);
        }
        return null;
    }
}
