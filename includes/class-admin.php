<?php
/**
 * AstroPress Admin Dashboard, Settings & Controls
 *
 * Provides a unified tabbed interface for:
 * - Overview & System Status
 * - Headless Bridge & Cache Invalidation
 * - Edge Cache Warmer (Interactive & Background)
 * - Cloudflare R2 Credentials & CDN Delivery
 * - Image Optimization (WebP/AVIF, reSmush.it, GD/Imagick)
 * - Media Sync & Rollback
 * - GitHub Updates & Version Switcher
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Admin {
    /**
     * Core orchestrator reference
     *
     * @var AstroPress
     */
    protected $core;

    /**
     * Constructor
     *
     * @param AstroPress $core
     */
    public function __construct(AstroPress $core) {
        $this->core = $core;

        if (is_admin()) {
            add_action('admin_menu', array($this, 'register_admin_menu'));
            add_action('admin_init', array($this, 'handle_save_settings'));
            add_action('admin_init', array($this, 'handle_admin_actions'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('enqueue_block_editor_assets', array($this, 'on_enqueue_block_editor_assets'));
            add_action('wp_enqueue_media', array($this, 'on_wp_enqueue_media'));
            add_filter('wp_client_side_media_processing_enabled', array($this, 'filter_core_client_side_media_processing'), 100);

            // AJAX Handlers for Headless & Cache Warmer
            add_action('wp_ajax_astropress_test_key', array($this, 'ajax_test_key'));
            add_action('wp_ajax_topnepali_test_key', array($this, 'ajax_test_key'));
            add_action('wp_ajax_astropress_get_warmup_urls', array($this, 'ajax_get_warmup_urls'));
            add_action('wp_ajax_topnepali_get_warmup_urls', array($this, 'ajax_get_warmup_urls'));
            add_action('wp_ajax_astropress_warm_batch', array($this, 'ajax_warm_batch'));
            add_action('wp_ajax_topnepali_warm_batch', array($this, 'ajax_warm_batch'));
            add_action('wp_ajax_astropress_start_bg_warm', array($this, 'ajax_start_bg_warm'));
            add_action('wp_ajax_topnepali_start_bg_warm', array($this, 'ajax_start_bg_warm'));
            add_action('wp_ajax_astropress_get_bg_warm_status', array($this, 'ajax_get_bg_warm_status'));
            add_action('wp_ajax_topnepali_get_bg_warm_status', array($this, 'ajax_get_bg_warm_status'));
            add_action('wp_ajax_astropress_pause_bg_warm', array($this, 'ajax_pause_bg_warm'));
            add_action('wp_ajax_topnepali_pause_bg_warm', array($this, 'ajax_pause_bg_warm'));
            add_action('wp_ajax_astropress_resume_bg_warm', array($this, 'ajax_resume_bg_warm'));
            add_action('wp_ajax_topnepali_resume_bg_warm', array($this, 'ajax_resume_bg_warm'));
            add_action('wp_ajax_astropress_cancel_bg_warm', array($this, 'ajax_cancel_bg_warm'));
            add_action('wp_ajax_topnepali_cancel_bg_warm', array($this, 'ajax_cancel_bg_warm'));
            add_action('wp_ajax_astropress_clear_bg_warm', array($this, 'ajax_clear_bg_warm'));
            add_action('wp_ajax_topnepali_clear_bg_warm', array($this, 'ajax_clear_bg_warm'));

            // AJAX Handlers for Cloudflare R2 & Image Compression
            add_action('wp_ajax_astropress_test_connection', array($this, 'ajax_test_connection'));
            add_action('wp_ajax_r2g_test_connection', array($this, 'ajax_test_connection'));
            add_action('wp_ajax_astropress_fetch_buckets', array($this, 'ajax_fetch_buckets'));
            add_action('wp_ajax_r2g_fetch_buckets', array($this, 'ajax_fetch_buckets'));
            add_action('wp_ajax_astropress_preview_compression', array($this, 'ajax_preview_compression'));
            add_action('wp_ajax_r2g_preview_compression', array($this, 'ajax_preview_compression'));

            // Security Notices
            add_action('admin_notices', array($this, 'render_security_notices'));
        }

        // Admin Bar Quick Purge & Sync Menu
        add_action('admin_bar_menu', array($this, 'register_admin_bar_menu'), 99);
        add_action('admin_init', array($this, 'handle_admin_bar_purge'));
    }

    /**
     * Singleton instance accessor
     *
     * @return AstroPress_Admin
     */
    public static function instance() {
        return astropress()->admin;
    }

    /**
     * Register Admin Menu & Submenus
     */
    public function register_admin_menu() {
        add_menu_page(
            'AstroPress',
            'AstroPress',
            'manage_options',
            'astropress',
            array($this, 'render_settings_page'),
            'dashicons-cloud',
            30
        );

        add_submenu_page(
            'astropress',
            'Overview — AstroPress',
            'Overview',
            'manage_options',
            'astropress',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Headless Bridge — AstroPress',
            'Headless Bridge',
            'manage_options',
            'astropress&tab=bridge',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Cache Warmer — AstroPress',
            'Cache Warmer',
            'manage_options',
            'astropress&tab=warmer',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Cloudflare R2 — AstroPress',
            'Cloudflare R2',
            'manage_options',
            'astropress&tab=r2',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Image Optimizer — AstroPress',
            'Image Optimizer',
            'manage_options',
            'astropress&tab=optimizer',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Media Sync — AstroPress',
            'Media Sync',
            'manage_options',
            'astropress&tab=sync',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Updates & Rollback — AstroPress',
            'Updates & Rollback',
            'manage_options',
            'astropress&tab=updates',
            array($this, 'render_settings_page')
        );

        // Backward compatibility redirect menus for legacy URLs
        add_submenu_page(null, 'R2 by Grisma', 'R2 by Grisma', 'manage_options', 'r2-by-grisma', array($this, 'redirect_to_r2_tab'));
        add_submenu_page(null, 'Headless Setup', 'Headless Setup', 'manage_options', 'topnepali-headless', array($this, 'redirect_to_bridge_tab'));
    }

    public function redirect_to_r2_tab() {
        wp_safe_redirect(admin_url('admin.php?page=astropress&tab=r2'));
        exit;
    }

    public function redirect_to_bridge_tab() {
        wp_safe_redirect(admin_url('admin.php?page=astropress&tab=bridge'));
        exit;
    }

    /**
     * Enqueue Admin Assets (CSS & JS)
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        $is_astropress_page = (strpos($hook, 'astropress') !== false || strpos($hook, 'r2-by-grisma') !== false || strpos($hook, 'topnepali-headless') !== false);
        $is_media_page = ($hook === 'upload.php' || $hook === 'media-new.php');

        if (!$is_astropress_page && !$is_media_page) {
            return;
        }

        wp_enqueue_style(
            'astropress-admin-css',
            ASTROPRESS_URL . 'assets/css/admin.css',
            array(),
            ASTROPRESS_VERSION
        );

        wp_enqueue_script(
            'astropress-admin-js',
            ASTROPRESS_URL . 'assets/js/admin.js',
            array('jquery'),
            ASTROPRESS_VERSION,
            true
        );

        // Compression config for upload manager & preview
        $compress_config = array(
            'engine'       => AstroPress_Optimizer::get_engine(),
            'storageMode'  => AstroPress_Media_Handler::get_storage_mode(),
            'interceptor'  => (int) get_option('r2g_interceptor_enabled', 1),
            'format'       => get_option('r2g_compress_format', 'webp'),
            'compress'     => (int) get_option('r2g_compress_enabled', 1),
            'quality'      => (int) get_option('r2g_compress_quality', 82),
            'maxWidth'     => (int) get_option('r2g_max_width', 1920),
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('astropress_admin_nonce'),
        );

        wp_localize_script('astropress-admin-js', 'astropressConfig', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('astropress_admin_nonce'),
            'legacyNonce'  => wp_create_nonce('topnepali_admin_nonce'),
            'r2Nonce'      => wp_create_nonce('r2g_admin_nonce'),
            'frontendUrl'  => $this->core->get_frontend_url(),
            'defaultDelay' => $this->core->get_warm_delay(),
            'defaultBatch' => $this->core->get_warm_batch_size(),
            'version'      => ASTROPRESS_VERSION,
            'compress'     => $compress_config,
        ));

        // Legacy compatibility objects for scripts expecting topnepaliAdmin or r2g_compress_config
        wp_localize_script('astropress-admin-js', 'topnepaliAdmin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('topnepali_admin_nonce'),
            'frontendUrl' => $this->core->get_frontend_url(),
            'defaultDelay'=> $this->core->get_warm_delay(),
            'defaultBatch'=> $this->core->get_warm_batch_size(),
        ));
        wp_localize_script('astropress-admin-js', 'r2g_compress_config', $compress_config);

        // Enqueue Upload Interceptor if on media screens or settings
        if ((int) get_option('r2g_interceptor_enabled', 1) === 1 || $is_astropress_page) {
            wp_enqueue_script(
                'astropress-upload-interceptor',
                ASTROPRESS_URL . 'assets/js/upload-interceptor.js',
                array('jquery'),
                ASTROPRESS_VERSION,
                true
            );
        }
    }

    public function filter_core_client_side_media_processing($enabled) {
        return (int) get_option('r2g_interceptor_enabled', 1) === 1 ? false : $enabled;
    }

    public function on_enqueue_block_editor_assets() {
        $this->enqueue_admin_assets('post.php');
    }

    public function on_wp_enqueue_media() {
        $this->enqueue_admin_assets('upload.php');
    }

    /**
     * Warn administrators about weak credential encryption or server capabilities
     */
    public function render_security_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->id, 'astropress') === false) {
            return;
        }

        if (!AstroPress_Encryption::is_available()) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>AstroPress:</strong> Neither OpenSSL nor Sodium extension is available in PHP. Cloudflare R2 Secret Keys will not be encrypted at rest.</p></div>';
        } elseif (!AstroPress_Encryption::has_strong_salts()) {
            echo '<div class="notice notice-warning is-dismissible"><p><strong>AstroPress:</strong> AUTH_KEY / SECURE_AUTH_KEY in wp-config.php are empty or default. Define unique security keys in wp-config.php for maximum encryption security.</p></div>';
        }
    }

    /**
     * Register Admin Bar Quick Actions
     *
     * @param WP_Admin_Bar $admin_bar
     */
    public function register_admin_bar_menu($admin_bar) {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $title = '<span class="ab-icon dashicons dashicons-cloud"></span><span class="ab-label">AstroPress</span>';

        $admin_bar->add_node(array(
            'id'    => 'astropress-menu',
            'title' => $title,
            'href'  => admin_url('admin.php?page=astropress'),
            'meta'  => array('title' => 'AstroPress Headless & R2 Controls'),
        ));

        // Purge current post if editing or viewing
        $current_post_id = 0;
        if (is_admin()) {
            global $post;
            if ($post && !empty($post->ID)) {
                $current_post_id = $post->ID;
            }
        } elseif (is_singular()) {
            $current_post_id = get_queried_object_id();
        }

        if ($current_post_id) {
            $nonce_url = wp_nonce_url(
                admin_url('admin-post.php?action=astropress_purge_single&post_id=' . $current_post_id),
                'astropress_purge_single_nonce'
            );
            $admin_bar->add_node(array(
                'id'     => 'astropress-purge-current',
                'parent' => 'astropress-menu',
                'title'  => 'Purge Current Page Cache',
                'href'   => $nonce_url,
            ));
        }

        // Global Edge Purge
        $nonce_all_url = wp_nonce_url(
            admin_url('admin-post.php?action=astropress_purge_all'),
            'astropress_purge_all_nonce'
        );
        $admin_bar->add_node(array(
            'id'     => 'astropress-purge-all',
            'parent' => 'astropress-menu',
            'title'  => 'Purge All Edge Cache',
            'href'   => $nonce_all_url,
        ));

        // Edge Cache Warmer
        $admin_bar->add_node(array(
            'id'     => 'astropress-warm-menu',
            'parent' => 'astropress-menu',
            'title'  => 'Warm Edge Cache',
            'href'   => admin_url('admin.php?page=astropress&tab=warmer'),
        ));

        // R2 Media Sync
        $admin_bar->add_node(array(
            'id'     => 'astropress-sync-menu',
            'parent' => 'astropress-menu',
            'title'  => 'Cloudflare R2 Sync',
            'href'   => admin_url('admin.php?page=astropress&tab=sync'),
        ));
    }

    /**
     * Handle Admin Bar Quick Purge Actions
     */
    public function handle_admin_bar_purge() {
        if (!is_user_logged_in()) {
            return;
        }

        // Single post purge
        if (isset($_GET['action']) && ($_GET['action'] === 'astropress_purge_single' || $_GET['action'] === 'topnepali_purge_single') && isset($_GET['post_id'])) {
            $post_id = intval($_GET['post_id']);
            if (!current_user_can('edit_post', $post_id)) {
                wp_die(esc_html__('Unauthorized.', 'astropress'), 403);
            }
            $post = get_post($post_id);
            if ($post) {
                delete_post_meta($post_id, '_astropress_rm_head');
                delete_post_meta($post_id, '_headless_rm_head');
                $this->core->cache->dispatch_revalidation($post->post_name, $post->post_type, 'update');
            }
            wp_safe_redirect(add_query_arg('astropress_msg', 'purged_single', wp_get_referer() ?: admin_url()));
            exit;
        }

        // Global edge cache purge
        if (isset($_GET['action']) && ($_GET['action'] === 'astropress_purge_all' || $_GET['action'] === 'topnepali_purge_all')) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Unauthorized.', 'astropress'), 403);
            }
            $this->core->cache->dispatch_revalidation(null, 'all', 'purge', array('/', '/sitemap.xml', '/rss.xml'));
            wp_safe_redirect(add_query_arg('astropress_msg', 'purged_all', wp_get_referer() ?: admin_url()));
            exit;
        }
    }

    /**
     * Handle Admin Form Submissions (Headless Bridge, Automation, Cloudflare R2, Optimizer)
     */
    public function handle_save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // 1. Save Headless Bridge Connection
        if (isset($_POST['astropress_save_bridge']) && check_admin_referer('astropress_bridge_action', 'astropress_bridge_nonce')) {
            $frontend_url = esc_url_raw(rtrim(trim(wp_unslash($_POST['astropress_frontend_url'] ?? '')), '/'));
            $new_secret   = sanitize_text_field(trim(wp_unslash($_POST['astropress_secret'] ?? '')));

            if (!empty($frontend_url)) {
                update_option(AstroPress::OPTION_FRONTEND_URL, $frontend_url);
                update_option('topnepali_headless_frontend_url', $frontend_url);
            }
            if (!empty($new_secret)) {
                update_option(AstroPress::OPTION_SECRET, $new_secret);
                update_option('topnepali_headless_secret', $new_secret);
            }

            // Test Handshake automatically
            $secret = !empty($new_secret) ? $new_secret : $this->core->get_secret();
            $connected = false;

            if (!empty($frontend_url) && !empty($secret)) {
                $test_res = wp_remote_post($frontend_url . '/api/revalidate', array(
                    'timeout'   => 8,
                    'sslverify' => true,
                    'headers'   => array(
                        'Content-Type'        => 'application/json',
                        'Origin'              => $frontend_url,
                        'x-revalidate-secret' => $secret,
                    ),
                    'body' => wp_json_encode(array('slug' => 'test-handshake', 'type' => 'test', 'action' => 'ping')),
                ));
                if (!is_wp_error($test_res) && wp_remote_retrieve_response_code($test_res) === 200) {
                    $connected = true;
                }
            }

            update_option(AstroPress::OPTION_CONNECTED, $connected ? '1' : '0');
            update_option('topnepali_headless_connected', $connected ? '1' : '0');

            set_transient('astropress_notice', array(
                'type'    => $connected ? 'success' : 'warning',
                'message' => $connected
                    ? esc_html__('Connected! Headless bridge handshake verified with Cloudflare Pages/Workers.', 'astropress')
                    : esc_html__('Settings saved, but handshake test could not reach frontend /api/revalidate.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=bridge'));
            exit;
        }

        // 2. Save Automation & Cache Preferences
        if (isset($_POST['astropress_save_automation']) && check_admin_referer('astropress_automation_action', 'astropress_automation_nonce')) {
            update_option(AstroPress::OPTION_PURGE_ON_SAVE, isset($_POST['purge_on_save']) ? '1' : '0');
            update_option(AstroPress::OPTION_PURGE_FEEDS, isset($_POST['purge_feeds']) ? '1' : '0');
            update_option(AstroPress::OPTION_PURGE_TAXONOMY, isset($_POST['purge_taxonomy']) ? '1' : '0');
            update_option(AstroPress::OPTION_PURGE_MENU, isset($_POST['purge_menu']) ? '1' : '0');
            update_option(AstroPress::OPTION_LSCWP_SYNC, isset($_POST['lscwp_sync']) ? '1' : '0');
            update_option(AstroPress::OPTION_WARM_DELAY, max(200, absint($_POST['warm_delay'] ?? 1000)));
            update_option(AstroPress::OPTION_WARM_BATCH_SIZE, max(1, min(10, absint($_POST['warm_batch_size'] ?? 2))));
            update_option(AstroPress::OPTION_REWRITE_LINKS, isset($_POST['rewrite_links']) ? '1' : '0');
            update_option(AstroPress::OPTION_SUBDOMAIN_SHIELD, isset($_POST['subdomain_shield']) ? '1' : '0');
            update_option(AstroPress::OPTION_SUBDOMAIN_NOINDEX, isset($_POST['subdomain_noindex']) ? '1' : '0');

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Automation & Cache rules saved successfully.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=bridge'));
            exit;
        }

        // 3. Save Cloudflare R2 Credentials & CDN Options
        if (isset($_POST['astropress_save_r2']) && check_admin_referer('astropress_r2_action', 'astropress_r2_nonce')) {
            $account_id    = sanitize_text_field(wp_unslash($_POST['r2_account_id'] ?? ''));
            $access_key    = sanitize_text_field(wp_unslash($_POST['r2_access_key'] ?? ''));
            $posted_secret = trim(wp_unslash($_POST['r2_secret_key'] ?? ''));
            $bucket        = sanitize_text_field(wp_unslash($_POST['r2_bucket'] ?? ''));
            $custom_domain = sanitize_text_field(wp_unslash($_POST['r2_custom_domain'] ?? ''));

            update_option('r2g_account_id', $account_id);
            update_option('astropress_r2_account_id', $account_id);
            update_option('r2g_access_key', $access_key);
            update_option('astropress_r2_access_key', $access_key);

            if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
                $encrypted_secret = AstroPress_Encryption::encrypt($posted_secret);
                update_option('r2g_secret_key', $encrypted_secret);
                update_option('astropress_r2_secret_key', $encrypted_secret);
            }

            update_option('r2g_bucket', $bucket);
            update_option('astropress_r2_bucket', $bucket);

            $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
            update_option('r2g_custom_domain', $clean_domain);
            update_option('astropress_r2_custom_domain', $clean_domain);

            // Storage & Upload Policies
            update_option('r2g_auto_upload', !empty($_POST['r2_auto_upload']) ? 1 : 0);
            update_option('r2g_rewrite_urls', !empty($_POST['r2_rewrite_urls']) ? 1 : 0);
            update_option('r2g_delete_from_r2', !empty($_POST['r2_delete_from_r2']) ? 1 : 0);

            $storage_mode = sanitize_key(wp_unslash($_POST['r2_storage_mode'] ?? 'both'));
            if (!in_array($storage_mode, array('both', 'r2_only', 'local_only'), true)) {
                $storage_mode = 'both';
            }
            update_option('r2g_storage_mode', $storage_mode);

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Cloudflare R2 configuration saved successfully.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=r2'));
            exit;
        }

        // 4. Save Image Optimizer Settings
        if (isset($_POST['astropress_save_optimizer']) && check_admin_referer('astropress_optimizer_action', 'astropress_optimizer_nonce')) {
            update_option('r2g_compress_enabled', !empty($_POST['compress_enabled']) ? 1 : 0);
            update_option('r2g_compress_format', sanitize_text_field(wp_unslash($_POST['compress_format'] ?? 'webp')));
            update_option('r2g_compress_quality', max(50, min(100, (int)($_POST['compress_quality'] ?? 82))));
            update_option('r2g_compress_engine', AstroPress_Optimizer::normalize_engine(sanitize_key(wp_unslash($_POST['compress_engine'] ?? 'resmush'))));
            update_option('r2g_max_width', max(0, (int)($_POST['max_width'] ?? 1920)));
            update_option('r2g_strip_exif', !empty($_POST['strip_exif']) ? 1 : 0);
            update_option('r2g_interceptor_enabled', !empty($_POST['interceptor_enabled']) ? 1 : 0);
            update_option('r2g_upload_workflow', sanitize_key(wp_unslash($_POST['upload_workflow'] ?? 'bar')));

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Image Optimizer preferences updated.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=optimizer'));
            exit;
        }
    }

    /**
     * Handle Manual Edge Purges from Admin Page Buttons
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Purge specific URL
        if (isset($_POST['astropress_purge_custom_url']) && check_admin_referer('astropress_purge_custom_action', 'astropress_purge_custom_nonce')) {
            $raw_url = trim(wp_unslash($_POST['astropress_custom_url'] ?? ''));
            if (!empty($raw_url)) {
                $path = parse_url($raw_url, PHP_URL_PATH) ?: $raw_url;
                $clean_path = '/' . ltrim($path, '/');
                $this->core->cache->dispatch_revalidation(null, 'custom', 'purge', array($clean_path));
                set_transient('astropress_notice', array(
                    'type'    => 'success',
                    'message' => sprintf(esc_html__('Dispatched edge purge for: %s', 'astropress'), esc_html($clean_path)),
                ), 30);
            }
            wp_safe_redirect(admin_url('admin.php?page=astropress'));
            exit;
        }

        // Global Edge Purge
        if (isset($_POST['astropress_purge_global']) && check_admin_referer('astropress_purge_global_action', 'astropress_purge_global_nonce')) {
            $this->core->cache->dispatch_revalidation(null, 'all', 'purge', array('/', '/sitemap.xml', '/rss.xml'));
            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Triggered global edge cache invalidation across all Cloudflare POPs.', 'astropress'),
            ), 30);
            wp_safe_redirect(admin_url('admin.php?page=astropress'));
            exit;
        }
    }

    /**
     * AJAX: Test existing stored Revalidation Secret Key against Cloudflare Pages/Workers
     */
    public function ajax_test_key() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized permissions.'), 403);
        }

        $frontend_url = $this->core->get_frontend_url();
        $secret       = $this->core->get_secret();

        if (empty($frontend_url) || empty($secret)) {
            wp_send_json_error(array(
                'message' => 'Credentials not found in WordPress database. Please configure a new key.',
                'code'    => 404,
            ));
        }

        $test_endpoint = $frontend_url . '/api/revalidate';
        $start_time    = microtime(true);
        $response      = wp_remote_post($test_endpoint, array(
            'timeout'   => 8,
            'sslverify' => true,
            'headers'   => array(
                'Content-Type'        => 'application/json',
                'Origin'              => $frontend_url,
                'Referer'             => $frontend_url,
                'x-revalidate-secret' => $secret,
            ),
            'body'      => wp_json_encode(array(
                'slug'   => 'test-connection',
                'type'   => 'test',
                'action' => 'ping'
            )),
        ));
        $latency = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message'    => 'Connection Failed: ' . $response->get_error_message(),
                'code'       => 0,
                'latency_ms' => $latency,
            ));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code === 200) {
            update_option(AstroPress::OPTION_CONNECTED, '1');
            wp_send_json_success(array(
                'message'    => 'Handshake verified. Key matches Cloudflare Pages/Workers REVALIDATE_SECRET (HTTP 200 OK).',
                'code'       => 200,
                'latency_ms' => $latency,
            ));
        } else {
            $err_msg = !empty($decoded['error']) ? $decoded['error'] : 'HTTP Error ' . $code;
            if ($code === 401) {
                $err_msg = 'Authentication Failed (HTTP 401): Stored secret key does not match Cloudflare environment.';
            }
            wp_send_json_error(array(
                'message'    => $err_msg,
                'code'       => $code,
                'latency_ms' => $latency,
            ));
        }
    }

    /**
     * AJAX: Get structured list of URLs to warm
     */
    public function ajax_get_warmup_urls() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $preset = sanitize_text_field($_POST['preset'] ?? 'standard');
        $items = $this->core->cache->get_warmup_urls($preset);

        wp_send_json_success(array(
            'total' => count($items),
            'items' => $items,
        ));
    }

    /**
     * AJAX: Warm a small batch of URLs sequentially with live metrics
     */
    public function ajax_warm_batch() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $raw_batch = $_POST['batch'] ?? array();
        if (!is_array($raw_batch) || empty($raw_batch)) {
            wp_send_json_error(array('message' => 'Empty batch provided'), 400);
        }

        $sanitized_batch = array();
        foreach ($raw_batch as $item) {
            if (is_array($item)) {
                $sanitized_batch[] = array(
                    'url'   => sanitize_text_field($item['url'] ?? '/'),
                    'title' => sanitize_text_field($item['title'] ?? ''),
                    'type'  => sanitize_text_field($item['type'] ?? 'Page'),
                );
            } else {
                $sanitized_batch[] = sanitize_text_field($item);
            }
        }

        $results = $this->core->cache->warm_url_batch($sanitized_batch);

        wp_send_json_success(array(
            'results' => $results,
        ));
    }

    /**
     * AJAX: Start asynchronous background warming job
     */
    public function ajax_start_bg_warm() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $preset     = sanitize_text_field($_POST['preset'] ?? 'standard');
        $delay_ms   = absint($_POST['delay'] ?? 1000);
        $batch_size = absint($_POST['batch_size'] ?? 2);

        $res = $this->core->cache->start_background_job($preset, $delay_ms, $batch_size);
        if (is_wp_error($res)) {
            wp_send_json_error(array('message' => $res->get_error_message()), 400);
        }

        wp_send_json_success(array('job' => $res));
    }

    /**
     * AJAX: Query background warming job progress
     */
    public function ajax_get_bg_warm_status() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $status = $this->core->cache->get_background_job_status();
        wp_send_json_success(array('job' => $status));
    }

    /**
     * AJAX: Pause background job
     */
    public function ajax_pause_bg_warm() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $ok = $this->core->cache->pause_background_job();
        wp_send_json_success(array('paused' => $ok));
    }

    /**
     * AJAX: Resume background job
     */
    public function ajax_resume_bg_warm() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $ok = $this->core->cache->resume_background_job();
        wp_send_json_success(array('resumed' => $ok));
    }

    /**
     * AJAX: Cancel background job
     */
    public function ajax_cancel_bg_warm() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $ok = $this->core->cache->cancel_background_job();
        wp_send_json_success(array('cancelled' => $ok));
    }

    /**
     * AJAX: Clear finished/cancelled background job
     */
    public function ajax_clear_bg_warm() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $this->core->cache->clear_background_job();
        wp_send_json_success(array('cleared' => true));
    }

    /**
     * AJAX: Test Cloudflare R2 Connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: get_option('r2g_account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: get_option('r2g_access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_key = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        }

        $bucket        = sanitize_text_field($_POST['bucket'] ?? '') ?: get_option('r2g_bucket', '');
        $custom_domain = sanitize_text_field($_POST['custom_domain'] ?? '') ?: get_option('r2g_custom_domain', '');

        if (empty($account_id) || empty($access_key) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'Please enter Cloudflare Account ID, Access Key ID, and Secret Access Key.'));
        }
        if (empty($bucket)) {
            wp_send_json_error(array('message' => 'Please select or enter an R2 Bucket name.'));
        }

        $client = new AstroPress_R2_Client($account_id, $access_key, $secret_key, $bucket);
        $res = $client->test_connection($custom_domain);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * AJAX: Discover R2 Buckets
     */
    public function ajax_fetch_buckets() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: get_option('r2g_account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: get_option('r2g_access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_key = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        }

        if (empty($account_id) || empty($access_key) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'Please enter Account ID, Access Key ID, and Secret Access Key first.'));
        }

        $client = new AstroPress_R2_Client($account_id, $access_key, $secret_key, '');
        $res = $client->list_buckets();

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * AJAX: Compression Preview Tester
     */
    public function ajax_preview_compression() {
        check_ajax_referer('astropress_admin_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'astropress')));
        }

        if (empty($_FILES['image']) || empty($_FILES['image']['tmp_name'])) {
            wp_send_json_error(array('message' => esc_html__('No image file provided for preview.', 'astropress')));
        }

        $format    = sanitize_text_field($_POST['format'] ?? 'webp');
        $quality   = (int) ($_POST['quality'] ?? 82);
        $max_width = (int) ($_POST['max_width'] ?? 1920);
        $engine    = AstroPress_Optimizer::normalize_engine(sanitize_text_field($_POST['engine'] ?? ''));

        $tmp_file  = $_FILES['image']['tmp_name'];
        if (!empty($_FILES['image']['error']) || !is_uploaded_file($tmp_file)) {
            wp_send_json_error(array('message' => esc_html__('Invalid upload for preview.', 'astropress')));
        }

        $orig_name = sanitize_file_name($_FILES['image']['name'] ?? 'image.jpg');
        $orig_size = (int) ($_FILES['image']['size'] ?? 0);
        if ($orig_size <= 0 && file_exists($tmp_file)) {
            $orig_size = filesize($tmp_file);
        }

        $optimizer = AstroPress_Optimizer::instance();
        $res = $optimizer->compress_image($tmp_file, $format, $quality, $max_width, $engine);

        if (!empty($res['success']) && !empty($res['output_path']) && file_exists($res['output_path'])) {
            $out_size = filesize($res['output_path']);
            $out_data = base64_encode(file_get_contents($res['output_path']));
            $mime     = ($format === 'webp') ? 'image/webp' : (($format === 'avif') ? 'image/avif' : 'image/jpeg');

            @unlink($res['output_path']);

            wp_send_json_success(array(
                'original_size' => $orig_size,
                'compressed_size' => $out_size,
                'saved_bytes'   => max(0, $orig_size - $out_size),
                'percent'       => ($orig_size > 0) ? round((($orig_size - $out_size) / $orig_size) * 100, 1) : 0,
                'data_uri'      => 'data:' . $mime . ';base64,' . $out_data,
                'engine_used'   => $res['engine_used'] ?? $engine,
            ));
        } else {
            wp_send_json_error(array('message' => !empty($res['error']) ? $res['error'] : 'Compression preview failed.'));
        }
    }

    /**
     * Render Master Settings Page
     */
    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        $notice = get_transient('astropress_notice');
        if ($notice) {
            delete_transient('astropress_notice');
        }

        $frontend_url = $this->core->get_frontend_url();
        $is_connected = $this->core->is_connected();
        $r2_bucket    = get_option('r2g_bucket', '');
        $r2_domain    = get_option('r2g_custom_domain', '');
        $stats        = AstroPress_Database::get_stats();

        ?>
        <div class="wrap astropress-wrap">
            <header class="astropress-header">
                <div class="astropress-header-brand">
                    <span class="dashicons dashicons-cloud astropress-logo-icon"></span>
                    <div>
                        <h1>AstroPress <span class="astropress-version-tag">v<?php echo esc_html(ASTROPRESS_VERSION); ?></span></h1>
                        <p class="astropress-tagline"><?php esc_html_e('Universal WordPress Headless Bridge & Native Cloudflare R2 Offloader', 'astropress'); ?></p>
                    </div>
                </div>
                <div class="astropress-header-badges">
                    <span class="astropress-badge <?php echo $is_connected ? 'astropress-badge-online' : 'astropress-badge-offline'; ?>">
                        <span class="astropress-dot"></span> Bridge: <?php echo $is_connected ? esc_html__('Online', 'astropress') : esc_html__('Disconnected', 'astropress'); ?>
                    </span>
                    <span class="astropress-badge <?php echo !empty($r2_bucket) ? 'astropress-badge-online' : 'astropress-badge-offline'; ?>">
                        <span class="astropress-dot"></span> R2: <?php echo !empty($r2_bucket) ? esc_html($r2_bucket) : esc_html__('Unconfigured', 'astropress'); ?>
                    </span>
                </div>
            </header>

            <?php if ($notice && !empty($notice['message'])): ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper astropress-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress')); ?>" class="nav-tab <?php echo ($active_tab === 'dashboard') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Overview', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=bridge')); ?>" class="nav-tab <?php echo ($active_tab === 'bridge') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('Headless Bridge', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=warmer')); ?>" class="nav-tab <?php echo ($active_tab === 'warmer') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-performance"></span> <?php esc_html_e('Cache Warmer', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" class="nav-tab <?php echo ($active_tab === 'r2') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-database"></span> <?php esc_html_e('Cloudflare R2', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=optimizer')); ?>" class="nav-tab <?php echo ($active_tab === 'optimizer') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-image"></span> <?php esc_html_e('Image Optimizer', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=sync')); ?>" class="nav-tab <?php echo ($active_tab === 'sync') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Media Sync', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=updates')); ?>" class="nav-tab <?php echo ($active_tab === 'updates') ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-cloud-saved"></span> <?php esc_html_e('Updates & Rollback', 'astropress'); ?>
                </a>
            </nav>

            <div class="astropress-content">
                <?php
                switch ($active_tab) {
                    case 'bridge':
                        $this->render_tab_bridge($frontend_url, $is_connected);
                        break;
                    case 'warmer':
                        $this->render_tab_warmer();
                        break;
                    case 'r2':
                        $this->render_tab_r2();
                        break;
                    case 'optimizer':
                        $this->render_tab_optimizer();
                        break;
                    case 'sync':
                        $this->render_tab_sync($stats);
                        break;
                    case 'updates':
                        $this->render_tab_updates();
                        break;
                    case 'dashboard':
                    default:
                        $this->render_tab_dashboard($frontend_url, $is_connected, $stats);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Dashboard & Overview
     */
    private function render_tab_dashboard($frontend_url, $is_connected, $stats) {
        $total_media = $stats['total'] ?? 0;
        $offloaded   = $stats['offloaded'] ?? 0;
        $percentage  = ($total_media > 0) ? round(($offloaded / $total_media) * 100, 1) : 0;
        $saved_space = size_format($stats['saved_bytes'] ?? 0, 2);
        ?>
        <div class="astropress-grid-2">
            <div class="astropress-card">
                <h2><?php esc_html_e('System Status', 'astropress'); ?></h2>
                <table class="astropress-status-table">
                    <tr>
                        <td><strong><?php esc_html_e('Headless Frontend URL', 'astropress'); ?></strong></td>
                        <td><code><?php echo esc_html($frontend_url); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Bridge Status', 'astropress'); ?></strong></td>
                        <td>
                            <?php if ($is_connected): ?>
                                <span class="astropress-text-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connected & Handshake Verified', 'astropress'); ?></span>
                            <?php else: ?>
                                <span class="astropress-text-danger"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Disconnected or Pending Verification', 'astropress'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Cloudflare R2 Bucket', 'astropress'); ?></strong></td>
                        <td><code><?php echo esc_html(get_option('r2g_bucket', 'None')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Custom CDN Host', 'astropress'); ?></strong></td>
                        <td><code><?php echo esc_html(get_option('r2g_custom_domain', 'Origin Uploads')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Image Engine', 'astropress'); ?></strong></td>
                        <td><span class="astropress-pill"><?php echo esc_html(strtoupper(AstroPress_Optimizer::get_engine())); ?></span></td>
                    </tr>
                </table>

                <div class="astropress-btn-group" style="margin-top:20px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=bridge')); ?>" class="button button-primary"><?php esc_html_e('Configure Bridge', 'astropress'); ?></a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" class="button button-secondary"><?php esc_html_e('R2 Settings', 'astropress'); ?></a>
                </div>
            </div>

            <div class="astropress-card">
                <h2><?php esc_html_e('Storage & Optimization Metrics', 'astropress'); ?></h2>
                <div class="astropress-metrics">
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($percentage); ?>%</span>
                        <span class="astropress-metric-label"><?php esc_html_e('Media Offloaded to R2', 'astropress'); ?></span>
                    </div>
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($offloaded); ?> / <?php echo esc_html($total_media); ?></span>
                        <span class="astropress-metric-label"><?php esc_html_e('Attachments Synced', 'astropress'); ?></span>
                    </div>
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($saved_space); ?></span>
                        <span class="astropress-metric-label"><?php esc_html_e('Server Disk Space Saved', 'astropress'); ?></span>
                    </div>
                </div>

                <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;" />

                <h3><?php esc_html_e('Instant Edge Cache Purge', 'astropress'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('astropress_purge_custom_action', 'astropress_purge_custom_nonce'); ?>
                    <div class="astropress-inline-form">
                        <input type="text" name="astropress_custom_url" placeholder="/blog/my-post or full URL" style="flex:1;" />
                        <button type="submit" name="astropress_purge_custom_url" class="button button-secondary"><?php esc_html_e('Purge Path', 'astropress'); ?></button>
                    </div>
                </form>

                <form method="post" action="" style="margin-top:10px;">
                    <?php wp_nonce_field('astropress_purge_global_action', 'astropress_purge_global_nonce'); ?>
                    <button type="submit" name="astropress_purge_global" class="button button-link-delete" onclick="return confirm('Purge entire website cache across all Cloudflare Edge POPs?');">
                        <?php esc_html_e('Purge Global Cache (All URLs)', 'astropress'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Headless Bridge Settings
     */
    private function render_tab_bridge($frontend_url, $is_connected) {
        $secret = $this->core->get_secret();
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Headless Astro Connection', 'astropress'); ?></h2>
            <p><?php esc_html_e('Connect WordPress to your Astro site running on Cloudflare Pages or Cloudflare Workers. Real-time cache invalidation webhooks are signed using this shared secret.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_bridge_action', 'astropress_bridge_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="astropress_frontend_url"><?php esc_html_e('Astro Frontend URL', 'astropress'); ?></label></th>
                        <td>
                            <input type="url" id="astropress_frontend_url" name="astropress_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" required placeholder="https://example.com" />
                            <p class="description"><?php esc_html_e('Your canonical production Astro website URL (e.g., https://example.com).', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="astropress_secret"><?php esc_html_e('Revalidation Secret Key', 'astropress'); ?></label></th>
                        <td>
                            <div class="astropress-inline-form">
                                <input type="password" id="astropress_secret" name="astropress_secret" value="<?php echo esc_attr($secret); ?>" class="regular-text" autocomplete="off" />
                                <button type="button" class="button" id="astropress-toggle-secret"><?php esc_html_e('Show', 'astropress'); ?></button>
                                <button type="button" class="button" id="astropress-generate-secret"><?php esc_html_e('Generate', 'astropress'); ?></button>
                                <button type="button" class="button button-secondary" id="astropress-test-handshake"><?php esc_html_e('Test Handshake', 'astropress'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Set the exact same token in your Cloudflare Pages/Workers environment variable: REVALIDATE_SECRET.', 'astropress'); ?></p>
                            <div id="astropress-handshake-status" style="margin-top:8px;"></div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_bridge" class="button button-primary"><?php esc_html_e('Save & Verify Connection', 'astropress'); ?></button>
                </p>
            </form>

            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;" />

            <h2><?php esc_html_e('Cache Invalidation & Security Shield', 'astropress'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('astropress_automation_action', 'astropress_automation_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Purge Triggers', 'astropress'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="purge_on_save" value="1" <?php checked($this->core->should_purge_on_save()); ?> /> <?php esc_html_e('Purge post / page cache on publish, update or trash', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_feeds" value="1" <?php checked($this->core->should_purge_feeds()); ?> /> <?php esc_html_e('Purge RSS and Atom feeds on new post publication', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_taxonomy" value="1" <?php checked($this->core->should_purge_taxonomy()); ?> /> <?php esc_html_e('Purge category and tag archives when terms are edited', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_menu" value="1" <?php checked($this->core->should_purge_menu()); ?> /> <?php esc_html_e('Purge global cache when navigation menus are updated', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="lscwp_sync" value="1" <?php checked($this->core->should_sync_lscwp()); ?> /> <?php esc_html_e('Synchronize with LiteSpeed Cache purge events', 'astropress'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Backend Security & SEO', 'astropress'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="rewrite_links" value="1" <?php checked($this->core->should_rewrite_links()); ?> /> <?php esc_html_e('Rewrite post/page preview & view permalinks to Astro frontend', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="subdomain_shield" value="1" <?php checked($this->core->should_shield_subdomain()); ?> /> <?php esc_html_e('301 Redirect public backend visitors to frontend (preserves admin, REST, cron)', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="subdomain_noindex" value="1" <?php checked($this->core->should_noindex_backend()); ?> /> <?php esc_html_e('Send X-Robots-Tag: noindex, nofollow on backend responses', 'astropress'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="warm_delay"><?php esc_html_e('Warmer Pacing (ms)', 'astropress'); ?></label></th>
                        <td>
                            <input type="number" id="warm_delay" name="warm_delay" value="<?php echo esc_attr($this->core->get_warm_delay()); ?>" class="small-text" min="200" max="5000" step="100" /> ms
                            <p class="description"><?php esc_html_e('Pause between warming requests to prevent edge CPU spikes.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="warm_batch_size"><?php esc_html_e('Warmer Batch Size', 'astropress'); ?></label></th>
                        <td>
                            <input type="number" id="warm_batch_size" name="warm_batch_size" value="<?php echo esc_attr($this->core->get_warm_batch_size()); ?>" class="small-text" min="1" max="10" />
                            <p class="description"><?php esc_html_e('URLs per concurrent warming request.', 'astropress'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_automation" class="button button-primary"><?php esc_html_e('Save Cache Rules', 'astropress'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Tab: Cache Warmer
     */
    private function render_tab_warmer() {
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Edge Cache Warmer', 'astropress'); ?></h2>
            <p><?php esc_html_e('Pre-warm your Cloudflare Edge caches so that first-time visitors experience instantaneous 20ms page loads instead of SSR cold boots.', 'astropress'); ?></p>

            <div class="astropress-warmer-controls">
                <label for="astropress-warmer-preset"><strong><?php esc_html_e('Warmup Scope:', 'astropress'); ?></strong></label>
                <select id="astropress-warmer-preset">
                    <option value="standard"><?php esc_html_e('Standard (Homepage, Top Posts & Categories)', 'astropress'); ?></option>
                    <option value="recent"><?php esc_html_e('Recent (Latest 50 Posts & Pages)', 'astropress'); ?></option>
                    <option value="full"><?php esc_html_e('Full (All Published Posts, Pages & Categories)', 'astropress'); ?></option>
                </select>

                <button type="button" class="button button-primary" id="astropress-start-warmer">
                    <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Start Interactive Warmer', 'astropress'); ?>
                </button>
                <button type="button" class="button button-secondary" id="astropress-pause-warmer" style="display:none;">
                    <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause', 'astropress'); ?>
                </button>
                <button type="button" class="button button-link-delete" id="astropress-cancel-warmer" style="display:none;">
                    <?php esc_html_e('Cancel', 'astropress'); ?>
                </button>
            </div>

            <div id="astropress-warmer-progress-container" style="display:none; margin-top:20px;">
                <div class="astropress-progress-bar-wrap">
                    <div class="astropress-progress-bar" id="astropress-warmer-progress-bar" style="width:0%;"></div>
                </div>
                <div class="astropress-progress-stats" id="astropress-warmer-progress-text">
                    0 / 0 URLs (0%)
                </div>
                <div class="astropress-log-box" id="astropress-warmer-log"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Cloudflare R2 Credentials & CDN
     */
    private function render_tab_r2() {
        $account_id    = get_option('r2g_account_id', '');
        $access_key    = get_option('r2g_access_key', '');
        $secret_key    = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        $bucket        = get_option('r2g_bucket', '');
        $custom_domain = get_option('r2g_custom_domain', '');
        $storage_mode  = get_option('r2g_storage_mode', 'both');
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Cloudflare R2 Object Storage', 'astropress'); ?></h2>
            <p><?php esc_html_e('Offload media library uploads to zero-egress Cloudflare R2 object storage using native SigV4 authentication without heavy AWS SDKs.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_r2_action', 'astropress_r2_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="r2_account_id"><?php esc_html_e('Cloudflare Account ID', 'astropress'); ?></label></th>
                        <td>
                            <input type="text" id="r2_account_id" name="r2_account_id" value="<?php echo esc_attr($account_id); ?>" class="regular-text" required placeholder="32-character hex ID" />
                            <p class="description"><?php esc_html_e('Found in Cloudflare Dashboard URL or right sidebar under Account ID.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_access_key"><?php esc_html_e('R2 Access Key ID', 'astropress'); ?></label></th>
                        <td>
                            <input type="text" id="r2_access_key" name="r2_access_key" value="<?php echo esc_attr($access_key); ?>" class="regular-text" required />
                            <p class="description"><?php esc_html_e('Created under Cloudflare Dashboard -> R2 -> Manage R2 API Tokens.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_secret_key"><?php esc_html_e('R2 Secret Access Key', 'astropress'); ?></label></th>
                        <td>
                            <input type="password" id="r2_secret_key" name="r2_secret_key" value="<?php echo !empty($secret_key) ? '••••••••••••••••••••••••••••••••' : ''; ?>" class="regular-text" autocomplete="off" />
                            <p class="description"><?php esc_html_e('Encrypted at rest using AES-256-GCM. Leave masked unless updating.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_bucket"><?php esc_html_e('R2 Bucket Name', 'astropress'); ?></label></th>
                        <td>
                            <div class="astropress-inline-form">
                                <input type="text" id="r2_bucket" name="r2_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" required placeholder="my-bucket" />
                                <button type="button" class="button" id="astropress-fetch-buckets"><?php esc_html_e('Discover Buckets', 'astropress'); ?></button>
                                <button type="button" class="button button-secondary" id="astropress-test-r2"><?php esc_html_e('Test Connection', 'astropress'); ?></button>
                            </div>
                            <div id="astropress-r2-status" style="margin-top:8px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_custom_domain"><?php esc_html_e('Public CDN Domain', 'astropress'); ?></label></th>
                        <td>
                            <input type="text" id="r2_custom_domain" name="r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" placeholder="media.example.com" />
                            <p class="description"><?php esc_html_e('Custom domain configured on your R2 bucket (e.g. media.example.com). Rewrites media attachment URLs.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Storage Delivery Mode', 'astropress'); ?></th>
                        <td>
                            <label><input type="radio" name="r2_storage_mode" value="both" <?php checked($storage_mode, 'both'); ?> /> <strong><?php esc_html_e('Dual Storage (Recommended)', 'astropress'); ?></strong> — <?php esc_html_e('Store files on both local server and R2. Fast CDN delivery with zero local data loss risk.', 'astropress'); ?></label><br />
                            <label><input type="radio" name="r2_storage_mode" value="r2_only" <?php checked($storage_mode, 'r2_only'); ?> /> <strong><?php esc_html_e('R2 Only (Disk Saver)', 'astropress'); ?></strong> — <?php esc_html_e('Delete local media file after successful upload to R2 to conserve hosting disk space.', 'astropress'); ?></label><br />
                            <label><input type="radio" name="r2_storage_mode" value="local_only" <?php checked($storage_mode, 'local_only'); ?> /> <strong><?php esc_html_e('Local Only', 'astropress'); ?></strong> — <?php esc_html_e('Bypass R2 uploads completely.', 'astropress'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Automation Policies', 'astropress'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="r2_auto_upload" value="1" <?php checked(get_option('r2g_auto_upload', 1)); ?> /> <?php esc_html_e('Automatically upload new media attachments to R2 on upload', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="r2_rewrite_urls" value="1" <?php checked(get_option('r2g_rewrite_urls', 1)); ?> /> <?php esc_html_e('Rewrite attachment URLs and srcset to use the R2 Custom CDN Domain', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="r2_delete_from_r2" value="1" <?php checked(get_option('r2g_delete_from_r2', 1)); ?> /> <?php esc_html_e('Delete file from R2 when deleted permanently from WordPress Media Library', 'astropress'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_r2" class="button button-primary"><?php esc_html_e('Save R2 Configuration', 'astropress'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Tab: Image Optimizer
     */
    private function render_tab_optimizer() {
        $enabled   = (int) get_option('r2g_compress_enabled', 1);
        $format    = get_option('r2g_compress_format', 'webp');
        $quality   = (int) get_option('r2g_compress_quality', 82);
        $engine    = AstroPress_Optimizer::get_engine();
        $max_width = (int) get_option('r2g_max_width', 1920);
        $strip_exif= (int) get_option('r2g_strip_exif', 1);
        $interceptor=(int) get_option('r2g_interceptor_enabled', 1);
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Image Optimization & Next-Gen Formats', 'astropress'); ?></h2>
            <p><?php esc_html_e('Convert images to WebP or AVIF on the fly using reSmush.it cloud compression with automatic fallback to server GD/Imagick.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_optimizer_action', 'astropress_optimizer_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Compression', 'astropress'); ?></th>
                        <td>
                            <label><input type="checkbox" name="compress_enabled" value="1" <?php checked($enabled, 1); ?> /> <?php esc_html_e('Optimize images automatically on upload', 'astropress'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="compress_format"><?php esc_html_e('Output Format', 'astropress'); ?></label></th>
                        <td>
                            <select id="compress_format" name="compress_format">
                                <option value="webp" <?php selected($format, 'webp'); ?>><?php esc_html_e('WebP (Recommended — 30% smaller, 98% browser support)', 'astropress'); ?></option>
                                <option value="avif" <?php selected($format, 'avif'); ?>><?php esc_html_e('AVIF (Ultra high compression — requires GD/Imagick with AVIF support)', 'astropress'); ?></option>
                                <option value="original" <?php selected($format, 'original'); ?>><?php esc_html_e('Keep Original Format (JPEG/PNG lossy compression only)', 'astropress'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="compress_quality"><?php esc_html_e('Quality (1 - 100)', 'astropress'); ?></label></th>
                        <td>
                            <input type="range" id="compress_quality" name="compress_quality" min="50" max="100" value="<?php echo esc_attr($quality); ?>" oninput="document.getElementById('quality_val').textContent = this.value;" />
                            <span id="quality_val" style="font-weight:600; margin-left:8px;"><?php echo esc_html($quality); ?></span>%
                            <p class="description"><?php esc_html_e('82% provides the ideal balance between crisp fidelity and ultra-low file size.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="compress_engine"><?php esc_html_e('Compression Engine', 'astropress'); ?></label></th>
                        <td>
                            <select id="compress_engine" name="compress_engine">
                                <option value="resmush" <?php selected($engine, 'resmush'); ?>><?php esc_html_e('reSmush.it Cloud API (Zero server CPU load + local GD fallback)', 'astropress'); ?></option>
                                <option value="server" <?php selected($engine, 'server'); ?>><?php esc_html_e('Server GD / Imagick (Pure local conversion)', 'astropress'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_width"><?php esc_html_e('Max Width (Downscale)', 'astropress'); ?></label></th>
                        <td>
                            <input type="number" id="max_width" name="max_width" value="<?php echo esc_attr($max_width); ?>" class="small-text" /> px
                            <p class="description"><?php esc_html_e('Automatically downscales giant camera images larger than this width. Set 0 to disable.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Metadata & Interceptor', 'astropress'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="strip_exif" value="1" <?php checked($strip_exif, 1); ?> /> <?php esc_html_e('Strip EXIF camera metadata for privacy and extra byte reduction', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="interceptor_enabled" value="1" <?php checked($interceptor, 1); ?> /> <?php esc_html_e('Enable client-side upload interceptor bar for instant visual upload status', 'astropress'); ?></label>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_optimizer" class="button button-primary"><?php esc_html_e('Save Optimizer Settings', 'astropress'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Tab: Media Sync
     */
    private function render_tab_sync($stats) {
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Bulk Media Sync', 'astropress'); ?></h2>
            <p><?php esc_html_e('Upload existing library attachments to Cloudflare R2 or restore files from R2 back to your local server.', 'astropress'); ?></p>

            <div class="astropress-sync-dashboard">
                <div class="astropress-sync-stats">
                    <strong><?php esc_html_e('Current Progress:', 'astropress'); ?></strong>
                    <span id="astropress-sync-counter"><?php echo esc_html($stats['offloaded']); ?> / <?php echo esc_html($stats['total']); ?> <?php esc_html_e('synced', 'astropress'); ?></span>
                </div>

                <div class="astropress-progress-bar-wrap" style="margin:15px 0;">
                    <div class="astropress-progress-bar" id="astropress-sync-progress-bar" style="width:<?php echo esc_attr(($stats['total'] > 0) ? round(($stats['offloaded'] / $stats['total']) * 100) : 0); ?>%;"></div>
                </div>

                <div class="astropress-btn-group">
                    <button type="button" class="button button-primary" id="astropress-start-sync">
                        <span class="dashicons dashicons-upload"></span> <?php esc_html_e('Sync All to Cloudflare R2', 'astropress'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="astropress-cancel-sync" style="display:none;">
                        <?php esc_html_e('Pause / Stop', 'astropress'); ?>
                    </button>
                </div>

                <div class="astropress-log-box" id="astropress-sync-log" style="margin-top:20px; display:none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Updates & Rollback
     */
    private function render_tab_updates() {
        $updater = AstroPress_Updater::instance();
        $latest  = $updater->get_latest_release();
        $all     = $updater->get_all_releases();
        $current = ASTROPRESS_VERSION;
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('GitHub Core Updates & Rollback Pipeline', 'astropress'); ?></h2>
            <p><?php esc_html_e('AstroPress updates directly via GitHub Releases using WordPress core native Plugin_Upgrader sandbox.', 'astropress'); ?></p>

            <table class="astropress-status-table">
                <tr>
                    <td><strong><?php esc_html_e('Installed Version', 'astropress'); ?></strong></td>
                    <td><code>v<?php echo esc_html($current); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Latest Available Version', 'astropress'); ?></strong></td>
                    <td>
                        <?php if (!empty($latest['version'])): ?>
                            <code>v<?php echo esc_html($latest['version']); ?></code>
                            <?php if (version_compare($current, $latest['version'], '<')): ?>
                                <span class="astropress-pill" style="background:#e02424; color:#fff; margin-left:6px;"><?php esc_html_e('Update Available', 'astropress'); ?></span>
                            <?php else: ?>
                                <span class="astropress-pill" style="background:#057a55; color:#fff; margin-left:6px;"><?php esc_html_e('Up to date', 'astropress'); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em><?php esc_html_e('Checking GitHub...', 'astropress'); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="astropress-btn-group" style="margin-top:20px;">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=astropress&tab=updates&astropress_check_updates=1'), 'astropress_manual_check_nonce')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Check for Updates Now', 'astropress'); ?>
                </a>

                <?php if (!empty($latest['version']) && version_compare($current, $latest['version'], '<')): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($updater->get_plugin_basename())), 'upgrade-plugin_' . $updater->get_plugin_basename())); ?>" class="button button-primary">
                        <?php esc_html_e('Upgrade to v' . $latest['version'], 'astropress'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;" />

            <h3><?php esc_html_e('Rollback / Switch Version', 'astropress'); ?></h3>
            <p><?php esc_html_e('If an update introduces unexpected behavior, you can instantly rollback to any previous release without manual FTP uploads.', 'astropress'); ?></p>

            <?php if (!empty($all) && is_array($all)): ?>
                <form method="get" action="<?php echo esc_url(self_admin_url('update.php')); ?>" onsubmit="return confirm('Rollback plugin to selected version?');">
                    <input type="hidden" name="action" value="upgrade-plugin" />
                    <input type="hidden" name="plugin" value="<?php echo esc_attr($updater->get_plugin_basename()); ?>" />
                    <?php wp_nonce_field('upgrade-plugin_' . $updater->get_plugin_basename()); ?>

                    <div class="astropress-inline-form">
                        <select name="astropress_target_version">
                            <?php foreach ($all as $rel): ?>
                                <option value="<?php echo esc_attr($rel['version']); ?>" <?php selected($rel['version'], $current); ?>>
                                    v<?php echo esc_html($rel['version']); ?> (<?php echo esc_html(date_i18n('Y-m-d', strtotime($rel['published']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button"><?php esc_html_e('Switch Version', 'astropress'); ?></button>
                    </div>
                </form>
            <?php else: ?>
                <p><em><?php esc_html_e('No previous releases found in cache.', 'astropress'); ?></em></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Register backwards compatibility aliases
if (!class_exists('TopNepali_Headless_Admin')) {
    class_alias('AstroPress_Admin', 'TopNepali_Headless_Admin');
}
if (!class_exists('R2G_Admin')) {
    class_alias('AstroPress_Admin', 'R2G_Admin');
}
