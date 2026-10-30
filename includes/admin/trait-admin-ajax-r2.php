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
     * AJAX: Test Cloudflare R2 Connection
     *
     * Supports both Single-Token Bearer REST Mode and S3 SigV4 Mode.
     */
    public function ajax_test_connection() {
        $this->verify_admin_ajax();

        $raw_account   = trim(sanitize_text_field($_POST['account_id'] ?? ''));
        $account_id    = ($raw_account !== '' && $raw_account !== 'undefined') ? $raw_account : self::get_r2_option('account_id', '');
        $raw_access    = trim(sanitize_text_field($_POST['access_key'] ?? ''));
        $access_key    = ($raw_access !== '' && $raw_access !== 'undefined') ? $raw_access : self::get_r2_option('access_key', '');
        $posted_secret = trim(wp_unslash($_POST['secret_key'] ?? ''));

        if (!empty($posted_secret) && $posted_secret !== 'undefined' && strpos($posted_secret, '••••') === false) {
            $secret_key = $posted_secret;
        } else {
            $secret_enc = self::get_r2_option('secret_key', '');
            $secret_key = !empty($secret_enc) ? AstroPress_Encryption::decrypt($secret_enc) : '';
        }

        $raw_cf_token = trim(sanitize_text_field($_POST['cf_api_token'] ?? ''));
        if (!empty($raw_cf_token) && $raw_cf_token !== 'undefined' && strpos($raw_cf_token, '••••') === false) {
            $cf_token = $raw_cf_token;
        } else {
            $cf_token = $this->core->get_cf_api_token();
        }

        $raw_bucket    = trim(sanitize_text_field($_POST['bucket'] ?? ''));
        $bucket        = ($raw_bucket !== '' && $raw_bucket !== 'undefined') ? $raw_bucket : self::get_r2_option('bucket', '');
        $raw_domain    = trim(sanitize_text_field($_POST['custom_domain'] ?? ''));
        $custom_domain = ($raw_domain !== '' && $raw_domain !== 'undefined') ? $raw_domain : self::get_r2_option('custom_domain', '');

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

        $token = trim(sanitize_text_field($_POST['token'] ?? ''));
        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        $custom_domain = trim(sanitize_text_field($_POST['custom_domain'] ?? '')) ?: self::get_r2_option('custom_domain', '');
        $bucket_name   = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: self::get_r2_option('bucket', '');
        $zone_id       = trim(sanitize_text_field($_POST['zone_id'] ?? '')) ?: ($this->core->get_cf_zone_id() ?: $this->resolve_zone_id($token));
        $zone_name     = trim(sanitize_text_field($_POST['zone_name'] ?? ''));
        $account_id    = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: self::get_r2_option('account_id', '');

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
     * AJAX: Auto-detect active custom domain attached to R2 bucket
     */
    public function ajax_detect_r2_custom_domain() {
        $this->verify_admin_ajax();

        $bucket_name = trim(sanitize_text_field($_POST['bucket_name'] ?? '')) ?: self::get_r2_option('bucket', '');
        $account_id  = trim(sanitize_text_field($_POST['account_id'] ?? '')) ?: self::get_r2_option('account_id', '');
        $token       = trim(sanitize_text_field($_POST['token'] ?? ''));

        if (empty($token) || strpos($token, '••••') !== false) {
            $token = $this->core->get_cf_api_token();
        }

        if (empty($bucket_name)) {
            wp_send_json_error(array('message' => esc_html__('Bucket name is required to detect custom domain.', 'astropress')));
        }

        $result = $this->core->cache->get_r2_custom_domains($bucket_name, $account_id, $token);
        if (!empty($result['success'])) {
            if (!empty($result['primary'])) {
                // Update stored custom domain
                update_option('astropress_r2_custom_domain', $result['primary']);
                if (get_option('r2g_custom_domain', null) !== null) {
                    update_option('r2g_custom_domain', $result['primary']);
                }
            }
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
