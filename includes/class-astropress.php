<?php
/**
 * Master AstroPress Plugin Container & Orchestrator
 *
 * Unifies Cloudflare R2 media offloading, WebP/reSmush optimization,
 * Astro on Cloudflare Pages/Workers cache purging, Edge cache warming,
 * Rank Math/Yoast SEO bridge, and GitHub releases updating into a cohesive system.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress {
    /**
     * Singleton instance
     *
     * @var AstroPress|null
     */
    private static $instance = null;

    // Headless Bridge Option Keys
    const OPTION_FRONTEND_URL      = 'astropress_frontend_url';
    const OPTION_SECRET            = 'astropress_secret';
    const OPTION_CONNECTED         = 'astropress_connected';
    const OPTION_REWRITE_LINKS     = 'astropress_rewrite_links';
    const OPTION_AUTO_UPDATE       = 'astropress_auto_update';
    const OPTION_PURGE_ON_SAVE     = 'astropress_purge_on_save';
    const OPTION_PURGE_FEEDS       = 'astropress_purge_feeds';
    const OPTION_PURGE_TAXONOMY    = 'astropress_purge_taxonomy';
    const OPTION_PURGE_MENU        = 'astropress_purge_menu';
    const OPTION_LSCWP_SYNC        = 'astropress_lscwp_sync';
    const OPTION_WARM_DELAY        = 'astropress_warm_delay';
    const OPTION_WARM_BATCH_SIZE   = 'astropress_warm_batch_size';
    const OPTION_SUBDOMAIN_SHIELD  = 'astropress_subdomain_shield';
    const OPTION_SUBDOMAIN_NOINDEX = 'astropress_subdomain_noindex';
    const OPTION_CF_API_TOKEN      = 'astropress_cf_api_token';
    const OPTION_CF_ZONE_ID        = 'astropress_cf_zone_id';
    const OPTION_CF_DEPLOY_HOOK    = 'astropress_cf_deploy_hook';
    const OPTION_DEBOUNCE_ENABLED  = 'astropress_debounce_enabled';
    const OPTION_CF_DIRECT_PURGE   = 'astropress_cf_direct_purge';

    // In-memory memoized option values
    private $cached_frontend_url = null;
    private $cached_secret       = null;

    /**
     * Sub-module instances
     */
    public $cache;
    public $warmer;
    public $seo;
    public $rest;
    public $security;
    public $media;
    public $rewriter;
    public $library;
    public $sync;
    public $optimizer;
    public $admin;
    public $updater;

    /**
     * Get or create singleton instance
     *
     * @return AstroPress
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone() {}

    /**
     * Constructor
     */
    private function __construct() {
        // Upgrade database and migrate legacy records on admin init
        if (is_admin()) {
            add_action('admin_init', array('AstroPress_Database', 'maybe_upgrade'));
            add_action('admin_init', array($this, 'run_legacy_migrations'));
        }

        // Initialize sub-modules
        $this->optimizer = AstroPress_Optimizer::instance();
        $this->media     = AstroPress_Media_Handler::instance();
        $this->rewriter  = AstroPress_URL_Rewriter::instance();
        $this->library   = AstroPress_Media_Library::instance();
        $this->sync      = AstroPress_Sync::instance();
        $this->cache     = new AstroPress_Cache($this);
        $this->warmer    = new AstroPress_Warmer($this);
        $this->seo       = new AstroPress_SEO($this);
        $this->rest      = new AstroPress_REST($this);
        $this->security  = new AstroPress_Security($this);
        $this->updater   = AstroPress_Updater::instance();
        $this->admin     = new AstroPress_Admin($this);

        // Core lifecycle hooks
        add_action('init', array($this, 'init'));
        add_action('astropress_retry_webhooks', array($this, 'on_retry_webhooks_cron'));
        add_filter('plugin_action_links_' . plugin_basename(ASTROPRESS_FILE), array($this, 'add_plugin_action_links'));
    }

    /**
     * Cron callback: Retry failed webhooks
     */
    public function on_retry_webhooks_cron() {
        if (isset($this->cache) && method_exists($this->cache, 'retry_failed_webhooks')) {
            $this->cache->retry_failed_webhooks();
        }
    }

    /**
     * Core WordPress init hook
     */
    public function init() {
        load_plugin_textdomain('astropress', false, dirname(plugin_basename(ASTROPRESS_FILE)) . '/languages');
    }

    /**
     * Add settings link to WordPress Plugins list
     *
     * @param array $links
     * @return array
     */
    public function add_plugin_action_links($links) {
        $settings_url = admin_url('admin.php?page=astropress');
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'astropress') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get R2 Client instance
     *
     * @return AstroPress_R2_Client
     */
    public function get_client() {
        return AstroPress_R2_Client::instance();
    }

    /**
     * Retrieve setting with tri-tier fallback chain:
     * 1. astropress_* (unified master)
     * 2. astropress_headless_* (Grisma-AstroPress)
     * 3. topnepali_headless_* (topnepali legacy)
     *
     * @param string $primary_key
     * @param string $generic_headless_key
     * @param string $legacy_key
     * @param mixed $default
     * @return mixed
     */
    public function get_compat_option($primary_key, $generic_headless_key, $legacy_key, $default = null) {
        $val = get_option($primary_key, null);
        if ($val !== null && $val !== '') {
            return $val;
        }
        $val_headless = get_option($generic_headless_key, null);
        if ($val_headless !== null && $val_headless !== '') {
            return $val_headless;
        }
        $val_legacy = get_option($legacy_key, null);
        if ($val_legacy !== null && $val_legacy !== '') {
            return $val_legacy;
        }
        return $default;
    }

    /**
     * Check if plugin has verified connection to Astro frontend
     *
     * @return bool
     */
    public function is_connected() {
        return (bool) $this->get_compat_option(self::OPTION_CONNECTED, 'astropress_headless_connected', 'topnepali_headless_connected', false);
    }

    /**
     * Get configured frontend Astro URL with in-memory memoization
     * Falls back to site_url() if unconfigured.
     *
     * @return string
     */
    public function get_frontend_url() {
        if ($this->cached_frontend_url === null) {
            $url = $this->get_compat_option(self::OPTION_FRONTEND_URL, 'astropress_headless_frontend_url', 'topnepali_headless_frontend_url', home_url());
            if (empty($url)) {
                $url = home_url();
            }
            $this->cached_frontend_url = rtrim(trim($url), '/');
        }
        return $this->cached_frontend_url;
    }

    /**
     * Get configured secret token with in-memory memoization
     *
     * @return string
     */
    public function get_secret() {
        if ($this->cached_secret === null) {
            $val = $this->get_compat_option(self::OPTION_SECRET, 'astropress_headless_secret', 'topnepali_headless_secret', '');
            $this->cached_secret = (string) $val;
        }
        return $this->cached_secret;
    }

    /**
     * Check if link rewrites are active
     *
     * @return bool
     */
    public function should_rewrite_links() {
        return (bool) $this->get_compat_option(self::OPTION_REWRITE_LINKS, 'astropress_headless_rewrite_links', 'topnepali_headless_rewrite_links', '1');
    }

    /**
     * Check if automatic updates are enabled
     *
     * @return bool
     */
    public function should_auto_update() {
        return (bool) $this->get_compat_option(self::OPTION_AUTO_UPDATE, 'astropress_headless_auto_update', 'topnepali_headless_auto_update', '1');
    }

    /**
     * Cache automation setting getters
     */
    public function should_purge_on_save() {
        return (bool) $this->get_compat_option(self::OPTION_PURGE_ON_SAVE, 'astropress_headless_purge_on_save', 'topnepali_headless_purge_on_save', '1');
    }

    public function should_purge_feeds() {
        return (bool) $this->get_compat_option(self::OPTION_PURGE_FEEDS, 'astropress_headless_purge_feeds', 'topnepali_headless_purge_feeds', '1');
    }

    public function should_purge_taxonomy() {
        return (bool) $this->get_compat_option(self::OPTION_PURGE_TAXONOMY, 'astropress_headless_purge_taxonomy', 'topnepali_headless_purge_taxonomy', '1');
    }

    public function should_purge_menu() {
        return (bool) $this->get_compat_option(self::OPTION_PURGE_MENU, 'astropress_headless_purge_menu', 'topnepali_headless_purge_menu', '1');
    }

    public function should_sync_lscwp() {
        return (bool) $this->get_compat_option(self::OPTION_LSCWP_SYNC, 'astropress_headless_lscwp_sync', 'topnepali_headless_lscwp_sync', '1');
    }

    public function should_sync_litespeed() {
        return $this->should_sync_lscwp();
    }

    public function get_warm_delay() {
        return (int) $this->get_compat_option(self::OPTION_WARM_DELAY, 'astropress_headless_warm_delay', 'topnepali_headless_warm_delay', 1000);
    }

    public function get_warm_batch_size() {
        return (int) $this->get_compat_option(self::OPTION_WARM_BATCH_SIZE, 'astropress_headless_warm_batch_size', 'topnepali_headless_warm_batch_size', 2);
    }

    public function should_shield_subdomain() {
        return (bool) $this->get_compat_option(self::OPTION_SUBDOMAIN_SHIELD, 'astropress_headless_subdomain_shield', 'topnepali_headless_subdomain_shield', '0');
    }

    public function should_noindex_backend() {
        return (bool) $this->get_compat_option(self::OPTION_SUBDOMAIN_NOINDEX, 'astropress_headless_subdomain_noindex', 'topnepali_headless_subdomain_noindex', '0');
    }

    public function get_cf_api_token() {
        $val = get_option(self::OPTION_CF_API_TOKEN, '');
        return !empty($val) ? AstroPress_Encryption::decrypt($val) : '';
    }

    public function get_cf_zone_id() {
        return (string) get_option(self::OPTION_CF_ZONE_ID, '');
    }

    public function get_cf_deploy_hook() {
        return (string) get_option(self::OPTION_CF_DEPLOY_HOOK, '');
    }

    public function should_debounce_webhooks() {
        return (bool) get_option(self::OPTION_DEBOUNCE_ENABLED, '1');
    }

    public function should_direct_purge_cloudflare() {
        return (bool) get_option(self::OPTION_CF_DIRECT_PURGE, '0');
    }

    /**
     * Activate default settings upon plugin activation
     */
    public static function activate_defaults() {
        $defaults = array(
            self::OPTION_REWRITE_LINKS     => '1',
            self::OPTION_AUTO_UPDATE       => '1',
            self::OPTION_PURGE_ON_SAVE     => '1',
            self::OPTION_PURGE_FEEDS       => '1',
            self::OPTION_PURGE_TAXONOMY    => '1',
            self::OPTION_PURGE_MENU        => '1',
            self::OPTION_LSCWP_SYNC        => '1',
            self::OPTION_WARM_DELAY        => 5,
            self::OPTION_WARM_BATCH_SIZE   => 5,
            self::OPTION_SUBDOMAIN_SHIELD  => '0',
            self::OPTION_SUBDOMAIN_NOINDEX => '0',
            self::OPTION_DEBOUNCE_ENABLED  => '1',
            self::OPTION_CF_DIRECT_PURGE   => '0',
            'r2g_compress_enabled'         => 1,
            'r2g_compress_format'          => 'webp',
            'r2g_compress_quality'         => 82,
            'r2g_compress_engine'          => 'resmush',
            'r2g_max_width'                => 1920,
            'r2g_active_preset'            => 'webp_balanced',
            'r2g_upload_workflow'          => 'bar',
        );

        foreach ($defaults as $opt => $val) {
            if (get_option($opt) === false) {
                add_option($opt, $val);
            }
        }
    }

    /**
     * Deactivation routine
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('astropress_warmer_cron');
        wp_clear_scheduled_hook('r2g_warmer_cron');
        wp_clear_scheduled_hook('astropress_retry_webhooks');
    }

    /**
     * Run backwards compatibility option and data migrations
     */
    public function run_legacy_migrations() {
        // Migrate R2 v109 defaults
        if (!get_option('astropress_v109_migrated')) {
            if (get_option('r2g_compress_enabled') === false) {
                update_option('r2g_compress_enabled', 1);
            }
            if (!get_option('r2g_compress_format')) {
                update_option('r2g_compress_format', 'webp');
            }
            if (!get_option('r2g_compress_quality')) {
                update_option('r2g_compress_quality', 82);
            }
            if (get_option('r2g_max_width', null) === null) {
                update_option('r2g_max_width', 1920);
            }
            if (!get_option('r2g_active_preset')) {
                update_option('r2g_active_preset', 'webp_balanced');
            }
            if (!get_option('r2g_upload_workflow')) {
                update_option('r2g_upload_workflow', 'bar');
            }
            update_option('astropress_v109_migrated', 1);
        }

        // Migrate R2 v122 defaults
        if (!get_option('astropress_v122_migrated')) {
            $engine = get_option('r2g_compress_engine');
            if ($engine === false || $engine === 'browser' || empty($engine)) {
                update_option('r2g_compress_engine', 'resmush');
            }
            if (get_option('r2g_interceptor_enabled') === false) {
                update_option('r2g_interceptor_enabled', 0);
            }
            update_option('astropress_v122_migrated', 1);
        }

        // Migrate postmeta to astropress_media table
        if (!get_option('astropress_postmeta_migrated')) {
            AstroPress_Database::migrate_from_postmeta();
            update_option('astropress_postmeta_migrated', 1);
        }
    }
}

// Register backwards compatibility aliases
if (!class_exists('TopNepali_Headless')) {
    class_alias('AstroPress', 'TopNepali_Headless');
}
if (!class_exists('R2_By_Grisma')) {
    class_alias('AstroPress', 'R2_By_Grisma');
}
