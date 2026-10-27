<?php
/**
 * Lightweight Pure PHP Cloudflare R2 S3-Compatible Client (SigV4)
 * Zero multi-MB AWS SDK dependencies.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_R2_Client {
    /**
     * Cloudflare Account ID
     *
     * @var string
     */
    protected $account_id;

    /**
     * R2 Access Key ID
     *
     * @var string
     */
    protected $access_key;

    /**
     * R2 Secret Access Key
     *
     * @var string
     */
    protected $secret_key;

    /**
     * R2 Bucket Name
     *
     * @var string
     */
    protected $bucket;

    /**
     * Region for Cloudflare R2 (always 'auto')
     */
    const REGION = 'auto';
    const SERVICE = 's3';

    /**
     * Constructor
     *
     * @param string $account_id
     * @param string $access_key
     * @param string $secret_key
     * @param string $bucket
     */
    public function __construct($account_id, $access_key, $secret_key, $bucket = '') {
        $this->account_id = trim((string) $account_id);
        $this->access_key = trim((string) $access_key);
        $this->secret_key = trim((string) $secret_key);
        $this->bucket     = trim((string) $bucket);
    }

    /**
     * Get R2 Endpoint Host
     *
     * @return string
     */
    public function get_endpoint_host() {
        return "{$this->account_id}.r2.cloudflarestorage.com";
    }

    /**
     * Check if client credentials are fully populated
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->account_id) && !empty($this->access_key) && !empty($this->secret_key) && !empty($this->bucket);
    }

    /**
     * Generate AWS Signature Version 4 Headers and make HTTP request
     *
     * @param string $method GET, PUT, DELETE, HEAD
     * @param string $uri e.g. "/bucket/path/to/file.webp" or "/"
     * @param string $payload Body content or empty
     * @param array $extra_headers Additional headers
     * @return array [ 'success' => bool, 'code' => int, 'body' => string, 'error' => string ]
     */
    public function request($method, $uri, $payload = '', $extra_headers = array()) {
        $is_root = ($uri === '/' || $uri === '');

        if ($is_root) {
            if (empty($this->account_id) || empty($this->access_key) || empty($this->secret_key)) {
                return array('success' => false, 'code' => 0, 'body' => '', 'error' => 'Account ID, Access Key, and Secret Key are required.');
            }
        } else {
            if (!$this->is_configured()) {
                return array('success' => false, 'code' => 0, 'body' => '', 'error' => 'R2 credentials or bucket name not fully configured.');
            }
        }

        $host = $this->get_endpoint_host();
        $date_time = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        if ($is_root) {
            $clean_uri = '/';
        } else {
            $segments = explode('/', ltrim(preg_replace('#/+#', '/', $uri), '/'));
            $encoded_segments = array_map('rawurlencode', $segments);
            $clean_uri = '/' . implode('/', $encoded_segments);
        }

        $payload_hash = hash('sha256', $payload);

        if (in_array(strtoupper($method), array('PUT', 'POST'))) {
            $has_content_type = false;
            foreach ($extra_headers as $k => $v) {
                if (strtolower($k) === 'content-type') {
                    $has_content_type = true;
                    break;
                }
            }
            if (!$has_content_type) {
                $extra_headers['Content-Type'] = 'application/octet-stream';
            }
        }

        $canonical_headers_arr = array(
            'host'                 => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'           => $date_time,
        );

        foreach ($extra_headers as $k => $v) {
            $canonical_headers_arr[strtolower(trim($k))] = trim($v);
        }
        ksort($canonical_headers_arr);

        $canonical_headers_str = '';
        $signed_headers_arr = array();
        foreach ($canonical_headers_arr as $k => $v) {
            $canonical_headers_str .= "{$k}:{$v}\n";
            $signed_headers_arr[] = $k;
        }
        $signed_headers_str = implode(';', $signed_headers_arr);

        $canonical_request = strtoupper($method) . "\n"
            . $clean_uri . "\n"
            . "" . "\n"
            . $canonical_headers_str . "\n"
            . $signed_headers_str . "\n"
            . $payload_hash;

        $credential_scope = "{$date}/" . self::REGION . "/" . self::SERVICE . "/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n"
            . $date_time . "\n"
            . $credential_scope . "\n"
            . hash('sha256', $canonical_request);

        $k_date    = hash_hmac('sha256', $date, 'AWS4' . $this->secret_key, true);
        $k_region  = hash_hmac('sha256', self::REGION, $k_date, true);
        $k_service = hash_hmac('sha256', self::SERVICE, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

        $auth_header = "AWS4-HMAC-SHA256 Credential={$this->access_key}/{$credential_scope}, SignedHeaders={$signed_headers_str}, Signature={$signature}";

        $request_url = "https://{$host}" . $clean_uri;
        $method_uc = strtoupper($method);
        $http_headers = array(
            'x-amz-date'           => $date_time,
            'x-amz-content-sha256' => $payload_hash,
            'Authorization'        => $auth_header,
            'Expect'               => '',
        );

        foreach ($extra_headers as $k => $v) {
            $http_headers[$k] = $v;
        }

        $args = array(
            'method'      => $method_uc,
            'headers'     => $http_headers,
            'timeout'     => (int) apply_filters('astropress_r2_http_timeout', 60, $method_uc),
            'redirection' => 0,
            'sslverify'   => true,
            'httpversion' => '1.1',
            'decompress'  => false,
        );

        if (in_array($method_uc, array('PUT', 'POST'), true)) {
            $args['body'] = $payload;
        }

        $response = wp_remote_request($request_url, $args);

        if (is_wp_error($response)) {
            return array('success' => false, 'code' => 0, 'body' => '', 'error' => 'HTTP error: ' . $response->get_error_message());
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);

        $success = ($http_code >= 200 && $http_code < 300);

        $error_message = '';
        if (!$success) {
            if (!empty($response_body)) {
                if (preg_match('#<Message>(.+?)</Message>#', $response_body, $matches)) {
                    $error_message = $matches[1];
                } elseif (preg_match('#<Code>(.+?)</Code>#', $response_body, $matches)) {
                    $error_message = $matches[1];
                } else {
                    $error_message = wp_strip_all_tags($response_body);
                }
            } else {
                $error_message = "HTTP {$http_code}";
            }
        }

        return array(
            'success' => $success,
            'code'    => $http_code,
            'body'    => $response_body,
            'error'   => $error_message,
        );
    }

    /**
     * List all buckets under this Cloudflare Account
     */
    public function list_buckets() {
        if (empty($this->account_id) || empty($this->access_key) || empty($this->secret_key)) {
            return array(
                'success' => false,
                'buckets' => array(),
                'error'   => 'Account ID, Access Key ID, and Secret Access Key are required to discover buckets.',
            );
        }

        $res = $this->request('GET', '/');

        if (!$res['success']) {
            return array(
                'success' => false,
                'buckets' => array(),
                'error'   => !empty($res['error']) ? $res['error'] : 'Failed to list buckets. Verify your Account ID and credentials.',
            );
        }

        $buckets = array();
        if (!empty($res['body'])) {
            if (preg_match_all('#<Name>([^<]+)</Name>#i', $res['body'], $matches)) {
                $buckets = array_values(array_unique($matches[1]));
            }
        }

        return array(
            'success' => true,
            'buckets' => $buckets,
            'message' => sprintf('Discovered %d bucket(s) under this account.', count($buckets)),
        );
    }

    /**
     * Upload a local file to Cloudflare R2
     */
    public function put_object($local_path, $r2_key, $mime_type = null) {
        if (!file_exists($local_path)) {
            return array('success' => false, 'code' => 0, 'error' => 'Local file does not exist.');
        }

        $file_size = (int) @filesize($local_path);
        if (!self::has_memory_for_payload($file_size)) {
            return array(
                'success' => false,
                'code'    => 0,
                'error'   => sprintf(
                    'File is too large to upload with current PHP memory_limit (%s MB file). Increase memory_limit or WP_MAX_MEMORY_LIMIT.',
                    number_format_i18n($file_size / 1048576, 1)
                ),
            );
        }

        $payload = file_get_contents($local_path);
        if ($payload === false) {
            return array('success' => false, 'code' => 0, 'error' => 'Could not read local file.');
        }

        if (empty($mime_type)) {
            $mime_type = wp_check_filetype($local_path)['type'] ?: 'application/octet-stream';
        }

        $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
        $extra_headers = array(
            'Content-Type'  => $mime_type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        );

        return $this->request('PUT', $uri, $payload, $extra_headers);
    }

    protected static function has_memory_for_payload($bytes) {
        if ($bytes <= 0) {
            return true;
        }

        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }

        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || (int) $limit === -1) {
            return true;
        }

        $limit_bytes = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes($limit) : (int) $limit;
        if ($limit_bytes <= 0) {
            return true;
        }

        $available = $limit_bytes - memory_get_usage(true);
        $required = (int) ceil($bytes * 2.5) + 8 * 1048576;

        return $available > $required;
    }

    public function delete_object($r2_key) {
        $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
        return $this->request('DELETE', $uri);
    }

    public function object_exists($r2_key) {
        $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
        $res = $this->request('HEAD', $uri);
        return $res['success'];
    }

    public function test_connection($custom_domain = '') {
        if (empty($this->bucket)) {
            return array(
                'success' => false,
                'message' => 'Bucket name is required to run test connection. Please select or enter a bucket.',
            );
        }

        $test_key = '.astropress-test-verification-' . wp_generate_password(8, false) . '.json';
        $test_payload = wp_json_encode(array(
            'status'     => 'ok',
            'plugin'     => 'AstroPress',
            'timestamp'  => current_time('mysql'),
            'bucket'     => $this->bucket,
        ));

        $uri = "/{$this->bucket}/{$test_key}";

        // 1. Test PUT
        $t1 = microtime(true);
        $put_res = $this->request('PUT', $uri, $test_payload, array('Content-Type' => 'application/json'));
        $put_time = round((microtime(true) - $t1) * 1000);

        if (!$put_res['success']) {
            return array(
                'success'    => false,
                'stage'      => 'r2_upload',
                'latency_ms' => $put_time,
                'message'    => "Failed to upload test object to R2 bucket '{$this->bucket}'. Check Account ID, Access Key, Secret Key, and Bucket name. ({$put_res['error']})",
            );
        }

        // 2. Test CDN Fetch (if custom domain provided)
        $cdn_verified = false;
        $cdn_message = '';
        if (!empty($custom_domain)) {
            $cdn_url = rtrim($custom_domain, '/') . '/' . $test_key;
            $cdn_response = wp_remote_get($cdn_url, array(
                'timeout'   => 8,
                'sslverify' => true,
                'headers'   => array('User-Agent' => 'AstroPress-Verifier/1.0'),
            ));

            if (!is_wp_error($cdn_response) && wp_remote_retrieve_response_code($cdn_response) === 200) {
                $cdn_body = wp_remote_retrieve_body($cdn_response);
                if (strpos($cdn_body, 'AstroPress') !== false || strpos($cdn_body, 'ok') !== false) {
                    $cdn_verified = true;
                    $cdn_message = "Custom CDN Domain ({$custom_domain}) is live, verified, and serving R2 objects!";
                } else {
                    $cdn_message = "CDN URL responded with 200, but file content did not match. Ensure custom domain points to bucket '{$this->bucket}'.";
                }
            } else {
                $err = is_wp_error($cdn_response) ? $cdn_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($cdn_response);
                $cdn_message = "R2 upload succeeded, but Custom CDN URL failed to fetch test object: {$err}. Check DNS and Cloudflare custom domain routing.";
            }
        }

        // 3. Clean up test object
        $this->request('DELETE', $uri);

        return array(
            'success'      => true,
            'latency_ms'   => $put_time,
            'bucket'       => $this->bucket,
            'cdn_verified' => $cdn_verified,
            'cdn_message'  => $cdn_message,
            'message'      => "Successfully connected to Cloudflare R2 bucket '{$this->bucket}' (Latency: {$put_time}ms)." . ($cdn_verified ? " {$cdn_message}" : ""),
        );
    }
}
