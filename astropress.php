<?php
/**
 * Plugin Name:       AstroPress
 * Plugin URI:        https://github.com/iamgrisma/astropress
 * Description:       Universal Headless WordPress & Cloudflare R2 Suite for Astro on Cloudflare Pages and Workers. Features instant edge cache invalidation, automated background warmer, pure PHP SigV4 R2 media offloading, WebP/reSmush optimization, Rank Math & Yoast SEO bridge, native GitHub core updates and rollback.
 * Version:           2.0.02
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
    define('ASTROPRESS_VERSION', '2.0.02');
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
 * Register backward compatibility shims (constants, functions, class aliases)
 * only after all active plugins have loaded (priority 999), and only if legacy plugins are absent.
 */
function astropress_register_legacy_shims() {
    // R2 by Grisma Compatibility
    if (!class_exists('R2_By_Grisma', false)) {
        if (!defined('R2G_VERSION')) define('R2G_VERSION', ASTROPRESS_VERSION);
        if (!defined('R2G_FILE'))    define('R2G_FILE', ASTROPRESS_FILE);
        if (!defined('R2G_PATH'))    define('R2G_PATH', ASTROPRESS_PATH);
        if (!defined('R2G_URL'))     define('R2G_URL', ASTROPRESS_URL);

        if (!function_exists('r2_by_grisma')) {
            function r2_by_grisma() {
                return astropress();
            }
        }

        $r2_aliases = array(
            'R2_By_Grisma'      => 'AstroPress',
            'R2G_Encryption'    => 'AstroPress_Encryption',
            'R2G_Database'      => 'AstroPress_Database',
            'R2G_Client'        => 'AstroPress_R2_Client',
            'R2G_Optimizer'     => 'AstroPress_Optimizer',
            'R2G_Media_Handler' => 'AstroPress_Media_Handler',
            'R2G_URL_Rewriter'  => 'AstroPress_URL_Rewriter',
            'R2G_Media_Library' => 'AstroPress_Media_Library',
            'R2G_Sync'          => 'AstroPress_Sync',
            'R2G_Admin'         => 'AstroPress_Admin',
            'R2G_Updater'       => 'AstroPress_Updater',
        );

        foreach ($r2_aliases as $alias => $original) {
            if (!class_exists($alias, false)) {
                class_alias($original, $alias);
            }
        }
    }

    // TopNepali Headless Compatibility
    if (!class_exists('TopNepali_Headless', false)) {
        if (!function_exists('topnepali_headless')) {
            function topnepali_headless() {
                if (class_exists('TopNepali_Headless', false)) {
                    return TopNepali_Headless::instance();
                }
                return astropress();
            }
        }

        $tn_aliases = array(
            'TopNepali_Headless'          => 'AstroPress',
            'TopNepali_Headless_Cache'    => 'AstroPress_Cache',
            'TopNepali_Headless_SEO'      => 'AstroPress_SEO',
            'TopNepali_Headless_REST'     => 'AstroPress_REST',
            'TopNepali_Headless_Security' => 'AstroPress_Security',
            'TopNepali_Headless_Admin'    => 'AstroPress_Admin',
            'TopNepali_Headless_Updater'  => 'AstroPress_Updater',
        );

        foreach ($tn_aliases as $alias => $original) {
            if (!class_exists($alias, false) && class_exists($original, false)) {
                class_alias($original, $alias);
            }
        }
    }
}

// Bootstrap
add_action('plugins_loaded', 'astropress', 5);
add_action('plugins_loaded', 'astropress_register_legacy_shims', 999);

// Plugin Activation Hook
register_activation_hook(__FILE__, function() {
    AstroPress_Database::create_table();
    AstroPress::activate_defaults();
    set_transient('astropress_activation_redirect', true, 60);
});

// Redirect to Setup Wizard on activation
add_action('admin_init', function() {
    if (get_transient('astropress_activation_redirect')) {
        delete_transient('astropress_activation_redirect');
        if (!isset($_GET['activate-multi']) && current_user_can('manage_options')) {
            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=onboarding'));
            exit;
        }
    }
});

// Plugin Deactivation Hook
register_deactivation_hook(__FILE__, function() {
    AstroPress::deactivate();
});
