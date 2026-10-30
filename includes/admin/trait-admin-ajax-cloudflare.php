<?php
/**
 * Admin Trait: Cloudflare Edge & Ruleset Engine AJAX Endpoints
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AstroPress_Admin_Ajax_Cloudflare {
    /**
     * AJAX: Purge Cloudflare Zone Cache
     */
    public function ajax_purge_cf_zone() {
        $this->verify_admin_ajax();

        $token   = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }
        $zone_id = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->resolve_zone_id($token);

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

        $hook_url = isset($_POST['deploy_hook']) ? trim(wp_unslash($_POST['deploy_hook'])) : '';
        if (!empty($hook_url) && strpos($hook_url, '••••') === false) {
            $clean_hook = esc_url_raw($hook_url);
            if (!empty($clean_hook)) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, AstroPress_Encryption::encrypt($clean_hook));
            }
        }

        $deploy_hook = $this->core->get_cf_deploy_hook();
        if (empty($deploy_hook)) {
            wp_send_json_error(array(
                'verified' => false,
                'message'  => esc_html__('No Deploy Hook configured. Please paste your Cloudflare Pages Deploy Hook URL first.', 'astropress'),
            ));
        }

        $success = $this->core->cache->trigger_deploy_hook('Manual Trigger from AstroPress Admin');
        if ($success) {
            wp_send_json_success(array(
                'verified' => true,
                'message'  => esc_html__('Cloudflare Pages deploy hook validated and triggered successfully (Build queued on Cloudflare Pages).', 'astropress'),
            ));
        } else {
            wp_send_json_error(array(
                'verified' => false,
                'message'  => esc_html__('Deploy hook trigger failed. Verify your Deploy Hook URL or network connection.', 'astropress'),
            ));
        }
    }

    /**
     * AJAX: Auto-save unified Warmer Pacing and Batch Size
     */
    public function ajax_save_warmer_settings() {
        $this->verify_admin_ajax();
        if (isset($_POST['warm_delay'])) {
            $delay = max(200, absint($_POST['warm_delay']));
            update_option(AstroPress::OPTION_WARM_DELAY, $delay);
        }
        if (isset($_POST['warm_batch_size'])) {
            $batch = max(1, min(10, absint($_POST['warm_batch_size'])));
            update_option(AstroPress::OPTION_WARM_BATCH_SIZE, $batch);
        }
        wp_send_json_success();
    }

    /**
     * AJAX: Generate Strong Revalidation Secret
     */
    public function ajax_generate_secret() {
        $this->verify_admin_ajax();

        $secret = $this->core->generate_secret();
        wp_send_json_success(array(
            'secret'  => $secret,
            'message' => esc_html__('Strong 32-character secret generated and saved securely in WordPress.', 'astropress'),
        ));
    }

    /**
     * AJAX: Step-by-Step 1. Verify Scoped Token, Probe Capabilities & Discover Resources
     */
    public function ajax_verify_token_capabilities() {
        $this->verify_admin_ajax();

        $token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($token)) {
            wp_send_json_error(array('message' => esc_html__('Please provide a valid Cloudflare Scoped API Token.', 'astropress')));
        }

        $headers = array('Authorization' => 'Bearer ' . $token);

        // 1. Verify token status
        $verify_res = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', array(
            'timeout' => 8,
            'headers' => $headers,
        ));

        if (is_wp_error($verify_res)) {
            wp_send_json_error(array('message' => sprintf(esc_html__('Connection error: %s', 'astropress'), $verify_res->get_error_message())));
        }

        $verify_body = json_decode(wp_remote_retrieve_body($verify_res), true);
        if (empty($verify_body['success'])) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare rejected this API Token. Please check token permissions and expiration.', 'astropress')));
        }

        // 2. Discover root zone search term
        $wp_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $fe_url  = $this->core->get_frontend_url();
        $fe_host = wp_parse_url($fe_url, PHP_URL_HOST) ?: $wp_host;
        $search_host = $fe_host ?: $wp_host;
        $parts = explode('.', $search_host);
        $root_search = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $search_host;

        // 3. Probe Zone & Cache Purge capability (Essential)
        $z_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones?per_page=10', array(
            'timeout' => 8,
            'headers' => $headers,
        ));
        $can_zone_read = (!is_wp_error($z_res) && wp_remote_retrieve_response_code($z_res) === 200);
        $zones = array();
        $matched_zone = null;
        $matched_account = null;

        if ($can_zone_read) {
            $z_body = json_decode(wp_remote_retrieve_body($z_res), true);
            if (!empty($z_body['result']) && is_array($z_body['result'])) {
                foreach ($z_body['result'] as $z) {
                    $z_item = array(
                        'id'   => sanitize_text_field($z['id'] ?? ''),
                        'name' => sanitize_text_field($z['name'] ?? ''),
                    );
                    $zones[] = $z_item;
                    if (strcasecmp($z_item['name'], $root_search) === 0 || str_ends_with(strtolower($search_host), '.' . strtolower($z_item['name']))) {
                        $matched_zone = $z_item;
                        if (!empty($z['account']['id'])) {
                            $matched_account = array(
                                'id'   => sanitize_text_field($z['account']['id']),
                                'name' => sanitize_text_field($z['account']['name'] ?? ''),
                            );
                        }
                    }
                }
                if (!$matched_zone && !empty($zones[0])) {
                    $matched_zone = $zones[0];
                }
            }
        }

        // Query accounts if not yet found from zone
        if (!$matched_account) {
            $a_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts?per_page=5', array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($a_res) && wp_remote_retrieve_response_code($a_res) === 200) {
                $a_body = json_decode(wp_remote_retrieve_body($a_res), true);
                if (!empty($a_body['result'][0]['id'])) {
                    $matched_account = array(
                        'id'   => sanitize_text_field($a_body['result'][0]['id']),
                        'name' => sanitize_text_field($a_body['result'][0]['name'] ?? ''),
                    );
                }
            }
        }

        $account_id = $matched_account['id'] ?? $this->core->get_r2_account_id();

        // 4. Probe Pages Project capability (Optional)
        $can_pages = false;
        $pages_projects = array();
        if (!empty($account_id)) {
            $p_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($account_id) . '/pages/projects', array(
                'timeout' => 8,
                'headers' => $headers,
            ));
            if (!is_wp_error($p_res) && wp_remote_retrieve_response_code($p_res) === 200) {
                $can_pages = true;
                $p_body = json_decode(wp_remote_retrieve_body($p_res), true);
                if (!empty($p_body['result']) && is_array($p_body['result'])) {
                    foreach ($p_body['result'] as $p) {
                        $p_domains = array();
                        if (!empty($p['domains']) && is_array($p['domains'])) {
                            $p_domains = array_map('sanitize_text_field', $p['domains']);
                        }
                        if (!empty($p['subdomain']) && !in_array($p['subdomain'], $p_domains, true)) {
                            $p_domains[] = sanitize_text_field($p['subdomain']);
                        }
                        $pages_projects[] = array(
                            'name'      => sanitize_text_field($p['name'] ?? ''),
                            'subdomain' => sanitize_text_field($p['subdomain'] ?? ''),
                            'domains'   => $p_domains,
                        );
                    }
                }
            }
        }

        // 5. Probe R2 Storage capability (Optional)
        $can_r2 = false;
        $buckets = array();
        if (!empty($account_id)) {
            $r_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($account_id) . '/r2/buckets', array(
                'timeout' => 8,
                'headers' => $headers,
            ));
            if (!is_wp_error($r_res) && wp_remote_retrieve_response_code($r_res) === 200) {
                $can_r2 = true;
                $r_body = json_decode(wp_remote_retrieve_body($r_res), true);
                if (!empty($r_body['result']['buckets']) && is_array($r_body['result']['buckets'])) {
                    foreach ($r_body['result']['buckets'] as $b) {
                        $buckets[] = sanitize_text_field($b['name'] ?? '');
                    }
                }
            }
        }

        // 6. Probe DNS capability (Optional)
        $can_dns = false;
        $zone_id = $matched_zone['id'] ?? $this->core->get_cf_zone_id();
        if (!empty($zone_id)) {
            $d_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($zone_id) . '/dns_records?per_page=1', array(
                'timeout' => 8,
                'headers' => $headers,
            ));
            $can_dns = (!is_wp_error($d_res) && wp_remote_retrieve_response_code($d_res) === 200);
        }

        // Save persistent discovery cache
        set_transient('astropress_cf_discovery_cache', array(
            'account_id'     => $account_id,
            'zone_id'        => $zone_id,
            'zone_name'      => $matched_zone['name'] ?? '',
            'buckets'        => $buckets,
            'pages_projects' => $pages_projects,
        ), 86400);

        $essential_pass = $can_zone_read;

        wp_send_json_success(array(
            'valid'          => true,
            'essential_pass' => $essential_pass,
            'account_id'     => $account_id,
            'account_name'   => $matched_account['name'] ?? '',
            'zone_id'        => $zone_id,
            'zone_name'      => $matched_zone['name'] ?? '',
            'permissions'    => array(
                'zone_read_purge' => $can_zone_read,
                'pages'           => $can_pages,
                'r2'              => $can_r2,
                'dns'             => $can_dns,
            ),
            'pages_projects' => $pages_projects,
            'buckets'        => $buckets,
            'message'        => $essential_pass
                ? esc_html__('Token verified successfully with active permissions.', 'astropress')
                : esc_html__('Essential permission missing: Zone Read/Cache Purge is required.', 'astropress'),
        ));
    }

    /**
     * AJAX: Save Step 1 (Connection & Frontend Hosting Target)
     */
    public function ajax_save_step1_connection() {
        $this->verify_admin_ajax();

        $token        = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        $target       = sanitize_key($_POST['hosting_target'] ?? 'pages');
        $pages_proj   = trim(sanitize_text_field($_POST['pages_project'] ?? ''));
        $frontend_url = trim(sanitize_text_field($_POST['frontend_url'] ?? ''));
        $zone_id      = trim(sanitize_text_field($_POST['zone_id'] ?? ''));
        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? ''));

        if (!empty($token) && strpos($token, '••••') === false) {
            update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
        }
        if (!empty($target)) {
            $this->core->set_hosting_target($target);
        }
        if (!empty($pages_proj) && $pages_proj !== '__custom__') {
            update_option(AstroPress::OPTION_CF_PAGES_PROJECT, $pages_proj);
        }
        if (!empty($frontend_url)) {
            $frontend_url = rtrim($frontend_url, '/');
            if (!preg_match('#^https?://#i', $frontend_url)) {
                $frontend_url = 'https://' . $frontend_url;
            }
            update_option(AstroPress::OPTION_FRONTEND_URL, $frontend_url);
        }
        if (!empty($zone_id)) {
            update_option(AstroPress::OPTION_CF_ZONE_ID, $zone_id);
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
        }
        if (!empty($account_id)) {
            update_option('astropress_r2_account_id', $account_id);
            update_option('r2g_account_id', $account_id);
        }

        wp_send_json_success(array(
            'message' => esc_html__('Section 1: Connection & Frontend settings saved successfully! Values are persisted.', 'astropress'),
        ));
    }

    /**
     * AJAX: Save Step 2 (Revalidation & Deploy Hook)
     */
    public function ajax_save_step2_revalidation() {
        $this->verify_admin_ajax();

        $secret = trim(sanitize_text_field($_POST['secret'] ?? ''));
        $hook   = trim(sanitize_text_field($_POST['deploy_hook'] ?? ''));

        if (!empty($secret) && strpos($secret, '••••') === false) {
            $this->core->set_custom_secret($secret);
        }
        if (!empty($hook) && strpos($hook, '••••') === false) {
            $clean_hook = esc_url_raw($hook);
            if (!empty($clean_hook)) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, AstroPress_Encryption::encrypt($clean_hook));
            }
        }

        wp_send_json_success(array(
            'message' => esc_html__('Section 2: Revalidation secret & deploy hook saved successfully!', 'astropress'),
        ));
    }

    /**
     * AJAX: Save Step 3 (Media & Storage Settings)
     */
    public function ajax_save_step3_media() {
        $this->verify_admin_ajax();

        $media_mode    = sanitize_key($_POST['media_mode'] ?? 'r2');
        $bucket        = trim(sanitize_text_field($_POST['bucket'] ?? ''));
        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? ''));
        $access_key    = trim(sanitize_text_field($_POST['access_key'] ?? ''));
        $secret_key    = trim(sanitize_text_field($_POST['secret_key'] ?? ''));

        update_option(AstroPress::OPTION_MEDIA_MODE, $media_mode);
        if ($media_mode === 'r2') {
            if (!empty($bucket) && $bucket !== '__custom__') {
                update_option('astropress_r2_bucket', $bucket);
                update_option('r2g_bucket', $bucket);
            }
            if (!empty($custom_domain)) {
                $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
                update_option('astropress_r2_custom_domain', $clean_domain);
                update_option('r2g_custom_domain', $clean_domain);
            }
            if (!empty($access_key) && strpos($access_key, '••••') === false) {
                update_option('astropress_r2_access_key', $access_key);
                update_option('r2g_access_key', $access_key);
            }
            if (!empty($secret_key) && strpos($secret_key, '••••') === false) {
                update_option('astropress_r2_secret_key', AstroPress_Encryption::encrypt($secret_key));
                update_option('r2g_secret_key', AstroPress_Encryption::encrypt($secret_key));
            }
        }

        wp_send_json_success(array(
            'message' => esc_html__('Section 3: Media & Storage settings saved successfully!', 'astropress'),
        ));
    }

    /**
     * AJAX: Put Generated Key to Pages & Trigger Deploy
     */
    public function ajax_put_secret_to_pages() {
        $this->verify_admin_ajax();

        $project_name = trim(sanitize_text_field($_POST['project_name'] ?? '')) ?: $this->core->get_cf_pages_project();
        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: $this->core->get_r2_account_id();
        $token        = trim(sanitize_text_field($_POST['token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($project_name)) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare Pages project name is required.', 'astropress')));
        }

        // Save deploy hook if provided
        $raw_deploy = trim(sanitize_text_field($_POST['deploy_hook'] ?? ''));
        if (!empty($raw_deploy) && strpos($raw_deploy, '••••') === false) {
            $clean_hook = esc_url_raw($raw_deploy);
            if (!empty($clean_hook)) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, AstroPress_Encryption::encrypt($clean_hook));
            }
        }

        // Deploy hook check
        if (!$this->core->has_cf_deploy_hook()) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare Pages Deploy Hook is required before syncing secrets so your Astro frontend rebuilds and activates the secret. Please configure your Deploy Hook first.', 'astropress')
            ));
        }

        // Secret setup
        $force_gen = !empty($_POST['force_generate']);
        $raw_secret = trim(sanitize_text_field($_POST['secret'] ?? ''));
        if ($force_gen) {
            $revalidate_secret = $this->core->generate_secret();
            $this->core->set_custom_secret($revalidate_secret);
        } elseif (!empty($raw_secret) && strpos($raw_secret, '••••') === false) {
            $revalidate_secret = $this->core->set_custom_secret($raw_secret);
        } else {
            $revalidate_secret = $this->core->get_custom_secret();
            if (empty($revalidate_secret)) {
                $revalidate_secret = $this->core->generate_secret();
                $this->core->set_custom_secret($revalidate_secret);
            }
        }

        $wp_api_url = home_url();
        $env_vars = array(
            'REVALIDATE_SECRET' => $revalidate_secret,
            'WP_API_URL'        => $wp_api_url,
            'API_URL'           => $wp_api_url,
        );
        $custom_domain = AstroPress_Admin::get_r2_option('custom_domain', '');
        if (!empty($custom_domain)) {
            $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
            $env_vars['CDN_MEDIA_URL'] = 'https://' . $clean_domain;
        }

        $result = $this->core->cache->sync_pages_env($project_name, $env_vars, $account_id, $token);
        if (!empty($result['success'])) {
            update_option(AstroPress::OPTION_CF_PAGES_PROJECT, $project_name);
            $result['secret'] = $revalidate_secret;

            // Trigger deploy hook
            $hook_ok = $this->core->cache->trigger_deploy_hook('Put Generated Key to Pages & Trigger Deploy');
            $deploy_msg = $hook_ok
                ? esc_html__('Deploy build queued successfully via your Deploy Hook! Cloudflare Pages is now compiling and rebuilding your Astro frontend. Builds typically take 1–2 minutes. Please wait for the deployment to finish in your Cloudflare dashboard before running the Handshake Test.', 'astropress')
                : esc_html__('Variable synced, but deploy hook trigger returned non-200. Please check your Deploy Hook.', 'astropress');

            $result['message'] = $deploy_msg;
            $result['deploy_triggered'] = $hook_ok;
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Save All Settings Across Wizard Atomically
     */
    public function ajax_save_all_wizard() {
        $this->verify_admin_ajax();

        // 1. Connection settings
        $token        = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        $target       = sanitize_key($_POST['hosting_target'] ?? 'pages');
        $pages_proj   = trim(sanitize_text_field($_POST['pages_project'] ?? ''));
        $frontend_url = trim(sanitize_text_field($_POST['frontend_url'] ?? ''));
        $zone_id      = trim(sanitize_text_field($_POST['zone_id'] ?? ''));
        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? ''));

        if (!empty($token) && strpos($token, '••••') === false) {
            update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
        }
        if (!empty($target)) {
            $this->core->set_hosting_target($target);
        }
        if (!empty($pages_proj) && $pages_proj !== '__custom__') {
            update_option(AstroPress::OPTION_CF_PAGES_PROJECT, $pages_proj);
        }
        if (!empty($frontend_url)) {
            $frontend_url = rtrim($frontend_url, '/');
            if (!preg_match('#^https?://#i', $frontend_url)) {
                $frontend_url = 'https://' . $frontend_url;
            }
            update_option(AstroPress::OPTION_FRONTEND_URL, $frontend_url);
        }
        if (!empty($zone_id)) {
            update_option(AstroPress::OPTION_CF_ZONE_ID, $zone_id);
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
        }
        if (!empty($account_id)) {
            update_option('astropress_r2_account_id', $account_id);
            update_option('r2g_account_id', $account_id);
        }

        // 2. Revalidation settings
        $secret = trim(sanitize_text_field($_POST['secret'] ?? ''));
        $hook   = trim(sanitize_text_field($_POST['deploy_hook'] ?? ''));
        if (!empty($secret) && strpos($secret, '••••') === false) {
            $this->core->set_custom_secret($secret);
        }
        if (!empty($hook) && strpos($hook, '••••') === false) {
            $clean_hook = esc_url_raw($hook);
            if (!empty($clean_hook)) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, AstroPress_Encryption::encrypt($clean_hook));
            }
        }

        // 3. Media settings
        $media_mode    = sanitize_key($_POST['media_mode'] ?? 'r2');
        $bucket        = trim(sanitize_text_field($_POST['bucket'] ?? ''));
        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? ''));
        $access_key    = trim(sanitize_text_field($_POST['access_key'] ?? ''));
        $secret_key    = trim(sanitize_text_field($_POST['secret_key'] ?? ''));

        update_option(AstroPress::OPTION_MEDIA_MODE, $media_mode);
        if ($media_mode === 'r2') {
            if (!empty($bucket) && $bucket !== '__custom__') {
                update_option('astropress_r2_bucket', $bucket);
                update_option('r2g_bucket', $bucket);
            }
            if (!empty($custom_domain)) {
                $clean_domain = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
                update_option('astropress_r2_custom_domain', $clean_domain);
                update_option('r2g_custom_domain', $clean_domain);
            }
            if (!empty($access_key) && strpos($access_key, '••••') === false) {
                update_option('astropress_r2_access_key', $access_key);
                update_option('r2g_access_key', $access_key);
            }
            if (!empty($secret_key) && strpos($secret_key, '••••') === false) {
                update_option('astropress_r2_secret_key', AstroPress_Encryption::encrypt($secret_key));
                update_option('r2g_secret_key', AstroPress_Encryption::encrypt($secret_key));
            }
        }

        wp_send_json_success(array(
            'message' => esc_html__('All AstroPress settings saved successfully! Your configuration is persisted.', 'astropress'),
        ));
    }

    /**
     * AJAX: Fetch Cloudflare Ruleset Cache Rules (Phase: http_request_cache_settings)
     */
    public function ajax_get_cf_cache_rules() {
        $this->verify_admin_ajax();

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->resolve_zone_id($token);

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare API Token and Zone ID must be configured first in Setup Wizard.', 'astropress')
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

        $token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }
        $zone_id = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->resolve_zone_id($token);

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare API Token and Zone ID must be configured first in Setup Wizard.', 'astropress')
            ));
        }

        $preset = sanitize_key($_POST['preset'] ?? '');
        if (empty($preset) && !empty($_POST['cdn_domain'])) {
            $preset = 'r2_cdn';
        }
        $custom_desc = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
        $custom_expr = sanitize_text_field(wp_unslash($_POST['expression'] ?? ''));

        $rule_to_add = null;

        if ($preset === 'astro_assets' || $preset === 'static_assets') {
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
        } elseif ($preset === 'wp_admin_bypass') {
            $rule_to_add = array(
                'expression'        => '(starts_with(http.request.uri.path, "/wp-admin") or starts_with(http.request.uri.path, "/wp-login.php") or starts_with(http.request.uri.path, "/wp-json/"))',
                'description'       => 'AstroPress: WordPress Admin & REST API Edge Bypass',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache' => false,
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
            $cache_action = sanitize_key($_POST['cache_action'] ?? 'cache');
            if ($cache_action === 'bypass') {
                $action_params = array('cache' => false);
            } else {
                $edge_ttl_mode = sanitize_text_field($_POST['edge_ttl_mode'] ?? 'override_origin');
                $edge_ttl_val  = absint($_POST['edge_ttl_default'] ?? 31536000);
                $browser_ttl_mode = sanitize_text_field($_POST['browser_ttl_mode'] ?? 'override_origin');
                $browser_ttl_val  = absint($_POST['browser_ttl_default'] ?? 31536000);

                $action_params = array('cache' => true);
                if ($edge_ttl_mode === 'override_origin') {
                    $action_params['edge_ttl'] = array('mode' => 'override_origin', 'default' => $edge_ttl_val);
                } else {
                    $action_params['edge_ttl'] = array('mode' => 'respect_origin');
                }

                if ($browser_ttl_mode === 'override_origin') {
                    $action_params['browser_ttl'] = array('mode' => 'override_origin', 'default' => $browser_ttl_val);
                } else {
                    $action_params['browser_ttl'] = array('mode' => 'respect_origin');
                }
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
        $zone_id = $this->resolve_zone_id($token);
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
        $zone_id = $this->resolve_zone_id($token);
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
     * AJAX: Test existing stored Revalidation Secret Key against Cloudflare Pages/Workers
     */
    public function ajax_test_key() {
        $this->verify_admin_ajax();

        $frontend_url  = esc_url_raw(trim(wp_unslash($_POST['frontend_url'] ?? ''))) ?: $this->core->get_frontend_url();
        $raw_secret    = trim(sanitize_text_field(wp_unslash($_POST['secret'] ?? '')));
        $custom_secret = (!empty($raw_secret) && strpos($raw_secret, '••••') === false) ? $raw_secret : $this->core->get_custom_secret();

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
        $test_endpoint = rtrim($frontend_url, '/') . '/api/revalidate?action=ping&slug=test-connection&ticket=' . rawurlencode($ticket);
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
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => $headers,
            'body'      => wp_json_encode(array(
                'slug'   => 'test-connection',
                'type'   => 'test',
                'action' => 'ping',
                'ticket' => $ticket,
                'secret' => $custom_secret,
            )),
        ));
        $latency = round((microtime(true) - $start_time) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message'    => sprintf(esc_html__('Connection Failed: %s', 'astropress'), $response->get_error_message()),
                'guidance'   => esc_html__('WordPress could not reach your Astro frontend. Verify that your frontend URL is online, has valid SSL, and is not blocking your WordPress server IP address.', 'astropress'),
                'code'       => 0,
                'latency_ms' => $latency,
            ));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if ($code === 200) {
            update_option(AstroPress::OPTION_CONNECTED, '1');
            $success_msg = !empty($custom_secret)
                ? esc_html__('Handshake verified. Key matches Cloudflare Pages/Workers REVALIDATE_SECRET (HTTP 200 OK).', 'astropress')
                : esc_html__('Handshake verified. Revalidation endpoint is online and responsive (No secret required / HTTP 200 OK).', 'astropress');

            wp_send_json_success(array(
                'message'    => $success_msg,
                'code'       => 200,
                'latency_ms' => $latency,
            ));
        } else {
            $err_msg = !empty($decoded['error']) ? $decoded['error'] : ('HTTP Error ' . $code);
            $guidance = '';

            if ($code === 401) {
                $err_msg  = esc_html__('Authentication Failed (HTTP 401 Unauthorized): Secret token mismatch.', 'astropress');
                $guidance = esc_html__('Your Astro frontend rejected the request because the secret does not match. If you recently synced the secret or triggered a deploy, Cloudflare Pages builds typically take 1–2 minutes to compile. Please wait for the deployment to finish in your Cloudflare dashboard, or verify REVALIDATE_SECRET.', 'astropress');
            } elseif ($code === 404) {
                $err_msg  = esc_html__('Endpoint Not Found (HTTP 404): /api/revalidate does not exist on your Astro site.', 'astropress');
                $guidance = esc_html__('Ensure your Astro project contains "src/pages/api/revalidate.ts" and that your site is deployed. You can copy the code snippet directly from the Astro Snippets tab.', 'astropress');
            } elseif ($code >= 500) {
                $err_msg  = sprintf(esc_html__('Astro Server Error (HTTP %d): The revalidation worker threw an exception.', 'astropress'), $code);
                $guidance = esc_html__('Check your Cloudflare Pages / Workers real-time logs for exception stack traces.', 'astropress');
            } else {
                $guidance = esc_html__('Verify that your Astro site is reachable and that your Cloudflare DNS is properly configured.', 'astropress');
            }

            wp_send_json_error(array(
                'message'    => $err_msg,
                'guidance'   => $guidance,
                'code'       => $code,
                'latency_ms' => $latency,
            ));
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

}
