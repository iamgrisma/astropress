<?php
/**
 * Admin Trait: R2 Storage, Optimizer & Onboarding AJAX Endpoints
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

trait AstroPress_Admin_Ajax_R2 {
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
     * AJAX: Test Cloudflare R2 Connection
     *
     * Supports both Single-Token Bearer REST Mode and S3 SigV4 Mode.
     */
    public function ajax_test_connection() {
        $this->verify_admin_ajax();

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: self::get_r2_option('account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: self::get_r2_option('access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_enc = self::get_r2_option('secret_key', '');
            $secret_key = !empty($secret_enc) ? AstroPress_Encryption::decrypt($secret_enc) : '';
        }

        $cf_token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (empty($cf_token) || strpos($cf_token, '••••') !== false) {
            $cf_token = $this->core->get_cf_api_token();
        }

        $bucket        = sanitize_text_field($_POST['bucket'] ?? '') ?: self::get_r2_option('bucket', '');
        $custom_domain = sanitize_text_field($_POST['custom_domain'] ?? '') ?: self::get_r2_option('custom_domain', '');

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

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: self::get_r2_option('account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: self::get_r2_option('access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_enc = self::get_r2_option('secret_key', '');
            $secret_key = !empty($secret_enc) ? AstroPress_Encryption::decrypt($secret_enc) : '';
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
        $cdn_url = trim(sanitize_text_field($_POST['cdn_domain'] ?? '')) ?: self::get_r2_option('custom_domain', '');

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

        $account_id    = sanitize_text_field($_POST['account_id'] ?? '') ?: self::get_r2_option('account_id', '');
        $access_key    = sanitize_text_field($_POST['access_key'] ?? '') ?: self::get_r2_option('access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_enc = self::get_r2_option('secret_key', '');
            $secret_key = !empty($secret_enc) ? AstroPress_Encryption::decrypt($secret_enc) : '';
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
     * AJAX: Audit Custom CDN Domain for DNS, R2 Bucket Attachment & Edge Rules
     */
    public function ajax_audit_cdn_domain() {
        $this->verify_admin_ajax();

        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? '')) ?: self::get_r2_option('custom_domain', '');
        $zone_id       = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();
        $account_id    = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: self::get_r2_option('account_id', '');
        $bucket_name   = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: self::get_r2_option('bucket', '');
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

        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? '')) ?: self::get_r2_option('custom_domain', '');
        $bucket_name   = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: self::get_r2_option('bucket', '');
        $zone_id       = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: $this->core->get_cf_zone_id();
        $zone_name     = trim(sanitize_text_field($_POST['zone_name'] ?? ''));
        $account_id    = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: self::get_r2_option('account_id', '');
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

}
