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
     * Pre-configures exact Cloudflare Dashboard permissionGroupKeys:
     * - Zone > Cache Purge (cache_purge): purge
     * - Zone > Cache Rules / Settings (cache_settings): edit
     * - Zone > Page Rules (page_rules): edit
     * - Zone > DNS (zone_dns): edit
     * - Zone > Zone (zone): read
     * - Account > Workers R2 Storage (workers_r2): edit
     * - Account > Cloudflare Pages (page): edit
     * - Account > Account Settings (account_settings): read
     *
     * @param array $target_keys Optional subset of keys to remediate
     * @return string
     */
    public function get_cf_token_template_url(array $target_keys = array()) {
        $all_permissions = array(
            'cache_purge'      => array('key' => 'cache_purge',      'type' => 'purge'),
            'cache_settings'   => array('key' => 'cache_settings',   'type' => 'edit'),
            'page_rules'       => array('key' => 'page_rules',       'type' => 'edit'),
            'zone_dns'         => array('key' => 'zone_dns',         'type' => 'edit'),
            'zone'             => array('key' => 'zone',             'type' => 'read'),
            'workers_r2'       => array('key' => 'workers_r2',       'type' => 'edit'),
            'page'             => array('key' => 'page',             'type' => 'edit'),
            'account_settings' => array('key' => 'account_settings', 'type' => 'read'),
        );

        // Comprehensive alias map from friendly names to exact Cloudflare dashboard keys
        $alias_map = array(
            'cache_rules'        => array('cache_settings'),
            'dns'                => array('zone_dns'),
            'workers_r2_storage' => array('workers_r2'),
            'r2_storage'         => array('workers_r2'),
            'pages'              => array('page'),
            'accounts'           => array('account_settings'),
            'zones'              => array('zone'),
        );

        $permissions = array();
        if (!empty($target_keys)) {
            foreach ($target_keys as $k) {
                if (isset($alias_map[$k])) {
                    foreach ($alias_map[$k] as $alias) {
                        if (isset($all_permissions[$alias])) {
                            $permissions[$alias] = $all_permissions[$alias];
                        }
                    }
                } elseif (isset($all_permissions[$k])) {
                    $permissions[$k] = $all_permissions[$k];
                }
            }
        }
        if (empty($permissions)) {
            $permissions = $all_permissions;
        }

        $token_name = !empty($target_keys) ? 'AstroPress Token (Remediated Scopes)' : 'AstroPress Universal Bridge';

        return 'https://dash.cloudflare.com/profile/api-tokens?permissionGroupKeys=' . rawurlencode(wp_json_encode(array_values($permissions))) . '&accountId=*&zoneId=all&name=' . rawurlencode($token_name);
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
                'key'      => 'cache_purge',
                'access'   => 'Purge',
                'desc'     => 'Real-time cache invalidation on post/page publish & 1-click CDN edge purge',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'Cache Rules (Cache Settings)',
                'key'      => 'cache_settings',
                'access'   => 'Edit',
                'desc'     => 'Modern Cloudflare Cache Rules deployment (1-year Edge & Browser TTL via Ruleset API)',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'Page Rules',
                'key'      => 'page_rules',
                'access'   => 'Edit',
                'desc'     => 'Fallback cache & edge rules for standard Cloudflare zones',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'DNS',
                'key'      => 'zone_dns',
                'access'   => 'Edit',
                'desc'     => 'Auto-verify DNS records & 1-click attach custom CDN domains to R2',
            ),
            array(
                'category' => 'Zone',
                'name'     => 'Zone',
                'key'      => 'zone',
                'access'   => 'Read',
                'desc'     => 'Auto-discovery of domain Zone ID',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Workers R2 Storage',
                'key'      => 'workers_r2',
                'access'   => 'Edit',
                'desc'     => 'Single-Token zero-egress media uploads & bucket management',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Cloudflare Pages',
                'key'      => 'page',
                'access'   => 'Edit',
                'desc'     => 'Zero-touch push of environment variables (REVALIDATE_SECRET, WP_API_URL)',
            ),
            array(
                'category' => 'Account',
                'name'     => 'Account Settings',
                'key'      => 'account_settings',
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
        return "Token Name: AstroPress Universal Bridge\n\nPermissions:\n1. Zone > Cache Purge > Purge\n2. Zone > Cache Rules (or Cache Settings) > Edit\n3. Zone > Page Rules > Edit\n4. Zone > DNS > Edit\n5. Zone > Zone > Read\n6. Account > Workers R2 Storage > Edit\n7. Account > Cloudflare Pages > Edit\n8. Account > Account Settings > Read\n\nAccount Resources: Include > All accounts (or your account)\nZone Resources: Include > All zones (or your domain)";
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
            }
            if (!empty($new_secret)) {
                $enc_secret = AstroPress_Encryption::encrypt($new_secret);
                update_option(AstroPress::OPTION_SECRET, $enc_secret);
            }

            // Test Handshake automatically
            $ticket = $this->core->cache->generate_auth_ticket('test-handshake');
            $custom_secret = !empty($new_secret) ? $new_secret : $this->core->get_custom_secret();
            $connected = false;

            if (!empty($frontend_url)) {
                $headers = array(
                    'Content-Type'        => 'application/json',
                    'Origin'              => $frontend_url,
                    'x-astropress-ticket' => $ticket,
                );
                if (!empty($custom_secret)) {
                    $headers['x-revalidate-secret'] = $custom_secret;
                    $headers['Authorization']       = 'Bearer ' . $custom_secret;
                }
                $test_res = wp_remote_post($frontend_url . '/api/revalidate', array(
                    'timeout'   => 8,
                    'sslverify' => true,
                    'headers'   => $headers,
                    'body' => wp_json_encode(array('slug' => 'test-handshake', 'type' => 'test', 'action' => 'ping')),
                ));
                if (!is_wp_error($test_res) && wp_remote_retrieve_response_code($test_res) === 200) {
                    $connected = true;
                }
            }

            update_option(AstroPress::OPTION_CONNECTED, $connected ? '1' : '0');

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
            update_option('astropress_cf_cache_tags_enabled', isset($_POST['cf_cache_tags_enabled']) ? '1' : '0');
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

            if (isset($_POST['revalidate_secret'])) {
                $raw_secret = sanitize_text_field(trim(wp_unslash($_POST['revalidate_secret'])));
                if (!empty($raw_secret) && strpos($raw_secret, '••••') === false) {
                    update_option(AstroPress::OPTION_SECRET, AstroPress_Encryption::encrypt($raw_secret));
                } elseif (empty($raw_secret)) {
                    delete_option(AstroPress::OPTION_SECRET);
                    delete_option('astropress_headless_secret');
                }
            }

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
            update_option(AstroPress::OPTION_SUBDOMAIN_SHIELD, $subdomain_shield);
            update_option(AstroPress::OPTION_SUBDOMAIN_NOINDEX, $subdomain_noindex);

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

            $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
            $storage_mode = sanitize_key(wp_unslash($_POST['r2_storage_mode'] ?? 'both'));
            if (!in_array($storage_mode, array('both', 'r2_only', 'local_only'), true)) {
                $storage_mode = 'both';
            }
            $auto_upload    = !empty($_POST['r2_auto_upload']) ? 1 : 0;
            $rewrite_urls   = !empty($_POST['r2_rewrite_urls']) ? 1 : 0;
            $delete_from_r2 = !empty($_POST['r2_delete_from_r2']) ? 1 : 0;

            // Primary canonical options
            if (!empty($account_id)) {
                update_option('astropress_r2_account_id', $account_id);
            }
            if (!empty($access_key)) {
                update_option('astropress_r2_access_key', $access_key);
            }
            if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
                $encrypted_secret = AstroPress_Encryption::encrypt($posted_secret);
                update_option('astropress_r2_secret_key', $encrypted_secret);
            }
            if (!empty($bucket)) {
                update_option('astropress_r2_bucket', $bucket);
            }
            update_option('astropress_r2_custom_domain', $clean_domain);
            update_option('astropress_r2_auto_upload', $auto_upload);
            update_option('astropress_r2_rewrite_urls', $rewrite_urls);
            update_option('astropress_r2_delete_from_r2', $delete_from_r2);
            update_option('astropress_storage_mode', $storage_mode);

            // Conditional legacy sync: only write r2g_* if legacy keys already exist in database
            if (get_option('r2g_account_id', null) !== null && !empty($account_id)) {
                update_option('r2g_account_id', $account_id);
            }
            if (get_option('r2g_access_key', null) !== null && !empty($access_key)) {
                update_option('r2g_access_key', $access_key);
            }
            if (get_option('r2g_secret_key', null) !== null && !empty($encrypted_secret)) {
                update_option('r2g_secret_key', $encrypted_secret);
            }
            if (get_option('r2g_bucket', null) !== null && !empty($bucket)) {
                update_option('r2g_bucket', $bucket);
            }
            if (get_option('r2g_custom_domain', null) !== null) {
                update_option('r2g_custom_domain', $clean_domain);
            }
            if (get_option('r2g_auto_upload', null) !== null) {
                update_option('r2g_auto_upload', $auto_upload);
                update_option('r2g_rewrite_urls', $rewrite_urls);
                update_option('r2g_delete_from_r2', $delete_from_r2);
            }
            if (get_option('r2g_storage_mode', null) !== null) {
                update_option('r2g_storage_mode', $storage_mode);
            }

            if (isset($_POST['astropress_retention_days'])) {
                $ret_days = max(0, (int) $_POST['astropress_retention_days']);
                update_option('astropress_retention_days', $ret_days);
                if (class_exists('AstroPress_Media_Handler')) {
                    AstroPress_Media_Handler::instance()->maybe_schedule_retention_cron();
                }
            }

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Cloudflare R2 configuration saved successfully.', 'astropress'),
            ), 30);

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=r2'));
            exit;
        }

        // 4. Save Image Optimizer Settings
        if (isset($_POST['astropress_save_optimizer']) && check_admin_referer('astropress_optimizer_action', 'astropress_optimizer_nonce')) {
            $compress_enabled = !empty($_POST['compress_enabled']) ? 1 : 0;
            $format = sanitize_text_field(wp_unslash($_POST['compress_format'] ?? 'webp'));
            $quality = max(50, min(100, (int)($_POST['compress_quality'] ?? 82)));
            $engine = AstroPress_Optimizer::normalize_engine(sanitize_key(wp_unslash($_POST['compress_engine'] ?? 'smart')));
            $smart_routing = !empty($_POST['smart_routing']) ? 1 : 0;
            $max_width = max(0, (int)($_POST['max_width'] ?? 1920));
            $max_height = max(0, (int)($_POST['max_height'] ?? 0));
            $strip_exif = !empty($_POST['strip_exif']) ? 1 : 0;
            $auto_orient = !empty($_POST['auto_orient']) ? 1 : 0;
            $backup_orig = !empty($_POST['backup_original']) ? 1 : 0;
            $editor_workflow = sanitize_key(wp_unslash($_POST['editor_workflow'] ?? 'both'));
            $interceptor_enabled = !empty($_POST['interceptor_enabled']) ? 1 : 0;

            // Primary canonical astropress_* options
            update_option('astropress_compress_enabled', $compress_enabled);
            update_option('astropress_compress_format', $format);
            update_option('astropress_compress_quality', $quality);
            update_option('astropress_compress_engine', $engine);
            update_option('astropress_smart_routing', $smart_routing);
            update_option('astropress_max_width', $max_width);
            update_option('astropress_max_height', $max_height);
            update_option('astropress_strip_exif', $strip_exif);
            update_option('astropress_auto_orient', $auto_orient);
            update_option('astropress_backup_original', $backup_orig);
            update_option('astropress_editor_workflow', $editor_workflow);
            update_option('astropress_interceptor_enabled', $interceptor_enabled);

            // Conditional legacy sync: only mirror if legacy option exists
            if (get_option('r2g_compress_enabled', null) !== null) {
                update_option('r2g_compress_enabled', $compress_enabled);
                update_option('r2g_compress_format', $format);
                update_option('r2g_compress_quality', $quality);
                update_option('r2g_compress_engine', $engine);
                update_option('r2g_smart_routing', $smart_routing);
                update_option('r2g_max_width', $max_width);
                update_option('r2g_max_height', $max_height);
                update_option('r2g_strip_exif', $strip_exif);
                update_option('r2g_auto_orient', $auto_orient);
                update_option('r2g_backup_original', $backup_orig);
                update_option('r2g_editor_workflow', $editor_workflow);
                update_option('r2g_interceptor_enabled', $interceptor_enabled);
            }

            set_transient('astropress_notice', array(
                'type'    => 'success',
                'message' => esc_html__('Image Optimizer preferences updated successfully.', 'astropress'),
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
            }

            if (!empty($cf_token) && strpos($cf_token, '••••') === false) {
                $enc_cf = AstroPress_Encryption::encrypt($cf_token);
                update_option(AstroPress::OPTION_CF_API_TOKEN, $enc_cf);
                update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
            }

            if (!empty($cf_zone_id)) {
                update_option(AstroPress::OPTION_CF_ZONE_ID, $cf_zone_id);
            }

            update_option(AstroPress::OPTION_MEDIA_MODE, $media_mode);

            // Cloudflare Account ID (Always persist if discovered)
            $account_id = sanitize_text_field(wp_unslash($_POST['onboard_r2_account_id'] ?? ''));
            if (!empty($account_id)) {
                update_option('astropress_r2_account_id', $account_id);
                if (get_option('r2g_account_id', null) !== null) {
                    update_option('r2g_account_id', $account_id);
                }
            }

            // R2 Object Storage
            if ($media_mode === 'r2') {
                $bucket = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket'] ?? ''));
                if (!empty($_POST['onboard_r2_bucket_select'])) {
                    $bucket = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket_select']));
                }
                $custom_domain = sanitize_text_field(wp_unslash($_POST['onboard_r2_custom_domain'] ?? ''));

                if (!empty($bucket)) {
                    update_option('astropress_r2_bucket', $bucket);
                    if (get_option('r2g_bucket', null) !== null) {
                        update_option('r2g_bucket', $bucket);
                    }
                }
                if (!empty($custom_domain)) {
                    $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
                    update_option('astropress_r2_custom_domain', $clean_domain);
                    if (get_option('r2g_custom_domain', null) !== null) {
                        update_option('r2g_custom_domain', $clean_domain);
                    }
                }

                update_option('astropress_r2_auto_upload', 1);
                update_option('astropress_r2_rewrite_urls', 1);
                update_option('astropress_compress_enabled', 1);
                update_option('astropress_compress_format', 'webp');
                update_option('astropress_compress_quality', 82);

                if (get_option('r2g_auto_upload', null) !== null) {
                    update_option('r2g_auto_upload', 1);
                    update_option('r2g_rewrite_urls', 1);
                    update_option('r2g_compress_enabled', 1);
                    update_option('r2g_compress_format', 'webp');
                    update_option('r2g_compress_quality', 82);
                }
            } else {
                update_option('astropress_r2_auto_upload', 0);
                update_option('astropress_r2_rewrite_urls', 0);
                if (get_option('r2g_auto_upload', null) !== null) {
                    update_option('r2g_auto_upload', 0);
                    update_option('r2g_rewrite_urls', 0);
                }
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

        $frontend_url  = esc_url_raw(trim(wp_unslash($_POST['frontend_url'] ?? ''))) ?: $this->core->get_frontend_url();
        $custom_secret = sanitize_text_field(wp_unslash($_POST['secret'] ?? '')) ?: $this->core->get_custom_secret();

        if (empty($frontend_url)) {
            wp_send_json_error(array(
                'message' => 'Please provide the Frontend URL.',
                'code'    => 400,
            ));
        }

        if (!AstroPress_Security::is_safe_external_url($frontend_url)) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid or forbidden frontend URL. Local loopback and reserved private IP ranges are blocked.', 'astropress'),
                'code'    => 400,
            ));
        }

        $ticket        = $this->core->cache->generate_auth_ticket('test-connection');
        $test_endpoint = rtrim($frontend_url, '/') . '/api/revalidate';
        $start_time    = microtime(true);
        $headers       = array(
            'Content-Type'        => 'application/json',
            'Origin'              => $frontend_url,
            'Referer'             => $frontend_url,
            'x-astropress-ticket' => $ticket,
        );
        if (!empty($custom_secret)) {
            $headers['x-revalidate-secret'] = $custom_secret;
            $headers['Authorization']       = 'Bearer ' . $custom_secret;
        }

        $response      = wp_remote_post($test_endpoint, array(
            'timeout'   => 8,
            'sslverify' => true,
            'headers'   => $headers,
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

                $clean_rules = array();
                foreach ($existing_rules as $r) {
                    if (empty($r['expression']) || empty($r['action'])) {
                        continue;
                    }
                    $cr = array(
                        'expression'        => $r['expression'],
                        'description'       => !empty($r['description']) ? $r['description'] : 'Cache Rule',
                        'action'            => $r['action'],
                        'action_parameters' => $r['action_parameters'] ?? array(),
                        'enabled'           => isset($r['enabled']) ? (bool) $r['enabled'] : true,
                    );
                    if (!empty($r['id'])) {
                        $cr['id'] = $r['id'];
                    }
                    if (!empty($r['ref'])) {
                        $cr['ref'] = $r['ref'];
                    }
                    $clean_rules[] = $cr;
                }

                // Append new rule and update entrypoint
                $clean_rules[] = $cache_rule_payload;
                $put_rs = wp_remote_request($ruleset_entry_url, array(
                    'method'  => 'PUT',
                    'timeout' => 15,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode(array('rules' => $clean_rules)),
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

        $format     = sanitize_text_field($_POST['format'] ?? 'webp');
        $quality    = (int) ($_POST['quality'] ?? 82);
        $max_width  = (int) ($_POST['max_width'] ?? 1920);
        $max_height = (int) ($_POST['max_height'] ?? 0);
        $engine     = AstroPress_Optimizer::normalize_engine(sanitize_text_field($_POST['engine'] ?? ''));

        $tmp_file  = $_FILES['image']['tmp_name'];
        if (!empty($_FILES['image']['error']) || !is_uploaded_file($tmp_file)) {
            wp_send_json_error(array('message' => esc_html__('Invalid upload for preview.', 'astropress')));
        }

        $orig_name = sanitize_file_name($_FILES['image']['name'] ?? 'image.jpg');
        $orig_size = (int) ($_FILES['image']['size'] ?? 0);
        if ($orig_size <= 0 && file_exists($tmp_file)) {
            $orig_size = filesize($tmp_file);
        }

        $detected_ext = AstroPress_Optimizer::detect_image_extension($tmp_file, $orig_name);
        if (empty($detected_ext)) {
            $detected_ext = 'jpg';
        }

        // Copy upload temp file to an extension-suffixed temp file so external APIs and libraries recognize it
        $temp_dir = get_temp_dir();
        $safe_tmp = trailingslashit($temp_dir) . 'astropress-test-' . wp_generate_password(8, false, false) . '.' . $detected_ext;
        if (!@copy($tmp_file, $safe_tmp)) {
            $safe_tmp = $tmp_file;
        }

        $optimizer = AstroPress_Optimizer::instance();
        $res = $optimizer->compress_image($safe_tmp, $format, $quality, $max_width, $engine, $max_height, $orig_name);

        if ($safe_tmp !== $tmp_file && file_exists($safe_tmp)) {
            @unlink($safe_tmp);
        }

        if (!empty($res['success']) && !empty($res['output_path']) && file_exists($res['output_path'])) {
            $out_size = filesize($res['output_path']);
            $out_data = base64_encode(file_get_contents($res['output_path']));
            $mime     = !empty($res['mime']) ? $res['mime'] : (($format === 'webp') ? 'image/webp' : (($format === 'avif') ? 'image/avif' : (($format === 'png') ? 'image/png' : 'image/jpeg')));

            if ($res['output_path'] !== $tmp_file && file_exists($res['output_path'])) {
                @unlink($res['output_path']);
            }

            wp_send_json_success(array(
                'original_size'   => $orig_size,
                'compressed_size' => $out_size,
                'saved_bytes'     => max(0, $orig_size - $out_size),
                'percent'         => ($orig_size > 0) ? round((($orig_size - $out_size) / $orig_size) * 100, 1) : 0,
                'data_uri'        => 'data:' . $mime . ';base64,' . $out_data,
                'engine_used'     => $res['engine_used'] ?? $engine,
                'engine_note'     => $res['engine_note'] ?? '',
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

        // Evaluate missing scopes and compute dynamic remediation URL
        $missing_scope_keys = array();
        if (!$can_purge) {
            $missing_scope_keys[] = 'cache_purge';
        }
        if (!$can_cache_rules) {
            $missing_scope_keys[] = 'cache_settings';
        }
        if (!$can_dns) {
            $missing_scope_keys[] = 'zone_dns';
        }
        if (empty($zones)) {
            $missing_scope_keys[] = 'zone';
        }
        if (!$can_r2) {
            $missing_scope_keys[] = 'workers_r2';
        }
        if (!$can_pages) {
            $missing_scope_keys[] = 'page';
        }
        if (empty($accounts)) {
            $missing_scope_keys[] = 'account_settings';
        }

        $all_granted   = empty($missing_scope_keys);
        $fix_token_url = $all_granted ? '' : $this->get_cf_token_template_url($missing_scope_keys);

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
            'all_granted'     => $all_granted,
            'missing_scopes'  => $missing_scope_keys,
            'fix_token_url'   => $fix_token_url,
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

        $err_msg  = $body['errors'][0]['message'] ?? sprintf(esc_html__('HTTP %d returned by Cloudflare Ruleset API.', 'astropress'), $code);
        $err_code = (int) ($body['errors'][0]['code'] ?? $code);

        // Friendly actionable detection for missing Cache Rules permission
        if ($code === 401 || $code === 403 || $err_code === 10000 || stripos($err_msg, 'authentication error') !== false) {
            wp_send_json_error(array(
                'message'       => esc_html__('Cloudflare API Token is missing the "Zone > Cache Rules: Edit" (internal key: cache_settings) permission. Please update your token in Cloudflare or re-create it via the Setup Wizard.', 'astropress'),
                'code'          => 10000,
                'missing_scope' => 'cache_settings',
                'fix_token_url' => $this->get_cf_token_template_url(array('cache_settings')),
            ));
        }

        wp_send_json_error(array('message' => $err_msg, 'code' => $err_code));
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
            $cdn_domain = sanitize_text_field(wp_unslash($_POST['cdn_domain'] ?? '')) ?: get_option('astropress_r2_custom_domain', get_option('r2g_custom_domain', ''));
            $parsed = wp_parse_url($cdn_domain);
            $host = !empty($parsed['host']) ? $parsed['host'] : trim(preg_replace('#^https?://#i', '', $cdn_domain), '/');
            $host = explode('/', $host)[0];
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
            $host = wp_parse_url($frontend_url, PHP_URL_HOST);
            if (empty($host)) {
                $host = trim(preg_replace('#^https?://#i', '', $frontend_url), '/');
                $host = explode('/', $host)[0];
            }
            if (empty($host)) {
                wp_send_json_error(array('message' => esc_html__('Frontend host could not be determined. Please configure Frontend URL first.', 'astropress')));
            }
            $rule_to_add = array(
                'expression'        => '(http.host eq "' . addslashes($host) . '")',
                'description'       => 'AstroPress: Global Edge Cache (Respect Origin Headers)',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'       => true,
                    'edge_ttl'    => array('mode' => 'respect_origin'),
                    'browser_ttl' => array('mode' => 'respect_origin'),
                ),
                'enabled'           => true,
            );
        } elseif ($preset === 'astro_edge_override') {
            $frontend_url = $this->core->get_frontend_url();
            $host = wp_parse_url($frontend_url, PHP_URL_HOST);
            if (empty($host)) {
                $host = trim(preg_replace('#^https?://#i', '', $frontend_url), '/');
                $host = explode('/', $host)[0];
            }
            if (empty($host)) {
                wp_send_json_error(array('message' => esc_html__('Frontend host could not be determined. Please configure Frontend URL first.', 'astropress')));
            }
            $rule_to_add = array(
                'expression'        => '(http.host eq "' . addslashes($host) . '")',
                'description'       => 'AstroPress: 1-Year Full Edge Cache (' . $host . ')',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'       => true,
                    'edge_ttl'    => array('mode' => 'override_origin', 'default' => 31536000),
                    'browser_ttl' => array('mode' => 'override_origin', 'default' => 31536000),
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

        if (is_wp_error($get_res)) {
            wp_send_json_error(array('message' => 'Cloudflare API connection error: ' . $get_res->get_error_message()));
        }

        $get_code = wp_remote_retrieve_response_code($get_res);
        $existing_rules = array();
        if ($get_code === 200) {
            $body = json_decode(wp_remote_retrieve_body($get_res), true);
            $existing_rules = $body['result']['rules'] ?? array();
        } elseif ($get_code === 404) {
            // Ruleset does not exist yet; Cloudflare automatically creates entrypoint on PUT
            $existing_rules = array();
        } else {
            $body = json_decode(wp_remote_retrieve_body($get_res), true);
            $err_msg  = $body['errors'][0]['message'] ?? ('HTTP ' . $get_code);
            $err_code = (int) ($body['errors'][0]['code'] ?? $get_code);

            if ($get_code === 401 || $get_code === 403 || $err_code === 10000 || stripos($err_msg, 'authentication error') !== false) {
                wp_send_json_error(array(
                    'message'       => esc_html__('Could not fetch existing cache rules: Your Cloudflare API Token is missing the "Zone > Cache Rules: Edit" (permission key: cache_settings). Please re-generate or update your token.', 'astropress'),
                    'code'          => 10000,
                    'missing_scope' => 'cache_settings',
                    'fix_token_url' => $this->get_cf_token_template_url(array('cache_settings')),
                ));
            }
            wp_send_json_error(array('message' => 'Could not fetch existing cache rules from Cloudflare: ' . $err_msg, 'code' => $err_code));
        }

        $clean_rules = array();
        $updated = false;

        foreach ($existing_rules as $r) {
            if (empty($r['expression']) || empty($r['action'])) {
                continue;
            }
            $item = array(
                'expression'        => $r['expression'],
                'description'       => $r['description'] ?? 'Cache Rule',
                'action'            => $r['action'],
                'action_parameters' => $r['action_parameters'] ?? array(),
                'enabled'           => isset($r['enabled']) ? (bool) $r['enabled'] : true,
            );
            if (!empty($r['id'])) {
                $item['id'] = $r['id'];
            }
            if (!empty($r['ref'])) {
                $item['ref'] = $r['ref'];
            }

            if ($item['expression'] === $rule_to_add['expression']) {
                $item['enabled']           = true;
                $item['description']       = $rule_to_add['description'];
                $item['action_parameters'] = $rule_to_add['action_parameters'];
                $updated = true;
            }
            $clean_rules[] = $item;
        }

        if (!$updated) {
            $clean_rules[] = $rule_to_add;
        }

        $put_res = wp_remote_request($entry_url, array(
            'method'  => 'PUT',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode(array('rules' => $clean_rules)),
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

        $err_msg  = $put_body['errors'][0]['message'] ?? sprintf(esc_html__('Cloudflare returned status code %d', 'astropress'), $put_code);
        $err_code = (int) ($put_body['errors'][0]['code'] ?? $put_code);

        if ($put_code === 401 || $put_code === 403 || $err_code === 10000 || stripos($err_msg, 'authentication error') !== false) {
            wp_send_json_error(array(
                'message'       => esc_html__('Could not deploy Cache Rule: Cloudflare API Token is missing "Zone > Cache Rules: Edit" (permission key: cache_settings).', 'astropress'),
                'code'          => 10000,
                'missing_scope' => 'cache_settings',
                'fix_token_url' => $this->get_cf_token_template_url(array('cache_settings')),
            ));
        }

        wp_send_json_error(array('message' => $err_msg, 'code' => $err_code));
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
        $clean_rules = array();
        $found = false;

        foreach ($rules as $r) {
            if (empty($r['expression']) || empty($r['action'])) {
                continue;
            }
            $item = array(
                'expression'        => $r['expression'],
                'description'       => $r['description'] ?? 'Cache Rule',
                'action'            => $r['action'],
                'action_parameters' => $r['action_parameters'] ?? array(),
                'enabled'           => isset($r['enabled']) ? (bool) $r['enabled'] : true,
            );
            if (!empty($r['id'])) {
                $item['id'] = $r['id'];
            }
            if (!empty($r['ref'])) {
                $item['ref'] = $r['ref'];
            }

            if (($r['id'] ?? '') === $rule_id) {
                $item['enabled'] = $enabled;
                $found = true;
            }
            $clean_rules[] = $item;
        }

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
            'body'    => wp_json_encode(array('rules' => $clean_rules)),
        ));

        if (!is_wp_error($put_res) && in_array(wp_remote_retrieve_response_code($put_res), array(200, 201), true)) {
            wp_send_json_success(array(
                'message' => $enabled
                    ? esc_html__('Cache rule enabled.', 'astropress')
                    : esc_html__('Cache rule disabled.', 'astropress'),
            ));
        }

        $put_body = !is_wp_error($put_res) ? json_decode(wp_remote_retrieve_body($put_res), true) : array();
        $err_msg  = $put_body['errors'][0]['message'] ?? (!is_wp_error($put_res) ? ('HTTP ' . wp_remote_retrieve_response_code($put_res)) : $put_res->get_error_message());
        wp_send_json_error(array('message' => sprintf(esc_html__('Failed to update cache rule state on Cloudflare: %s', 'astropress'), $err_msg)));
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
                if (empty($r['expression']) || empty($r['action'])) {
                    continue;
                }
                $item = array(
                    'expression'        => $r['expression'],
                    'description'       => $r['description'] ?? 'Cache Rule',
                    'action'            => $r['action'],
                    'action_parameters' => $r['action_parameters'] ?? array(),
                    'enabled'           => isset($r['enabled']) ? (bool) $r['enabled'] : true,
                );
                if (!empty($r['id'])) {
                    $item['id'] = $r['id'];
                }
                if (!empty($r['ref'])) {
                    $item['ref'] = $r['ref'];
                }
                $new_rules[] = $item;
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

        $put_body = !is_wp_error($put_res) ? json_decode(wp_remote_retrieve_body($put_res), true) : array();
        $err_msg  = $put_body['errors'][0]['message'] ?? (!is_wp_error($put_res) ? ('HTTP ' . wp_remote_retrieve_response_code($put_res)) : $put_res->get_error_message());
        wp_send_json_error(array('message' => sprintf(esc_html__('Failed to delete cache rule on Cloudflare: %s', 'astropress'), $err_msg)));
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
            AstroPress::OPTION_SUBDOMAIN_SHIELD,
            AstroPress::OPTION_SUBDOMAIN_NOINDEX,
            AstroPress::OPTION_REWRITE_LINKS,
            AstroPress::OPTION_PURGE_ON_SAVE,
            AstroPress::OPTION_CF_DEPLOY_HOOK,
            'astropress_setup_mode',
            'astropress_cf_pages_project',
            'astropress_r2_account_id',
            'astropress_r2_access_key',
            'astropress_r2_secret_key',
            'astropress_r2_bucket',
            'astropress_r2_custom_domain',
            'astropress_headless_frontend_url',
            'astropress_headless_secret',
            'astropress_headless_rewrite_links',
            'astropress_headless_subdomain_shield',
            'astropress_headless_subdomain_noindex',
            'topnepali_headless_frontend_url',
            'topnepali_headless_secret',
            'topnepali_headless_connected',
            'topnepali_headless_cf_api_token',
            'topnepali_headless_cf_zone_id',
            'topnepali_headless_subdomain_shield',
            'topnepali_headless_subdomain_noindex',
            'topnepali_headless_rewrite_links',
            'r2g_account_id',
            'r2g_access_key',
            'r2g_secret_key',
            'r2g_bucket',
            'r2g_custom_domain',
            'astropress_webhook_logs',
            'astropress_warm_bg_job',
            'topnepali_warm_bg_job',
        );

        foreach ($options_to_delete as $opt) {
            delete_option($opt);
        }

        delete_transient('astropress_cf_discovery_cache');
        wp_clear_scheduled_hook('astropress_warm_bg_worker');
        wp_clear_scheduled_hook('topnepali_warm_bg_worker');

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
     * Tab: Headless Bridge (Delegates to Cache Rules)
     */
    public function render_tab_bridge($frontend_url = '', $is_connected = false) {
        $this->render_tab_cache_rules();
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
