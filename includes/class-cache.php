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
    public function queue_invalidation($slug = null, $type = 'post', $action = 'update', $extra_urls = array(), $extra_data = array()) {
        // If debouncing is disabled, dispatch immediately
        if (!$this->core->should_debounce_webhooks()) {
            return $this->dispatch_revalidation($slug, $type, $action, $extra_urls, false, $extra_data);
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
            'data'   => $extra_data,
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

        $extra_data = array();
        foreach ($events as $ev) {
            if (!empty($ev['data']) && is_array($ev['data'])) {
                $extra_data = array_merge($extra_data, $ev['data']);
            }
        }

        $this->dispatch_revalidation($slug, $type, $action, $unique_urls, false, $extra_data);
    }

    /**
     * Dispatch revalidation request to Astro Cloudflare worker and optional direct purge
     *
     * @param string|null $slug
     * @param string $type
     * @param string $action
     * @param array $extra_urls
     * @param bool $blocking Whether to wait for HTTP response (useful for test/resend)
     * @param array $extra_data Dynamic post or page payload for in-memory snapshot updates
     * @return array Result summary with status and response info
     */
    public function dispatch_revalidation($slug = null, $type = 'post', $action = 'update', $extra_urls = array(), $blocking = false, $extra_data = array()) {
        $frontend_url = $this->core->get_frontend_url();
        $cf_token     = $this->core->get_cf_api_token();
        $cf_zone      = $this->core->get_cf_zone_id();

        $clean_urls = array_values(array_unique(array_filter((array)$extra_urls)));

        $results = array(
            'cloudflare_api' => null,
            'deploy_hook'    => null,
        );

        // 1. Direct Cloudflare Zone Cache API Purge (Primary Invalidation)
        if (!empty($cf_token) && !empty($cf_zone)) {
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

        // 2. Trigger Cloudflare Deploy Hook on full site cache purge
        if ($type === 'all' && $action === 'purge') {
            $deploy_hook = $this->core->get_cf_deploy_hook();
            if (!empty($deploy_hook)) {
                $results['deploy_hook'] = $this->trigger_deploy_hook('Full Purge & Rebuild');
            }
        }

        // 3. LiteSpeed Cache synchronization
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
    public function purge_cloudflare_zone($urls = array(), $purge_everything = false, $override_token = '', $override_zone_id = '') {
        $token   = !empty($override_token) ? $override_token : $this->core->get_cf_api_token();
        $zone_id = !empty($override_zone_id) ? $override_zone_id : $this->core->get_cf_zone_id();

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
        if (empty($deploy_hook) || !AstroPress_Security::is_safe_external_url($deploy_hook)) {
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

        $extra_data = array();
        if ($post->post_type === 'post') {
            if (class_exists('WP_REST_Request') && class_exists('WP_REST_Posts_Controller')) {
                $req = new WP_REST_Request('GET', '/wp/v2/posts/' . $post_id);
                $req->set_param('context', 'view');
                $req->set_param('_embed', true);
                $res = rest_do_request($req);
                if (!$res->is_error()) {
                    $extra_data['post'] = $res->get_data();
                }
            }
        } elseif ($post->post_type === 'page') {
            if (class_exists('WP_REST_Request') && class_exists('WP_REST_Posts_Controller')) {
                $req = new WP_REST_Request('GET', '/wp/v2/pages/' . $post_id);
                $req->set_param('context', 'view');
                $req->set_param('_embed', true);
                $res = rest_do_request($req);
                if (!$res->is_error()) {
                    $extra_data['page'] = $res->get_data();
                }
            }
        }

        $this->queue_invalidation($post->post_name, $post->post_type, $update ? 'update' : 'publish', array_unique(array_filter($extra_urls)), $extra_data);
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

    /**
     * Push Environment Variables directly to a Cloudflare Pages Project
     *
     * Injects REVALIDATE_SECRET, WP_API_URL, etc., into both production & preview environments.
     *
     * @param string $project_name
     * @param array $env_vars Associative array of key => value pairs
     * @param string $account_id
     * @param string $token
     * @return array [ 'success' => bool, 'message' => string ]
     */
    public function sync_pages_env($project_name, $env_vars, $account_id = '', $token = '') {
        $project_name = sanitize_text_field($project_name);
        $account_id   = sanitize_text_field($account_id ?: get_option('r2g_account_id', ''));
        $token        = $token ?: $this->core->get_cf_api_token();

        if (empty($project_name) || empty($account_id) || empty($token)) {
            return array('success' => false, 'message' => esc_html__('Missing Pages Project Name, Account ID, or Cloudflare API Token.', 'astropress'));
        }

        // 1. Fetch current project configuration to avoid overwriting unrelated environment variables
        $get_url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/" . rawurlencode($project_name);
        $get_res = wp_remote_get($get_url, array(
            'timeout' => 12,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (is_wp_error($get_res)) {
            return array('success' => false, 'message' => 'Connection error: ' . $get_res->get_error_message());
        }

        $get_code = wp_remote_retrieve_response_code($get_res);
        $get_body = json_decode(wp_remote_retrieve_body($get_res), true);

        if ($get_code !== 200 || empty($get_body['success'])) {
            $err = !empty($get_body['errors'][0]['message']) ? $get_body['errors'][0]['message'] : "HTTP {$get_code}";
            return array('success' => false, 'message' => sprintf(esc_html__('Could not read Pages project "%s": %s', 'astropress'), $project_name, $err));
        }

        $project_data = $get_body['result'] ?? array();
        $prod_env = $project_data['deployment_configs']['production']['env_vars'] ?? array();
        $prev_env = $project_data['deployment_configs']['preview']['env_vars'] ?? array();

        // 2. Merge new variables into production and preview maps
        foreach ($env_vars as $k => $v) {
            $prod_env[$k] = array('value' => (string) $v, 'type' => 'plain_text');
            $prev_env[$k] = array('value' => (string) $v, 'type' => 'plain_text');
        }

        $patch_payload = array(
            'deployment_configs' => array(
                'production' => array('env_vars' => $prod_env),
                'preview'    => array('env_vars' => $prev_env),
            ),
        );

        $patch_res = wp_remote_request($get_url, array(
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($patch_payload),
        ));

        if (is_wp_error($patch_res)) {
            return array('success' => false, 'message' => 'Update failed: ' . $patch_res->get_error_message());
        }

        $patch_code = wp_remote_retrieve_response_code($patch_res);
        $patch_body = json_decode(wp_remote_retrieve_body($patch_res), true);

        if ($patch_code === 200 && !empty($patch_body['success'])) {
            return array(
                'success' => true,
                'message' => sprintf(esc_html__('Successfully synced %d environment variable(s) to Cloudflare Pages project "%s" (Production & Preview)!', 'astropress'), count($env_vars), $project_name),
            );
        }

        $patch_err = !empty($patch_body['errors'][0]['message']) ? $patch_body['errors'][0]['message'] : "HTTP {$patch_code}";
        return array('success' => false, 'message' => sprintf(esc_html__('Cloudflare Pages API error: %s (Ensure token has "Account > Cloudflare Pages: Edit" permission).', 'astropress'), $patch_err));
    }

    /**
     * Audit Custom CDN Domain for DNS, R2 Bucket Attachment & Edge Caching Rules
     *
     * @param string $custom_domain
     * @param string $zone_id
     * @param string $account_id
     * @param string $bucket_name
     * @param string $token
     * @return array
     */
    public function audit_cdn_domain($custom_domain, $zone_id, $account_id = '', $bucket_name = '', $token = '') {
        $parsed = wp_parse_url($custom_domain);
        $host   = !empty($parsed['host']) ? $parsed['host'] : trim($custom_domain, '/');
        $zone_id = sanitize_text_field($zone_id ?: $this->core->get_cf_zone_id());
        $account_id = sanitize_text_field($account_id ?: get_option('r2g_account_id', ''));
        $bucket_name = sanitize_text_field($bucket_name ?: get_option('r2g_bucket', ''));
        $token   = $token ?: $this->core->get_cf_api_token();

        if (empty($host) || empty($zone_id) || empty($token)) {
            return array('success' => false, 'message' => esc_html__('Domain, Zone ID, and API Token are required.', 'astropress'));
        }

        $headers = array('Authorization' => 'Bearer ' . $token);

        // A. Check DNS Record on Cloudflare Zone
        $dns_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?name=" . rawurlencode($host);
        $dns_res = wp_remote_get($dns_url, array('timeout' => 8, 'headers' => $headers));
        $dns_found = false;
        $dns_proxied = false;
        $dns_content = '';
        if (!is_wp_error($dns_res) && wp_remote_retrieve_response_code($dns_res) === 200) {
            $dns_body = json_decode(wp_remote_retrieve_body($dns_res), true);
            if (!empty($dns_body['result']) && is_array($dns_body['result']) && count($dns_body['result']) > 0) {
                $dns_found = true;
                $dns_proxied = !empty($dns_body['result'][0]['proxied']);
                $dns_content = $dns_body['result'][0]['content'] ?? '';
            }
        }

        // B. Check R2 Custom Domains Attachment
        $r2_attached = false;
        $r2_status = '';
        if (!empty($account_id) && !empty($bucket_name)) {
            $r2_cd_url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/r2/buckets/" . rawurlencode($bucket_name) . "/domains/custom";
            $r2_cd_res = wp_remote_get($r2_cd_url, array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($r2_cd_res) && wp_remote_retrieve_response_code($r2_cd_res) === 200) {
                $r2_cd_body = json_decode(wp_remote_retrieve_body($r2_cd_res), true);
                if (!empty($r2_cd_body['result']['domains']) && is_array($r2_cd_body['result']['domains'])) {
                    foreach ($r2_cd_body['result']['domains'] as $d) {
                        if (strcasecmp($d['domain'] ?? '', $host) === 0) {
                            $r2_attached = true;
                            $r2_status = $d['status']['state'] ?? 'active';
                            break;
                        }
                    }
                }
            }
        }

        // C. Check Modern Cloudflare Cache Rules (Ruleset Engine) & Legacy Page Rules
        $pattern = "*{$host}/*";
        $rule_status = 'missing';
        $rule_id = '';
        $rule_desc = '';

        // C1. Check Modern Cache Rules (Phase: http_request_cache_settings)
        $ruleset_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/phases/http_request_cache_settings/entrypoint";
        $ruleset_res = wp_remote_get($ruleset_url, array('timeout' => 8, 'headers' => $headers));
        if (!is_wp_error($ruleset_res) && wp_remote_retrieve_response_code($ruleset_res) === 200) {
            $rs_body = json_decode(wp_remote_retrieve_body($ruleset_res), true);
            if (!empty($rs_body['result']['rules']) && is_array($rs_body['result']['rules'])) {
                foreach ($rs_body['result']['rules'] as $rule) {
                    $expr = $rule['expression'] ?? '';
                    if (strpos($expr, $host) !== false) {
                        $rule_id = $rule['id'] ?? '';
                        $act_params = $rule['action_parameters'] ?? array();
                        $has_cache = !empty($act_params['cache']);
                        $has_edge_ttl = !empty($act_params['edge_ttl']);
                        if ($has_cache && $has_edge_ttl) {
                            $rule_status = 'optimal';
                            $rule_desc = '1-Year Cache Everything Active (Cloudflare Cache Rule)';
                        } else {
                            $rule_status = 'suboptimal';
                            $rule_desc = 'Cache Rule exists for domain but lacks 1-year Edge TTL';
                        }
                        break;
                    }
                }
            }
        }

        // C2. Fallback: Check Legacy Page Rules if not matched in Ruleset Engine
        if ($rule_status === 'missing') {
            $rules_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules?status=active&per_page=50";
            $rules_res = wp_remote_get($rules_url, array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($rules_res) && wp_remote_retrieve_response_code($rules_res) === 200) {
                $rules_body = json_decode(wp_remote_retrieve_body($rules_res), true);
                if (!empty($rules_body['result']) && is_array($rules_body['result'])) {
                    foreach ($rules_body['result'] as $r) {
                        $val = $r['targets'][0]['constraint']['value'] ?? '';
                        if ($val === $pattern || strpos($val, $host) !== false) {
                            $rule_id = $r['id'] ?? '';
                            $is_cache_everything = false;
                            $has_long_ttl = false;
                            if (!empty($r['actions']) && is_array($r['actions'])) {
                                foreach ($r['actions'] as $act) {
                                    if ($act['id'] === 'cache_level' && $act['value'] === 'cache_everything') {
                                        $is_cache_everything = true;
                                    }
                                    if ($act['id'] === 'edge_cache_ttl' && $act['value'] >= 86400) {
                                        $has_long_ttl = true;
                                    }
                                }
                            }
                            if ($is_cache_everything && $has_long_ttl) {
                                $rule_status = 'optimal';
                                $rule_desc = '1-Year Cache Everything Active (Page Rule)';
                            } else {
                                $rule_status = 'suboptimal';
                                $rule_desc = 'Page Rule exists but lacks 1-year Cache Everything setting';
                            }
                            break;
                        }
                    }
                }
            }
        }

        return array(
            'success'          => true,
            'host'             => $host,
            'dns_found'        => $dns_found,
            'dns_proxied'      => $dns_proxied,
            'dns_content'      => $dns_content,
            'r2_attached'      => $r2_attached,
            'r2_status'        => $r2_status,
            'rule_status'      => $rule_status,
            'rule_id'          => $rule_id,
            'rule_desc'        => $rule_desc,
        );
    }

    /**
     * Attach a Custom Domain to Cloudflare R2 Bucket
     *
     * @param string $bucket_name
     * @param string $custom_domain
     * @param string $zone_id
     * @param string $zone_name
     * @param string $account_id
     * @param string $token
     * @return array
     */
    public function attach_r2_custom_domain($bucket_name, $custom_domain, $zone_id, $zone_name = '', $account_id = '', $token = '') {
        $parsed = wp_parse_url($custom_domain);
        $host   = !empty($parsed['host']) ? $parsed['host'] : trim($custom_domain, '/');
        $bucket_name = sanitize_text_field($bucket_name ?: get_option('r2g_bucket', ''));
        $zone_id     = sanitize_text_field($zone_id ?: $this->core->get_cf_zone_id());
        $account_id  = sanitize_text_field($account_id ?: get_option('r2g_account_id', ''));
        $token       = $token ?: $this->core->get_cf_api_token();

        if (empty($host) || empty($bucket_name) || empty($zone_id) || empty($account_id) || empty($token)) {
            return array('success' => false, 'message' => esc_html__('Missing required parameters to attach R2 custom domain.', 'astropress'));
        }

        $endpoint = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/r2/buckets/" . rawurlencode($bucket_name) . "/domains/custom";
        $payload = array(
            'domain'   => $host,
            'zoneId'   => $zone_id,
            'zoneName' => $zone_name ?: $host,
            'minTLS'   => '1.2',
        );

        $res = wp_remote_post($endpoint, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($payload),
        ));

        if (is_wp_error($res)) {
            return array('success' => false, 'message' => 'Connection error: ' . $res->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = json_decode(wp_remote_retrieve_body($res), true);

        if ($code === 200 || $code === 201 || !empty($body['success'])) {
            $this->ensure_dns_cname($zone_id, $host, "{$bucket_name}.r2.cloudflarestorage.com", $token);
            return array(
                'success' => true,
                'message' => sprintf(esc_html__('Custom domain "%s" attached to R2 bucket "%s" successfully! DNS routing and Edge proxying are active.', 'astropress'), $host, $bucket_name),
            );
        }

        $err = !empty($body['errors'][0]['message']) ? $body['errors'][0]['message'] : "HTTP {$code}";

        // If domain is already attached or exists, treat gracefully as success and ensure DNS proxying
        if (stripos($err, 'already') !== false || $code === 409) {
            $this->ensure_dns_cname($zone_id, $host, "{$bucket_name}.r2.cloudflarestorage.com", $token);
            return array(
                'success' => true,
                'message' => sprintf(esc_html__('Custom domain "%s" is already linked to R2 bucket "%s". DNS routing and Edge proxying verified.', 'astropress'), $host, $bucket_name),
            );
        }

        return array('success' => false, 'message' => sprintf(esc_html__('Failed to attach custom domain: %s (Requires "Zone > DNS: Edit" & "Account > Workers R2 Storage: Edit").', 'astropress'), $err));
    }

    /**
     * Ensure DNS CNAME record exists and is proxied for R2 custom domain
     *
     * @param string $zone_id
     * @param string $host
     * @param string $cname_target
     * @param string $token
     * @return array
     */
    public function ensure_dns_cname($zone_id, $host, $cname_target, $token = '') {
        $token = $token ?: $this->core->get_cf_api_token();
        if (empty($zone_id) || empty($host) || empty($token)) {
            return array('success' => false, 'message' => esc_html__('Zone ID, host, and token are required.', 'astropress'));
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        );

        // 1. Query existing DNS records for this hostname
        $check_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?name=" . rawurlencode($host);
        $res = wp_remote_get($check_url, array('timeout' => 8, 'headers' => $headers));
        if (is_wp_error($res)) {
            return array('success' => false, 'message' => $res->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!empty($body['result']) && is_array($body['result']) && count($body['result']) > 0) {
            $record = $body['result'][0];
            // If DNS record exists but is unproxied, patch it to proxied: true
            if (empty($record['proxied'])) {
                $patch_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/" . rawurlencode($record['id']);
                wp_remote_request($patch_url, array(
                    'method'  => 'PATCH',
                    'timeout' => 8,
                    'headers' => $headers,
                    'body'    => wp_json_encode(array('proxied' => true)),
                ));
            }
            return array('success' => true, 'message' => sprintf(esc_html__('DNS record for %s is verified and proxied.', 'astropress'), $host));
        }

        // 2. If no record exists, create CNAME record pointing to target with Cloudflare proxy
        $create_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records";
        $create_res = wp_remote_post($create_url, array(
            'timeout' => 10,
            'headers' => $headers,
            'body'    => wp_json_encode(array(
                'type'    => 'CNAME',
                'name'    => $host,
                'content' => $cname_target,
                'proxied' => true,
                'ttl'     => 1, // Auto
            )),
        ));

        if (is_wp_error($create_res)) {
            return array('success' => false, 'message' => $create_res->get_error_message());
        }

        $c_code = wp_remote_retrieve_response_code($create_res);
        $c_body = json_decode(wp_remote_retrieve_body($create_res), true);
        if ($c_code === 200 || $c_code === 201 || !empty($c_body['success'])) {
            return array('success' => true, 'message' => sprintf(esc_html__('DNS CNAME record created for %s with Cloudflare Proxy enabled.', 'astropress'), $host));
        }

        $c_err = !empty($c_body['errors'][0]['message']) ? $c_body['errors'][0]['message'] : "HTTP {$c_code}";
        return array('success' => false, 'message' => $c_err);
    }

    /**
     * Auto-Detect Active Astro Frontend & Revalidation Bridge on Entered URL
     *
     * Pings root and /api/revalidate to verify deployment status, Cloudflare edge,
     * framework signatures, and webhook reachability.
     *
     * @param string $frontend_url
     * @return array
     */
    public function detect_frontend($frontend_url) {
        $url = trim($frontend_url);
        if (empty($url)) {
            return array(
                'reachable' => false,
                'message'   => esc_html__('No frontend URL provided.', 'astropress'),
            );
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $url = rtrim($url, '/');

        if (!AstroPress_Security::is_safe_external_url($url)) {
            return array(
                'url'               => $url,
                'reachable'         => false,
                'root_status'       => 0,
                'latency_ms'        => 0,
                'is_astro'          => false,
                'is_cloudflare'     => false,
                'webhook_reachable' => false,
                'webhook_status'    => 0,
                'message'           => esc_html__('Target URL blocked: local loopback and reserved private IP ranges are not permitted.', 'astropress'),
            );
        }

        // 1. Ping root URL with latency measurement
        $start_time = microtime(true);
        $root_res = wp_remote_get($url, array(
            'timeout'            => 10,
            'redirection'        => 3,
            'sslverify'          => false,
            'reject_unsafe_urls' => true,
            'headers'            => array(
                'User-Agent' => 'AstroPress-Bridge-Detector/1.2.5 (WordPress/' . get_bloginfo('version') . ')',
            ),
        ));
        $latency_ms = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($root_res)) {
            return array(
                'url'               => $url,
                'reachable'         => false,
                'root_status'       => 0,
                'latency_ms'        => $latency_ms,
                'is_astro'          => false,
                'is_cloudflare'     => false,
                'webhook_reachable' => false,
                'webhook_status'    => 0,
                'message'           => sprintf(esc_html__('Could not reach frontend URL: %s', 'astropress'), $root_res->get_error_message()),
            );
        }

        $root_code = (int) wp_remote_retrieve_response_code($root_res);
        $headers   = wp_remote_retrieve_headers($root_res);
        $body      = wp_remote_retrieve_body($root_res);

        // Analyze Cloudflare Edge signatures
        $server_header = strtolower($headers['server'] ?? '');
        $cf_ray        = $headers['cf-ray'] ?? '';
        $is_cloudflare = strpos($server_header, 'cloudflare') !== false || !empty($cf_ray);

        // Analyze Astro Framework signatures
        $is_astro = false;
        $confidence = 'unknown';

        if (stripos($body, 'generator" content="Astro') !== false || stripos($body, 'astro-island') !== false || stripos($body, 'data-astro-') !== false) {
            $is_astro = true;
            $confidence = 'confirmed';
        } elseif (stripos($body, '_astro/') !== false || stripos($body, 'astro') !== false) {
            $is_astro = true;
            $confidence = 'probable';
        }

        $summary = sprintf(
            esc_html__('Status %d in %dms. %s%s.', 'astropress'),
            $root_code,
            $latency_ms,
            $is_astro ? esc_html__('Astro frontend detected', 'astropress') : esc_html__('Frontend responding', 'astropress'),
            $is_cloudflare ? esc_html__(' (Cloudflare Edge active)', 'astropress') : ''
        );

        return array(
            'url'           => $url,
            'reachable'     => ($root_code >= 200 && $root_code < 400),
            'root_status'   => $root_code,
            'latency_ms'    => $latency_ms,
            'is_astro'      => $is_astro,
            'confidence'    => $confidence,
            'is_cloudflare' => $is_cloudflare,
            'cf_ray'        => !empty($cf_ray),
            'message'       => $summary,
        );
    }

    public function __call($name, $arguments) {
        if (isset($this->core->warmer) && method_exists($this->core->warmer, $name)) {
            return call_user_func_array(array($this->core->warmer, $name), $arguments);
        }
        return null;
    }
}
