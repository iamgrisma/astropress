<?php
/**
 * Admin Trait: Preflight Diagnostics & Scoped Permission Blueprints
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AstroPress_Admin_Preflight {
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

}
