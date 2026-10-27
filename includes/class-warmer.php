<?php
/**
 * AstroPress Resilient Edge Cache Warmer & Background Worker
 *
 * Provides batched interactive cache pre-warming with live progress and metrics,
 * plus a WP-Cron powered non-blocking background worker with start, pause, resume, and cancel capabilities.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Warmer {
    /**
     * Master Plugin Instance
     *
     * @var AstroPress
     */
    protected $core;

    const OPTION_BG_JOB = 'astropress_warm_bg_job';
    const LEGACY_OPTION_BG_JOB = 'topnepali_warm_bg_job';

    public function __construct(AstroPress $core) {
        $this->core = $core;

        // Background Warmer WP-Cron Hook
        add_action('astropress_warm_bg_worker', array($this, 'run_background_worker'));
        add_action('topnepali_warm_bg_worker', array($this, 'run_background_worker'));

        // AJAX handlers (both AstroPress & legacy TopNepali)
        if (is_admin()) {
            $actions = array(
                'get_warmup_urls',
                'warm_batch',
                'start_bg_warm',
                'get_bg_warm_status',
                'pause_bg_warm',
                'resume_bg_warm',
                'cancel_bg_warm',
                'clear_bg_warm',
            );

            foreach ($actions as $act) {
                add_action("wp_ajax_astropress_{$act}", array($this, "ajax_{$act}"));
                add_action("wp_ajax_topnepali_{$act}", array($this, "ajax_{$act}"));
            }
        }
    }

    private function verify_request() {
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'astropress_admin_nonce') || wp_verify_nonce($_POST['nonce'], 'topnepali_admin_nonce')) {
                return current_user_can('manage_options');
            }
        }
        return false;
    }

    private function fetch_sitemap_xml($url) {
        $clean_url = trim($url);
        if (strpos($clean_url, 'http') !== 0) {
            $clean_url = home_url('/' . ltrim($clean_url, '/'));
        }

        $home_host = parse_url(home_url(), PHP_URL_HOST);
        $req_host  = parse_url($clean_url, PHP_URL_HOST);
        if ($home_host && $req_host && strtolower($home_host) !== strtolower($req_host)) {
            return false;
        }

        $res = wp_remote_get($clean_url, array(
            'timeout'             => 10,
            'sslverify'           => false,
            'reject_unsafe_urls'  => true,
            'headers'             => array(
                'User-Agent' => 'AstroPress-Sitemap-Crawler/1.0',
                'Accept'     => 'application/xml,text/xml,*/*',
            ),
        ));

        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($res);
        return (!empty($body) && strpos($body, '<loc>') !== false) ? $body : false;
    }

    public function get_sitemap_urls($sitemap_type = 'sitemap_crawler', $deep = true) {
        $candidate_paths = array();

        if ($sitemap_type === 'sitemap_posts') {
            $candidate_paths = array('/post-sitemap.xml', '/post-sitemap1.xml', '/wp-sitemap-posts-post-1.xml');
        } elseif ($sitemap_type === 'sitemap_pages') {
            $candidate_paths = array('/page-sitemap.xml', '/wp-sitemap-posts-page-1.xml');
        } elseif ($sitemap_type === 'sitemap_categories') {
            $candidate_paths = array('/category-sitemap.xml', '/wp-sitemap-taxonomies-category-1.xml');
        } else {
            $candidate_paths = array('/sitemap_index.xml', '/sitemap.xml', '/wp-sitemap.xml');
        }

        $xml_content = '';
        $matched_path = '';
        foreach ($candidate_paths as $p) {
            $body = $this->fetch_sitemap_xml($p);
            if ($body !== false) {
                $xml_content = $body;
                $matched_path = $p;
                break;
            }
        }

        $items = array();
        $seen = array();

        if (!empty($xml_content)) {
            $is_index = (stripos($xml_content, '<sitemapindex') !== false);
            preg_match_all('/<loc>\s*(https?:\/\/[^\s<]+)\s*<\/loc>/i', $xml_content, $matches);
            $locs = !empty($matches[1]) ? $matches[1] : array();

            if ($is_index && $sitemap_type === 'sitemap_files_only') {
                $clean_index = '/' . ltrim(parse_url(home_url($matched_path), PHP_URL_PATH), '/');
                $items[] = array('url' => $clean_index, 'title' => basename($clean_index) . ' (Sitemap Index)', 'type' => 'Sitemap XML');
                $seen[$clean_index] = true;

                foreach ($locs as $loc) {
                    $parsed = parse_url($loc, PHP_URL_PATH);
                    if ($parsed) {
                        $clean = '/' . ltrim($parsed, '/');
                        if (!isset($seen[$clean])) {
                            $seen[$clean] = true;
                            $items[] = array('url' => $clean, 'title' => basename($clean) . ' (Sub-Sitemap)', 'type' => 'Sitemap XML');
                        }
                    }
                }
            } elseif ($is_index && $deep) {
                foreach ($locs as $sub_loc) {
                    $sub_xml = $this->fetch_sitemap_xml($sub_loc);
                    if (!$sub_xml) continue;

                    preg_match_all('/<loc>\s*(https?:\/\/[^\s<]+)\s*<\/loc>/i', $sub_xml, $sub_matches);
                    if (!empty($sub_matches[1])) {
                        foreach ($sub_matches[1] as $page_loc) {
                            $parsed = parse_url($page_loc, PHP_URL_PATH);
                            if ($parsed) {
                                $clean = '/' . ltrim($parsed, '/');
                                if (!isset($seen[$clean])) {
                                    $seen[$clean] = true;
                                    $sub_name = basename(parse_url($sub_loc, PHP_URL_PATH));
                                    $items[] = array('url' => $clean, 'title' => trim($clean, '/'), 'type' => 'Page (' . $sub_name . ')');
                                }
                            }
                        }
                    }
                }
            } else {
                foreach ($locs as $loc) {
                    $parsed = parse_url($loc, PHP_URL_PATH);
                    if ($parsed) {
                        $clean = '/' . ltrim($parsed, '/');
                        if (!isset($seen[$clean])) {
                            $seen[$clean] = true;
                            $is_xml = (substr($clean, -4) === '.xml');
                            $items[] = array('url' => $clean, 'title' => trim($clean, '/'), 'type' => $is_xml ? 'Sitemap XML' : 'Sitemap URL');
                        }
                    }
                }
            }
        }

        if (empty($items)) {
            if ($sitemap_type === 'sitemap_posts' || $sitemap_type === 'sitemap_crawler') {
                global $wpdb;
                $posts = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY ID DESC LIMIT 100");
                foreach ($posts as $p) {
                    $items[] = array('url' => '/' . ltrim($p->post_name, '/'), 'title' => $p->post_title ?: $p->post_name, 'type' => 'Post');
                }
            }
            if ($sitemap_type === 'sitemap_pages' || $sitemap_type === 'sitemap_crawler') {
                global $wpdb;
                $pages = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' ORDER BY ID DESC LIMIT 50");
                foreach ($pages as $p) {
                    $items[] = array('url' => '/' . ltrim($p->post_name, '/'), 'title' => $p->post_title ?: $p->post_name, 'type' => 'Page');
                }
            }
            if ($sitemap_type === 'sitemap_categories' || $sitemap_type === 'sitemap_crawler') {
                $cats = get_categories(array('number' => 50, 'hide_empty' => true));
                foreach ($cats as $c) {
                    $items[] = array('url' => $this->core->cache->get_term_path($c), 'title' => $c->name . ' (Category)', 'type' => 'Category');
                }
            }
        }

        return $items;
    }

    public function get_warmup_urls($preset = 'standard') {
        global $wpdb;
        $items = array();
        $seen = array();

        if ($preset === 'all_posts') {
            $posts = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY ID DESC");
            foreach ($posts as $p) {
                $url = '/' . ltrim($p->post_name, '/');
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $p->post_title ?: $p->post_name, 'type' => 'Post');
                }
            }
            return $items;
        }

        if ($preset === 'all_pages') {
            $pages = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' ORDER BY ID DESC");
            foreach ($pages as $p) {
                $url = '/' . ltrim($p->post_name, '/');
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $p->post_title ?: $p->post_name, 'type' => 'Page');
                }
            }
            return $items;
        }

        if ($preset === 'all_taxonomies') {
            $cats = get_categories(array('hide_empty' => true));
            foreach ($cats as $c) {
                $url = $this->core->cache->get_term_path($c);
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $c->name . ' (Category)', 'type' => 'Category');
                }
            }
            $tags = get_tags(array('hide_empty' => true));
            foreach ($tags as $t) {
                $url = '/tag/' . ltrim($t->slug, '/');
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $t->name . ' (Tag)', 'type' => 'Tag');
                }
            }
            return $items;
        }

        if ($preset === 'entire_site') {
            $items[] = array('url' => '/', 'title' => 'Homepage', 'type' => 'Core');
            $items[] = array('url' => '/blogs', 'title' => 'Blogs Archive', 'type' => 'Core');
            $items[] = array('url' => '/blog', 'title' => 'Blog Index', 'type' => 'Core');
            $items[] = array('url' => '/rss.xml', 'title' => 'RSS Feed', 'type' => 'Feed');
            $items[] = array('url' => '/feed.xml', 'title' => 'Atom/XML Feed', 'type' => 'Feed');
            $items[] = array('url' => '/sitemap.xml', 'title' => 'Sitemap Index', 'type' => 'SEO');
            $seen['/'] = $seen['/blogs'] = $seen['/blog'] = $seen['/rss.xml'] = $seen['/feed.xml'] = $seen['/sitemap.xml'] = true;

            $pages = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' ORDER BY ID DESC");
            foreach ($pages as $p) {
                $url = '/' . ltrim($p->post_name, '/');
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $p->post_title ?: $p->post_name, 'type' => 'Page');
                }
            }

            $cats = get_categories(array('hide_empty' => true));
            foreach ($cats as $c) {
                $url = $this->core->cache->get_term_path($c);
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $c->name . ' (Category)', 'type' => 'Category');
                }
            }

            $posts = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' ORDER BY ID DESC");
            foreach ($posts as $p) {
                $url = '/' . ltrim($p->post_name, '/');
                if (!isset($seen[$url])) {
                    $seen[$url] = true;
                    $items[] = array('url' => $url, 'title' => $p->post_title ?: $p->post_name, 'type' => 'Post');
                }
            }

            return apply_filters('astropress_warmup_urls', $items, $preset);
        }

        if ($preset === 'sitemap_files_only') {
            return $this->get_sitemap_urls('sitemap_files_only', false);
        }
        if ($preset === 'sitemap_crawler') {
            return $this->get_sitemap_urls('sitemap_crawler', true);
        }
        if (strpos($preset, 'sitemap_') === 0) {
            return $this->get_sitemap_urls($preset, true);
        }

        // Standard and Deep Presets
        $items[] = array('url' => '/', 'title' => 'Homepage', 'type' => 'Core');
        $items[] = array('url' => '/blogs', 'title' => 'Blogs Archive', 'type' => 'Core');
        $items[] = array('url' => '/blog', 'title' => 'Blog Index', 'type' => 'Core');
        $items[] = array('url' => '/rss.xml', 'title' => 'RSS Feed', 'type' => 'Feed');
        $items[] = array('url' => '/sitemap.xml', 'title' => 'Sitemap Index', 'type' => 'SEO');
        $seen['/'] = $seen['/blogs'] = $seen['/blog'] = $seen['/rss.xml'] = $seen['/sitemap.xml'] = true;

        $cats = get_categories(array('number' => ($preset === 'deep' ? 25 : 10), 'hide_empty' => true));
        foreach ($cats as $c) {
            $url = $this->core->cache->get_term_path($c);
            if (!isset($seen[$url])) {
                $seen[$url] = true;
                $items[] = array('url' => $url, 'title' => $c->name . ' (Category)', 'type' => 'Category');
            }
        }

        $post_count = ($preset === 'deep') ? 50 : 20;
        $posts = get_posts(array(
            'numberposts' => $post_count,
            'post_status' => 'publish',
            'post_type'   => 'post',
        ));
        foreach ($posts as $p) {
            $url = '/' . ltrim($p->post_name, '/');
            if (!isset($seen[$url])) {
                $seen[$url] = true;
                $items[] = array('url' => $url, 'title' => $p->post_title ?: $p->post_name, 'type' => 'Post');
            }
        }

        return apply_filters('astropress_warmup_urls', $items, $preset);
    }

    public function warm_url_batch($urls = array()) {
        if (empty($urls)) {
            return array();
        }

        $frontend_url = $this->core->get_frontend_url();
        $results = array();

        foreach ($urls as $item) {
            $path = is_array($item) ? ($item['url'] ?? '/') : (string) $item;
            $full_url = (strpos($path, 'http') === 0) ? $path : rtrim($frontend_url, '/') . '/' . ltrim($path, '/');

            $start_time = microtime(true);
            $response = wp_remote_get($full_url, array(
                'timeout'     => 12,
                'blocking'    => true,
                'sslverify'   => true,
                'headers'     => array(
                    'User-Agent' => 'AstroPress-Cache-Warmer/1.0',
                    'Accept'     => 'text/html,application/xhtml+xml,application/xml',
                ),
            ));
            $duration = round((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                $results[] = array(
                    'url'         => $path,
                    'full_url'    => $full_url,
                    'code'        => 0,
                    'duration_ms' => $duration,
                    'cf_cache'    => 'ERR',
                    'success'     => false,
                    'message'     => $response->get_error_message(),
                );
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $cf_cache = wp_remote_retrieve_header($response, 'cf-cache-status') ?: (wp_remote_retrieve_header($response, 'x-cache') ?: 'UNKNOWN');
                $results[] = array(
                    'url'         => $path,
                    'full_url'    => $full_url,
                    'code'        => $code,
                    'duration_ms' => $duration,
                    'cf_cache'    => strtoupper($cf_cache),
                    'success'     => ($code >= 200 && $code < 400),
                    'message'     => "Status: {$code} ({$duration}ms) [CF: {$cf_cache}]",
                );
            }
        }

        return $results;
    }

    public function start_background_job($preset = 'standard', $delay_ms = 1000, $batch_size = 2) {
        $items = $this->get_warmup_urls($preset);
        if (empty($items)) {
            return new WP_Error('empty_queue', 'No URLs found for the selected scope.');
        }

        $job = array(
            'status'     => 'running',
            'scope'      => $preset,
            'delay_ms'   => max(200, (int)$delay_ms),
            'batch_size' => max(1, min(10, (int)$batch_size)),
            'total'      => count($items),
            'processed'  => 0,
            'succeeded'  => 0,
            'failed'     => 0,
            'queue'      => $items,
            'log'        => array(
                array(
                    'time'    => current_time('H:i:s'),
                    'url'     => '— System Initialized —',
                    'meta'    => 'Background Job Started: ' . count($items) . ' URLs (' . $preset . ') | Delay: ' . $delay_ms . 'ms',
                    'success' => true,
                ),
            ),
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        update_option(self::OPTION_BG_JOB, $job);
        update_option(self::LEGACY_OPTION_BG_JOB, $job);

        wp_clear_scheduled_hook('astropress_warm_bg_worker');
        wp_clear_scheduled_hook('topnepali_warm_bg_worker');
        wp_schedule_single_event(time(), 'astropress_warm_bg_worker');

        if (function_exists('spawn_cron')) {
            spawn_cron();
        }

        return $job;
    }

    public function run_background_worker() {
        $job = get_option(self::OPTION_BG_JOB, null);
        if (empty($job)) {
            $job = get_option(self::LEGACY_OPTION_BG_JOB, array());
        }

        if (empty($job) || !is_array($job) || empty($job['status']) || $job['status'] !== 'running') {
            return;
        }

        $batch_size = max(1, min(10, (int)($job['batch_size'] ?? 2)));
        $queue      = !empty($job['queue']) && is_array($job['queue']) ? $job['queue'] : array();

        if (empty($queue)) {
            $job['status']       = 'completed';
            $job['completed_at'] = current_time('mysql');
            $job['updated_at']   = current_time('mysql');
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => '— All Batches Finished Successfully —',
                'meta'    => 'Total: ' . ($job['processed'] ?? 0) . ' URLs pre-warmed',
                'success' => true,
            );
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
            return;
        }

        $batch = array_splice($queue, 0, $batch_size);
        $job['queue'] = $queue;

        $results = $this->warm_url_batch($batch);

        foreach ($results as $res) {
            $job['processed'] = ($job['processed'] ?? 0) + 1;
            if (!empty($res['success'])) {
                $job['succeeded'] = ($job['succeeded'] ?? 0) + 1;
            } else {
                $job['failed'] = ($job['failed'] ?? 0) + 1;
            }
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => $res['url'],
                'meta'    => ($res['code'] ?: 'ERR') . ' (' . $res['duration_ms'] . 'ms) [CF: ' . ($res['cf_cache'] ?: 'NONE') . ']',
                'success' => !empty($res['success']),
            );
        }

        if (count($job['log']) > 100) {
            $job['log'] = array_slice($job['log'], -100);
        }

        $job['updated_at'] = current_time('mysql');

        if (empty($job['queue']) || $job['processed'] >= $job['total']) {
            $job['status']       = 'completed';
            $job['completed_at'] = current_time('mysql');
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => '— All Batches Finished Successfully —',
                'meta'    => 'Total: ' . $job['processed'] . ' URLs pre-warmed',
                'success' => true,
            );
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
        } else {
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
            $delay_seconds = max(1, (int) ceil(($job['delay_ms'] ?? 1000) / 1000));
            wp_schedule_single_event(time() + $delay_seconds, 'astropress_warm_bg_worker');
            if (function_exists('spawn_cron')) {
                spawn_cron();
            }
        }
    }

    public function get_background_job_status() {
        $job = get_option(self::OPTION_BG_JOB, null);
        if (empty($job)) {
            $job = get_option(self::LEGACY_OPTION_BG_JOB, null);
        }
        if (empty($job) || !is_array($job)) {
            return null;
        }

        return array(
            'status'     => $job['status'] ?? 'idle',
            'scope'      => $job['scope'] ?? '',
            'total'      => (int) ($job['total'] ?? 0),
            'processed'  => (int) ($job['processed'] ?? 0),
            'succeeded'  => (int) ($job['succeeded'] ?? 0),
            'failed'     => (int) ($job['failed'] ?? 0),
            'remaining'  => count($job['queue'] ?? array()),
            'delay_ms'   => (int) ($job['delay_ms'] ?? 1000),
            'batch_size' => (int) ($job['batch_size'] ?? 2),
            'started_at' => $job['started_at'] ?? '',
            'updated_at' => $job['updated_at'] ?? '',
            'log'        => array_slice($job['log'] ?? array(), -30),
        );
    }

    public function pause_background_job() {
        $job = get_option(self::OPTION_BG_JOB, null);
        if (empty($job)) $job = get_option(self::LEGACY_OPTION_BG_JOB, null);

        if ($job && is_array($job) && ($job['status'] ?? '') === 'running') {
            $job['status']     = 'paused';
            $job['updated_at'] = current_time('mysql');
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => '— System Paused —',
                'meta'    => 'Background job paused by administrator',
                'success' => false,
            );
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
            wp_clear_scheduled_hook('astropress_warm_bg_worker');
            wp_clear_scheduled_hook('topnepali_warm_bg_worker');
            return true;
        }
        return false;
    }

    public function resume_background_job() {
        $job = get_option(self::OPTION_BG_JOB, null);
        if (empty($job)) $job = get_option(self::LEGACY_OPTION_BG_JOB, null);

        if ($job && is_array($job) && ($job['status'] ?? '') === 'paused') {
            $job['status']     = 'running';
            $job['updated_at'] = current_time('mysql');
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => '— System Resumed —',
                'meta'    => 'Background job resumed by administrator',
                'success' => true,
            );
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
            wp_clear_scheduled_hook('astropress_warm_bg_worker');
            wp_schedule_single_event(time(), 'astropress_warm_bg_worker');
            if (function_exists('spawn_cron')) {
                spawn_cron();
            }
            return true;
        }
        return false;
    }

    public function cancel_background_job() {
        $job = get_option(self::OPTION_BG_JOB, null);
        if (empty($job)) $job = get_option(self::LEGACY_OPTION_BG_JOB, null);

        if ($job && is_array($job)) {
            $job['status']       = 'cancelled';
            $job['cancelled_at'] = current_time('mysql');
            $job['updated_at']   = current_time('mysql');
            $job['log'][] = array(
                'time'    => current_time('H:i:s'),
                'url'     => '— Job Cancelled —',
                'meta'    => 'Background warming terminated by administrator',
                'success' => false,
            );
            update_option(self::OPTION_BG_JOB, $job);
            update_option(self::LEGACY_OPTION_BG_JOB, $job);
            wp_clear_scheduled_hook('astropress_warm_bg_worker');
            wp_clear_scheduled_hook('topnepali_warm_bg_worker');
            return true;
        }
        return false;
    }

    public function clear_background_job() {
        wp_clear_scheduled_hook('astropress_warm_bg_worker');
        wp_clear_scheduled_hook('topnepali_warm_bg_worker');
        delete_option(self::LEGACY_OPTION_BG_JOB);
        return delete_option(self::OPTION_BG_JOB);
    }

    // AJAX Handlers
    public function ajax_get_warmup_urls() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }

        $preset = sanitize_key($_POST['preset'] ?? 'standard');
        $urls = $this->get_warmup_urls($preset);

        wp_send_json_success(array(
            'total' => count($urls),
            'urls'  => $urls,
        ));
    }

    public function ajax_warm_batch() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }

        $urls = isset($_POST['urls']) ? (array) $_POST['urls'] : array();
        if (empty($urls)) {
            wp_send_json_error(array('message' => 'No URLs provided in batch.'));
        }

        $results = $this->warm_url_batch($urls);
        wp_send_json_success(array('results' => $results));
    }

    public function ajax_start_bg_warm() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }

        $preset     = sanitize_key($_POST['preset'] ?? 'standard');
        $delay_ms   = max(200, (int) ($_POST['delay_ms'] ?? 1000));
        $batch_size = max(1, min(10, (int) ($_POST['batch_size'] ?? 2)));

        $res = $this->start_background_job($preset, $delay_ms, $batch_size);
        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()));
        }

        wp_send_json_success($this->get_background_job_status());
    }

    public function ajax_get_bg_warm_status() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }

        $status = $this->get_background_job_status();
        wp_send_json_success($status ?: array('status' => 'idle'));
    }

    public function ajax_pause_bg_warm() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }
        $this->pause_background_job();
        wp_send_json_success($this->get_background_job_status());
    }

    public function ajax_resume_bg_warm() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }
        $this->resume_background_job();
        wp_send_json_success($this->get_background_job_status());
    }

    public function ajax_cancel_bg_warm() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }
        $this->cancel_background_job();
        wp_send_json_success($this->get_background_job_status());
    }

    public function ajax_clear_bg_warm() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }
        $this->clear_background_job();
        wp_send_json_success(array('status' => 'idle'));
    }
}
