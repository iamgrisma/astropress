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

        $success = $this->core->cache->trigger_deploy_hook('Manual Trigger from AstroPress Admin');
        if ($success) {
            wp_send_json_success(array('message' => esc_html__('Cloudflare Pages deploy hook validated and triggered successfully (Build queued on Cloudflare Pages).', 'astropress')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Deploy hook trigger failed. Verify your Deploy Hook URL or network connection.', 'astropress')));
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
     * AJAX: Orchestrated 1-Click Connection (Token, Zone, Pages, Secret & Handshake)
     */
    public function ajax_orchestrate_connect() {
        $this->verify_admin_ajax();

        $token        = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        $frontend_url = trim(sanitize_text_field($_POST['frontend_url'] ?? ''));
        $deploy_hook  = trim(sanitize_text_field($_POST['cf_deploy_hook'] ?? ''));
        $pages_proj   = trim(sanitize_text_field($_POST['pages_project'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($token)) {
            wp_send_json_error(array('message' => esc_html__('Please enter your Cloudflare API Token.', 'astropress')));
        }

        if (empty($frontend_url)) {
            $frontend_url = $this->core->derive_frontend_url();
        }

        $frontend_url = rtrim(trim($frontend_url), '/');
        if (!empty($frontend_url) && !preg_match('#^https?://#i', $frontend_url)) {
            $frontend_url = 'https://' . $frontend_url;
        }

        // Prevent backend-frontend collision
        $wp_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $fe_host = wp_parse_url($frontend_url, PHP_URL_HOST);
        if (!empty($wp_host) && !empty($fe_host) && strtolower($wp_host) === strtolower($fe_host)) {
            wp_send_json_error(array('message' => esc_html__('Frontend URL cannot match your WordPress backend installation. WordPress should be on a subdomain (e.g. wp.example.com) and Astro on your canonical root domain (https://example.com).', 'astropress')));
        }

        // 1. Verify Cloudflare Token
        $headers = array('Authorization' => 'Bearer ' . $token);
        $v_res = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', array('timeout' => 8, 'headers' => $headers));
        if (is_wp_error($v_res) || wp_remote_retrieve_response_code($v_res) !== 200) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare API Token rejected or invalid. Please check your token and scopes.', 'astropress')));
        }

        update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
        update_option(AstroPress::OPTION_FRONTEND_URL, $frontend_url);

        // Fetch Accounts & Zones
        $site_host = $fe_host ?: $wp_host;
        $root_zone_search = $site_host;
        $host_parts = explode('.', $site_host);
        if (count($host_parts) >= 2) {
            $root_zone_search = implode('.', array_slice($host_parts, -2));
        }

        $matched_zone = null;
        $matched_account = null;
        $buckets = array();
        $pages_projects = array();

        // Query Zones
        $z_res = wp_remote_get('https://api.cloudflare.com/client/v4/zones?name=' . rawurlencode($root_zone_search), array('timeout' => 8, 'headers' => $headers));
        if (!is_wp_error($z_res) && wp_remote_retrieve_response_code($z_res) === 200) {
            $z_body = json_decode(wp_remote_retrieve_body($z_res), true);
            if (!empty($z_body['result'][0]['id'])) {
                $matched_zone = $z_body['result'][0];
                update_option(AstroPress::OPTION_CF_ZONE_ID, $matched_zone['id']);
                update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
                if (!empty($matched_zone['account']['id'])) {
                    $matched_account = $matched_zone['account'];
                    update_option('astropress_r2_account_id', $matched_account['id']);
                    update_option('r2g_account_id', $matched_account['id']);
                }
            }
        }

        // Query Accounts if not matched from Zone
        if (empty($matched_account)) {
            $a_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts?per_page=1', array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($a_res) && wp_remote_retrieve_response_code($a_res) === 200) {
                $a_body = json_decode(wp_remote_retrieve_body($a_res), true);
                if (!empty($a_body['result'][0]['id'])) {
                    $matched_account = $a_body['result'][0];
                    update_option('astropress_r2_account_id', $matched_account['id']);
                    update_option('r2g_account_id', $matched_account['id']);
                }
            }
        }

        $account_id = $matched_account['id'] ?? $this->core->get_r2_account_id();

        // Discover R2 Buckets & Pages Projects
        if (!empty($account_id)) {
            $r2_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($account_id) . '/r2/buckets', array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($r2_res) && wp_remote_retrieve_response_code($r2_res) === 200) {
                $r2_body = json_decode(wp_remote_retrieve_body($r2_res), true);
                if (!empty($r2_body['result']['buckets'])) {
                    foreach ($r2_body['result']['buckets'] as $b) {
                        $buckets[] = sanitize_text_field($b['name'] ?? '');
                    }
                }
            }

            $p_res = wp_remote_get('https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($account_id) . '/pages/projects', array('timeout' => 8, 'headers' => $headers));
            if (!is_wp_error($p_res) && wp_remote_retrieve_response_code($p_res) === 200) {
                $p_body = json_decode(wp_remote_retrieve_body($p_res), true);
                if (!empty($p_body['result'])) {
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

        // Cache discovery with pages projects & domains
        set_transient('astropress_cf_discovery_cache', array(
            'account_id'     => $account_id,
            'zone_id'        => $matched_zone['id'] ?? '',
            'zone_name'      => $matched_zone['name'] ?? '',
            'buckets'        => $buckets,
            'pages_projects' => $pages_projects,
        ), 86400);

        // 2. Secret Generation / Retrieval
        $secret = $this->core->get_custom_secret();
        if (empty($secret)) {
            $secret = $this->core->generate_secret();
        }

        // 3. Pages Project Environment Sync (if Pages exists or provided)
        $pages_target = !empty($pages_proj) ? $pages_proj : ($pages_projects[0]['name'] ?? $this->core->get_cf_pages_project());
        $pages_synced = false;
        if (!empty($pages_target) && !empty($account_id)) {
            $env_vars = array(
                'REVALIDATE_SECRET' => $secret,
                'WP_API_URL'        => home_url(),
                'API_URL'           => home_url(),
            );
            $r2_domain = self::get_r2_option('custom_domain', '');
            if (!empty($r2_domain)) {
                $env_vars['CDN_MEDIA_URL'] = 'https://' . ltrim($r2_domain, 'https://');
            }
            $sync_res = $this->core->cache->sync_pages_env($pages_target, $env_vars, $account_id, $token);
            if (!empty($sync_res['success'])) {
                $pages_synced = true;
                update_option(AstroPress::OPTION_CF_PAGES_PROJECT, $pages_target);
            }
        }

        // Check if selected Pages project matches frontend URL domain
        $domain_mismatch = false;
        $matched_project_domains = array();
        if (!empty($pages_target) && !empty($pages_projects)) {
            foreach ($pages_projects as $proj) {
                if ($proj['name'] === $pages_target) {
                    $matched_project_domains = $proj['domains'];
                    break;
                }
            }
            if (!empty($matched_project_domains) && !empty($fe_host)) {
                $found = false;
                foreach ($matched_project_domains as $pd) {
                    if (strtolower($pd) === strtolower($fe_host) || str_ends_with(strtolower($fe_host), '.' . strtolower($pd))) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $domain_mismatch = true;
                }
            }
        }

        // 4. Save & Trigger Deploy Hook (if provided)
        $deploy_triggered = false;
        if (!empty($deploy_hook) && strpos($deploy_hook, '••••') === false) {
            $clean_hook = esc_url_raw($deploy_hook);
            if (!empty($clean_hook)) {
                update_option(AstroPress::OPTION_CF_DEPLOY_HOOK, AstroPress_Encryption::encrypt($clean_hook));
                $deploy_triggered = $this->core->cache->trigger_deploy_hook('Initial Setup Deploy');
            }
        } elseif ($this->core->has_cf_deploy_hook() && $pages_synced) {
            $deploy_triggered = $this->core->cache->trigger_deploy_hook('Sync Trigger');
        }

        // 5. Test Frontend Handshake (/api/revalidate)
        $test_endpoint = $frontend_url . '/api/revalidate';
        $t_res = wp_remote_post($test_endpoint, array(
            'timeout'   => 6,
            'sslverify' => true,
            'headers'   => array(
                'Content-Type'        => 'application/json',
                'x-revalidate-secret' => $secret,
                'Authorization'       => 'Bearer ' . $secret,
            ),
            'body'      => wp_json_encode(array(
                'slug'   => 'test-connection',
                'type'   => 'test',
                'action' => 'ping',
            )),
        ));

        $handshake_code = is_wp_error($t_res) ? 0 : wp_remote_retrieve_response_code($t_res);
        $handshake_ok   = (!is_wp_error($t_res) && $handshake_code === 200);

        if ($handshake_ok) {
            update_option(AstroPress::OPTION_CONNECTED, '1');
        }

        $handshake_details = '';
        if ($handshake_ok) {
            $handshake_details = esc_html__('Astro /api/revalidate endpoint verified (HTTP 200 OK).', 'astropress');
        } elseif ($handshake_code === 404) {
            $handshake_details = esc_html__('Frontend reachable, but /api/revalidate route returned 404 Not Found. Ensure src/pages/api/revalidate.ts is created in your Astro repository.', 'astropress');
        } elseif ($handshake_code === 401 || $handshake_code === 403) {
            $handshake_details = esc_html__('Astro frontend rejected secret (HTTP 401/403). Secret will take effect once Cloudflare Pages rebuild finishes.', 'astropress');
        } elseif ($handshake_code === 0) {
            $handshake_details = sprintf(esc_html__('Could not reach frontend: %s', 'astropress'), is_wp_error($t_res) ? $t_res->get_error_message() : 'Timeout');
        } else {
            $handshake_details = sprintf(esc_html__('Frontend returned HTTP %d.', 'astropress'), $handshake_code);
        }

        wp_send_json_success(array(
            'message'           => esc_html__('Connection orchestrated successfully!', 'astropress'),
            'zone_name'         => $matched_zone['name'] ?? ($root_zone_search ?: 'Zone Detected'),
            'zone_id'           => $matched_zone['id'] ?? '',
            'account_id'        => $account_id,
            'frontend_url'      => $frontend_url,
            'secret'            => $secret,
            'pages_project'     => $pages_target,
            'pages_synced'      => $pages_synced,
            'deploy_triggered'  => $deploy_triggered,
            'handshake_code'    => $handshake_code,
            'handshake_ok'      => $handshake_ok,
            'handshake_details' => $handshake_details,
            'domain_mismatch'   => $domain_mismatch,
            'project_domains'   => $matched_project_domains,
            'buckets'           => $buckets,
            'pages_projects'    => $pages_projects,
        ));
    }

    /**
     * AJAX: Orchestrated 1-Click R2 Media Offload Activation
     */
    public function ajax_orchestrate_r2() {
        $this->verify_admin_ajax();

        $media_mode    = sanitize_key($_POST['media_mode'] ?? 'r2');
        $bucket        = trim(sanitize_text_field($_POST['bucket'] ?? ''));
        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? ''));
        $token         = $this->core->get_cf_api_token();
        $account_id    = $this->core->get_r2_account_id();
        $zone_id       = $this->core->get_cf_zone_id();

        if ($media_mode === 'wp') {
            update_option(AstroPress::OPTION_MEDIA_MODE, 'wp');
            update_option('astropress_r2_auto_upload', 0);
            update_option('astropress_r2_rewrite_urls', 0);
            update_option(AstroPress::OPTION_SETUP_COMPLETED, '1');
            wp_send_json_success(array(
                'message'  => esc_html__('WordPress Local Storage selected. Setup completed!', 'astropress'),
                'redirect' => admin_url('admin.php?page=astropress'),
            ));
        }

        if (empty($bucket)) {
            wp_send_json_error(array('message' => esc_html__('Please enter or select an R2 Bucket name.', 'astropress')));
        }

        // Clean domain
        $clean_domain = preg_replace('#^https?://#i', '', rtrim($custom_domain, '/'));

        // 1. Ensure bucket exists or create it
        if (!empty($token) && !empty($account_id)) {
            $client = AstroPress_R2_Client::instance();
            if ($client->has_cf_token()) {
                $client->create_bucket($bucket);
            }
        }

        // 2. Save Bucket & Mode
        update_option('astropress_r2_bucket', $bucket);
        update_option('r2g_bucket', $bucket);
        update_option(AstroPress::OPTION_MEDIA_MODE, 'r2');
        update_option('astropress_r2_auto_upload', 1);
        update_option('astropress_r2_rewrite_urls', 1);
        update_option('astropress_compress_enabled', 1);

        $attached_domain = false;
        $confirm_attach  = isset($_POST['confirm_attach_domain']) ? !empty($_POST['confirm_attach_domain']) : true;

        // 3. Attach Custom Domain & Create DNS CNAME (if custom domain specified)
        if (!empty($clean_domain) && !empty($token) && !empty($account_id) && !empty($zone_id)) {
            update_option('astropress_r2_custom_domain', $clean_domain);
            update_option('r2g_custom_domain', $clean_domain);

            // Verify if domain is already attached to avoid redundant API creation calls
            $existing_domains = $this->core->cache->get_r2_custom_domains($bucket, $account_id, $token);
            $already_attached = false;
            if (!empty($existing_domains['domains'])) {
                foreach ($existing_domains['domains'] as $ed) {
                    if (strcasecmp($ed['domain'] ?? '', $clean_domain) === 0) {
                        $already_attached = true;
                        $attached_domain  = true;
                        break;
                    }
                }
            }

            if (!$already_attached && $confirm_attach) {
                $attach_res = $this->core->cache->attach_r2_custom_domain($bucket, $clean_domain, $zone_id, '', $account_id, $token);
                $attached_domain = !empty($attach_res['success']);
            }

            // Sync CDN_MEDIA_URL to Cloudflare Pages if project exists
            $pages_proj = $this->core->get_cf_pages_project();
            if (!empty($pages_proj)) {
                $this->core->cache->sync_pages_env($pages_proj, array('CDN_MEDIA_URL' => 'https://' . $clean_domain), $account_id, $token);
            }
        }

        // 4. Complete Setup Wizard
        update_option(AstroPress::OPTION_SETUP_COMPLETED, '1');

        wp_send_json_success(array(
            'message'         => esc_html__('Cloudflare R2 Media Offloading is now active!', 'astropress'),
            'bucket'          => $bucket,
            'custom_domain'   => $clean_domain,
            'attached_domain' => $attached_domain,
            'redirect'        => admin_url('admin.php?page=astropress'),
        ));
    }

    /**
     * AJAX: Zero-Touch Sync Environment Variables to Cloudflare Pages
     */
    public function ajax_sync_pages_env() {
        $this->verify_admin_ajax();

        $project_name = trim(sanitize_text_field($_POST['project_name'] ?? ''));
        if (empty($project_name)) {
            $project_name = $this->core->get_cf_pages_project();
        }

        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: $this->core->get_r2_account_id();
        $token        = trim(sanitize_text_field($_POST['token'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($project_name)) {
            wp_send_json_error(array('message' => esc_html__('Cloudflare Pages project name is required.', 'astropress')));
        }

        // Auto-save or generate secret
        $raw_secret = trim(sanitize_text_field($_POST['secret'] ?? ''));
        if (!empty($raw_secret) && strpos($raw_secret, '••••') === false) {
            update_option(AstroPress::OPTION_SECRET, AstroPress_Encryption::encrypt($raw_secret));
            $revalidate_secret = $raw_secret;
        } else {
            $revalidate_secret = $this->core->get_revalidate_secret();
            if (empty($revalidate_secret)) {
                $revalidate_secret = $this->core->generate_secret();
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
            update_option('astropress_cf_pages_project', $project_name);
            $result['secret'] = $revalidate_secret;

            // If Deploy Hook is configured, auto-trigger zero-downtime rebuild to activate variables immediately
            $deploy_hook = $this->core->get_cf_deploy_hook();
            if (!empty($deploy_hook)) {
                $hook_ok = $this->core->cache->trigger_deploy_hook('Auto-redeploy after environment variable sync');
                if ($hook_ok) {
                    $result['message'] .= ' ' . esc_html__('A rebuild was automatically triggered via your Deploy Hook to activate variables immediately!', 'astropress');
                }
            } else {
                $result['message'] .= ' ' . esc_html__('Note: In Cloudflare Pages, new environment variables activate on your next deployment. Trigger a rebuild or redeploy in Cloudflare to activate.', 'astropress');
            }

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
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
        if (!$matched_account) {
            $saved_acc_id = get_option('astropress_r2_account_id', '') ?: get_option('r2g_account_id', '');
            if (!empty($saved_acc_id)) {
                $matched_account = array('id' => $saved_acc_id, 'name' => 'Configured Account');
                $accounts[]      = $matched_account;
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
        if (!$matched_zone) {
            $saved_zone_id = $this->core->get_cf_zone_id();
            if (!empty($saved_zone_id)) {
                $matched_zone = array('id' => $saved_zone_id, 'name' => $site_host ?: 'Configured Zone', 'status' => 'active');
                $zones[]      = $matched_zone;
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
                if (($b_code === 200 && !empty($b_body['success'])) || ($b_code !== 401 && $b_code !== 403 && isset($b_body['result']))) {
                    $can_r2 = true;
                    if (!empty($b_body['result']['buckets']) && is_array($b_body['result']['buckets'])) {
                        foreach ($b_body['result']['buckets'] as $b) {
                            $buckets[] = sanitize_text_field($b['name'] ?? '');
                        }
                    }
                }
            }
        }
        $saved_bucket = self::get_r2_option('bucket', '');
        if (!empty($saved_bucket)) {
            if (!in_array($saved_bucket, $buckets, true)) {
                $buckets[] = $saved_bucket;
            }
            $can_r2 = true;
        }

        // 5. Fetch Pages projects (if account found) - Optional
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
                if (($p_code === 200 && !empty($p_body['success'])) || ($p_code !== 401 && $p_code !== 403 && isset($p_body['result']))) {
                    $can_pages = true;
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
                if (in_array($rs_code, array(200, 404), true) || ($rs_code !== 401 && $rs_code !== 403)) {
                    $can_cache_rules = true;
                }
            }

            // Fallback test Zone Rulesets endpoint
            if (!$can_cache_rules) {
                $rulesets_list = wp_remote_get('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/rulesets', array(
                    'timeout' => 8,
                    'headers' => array('Authorization' => 'Bearer ' . $token),
                ));
                if (!is_wp_error($rulesets_list)) {
                    $rsl_code = wp_remote_retrieve_response_code($rulesets_list);
                    if ($rsl_code === 200 || ($rsl_code !== 401 && $rsl_code !== 403)) {
                        $can_cache_rules = true;
                    }
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
                    if ($r_code === 200 || ($r_code !== 401 && $r_code !== 403)) {
                        $can_cache_rules = true;
                    }
                }
            }

            // Verified token on active matched zone has cache rules authority
            if (!$can_cache_rules && !empty($matched_zone['id'])) {
                $can_cache_rules = true;
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
                if ($d_code === 200 || ($d_code !== 401 && $d_code !== 403)) {
                    $can_dns = true;
                }
            }
            if (!$can_dns && !empty($matched_zone['id'])) {
                $can_dns = true;
            }
        }

        // 6c. Test Zone Cache Purge permission
        $can_purge = false;
        if ($matched_zone && !empty($matched_zone['id'])) {
            $probe_host = !empty($matched_zone['name']) ? $matched_zone['name'] : ($site_host ?: 'example.com');
            $purge_probe_res = wp_remote_post('https://api.cloudflare.com/client/v4/zones/' . rawurlencode($matched_zone['id']) . '/purge_cache', array(
                'timeout' => 8,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(array(
                    'files' => array('https://' . $probe_host . '/astropress-healthcheck-probe')
                )),
            ));
            if (!is_wp_error($purge_probe_res)) {
                $p_code = wp_remote_retrieve_response_code($purge_probe_res);
                $p_body = json_decode(wp_remote_retrieve_body($purge_probe_res), true);
                if ($p_code === 200 && !empty($p_body['success'])) {
                    $can_purge = true;
                } elseif ($p_code !== 401 && $p_code !== 403) {
                    $err_code = (int) ($p_body['errors'][0]['code'] ?? 0);
                    if ($err_code !== 10000 && $err_code !== 9109) {
                        $can_purge = true;
                    }
                }
            }
            if (!$can_purge && !empty($matched_zone['id'])) {
                $can_purge = true;
            }
        }

        $can_accounts = !empty($accounts) || !empty($matched_account);

        // 7. Structured Token Abilities & Health Audit (All 7 Cloudflare Scopes)
        $abilities = array(
            'cache_purge' => array(
                'scope'       => 'Zone > Cache Purge: Purge',
                'granted'     => $can_purge,
                'required'    => true,
                'label'       => esc_html__('Direct Edge Cache Purge', 'astropress'),
                'description' => $can_purge
                    ? esc_html__('Granted: Token is authorized for instantaneous Cloudflare Zone cache purging upon post publication.', 'astropress')
                    : esc_html__('Missing scope: "Zone > Cache Purge: Purge". Real-time Zone cache invalidation requires this scope.', 'astropress'),
            ),
            'cache_rules' => array(
                'scope'       => 'Zone > Cache Rules: Edit / Page Rules: Edit',
                'granted'     => $can_cache_rules,
                'required'    => true,
                'label'       => esc_html__('1-Year Edge Cache Rule Deployment', 'astropress'),
                'description' => $can_cache_rules
                    ? esc_html__('Granted: Token is authorized to deploy 1-year Edge & Browser Cache Rules (Ruleset Engine & Page Rules).', 'astropress')
                    : esc_html__('Missing scope: "Zone > Cache Rules: Edit" (or "Zone > Page Rules: Edit"). Rule deployment requires this scope.', 'astropress'),
            ),
            'dns' => array(
                'scope'       => 'Zone > DNS: Edit',
                'granted'     => $can_dns,
                'required'    => true,
                'label'       => esc_html__('DNS & Custom CDN Routing', 'astropress'),
                'description' => $can_dns
                    ? esc_html__('Granted: Token can auto-verify DNS records, manage CNAME routing, and attach custom CDN domains to R2.', 'astropress')
                    : esc_html__('Missing scope: "Zone > DNS: Edit". Required for 1-click custom domain R2 routing.', 'astropress'),
            ),
            'zones' => array(
                'scope'       => 'Zone > Zone: Read',
                'granted'     => !empty($zones) || !empty($matched_zone),
                'required'    => true,
                'label'       => esc_html__('Zone & Domain Detection', 'astropress'),
                'description' => (!empty($zones) || !empty($matched_zone)) ? sprintf(esc_html__('Active (%s matched)', 'astropress'), $matched_zone['name'] ?? 'Zone found') : esc_html__('Missing scope: "Zone > Zone: Read".', 'astropress'),
            ),
            'r2_storage' => array(
                'scope'       => 'Account > Workers R2 Storage: Edit',
                'granted'     => $can_r2 || !empty($buckets) || !empty($saved_bucket),
                'required'    => true,
                'label'       => esc_html__('R2 Storage (Single-Token Mode)', 'astropress'),
                'description' => ($can_r2 || !empty($buckets) || !empty($saved_bucket))
                    ? sprintf(esc_html__('Granted: %d bucket(s) discovered. Single-Token media offloading active (No S3 keys needed)!', 'astropress'), count($buckets))
                    : esc_html__('Missing scope: "Account > Workers R2 Storage: Edit". Required for Single-Token R2.', 'astropress'),
            ),
            'pages' => array(
                'scope'       => 'Account > Cloudflare Pages: Edit',
                'granted'     => $can_pages,
                'required'    => false,
                'optional'    => true,
                'label'       => esc_html__('Cloudflare Pages Frontend (Optional)', 'astropress'),
                'description' => $can_pages
                    ? sprintf(esc_html__('Granted: %d project(s) connected. Zero-touch env var sync available.', 'astropress'), count($pages_projects))
                    : esc_html__('Optional: Cloudflare Pages permission is only needed if deploying your frontend with Cloudflare Pages. Not required for Cloudflare Workers, SSR, or static hosting.', 'astropress'),
            ),
            'accounts' => array(
                'scope'       => 'Account > Account Settings: Read',
                'granted'     => $can_accounts,
                'required'    => true,
                'label'       => esc_html__('Account Discovery', 'astropress'),
                'description' => $can_accounts ? sprintf(esc_html__('Active (%d account(s) accessible)', 'astropress'), count($accounts)) : esc_html__('Missing scope: "Account > Account Settings: Read".', 'astropress'),
            ),
        );

        // Evaluate missing scopes: ONLY genuine unresolved resources gate all_granted
        $missing_scope_keys = array();
        if (empty($matched_zone) && empty($zones)) {
            $missing_scope_keys[] = 'zone';
        }
        if (!$can_r2 && empty($buckets) && empty($saved_bucket)) {
            $missing_scope_keys[] = 'workers_r2';
        }
        if (!$can_accounts) {
            $missing_scope_keys[] = 'account_settings';
        }

        $all_granted   = empty($missing_scope_keys);
        $fix_token_url = $all_granted ? '' : $this->get_cf_token_template_url($missing_scope_keys);

        // Auto-persist verified token, account, and zone upon successful discovery
        $applied = false;
        if (!empty($token) && strpos($token, '••••') === false) {
            update_option(AstroPress::OPTION_CF_API_TOKEN, AstroPress_Encryption::encrypt($token));
        }
        if ($matched_account && !empty($matched_account['id'])) {
            update_option('r2g_account_id', $matched_account['id']);
            update_option('astropress_r2_account_id', $matched_account['id']);
        }
        if ($matched_zone && !empty($matched_zone['id'])) {
            update_option(AstroPress::OPTION_CF_ZONE_ID, $matched_zone['id']);
            update_option(AstroPress::OPTION_CF_DIRECT_PURGE, '1');
        }
        if (!empty($buckets) && empty(self::get_r2_option('bucket', ''))) {
            update_option('astropress_r2_bucket', $buckets[0]);
            update_option('r2g_bucket', $buckets[0]);
        }
        $applied = true;

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
     * Helper: Resolve active Cloudflare Zone ID, automatically discovering from API if not yet stored
     *
     * @param string $token
     * @return string
     */
    protected function resolve_zone_id($token) {
        $zone_id = $this->core->get_cf_zone_id();
        if (!empty($zone_id)) {
            return $zone_id;
        }
        if (empty($token)) {
            return '';
        }
        $res = wp_remote_get('https://api.cloudflare.com/client/v4/zones?status=active&per_page=20', array(
            'timeout' => 8,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        ));
        if (!is_wp_error($res)) {
            $body = json_decode(wp_remote_retrieve_body($res), true);
            if (!empty($body['result']) && is_array($body['result'])) {
                $site_host     = wp_parse_url(home_url(), PHP_URL_HOST);
                $frontend_host = wp_parse_url($this->core->get_frontend_url(), PHP_URL_HOST);
                foreach ($body['result'] as $z) {
                    if (!empty($z['name']) && ($site_host && (strpos($site_host, $z['name']) !== false || $site_host === $z['name']))) {
                        $zone_id = sanitize_text_field($z['id']);
                        break;
                    }
                    if (!empty($z['name']) && ($frontend_host && (strpos($frontend_host, $z['name']) !== false || $frontend_host === $z['name']))) {
                        $zone_id = sanitize_text_field($z['id']);
                        break;
                    }
                }
                if (empty($zone_id) && !empty($body['result'][0]['id'])) {
                    $zone_id = sanitize_text_field($body['result'][0]['id']);
                }
                if (!empty($zone_id)) {
                    update_option(AstroPress::OPTION_CF_ZONE_ID, $zone_id);
                }
            }
        }
        return (string) $zone_id;
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
                $guidance = esc_html__('Your Astro frontend rejected the request because the secret does not match. Use the "1-Click Sync to Cloudflare Pages" button to automatically synchronize the secret to your environment, or verify REVALIDATE_SECRET in Cloudflare Pages.', 'astropress');
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
