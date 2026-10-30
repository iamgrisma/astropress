<?php
/**
 * Admin Trait: Settings Persistence & Lifecycle Actions
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AstroPress_Admin_Settings {
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

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=cloudflare'));
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
            update_option(AstroPress::OPTION_DEBOUNCE_ENABLED, isset($_POST['debounce_enabled']) ? '1' : '0');
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, isset($_POST['cf_direct_purge']) ? '1' : '0');

            if (!empty($_POST['cf_zone_id'])) {
                update_option(AstroPress::OPTION_CF_ZONE_ID, sanitize_text_field(wp_unslash($_POST['cf_zone_id'])));
            }
            if (isset($_POST['cf_deploy_hook'])) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, esc_url_raw(trim(wp_unslash($_POST['cf_deploy_hook']))));
            }

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

            wp_safe_redirect(admin_url('admin.php?page=astropress&tab=cloudflare'));
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
            
            $bucket_input  = sanitize_text_field(wp_unslash($_POST['r2_bucket'] ?? ''));
            $bucket_select = sanitize_text_field(wp_unslash($_POST['r2_bucket_select'] ?? ''));
            $bucket        = $bucket_input !== '' ? $bucket_input : ($bucket_select !== '' ? $bucket_select : '');
            
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
                update_option('r2g_bucket', $bucket);
            }
            if (!empty($clean_domain)) {
                update_option('astropress_r2_custom_domain', $clean_domain);
            }
            update_option('astropress_r2_auto_upload', $auto_upload);
            update_option('astropress_r2_rewrite_urls', $rewrite_urls);
            update_option('astropress_r2_delete_from_r2', $delete_from_r2);
            update_option('astropress_storage_mode', $storage_mode);
            update_option('astropress_r2_storage_mode', $storage_mode);

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

            // Revalidation Secret (if specified in onboarding)
            $raw_secret = trim(wp_unslash($_POST['onboard_revalidate_secret'] ?? ''));
            if (!empty($raw_secret) && strpos($raw_secret, '••••') === false) {
                update_option(AstroPress::OPTION_SECRET, AstroPress_Encryption::encrypt($raw_secret));
            }

            // R2 Object Storage
            if ($media_mode === 'r2') {
                $bucket_input  = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket'] ?? ''));
                $bucket_select = sanitize_text_field(wp_unslash($_POST['onboard_r2_bucket_select'] ?? ''));
                $bucket        = $bucket_input !== '' ? $bucket_input : ($bucket_select !== '' ? $bucket_select : '');
                $custom_domain = sanitize_text_field(wp_unslash($_POST['onboard_r2_custom_domain'] ?? ''));

                if (!empty($bucket)) {
                    update_option('astropress_r2_bucket', $bucket);
                    update_option('r2g_bucket', $bucket);
                }

                if (!empty($custom_domain)) {
                    $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
                    update_option('astropress_r2_custom_domain', $clean_domain);
                    update_option('r2g_custom_domain', $clean_domain);

                    $token_for_r2 = (!empty($cf_token) && strpos($cf_token, '••••') === false) ? $cf_token : $this->core->get_cf_api_token();
                    $zone_for_r2  = !empty($cf_zone_id) ? $cf_zone_id : $this->core->get_cf_zone_id();
                    $acc_for_r2   = !empty($account_id) ? $account_id : $this->core->get_r2_account_id();
                    if (!empty($bucket) && !empty($token_for_r2) && !empty($acc_for_r2)) {
                        $this->core->cache->attach_r2_custom_domain($bucket, $clean_domain, $zone_for_r2, '', $acc_for_r2, $token_for_r2);
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

}
