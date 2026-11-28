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

        // 1. Verify token status
        $verify_res = $this->core->cache->cf_api_request('user/tokens/verify', $token, 'GET', null, 8);
        if ($verify_res['code'] === 0) {
            wp_send_json_error(array('message' => sprintf(esc_html__('Connection error: %s', 'astropress'), $verify_res['error'])));
        }
        if (!$verify_res['ok']) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare rejected this API Token. Please check token permissions and expiration.', 'astropress')));
        }

        // 2. Discover root zone search term
        $wp_host     = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        $fe_url      = $this->core->get_frontend_url();
        $fe_host     = wp_parse_url($fe_url, PHP_URL_HOST) ?: $wp_host;
        $search_host = strtolower($fe_host ?: $wp_host);
        $root_search = AstroPress::get_root_domain($search_host);

        // 3. Probe Zone & Cache Purge capability (Essential)
        $z_res           = $this->core->cache->cf_api_request('zones?per_page=50', $token, 'GET', null, 8);
        $can_zone_read   = $z_res['ok'];
        $zones           = array();
        $matched_zone    = null;
        $matched_account = null;

        if ($can_zone_read && !empty($z_res['result']) && is_array($z_res['result'])) {
            foreach ($z_res['result'] as $z) {
                $z_item = array(
                    'id'   => sanitize_text_field($z['id'] ?? ''),
                    'name' => sanitize_text_field($z['name'] ?? ''),
                );
                $zones[] = $z_item;
                $z_name  = strtolower($z_item['name']);
                if (
                    $z_name === $root_search ||
                    $z_name === $search_host ||
                    ($search_host && (substr($search_host, -strlen('.' . $z_name)) === '.' . $z_name))
                ) {
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

        // Query accounts if not yet found from zone
        if (!$matched_account) {
            $a_res = $this->core->cache->cf_api_request('accounts?per_page=5', $token, 'GET', null, 8);
            if ($a_res['ok'] && !empty($a_res['result'][0]['id'])) {
                $matched_account = array(
                    'id'   => sanitize_text_field($a_res['result'][0]['id']),
                    'name' => sanitize_text_field($a_res['result'][0]['name'] ?? ''),
                );
            }
        }

        $account_id = $matched_account['id'] ?? $this->core->get_r2_account_id();

        // 4. Probe Pages Project capability (Optional)
        $can_pages      = false;
        $pages_projects = array();
        if (!empty($account_id)) {
            $p_res     = $this->core->cache->cf_api_request('accounts/' . rawurlencode($account_id) . '/pages/projects', $token, 'GET', null, 8);
            $can_pages = $p_res['ok'];
            if ($can_pages && !empty($p_res['result']) && is_array($p_res['result'])) {
                foreach ($p_res['result'] as $p) {
                    $p_subdomain    = sanitize_text_field($p['subdomain'] ?? '');
                    $custom_domains = array();

                    foreach ((array) ($p['domains'] ?? array()) as $d) {
                        $clean_d = sanitize_text_field($d);
                        if (empty($clean_d)) continue;
                        if (strpos($clean_d, '.pages.dev') !== false) {
                            if (empty($p_subdomain)) $p_subdomain = $clean_d;
                        } else {
                            $custom_domains[] = $clean_d;
                        }
                    }

                    // Prefer root/non-www domain first among custom domains
                    usort($custom_domains, function ($a, $b) {
                        $is_a_www = (strpos($a, 'www.') === 0);
                        $is_b_www = (strpos($b, 'www.') === 0);
                        return ($is_a_www !== $is_b_www) ? ($is_a_www ? 1 : -1) : (strlen($a) <=> strlen($b));
                    });

                    $primary_domain = !empty($custom_domains) ? $custom_domains[0] : $p_subdomain;
                    $all_domains    = array_merge($custom_domains, !empty($p_subdomain) ? array($p_subdomain) : array());

                    $pages_projects[] = array(
                        'name'           => sanitize_text_field($p['name'] ?? ''),
                        'subdomain'      => $p_subdomain,
                        'custom_domain'  => !empty($custom_domains) ? $custom_domains[0] : '',
                        'primary_domain' => $primary_domain,
                        'domains'        => $all_domains,
                    );
                }
            }
        }

        // 5. Probe R2 Storage capability (Optional)
        $can_r2  = false;
        $buckets = array();
        if (!empty($account_id)) {
            $r_res  = $this->core->cache->cf_api_request('accounts/' . rawurlencode($account_id) . '/r2/buckets', $token, 'GET', null, 8);
            $can_r2 = $r_res['ok'];
            if ($can_r2 && !empty($r_res['result']['buckets']) && is_array($r_res['result']['buckets'])) {
                foreach ($r_res['result']['buckets'] as $b) {
                    $buckets[] = sanitize_text_field($b['name'] ?? '');
                }
            }
        }

        // 6. Probe DNS capability (Optional)
        $zone_id = $matched_zone['id'] ?? $this->core->get_cf_zone_id();
        $can_dns = !empty($zone_id) && $this->core->cache->cf_api_request('zones/' . rawurlencode($zone_id) . '/dns_records?per_page=1', $token, 'GET', null, 8)['ok'];

        // Save persistent discovery cache (both transient and permanent option)
        $discovery_data = array(
            'account_id'     => $account_id,
            'zone_id'        => $zone_id,
            'zone_name'      => $matched_zone['name'] ?? '',
            'buckets'        => $buckets,
            'pages_projects' => $pages_projects,
        );
        AstroPress::set_discovery_cache($discovery_data);

        // Auto-persist verified credentials immediately so user only needs to provide token
        if (!empty($token) && strpos($token, '••••') === false) {
            update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
        }
        if (!empty($zone_id)) {
            update_option(AstroPress::OPTION_CF_ZONE_ID, $zone_id);
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
            update_option(AstroPress::OPTION_PURGE_ON_SAVE, '1');
        }
        if (!empty($account_id)) {
            update_option('astropress_r2_account_id', $account_id);
            update_option('r2g_account_id', $account_id);
        }
        if (empty($fe_url) && !empty($matched_zone['name'])) {
            update_option(AstroPress::OPTION_FRONTEND_URL, 'https://' . $matched_zone['name']);
        }

        $essential_pass = $can_zone_read;

        $primary_fe = '';
        if (!empty($pages_projects[0]['primary_domain'])) {
            $primary_fe = 'https://' . preg_replace('#^https?://#i', '', $pages_projects[0]['primary_domain']);
        }

        wp_send_json_success(array(
            'valid'                => true,
            'essential_pass'       => $essential_pass,
            'account_id'           => $account_id,
            'account_name'         => $matched_account['name'] ?? '',
            'zone_id'              => $zone_id,
            'zone_name'            => $matched_zone['name'] ?? '',
            'primary_frontend_url' => $primary_fe,
            'saved_pages_project'  => $this->core->get_cf_pages_project(),
            'saved_bucket'         => AstroPress_Admin::get_r2_option('bucket', ''),
            'permissions'          => array(
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
            $disc = AstroPress::get_discovery_cache();
            if (!empty($disc) && is_array($disc)) {
                $disc['selected_pages_project'] = $pages_proj;
                AstroPress::set_discovery_cache($disc);
            }
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
                $disc = AstroPress::get_discovery_cache();
                if (!empty($disc) && is_array($disc)) {
                    $disc['selected_bucket'] = $bucket;
                    AstroPress::set_discovery_cache($disc);
                }
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
            'WP_URL'            => $wp_api_url,
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
            $disc = AstroPress::get_discovery_cache();
            if (!empty($disc) && is_array($disc)) {
                $disc['selected_pages_project'] = $pages_proj;
                AstroPress::set_discovery_cache($disc);
            }
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
                $disc = AstroPress::get_discovery_cache();
                if (!empty($disc) && is_array($disc)) {
                    $disc['selected_bucket'] = $bucket;
                    AstroPress::set_discovery_cache($disc);
                }
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
     * Resolve Cloudflare Zone ID from options, discovery cache, or Cloudflare API
     *
     * @param string $token Optional Cloudflare API Token
     * @return string Zone ID or empty string
     */
    public function resolve_zone_id($token = '') {
        return $this->core->resolve_zone_id($token);
    }

    /**
     * Handle Cloudflare API error responses for Cache Rules operations uniformly.
     *
     * @param int    $code
     * @param array  $body
     * @param string $fallback_msg
     */
    protected function send_cf_cache_rules_error($code, $body, $fallback_msg = '') {
        $err_msg  = $body['errors'][0]['message'] ?? ($fallback_msg ?: sprintf(esc_html__('Cloudflare returned status code %d', 'astropress'), $code));
        $err_code = (int) ($body['errors'][0]['code'] ?? $code);

        if ($code === 401 || $code === 403 || $err_code === 10000 || stripos($err_msg, 'authentication error') !== false) {
            wp_send_json_error(array(
                'message'       => esc_html__('Cloudflare API Token lacks the optional "Zone > Cache Rules: Edit" permission. Standard edge purge and headless operations remain fully functional.', 'astropress'),
                'code'          => 10000,
                'missing_scope' => 'cache_settings',
                'fix_token_url' => $this->get_cf_token_template_url(array('cache_settings')),
            ));
        }

        wp_send_json_error(array('message' => $err_msg, 'code' => $err_code));
    }

    /**
     * Normalize a Cloudflare cache rule object for PUT payloads.
     *
     * @param array $r Raw rule payload.
     * @return array|null
     */
    protected function normalize_cf_cache_rule(array $r) {
        if (empty($r['expression']) || empty($r['action'])) {
            return null;
        }
        $item = array(
            'expression'        => $r['expression'],
            'description'       => $r['description'] ?? 'Cache Rule',
            'action'            => $r['action'],
            'action_parameters' => $r['action_parameters'] ?? array(),
            'enabled'           => isset($r['enabled']) ? (bool) $r['enabled'] : true,
        );
        foreach (array('id', 'ref') as $key) {
            if (!empty($r[$key])) {
                $item[$key] = $r[$key];
            }
        }
        return $item;
    }

    /**
     * Fetch the http_request_cache_settings entrypoint ruleset from Cloudflare.
     *
     * @param string $zone_id
     * @param string $token
     * @param bool   $allow_404
     * @return array{rules: array, ruleset_id: string}
     */
    protected function fetch_cf_cache_ruleset($zone_id, $token, $allow_404 = true) {
        $res = $this->core->cache->cf_api_request(
            'zones/' . rawurlencode($zone_id) . '/rulesets/phases/http_request_cache_settings/entrypoint',
            $token,
            'GET',
            null,
            12
        );
        if ($res['code'] === 0) {
            wp_send_json_error(array('message' => 'Cloudflare API connection error: ' . $res['error']));
        }
        if ($res['code'] === 404 && $allow_404) {
            return array('rules' => array(), 'ruleset_id' => '');
        }
        if (!$res['ok']) {
            $this->send_cf_cache_rules_error($res['code'], $res['body']);
        }

        return array(
            'rules'      => $res['result']['rules'] ?? array(),
            'ruleset_id' => $res['result']['id'] ?? '',
        );
    }

    /**
     * Save normalized cache rules back to Cloudflare Ruleset Engine.
     *
     * @param string $zone_id
     * @param string $token
     * @param array  $rules
     * @return array Updated rules from Cloudflare response
     */
    protected function put_cf_cache_ruleset($zone_id, $token, array $rules) {
        $res = $this->core->cache->cf_api_request(
            'zones/' . rawurlencode($zone_id) . '/rulesets/phases/http_request_cache_settings/entrypoint',
            $token,
            'PUT',
            array('rules' => $rules),
            15
        );
        if ($res['code'] === 0) {
            wp_send_json_error(array('message' => $res['error']));
        }
        if ($res['ok']) {
            return $res['result']['rules'] ?? array();
        }

        $this->send_cf_cache_rules_error($res['code'], $res['body']);
        return array();
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

        $data = $this->fetch_cf_cache_ruleset($zone_id, $token, true);
        $payload = array(
            'rules'      => $data['rules'],
            'ruleset_id' => $data['ruleset_id'],
        );
        if (empty($data['rules']) && empty($data['ruleset_id'])) {
            $payload['message'] = esc_html__('No cache rules configured in Ruleset Engine.', 'astropress');
        }
        wp_send_json_success($payload);
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

        $extract_host = static function ($raw) {
            $parsed = wp_parse_url($raw);
            $host   = !empty($parsed['host']) ? $parsed['host'] : trim(preg_replace('#^https?://#i', '', (string) $raw), '/');
            return explode('/', $host)[0];
        };

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
                'action_parameters' => array('cache' => false),
                'enabled'           => true,
            );
        } elseif ($preset === 'r2_cdn') {
            $cdn_domain = sanitize_text_field(wp_unslash($_POST['cdn_domain'] ?? '')) ?: astropress()->get_r2_option('custom_domain', '');
            $host       = $extract_host($cdn_domain);
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
        } elseif ($preset === 'astro_ssr' || $preset === 'astro_edge_override') {
            $host = $extract_host($this->core->get_frontend_url());
            if (empty($host)) {
                wp_send_json_error(array('message' => esc_html__('Frontend host could not be determined. Please configure Frontend URL first.', 'astropress')));
            }
            $ttl_cfg = ($preset === 'astro_edge_override')
                ? array('mode' => 'override_origin', 'default' => 31536000)
                : array('mode' => 'respect_origin');
            $rule_to_add = array(
                'expression'        => '(http.host eq "' . addslashes($host) . '")',
                'description'       => ($preset === 'astro_edge_override')
                    ? 'AstroPress: 1-Year Full Edge Cache (' . $host . ')'
                    : 'AstroPress: Global Edge Cache (Respect Origin Headers)',
                'action'            => 'set_cache_settings',
                'action_parameters' => array(
                    'cache'       => true,
                    'edge_ttl'    => $ttl_cfg,
                    'browser_ttl' => $ttl_cfg,
                ),
                'enabled'           => true,
            );
        } elseif (!empty($custom_expr)) {
            $cache_action = sanitize_key($_POST['cache_action'] ?? 'cache');
            if ($cache_action === 'bypass') {
                $action_params = array('cache' => false);
            } else {
                $edge_mode    = sanitize_text_field($_POST['edge_ttl_mode'] ?? 'override_origin');
                $edge_val     = absint($_POST['edge_ttl_default'] ?? 31536000);
                $browser_mode = sanitize_text_field($_POST['browser_ttl_mode'] ?? 'override_origin');
                $browser_val  = absint($_POST['browser_ttl_default'] ?? 31536000);

                $action_params = array(
                    'cache'       => true,
                    'edge_ttl'    => ($edge_mode === 'override_origin') ? array('mode' => 'override_origin', 'default' => $edge_val) : array('mode' => 'respect_origin'),
                    'browser_ttl' => ($browser_mode === 'override_origin') ? array('mode' => 'override_origin', 'default' => $browser_val) : array('mode' => 'respect_origin'),
                );
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

        $ruleset     = $this->fetch_cf_cache_ruleset($zone_id, $token, true);
        $clean_rules = array();
        $updated     = false;

        foreach ($ruleset['rules'] as $r) {
            $item = $this->normalize_cf_cache_rule((array) $r);
            if ($item === null) {
                continue;
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

        $saved_rules = $this->put_cf_cache_ruleset($zone_id, $token, $clean_rules);
        wp_send_json_success(array(
            'message' => esc_html__('Cache rule deployed successfully to Cloudflare Ruleset Engine!', 'astropress'),
            'rules'   => $saved_rules,
        ));
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

        $ruleset     = $this->fetch_cf_cache_ruleset($zone_id, $token, false);
        $clean_rules = array();
        $found       = false;

        foreach ($ruleset['rules'] as $r) {
            $item = $this->normalize_cf_cache_rule((array) $r);
            if ($item === null) {
                continue;
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

        $this->put_cf_cache_ruleset($zone_id, $token, $clean_rules);
        wp_send_json_success(array(
            'message' => $enabled
                ? esc_html__('Cache rule enabled.', 'astropress')
                : esc_html__('Cache rule disabled.', 'astropress'),
        ));
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

        $ruleset   = $this->fetch_cf_cache_ruleset($zone_id, $token, false);
        $new_rules = array();

        foreach ($ruleset['rules'] as $r) {
            if (($r['id'] ?? '') === $rule_id) {
                continue;
            }
            $item = $this->normalize_cf_cache_rule((array) $r);
            if ($item !== null) {
                $new_rules[] = $item;
            }
        }

        $this->put_cf_cache_ruleset($zone_id, $token, $new_rules);
        wp_send_json_success(array('message' => esc_html__('Cache rule deleted from Cloudflare Ruleset Engine.', 'astropress')));
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
