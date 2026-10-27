<?php
/**
 * Plugin Name:       AstroPress
 * Plugin URI:        https://github.com/iamgrisma/astropress
 * Description:       Universal Headless WordPress & Cloudflare R2 Suite for Astro on Cloudflare Pages and Workers. Features instant edge cache invalidation, automated background warmer, pure PHP SigV4 R2 media offloading, WebP/reSmush optimization, Rank Math & Yoast SEO bridge, native GitHub core updates and rollback.
 * Version:           1.1.0
 * Author:            Grisma
 * Author URI:        https://grisma.com.np
 * Developer:         Grisma Bhandari
 * Developer URI:     https://grisma.com.np
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       astropress
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/iamgrisma/astropress
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

// Core Plugin Constants
if (!defined('ASTROPRESS_VERSION')) {
    define('ASTROPRESS_VERSION', '1.1.0');
}
if (!defined('ASTROPRESS_FILE')) {
    define('ASTROPRESS_FILE', __FILE__);
}
if (!defined('ASTROPRESS_PATH')) {
    define('ASTROPRESS_PATH', plugin_dir_path(__FILE__));
}
if (!defined('ASTROPRESS_URL')) {
    define('ASTROPRESS_URL', plugin_dir_url(__FILE__));
}

// Backward Compatibility Constants for existing integrations
if (!defined('R2G_VERSION')) {
    define('R2G_VERSION', ASTROPRESS_VERSION);
}
if (!defined('R2G_FILE')) {
    define('R2G_FILE', ASTROPRESS_FILE);
}
if (!defined('R2G_PATH')) {
    define('R2G_PATH', ASTROPRESS_PATH);
}
if (!defined('R2G_URL')) {
    define('R2G_URL', ASTROPRESS_URL);
}
if (!defined('TOPNEPALI_HEADLESS_VERSION')) {
    define('TOPNEPALI_HEADLESS_VERSION', ASTROPRESS_VERSION);
}
if (!defined('TOPNEPALI_HEADLESS_FILE')) {
    define('TOPNEPALI_HEADLESS_FILE', ASTROPRESS_FILE);
}
if (!defined('TOPNEPALI_HEADLESS_PATH')) {
    define('TOPNEPALI_HEADLESS_PATH', ASTROPRESS_PATH);
}
if (!defined('TOPNEPALI_HEADLESS_URL')) {
    define('TOPNEPALI_HEADLESS_URL', ASTROPRESS_URL);
}

// Require Core Modules
require_once ASTROPRESS_PATH . 'includes/class-encryption.php';
require_once ASTROPRESS_PATH . 'includes/class-database.php';
require_once ASTROPRESS_PATH . 'includes/class-r2-client.php';
require_once ASTROPRESS_PATH . 'includes/class-optimizer.php';
require_once ASTROPRESS_PATH . 'includes/class-media-handler.php';
require_once ASTROPRESS_PATH . 'includes/class-url-rewriter.php';
require_once ASTROPRESS_PATH . 'includes/class-media-library.php';
require_once ASTROPRESS_PATH . 'includes/class-sync.php';
require_once ASTROPRESS_PATH . 'includes/class-cache.php';
require_once ASTROPRESS_PATH . 'includes/class-warmer.php';
require_once ASTROPRESS_PATH . 'includes/class-seo.php';
require_once ASTROPRESS_PATH . 'includes/class-rest.php';
require_once ASTROPRESS_PATH . 'includes/class-security.php';
require_once ASTROPRESS_PATH . 'includes/class-admin.php';
require_once ASTROPRESS_PATH . 'includes/class-updater.php';
require_once ASTROPRESS_PATH . 'includes/class-astropress.php';

/**
 * Main Singleton Accessor
 *
 * @return AstroPress
 */
function astropress() {
    return AstroPress::instance();
}

/**
 * Legacy Global Accessors for Seamless Backward Compatibility
 */
if (!function_exists('r2_by_grisma')) {
    function r2_by_grisma() {
        return astropress();
    }
}

if (!function_exists('topnepali_headless')) {
    function topnepali_headless() {
        return astropress();
    }
}

// Bootstrap
add_action('plugins_loaded', 'astropress', 5);

// Plugin Activation Hook
register_activation_hook(__FILE__, function() {
    AstroPress_Database::create_table();
    AstroPress::activate_defaults();
});

// Plugin Deactivation Hook
register_deactivation_hook(__FILE__, function() {
    AstroPress::deactivate();
});
