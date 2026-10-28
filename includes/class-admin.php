<?php
/**
 * AstroPress Admin Dashboard, Guided Onboarding, Settings & Diagnostics
 *
 * Provides a unified, high-performance tabbed interface for:
 * - Overview & Real-Time System Status
 * - Step-by-Step Guided Setup & Onboarding Wizard
 * - Headless Bridge & Cache Invalidation Webhooks
 * - Edge Cache Warmer (Interactive & Background Cron)
 * - Cloudflare R2 Credentials, Bucket Discovery & CDN Delivery
 * - Image Optimization (WebP/AVIF, reSmush.it Cloud & Server GD/Imagick)
 * - Live Compression Preview Tester
 * - Media Sync & Rollback
 * - Copyable Astro Code Snippets & Architecture Blueprints
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
            add_action('wp_ajax_astropress_create_bucket', array($this, 'ajax_create_bucket'));
            add_action('wp_ajax_r2g_create_bucket', array($this, 'ajax_create_bucket'));
            add_action('wp_ajax_astropress_deploy_cache_rule', array($this, 'ajax_deploy_cache_rule'));

            // AJAX Handlers for Webhook Logs, Direct Purge, Deploy Hook & Cloudflare Quick Connect
            add_action('wp_ajax_astropress_resend_webhook', array($this, 'ajax_resend_webhook'));
            add_action('wp_ajax_astropress_clear_webhook_logs', array($this, 'ajax_clear_webhook_logs'));
            add_action('wp_ajax_astropress_cf_quick_connect', array($this, 'ajax_cf_quick_connect'));
            add_action('wp_ajax_astropress_purge_cf_zone', array($this, 'ajax_purge_cf_zone'));
            add_action('wp_ajax_astropress_trigger_deploy_hook', array($this, 'ajax_trigger_deploy_hook'));
            add_action('wp_ajax_astropress_detect_frontend', array($this, 'ajax_detect_frontend'));
            add_action('wp_ajax_astropress_sync_pages_env', array($this, 'ajax_sync_pages_env'));
            add_action('wp_ajax_astropress_audit_cdn_domain', array($this, 'ajax_audit_cdn_domain'));
            add_action('wp_ajax_astropress_attach_r2_custom_domain', array($this, 'ajax_attach_r2_custom_domain'));

            // AJAX Handlers for Cloudflare Ruleset Engine & Danger Zone Reset
            add_action('wp_ajax_astropress_get_cache_rules', array($this, 'ajax_get_cf_cache_rules'));
            add_action('wp_ajax_astropress_deploy_cache_rule_preset', array($this, 'ajax_deploy_cf_cache_rule'));
            add_action('wp_ajax_astropress_toggle_cache_rule', array($this, 'ajax_toggle_cf_cache_rule'));
            add_action('wp_ajax_astropress_delete_cache_rule', array($this, 'ajax_delete_cf_cache_rule'));
            add_action('wp_ajax_astropress_reset_connection', array($this, 'ajax_reset_connection'));

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
            'Setup Wizard — AstroPress',
            'Setup Wizard',
            'manage_options',
            'astropress&tab=onboarding',
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
            'Cache Warmer — AstroPress',
            'Cache Warmer',
            'manage_options',
            'astropress&tab=warmer',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Webhook Logs — AstroPress',
            'Webhook Logs',
            'manage_options',
            'astropress&tab=logs',
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
            'SEO & Security — AstroPress',
            'SEO & Security',
            'manage_options',
            'astropress&tab=seo',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'astropress',
            'Code & Snippets — AstroPress',
            'Astro Snippets',
            'manage_options',
            'astropress&tab=integration',
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
            'allowedRoots' => $this->get_allowed_domain_roots(),
        ));

        wp_localize_script('astropress-admin-js', 'topnepaliAdmin', array(
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('topnepali_admin_nonce'),
            'frontendUrl' => $this->core->get_frontend_url(),
            'defaultDelay'=> $this->core->get_warm_delay(),
            'defaultBatch'=> $this->core->get_warm_batch_size(),
        ));
        wp_localize_script('astropress-admin-js', 'r2g_compress_config', $compress_config);

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

        // Check for active legacy standalone plugins that AstroPress unifies
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $legacy_active = array();
        if (is_plugin_active('r2-by-grisma/r2-by-grisma.php') || (defined('R2G_FILE') && R2G_FILE !== ASTROPRESS_FILE)) {
            $legacy_active[] = 'R2 by Grisma';
        }
        if (is_plugin_active('topnepali-headless/topnepali-headless.php') || is_plugin_active('headless/headless.php') || is_plugin_active('headless/topnepali-headless.php') || (defined('TOPNEPALI_HEADLESS_FILE') && TOPNEPALI_HEADLESS_FILE !== ASTROPRESS_FILE)) {
            $legacy_active[] = 'TopNepali Headless Engine';
        }

        if (!empty($legacy_active)) {
            $deactivate_url = wp_nonce_url(admin_url('admin.php?page=astropress&astropress_deactivate_legacy=1'), 'astropress_deactivate_legacy_nonce');
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>AstroPress Migration Detected:</strong> The following legacy plugin(s) are still active: <em>' . esc_html(implode(', ', $legacy_active)) . '</em>. ';
            echo 'AstroPress has unified all headless bridge, cache invalidation, and R2 offloading functionality with 100% backward compatibility. ';
            echo 'All existing settings and media records are automatically preserved. You can safely deactivate the legacy plugins to avoid duplicate background hooks.</p>';
            echo '<p><a href="' . esc_url($deactivate_url) . '" class="button button-primary">Deactivate Legacy Plugins</a></p>';
            echo '</div>';
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
     * Get Cloudflare API Token Template URL with pre-filled scopes & permissions
     *
     * Pre-configures:
     * - Cache Purge (Zone): Purge
     * - Workers R2 (Account): Edit
     * - Cloudflare Pages (Account): Edit
     * - Page Rules / Cache Rules (Zone): Edit
     * - Zone (Zone): Read
     * - Account Settings (Account): Read
     * - Zone DNS (Zone): Edit
     * - Cloudflare Pages (Account): Edit
     *
     * @return string
     */
    public function get_cf_token_template_url() {
        $permissions = array(
            array('key' => 'cache_purge',      'type' => 'purge'),
            array('key' => 'cache_rules',      'type' => 'edit'),
            array('key' => 'dns',              'type' => 'edit'),
            array('key' => 'zone',             'type' => 'read'),
            array('key' => 'workers_r2',       'type' => 'edit'),
            array('key' => 'page',             'type' => 'edit'),
            array('key' => 'account_settings', 'type' => 'read'),
        );

        return 'https://dash.cloudflare.com/profile/api-tokens?permissionGroupKeys=' . rawurlencode(wp_json_encode($permissions)) . '&accountId=*&zoneId=all&name=' . rawurlencode('AstroPress Universal Bridge');
    }

    /**
     * Get Cloudflare Scoped Token Permissions Blueprint
     *
     * @return array
     */
    public function get_cf_permissions_blueprint() {
        return array(
            array(
                'category' => 'Zone',
                'name'     => 'Cache Purge',
                'access'   => 'Purge',
                'desc'     => 'Real-time cache invalidation on post/page publish & 1-click CDN edge purge',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'Cache Rules',
                'access'   => 'Edit',
                'desc'     => 'Modern Cloudflare Cache Rules deployment (1-year Edge & Browser TTL via Ruleset API)',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'DNS',
                'access'   => 'Edit',
                'desc'     => 'Auto-verify DNS records & 1-click attach custom CDN domains to R2',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'Zone',
                'access'   => 'Read',
                'desc'     => 'Auto-discovery of domain Zone ID',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Workers R2 Storage',
                'access'   => 'Edit',
                'desc'     => 'Single-Token zero-egress media uploads & bucket management',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Cloudflare Pages',
                'access'   => 'Edit',
                'desc'     => 'Zero-touch push of environment variables (REVALIDATE_SECRET, WP_API_URL)',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Account Settings',
                'access'   => 'Read',
                'desc'     => 'Cloudflare Account ID discovery',
            ),
        );
    }

    /**
     * Get Plaintext Cloudflare Permissions Recipe for 1-Click Clipboard Copy
     *
     * @return string
     */
    public function get_cf_permissions_recipe_text() {
        return "Token Name: AstroPress Universal Bridge\n\nPermissions:\n1. Zone > Cache Purge > Purge\n2. Zone > Cache Rules > Edit\n3. Zone > Page Rules > Edit\n4. Zone > DNS > Edit\n5. Zone > Zone > Read\n6. Account > Workers R2 Storage > Edit\n7. Account > Cloudflare Pages > Edit\n8. Account > Account Settings > Read\n\nAccount Resources: Include > All accounts (or your account)\nZone Resources: Include > All zones (or your domain)";
    }

    /**
     * Get Cloudflare R2 S3-Compatible API Tokens Dashboard URL
     *
     * @param string $account_id Optional specific account ID
     * @return string
     */
    public function get_cf_r2_tokens_url($account_id = '') {
        $account_id = sanitize_text_field($account_id ?: get_option('r2g_account_id', ''));
        if (!empty($account_id)) {
            return 'https://dash.cloudflare.com/' . rawurlencode($account_id) . '/r2/api-tokens';
        }
        return 'https://dash.cloudflare.com/?to=/:account/r2/api-tokens';
    }

    /**
     * Extract root domain name (e.g., "media.topnepali.com" -> "topnepali.com", "wp.tester.co.uk" -> "tester.co.uk")
     *
     * @param string $host
     * @return string
     */
    public function extract_root_domain($host) {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('#^https?://#i', '', $host);
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];

        if (empty($host) || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $parts = explode('.', $host);
        $count = count($parts);
        if ($count <= 2) {
            return $host;
        }

        $multi_tlds = array(
            'com.np', 'org.np', 'edu.np', 'gov.np', 'net.np', 'mil.np',
            'co.uk', 'org.uk', 'me.uk', 'ltd.uk', 'plc.uk',
            'co.in', 'net.in', 'org.in', 'gen.in',
            'com.au', 'net.au', 'org.au', 'edu.au',
        );
        $last_two = $parts[$count - 2] . '.' . $parts[$count - 1];
        if (in_array($last_two, $multi_tlds, true) && $count >= 3) {
            return $parts[$count - 3] . '.' . $last_two;
        }

        return $parts[$count - 2] . '.' . $parts[$count - 1];
    }

    /**
     * Get allowed root domains for Custom CDN Subdomains
     *
     * @return array
     */
    public function get_allowed_domain_roots() {
        $allowed = array();

        // 1. WordPress site host
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($site_host) {
            $root = $this->extract_root_domain($site_host);
            if ($root && !in_array($root, $allowed, true)) {
                $allowed[] = strtolower($root);
            }
        }

        // 2. Astro Frontend host
        $fe_url  = $this->core->get_frontend_url();
        $fe_host = wp_parse_url($fe_url, PHP_URL_HOST);
        if ($fe_host) {
            $root = $this->extract_root_domain($fe_host);
            if ($root && !in_array($root, $allowed, true)) {
                $allowed[] = strtolower($root);
            }
        }

        // 3. Matched Cloudflare Zone Name (if saved)
        $zone_name = get_option('astropress_cf_zone_name', '');
        if ($zone_name) {
            $root = $this->extract_root_domain($zone_name);
            if ($root && !in_array($root, $allowed, true)) {
                $allowed[] = strtolower($root);
            }
        }

        return array_values(array_filter($allowed));
    }

    /**
     * Validate if a given CDN host is a valid subdomain/host of site or frontend
     *
     * @param string $cdn_host
     * @return bool
     */
    public function is_allowed_cdn_domain($cdn_host) {
        $cdn_host = strtolower(trim((string) $cdn_host));
        $cdn_host = preg_replace('#^https?://#i', '', $cdn_host);
        $cdn_host = explode('/', $cdn_host)[0];
        $cdn_host = explode(':', $cdn_host)[0];

        if (empty($cdn_host)) {
            return false;
        }

        $allowed_roots = $this->get_allowed_domain_roots();
        if (empty($allowed_roots)) {
            return true;
        }

        $cdn_root = $this->extract_root_domain($cdn_host);

        foreach ($allowed_roots as $root) {
            if ($cdn_root === $root || $cdn_host === $root || substr($cdn_host, -strlen('.' . $root)) === '.' . $root) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pre-Flight System Capabilities & Health Diagnostics
     *
     * @return array
     */
    public function get_preflight_checks() {
        $checks = array();

        // 1. PHP Version
        $php_ok = version_compare(PHP_VERSION, '7.4', '>=');
        $checks['php'] = array(
            'label'   => 'PHP Version',
            'value'   => PHP_VERSION,
            'status'  => $php_ok ? 'pass' : 'fail',
            'message' => $php_ok ? 'Compatible (7.4+)' : 'Requires PHP 7.4 or higher.',
        );

        // 2. cURL Extension
        $curl_ok = extension_loaded('curl');
        $checks['curl'] = array(
            'label'   => 'cURL Extension',
            'value'   => $curl_ok ? 'Installed' : 'Missing',
            'status'  => $curl_ok ? 'pass' : 'fail',
            'message' => $curl_ok ? 'Ready for SigV4 and Webhooks' : 'Required for Cloudflare R2 and Edge Cache webhooks.',
        );

        // 3. Cryptography (OpenSSL or Sodium)
        $crypto_ok = AstroPress_Encryption::is_available();
        $checks['crypto'] = array(
            'label'   => 'Credential Encryption',
            'value'   => extension_loaded('sodium') ? 'libsodium (AES-256)' : (extension_loaded('openssl') ? 'OpenSSL (AES-256-GCM)' : 'None'),
            'status'  => $crypto_ok ? 'pass' : 'warn',
            'message' => $crypto_ok ? 'Hardware-accelerated AES-256 encryption active' : 'Install OpenSSL or Sodium for encrypted R2 credentials.',
        );

        // 4. WordPress Security Salts
        $salts_ok = AstroPress_Encryption::has_strong_salts();
        $checks['salts'] = array(
            'label'   => 'Security Salts',
            'value'   => $salts_ok ? 'Defined' : 'Default / Missing',
            'status'  => $salts_ok ? 'pass' : 'warn',
            'message' => $salts_ok ? 'Unique cryptographic salts in wp-config.php' : 'Define unique salts in wp-config.php for maximum key isolation.',
        );

        // 5. Image Processing Library
        $gd_ok = extension_loaded('gd');
        $imagick_ok = extension_loaded('imagick');
        $checks['image_lib'] = array(
            'label'   => 'Image Processing',
            'value'   => $imagick_ok ? 'Imagick Active' : ($gd_ok ? 'GD Active' : 'Missing'),
            'status'  => ($gd_ok || $imagick_ok) ? 'pass' : 'warn',
            'message' => ($gd_ok || $imagick_ok) ? 'Ready for on-the-fly conversion' : 'reSmush.it cloud API will be used exclusively.',
        );

        // 6. WebP Support
        $webp_ok = AstroPress_Optimizer::can_generate_webp();
        $checks['webp'] = array(
            'label'   => 'WebP Conversion',
            'value'   => $webp_ok ? 'Supported' : 'Cloud Fallback',
            'status'  => $webp_ok ? 'pass' : 'info',
            'message' => $webp_ok ? 'Native server generation enabled' : 'WebP generated via reSmush.it Cloud API.',
        );

        // 7. AVIF Support
        $avif_ok = AstroPress_Optimizer::can_generate_avif();
        $checks['avif'] = array(
            'label'   => 'AVIF Conversion',
            'value'   => $avif_ok ? 'Supported' : 'Not available',
            'status'  => $avif_ok ? 'pass' : 'info',
            'message' => $avif_ok ? 'Native AVIF generation active' : 'Requires PHP 8.1+ with libavif.',
        );

        return $checks;
    }

    /**
     * Handle Admin Form Submissions
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
                $enc_secret = AstroPress_Encryption::encrypt($new_secret);
                update_option(AstroPress::OPTION_SECRET, $enc_secret);
                update_option('topnepali_headless_secret', $enc_secret);
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
            update_option(AstroPress::OPTION_DEBOUNCE_ENABLED, isset($_POST['debounce_enabled']) ? '1' : '0');
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, isset($_POST['cf_direct_purge']) ? '1' : '0');
            update_option(AstroPress::OPTION_CF_ZONE_ID, sanitize_text_field(wp_unslash($_POST['cf_zone_id'] ?? '')));
            update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, esc_url_raw(trim(wp_unslash($_POST['cf_deploy_hook'] ?? ''))));

            $cf_token = trim(wp_unslash($_POST['cf_api_token'] ?? ''));
            if (!empty($cf_token) && strpos($cf_token, '••••') === false) {
                update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($cf_token));
            }

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Automation & Cache rules saved successfully.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=bridge'));
            exit;
        }

        // Save Dedicated SEO & Security Settings
        if ((isset($_POST['astropress_save_seo']) && check_admin_referer('astropress_seo_action', 'astropress_seo_nonce')) ||
            (isset($_POST['topnepali_save_seo']) && check_admin_referer('topnepali_seo_action', 'topnepali_seo_nonce'))) {
            $rewrite_links     = isset($_POST['rewrite_links']) ? '1' : '0';
            $subdomain_shield  = isset($_POST['subdomain_shield']) ? '1' : '0';
            $subdomain_noindex = isset($_POST['subdomain_noindex']) ? '1' : '0';

            update_option(AstroPress::OPTION_REWRITE_LINKS, $rewrite_links);
            update_option('topnepali_headless_rewrite_links', $rewrite_links);

            update_option(AstroPress::OPTION_SUBDOMAIN_SHIELD, $subdomain_shield);
            update_option('topnepali_headless_subdomain_shield', $subdomain_shield);

            update_option(AstroPress::OPTION_SUBDOMAIN_NOINDEX, $subdomain_noindex);
            update_option('topnepali_headless_subdomain_noindex', $subdomain_noindex);

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('SEO & Security settings saved successfully.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=seo'));
            exit;
        }

        // 3. Save Cloudflare R2 Credentials & CDN Options
        if (isset($_POST['astropress_save_r2']) && check_admin_referer('astropress_r2_action', 'astropress_r2_nonce')) {
            $account_id    = sanitize_text_field(wp_unslash($_POST['r2_account_id'] ?? ($_POST['r2g_account_id'] ?? '')));
            $access_key    = sanitize_text_field(wp_unslash($_POST['r2_access_key'] ?? ($_POST['r2g_access_key'] ?? '')));
            $posted_secret = trim(wp_unslash($_POST['r2_secret_key'] ?? ($_POST['r2g_secret_key'] ?? '')));
            
            $bucket = sanitize_text_field(wp_unslash($_POST['r2_bucket'] ?? ($_POST['r2g_bucket'] ?? '')));
            if (!empty($_POST['r2_bucket_select'])) {
                $bucket = sanitize_text_field(wp_unslash($_POST['r2_bucket_select']));
            } elseif (!empty($_POST['r2g_bucket_select'])) {
                $bucket = sanitize_text_field(wp_unslash($_POST['r2g_bucket_select']));
            }
            
            $custom_domain = sanitize_text_field(wp_unslash($_POST['r2_custom_domain'] ?? ($_POST['r2g_custom_domain'] ?? '')));

            if (!empty($account_id)) {
                update_option('r2g_account_id', $account_id);
                update_option('astropress_r2_account_id', $account_id);
            }
            if (!empty($access_key)) {
                update_option('r2g_access_key', $access_key);
                update_option('astropress_r2_access_key', $access_key);
            }

            if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
                $encrypted_secret = AstroPress_Encryption::encrypt($posted_secret);
                update_option('r2g_secret_key', $encrypted_secret);
                update_option('astropress_r2_secret_key', $encrypted_secret);
            }

            if (!empty($bucket)) {
                update_option('r2g_bucket', $bucket);
                update_option('astropress_r2_bucket', $bucket);
            }

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

        // 5. Save Quick Onboarding Form (v2.0 Gated Wizard)
        if (isset($_POST['astropress_save_onboarding']) && check_admin_referer('astropress_onboarding_action', 'astropress_onboarding_nonce')) {
            $frontend_url = esc_url_raw(rtrim(trim(wp_unslash($_POST['onboard_frontend_url'] ?? '')), '/'));
            $cf_token     = trim(wp_unslash($_POST['onboard_cf_api_token'] ?? ''));
            $cf_zone_id   = sanitize_text_field(wp_unslash($_POST['onboard_cf_zone_id'] ?? ''));
            $media_mode   = sanitize_key(wp_unslash($_POST['onboard_media_mode'] ?? 'wp'));

            // Collision check: Frontend URL cannot match WordPress backend
            $site_host     = wp_parse_url(home_url(), PHP_URL_HOST);
            $frontend_host = wp_parse_url($frontend_url, PHP_URL_HOST);

            if (!empty($frontend_url) && $site_host && $frontend_host && strtolower($site_host) === strtolower($frontend_host)) {
                set_transient('astropress_notice', array(
                    'type'    => 'error',
                    'message' => esc_html__('Error: Frontend URL cannot be identical to your WordPress backend URL. Headless architecture requires an independent domain or root domain (e.g., WordPress on wp.example.com and Astro on https://example.com).', 'astropress'),
                ), 30);
                wp_safe_redirect(admin_url('admin.php?page=astropress&tab=onboarding'));
                exit;
            }

            if (!empty($frontend_url)) {
                update_option(AstroPress::OPTION_FRONTEND_URL, $frontend_url);
                update_option('topnepali_headless_frontend_url', $frontend_url);
            }

            if (!empty($cf_token) && strpos($cf_token, '••••') === false) {
                $enc_cf = AstroPress_Encryption::encrypt($cf_token);
                update_option(AstroPress::OPTION_CF_API_TOKEN, $enc_cf);
                update_option('topnepali_headless_cf_api_token', $enc_cf);
                update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
            }

            if (!empty($cf_zone_id)) {
                update_option(AstroPress::OPTION_CF_ZONE_ID, $cf_zone_id);
                update_option('topnepali_headless_cf_zone_id', $cf_zone_id);
            }

            update_option(AstroPress::OPTION_MEDIA_MODE, $media_mode);

            // R2 Object Storage
            if ($media_mode === 'r2') {
                $account_id    = sanitize_text_field(wp_unslash($_POST['onboard_r2_account_id'] ?? ''));
                $bucket        = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket'] ?? ''));
                if (!empty($_POST['onboard_r2_bucket_select'])) {
                    $bucket = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket_select']));
                }
                $custom_domain = sanitize_text_field(wp_unslash($_POST['onboard_r2_custom_domain'] ?? ''));

                if (!empty($account_id)) {
                    update_option('r2g_account_id', $account_id);
                    update_option('astropress_r2_account_id', $account_id);
                }
                if (!empty($bucket)) {
                    update_option('r2g_bucket', $bucket);
                    update_option('astropress_r2_bucket', $bucket);
                }
                if (!empty($custom_domain)) {
                    $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
                    update_option('r2g_custom_domain', $clean_domain);
                    update_option('astropress_r2_custom_domain', $clean_domain);
                }

                update_option('r2g_auto_upload', 1);
                update_option('r2g_rewrite_urls', 1);
                update_option('r2g_compress_enabled', 1);
                update_option('r2g_compress_format', 'webp');
                update_option('r2g_compress_quality', 82);
            } else {
                update_option('r2g_auto_upload', 0);
                update_option('r2g_rewrite_urls', 0);
            }

            // Defaults & Core Integrations
            update_option(AstroPress::OPTION_SETUP_COMPLETED, '1');
            update_option(AstroPress::OPTION_CONNECTED, '1');
            update_option(AstroPress::OPTION_REWRITE_LINKS, '1');
            update_option(AstroPress::OPTION_PURGE_ON_SAVE, '1');
            update_option(AstroPress::OPTION_SUBDOMAIN_SHIELD, '1');
            update_option(AstroPress::OPTION_SUBDOMAIN_NOINDEX, '1');

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('AstroPress v2.0 Setup Complete! Dashboard unlocked, Cloudflare Edge connected, and cache management ready.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress'));
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

        // Clear Webhook Logs Action
        if (isset($_POST['astropress_clear_logs']) && check_admin_referer('astropress_clear_logs_action', 'astropress_clear_logs_nonce')) {
            $this->core->cache->clear_webhook_logs();
            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Webhook delivery logs cleared.', 'astropress'),
            ), 30);
            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=logs'));
            exit;
        }

        // Deactivate Legacy Plugins Action
        if (isset($_GET['astropress_deactivate_legacy']) && check_admin_referer('astropress_deactivate_legacy_nonce')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $legacy_slugs = array(
                'r2-by-grisma/r2-by-grisma.php',
                'topnepali-headless/topnepali-headless.php',
                'headless/headless.php',
                'headless/topnepali-headless.php',
            );
            deactivate_plugins($legacy_slugs);
            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Legacy plugins deactivated successfully! AstroPress is now exclusively managing all headless bridge and R2 media operations.', 'astropress'),
            ), 30);
            wp_safe_redirect(admin_url('admin.php?page=astropress'));
            exit;
        }
    }

    /**
     * AJAX: Test existing stored Revalidation Secret Key against Cloudflare Pages/Workers
     */
    public function ajax_test_key() {
        $this->verify_admin_ajax();

        $frontend_url = esc_url_raw(trim(wp_unslash($_POST['frontend_url'] ?? ''))) ?: $this->core->get_frontend_url();
        $secret       = sanitize_text_field(wp_unslash($_POST['secret'] ?? '')) ?: $this->core->get_secret();

        if (empty($frontend_url) || empty($secret)) {
            wp_send_json_error(array(
                'message' => 'Please provide both the Frontend URL and Revalidation Secret token.',
                'code'    => 404,
            ));
        }

        if (!AstroPress_Security::is_safe_external_url($frontend_url)) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid or forbidden frontend URL. Local loopback and reserved private IP ranges are blocked.', 'astropress'),
                'code'    => 400,
            ));
        }

        $test_endpoint = rtrim($frontend_url, '/') . '/api/revalidate';
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
     * Resilient & Strictly Validated AJAX Security Verification
     * Supports current, onboarding, and legacy compatibility nonces with mandatory capability checks.
     */
    public function verify_admin_ajax() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_REQUEST['nonce'] ?? ''));
        if (empty($nonce) || (
            !wp_verify_nonce($nonce, 'astropress_admin_nonce') &&
            !wp_verify_nonce($nonce, 'astropress_onboarding_nonce') &&
            !wp_verify_nonce($nonce, 'topnepali_admin_nonce') &&
            !wp_verify_nonce($nonce, 'r2g_admin_nonce')
        )) {
            wp_send_json_error(array('message' => esc_html__('Invalid or missing security token (nonce).', 'astropress')), 403);
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized permissions.', 'astropress')), 403);
        }
    }

    /**
     * AJAX: Get structured list of URLs to warm
     */
    public function ajax_get_warmup_urls() {
        $this->verify_admin_ajax();

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
        $this->verify_admin_ajax();

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
        $this->verify_admin_ajax();

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
        $this->verify_admin_ajax();

        $status = $this->core->cache->get_background_job_status();
        wp_send_json_success(array('job' => $status));
    }

    /**
     * AJAX: Pause background job
     */
    public function ajax_pause_bg_warm() {
        $this->verify_admin_ajax();

        $ok = $this->core->cache->pause_background_job();
        wp_send_json_success(array('paused' => $ok));
    }

    /**
     * AJAX: Resume background job
     */
    public function ajax_resume_bg_warm() {
        $this->verify_admin_ajax();

        $ok = $this->core->cache->resume_background_job();
        wp_send_json_success(array('resumed' => $ok));
    }

    /**
     * AJAX: Cancel background job
     */
    public function ajax_cancel_bg_warm() {
        $this->verify_admin_ajax();

        $ok = $this->core->cache->cancel_background_job();
        wp_send_json_success(array('cancelled' => $ok));
    }

    /**
     * AJAX: Clear finished/cancelled background job
     */
    public function ajax_clear_bg_warm() {
        $this->verify_admin_ajax();

        $this->core->cache->clear_background_job();
        wp_send_json_success(array('cleared' => true));
    }

    /**
     * AJAX: Test Cloudflare R2 Connection
     *
     * Supports both Single-Token Bearer REST Mode and S3 SigV4 Mode.
     */
    public function ajax_test_connection() {
        $this->verify_admin_ajax();

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: get_option('r2g_account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: get_option('r2g_access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_key = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        }

        $cf_token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($cf_token) || strpos($cf_token, '••••') !== false) {
            $cf_token = $this->core->get_cf_api_token();
        }

        $bucket        = sanitize_text_field($_POST['bucket'] ?? '') ?: get_option('r2g_bucket', '');
        $custom_domain = sanitize_text_field($_POST['custom_domain'] ?? '') ?: get_option('r2g_custom_domain', '');

        $has_s3 = !empty($access_key) && !empty($secret_key);
        $has_cf = !empty($cf_token);

        if (empty($account_id) || (!$has_s3 && !$has_cf)) {
            wp_send_json_error(array('message' => esc_html__('Please enter Cloudflare Account ID and either a Cloudflare API Token (Single-Token Mode) or S3 Access/Secret Keys.', 'astropress')));
        }
        if (empty($bucket)) {
            wp_send_json_error(array('message' => esc_html__('Please select or enter an R2 Bucket name.', 'astropress')));
        }

        $client = new AstroPress_R2_Client($account_id, $access_key, $secret_key, $bucket, $cf_token);
        $res = $client->test_connection($custom_domain);

        if ($res['success']) {
            wp_send_json_success($res);
        } else {
            wp_send_json_error($res);
        }
    }

    /**
     * AJAX: Create a new Cloudflare R2 Bucket
     */
    public function ajax_create_bucket() {
        $this->verify_admin_ajax();

        $bucket_name = sanitize_key(trim($_POST['bucket_name'] ?? ''));
        if (empty($bucket_name)) {
            wp_send_json_error(array('message' => esc_html__('Please enter a valid bucket name (lowercase letters, numbers, hyphens).', 'astropress')));
        }

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: get_option('r2g_account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: get_option('r2g_access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_key = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        }

        $cf_token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($cf_token) || strpos($cf_token, '••••') !== false) {
            $cf_token = $this->core->get_cf_api_token();
        }

        $client = new AstroPress_R2_Client($account_id, $access_key, $secret_key, $bucket_name, $cf_token);
        $res = $client->create_bucket($bucket_name);

        if (!empty($res['success'])) {
            update_option('r2g_bucket', $bucket_name);
            update_option('astropress_r2_bucket', $bucket_name);
            wp_send_json_success(array(
                'bucket'  => $bucket_name,
                'message' => sprintf(esc_html__('Bucket "%s" created successfully on Cloudflare R2.', 'astropress'), $bucket_name),
            ));
        }

        wp_send_json_error(array('message' => !empty($res['error']) ? $res['error'] : esc_html__('Failed to create bucket. Verify Cloudflare API Token permissions or credentials.', 'astropress')));
    }

    /**
     * AJAX: Deploy Cloudflare "Cache Everything" Page Rule / Cache Rule for Custom CDN Domain
     */
    public function ajax_deploy_cache_rule() {
        $this->verify_admin_ajax();

        $token   = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }
        $zone_id = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();
        $cdn_url = trim(sanitize_text_field($_POST['cdn_domain'] ?? '')) ?: get_option('r2g_custom_domain', '');

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare API Token and Zone ID must be configured first in Headless Bridge settings or Quick Connect.', 'astropress')));
        }

        $parsed = wp_parse_url($cdn_url);
        $host   = !empty($parsed['host']) ? $parsed['host'] : trim($cdn_url, '/');
        if (empty($host)) {
            wp_send_json_error(array('message' => esc_html__('Please enter a valid Custom CDN Domain (e.g. media.example.com).', 'astropress')));
        }
        if (!$this->is_allowed_cdn_domain($host)) {
            $roots = $this->get_allowed_domain_roots();
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('CDN Domain "%s" is not permitted. It must be a subdomain or hostname of your WordPress site or Astro frontend (Allowed root domains: %s).', 'astropress'),
                    esc_html($host),
                    esc_html(implode(', ', $roots))
                ),
            ));
        }

        // STEP 1: Attempt Modern Cloudflare Cache Rules (Rulesets API)
        $ruleset_entry_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/phases/http_request_cache_settings/entrypoint";
        $get_rs = wp_remote_get($ruleset_entry_url, array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        $cache_rule_payload = array(
            'expression'        => '(http.host eq "' . addslashes($host) . '")',
            'description'       => 'AstroPress 1-Year Cache Everything for R2 CDN (' . $host . ')',
            'action'            => 'set_cache_settings',
            'action_parameters' => array(
                'edge_ttl' => array(
                    'mode'    => 'override_origin',
                    'default' => 31536000,
                ),
                'browser_ttl' => array(
                    'mode'    => 'override_origin',
                    'default' => 31536000,
                ),
                'cache' => true,
            ),
            'enabled' => true,
        );

        if (!is_wp_error($get_rs)) {
            $get_code = wp_remote_retrieve_response_code($get_rs);
            if ($get_code === 200 || $get_code === 404) {
                $existing_rules = array();
                if ($get_code === 200) {
                    $rs_data = json_decode(wp_remote_retrieve_body($get_rs), true);
                    $existing_rules = $rs_data['result']['rules'] ?? array();

                    // Check if already in rules
                    foreach ($existing_rules as $r) {
                        if (strpos($r['expression'] ?? '', $host) !== false) {
                            wp_send_json_success(array(
                                'message'        => sprintf(esc_html__('Modern Cloudflare Cache Rule is already active for %s!', 'astropress'), $host),
                                'already_exists' => true,
                            ));
                        }
                    }
                }

                // Append new rule and update entrypoint
                $existing_rules[] = $cache_rule_payload;
                $put_rs = wp_remote_request($ruleset_entry_url, array(
                    'method'  => 'PUT',
                    'timeout' => 12,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode(array('rules' => $existing_rules)),
                ));

                if (!is_wp_error($put_rs) && in_array(wp_remote_retrieve_response_code($put_rs), array(200, 201), true)) {
                    wp_send_json_success(array(
                        'message' => sprintf(esc_html__('Successfully deployed Cloudflare Cache Rule with 1-Year Edge TTL for %s (Ruleset Engine)!', 'astropress'), $host),
                        'type'    => 'cache_rule',
                    ));
                }
            }
        }

        // STEP 2: Fallback to Cloudflare Page Rules (Legacy)
        $rule_pattern = "*{$host}/*";
        $list_res = wp_remote_get("https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules?status=active&per_page=50", array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (!is_wp_error($list_res)) {
            $list_body = json_decode(wp_remote_retrieve_body($list_res), true);
            if (!empty($list_body['result']) && is_array($list_body['result'])) {
                foreach ($list_body['result'] as $existing_rule) {
                    if (!empty($existing_rule['targets'][0]['constraint']['value']) && $existing_rule['targets'][0]['constraint']['value'] === $rule_pattern) {
                        wp_send_json_success(array(
                            'message'        => sprintf(esc_html__('Cloudflare "Cache Everything" Page Rule is already active for %s (Rule ID: %s)!', 'astropress'), $rule_pattern, $existing_rule['id']),
                            'rule_id'        => $existing_rule['id'],
                            'already_exists' => true,
                        ));
                    }
                }
            }
        }

        $page_rule_payload = array(
            'targets' => array(
                array(
                    'target'     => 'url',
                    'constraint' => array(
                        'operator' => 'matches',
                        'value'    => $rule_pattern,
                    ),
                ),
            ),
            'actions' => array(
                array('id' => 'cache_level', 'value' => 'cache_everything'),
                array('id' => 'edge_cache_ttl', 'value' => 31536000),
            ),
            'priority' => 1,
            'status'   => 'active',
        );

        $res = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone_id}/pagerules", array(
            'timeout' => 12,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode($page_rule_payload),
        ));

        if (!is_wp_error($res)) {
            $body = json_decode(wp_remote_retrieve_body($res), true);
            if (!empty($body['success'])) {
                wp_send_json_success(array(
                    'message' => sprintf(esc_html__('Successfully deployed Cloudflare "Cache Everything" Page Rule with 1-Year Edge TTL for %s!', 'astropress'), $rule_pattern),
                    'rule_id' => $body['result']['id'] ?? '',
                ));
            }
        }

        wp_send_json_error(array(
            'message' => sprintf(
                esc_html__('Failed to deploy Edge Cache Rule for "%s". Ensure your Cloudflare API Token has "Zone > Cache Rules: Edit" and "Zone > Page Rules: Edit" scopes.', 'astropress'),
                $host
            ),
        ));
    }

    /**
     * AJAX: Discover R2 Buckets
     */
    public function ajax_fetch_buckets() {
        $this->verify_admin_ajax();

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: get_option('r2g_account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: get_option('r2g_access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_key = AstroPress_Encryption::decrypt(get_option('r2g_secret_key', ''));
        }

        $cf_token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($cf_token) || strpos($cf_token, '••••') !== false) {
            $cf_token = $this->core->get_cf_api_token();
        }

        if (empty($account_id) || (empty($access_key) && empty($cf_token))) {
            wp_send_json_error(array('message' => esc_html__('Please provide Cloudflare Account ID and either a Cloudflare API Token or S3 keys.', 'astropress')));
        }

        $client = new AstroPress_R2_Client($account_id, $access_key, $secret_key, '', $cf_token);
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
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (empty($nonce) || (!wp_verify_nonce($nonce, 'astropress_admin_nonce') && !wp_verify_nonce($nonce, 'r2g_admin_nonce'))) {
            wp_send_json_error(array('message' => esc_html__('Invalid security token.', 'astropress')), 403);
        }
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized.', 'astropress')), 403);
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
                'original_size'   => $orig_size,
                'compressed_size' => $out_size,
                'saved_bytes'     => max(0, $orig_size - $out_size),
                'percent'         => ($orig_size > 0) ? round((($orig_size - $out_size) / $orig_size) * 100, 1) : 0,
                'data_uri'        => 'data:' . $mime . ';base64,' . $out_data,
                'engine_used'     => $res['engine_used'] ?? $engine,
            ));
        } else {
            wp_send_json_error(array('message' => !empty($res['error']) ? $res['error'] : 'Compression preview failed.'));
        }
    }

    /**
     * AJAX: Resend a logged webhook
     */
    public function ajax_resend_webhook() {
        $this->verify_admin_ajax();

        $log_id = sanitize_text_field($_POST['log_id'] ?? '');
        if (empty($log_id)) {
            wp_send_json_error(array('message' => esc_html__('Missing log ID.', 'astropress')));
        }

        $res = $this->core->cache->resend_webhook($log_id);
        if (!empty($res['success'])) {
            wp_send_json_success(array('message' => esc_html__('Webhook re-dispatched successfully.', 'astropress'), 'result' => $res));
        } else {
            wp_send_json_error(array('message' => $res['message'] ?? esc_html__('Failed to resend webhook.', 'astropress')));
        }
    }

    /**
     * AJAX: Clear Webhook Logs
     */
    public function ajax_clear_webhook_logs() {
        $this->verify_admin_ajax();

        $this->core->cache->clear_webhook_logs();
        wp_send_json_success(array('message' => esc_html__('Webhook logs cleared successfully.', 'astropress')));
    }

    /**
     * AJAX: Purge Cloudflare Zone Cache
     */
    public function ajax_purge_cf_zone() {
        $this->verify_admin_ajax();

        $token   = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }
        $zone_id = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();

        $purge_everything = !empty($_POST['purge_everything']);
        $urls_raw         = $_POST['urls'] ?? array();
        $urls             = is_array($urls_raw) ? array_map('sanitize_text_field', $urls_raw) : array();

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare API Token and Zone ID must be provided or configured.', 'astropress')));
        }

        $success = $this->core->cache->purge_cloudflare_zone($urls, $purge_everything, $token, $zone_id);
        if ($success) {
            wp_send_json_success(array('message' => esc_html__('Cloudflare Zone & CDN Edge cache purged successfully.', 'astropress')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Cloudflare Edge purge failed. Verify your API Token has "Zone > Cache Purge: Purge" scope and Zone ID is valid.', 'astropress')));
        }
    }

    /**
     * AJAX: Trigger Deploy Hook
     */
    public function ajax_trigger_deploy_hook() {
        $this->verify_admin_ajax();

        $success = $this->core->cache->trigger_deploy_hook('Manual Trigger from AstroPress Admin');
        if ($success) {
            wp_send_json_success(array('message' => esc_html__('Cloudflare Pages deploy hook triggered successfully.', 'astropress')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Deploy hook trigger failed. Verify your Deploy Hook URL.', 'astropress')));
        }
    }

    /**
     * AJAX: Live Auto-Detect Astro Frontend & Revalidation Bridge
     */
    public function ajax_detect_frontend() {
        $this->verify_admin_ajax();

        $url = trim(sanitize_text_field($_POST['url'] ?? ''));
        if (empty($url)) {
            $url = $this->core->get_frontend_url();
        }

        if (empty($url)) {
            wp_send_json_error(array('message' => esc_html__('Frontend URL is required.', 'astropress')));
        }

        if (!AstroPress_Security::is_safe_external_url($url)) {
            wp_send_json_error(array('message' => esc_html__('Invalid or forbidden frontend URL. Local loopback and reserved private IP ranges are blocked.', 'astropress')));
        }

        $result = $this->core->cache->detect_frontend($url);
        wp_send_json_success($result);
    }

    /**
     * AJAX: Zero-Touch Sync Environment Variables to Cloudflare Pages
     */
    public function ajax_sync_pages_env() {
        $this->verify_admin_ajax();

        $project_name = trim(sanitize_text_field($_POST['project_name'] ?? ''));
        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: get_option('r2g_account_id', '');
        $token        = trim(sanitize_text_field($_POST['token'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($project_name)) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare Pages project name is required.', 'astropress')));
        }

        $revalidate_secret = $this->core->get_revalidate_secret();
        $wp_api_url        = home_url();

        $env_vars = array(
            'REVALIDATE_SECRET' => $revalidate_secret,
            'WP_API_URL'        => $wp_api_url,
            'API_URL'           => $wp_api_url,
        );

        $result = $this->core->cache->sync_pages_env($project_name, $env_vars, $account_id, $token);
        if (!empty($result['success'])) {
            update_option('astropress_cf_pages_project', $project_name);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Audit Custom CDN Domain for DNS, R2 Bucket Attachment & Edge Rules
     */
    public function ajax_audit_cdn_domain() {
        $this->verify_admin_ajax();

        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? '')) ?: get_option('r2g_custom_domain', '');
        $zone_id       = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();
        $account_id    = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: get_option('r2g_account_id', '');
        $bucket_name   = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: get_option('r2g_bucket', '');
        $token         = trim(sanitize_text_field($_POST['token'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($custom_domain)) {
            wp_send_json_error(array('message' => esc_html__('Custom domain is required to audit.', 'astropress')));
        }

        $parsed_host = wp_parse_url($custom_domain, PHP_URL_HOST) ?: trim($custom_domain, '/');
        if (!$this->is_allowed_cdn_domain($parsed_host)) {
            $roots = $this->get_allowed_domain_roots();
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('CDN Domain "%s" is not permitted. It must be a subdomain or hostname of your WordPress site or Astro frontend (Allowed root domains: %s).', 'astropress'),
                    esc_html($parsed_host),
                    esc_html(implode(', ', $roots))
                ),
            ));
        }

        $result = $this->core->cache->audit_cdn_domain($custom_domain, $zone_id, $account_id, $bucket_name, $token);
        if (!empty($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: 1-Click Attach Custom Domain to Cloudflare R2 Bucket
     */
    public function ajax_attach_r2_custom_domain() {
        $this->verify_admin_ajax();

        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? '')) ?: get_option('r2g_custom_domain', '');
        $bucket_name   = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: get_option('r2g_bucket', '');
        $zone_id       = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();
        $zone_name     = trim(sanitize_text_field($_POST['zone_name'] ?? ''));
        $account_id    = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: get_option('r2g_account_id', '');
        $token         = trim(sanitize_text_field($_POST['token'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($custom_domain)) {
            wp_send_json_error(array('message' => esc_html__('Custom domain is required.', 'astropress')));
        }

        $parsed_host = wp_parse_url($custom_domain, PHP_URL_HOST) ?: trim($custom_domain, '/');
        if (!$this->is_allowed_cdn_domain($parsed_host)) {
            $roots = $this->get_allowed_domain_roots();
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('CDN Domain "%s" is not permitted. It must be a subdomain or hostname of your WordPress site or Astro frontend (Allowed root domains: %s).', 'astropress'),
                    esc_html($parsed_host),
                    esc_html(implode(', ', $roots))
                ),
            ));
        }

        $result = $this->core->cache->attach_r2_custom_domain($bucket_name, $custom_domain, $zone_id, $zone_name, $account_id, $token);
        if (!empty($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Cloudflare 1-Click Quick Connect Auto-Discovery
     */
    public function ajax_cf_quick_connect() {
        $this->verify_admin_ajax();

        $token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($token)) {
            wp_send_json_error(array('message' => esc_html__('Please provide a valid Cloudflare API Token.', 'astropress')));
        }

        // 1. Verify token
        $verify_res = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (is_wp_error($verify_res)) {
            wp_send_json_error(array('message' => 'Could not connect to Cloudflare: ' . $verify_res->get_error_message()));
        }

        $verify_body = json_decode(wp_remote_retrieve_body($verify_res), true);
        if (empty($verify_body['success'])) {
            wp_send_json_error(array('message' => esc_html__('Invalid or expired Cloudflare API Token.', 'astropress')));
        }

        // 2. Fetch Accounts
        $acc_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts?per_page=20', array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));
        $accounts = array();
        $matched_account = null;

        if (!is_wp_error($acc_res)) {
            $acc_body = json_decode(wp_remote_retrieve_body($acc_res), true);
            if (!empty($acc_body['result']) && is_array($acc_body['result'])) {
                foreach ($acc_body['result'] as $acc) {
                    $item = array(
                        'id'   => sanitize_text_field($acc['id'] ?? ''),
                        'name' => sanitize_text_field($acc['name'] ?? ''),
                    );
                    $accounts[] = $item;
                    if (!$matched_account) {
                        $matched_account = $item;
                    }
                }
            }
        }

        // 3. Fetch Zones
        $zone_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones?status=active&per_page=50', array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));
        $zones = array();
        $matched_zone = null;

        $site_host     = wp_parse_url(home_url(), PHP_URL_HOST);
        $frontend_host = wp_parse_url($this->core->get_frontend_url(), PHP_URL_HOST);

        if (!is_wp_error($zone_res)) {
            $zone_body = json_decode(wp_remote_retrieve_body($zone_res), true);
            if (!empty($zone_body['result']) && is_array($zone_body['result'])) {
                foreach ($zone_body['result'] as $z) {
                    $z_item = array(
                        'id'     => sanitize_text_field($z['id'] ?? ''),
                        'name'   => sanitize_text_field($z['name'] ?? ''),
                        'status' => sanitize_text_field($z['status'] ?? ''),
                    );
                    $zones[] = $z_item;

                    if ($site_host && (strpos($site_host, $z_item['name']) !== false || $site_host === $z_item['name'])) {
                        $matched_zone = $z_item;
                    } elseif ($frontend_host && (strpos($frontend_host, $z_item['name']) !== false || $frontend_host === $z_item['name'])) {
                        $matched_zone = $z_item;
                    }
                }
                if (!$matched_zone && !empty($zones)) {
                    $matched_zone = $zones[0];
                }
            }
        }

        // 4. Fetch R2 Buckets (if account found)
        $buckets = array();
        $can_r2 = false;
        if ($matched_account && !empty($matched_account['id'])) {
            $bucket_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($matched_account['id']) . '/r2/buckets', array(
                'timeout' => 10,
                'headers' => array('Authorization' => 'Bearer ' . $token),
            ));
            if (!is_wp_error($bucket_res)) {
                $b_code = wp_remote_retrieve_response_code($bucket_res);
                $b_body = json_decode(wp_remote_retrieve_body($bucket_res), true);
                if ($b_code === 200 && !empty($b_body['success'])) {
                    $can_r2 = true;
                    if (!empty($b_body['result']['buckets']) && is_array($b_body['result']['buckets'])) {
                        foreach ($b_body['result']['buckets'] as $b) {
                            $buckets[] = sanitize_text_field($b['name'] ?? '');
                        }
                    }
                }
            }
        }

        // 5. Fetch Pages projects (if account found)
        $pages_projects = array();
        $can_pages = false;
        if ($matched_account && !empty($matched_account['id'])) {
            $pages_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($matched_account['id']) . '/pages/projects', array(
                'timeout' => 10,
                'headers' => array('Authorization' => 'Bearer ' . $token),
            ));
            if (!is_wp_error($pages_res)) {
                $p_code = wp_remote_retrieve_response_code($pages_res);
                $p_body = json_decode(wp_remote_retrieve_body($pages_res), true);
                if ($p_code === 200 && !empty($p_body['success'])) {
                    $can_pages = true;
                    if (!empty($p_body['result']) && is_array($p_body['result'])) {
                        foreach ($p_body['result'] as $p) {
                            $pages_projects[] = array(
                                'name'      => sanitize_text_field($p['name'] ?? ''),
                                'subdomain' => sanitize_text_field($p['subdomain'] ?? ''),
                            );
                        }
                    }
                }
            }
        }

        // 6. Test Modern Cache Rules or Page Rules permission on Zone
        $can_cache_rules = false;
        if ($matched_zone && !empty($matched_zone['id'])) {
            // Check Modern Ruleset Engine entrypoint
            $ruleset_test = wp_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/rulesets/phases/http_request_cache_settings/entrypoint', array(
                'timeout' => 8,
                'headers' => array('Authorization' => 'Bearer ' . $token),
            ));
            if (!is_wp_error($ruleset_test)) {
                $rs_code = wp_remote_retrieve_response_code($ruleset_test);
                if (in_array($rs_code, array(200, 404), true)) {
                    $can_cache_rules = true;
                }
            }

            // Fallback test Page Rules
            if (!$can_cache_rules) {
                $rules_test_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/pagerules?per_page=1', array(
                    'timeout' => 8,
                    'headers' => array('Authorization' => 'Bearer ' . $token),
                ));
                if (!is_wp_error($rules_test_res)) {
                    $r_code = wp_remote_retrieve_response_code($rules_test_res);
                    if ($r_code === 200) {
                        $can_cache_rules = true;
                    }
                }
            }
        }

        // 6b. Test DNS records permission on Zone
        $can_dns = false;
        if ($matched_zone && !empty($matched_zone['id'])) {
            $dns_test_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/dns_records?per_page=1', array(
                'timeout' => 8,
                'headers' => array('Authorization' => 'Bearer ' . $token),
            ));
            if (!is_wp_error($dns_test_res)) {
                $d_code = wp_remote_retrieve_response_code($dns_test_res);
                if ($d_code === 200) {
                    $can_dns = true;
                }
            }
        }

        // 6c. Test Zone Cache Purge permission (non-destructive single dummy probe)
        $can_purge = false;
        if ($matched_zone && !empty($matched_zone['id'])) {
            $purge_probe_res = wp_remote_post('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/purge_cache', array(
                'timeout' => 8,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(array(
                    'files' => array('https://' . ($matched_zone['name'] ?? 'example.com') . '/astropress-healthcheck-probe')
                )),
            ));
            if (!is_wp_error($purge_probe_res)) {
                $p_code = wp_remote_retrieve_response_code($purge_probe_res);
                if ($p_code === 200) {
                    $can_purge = true;
                }
            }
        }

        // 7. Structured Token Abilities & Health Audit (All 7 Cloudflare Scopes)
        $abilities = array(
            'cache_purge' => array(
                'scope'       => 'Zone > Cache Purge: Purge',
                'granted'     => $can_purge,
                'label'       => esc_html__('Direct Edge Cache Purge', 'astropress'),
                'description' => $can_purge
                    ? esc_html__('Granted: Token is authorized for instantaneous Cloudflare Zone cache purging upon post publication.', 'astropress')
                    : esc_html__('Missing scope: "Zone > Cache Purge: Purge". Real-time Zone cache invalidation requires this scope.', 'astropress'),
            ),
            'cache_rules' => array(
                'scope'       => 'Zone > Cache Rules: Edit / Page Rules: Edit',
                'granted'     => $can_cache_rules,
                'label'       => esc_html__('1-Year Edge Cache Rule Deployment', 'astropress'),
                'description' => $can_cache_rules
                    ? esc_html__('Granted: Token is authorized to deploy 1-year Edge & Browser Cache Rules (Ruleset Engine & Page Rules).', 'astropress')
                    : esc_html__('Missing scope: "Zone > Cache Rules: Edit" (or "Zone > Page Rules: Edit"). Rule deployment requires this scope.', 'astropress'),
            ),
            'dns' => array(
                'scope'       => 'Zone > DNS: Edit',
                'granted'     => $can_dns,
                'label'       => esc_html__('DNS & Custom CDN Routing', 'astropress'),
                'description' => $can_dns
                    ? esc_html__('Granted: Token can auto-verify DNS records, manage CNAME routing, and attach custom CDN domains to R2.', 'astropress')
                    : esc_html__('Missing scope: "Zone > DNS: Edit". Required for 1-click custom domain R2 routing.', 'astropress'),
            ),
            'zones' => array(
                'scope'       => 'Zone > Zone: Read',
                'granted'     => !empty($zones),
                'label'       => esc_html__('Zone & Domain Detection', 'astropress'),
                'description' => !empty($zones) ? sprintf(esc_html__('Active (%s matched)', 'astropress'), $matched_zone['name'] ?? 'Zone found') : esc_html__('Missing scope: "Zone > Zone: Read".', 'astropress'),
            ),
            'r2_storage' => array(
                'scope'       => 'Account > Workers R2 Storage: Edit',
                'granted'     => $can_r2,
                'label'       => esc_html__('R2 Storage (Single-Token Mode)', 'astropress'),
                'description' => $can_r2
                    ? sprintf(esc_html__('Granted: %d bucket(s) discovered. Single-Token media offloading active (No S3 keys needed)!', 'astropress'), count($buckets))
                    : esc_html__('Missing scope: "Account > Workers R2 Storage: Edit". Required for Single-Token R2.', 'astropress'),
            ),
            'pages' => array(
                'scope'       => 'Account > Cloudflare Pages: Edit',
                'granted'     => $can_pages,
                'label'       => esc_html__('Cloudflare Pages Frontend', 'astropress'),
                'description' => $can_pages
                    ? sprintf(esc_html__('Granted: %d project(s) connected. Zero-touch env var sync available.', 'astropress'), count($pages_projects))
                    : esc_html__('Missing scope: "Account > Cloudflare Pages: Edit". Required for zero-touch secret injection.', 'astropress'),
            ),
            'accounts' => array(
                'scope'       => 'Account > Account Settings: Read',
                'granted'     => !empty($accounts),
                'label'       => esc_html__('Account Discovery', 'astropress'),
                'description' => !empty($accounts) ? sprintf(esc_html__('Active (%d account(s) accessible)', 'astropress'), count($accounts)) : esc_html__('Missing scope: "Account > Account Settings: Read".', 'astropress'),
            ),
        );

        // Auto-save if user checked "Apply Discovered Settings"
        $applied = false;
        if (!empty($_POST['apply'])) {
            update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
            if ($matched_account) {
                update_option('r2g_account_id', $matched_account['id']);
                update_option('astropress_r2_account_id', $matched_account['id']);
            }
            if ($matched_zone) {
                update_option(AstroPress::OPTION_CF_ZONE_ID, $matched_zone['id']);
                update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
            }
            if (!empty($buckets) && empty(get_option('r2g_bucket', ''))) {
                update_option('r2g_bucket', $buckets[0]);
                update_option('astropress_r2_bucket', $buckets[0]);
            }
            $applied = true;
        }

        // Cache discovery in transient
        $discovery_data = array(
            'account_id' => $matched_account['id'] ?? '',
            'zone_id'    => $matched_zone['id'] ?? '',
            'zone_name'  => $matched_zone['name'] ?? '',
            'buckets'    => $buckets,
        );
        set_transient('astropress_cf_discovery_cache', $discovery_data, 86400);

        wp_send_json_success(array(
            'message'         => esc_html__('Cloudflare resources discovered & token abilities audited successfully.', 'astropress'),
            'accounts'        => $accounts,
            'matched_account' => $matched_account,
            'zones'           => $zones,
            'matched_zone'    => $matched_zone,
            'buckets'         => $buckets,
            'pages_projects'  => $pages_projects,
            'abilities'       => $abilities,
            'can_purge'       => $can_purge,
            'can_r2'          => $can_r2,
            'can_dns'         => $can_dns,
            'can_cache_rules' => $can_cache_rules,
            'applied'         => $applied,
        ));
    }

    /**
     * AJAX: Fetch Cloudflare Ruleset Cache Rules (Phase: http_request_cache_settings)
     */
    public function ajax_get_cf_cache_rules() {
        $this->verify_admin_ajax();

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->core->get_cf_zone_id();

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare API Token and Zone ID must be configured first.', 'astropress')
            ));
        }

        $url = "https://api.cloudflare.com/client/v4/zones/" . rawurlencode($zone_id) . "/rulesets/phases/http_request_cache_settings/entrypoint";
        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 404) {
            wp_send_json_success(array(
                'rules'      => array(),
                'ruleset_id' => '',
                'message'    => esc_html__('No cache rules configured in Ruleset Engine.', 'astropress'),
            ));
        }

        if ($code === 200 && !empty($body['success'])) {
            $rules = $body['result']['rules'] ?? array();
            $ruleset_id = $body['result']['id'] ?? '';
            wp_send_json_success(array(
                'rules'      => $rules,
                'ruleset_id' => $ruleset_id,
            ));
        }

        $err_msg = $body['errors'][0]['message'] ?? sprintf(esc_html__('HTTP %d returned by Cloudflare Ruleset API.', 'astropress'), $code);
        wp_send_json_error(array('message' => $err_msg));
    }

    /**
     * AJAX: Deploy Ruleset Cache Rule Preset (or Custom Rule)
     */
    public function ajax_deploy_cf_cache_rule() {
        $this->verify_admin_ajax();

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->core->get_cf_zone_id();

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare API Token and Zone ID must be configured first.', 'astropress')
            ));
        }

        $preset      = sanitize_key($_POST['preset'] ?? '');
        $custom_desc = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
        $custom_expr = sanitize_text_field(wp_unslash($_POST['expression'] ?? ''));

        $rule_to_add = null;

        if ($preset === 'astro_assets') {
            $rule_to_add = array(
                'expression'        => '(starts_with(http.request.uri.path, "/_astro/") or starts_with(http.request.uri.path, "/assets/"))',
                'description'       => 'AstroPress: 1-Year Static Assets Edge Cache',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'       => true,
                    'edge_ttl'    => array('mode' => 'override_origin', 'default' => 31536000),
                    'browser_ttl' => array('mode' => 'override_origin', 'default' => 31536000),
                ),
                'enabled'           => true,
            );
        } elseif ($preset === 'r2_cdn') {
            $cdn_domain = sanitize_text_field(wp_unslash($_POST['cdn_domain'] ?? '')) ?: get_option('r2g_custom_domain', '');
            $parsed = wp_parse_url($cdn_domain);
            $host = !empty($parsed['host']) ? $parsed['host'] : trim($cdn_domain, '/');
            if (empty($host)) {
                wp_send_json_error(array('message' => esc_html__('Please provide a valid CDN domain for R2.', 'astropress')));
            }
            $rule_to_add = array(
                'expression'        => '(http.host eq "' . addslashes($host) . '")',
                'description'       => 'AstroPress: R2 Media CDN 1-Year Edge & Browser Cache (' . $host . ')',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'       => true,
                    'edge_ttl'    => array('mode' => 'override_origin', 'default' => 31536000),
                    'browser_ttl' => array('mode' => 'override_origin', 'default' => 31536000),
                ),
                'enabled'           => true,
            );
        } elseif ($preset === 'astro_ssr') {
            $frontend_url = $this->core->get_frontend_url();
            $host = wp_parse_url($frontend_url, PHP_URL_HOST) ?: $frontend_url;
            $rule_to_add = array(
                'expression'        => '(http.host eq "' . addslashes($host) . '")',
                'description'       => 'AstroPress: Global Edge Cache (Respect Origin Headers)',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'    => true,
                    'edge_ttl' => array('mode' => 'respect_origin'),
                ),
                'enabled'           => true,
            );
        } elseif (!empty($custom_expr)) {
            $edge_ttl_mode = sanitize_text_field($_POST['edge_ttl_mode'] ?? 'override_origin');
            $edge_ttl_val  = absint($_POST['edge_ttl_default'] ?? 31536000);
            $action_params = array('cache' => true);
            if ($edge_ttl_mode === 'override_origin') {
                $action_params['edge_ttl']    = array('mode' => 'override_origin', 'default' => $edge_ttl_val);
                $action_params['browser_ttl'] = array('mode' => 'override_origin', 'default' => $edge_ttl_val);
            } else {
                $action_params['edge_ttl'] = array('mode' => 'respect_origin');
            }

            $rule_to_add = array(
                'expression'        => $custom_expr,
                'description'       => !empty($custom_desc) ? $custom_desc : 'AstroPress Custom Cache Rule',
                'action'            => 'set_cache_settings',
                'action_parameters' => $action_params,
                'enabled'           => true,
            );
        } else {
            wp_send_json_error(array('message' => esc_html__('Invalid rule preset or expression.', 'astropress')));
        }

        // Read current ruleset
        $entry_url = "https://api.cloudflare.com/client/v4/zones/" . rawurlencode($zone_id) . "/rulesets/phases/http_request_cache_settings/entrypoint";
        $get_res = wp_remote_get($entry_url, array(
            'timeout' => 12,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
        ));

        $existing_rules = array();
        if (!is_wp_error($get_res) && wp_remote_retrieve_response_code($get_res) === 200) {
            $body = json_decode(wp_remote_retrieve_body($get_res), true);
            $existing_rules = $body['result']['rules'] ?? array();
        }

        $updated = false;
        foreach ($existing_rules as $idx => $r) {
            if (($r['expression'] ?? '') === $rule_to_add['expression']) {
                $existing_rules[$idx]['enabled']           = true;
                $existing_rules[$idx]['description']       = $rule_to_add['description'];
                $existing_rules[$idx]['action_parameters'] = $rule_to_add['action_parameters'];
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $existing_rules[] = $rule_to_add;
        }

        foreach ($existing_rules as &$r) {
            unset($r['last_updated'], $r['version']);
        }
        unset($r);

        $put_res = wp_remote_request($entry_url, array(
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(array('rules' => $existing_rules)),
        ));

        if (is_wp_error($put_res)) {
            wp_send_json_error(array('message' => $put_res->get_error_message()));
        }

        $put_code = wp_remote_retrieve_response_code($put_res);
        $put_body = json_decode(wp_remote_retrieve_body($put_res), true);

        if (in_array($put_code, array(200, 201), true) && !empty($put_body['success'])) {
            wp_send_json_success(array(
                'message' => esc_html__('Cache rule deployed successfully to Cloudflare Ruleset Engine!', 'astropress'),
                'rules'   => $put_body['result']['rules'] ?? array(),
            ));
        }

        $err_msg = $put_body['errors'][0]['message'] ?? sprintf(esc_html__('Cloudflare returned status code %d', 'astropress'), $put_code);
        wp_send_json_error(array('message' => $err_msg));
    }

    /**
     * AJAX: Toggle Cache Rule Enabled State
     */
    public function ajax_toggle_cf_cache_rule() {
        $this->verify_admin_ajax();

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->core->get_cf_zone_id();
        $rule_id = sanitize_text_field(wp_unslash($_POST['rule_id'] ?? ''));
        $enabled = !empty($_POST['enabled']);

        if (empty($token) || empty($zone_id) || empty($rule_id)) {
            wp_send_json_error(array('message' => esc_html__('Missing required parameters.', 'astropress')));
        }

        $entry_url = "https://api.cloudflare.com/client/v4/zones/" . rawurlencode($zone_id) . "/rulesets/phases/http_request_cache_settings/entrypoint";
        $get_res = wp_remote_get($entry_url, array(
            'timeout' => 12,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (is_wp_error($get_res) || wp_remote_retrieve_response_code($get_res) !== 200) {
            wp_send_json_error(array('message' => esc_html__('Could not retrieve current cache ruleset.', 'astropress')));
        }

        $body = json_decode(wp_remote_retrieve_body($get_res), true);
        $rules = $body['result']['rules'] ?? array();
        $found = false;

        foreach ($rules as &$r) {
            if (($r['id'] ?? '') === $rule_id) {
                $r['enabled'] = $enabled;
                $found = true;
            }
            unset($r['last_updated'], $r['version']);
        }
        unset($r);

        if (!$found) {
            wp_send_json_error(array('message' => esc_html__('Rule ID not found in current ruleset.', 'astropress')));
        }

        $put_res = wp_remote_request($entry_url, array(
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(array('rules' => $rules)),
        ));

        if (!is_wp_error($put_res) && in_array(wp_remote_retrieve_response_code($put_res), array(200, 201), true)) {
            wp_send_json_success(array(
                'message' => $enabled
                    ? esc_html__('Cache rule enabled.', 'astropress')
                    : esc_html__('Cache rule disabled.', 'astropress'),
            ));
        }

        wp_send_json_error(array('message' => esc_html__('Failed to update cache rule state on Cloudflare.', 'astropress')));
    }

    /**
     * AJAX: Delete Cache Rule from Ruleset
     */
    public function ajax_delete_cf_cache_rule() {
        $this->verify_admin_ajax();

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->core->get_cf_zone_id();
        $rule_id = sanitize_text_field(wp_unslash($_POST['rule_id'] ?? ''));

        if (empty($token) || empty($zone_id) || empty($rule_id)) {
            wp_send_json_error(array('message' => esc_html__('Missing required parameters.', 'astropress')));
        }

        $entry_url = "https://api.cloudflare.com/client/v4/zones/" . rawurlencode($zone_id) . "/rulesets/phases/http_request_cache_settings/entrypoint";
        $get_res = wp_remote_get($entry_url, array(
            'timeout' => 12,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));

        if (is_wp_error($get_res) || wp_remote_retrieve_response_code($get_res) !== 200) {
            wp_send_json_error(array('message' => esc_html__('Could not retrieve current cache ruleset.', 'astropress')));
        }

        $body = json_decode(wp_remote_retrieve_body($get_res), true);
        $rules = $body['result']['rules'] ?? array();
        $new_rules = array();

        foreach ($rules as $r) {
            if (($r['id'] ?? '') !== $rule_id) {
                unset($r['last_updated'], $r['version']);
                $new_rules[] = $r;
            }
        }

        $put_res = wp_remote_request($entry_url, array(
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(array('rules' => $new_rules)),
        ));

        if (!is_wp_error($put_res) && in_array(wp_remote_retrieve_response_code($put_res), array(200, 201), true)) {
            wp_send_json_success(array('message' => esc_html__('Cache rule deleted from Cloudflare Ruleset Engine.', 'astropress')));
        }

        wp_send_json_error(array('message' => esc_html__('Failed to delete cache rule on Cloudflare.', 'astropress')));
    }

    /**
     * AJAX: Danger Zone Disconnect & Reset Connection
     */
    public function ajax_reset_connection() {
        $this->verify_admin_ajax();

        $options_to_delete = array(
            AstroPress::OPTION_SETUP_COMPLETED,
            AstroPress::OPTION_MEDIA_MODE,
            AstroPress::OPTION_CONNECTED,
            AstroPress::OPTION_FRONTEND_URL,
            AstroPress::OPTION_SECRET,
            AstroPress::OPTION_CF_API_TOKEN,
            AstroPress::OPTION_CF_ZONE_ID,
            AstroPress::OPTION_CF_DIRECT_PURGE,
            'astropress_setup_mode',
            'astropress_cf_pages_project',
            'astropress_r2_account_id',
            'astropress_r2_access_key',
            'astropress_r2_secret_key',
            'astropress_r2_bucket',
            'astropress_r2_custom_domain',
            'topnepali_headless_frontend_url',
            'topnepali_headless_secret',
            'topnepali_headless_connected',
            'topnepali_headless_cf_api_token',
            'topnepali_headless_cf_zone_id',
            'r2g_account_id',
            'r2g_access_key',
            'r2g_secret_key',
            'r2g_bucket',
            'r2g_custom_domain',
        );

        foreach ($options_to_delete as $opt) {
            delete_option($opt);
        }

        delete_transient('astropress_cf_discovery_cache');

        update_option(AstroPress::OPTION_SETUP_COMPLETED, '0');
        update_option(AstroPress::OPTION_CONNECTED, '0');

        wp_send_json_success(array(
            'message'  => esc_html__('AstroPress connection and credentials have been reset. Redirecting to Setup Wizard...', 'astropress'),
            'redirect' => admin_url('admin.php?page=astropress&tab=onboarding'),
        ));
    }

    /**
     * Render Master Settings Page
     */
    public function render_settings_page() {
        $is_setup_done = $this->core->is_setup_completed();
        $active_tab    = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : ($is_setup_done ? 'dashboard' : 'onboarding');

        // Force Setup Wizard if setup is incomplete
        if (!$is_setup_done) {
            $active_tab = 'onboarding';
        }

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

            <?php if (!$is_setup_done): ?>
                <nav class="nav-tab-wrapper astropress-nav-tabs">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="nav-tab nav-tab-active">
                        <span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e('Setup Wizard', 'astropress'); ?>
                    </a>
                </nav>
            <?php else: ?>
                <nav class="nav-tab-wrapper astropress-nav-tabs">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress')); ?>" class="nav-tab <?php echo ($active_tab === 'dashboard') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Overview', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cache_rules')); ?>" class="nav-tab <?php echo ($active_tab === 'cache_rules') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-cloud"></span> <?php esc_html_e('Edge & Cache Rules', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" class="nav-tab <?php echo ($active_tab === 'r2') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-database"></span> <?php esc_html_e('Cloudflare R2', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=sync')); ?>" class="nav-tab <?php echo ($active_tab === 'sync') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Media Sync', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=optimizer')); ?>" class="nav-tab <?php echo ($active_tab === 'optimizer') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-format-image"></span> <?php esc_html_e('Image Optimizer', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=warmer')); ?>" class="nav-tab <?php echo ($active_tab === 'warmer') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-performance"></span> <?php esc_html_e('Cache Warmer', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=seo')); ?>" class="nav-tab <?php echo ($active_tab === 'seo') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-shield"></span> <?php esc_html_e('SEO & Security', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=logs')); ?>" class="nav-tab <?php echo ($active_tab === 'logs') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Purge Logs', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=integration')); ?>" class="nav-tab <?php echo ($active_tab === 'integration') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('Astro Snippets', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=settings')); ?>" class="nav-tab <?php echo ($active_tab === 'settings') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Settings & Reset', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=updates')); ?>" class="nav-tab <?php echo ($active_tab === 'updates') ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-cloud-saved"></span> <?php esc_html_e('Updates', 'astropress'); ?>
                    </a>
                </nav>
            <?php endif; ?>

            <div class="astropress-content">
                <?php
                try {
                    switch ($active_tab) {
                        case 'onboarding':
                            $this->render_tab_onboarding($frontend_url, $is_connected);
                            break;
                        case 'cache_rules':
                            $this->render_tab_cache_rules();
                            break;
                        case 'r2':
                            $this->render_tab_r2();
                            break;
                        case 'optimizer':
                            $this->render_tab_optimizer();
                            break;
                        case 'warmer':
                            $this->render_tab_warmer();
                            break;
                        case 'seo':
                            $this->render_tab_seo();
                            break;
                        case 'logs':
                            $this->render_tab_logs();
                            break;
                        case 'sync':
                            $this->render_tab_sync($stats);
                            break;
                        case 'integration':
                            $this->render_tab_integration($frontend_url);
                            break;
                        case 'settings':
                            $this->render_tab_settings();
                            break;
                        case 'updates':
                            $this->render_tab_updates();
                            break;
                        case 'bridge':
                            $this->render_tab_cache_rules();
                            break;
                        case 'dashboard':
                        default:
                            $this->render_tab_dashboard($frontend_url, $is_connected, $stats);
                            break;
                    }
                } catch (\Throwable $e) {
                    echo '<div class="notice notice-error" style="padding:16px; margin:20px 0; border-left:4px solid #dc2626; background:#fef2f2;">';
                    echo '<h3 style="margin-top:0; color:#b91c1c; font-size:14px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' . esc_html__('AstroPress Tab Error', 'astropress') . '</h3>';
                    echo '<p style="color:#7f1d1d; font-size:13px; margin:6px 0;"><strong>' . esc_html($e->getMessage()) . '</strong></p>';
                    echo '<div style="font-size:11px; color:#991b1b; margin-top:8px;"><strong>File:</strong> ' . esc_html($e->getFile()) . ' : line ' . esc_html($e->getLine()) . '</div>';
                    echo '<pre style="background:#fff; border:1px solid #fecaca; padding:10px; border-radius:4px; max-height:220px; overflow:auto; font-size:11px; margin-top:8px;">' . esc_html($e->getTraceAsString()) . '</pre>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Gated Setup Wizard (v2.0)
     */
    private function render_tab_onboarding($frontend_url, $is_connected) {
        $checks        = $this->get_preflight_checks();
        $cf_token      = $this->core->get_cf_api_token();
        $cf_zone_id    = $this->core->get_cf_zone_id();
        $account_id    = get_option('r2g_account_id', '');
        $bucket        = get_option('r2g_bucket', '');
        $custom_domain = get_option('r2g_custom_domain', '');
        $media_mode    = get_option(AstroPress::OPTION_MEDIA_MODE, 'wp');
        $allowed_roots = $this->get_allowed_domain_roots();
        $allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';

        // Auto-derive root frontend URL if empty or identical to WP backend
        if (empty($frontend_url) || $frontend_url === home_url()) {
            $frontend_url = $this->core->derive_frontend_url();
        }

        // Check cached discovery
        $cached_discovery = get_transient('astropress_cf_discovery_cache');
        $cached_buckets   = !empty($cached_discovery['buckets']) ? $cached_discovery['buckets'] : array();
        ?>
        <div class="astropress-wizard-wrap" style="max-width:880px; margin:20px auto;">
            <div class="astropress-card" style="padding:28px;">
                <div class="astropress-wizard-header" style="margin-bottom:24px; text-align:center;">
                    <span class="dashicons dashicons-cloud" style="font-size:42px; width:42px; height:42px; color:#2563eb; margin-bottom:8px;"></span>
                    <h2 style="font-size:22px; margin:0 0 6px 0;"><?php esc_html_e('Welcome to AstroPress v2.0 Setup Wizard', 'astropress'); ?></h2>
                    <p style="color:#64748b; font-size:14px; margin:0;">
                        <?php esc_html_e('Connect WordPress to Cloudflare Edge in 3 effortless steps. A single Scoped API Token powers direct cache purges, modern Cache Rules, and R2 media storage.', 'astropress'); ?>
                    </p>
                </div>

                <!-- Pre-flight server check -->
                <div class="astropress-preflight-card" style="margin-bottom:24px;">
                    <h3><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Pre-Flight Server Readiness Check', 'astropress'); ?></h3>
                    <div class="astropress-preflight-grid">
                        <?php foreach ($checks as $k => $c): ?>
                            <div class="astropress-preflight-item astropress-status-<?php echo esc_attr($c['status']); ?>">
                                <span class="astropress-preflight-badge">
                                    <?php echo ($c['status'] === 'pass') ? '✓' : (($c['status'] === 'fail') ? '✗' : 'ℹ'); ?>
                                </span>
                                <div>
                                    <strong><?php echo esc_html($c['label']); ?>:</strong> <?php echo esc_html($c['value']); ?>
                                    <div class="astropress-preflight-desc"><?php echo esc_html($c['message']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form method="post" action="" id="astropress-onboarding-form">
                    <?php wp_nonce_field('astropress_onboarding_action', 'astropress_onboarding_nonce'); ?>

                    <!-- STEP 1: Cloudflare Scoped Token -->
                    <div class="astropress-wizard-step" style="border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:20px; background:#ffffff;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                            <span style="background:#2563eb; color:#fff; font-weight:700; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:14px;">1</span>
                            <div>
                                <h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e('Cloudflare Scoped API Token', 'astropress'); ?></h3>
                                <p style="margin:2px 0 0 0; font-size:13px; color:#64748b;">
                                    <?php esc_html_e('One token to power your entire architecture (Edge Cache Purge, Ruleset Engine, DNS, and R2 Storage).', 'astropress'); ?>
                                </p>
                            </div>
                        </div>

                        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:14px; margin-bottom:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                <div>
                                    <strong style="color:#0f172a; font-size:13px;"><?php esc_html_e('Need an API Token?', 'astropress'); ?></strong>
                                    <p style="margin:2px 0 0 0; font-size:12px; color:#64748b;">
                                        <?php esc_html_e('Click below to create a pre-configured scoped token with all 7 exact permissions on Cloudflare.', 'astropress'); ?>
                                    </p>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <a href="<?php echo esc_url($this->get_cf_token_template_url()); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                        <span class="dashicons dashicons-external" style="vertical-align:-2px;"></span> <?php esc_html_e('Create Scoped Token on Cloudflare', 'astropress'); ?> &rarr;
                                    </a>
                                    <button type="button" class="button button-link" id="onboard-toggle-blueprint-btn" style="text-decoration:none; font-size:12px;">
                                        <span class="dashicons dashicons-editor-help" style="vertical-align:-2px;"></span> <?php esc_html_e('View Scopes Blueprint', 'astropress'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Blueprint Table (Collapsible) -->
                            <div id="onboard-blueprint-wrap" style="display:none; background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; padding:12px; margin-top:12px;">
                                <strong style="font-size:12px; color:#1e293b; display:block; margin-bottom:6px;"><?php esc_html_e('Required Token Scopes & Permissions:', 'astropress'); ?></strong>
                                <table class="widefat striped" style="font-size:12px; margin-bottom:8px;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Category', 'astropress'); ?></th>
                                            <th><?php esc_html_e('Scope', 'astropress'); ?></th>
                                            <th><?php esc_html_e('Access', 'astropress'); ?></th>
                                            <th><?php esc_html_e('Purpose in AstroPress', 'astropress'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($this->get_cf_permissions_blueprint() as $p): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($p['category']); ?></strong></td>
                                                <td><code><?php echo esc_html($p['name']); ?></code></td>
                                                <td><span class="astropress-badge" style="background:#eff6ff; color:#1d4ed8;"><?php echo esc_html($p['access']); ?></span></td>
                                                <td><?php echo esc_html($p['desc']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Token Input Box -->
                            <div style="display:flex; gap:8px; align-items:center; margin-top:14px; flex-wrap:wrap;">
                                <div style="flex:1; min-width:280px;">
                                    <input type="password" id="onboard_cf_api_token" name="onboard_cf_api_token" placeholder="<?php echo !empty($cf_token) ? '••••••••••••••••••••••••••••••••' : 'Paste Cloudflare API Token here...'; ?>" class="regular-text" style="width:100%;" autocomplete="new-password" />
                                </div>
                                <button type="button" class="button button-primary" id="onboard-quick-audit-btn">
                                    <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Auto-Discover & Verify Token', 'astropress'); ?>
                                </button>
                            </div>
                            <input type="hidden" id="onboard_cf_zone_id" name="onboard_cf_zone_id" value="<?php echo esc_attr($cf_zone_id); ?>" />
                            <input type="hidden" id="onboard_r2_account_id" name="onboard_r2_account_id" value="<?php echo esc_attr($account_id); ?>" />
                        </div>

                        <!-- Live Audit Results Container -->
                        <div id="onboard-audit-results" style="<?php echo (!empty($cf_token) && !empty($cf_zone_id)) ? '' : 'display:none;'; ?> margin-top:12px;">
                            <?php if (!empty($cf_token) && !empty($cf_zone_id)): ?>
                                <div class="astropress-detect-pill is-cf" style="padding:10px 14px;">
                                    <span class="dashicons dashicons-yes-alt" style="color:#059669; font-size:18px;"></span>
                                    <span>Cloudflare Scoped Token verified and Zone ID active (<code><?php echo esc_html(substr($cf_zone_id, 0, 10)); ?>••••</code>).</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- STEP 2: Headless Astro Frontend URL -->
                    <div class="astropress-wizard-step" style="border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:20px; background:#ffffff;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                            <span style="background:#2563eb; color:#fff; font-weight:700; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:14px;">2</span>
                            <div>
                                <h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e('Headless Astro Frontend URL', 'astropress'); ?></h3>
                                <p style="margin:2px 0 0 0; font-size:13px; color:#64748b;">
                                    <?php esc_html_e('The public URL where your Astro website is hosted (e.g., https://example.com). Auto-derived from your root domain.', 'astropress'); ?>
                                </p>
                            </div>
                        </div>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                            <input type="url" id="onboard_frontend_url" name="onboard_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" style="flex:1; min-width:240px;" required />
                            <button type="button" class="button button-secondary" id="onboard-detect-frontend-btn">
                                <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Ping & Verify Reachability', 'astropress'); ?>
                            </button>
                        </div>
                        <div id="onboard-frontend-collision-warning" style="display:none; margin-top:8px; padding:10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#991b1b; font-size:13px;">
                            <strong><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> <?php esc_html_e('URL Collision Error:', 'astropress'); ?></strong>
                            <?php esc_html_e('Frontend URL cannot match your WordPress backend installation. WordPress should be on a subdomain (e.g. wp.example.com) and Astro on your canonical root domain (https://example.com).', 'astropress'); ?>
                        </div>
                        <div id="onboard-frontend-detect-badge" style="display:none; margin-top:8px; max-width:650px;"></div>
                    </div>

                    <!-- STEP 3: Media Storage Mode -->
                    <div class="astropress-wizard-step" style="border:1px solid #e2e8f0; border-radius:8px; padding:20px; margin-bottom:24px; background:#ffffff;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                            <span style="background:#2563eb; color:#fff; font-weight:700; width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:14px;">3</span>
                            <div>
                                <h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e('Media Storage & CDN Offloading', 'astropress'); ?></h3>
                                <p style="margin:2px 0 0 0; font-size:13px; color:#64748b;">
                                    <?php esc_html_e('Choose how media library uploads should be handled.', 'astropress'); ?>
                                </p>
                            </div>
                        </div>

                        <div style="max-width:650px; margin-bottom:16px;">
                            <label for="onboard_media_mode" style="display:block; font-weight:600; font-size:13px; margin-bottom:6px;">
                                <?php esc_html_e('Select Media Offloading Method:', 'astropress'); ?>
                            </label>
                            <select id="onboard_media_mode" name="onboard_media_mode" style="width:100%; max-width:400px; font-size:14px; padding:6px 10px;">
                                <option value="wp" <?php selected($media_mode, 'wp'); ?>><?php esc_html_e('WordPress Local Storage (Default - Store on Hosting Server)', 'astropress'); ?></option>
                                <option value="r2" <?php selected($media_mode, 'r2'); ?>><?php esc_html_e('Cloudflare R2 Object Storage (Zero Egress + Global CDN)', 'astropress'); ?></option>
                            </select>
                        </div>

                        <!-- R2 Config Fields (revealed when R2 is chosen) -->
                        <div id="onboard-r2-fields-wrap" style="<?php echo ($media_mode === 'r2') ? '' : 'display:none;'; ?> background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:16px;">
                            <h4 style="margin:0 0 12px 0; font-size:14px; color:#1e293b;">
                                <?php esc_html_e('Cloudflare R2 Storage Configuration', 'astropress'); ?>
                            </h4>

                            <table class="form-table" style="margin:0;">
                                <tr>
                                    <th scope="row" style="padding:10px 10px 10px 0;"><label for="onboard_r2_bucket"><?php esc_html_e('R2 Bucket Name', 'astropress'); ?></label></th>
                                    <td style="padding:10px 0;">
                                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                            <input type="text" id="onboard_r2_bucket" name="onboard_r2_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" placeholder="e.g. topnepali" style="flex:1; min-width:200px;" />
                                            <select id="onboard_r2_bucket_select" name="onboard_r2_bucket_select" class="regular-text" style="<?php echo !empty($cached_buckets) ? '' : 'display:none;'; ?> flex:1; min-width:200px;">
                                                <option value=""><?php esc_html_e('-- Discovered Buckets --', 'astropress'); ?></option>
                                                <?php foreach ($cached_buckets as $cb): ?>
                                                    <option value="<?php echo esc_attr($cb); ?>" <?php selected($bucket, $cb); ?>><?php echo esc_html($cb); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button button-secondary" id="onboard-fetch-buckets">
                                                <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Discover Buckets', 'astropress'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary" id="onboard-btn-show-create-bucket">
                                                <span class="dashicons dashicons-plus-alt2" style="vertical-align:-2px;"></span> <?php esc_html_e('+ Create Bucket', 'astropress'); ?>
                                            </button>
                                        </div>

                                        <!-- Inline Create Bucket Box -->
                                        <div id="onboard-create-bucket-wrap" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px; margin-top:10px; max-width:650px;">
                                            <label for="onboard_new_bucket_name" style="display:block; font-size:12px; font-weight:600; color:#166534; margin-bottom:4px;">
                                                <?php esc_html_e('New Bucket Name:', 'astropress'); ?>
                                            </label>
                                            <div style="display:flex; gap:8px; align-items:center;">
                                                <input type="text" id="onboard_new_bucket_name" placeholder="lowercase letters, numbers, hyphens" class="regular-text" style="flex:1;" />
                                                <button type="button" id="onboard-btn-confirm-create-bucket" class="button button-primary button-small">
                                                    <?php esc_html_e('Create & Select', 'astropress'); ?>
                                                </button>
                                                <button type="button" id="onboard-btn-cancel-create-bucket" class="button button-secondary button-small">
                                                    <?php esc_html_e('Cancel', 'astropress'); ?>
                                                </button>
                                            </div>
                                            <div id="onboard-create-bucket-status" style="margin-top:6px; font-size:12px;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row" style="padding:10px 10px 10px 0;"><label for="onboard_r2_custom_domain"><?php esc_html_e('Public CDN Domain', 'astropress'); ?></label></th>
                                    <td style="padding:10px 0;">
                                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                                            <input type="text" id="onboard_r2_custom_domain" name="onboard_r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" style="flex:1; min-width:240px;" placeholder="media.example.com" />
                                            <button type="button" class="button button-secondary" id="onboard-audit-cdn-btn">
                                                <span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit CDN & Rules', 'astropress'); ?>
                                            </button>
                                            <button type="button" class="button button-secondary" id="onboard-deploy-cache-rule-btn">
                                                <span class="dashicons dashicons-cloud" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Cache Rule', 'astropress'); ?>
                                            </button>
                                        </div>
                                        <p class="description">
                                            <?php printf(
                                                esc_html__('Custom CDN subdomain hooked to your R2 bucket. Must be a subdomain of %s (e.g. media.%s).', 'astropress'),
                                                '<code>' . esc_html($allowed_roots_str) . '</code>',
                                                esc_html($allowed_roots[0] ?? 'example.com')
                                            ); ?>
                                        </p>
                                        <div id="onboard-cdn-audit-box" style="display:none; margin-top:10px; max-width:650px;"></div>
                                        <div id="onboard-cache-rule-status" style="margin-top:6px; font-size:12px;"></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div style="text-align:center; margin-top:28px;">
                        <button type="submit" name="astropress_save_onboarding" id="onboard-submit-btn" class="button button-primary button-hero" style="min-width:280px; font-size:16px;">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align:-3px;"></span> <?php esc_html_e('Complete Setup & Launch Dashboard', 'astropress'); ?> &rarr;
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Dashboard & Overview
     */
    private function render_tab_dashboard($frontend_url, $is_connected, $stats) {
        $total_media = $stats['total_wp'] ?? ($stats['total'] ?? 0);
        $offloaded   = $stats['synced'] ?? ($stats['offloaded'] ?? 0);
        $percentage  = ($total_media > 0) ? round(($offloaded / $total_media) * 100, 1) : 0;
        $saved_space = size_format($stats['savings_bytes'] ?? ($stats['saved_bytes'] ?? 0), 2);
        ?>
        <div class="astropress-grid-2">
            <div class="astropress-card">
                <h2><?php esc_html_e('System Status & Diagnostics', 'astropress'); ?></h2>
                <table class="astropress-status-table">
                    <tr>
                        <td><strong><?php esc_html_e('Headless Frontend URL', 'astropress'); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url($frontend_url); ?>" target="_blank" rel="noopener">
                                <code><?php echo esc_html($frontend_url); ?></code> <span class="dashicons dashicons-external" style="font-size:13px;"></span>
                            </a>
                        </td>
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
                    <tr>
                        <td><strong><?php esc_html_e('Subdomain Shield', 'astropress'); ?></strong></td>
                        <td>
                            <?php if ($this->core->should_shield_subdomain()): ?>
                                <span class="astropress-pill" style="background:#def7ec;color:#03543f;">301 Redirect Active</span>
                            <?php else: ?>
                                <span class="astropress-pill" style="background:#f3f4f6;color:#4b5563;">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="astropress-btn-group" style="margin-top:20px;">
                    <button type="button" class="button button-primary" id="astropress-test-handshake">
                        <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Test Webhook Handshake', 'astropress'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e('Setup Wizard', 'astropress'); ?>
                    </a>
                </div>
                <div id="astropress-handshake-status" style="margin-top:10px;"></div>
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
        $cf_token      = $this->core->get_cf_api_token();
        $zone_id       = $this->core->get_cf_zone_id();
        $r2_bucket     = get_option('r2g_bucket', '');
        $is_configured = (!empty($cf_token) && !empty($frontend_url));
        ?>
        <div class="astropress-card" style="margin-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <span class="dashicons dashicons-cloud" style="font-size:32px; width:32px; height:32px; color:<?php echo $is_configured ? '#059669' : '#f6821f'; ?>; margin-top:2px;"></span>
                    <div>
                        <h3 style="margin:0 0 4px 0;">
                            <?php echo $is_configured ? esc_html__('Cloudflare & Astro Architecture Status', 'astropress') : esc_html__('Setup & Architecture Incomplete', 'astropress'); ?>
                        </h3>
                        <p style="margin:0; color:#64748b;">
                            <?php if ($is_configured): ?>
                                <?php esc_html_e('Your headless WordPress instance is unified with Cloudflare Edge and Astro. Manage all credentials and pipeline settings via the Setup Wizard.', 'astropress'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Configure your single Cloudflare Scoped API Token, Astro frontend sync, and R2 media offloading through the unified Setup Wizard.', 'astropress'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="astropress-btn-group" style="display:flex; gap:8px; align-items:center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php echo $is_configured ? esc_html__('Configure in Setup Wizard', 'astropress') : esc_html__('Launch Setup Wizard &rarr;', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=logs')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-list-view" style="vertical-align:-2px;"></span> <?php esc_html_e('Webhook Logs', 'astropress'); ?>
                    </a>
                </div>
            </div>

            <?php if ($is_configured): ?>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-top:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
                    <div>
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Astro Frontend', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a; word-break:break-all;"><?php echo esc_html($frontend_url); ?></span>
                    </div>
                    <div>
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Cloudflare Zone ID', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a;"><?php echo !empty($zone_id) ? esc_html(substr($zone_id, 0, 10) . '••••') : esc_html__('Not Configured', 'astropress'); ?></span>
                    </div>
                    <div>
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('R2 Bucket', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a;"><?php echo !empty($r2_bucket) ? esc_html($r2_bucket) : esc_html__('Local Only (Not Enabled)', 'astropress'); ?></span>
                    </div>
                    <div>
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Cloudflare Scoped Token', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#059669; font-weight:600;"><span class="dashicons dashicons-shield-alt" style="font-size:15px; width:15px; height:15px; vertical-align:-2px;"></span> <?php esc_html_e('Connected & Masked', 'astropress'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Tab: Cloudflare Edge & Modern Ruleset Cache Rules Manager
     */
    private function render_tab_cache_rules() {
        $token         = $this->core->get_cf_api_token();
        $zone_id       = $this->core->get_cf_zone_id();
        $frontend      = $this->core->get_frontend_url();
        $frontend_host = wp_parse_url($frontend, PHP_URL_HOST) ?: $frontend;
        $cdn_domain    = get_option('r2g_custom_domain', '');
        ?>
        <div class="astropress-card" style="max-width:960px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0 0 4px 0;"><?php esc_html_e('Cloudflare Edge & Cache Rules Manager', 'astropress'); ?></h2>
                    <p style="margin:0; color:#64748b; font-size:13px;">
                        <?php esc_html_e('Manage modern Cloudflare Ruleset Engine Cache Rules (http_request_cache_settings) directly from WordPress. No Page Rules required.', 'astropress'); ?>
                    </p>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="button button-secondary" id="astropress-refresh-cache-rules-btn">
                        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Refresh Rules', 'astropress'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="astropress-purge-all-zone-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge All Zone Cache', 'astropress'); ?>
                    </button>
                </div>
            </div>

            <!-- 1-Click Presets -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 10px 0; font-size:14px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-superhero" style="color:#2563eb;"></span>
                    <?php esc_html_e('1-Click Cache Rule Presets', 'astropress'); ?>
                </h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                    <!-- Preset 1: Astro Static Assets -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('Astro Static Assets (1-Year TTL)', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                <code>/_astro/*</code> &amp; <code>/assets/*</code> edge &amp; browser cache for 31,536,000s (1 year).
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="astro_assets">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Preset 2: Astro SSR Origin Headers -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('Astro SSR HTML Cache', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                Matches <code><?php echo esc_html($frontend_host); ?></code> and respects origin cache headers (s-maxage &amp; stale-while-revalidate).
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="astro_ssr">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Preset 3: R2 Media CDN -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('R2 Media CDN (1-Year TTL)', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                Matches <code><?php echo !empty($cdn_domain) ? esc_html($cdn_domain) : 'media.example.com'; ?></code> and forces 1-year Edge Cache Everything.
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="r2_cdn">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>
                </div>
                <div id="astropress-preset-deploy-status" style="margin-top:10px; font-size:12px;"></div>
            </div>

            <!-- Active Cache Rules Table -->
            <div style="margin-bottom:24px;">
                <h3 style="margin:0 0 10px 0; font-size:14px; color:#1e293b;">
                    <?php esc_html_e('Active Cloudflare Ruleset Cache Rules:', 'astropress'); ?>
                </h3>
                <div id="astropress-rules-table-container">
                    <p style="color:#64748b; font-size:13px;">
                        <span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span>
                        <?php esc_html_e('Loading active cache rules from Cloudflare Ruleset API...', 'astropress'); ?>
                    </p>
                </div>
            </div>

            <hr style="margin:24px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Direct Zone Cache Purge Controls -->
            <div>
                <h3 style="margin:0 0 8px 0; font-size:14px; color:#1e293b;">
                    <?php esc_html_e('Direct Cloudflare Zone Edge Purge', 'astropress'); ?>
                </h3>
                <p style="font-size:13px; color:#64748b; margin:0 0 14px 0;">
                    <?php esc_html_e('Purge specific URLs or your entire Cloudflare Zone edge cache instantly via Cloudflare API v4.', 'astropress'); ?>
                </p>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; max-width:700px;">
                    <input type="text" id="astropress-purge-urls-input" placeholder="e.g. /news, /district/kathmandu (comma-separated or full URLs)" class="regular-text" style="flex:1; min-width:280px;" />
                    <button type="button" class="button button-secondary" id="astropress-purge-urls-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge URLs', 'astropress'); ?>
                    </button>
                </div>
                <div id="astropress-direct-purge-status" style="margin-top:8px; font-size:12px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: General Settings & Danger Zone
     */
    private function render_tab_settings() {
        $cf_deploy_hook = $this->core->get_cf_deploy_hook();
        $cf_token       = $this->core->get_cf_api_token();
        $cf_zone_id     = $this->core->get_cf_zone_id();
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Automation & Cache Invalidation Policies', 'astropress'); ?></h2>
            <p><?php esc_html_e('Configure automatic edge cache invalidation triggers and headless SEO headers.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_automation_action', 'astropress_automation_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Automatic Purge Triggers', 'astropress'); ?></th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="purge_on_save" value="1" <?php checked($this->core->should_purge_on_save()); ?> /> <?php esc_html_e('Purge post / page cache on publish, update or trash', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_feeds" value="1" <?php checked($this->core->should_purge_feeds()); ?> /> <?php esc_html_e('Purge RSS and Atom feeds on new post publication', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_taxonomy" value="1" <?php checked($this->core->should_purge_taxonomy()); ?> /> <?php esc_html_e('Purge category and tag archives when terms are edited', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="purge_menu" value="1" <?php checked($this->core->should_purge_menu()); ?> /> <?php esc_html_e('Purge global cache when navigation menus are updated', 'astropress'); ?></label><br />
                                <label><input type="checkbox" name="debounce_enabled" value="1" <?php checked($this->core->should_debounce_webhooks()); ?> /> <?php esc_html_e('Debounce requests (batch multiple invalidations into a single payload)', 'astropress'); ?></label><br />
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
                    <tr>
                        <th scope="row"><label for="astropress_cf_deploy_hook"><?php esc_html_e('Cloudflare Pages Deploy Hook', 'astropress'); ?></label></th>
                        <td>
                            <div class="astropress-inline-form">
                                <input type="url" id="astropress_cf_deploy_hook" name="cf_deploy_hook" value="<?php echo esc_attr($cf_deploy_hook); ?>" class="regular-text" placeholder="https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/..." />
                                <button type="button" class="button" id="astropress-trigger-deploy-now"><?php esc_html_e('Trigger Deploy', 'astropress'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Optional: Trigger a static site build in Cloudflare Pages when a full cache purge is requested.', 'astropress'); ?></p>
                            <div id="astropress-deploy-status" style="margin-top:8px;"></div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_automation" class="button button-primary"><?php esc_html_e('Save Settings', 'astropress'); ?></button>
                </p>
            </form>

            <hr style="margin:35px 0 25px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Danger Zone: Disconnect & Reset -->
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:18px;">
                <h3 style="margin:0 0 6px 0; color:#991b1b; font-size:15px; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-warning" style="color:#dc2626;"></span>
                    <?php esc_html_e('Danger Zone: Disconnect & Reset Connection', 'astropress'); ?>
                </h3>
                <p style="margin:0 0 14px 0; color:#7f1d1d; font-size:13px; line-height:1.5;">
                    <?php esc_html_e('Disconnects WordPress from Cloudflare, purges encrypted tokens and stored zone configurations, and resets AstroPress to a fresh, unconfigured state. The dashboard will lock until the Setup Wizard is completed again.', 'astropress'); ?>
                </p>
                <button type="button" class="button button-secondary" id="astropress-reset-connection-btn" style="color:#b91c1c; border-color:#f87171; background:#ffffff;">
                    <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Disconnect & Reset AstroPress', 'astropress'); ?>
                </button>
                <div id="astropress-reset-status" style="margin-top:10px; font-size:12px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Headless Bridge (Redirects to Cache Rules in v2.0)
     */
    private function render_tab_bridge($frontend_url, $is_connected) {
        $this->render_tab_cache_rules();
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
        $cf_token      = $this->core->get_cf_api_token();
        $allowed_roots = $this->get_allowed_domain_roots();
        $allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';
        ?>
        <div class="astropress-card" style="max-width:850px; margin-bottom:20px; background:#f8fafc; border-left:4px solid #f6821f;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="dashicons dashicons-cloud" style="font-size:28px; width:28px; height:28px; color:#f6821f;"></span>
                    <div>
                        <h4 style="margin:0; font-size:14px;"><?php esc_html_e('Unified Cloudflare Token Pipeline', 'astropress'); ?></h4>
                        <p style="margin:2px 0 0 0; color:#64748b; font-size:12px;">
                            <?php if (!empty($cf_token)): ?>
                                <?php esc_html_e('Cloudflare Scoped Token is active. R2 media storage is authenticated natively without requiring AWS S3 keys.', 'astropress'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Connect your single Cloudflare Scoped Token in the Setup Wizard for zero-config media offloading.', 'astropress'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php esc_html_e('Setup Wizard &rarr;', 'astropress'); ?>
                </a>
            </div>
        </div>

        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Cloudflare R2 Media Storage & CDN Delivery', 'astropress'); ?></h2>
            <p><?php esc_html_e('Offload media library uploads to zero-egress Cloudflare R2 object storage with fast public CDN delivery.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_r2_action', 'astropress_r2_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="r2_account_id"><?php esc_html_e('Cloudflare Account ID', 'astropress'); ?></label></th>
                        <td>
                            <input type="text" id="r2_account_id" name="r2_account_id" value="<?php echo esc_attr($account_id); ?>" class="regular-text" placeholder="32-character hex Account ID" />
                            <p class="description"><?php esc_html_e('Auto-detected from your Cloudflare Scoped Token or found in Cloudflare Overview.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_bucket"><?php esc_html_e('R2 Bucket Name', 'astropress'); ?></label></th>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                                <input type="text" id="r2_bucket" name="r2_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" style="flex:1; min-width:200px;" placeholder="e.g. topnepali" />
                                <select id="r2_bucket_select" name="r2_bucket_select" class="regular-text" style="display:none; flex:1; min-width:200px;">
                                    <option value=""><?php esc_html_e('-- Choose Discovered Bucket --', 'astropress'); ?></option>
                                    <?php if (!empty($bucket)): ?>
                                        <option value="<?php echo esc_attr($bucket); ?>" selected><?php echo esc_html($bucket); ?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="button button-secondary" id="astropress-fetch-buckets">
                                    <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Discover Buckets', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-btn-show-create-bucket">
                                    <span class="dashicons dashicons-plus-alt2" style="vertical-align:-2px;"></span> <?php esc_html_e('+ Create Bucket', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-link" id="astropress-btn-toggle-bucket-input" style="display:none; text-decoration:none; font-size:12px;">
                                    <?php esc_html_e('Type Manually', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-test-r2">
                                    <span class="dashicons dashicons-yes" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Connection', 'astropress'); ?>
                                </button>
                            </div>

                            <!-- Inline Create Bucket Box -->
                            <div id="astropress-create-bucket-wrap" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:14px; margin-top:10px; max-width:650px;">
                                <label for="astropress_new_bucket_name" style="display:block; font-size:13px; font-weight:600; color:#166534; margin-bottom:6px;">
                                    <?php esc_html_e('Create New Cloudflare R2 Bucket:', 'astropress'); ?>
                                </label>
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    <input type="text" id="astropress_new_bucket_name" placeholder="lowercase letters, numbers, hyphens" class="regular-text" style="flex:1; min-width:200px;" />
                                    <button type="button" id="astropress-btn-confirm-create-bucket" class="button button-primary">
                                        <?php esc_html_e('Create & Use Bucket', 'astropress'); ?>
                                    </button>
                                    <button type="button" id="astropress-btn-cancel-create-bucket" class="button button-secondary">
                                        <?php esc_html_e('Cancel', 'astropress'); ?>
                                    </button>
                                </div>
                                <div id="astropress-create-bucket-status" style="margin-top:8px; font-size:12px;"></div>
                            </div>

                            <div id="astropress-r2-status" style="margin-top:8px; font-size:12px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_custom_domain"><?php esc_html_e('Public CDN Domain', 'astropress'); ?></label></th>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                                <input type="text" id="r2_custom_domain" name="r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" placeholder="media.example.com" style="flex:1; min-width:240px;" />
                                <button type="button" class="button button-secondary" id="r2-audit-cdn-btn">
                                    <span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit CDN & Rules', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-btn-deploy-cache-rule">
                                    <span class="dashicons dashicons-cloud" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Cache Rule', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="r2-purge-cdn-btn">
                                    <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge CDN Cache', 'astropress'); ?>
                                </button>
                            </div>
                            <p class="description" id="astropress-domain-hint">
                                <?php printf(
                                    esc_html__('Custom CDN subdomain hooked to your R2 bucket. Must be a subdomain of %s (e.g. media.%s).', 'astropress'),
                                    '<code>' . esc_html($allowed_roots_str) . '</code>',
                                    esc_html($allowed_roots[0] ?? 'example.com')
                                ); ?>
                            </p>
                            <div id="r2-cdn-audit-box" style="display:none; margin-top:10px; max-width:650px;"></div>
                            <div id="astropress-deploy-rule-status" style="margin-top:8px; font-size:12px;"></div>
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

                <!-- Optional Collapsible Details for Legacy S3 Credentials -->
                <details style="margin:20px 0; border:1px dashed #cbd5e1; border-radius:6px; padding:12px 14px; background:#f8fafc; max-width:850px;">
                    <summary style="cursor:pointer; font-size:12px; font-weight:600; color:#475569;">
                        <?php esc_html_e('Advanced: Manual AWS S3 Credentials (Optional - Not needed when using Scoped Token)', 'astropress'); ?>
                    </summary>
                    <table class="form-table" style="margin-top:10px;">
                        <tr>
                            <th scope="row"><label for="r2_access_key"><?php esc_html_e('R2 Access Key ID', 'astropress'); ?></label></th>
                            <td>
                                <input type="text" id="r2_access_key" name="r2_access_key" value="<?php echo esc_attr($access_key); ?>" class="regular-text" placeholder="Optional" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="r2_secret_key"><?php esc_html_e('R2 Secret Access Key', 'astropress'); ?></label></th>
                            <td>
                                <?php if (!empty($secret_key)): ?>
                                    <div id="r2-secret-display" style="display:flex; align-items:center; gap:8px;">
                                        <input type="text" value="••••••••••••••••••••••••" class="regular-text" disabled style="background:#f8fafc;" />
                                        <button type="button" id="r2-btn-change-secret" class="button button-small"><?php esc_html_e('Change Key', 'astropress'); ?></button>
                                    </div>
                                    <div id="r2-secret-input-wrap" style="display:none; margin-top:8px;">
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <input type="password" id="r2_secret_key" name="r2_secret_key" class="regular-text" placeholder="<?php esc_attr_e('Enter new Secret Access Key', 'astropress'); ?>" autocomplete="new-password" />
                                            <button type="button" id="r2-btn-cancel-secret" class="button button-secondary button-small"><?php esc_html_e('Cancel', 'astropress'); ?></button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="password" id="r2_secret_key" name="r2_secret_key" class="regular-text" placeholder="<?php esc_attr_e('Optional', 'astropress'); ?>" autocomplete="new-password" />
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </details>

                <p class="submit">
                    <button type="submit" name="astropress_save_r2" class="button button-primary"><?php esc_html_e('Save R2 Configuration', 'astropress'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Tab: Image Optimizer & Live Preview Tester
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

            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Live Compression Preview Tester -->
            <h3><span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Live Compression Preview Tester', 'astropress'); ?></h3>
            <p><?php esc_html_e('Upload a sample image to see the exact byte reduction and inspect visual fidelity before applying settings site-wide.', 'astropress'); ?></p>

            <div class="astropress-preview-tester">
                <input type="file" id="astropress-test-image-input" accept="image/*" />
                <button type="button" class="button button-secondary" id="astropress-run-preview-test">
                    <?php esc_html_e('Test Image Compression', 'astropress'); ?>
                </button>
                <div id="astropress-preview-result" style="margin-top:15px; display:none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Edge Cache Warmer (Full Feature Parity: Presets, WP-Cron Background Worker, Live Console)
     */
    private function render_tab_warmer() {
        ?>
        <div class="astropress-card" style="max-width:920px;">
            <div class="astropress-card-header">
                <h2><span class="dashicons dashicons-cloud"></span> <?php esc_html_e('Live Edge Cache Warmer & Sitemap Crawler', 'astropress'); ?></h2>
                <span class="astropress-badge astropress-badge-online"><?php esc_html_e('Zero Edge Latency', 'astropress'); ?></span>
            </div>
            <p style="color: #64748b; font-size: 13px; margin-top: 0;">
                <?php esc_html_e('Pre-warms Cloudflare Edge CDN POPs in polite, throttled batches so visitors receive instant sub-15ms cache HITs without cold-start latency.', 'astropress'); ?>
            </p>

            <!-- Server Resource & Capacity Advisory -->
            <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:18px;">
                <div style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#92400e;">
                    <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                    <?php esc_html_e('Server Capacity & Resource Advisory', 'astropress'); ?>
                </div>
                <p style="margin:4px 0 0 0; font-size:12px; color:#78350f; line-height:1.5;">
                    <?php esc_html_e('Edge warming issues continuous HTTP requests to your Astro frontend and origin WordPress. If your hosting environment is running on shared CPU/RAM or has limited PHP workers, please select a gentle request gap (1,500ms – 3,000ms) and low batch size (1–2), or click Run in Background to run politely via WP-Cron without keeping your browser open.', 'astropress'); ?>
                </p>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-bottom: 20px;">
                <div>
                    <label for="topnepali-warm-preset" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php esc_html_e('Select Warming Scope / XML Sitemap:', 'astropress'); ?>
                    </label>
                    <select id="topnepali-warm-preset" style="width:100%; font-size:13px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                        <optgroup label="<?php esc_attr_e('Standard Presets', 'astropress'); ?>">
                            <option value="standard" selected><?php esc_html_e('Standard (Core Pages + Top Categories + Top 20 Posts ~ 35 URLs)', 'astropress'); ?></option>
                            <option value="deep"><?php esc_html_e('Comprehensive Deep (Core Pages + Top Categories + Top 50 Posts ~ 80 URLs)', 'astropress'); ?></option>
                            <option value="tools_only"><?php esc_html_e('Interactive Web Tools Only', 'astropress'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('Complete Database Content (100% Coverage)', 'astropress'); ?>">
                            <option value="all_posts"><?php esc_html_e('All Published Posts (100% Complete from Database)', 'astropress'); ?></option>
                            <option value="all_pages"><?php esc_html_e('All Published Pages (100% Complete from Database)', 'astropress'); ?></option>
                            <option value="all_taxonomies"><?php esc_html_e('All Categories & Tags', 'astropress'); ?></option>
                            <option value="entire_site"><?php esc_html_e('Entire Website (All Posts, Pages, Categories, Tags & Core Pages)', 'astropress'); ?></option>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e('XML Sitemaps (Deep Crawl & XML Files)', 'astropress'); ?>">
                            <option value="sitemap_crawler"><?php esc_html_e('Deep Sitemap Crawler (Extract & Warm ALL Page URLs inside XML Sitemaps)', 'astropress'); ?></option>
                            <option value="sitemap_files_only"><?php esc_html_e('XML Sitemap Files Only (Refresh XML Index & Sub-Sitemaps on Edge)', 'astropress'); ?></option>
                            <option value="sitemap_posts"><?php esc_html_e('Posts Sitemap (/post-sitemap.xml)', 'astropress'); ?></option>
                            <option value="sitemap_pages"><?php esc_html_e('Pages Sitemap (/page-sitemap.xml)', 'astropress'); ?></option>
                            <option value="sitemap_categories"><?php esc_html_e('Categories Sitemap (/category-sitemap.xml)', 'astropress'); ?></option>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label for="topnepali-warm-delay" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                        <?php esc_html_e('Gap Between Requests:', 'astropress'); ?>
                    </label>
                    <select id="topnepali-warm-delay" style="width:100%; font-size:13px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                        <option value="500"><?php esc_html_e('500ms (0.5s - Aggressive / Fast Server)', 'astropress'); ?></option>
                        <option value="1000" selected><?php esc_html_e('1,000ms (1.0s - Recommended Balanced)', 'astropress'); ?></option>
                        <option value="1500"><?php esc_html_e('1,500ms (1.5s - Moderate Shared Host)', 'astropress'); ?></option>
                        <option value="2000"><?php esc_html_e('2,000ms (2.0s - Low Impact Server)', 'astropress'); ?></option>
                        <option value="3000"><?php esc_html_e('3,000ms (3.0s - Conservative Safe)', 'astropress'); ?></option>
                        <option value="5000"><?php esc_html_e('5,000ms (5.0s - Ultra Gentle)', 'astropress'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons Row -->
            <div style="display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap;">
                <button type="button" id="topnepali-start-warm-btn" class="button button-primary button-hero" style="display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-controls-play" style="vertical-align:-2px;"></span> <?php esc_html_e('Start Interactive Warmer (Browser Mode)', 'astropress'); ?>
                </button>
                <button type="button" id="topnepali-start-bg-warm-btn" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Run in Background (WP-Cron)', 'astropress'); ?>
                </button>
                <button type="button" id="topnepali-cancel-warm-btn" class="button button-link-delete" style="display:none; padding:8px 14px;">
                    <span class="dashicons dashicons-controls-pause" style="vertical-align:-2px;"></span> <?php esc_html_e('Pause / Cancel', 'astropress'); ?>
                </button>
            </div>

            <!-- Persistent Background Job Box -->
            <div id="topnepali-bg-warm-box" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #16a34a; padding:16px; border-radius:8px; margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                    <span style="font-size:13px; font-weight:700; color:#15803d;">
                        <span class="dashicons dashicons-admin-generic" style="vertical-align:text-bottom;"></span>
                        <?php esc_html_e('Background Warmer Job (WP-Cron):', 'astropress'); ?> <span id="topnepali-bg-warm-current">0</span> / <span id="topnepali-bg-warm-total">0</span>
                    </span>
                    <span id="topnepali-bg-warm-status-pill" class="astropress-badge astropress-badge-online" style="font-size:11px;">RUNNING</span>
                </div>
                <div class="topnepali-progress-bar-bg" style="background:#dcfce7;">
                    <div id="topnepali-bg-progress-bar-fill" class="topnepali-progress-bar-fill" style="width: 0%; background:#16a34a;"></div>
                </div>
                <div id="topnepali-bg-warm-meta" style="font-size:12px; color:#166534; margin:8px 0;">
                    <?php esc_html_e('Job active in background. You may safely close this browser window.', 'astropress'); ?>
                </div>
                <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                    <button type="button" id="topnepali-pause-bg-btn" class="button button-secondary button-small">
                        <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause Job', 'astropress'); ?>
                    </button>
                    <button type="button" id="topnepali-resume-bg-btn" class="button button-secondary button-small" style="display:none;">
                        <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Resume Job', 'astropress'); ?>
                    </button>
                    <button type="button" id="topnepali-cancel-bg-btn" class="button button-link-delete button-small">
                        <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Cancel Job', 'astropress'); ?>
                    </button>
                    <button type="button" id="topnepali-clear-bg-btn" class="button button-secondary button-small" style="display:none;">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Dismiss Job', 'astropress'); ?>
                    </button>
                </div>
            </div>

            <!-- Real-time Progress Display -->
            <div id="topnepali-warm-progress-box" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:13px; font-weight:600; color:#334155; flex-wrap:wrap; gap:8px;">
                    <span><?php esc_html_e('Warming Progress:', 'astropress'); ?> <span id="topnepali-warm-current">0</span> / <span id="topnepali-warm-total">0</span></span>
                    <span id="topnepali-warm-percent" style="color:#0284c7; font-weight:700;">0%</span>
                </div>

                <div class="topnepali-progress-bar-bg">
                    <div id="topnepali-progress-bar-fill" class="topnepali-progress-bar-fill" style="width: 0%;"></div>
                </div>

                <div id="topnepali-warm-current-url" style="font-size:12px; color:#64748b; margin:10px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?php esc_html_e('Initializing queue...', 'astropress'); ?>
                </div>

                <div style="margin:12px 0 6px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <strong style="font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
                        <?php esc_html_e('Live Cloudflare Edge Activity Console:', 'astropress'); ?>
                    </strong>
                    <span id="topnepali-warm-status-pill" class="astropress-badge" style="background:#0284c7; color:#fff; font-size:10px;">IDLE</span>
                </div>
                <div id="topnepali-warm-log" class="topnepali-log-console"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Rank Math SEO Bridge & Subdomain Protection Shield
     */
    private function render_tab_seo() {
        $rewrite_links    = $this->core->should_rewrite_links();
        $subdomain_shield = $this->core->should_shield_subdomain();
        $subdomain_noindex= $this->core->should_noindex_backend();
        $rank_math_active = class_exists('RankMath');
        $rank_math_pro    = defined('RANK_MATH_PRO_VERSION');
        ?>
        <div style="max-width:920px;">
            <div class="astropress-card">
                <div class="astropress-card-header">
                    <h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Rank Math SEO & Subdomain Protection Shield', 'astropress'); ?></h2>
                    <span class="astropress-badge astropress-badge-online">
                        <span class="astropress-dot"></span> <?php echo $rank_math_active ? ($rank_math_pro ? 'Rank Math Pro Active' : 'Rank Math Active') : 'SEO Bridge Ready'; ?>
                    </span>
                </div>
                <p style="color: #64748b; font-size: 13px; margin-top: 0;">
                    <?php esc_html_e('Manage search engine indexation, Rank Math JSON-LD schema bridge, preview/view permalink rewrites, and origin subdomain shield.', 'astropress'); ?>
                </p>

                <form method="post" action="">
                    <?php wp_nonce_field('astropress_seo_action', 'astropress_seo_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Preview & View Links', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="rewrite_links" id="seo_rewrite_links" value="1" <?php checked($rewrite_links); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Rewrite Preview & View Links to Astro Frontend', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Points "View Post", post previews, taxonomy links, and author archives directly to your canonical Astro frontend domain instead of the WordPress backend.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Subdomain 301 Shield', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="subdomain_shield" id="seo_subdomain_shield" value="1" <?php checked($subdomain_shield); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Subdomain 301 Protection Shield', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Automatically issues an HTTP 301 Moved Permanently redirect for public human visitors from the WordPress origin subdomain (e.g. wp.topnepali.com) to the production Astro website (topnepali.com). Excludes WP-Admin, REST API endpoints, Cron jobs, and webhooks.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Search Engine Noindex', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="subdomain_noindex" id="seo_subdomain_noindex" value="1" <?php checked($subdomain_noindex); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Backend Noindex HTTP Headers', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Emits X-Robots-Tag: noindex, nofollow on the WordPress backend to prevent duplicate content indexing by Google, Bing, and other crawlers.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:20px;">
                        <button type="submit" name="astropress_save_seo" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save SEO & Security Settings', 'astropress'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEO Architecture Blueprint & REST Bridge Card -->
            <div class="astropress-card" style="margin-top:20px;">
                <div class="astropress-card-header">
                    <h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('Rank Math Schema & REST API Integration', 'astropress'); ?></h2>
                </div>
                <p style="font-size:13px; color:#475569; line-height:1.6;">
                    <?php esc_html_e('AstroPress automatically registers deep Rank Math SEO fields into the WordPress REST API responses for Posts, Pages, Categories, and Tags. Your Astro templates can extract and render rich metadata and Google-compliant JSON-LD structured data without any complex plugins.', 'astropress'); ?>
                </p>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; margin-top:12px;">
                    <h4 style="margin:0 0 8px 0; font-size:13px; color:#0f172a;"><?php esc_html_e('Exposed REST API Fields:', 'astropress'); ?></h4>
                    <ul style="margin:0; padding-left:18px; font-size:12px; color:#334155; line-height:1.8;">
                        <li><code>rank_math_seo.title</code> &mdash; <?php esc_html_e('Custom SEO Title with fallback to post title', 'astropress'); ?></li>
                        <li><code>rank_math_seo.description</code> &mdash; <?php esc_html_e('Custom Meta Description with fallback to excerpt', 'astropress'); ?></li>
                        <li><code>rank_math_seo.canonical_url</code> &mdash; <?php esc_html_e('Canonical production frontend URL', 'astropress'); ?></li>
                        <li><code>rank_math_seo.og_image</code> &mdash; <?php esc_html_e('OpenGraph social card image URL (via Cloudflare R2 CDN)', 'astropress'); ?></li>
                        <li><code>rank_math_seo.json_ld</code> &mdash; <?php esc_html_e('Rich Article, BreadcrumbList, and Organization schema graph', 'astropress'); ?></li>
                        <li><code>rank_math_seo.robots</code> &mdash; <?php esc_html_e('Robots indexing directives (index, follow, noindex)', 'astropress'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Media Sync Dashboard (Full Feature Parity)
     */
    private function render_tab_sync($stats) {
        $client        = astropress()->get_r2_client();
        $is_configured = $client && $client->is_configured();
        $quality       = (int) get_option('r2g_compress_quality', 82);
        $engine        = get_option('r2g_compress_engine', 'resmush');
        ?>
        <div style="max-width:920px;">
            <?php if (!$is_configured): ?>
                <div class="notice notice-warning inline" style="margin: 0 0 20px 0; padding: 14px 18px; border-left-color: #d97706;">
                    <h3 style="margin: 0 0 6px 0; font-size: 15px; color: #92400e;">
                        <?php esc_html_e('Cloudflare R2 Credentials Incomplete', 'astropress'); ?>
                    </h3>
                    <p style="margin: 0; color: #78350f; font-size: 13px;">
                        <?php esc_html_e('Please enter your Cloudflare Account ID, Access Key ID, Secret Key, and Bucket Name in the R2 Storage tab before starting bulk sync.', 'astropress'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" class="button button-small" style="margin-left: 8px;">
                            <?php esc_html_e('Configure R2 Storage &rarr;', 'astropress'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- 1. Stat Cards Grid -->
            <div class="r2g-stats-grid">
                <div class="r2g-stat-card">
                    <span class="r2g-stat-label"><?php esc_html_e('Total Media', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-total"><?php echo esc_html($stats['total_wp'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Attachments in Library', 'astropress'); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-success">
                    <span class="r2g-stat-label"><?php esc_html_e('Synced to R2', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-synced"><?php echo esc_html($stats['synced'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php printf(esc_html__('%d dual, %d cloud-only', 'astropress'), $stats['synced_both'] ?? 0, $stats['cloud_only'] ?? 0); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-info">
                    <span class="r2g-stat-label"><?php esc_html_e('Cloud Only', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-cloud"><?php echo esc_html($stats['cloud_only'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Server disk space freed', 'astropress'); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-warning">
                    <span class="r2g-stat-label"><?php esc_html_e('Local Only', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-local"><?php echo esc_html($stats['local_only'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Awaiting push to R2', 'astropress'); ?></span>
                </div>
            </div>

            <!-- 2. Bulk Sync Tool Card -->
            <div class="astropress-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Bulk Media Sync Engine', 'astropress'); ?></h2>
                <p class="description"><?php esc_html_e('Push all unsynced media attachments to Cloudflare R2 in safe, progressive batches without server timeouts.', 'astropress'); ?></p>

                <!-- Batch Wildcard Configuration -->
                <div class="r2g-bulk-config-box" style="margin-bottom:18px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                    <h3 style="margin-top:0; font-size:13px; font-weight:700; color:#0f172a; margin-bottom:10px;"><?php esc_html_e('Batch Conversion & Sync Options', 'astropress'); ?></h3>
                    <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
                        <div>
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Sync Preset:', 'astropress'); ?></label>
                            <select id="r2g-sync-preset" class="r2g-input" style="height:32px; font-size:12px; font-weight:600;">
                                <option value="keep_current"><?php esc_html_e('Keep Existing Files (Recommended)', 'astropress'); ?></option>
                                <option value="custom"><?php esc_html_e('Use Current Upload Defaults', 'astropress'); ?></option>
                                <option value="webp_balanced" selected><?php esc_html_e('WebP Balanced (82%, 1920px)', 'astropress'); ?></option>
                                <option value="webp_high"><?php esc_html_e('WebP High Quality (90%, 2560px)', 'astropress'); ?></option>
                                <option value="jpeg_balanced"><?php esc_html_e('JPEG Balanced (82%, 1920px)', 'astropress'); ?></option>
                                <option value="jpeg_high"><?php esc_html_e('JPEG High Quality (90%, 2560px)', 'astropress'); ?></option>
                                <option value="raw_lossless"><?php esc_html_e('Raw Offload (No Processing)', 'astropress'); ?></option>
                            </select>
                        </div>
                        <div id="r2g-sync-quality-wrap">
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Quality:', 'astropress'); ?></label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <input type="range" id="r2g-sync-quality-slider" min="50" max="100" value="<?php echo esc_attr($quality); ?>" style="width:110px;" />
                                <span id="r2g-sync-quality-val" class="r2g-quality-badge"><?php echo esc_html($quality); ?>%</span>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Processing Engine:', 'astropress'); ?></label>
                            <select id="r2g-sync-engine" class="r2g-input" style="height:32px; font-size:12px; font-weight:600;">
                                <option value="resmush" <?php selected($engine, 'resmush'); ?>><?php esc_html_e('reSmush.it Free API (Auto GD/Imagick fallback)', 'astropress'); ?></option>
                                <option value="server" <?php selected($engine, 'server'); ?>><?php esc_html_e('Server: PHP GD / Imagick (Native Background)', 'astropress'); ?></option>
                                <option value="none" <?php selected($engine, 'none'); ?>><?php esc_html_e('Raw Offload (Lossless)', 'astropress'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div id="r2g-sync-engine-notice" style="margin-top:12px; font-size:12px; color:#64748b; line-height:1.4;">
                        <span id="r2g-sync-notice-text"><?php esc_html_e('Server Engine: Media items are processed and pushed to R2 in safe batches of 5.', 'astropress'); ?></span>
                    </div>
                </div>

                <!-- Safety Guarantee Banner -->
                <div style="padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; margin-bottom:16px; font-size:12px; color:#1e40af; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-shield" style="font-size:18px; width:18px; height:18px; color:#2563eb;"></span>
                    <span><strong><?php esc_html_e('Data Safety Guarantee:', 'astropress'); ?></strong> <?php esc_html_e('Bulk sync NEVER deletes local files automatically. Your local server copies are strictly preserved until you verify and explicitly choose to clean them below.', 'astropress'); ?></span>
                </div>

                <div class="r2g-sync-container">
                    <div class="r2g-progress-wrap" style="display:none; margin-bottom:16px;" id="r2g-sync-progress-box">
                        <div class="r2g-progress-bar-bg">
                            <div class="r2g-progress-bar-fill" id="r2g-sync-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="r2g-progress-meta" style="display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:6px;">
                            <span id="r2g-sync-status-text"><?php esc_html_e('Syncing media...', 'astropress'); ?></span>
                            <span id="r2g-sync-percentage" style="font-weight:600;">0%</span>
                        </div>
                    </div>

                    <div class="r2g-sync-actions" style="display:flex; gap:10px; align-items:center;">
                        <button type="button" id="r2g-btn-start-sync" class="button button-primary button-large" <?php echo !$is_configured ? 'disabled title="' . esc_attr__('Please complete R2 Storage configuration first', 'astropress') . '"' : ''; ?>>
                            <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Sync All Unsynced to R2', 'astropress'); ?>
                        </button>
                        <button type="button" id="r2g-btn-pause-sync" class="button button-secondary" style="display:none;">
                            <?php esc_html_e('Pause', 'astropress'); ?>
                        </button>
                        <button type="button" id="r2g-btn-cancel-sync" class="button button-link-delete" style="display:none; color:#dc2626; text-decoration:none;">
                            <?php esc_html_e('Cancel', 'astropress'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. Verified Storage Cleanup Card -->
            <div class="astropress-card" style="margin-top: 20px; border-left: 4px solid #10b981;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div style="flex:1; min-width:280px;">
                        <h2 style="margin:0 0 6px 0; color:#065f46;"><?php esc_html_e('Verified Storage Cleanup (Free Web Hosting Disk Space)', 'astropress'); ?></h2>
                        <p class="description" style="margin-bottom:10px;">
                            <?php esc_html_e('Once attachments are verified on Cloudflare R2, you can safely clean local copies from your web hosting server to save disk space. Previews and responsive images will continue loading fast from your Cloudflare CDN domain.', 'astropress'); ?>
                        </p>
                        <div style="font-size:13px; color:#334155; font-weight:600; margin-bottom:12px;">
                            <?php esc_html_e('Verified on R2 with Local Copy:', 'astropress'); ?>
                            <span id="r2g-verified-count" style="font-size:14px; font-weight:700; color:#059669; padding:2px 8px; background:#ecfdf5; border-radius:4px; margin-left:4px;"><?php echo esc_html($stats['verified_with_local'] ?? 0); ?></span> <?php esc_html_e('attachments', 'astropress'); ?>
                        </div>
                    </div>
                    <div>
                        <button type="button" id="r2g-btn-clean-verified" class="button button-secondary button-large" <?php echo empty($stats['verified_with_local']) ? 'disabled' : ''; ?> style="border-color:#10b981; color:#065f46; font-weight:600;">
                            <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Clean Verified Local Files', 'astropress'); ?>
                        </button>
                    </div>
                </div>

                <div class="r2g-progress-wrap" style="display:none; margin-top:14px;" id="r2g-clean-progress-box">
                    <div class="r2g-progress-bar-bg">
                        <div class="r2g-progress-bar-fill" id="r2g-clean-progress-fill" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                    </div>
                    <div class="r2g-progress-meta" style="display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:6px;">
                        <span id="r2g-clean-status-text"><?php esc_html_e('Cleaning verified local files...', 'astropress'); ?></span>
                        <span id="r2g-clean-percentage" style="font-weight:600;">0%</span>
                    </div>
                </div>
            </div>

            <!-- 4. Database Index Maintenance & Migration Card -->
            <div class="astropress-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Database Index Maintenance & Migration', 'astropress'); ?></h2>
                <p class="description"><?php esc_html_e('Tools to synchronize WordPress with existing offloaded media and rebuild the local index without costly Cloudflare R2 Class B list operations.', 'astropress'); ?></p>

                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Import Existing Offload Records', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Scans previous plugin tables (wpmcs_items, r2_by_grisma) and existing attachment offload meta, registering them in AstroPress so CDN delivery and controls work instantly.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-import-legacy" class="button button-secondary">
                            <span class="dashicons dashicons-database-import" style="vertical-align:-2px;"></span> <?php esc_html_e('Import Existing Offloaded Media', 'astropress'); ?>
                        </button>
                        <div id="r2g-import-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>

                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Scan & Refresh Media Index', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Verifies local server files against the database table and repairs any missing records or desynced attachment states.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-reindex" class="button button-secondary">
                            <span class="dashicons dashicons-image-rotate" style="vertical-align:-2px;"></span> <?php esc_html_e('Scan & Refresh Index', 'astropress'); ?>
                        </button>
                        <div id="r2g-reindex-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>

                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Live R2 Availability Audit', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Audits live CDN URLs via HEAD requests to confirm offloaded images exist in R2, and checks if server copies are still on disk.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-audit-health" class="button button-secondary">
                            <span class="dashicons dashicons-heart" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit Media Health & Missing Files', 'astropress'); ?>
                        </button>
                        <div id="r2g-audit-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Astro Code Snippets & Architecture Blueprints
     */
    private function render_tab_integration($frontend_url) {
        $secret = $this->core->get_secret();
        $custom_domain = get_option('r2g_custom_domain', 'media.example.com');
        ?>
        <div class="astropress-card" style="max-width:900px;">
            <h2><?php esc_html_e('Astro Project Integration Snippets', 'astropress'); ?></h2>
            <p><?php esc_html_e('Copy and paste these lightweight, production-ready code blocks directly into your Astro project to enable instantaneous cache invalidation, edge redirects, and SEO injection.', 'astropress'); ?></p>

            <div class="astropress-snippet-block">
                <div class="astropress-snippet-header">
                    <span>1. Revalidation Endpoint (<code>src/pages/api/revalidate.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-revalidate"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-revalidate">import type { APIRoute } from 'astro';

export const POST: APIRoute = async ({ request, locals }) => {
  const secret = request.headers.get('x-revalidate-secret');
  const expectedSecret = (locals as any)?.runtime?.env?.REVALIDATE_SECRET || process.env.REVALIDATE_SECRET;

  if (!secret || secret !== expectedSecret) {
    return new Response(JSON.stringify({ error: 'Unauthorized' }), { status: 401 });
  }

  try {
    const payload = await request.json();
    const { slug, type, action, urls } = payload;

    // Optional: Purge specific Cloudflare Edge cache keys or caches.default
    const purgeUrls = Array.isArray(urls) ? urls : (slug ? ['/' + slug] : ['/']);

    return new Response(JSON.stringify({
      revalidated: true,
      purged: purgeUrls,
      timestamp: Date.now()
    }), { status: 200, headers: { 'Content-Type': 'application/json' } });
  } catch (err: any) {
    return new Response(JSON.stringify({ error: err.message }), { status: 400 });
  }
};</code></pre>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>2. Dynamic Edge Redirection Middleware (<code>src/middleware.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-middleware"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-middleware">import { defineMiddleware } from 'astro:middleware';

export const onRequest = defineMiddleware(async (context, next) => {
  const url = new URL(context.request.url);
  const slug = url.pathname.replace(/^\/|\/$/g, '');

  if (slug && !slug.startsWith('api/') && !slug.includes('.')) {
    try {
      const wpOrigin = '<?php echo esc_js(home_url()); ?>';
      const res = await fetch(`${wpOrigin}/wp-json/astropress/v1/redirection?slug=${encodeURIComponent(slug)}`, {
        cf: { cacheTtl: 300 }
      } as any);

      if (res.ok) {
        const data = await res.json();
        if (data.redirect_url) {
          return context.redirect(data.redirect_url, data.redirect_type || 301);
        }
      }
    } catch (_) {}
  }

  return next();
});</code></pre>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>3. Cloudflare Environment Variables (<code>.env</code> / <code>wrangler.toml</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-env"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-env"># .env or Cloudflare Pages > Settings > Environment Variables
REVALIDATE_SECRET=your_revalidate_secret_token

WP_API_URL=<?php echo esc_html(home_url()); ?>

CDN_MEDIA_URL=https://<?php echo esc_html($custom_domain); ?>
</code></pre>
            </div>

            <div class="astropress-snippet-block" style="margin-top:20px;">
                <div class="astropress-snippet-header">
                    <span>4. Universal WordPress Backend API Resolver (<code>src/lib/wp.ts</code>)</span>
                    <button type="button" class="button button-small astropress-copy-btn" data-target="code-resolver"><?php esc_html_e('Copy', 'astropress'); ?></button>
                </div>
                <pre><code id="code-resolver">/**
 * Universal WordPress API Endpoint Resolver
 *
 * Automatically resolves the active WordPress backend:
 * 1. Honors explicit WP_API_URL or API_URL environment variables (synced via AstroPress 1-Click Sync)
 * 2. Supports custom worker testers / subpaths (e.g. https://shhshsb-maj.wpbackend.tester.dev/xeaa)
 * 3. In request context, falls back dynamically to https://wp.{rootDomain}
 */
export function getWordPressApiUrl(request?: Request): string {
  // 1. Explicit Environment Variable (Recommended)
  const envUrl = (import.meta.env.WP_API_URL || import.meta.env.API_URL || process.env.WP_API_URL || process.env.API_URL);
  if (envUrl) {
    return envUrl.replace(/\/+$/, '');
  }

  // 2. Derive https://wp.{rootDomain} from current incoming request
  if (request) {
    try {
      const url = new URL(request.url);
      const hostParts = url.hostname.split('.');
      if (hostParts.length >= 2) {
        const rootDomain = hostParts.slice(-2).join('.');
        return `https://wp.${rootDomain}`;
      }
    } catch (_) {}
  }

  // 3. Fallback to Origin
  return '<?php echo esc_js(home_url()); ?>';
}</code></pre>
            </div>
        </div>
        <?php
    }

    /**
     * Tab: Webhook Delivery Audit Logs
     */
    private function render_tab_logs() {
        $logs = $this->core->cache->get_webhook_logs(50);
        ?>
        <div class="astropress-card" style="max-width:1050px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h2 style="margin:0;"><?php esc_html_e('Webhook Delivery & Invalidation Audit Log', 'astropress'); ?></h2>
                    <p style="margin:5px 0 0 0; color:#64748b;"><?php esc_html_e('Inspect the last 50 revalidation events sent to your Astro frontend and Cloudflare Edge.', 'astropress'); ?></p>
                </div>
                <?php if (!empty($logs)): ?>
                    <form method="post" action="" onsubmit="return confirm('Clear webhook delivery logs?');">
                        <?php wp_nonce_field('astropress_clear_logs_action', 'astropress_clear_logs_nonce'); ?>
                        <button type="submit" name="astropress_clear_logs" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Logs', 'astropress'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($logs)): ?>
                <div class="notice notice-info inline" style="margin:20px 0;"><p><?php esc_html_e('No webhook events recorded yet. Save or publish a post/page to see live revalidation records here.', 'astropress'); ?></p></div>
            <?php else: ?>
                <table class="widefat striped astropress-table" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Timestamp', 'astropress'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Event', 'astropress'); ?></th>
                            <th style="width:100px;"><?php esc_html_e('Status', 'astropress'); ?></th>
                            <th><?php esc_html_e('Purged Paths', 'astropress'); ?></th>
                            <th style="width:180px;"><?php esc_html_e('Response Details', 'astropress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $is_ok = !empty($log['success']);
                            $status_class = $is_ok ? 'astropress-badge-online' : 'astropress-badge-offline';
                            $status_text  = $is_ok ? __('Dispatched', 'astropress') : __('Failed', 'astropress');
                            ?>
                            <tr>
                                <td><small><code><?php echo esc_html($log['timestamp'] ?? ''); ?></code></small></td>
                                <td><span class="astropress-pill"><?php echo esc_html($log['event'] ?? 'post_updated'); ?></span></td>
                                <td><span class="astropress-badge <?php echo esc_attr($status_class); ?>"><span class="astropress-dot"></span> <?php echo esc_html($status_text); ?></span></td>
                                <td>
                                    <?php
                                    $paths = (array) ($log['paths'] ?? array());
                                    if (empty($paths)) {
                                        echo '<em>' . esc_html__('Global Purge (All)', 'astropress') . '</em>';
                                    } else {
                                        echo '<code>' . esc_html(implode(', ', array_slice($paths, 0, 5))) . (count($paths) > 5 ? ' ... (+' . (count($paths) - 5) . ' more)' : '') . '</code>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <small><?php echo esc_html($log['details'] ?? ($is_ok ? 'HTTP 200 OK' : 'Connection timeout/error')); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
