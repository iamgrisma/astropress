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


// Modular Admin Traits
require_once ASTROPRESS_PATH . 'includes/admin/trait-admin-preflight.php';
require_once ASTROPRESS_PATH . 'includes/admin/trait-admin-settings.php';
require_once ASTROPRESS_PATH . 'includes/admin/trait-admin-ajax-r2.php';
require_once ASTROPRESS_PATH . 'includes/admin/trait-admin-ajax-cloudflare.php';

class AstroPress_Admin {
    use AstroPress_Admin_Preflight;
    use AstroPress_Admin_Settings;
    use AstroPress_Admin_Ajax_R2;
    use AstroPress_Admin_Ajax_Cloudflare;

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

            // AJAX Handlers for Headless & Cache Warmer (warmer hooks delegated to AstroPress_Warmer)
            add_action('wp_ajax_astropress_test_key', array($this, 'ajax_test_key'));

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
            add_action('wp_ajax_astropress_detect_r2_custom_domain', array($this, 'ajax_detect_r2_custom_domain'));

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
    }

    public function redirect_to_r2_tab() {
        wp_safe_redirect(admin_url('admin.php?page=astropress&tab=r2'));
        exit;
    }

    /**
     * Enqueue Admin Assets (CSS & JS)
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        $screen      = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_base = $screen ? (string) $screen->base : '';

        $is_astropress_page = (
            strpos($hook, 'astropress') !== false ||
            strpos($hook, 'r2-by-grisma') !== false ||
            strpos($hook, 'topnepali-headless') !== false
        );

        $allowed_media_hooks = array('upload.php', 'media-new.php', 'post.php', 'post-new.php', 'page.php', 'page-new.php', 'wp_enqueue_media');
        $is_editor_or_media  = (
            in_array($hook, $allowed_media_hooks, true) ||
            in_array($screen_base, array('post', 'upload', 'media', 'edit'), true) ||
            did_action('wp_enqueue_media')
        );

        if (!$is_astropress_page && !$is_editor_or_media) {
            return;
        }

        // Always enqueue Admin CSS on AstroPress, Editor, and Media screens for UI & Interceptor modal styling
        wp_enqueue_style(
            'astropress-admin-css',
            ASTROPRESS_URL . 'assets/css/admin.css',
            array(),
            ASTROPRESS_VERSION
        );

        $compress_config = array(
            'engine'          => AstroPress_Optimizer::get_engine(),
            'storageMode'     => AstroPress_Media_Handler::get_storage_mode(),
            'interceptor'     => (int) get_option('r2g_interceptor_enabled', 1),
            'editorWorkflow'  => get_option('r2g_editor_workflow', get_option('astropress_editor_workflow', 'both')),
            'smartRouting'    => (bool) get_option('r2g_smart_routing', get_option('astropress_smart_routing', 1)),
            'format'          => get_option('r2g_compress_format', 'webp'),
            'compress'        => (int) get_option('r2g_compress_enabled', 1),
            'quality'         => (int) get_option('r2g_compress_quality', 82),
            'maxWidth'        => (int) get_option('r2g_max_width', 1920),
            'maxHeight'       => (int) get_option('r2g_max_height', 0),
            'stripExif'       => (bool) get_option('r2g_strip_exif', 1),
            'autoOrient'      => (bool) get_option('r2g_auto_orient', 1),
            'backupOriginal'  => (bool) get_option('r2g_backup_original', 0),
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('r2g_admin_nonce'),
        );

        // Enqueue AstroPress Dashboard script only on AstroPress settings screens
        if ($is_astropress_page) {
            wp_enqueue_script(
                'astropress-admin-js',
                ASTROPRESS_URL . 'assets/js/admin.js',
                array('jquery'),
                ASTROPRESS_VERSION,
                true
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
        }

        // Enqueue Upload Interceptor on all media & editor pages or when enabled
        if ((int) get_option('r2g_interceptor_enabled', 1) === 1 || $is_editor_or_media || $is_astropress_page) {
            wp_enqueue_script(
                'astropress-upload-interceptor',
                ASTROPRESS_URL . 'assets/js/upload-interceptor.js',
                array('jquery'),
                ASTROPRESS_VERSION,
                true
            );

            // Localize both modern and legacy config objects directly to the interceptor script
            wp_localize_script('astropress-upload-interceptor', 'astropress_compress_config', $compress_config);
            wp_localize_script('astropress-upload-interceptor', 'r2g_compress_config', $compress_config);
        }
    }

    public function filter_core_client_side_media_processing($enabled) {
        return (int) get_option('r2g_interceptor_enabled', 1) === 1 ? false : $enabled;
    }

    public function on_enqueue_block_editor_assets() {
        $this->enqueue_admin_assets('post.php');

        // Option B: Gutenberg Block Inspector sidebar extension for Image blocks
        wp_enqueue_script(
            'astropress-editor-sidebar',
            ASTROPRESS_URL . 'assets/js/editor-sidebar.js',
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-block-editor', 'wp-compose', 'wp-data', 'wp-hooks'),
            ASTROPRESS_VERSION,
            true
        );

        wp_localize_script('astropress-editor-sidebar', 'astropressEditorConfig', array(
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('astropress_admin_nonce'),
            'r2_nonce'     => wp_create_nonce('r2g_admin_nonce'),
            'engine'       => AstroPress_Optimizer::get_engine(),
            'format'       => get_option('r2g_compress_format', 'webp'),
            'quality'      => (int) get_option('r2g_compress_quality', 82),
            'smartRouting' => (bool) get_option('r2g_smart_routing', get_option('astropress_smart_routing', 1)),
            'storageMode'  => AstroPress_Media_Handler::get_storage_mode(),
            'canWebP'      => AstroPress_Optimizer::can_generate_webp(),
            'canAVIF'      => AstroPress_Optimizer::can_generate_avif(),
        ));
    }

    public function on_wp_enqueue_media() {
        $this->enqueue_admin_assets('wp_enqueue_media');
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
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'astropress_purge_single_nonce') && !wp_verify_nonce($nonce, 'topnepali_purge_single_nonce')) {
                wp_die(esc_html__('Invalid or missing security token (nonce).', 'astropress'), 403);
            }
            $post_id = intval($_GET['post_id']);
            if (!current_user_can('edit_post', $post_id)) {
                wp_die(esc_html__('Unauthorized.', 'astropress'), 403);
            }
            $post = get_post($post_id);
            if ($post) {
                delete_post_meta($post_id, '_astropress_rm_head');
                delete_post_meta($post_id, '_headless_rm_head');
                delete_post_meta($post_id, '_astropress_yoast_head');
                delete_post_meta($post_id, '_astropress_seopress_head');
                delete_post_meta($post_id, '_astropress_aioseo_head');
                $this->core->cache->dispatch_revalidation($post->post_name, $post->post_type, 'update');
            }
            wp_safe_redirect(add_query_arg('astropress_msg', 'purged_single', wp_get_referer() ?: admin_url()));
            exit;
        }

        // Global edge cache purge
        if (isset($_GET['action']) && ($_GET['action'] === 'astropress_purge_all' || $_GET['action'] === 'topnepali_purge_all')) {
            $nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'astropress_purge_all_nonce') && !wp_verify_nonce($nonce, 'topnepali_purge_all_nonce')) {
                wp_die(esc_html__('Invalid or missing security token (nonce).', 'astropress'), 403);
            }
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Unauthorized.', 'astropress'), 403);
            }
            $this->core->cache->dispatch_revalidation(null, 'all', 'purge', array('/', '/sitemap.xml', '/rss.xml'));
            wp_safe_redirect(add_query_arg('astropress_msg', 'purged_all', wp_get_referer() ?: admin_url()));
            exit;
        }
    }

    /**
     * Get R2 setting with canonical astropress_r2_* fallback to legacy r2g_*
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get_r2_option($key, $default = '') {
        $val = get_option('astropress_r2_' . $key, null);
        if ($val !== null && $val !== false && $val !== '') {
            return $val;
        }
        $val = get_option('astropress_' . $key, null);
        if ($val !== null && $val !== false && $val !== '') {
            return $val;
        }
        $val = get_option('r2g_' . $key, null);
        if ($val !== null && $val !== false && $val !== '') {
            return $val;
        }
        return $default;
    }

    /**
     * Render Master Settings Page
     */
    public function render_settings_page() {
        $is_setup_done = $this->core->is_setup_completed();
        $active_tab    = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : ($is_setup_done ? 'dashboard' : 'onboarding');

        // Force Setup Wizard if setup is incomplete and no explicit tab was requested
        if (!$is_setup_done && !isset($_GET['tab'])) {
            $active_tab = 'onboarding';
        }

        $notice = get_transient('astropress_notice');
        if ($notice) {
            delete_transient('astropress_notice');
        }

        $frontend_url = $this->core->get_frontend_url();
        $is_connected = $this->core->is_connected();
        $r2_bucket    = self::get_r2_option('bucket', '');
        $r2_domain    = self::get_r2_option('custom_domain', '');
        $stats        = AstroPress_Database::get_stats();

        $tab_categories = array(
            'dashboard'   => 'dashboard',
            'onboarding'  => 'dashboard',
            'settings'    => 'dashboard',
            'r2'          => 'cloudflare',
            'sync'        => 'cloudflare',
            'cache_rules' => 'edge',
            'bridge'      => 'edge',
            'warmer'      => 'edge',
            'logs'        => 'edge',
            'optimizer'   => 'optimizer',
            'seo'         => 'optimizer',
            'integration' => 'optimizer',
            'updates'     => 'optimizer',
        );
        $active_category = $tab_categories[$active_tab] ?? 'dashboard';

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

            <!-- Tier 1: Category Section Pills -->
            <div class="astropress-nav-categories" role="tablist" aria-label="AstroPress Categories">
                <button type="button" class="astropress-cat-btn <?php echo ($active_category === 'dashboard') ? 'active' : ''; ?>" data-category="dashboard" id="astropress-cat-dashboard" role="tab" aria-selected="<?php echo ($active_category === 'dashboard') ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
                    <span class="astropress-cat-label"><?php esc_html_e('Dashboard & Setup', 'astropress'); ?></span>
                    <span class="astropress-cat-count">3</span>
                </button>
                <button type="button" class="astropress-cat-btn <?php echo ($active_category === 'cloudflare') ? 'active' : ''; ?>" data-category="cloudflare" id="astropress-cat-cloudflare" role="tab" aria-selected="<?php echo ($active_category === 'cloudflare') ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-cloud" aria-hidden="true"></span>
                    <span class="astropress-cat-label"><?php esc_html_e('Cloudflare & Storage', 'astropress'); ?></span>
                    <span class="astropress-cat-count">2</span>
                </button>
                <button type="button" class="astropress-cat-btn <?php echo ($active_category === 'edge') ? 'active' : ''; ?>" data-category="edge" id="astropress-cat-edge" role="tab" aria-selected="<?php echo ($active_category === 'edge') ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-performance" aria-hidden="true"></span>
                    <span class="astropress-cat-label"><?php esc_html_e('Edge Cache & Warmer', 'astropress'); ?></span>
                    <span class="astropress-cat-count">3</span>
                </button>
                <button type="button" class="astropress-cat-btn <?php echo ($active_category === 'optimizer') ? 'active' : ''; ?>" data-category="optimizer" id="astropress-cat-optimizer" role="tab" aria-selected="<?php echo ($active_category === 'optimizer') ? 'true' : 'false'; ?>">
                    <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                    <span class="astropress-cat-label"><?php esc_html_e('Optimizer & SEO', 'astropress'); ?></span>
                    <span class="astropress-cat-count">4</span>
                </button>
            </div>

            <!-- Tier 2: Filtered Sub-tabs -->
            <nav class="nav-tab-wrapper astropress-nav-tabs">
                <!-- Category: Dashboard & Setup -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'dashboard') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'dashboard') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="dashboard" data-tab="dashboard">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Overview', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'onboarding') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'dashboard') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="dashboard" data-tab="onboarding">
                    <span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e('Setup Wizard', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=settings')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'settings') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'dashboard') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="dashboard" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Settings & Reset', 'astropress'); ?>
                </a>

                <!-- Category: Cloudflare & Storage -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'r2') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'cloudflare') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="cloudflare" data-tab="r2">
                    <span class="dashicons dashicons-database"></span> <?php esc_html_e('Cloudflare R2', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=sync')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'sync') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'cloudflare') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="cloudflare" data-tab="sync">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Media Sync', 'astropress'); ?>
                </a>

                <!-- Category: Edge Cache & Warmer -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cache_rules')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'cache_rules' || $active_tab === 'bridge') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'edge') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="edge" data-tab="cache_rules">
                    <span class="dashicons dashicons-cloud"></span> <?php esc_html_e('Edge & Cache Rules', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=warmer')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'warmer') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'edge') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="edge" data-tab="warmer">
                    <span class="dashicons dashicons-performance"></span> <?php esc_html_e('Cache Warmer', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=logs')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'logs') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'edge') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="edge" data-tab="logs">
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Purge Logs', 'astropress'); ?>
                </a>

                <!-- Category: Optimizer & SEO -->
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=optimizer')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'optimizer') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'optimizer') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="optimizer" data-tab="optimizer">
                    <span class="dashicons dashicons-format-image"></span> <?php esc_html_e('Image Optimizer', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=seo')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'seo') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'optimizer') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="optimizer" data-tab="seo">
                    <span class="dashicons dashicons-shield"></span> <?php esc_html_e('SEO & Security', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=integration')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'integration') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'optimizer') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="optimizer" data-tab="integration">
                    <span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('Astro Snippets', 'astropress'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=updates')); ?>" 
                   class="nav-tab <?php echo ($active_tab === 'updates') ? 'nav-tab-active ' : ''; ?><?php echo ($active_category === 'optimizer') ? 'astropress-tab-visible' : 'astropress-tab-hidden'; ?>" 
                   data-category="optimizer" data-tab="updates">
                    <span class="dashicons dashicons-cloud-saved"></span> <?php esc_html_e('Updates', 'astropress'); ?>
                </a>
            </nav>

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
     * Render an admin view template from includes/admin/views/
     *
     * @param string $view_slug Template name without .php extension
     * @param array  $args      Data array extracted into local scope
     */
    public function render_view($view_slug, array $args = array()) {
        $view_file = ASTROPRESS_PATH . 'includes/admin/views/' . sanitize_file_name($view_slug) . '.php';
        if (file_exists($view_file)) {
            if (!empty($args)) {
                extract($args, EXTR_SKIP);
            }
            include $view_file;
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html(sprintf(__('View template not found: %s', 'astropress'), $view_slug)) . '</p></div>';
        }
    }

    /**
     * Tab: Gated Setup Wizard (v2.0)
     */
    public function render_tab_onboarding($frontend_url = '', $is_connected = false) {
        $this->render_view('tab-onboarding', compact('frontend_url', 'is_connected'));
    }

    /**
     * Tab: Dashboard & Overview
     */
    public function render_tab_dashboard($frontend_url = '', $is_connected = false, $stats = array()) {
        $this->render_view('tab-dashboard', compact('frontend_url', 'is_connected', 'stats'));
    }

    /**
     * Tab: Cloudflare Edge & Modern Ruleset Cache Rules Manager
     */
    public function render_tab_cache_rules() {
        $this->render_view('tab-cache-rules');
    }

    /**
     * Tab: General Settings & Danger Zone
     */
    public function render_tab_settings() {
        $this->render_view('tab-settings');
    }


    /**
     * Tab: Cloudflare R2 Credentials & CDN
     */
    public function render_tab_r2() {
        $this->render_view('tab-r2');
    }

    /**
     * Tab: Image Optimizer & Live Preview Tester
     */
    public function render_tab_optimizer() {
        $this->render_view('tab-optimizer');
    }

    /**
     * Tab: Edge Cache Warmer
     */
    public function render_tab_warmer() {
        $this->render_view('tab-warmer');
    }

    /**
     * Tab: Headless SEO Bridge & Subdomain Protection
     */
    public function render_tab_seo() {
        $this->render_view('tab-seo');
    }

    /**
     * Tab: Media Sync Dashboard
     */
    public function render_tab_sync($stats = array()) {
        $this->render_view('tab-sync', compact('stats'));
    }

    /**
     * Tab: Astro Code Snippets
     */
    public function render_tab_integration($frontend_url = '') {
        $this->render_view('tab-integration', compact('frontend_url'));
    }

    /**
     * Tab: Webhook Delivery Audit Logs
     */
    public function render_tab_logs() {
        $this->render_view('tab-logs');
    }

    /**
     * Tab: GitHub Core Updates & Rollback
     */
    public function render_tab_updates() {
        $this->render_view('tab-updates');
    }
}
