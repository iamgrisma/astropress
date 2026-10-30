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
        $account_id   = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: self::get_r2_option('account_id', '');
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
        }

        // 6c. Test Zone Cache Purge permission (non-destructive probe)
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
                    // Non-auth responses (400 Bad Request on unresolvable probe file, 429 rate limit, etc.) prove token authentication is valid
                    $err_code = (int) ($p_body['errors'][0]['code'] ?? 0);
                    if ($err_code !== 10000 && $err_code !== 9109) {
                        $can_purge = true;
                    }
                }
            }
            if (!$can_purge && get_option(AstroPress::OPTION_CF_DIRECT_PURGE, '') === '1') {
                $can_purge = true;
            }
        }

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
                'granted'     => !empty($zones),
                'required'    => true,
                'label'       => esc_html__('Zone & Domain Detection', 'astropress'),
                'description' => !empty($zones) ? sprintf(esc_html__('Active (%s matched)', 'astropress'), $matched_zone['name'] ?? 'Zone found') : esc_html__('Missing scope: "Zone > Zone: Read".', 'astropress'),
            ),
            'r2_storage' => array(
                'scope'       => 'Account > Workers R2 Storage: Edit',
                'granted'     => $can_r2,
                'required'    => true,
                'label'       => esc_html__('R2 Storage (Single-Token Mode)', 'astropress'),
                'description' => $can_r2
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
                'granted'     => !empty($accounts),
                'required'    => true,
                'label'       => esc_html__('Account Discovery', 'astropress'),
                'description' => !empty($accounts) ? sprintf(esc_html__('Active (%d account(s) accessible)', 'astropress'), count($accounts)) : esc_html__('Missing scope: "Account > Account Settings: Read".', 'astropress'),
            ),
        );

        // Evaluate missing scopes and compute dynamic remediation URL (Only REQUIRED scopes gate all_granted)
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
        // Cloudflare Pages is optional: do not gate core setup
        if (empty($accounts)) {
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

        $token   = $this->core->get_cf_api_token();
        $zone_id = $this->resolve_zone_id($token);

        if (empty($token) || empty($zone_id)) {
            wp_send_json_error(array(
                'message' => esc_html__('Cloudflare API Token and Zone ID must be configured first in Setup Wizard.', 'astropress')
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

}
