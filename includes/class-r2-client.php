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
     * Cloudflare Scoped API Token (for Single-Token Bearer REST operations)
     *
     * @var string
     */
    protected $cf_api_token;

    /**
     * Region for Cloudflare R2 (always 'auto')
     */
    const REGION = 'auto';
    const SERVICE = 's3';

    /**
     * Multipart upload threshold in bytes (10 MB).
     * Files >= 10 MB use streamed multipart uploads.
     */
    const MULTIPART_THRESHOLD = 10485760;

    /**
     * Multipart chunk size in bytes (5 MB per part - S3 min part size).
     */
    const MULTIPART_CHUNK_SIZE = 5242880;

    /**
     * Singleton / global accessor
     *
     * @return AstroPress_R2_Client
     */
    public static function instance() {
        return astropress()->get_client();
    }

    /**
     * Constructor
     *
     * Supports both S3 SigV4 credentials (Dual Mode) and Cloudflare Bearer API Tokens (Single-Token Mode).
     *
     * @param string $account_id
     * @param string $access_key
     * @param string $secret_key
     * @param string $bucket
     * @param string $cf_api_token Optional Bearer API Token
     */
    public function __construct($account_id, $access_key, $secret_key, $bucket = '', $cf_api_token = '') {
        $this->account_id   = trim((string) $account_id);
        $this->access_key   = trim((string) $access_key);
        $this->secret_key   = trim((string) $secret_key);
        $this->bucket       = trim((string) $bucket);
        $this->cf_api_token = trim((string) $cf_api_token);

        if (empty($this->cf_api_token) && function_exists('astropress')) {
            $this->cf_api_token = astropress()->get_cf_api_token();
        }
    }

    /**
     * Check if S3 SigV4 credentials are provided
     *
     * @return bool
     */
    public function has_s3_keys() {
        return !empty($this->access_key) && !empty($this->secret_key);
    }

    /**
     * Check if Cloudflare API Token is available
     *
     * @return bool
     */
    public function has_cf_token() {
        return !empty($this->cf_api_token);
    }

    /**
     * Get active authentication mode
     *
     * @return string 's3', 'bearer', or 'none'
     */
    public function get_auth_mode() {
        if ($this->has_s3_keys()) {
            return 's3';
        }
        if ($this->has_cf_token()) {
            return 'bearer';
        }
        return 'none';
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
     * Check if client credentials are fully populated (via S3 keys or Bearer Token)
     *
     * @return bool
     */
    public function is_configured() {
        $has_acc    = !empty($this->account_id);
        $has_bucket = !empty($this->bucket);
        return $has_acc && $has_bucket && ($this->has_s3_keys() || $this->has_cf_token());
    }

    /**
     * Execute Cloudflare Native REST API Request with Bearer Token
     *
     * @param string $method
     * @param string $endpoint e.g. "r2/buckets" or "r2/buckets/{bucket}/objects/{key}"
     * @param mixed  $payload String for binary object payloads or Array for JSON payloads
     * @param array  $headers Additional HTTP headers
     * @return array [ 'success' => bool, 'code' => int, 'body' => string, 'error' => string, 'data' => mixed ]
     */
    public function cf_rest_request($method, $endpoint, $payload = '', $headers = array()) {
        if (empty($this->account_id) || empty($this->cf_api_token)) {
            return array(
                'success' => false,
                'code'    => 0,
                'body'    => '',
                'error'   => 'Cloudflare Account ID and API Token are required for native REST operations.',
                'data'    => null,
            );
        }

        $url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode($this->account_id) . '/' . ltrim($endpoint, '/');
        $method_uc = strtoupper($method);

        $default_headers = array(
            'Authorization' => 'Bearer ' . $this->cf_api_token,
        );

        $is_json_array = is_array($payload);
        if ($is_json_array) {
            $default_headers['Content-Type'] = 'application/json';
            $body = wp_json_encode($payload);
        } else {
            $body = $payload;
        }

        $all_headers = array_merge($default_headers, $headers);

        $args = array(
            'method'    => $method_uc,
            'headers'   => $all_headers,
            'timeout'   => (int) apply_filters('astropress_cf_http_timeout', 60, $method_uc),
            'sslverify' => true,
        );

        if (in_array($method_uc, array('POST', 'PUT', 'PATCH'), true)) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'code'    => 0,
                'body'    => '',
                'error'   => 'HTTP error: ' . $response->get_error_message(),
                'data'    => null,
            );
        }

        $code      = (int) wp_remote_retrieve_response_code($response);
        $raw_body  = (string) wp_remote_retrieve_body($response);
        $json_data = json_decode($raw_body, true);

        $success = ($code >= 200 && $code < 300);
        if (is_array($json_data) && isset($json_data['success'])) {
            $success = $success && !empty($json_data['success']);
        }

        $error = '';
        if (!$success) {
            if (!empty($json_data['errors'][0]['message'])) {
                $error = $json_data['errors'][0]['message'];
            } elseif (!empty($raw_body)) {
                $error = wp_strip_all_tags(substr($raw_body, 0, 500));
            } else {
                $error = "HTTP {$code}";
            }
        }

        return array(
            'success' => $success,
            'code'    => $code,
            'body'    => $raw_body,
            'error'   => $error,
            'data'    => $json_data,
        );
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
        $parsed_url = parse_url($uri);
        $raw_path   = isset($parsed_url['path']) ? $parsed_url['path'] : $uri;
        $raw_query  = isset($parsed_url['query']) ? $parsed_url['query'] : '';
        $is_root    = ($raw_path === '/' || $raw_path === '');

        if ($is_root) {
            if (empty($this->account_id) || empty($this->access_key) || empty($this->secret_key)) {
                return array('success' => false, 'code' => 0, 'body' => '', 'headers' => array(), 'error' => 'Account ID, Access Key, and Secret Key are required.');
            }
        } else {
            if (!$this->is_configured()) {
                return array('success' => false, 'code' => 0, 'body' => '', 'headers' => array(), 'error' => 'R2 credentials or bucket name not fully configured.');
            }
        }

        $host = $this->get_endpoint_host();
        $date_time = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        if ($is_root) {
            $clean_uri = '/';
        } else {
            $segments = explode('/', ltrim(preg_replace('#/+#', '/', $raw_path), '/'));
            $encoded_segments = array_map('rawurlencode', array_map('urldecode', $segments));
            $clean_uri = '/' . implode('/', $encoded_segments);
        }

        $canonical_query_str = '';
        if ($raw_query !== '') {
            $query_parts  = explode('&', $raw_query);
            $query_params = array();
            foreach ($query_parts as $part) {
                if ($part === '') {
                    continue;
                }
                $kv = explode('=', $part, 2);
                $k  = rawurlencode(urldecode($kv[0]));
                $v  = isset($kv[1]) ? rawurlencode(urldecode($kv[1])) : '';
                $query_params[$k] = $v;
            }
            ksort($query_params);
            $pairs = array();
            foreach ($query_params as $k => $v) {
                $pairs[] = "{$k}={$v}";
            }
            $canonical_query_str = implode('&', $pairs);
        }

        $payload_hash = hash('sha256', $payload);
        foreach ($extra_headers as $k => $v) {
            if (strtolower(trim($k)) === 'x-amz-content-sha256') {
                $payload_hash = trim($v);
                break;
            }
        }

        if (in_array(strtoupper($method), array('PUT', 'POST'), true)) {
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
            . $canonical_query_str . "\n"
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
        if ($raw_query !== '') {
            $request_url .= '?' . $raw_query;
        }

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
            'timeout'     => (int) apply_filters('astropress_r2_http_timeout', 120, $method_uc),
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
            return array('success' => false, 'code' => 0, 'body' => '', 'headers' => array(), 'error' => 'HTTP error: ' . $response->get_error_message());
        }

        $http_code        = (int) wp_remote_retrieve_response_code($response);
        $response_body    = (string) wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

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
            'headers' => $response_headers,
            'error'   => $error_message,
        );
    }

    /**
     * List all buckets under this Cloudflare Account
     *
     * Supports both Cloudflare REST API (Bearer token) and S3 SigV4.
     *
     * @return array
     */
    public function list_buckets() {
        if (empty($this->account_id)) {
            return array(
                'success' => false,
                'buckets' => array(),
                'error'   => esc_html__('Cloudflare Account ID is required to discover buckets.', 'astropress'),
            );
        }

        // 1. Try Cloudflare Native REST API first if token is available
        if ($this->has_cf_token()) {
            $cf_res = $this->cf_rest_request('GET', 'r2/buckets');
            if ($cf_res['success'] && !empty($cf_res['data']['result']['buckets']) && is_array($cf_res['data']['result']['buckets'])) {
                $buckets = array();
                foreach ($cf_res['data']['result']['buckets'] as $b) {
                    if (!empty($b['name'])) {
                        $buckets[] = sanitize_text_field($b['name']);
                    }
                }
                return array(
                    'success' => true,
                    'buckets' => array_values(array_unique($buckets)),
                    'message' => sprintf(esc_html__('Discovered %d bucket(s) via Cloudflare REST API.', 'astropress'), count($buckets)),
                    'mode'    => 'bearer',
                );
            }
        }

        // 2. Fall back to S3 SigV4 if S3 keys are available
        if ($this->has_s3_keys()) {
            $res = $this->request('GET', '/');
            if ($res['success']) {
                $buckets = array();
                if (!empty($res['body'])) {
                    if (preg_match_all('#<Name>([^<]+)</Name>#i', $res['body'], $matches)) {
                        $buckets = array_values(array_unique($matches[1]));
                    }
                }
                return array(
                    'success' => true,
                    'buckets' => $buckets,
                    'message' => sprintf(esc_html__('Discovered %d bucket(s) via S3 SigV4 API.', 'astropress'), count($buckets)),
                    'mode'    => 's3',
                );
            }
            return array(
                'success' => false,
                'buckets' => array(),
                'error'   => !empty($res['error']) ? $res['error'] : esc_html__('Failed to list buckets via S3 SigV4.', 'astropress'),
            );
        }

        return array(
            'success' => false,
            'buckets' => array(),
            'error'   => esc_html__('Either a Cloudflare API Token (Workers R2 Storage: Edit) or S3 Access Key ID & Secret Access Key are required to discover buckets.', 'astropress'),
        );
    }

    /**
     * Create a new R2 Bucket under this Cloudflare Account
     *
     * @param string $bucket_name
     * @return array
     */
    public function create_bucket($bucket_name) {
        $bucket_name = sanitize_key(trim((string)$bucket_name));
        if (empty($bucket_name)) {
            return array('success' => false, 'error' => esc_html__('Invalid bucket name. Use lowercase alphanumeric characters and dashes.', 'astropress'));
        }

        if (empty($this->account_id)) {
            return array('success' => false, 'error' => esc_html__('Account ID is required to create a bucket.', 'astropress'));
        }

        // 1. Try Cloudflare Native REST API first
        if ($this->has_cf_token()) {
            $cf_res = $this->cf_rest_request('POST', 'r2/buckets', array('name' => $bucket_name));
            if ($cf_res['success']) {
                $this->bucket = $bucket_name;
                return array(
                    'success' => true,
                    'bucket'  => $bucket_name,
                    'message' => sprintf(esc_html__('Bucket "%s" created successfully on Cloudflare R2.', 'astropress'), $bucket_name),
                    'mode'    => 'bearer',
                );
            }
        }

        // 2. Fall back to S3 SigV4 PUT
        if ($this->has_s3_keys()) {
            $res = $this->request('PUT', '/' . rawurlencode($bucket_name));

            if ($res['code'] === 200 || $res['code'] === 204) {
                $this->bucket = $bucket_name;
                return array(
                    'success' => true,
                    'bucket'  => $bucket_name,
                    'message' => sprintf(esc_html__('Bucket "%s" created successfully on Cloudflare R2.', 'astropress'), $bucket_name),
                    'mode'    => 's3',
                );
            }

            $xml_msg = '';
            if (!empty($res['body']) && strpos($res['body'], '<Message>') !== false) {
                if (preg_match('/<Message>(.*?)<\/Message>/s', $res['body'], $m)) {
                    $xml_msg = trim($m[1]);
                }
            }

            return array(
                'success' => false,
                'code'    => $res['code'],
                'error'   => $xml_msg ?: sprintf(esc_html__('Failed to create bucket on Cloudflare R2 (HTTP %d).', 'astropress'), $res['code']),
            );
        }

        return array(
            'success' => false,
            'error'   => esc_html__('Could not create bucket: Provide a Cloudflare API Token (Workers R2 Storage: Edit) or S3 credentials.', 'astropress'),
        );
    }

    /**
     * Upload a local file to Cloudflare R2
     *
     * Automatically uses S3 SigV4 or Cloudflare Native REST Bearer Token depending on available credentials.
     *
     * @param string $local_path
     * @param string $r2_key
     * @param string|null $mime_type
     * @return array
     */
    public function put_object($local_path, $r2_key, $mime_type = null) {
        if (!file_exists($local_path)) {
            return array('success' => false, 'code' => 0, 'error' => esc_html__('Local file does not exist.', 'astropress'));
        }

        $file_size = (int) @filesize($local_path);

        if (empty($mime_type)) {
            $mime_type = function_exists('wp_check_filetype') ? (wp_check_filetype($local_path)['type'] ?: 'application/octet-stream') : 'application/octet-stream';
        }

        // Seamlessly delegate to streamed multipart upload for large files or memory-constrained environments
        if (($file_size >= self::MULTIPART_THRESHOLD || !self::has_memory_for_payload($file_size)) && $this->has_s3_keys()) {
            $multipart_res = $this->put_large_object_multipart($local_path, $r2_key, $mime_type);
            if (is_wp_error($multipart_res)) {
                return array(
                    'success' => false,
                    'code'    => 0,
                    'error'   => $multipart_res->get_error_message(),
                );
            }
            return $multipart_res;
        }

        if (!self::has_memory_for_payload($file_size)) {
            return array(
                'success' => false,
                'code'    => 0,
                'error'   => sprintf(
                    esc_html__('File is too large to upload with current PHP memory_limit (%s MB file). Increase memory_limit or WP_MAX_MEMORY_LIMIT.', 'astropress'),
                    number_format_i18n($file_size / 1048576, 1)
                ),
            );
        }

        $payload = file_get_contents($local_path);
        if ($payload === false) {
            return array('success' => false, 'code' => 0, 'error' => esc_html__('Could not read local file.', 'astropress'));
        }

        $clean_segments = array_map('rawurlencode', explode('/', ltrim(preg_replace('#/+#', '/', $r2_key), '/')));
        $clean_key = implode('/', $clean_segments);

        // Mode 1: S3 SigV4
        if ($this->has_s3_keys()) {
            $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
            $extra_headers = array(
                'Content-Type'  => $mime_type,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            );
            return $this->request('PUT', $uri, $payload, $extra_headers);
        }

        // Mode 2: Cloudflare Native REST Bearer API
        if ($this->has_cf_token()) {
            $endpoint = "r2/buckets/" . rawurlencode($this->bucket) . "/objects/" . $clean_key;
            $headers  = array(
                'Content-Type'  => $mime_type,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            );
            $res = $this->cf_rest_request('PUT', $endpoint, $payload, $headers);
            return array(
                'success' => $res['success'],
                'code'    => $res['code'],
                'body'    => $res['body'],
                'error'   => $res['error'],
                'mode'    => 'bearer',
            );
        }

        return array(
            'success' => false,
            'code'    => 0,
            'error'   => esc_html__('R2 Storage unconfigured: Need S3 credentials or Cloudflare API Token.', 'astropress'),
        );
    }

    /**
     * Initiate S3 SigV4 Multipart Upload
     *
     * @param string $r2_key
     * @param string $content_type
     * @return string|WP_Error UploadId on success, WP_Error on failure
     */
    private function initiate_multipart_upload($r2_key, $content_type) {
        if (!$this->has_s3_keys() || empty($this->bucket)) {
            return new WP_Error('r2_unconfigured', esc_html__('S3 SigV4 credentials and bucket name are required.', 'astropress'));
        }

        $clean_key = ltrim($r2_key, '/');
        $uri       = "/{$this->bucket}/{$clean_key}?uploads";

        $headers = array(
            'Content-Type'         => !empty($content_type) ? $content_type : 'application/octet-stream',
            'Cache-Control'        => 'public, max-age=31536000, immutable',
            'x-amz-content-sha256' => 'UNSIGNED-PAYLOAD',
        );

        $res = $this->request('POST', $uri, '', $headers);

        if (!$res['success'] || $res['code'] !== 200) {
            return new WP_Error(
                'r2_initiate_multipart_failed',
                sprintf(
                    esc_html__('Failed to initiate multipart upload (HTTP %d): %s', 'astropress'),
                    $res['code'],
                    !empty($res['error']) ? $res['error'] : esc_html__('Unknown error', 'astropress')
                )
            );
        }

        if (preg_match('#<UploadId>([^<]+)</UploadId>#i', $res['body'], $matches)) {
            return trim($matches[1]);
        }

        return new WP_Error(
            'r2_invalid_upload_id',
            esc_html__('Could not parse UploadId from R2 initiate multipart response.', 'astropress')
        );
    }

    /**
     * Upload an individual part in a multipart upload
     *
     * @param string $r2_key
     * @param string $upload_id
     * @param int    $part_number 1-based part index
     * @param string $chunk_bytes Binary bytes of part chunk
     * @return string|WP_Error Sanitized ETag string on success, WP_Error on failure
     */
    private function upload_part($r2_key, $upload_id, $part_number, $chunk_bytes) {
        if (!$this->has_s3_keys() || empty($this->bucket)) {
            return new WP_Error('r2_unconfigured', esc_html__('S3 SigV4 credentials and bucket name are required.', 'astropress'));
        }

        $chunk_hash = hash('sha256', $chunk_bytes);
        $clean_key  = ltrim($r2_key, '/');
        $uri        = "/{$this->bucket}/{$clean_key}?partNumber={$part_number}&uploadId=" . rawurlencode($upload_id);

        $headers = array(
            'Content-Type'         => 'application/octet-stream',
            'x-amz-content-sha256' => $chunk_hash,
        );

        $res = $this->request('PUT', $uri, $chunk_bytes, $headers);

        if (!$res['success'] || $res['code'] !== 200) {
            return new WP_Error(
                'r2_upload_part_failed',
                sprintf(
                    esc_html__('Failed to upload part %d (HTTP %d): %s', 'astropress'),
                    $part_number,
                    $res['code'],
                    !empty($res['error']) ? $res['error'] : esc_html__('Unknown error', 'astropress')
                )
            );
        }

        $etag = '';
        if (!empty($res['headers'])) {
            if (isset($res['headers']['etag'])) {
                $etag = $res['headers']['etag'];
            } elseif (isset($res['headers']['ETag'])) {
                $etag = $res['headers']['ETag'];
            }
        }

        $etag = trim((string) $etag, '"\' ');
        if (empty($etag)) {
            return new WP_Error(
                'r2_missing_part_etag',
                sprintf(esc_html__('Part %d uploaded but ETag response header was missing.', 'astropress'), $part_number)
            );
        }

        return $etag;
    }

    /**
     * Complete a multipart upload by assembling parts
     *
     * @param string $r2_key
     * @param string $upload_id
     * @param array  $parts Array of parts (PartNumber, ETag)
     * @return array|WP_Error Array with final etag on success, WP_Error on failure
     */
    private function complete_multipart_upload($r2_key, $upload_id, array $parts) {
        if (!$this->has_s3_keys() || empty($this->bucket)) {
            return new WP_Error('r2_unconfigured', esc_html__('S3 SigV4 credentials and bucket name are required.', 'astropress'));
        }

        $normalized_parts = array();
        foreach ($parts as $key => $item) {
            if (is_array($item)) {
                $p_num  = isset($item['PartNumber']) ? (int) $item['PartNumber'] : (int) ($item['part_number'] ?? $key);
                $p_etag = isset($item['ETag']) ? (string) $item['ETag'] : (string) ($item['etag'] ?? '');
            } else {
                $p_num  = (int) $key;
                $p_etag = (string) $item;
            }
            $p_etag = trim($p_etag, '"\' ');
            if ($p_num > 0 && !empty($p_etag)) {
                $normalized_parts[$p_num] = $p_etag;
            }
        }

        ksort($normalized_parts, SORT_NUMERIC);

        $xml = "<CompleteMultipartUpload>\n";
        foreach ($normalized_parts as $p_num => $p_etag) {
            $xml .= "  <Part><PartNumber>{$p_num}</PartNumber><ETag>\"" . htmlspecialchars($p_etag, ENT_COMPAT, 'UTF-8') . "\"</ETag></Part>\n";
        }
        $xml .= "</CompleteMultipartUpload>";

        $clean_key = ltrim($r2_key, '/');
        $uri       = "/{$this->bucket}/{$clean_key}?uploadId=" . rawurlencode($upload_id);

        $headers = array(
            'Content-Type' => 'application/xml',
        );

        $res = $this->request('POST', $uri, $xml, $headers);

        if (!$res['success'] || $res['code'] !== 200) {
            return new WP_Error(
                'r2_complete_multipart_failed',
                sprintf(
                    esc_html__('Failed to complete multipart upload (HTTP %d): %s', 'astropress'),
                    $res['code'],
                    !empty($res['error']) ? $res['error'] : esc_html__('Unknown error', 'astropress')
                )
            );
        }

        if (strpos($res['body'], '<Error>') !== false) {
            $err_msg = 'S3 CompleteMultipartUpload error';
            if (preg_match('#<Message>([^<]+)</Message>#i', $res['body'], $m)) {
                $err_msg = trim($m[1]);
            }
            return new WP_Error('r2_complete_multipart_xml_error', $err_msg);
        }

        $final_etag = '';
        if (preg_match('#<ETag>([^<]+)</ETag>#i', $res['body'], $m)) {
            $final_etag = trim($m[1], '"\' ');
        }

        return array(
            'success' => true,
            'etag'    => $final_etag,
            'body'    => $res['body'],
        );
    }

    /**
     * Abort an uncompleted multipart upload to purge orphaned chunks
     *
     * @param string $r2_key
     * @param string $upload_id
     * @return bool
     */
    private function abort_multipart_upload($r2_key, $upload_id) {
        if (!$this->has_s3_keys() || empty($this->bucket)) {
            return false;
        }

        $clean_key = ltrim($r2_key, '/');
        $uri       = "/{$this->bucket}/{$clean_key}?uploadId=" . rawurlencode($upload_id);

        $res = $this->request('DELETE', $uri);

        return ($res['success'] || $res['code'] === 204 || $res['code'] === 200);
    }

    /**
     * Upload a large file to Cloudflare R2 using pure PHP streamed multipart chunks
     *
     * Memory-safe: reads and transmits files in 5 MB chunks without loading full payloads into memory.
     *
     * @param string      $file_path    Absolute path to local file
     * @param string      $r2_key       Target key on R2
     * @param string|null $content_type MIME type
     * @return array|WP_Error Array with success/url/etag/parts_count on success, WP_Error on failure
     */
    public function put_large_object_multipart($file_path, $r2_key, $content_type = null) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return new WP_Error('r2_file_not_found', esc_html__('Local file does not exist or is not readable.', 'astropress'));
        }

        if (!$this->has_s3_keys() || empty($this->bucket)) {
            return new WP_Error('r2_unconfigured', esc_html__('S3 SigV4 credentials and bucket name are required for multipart uploads.', 'astropress'));
        }

        if (empty($content_type)) {
            if (function_exists('wp_check_filetype')) {
                $file_type    = wp_check_filetype($file_path);
                $content_type = !empty($file_type['type']) ? $file_type['type'] : 'application/octet-stream';
            } else {
                $content_type = 'application/octet-stream';
            }
        }

        $file_handle = @fopen($file_path, 'rb');
        if (!$file_handle) {
            return new WP_Error('r2_file_open_failed', esc_html__('Could not open file handle for streaming.', 'astropress'));
        }

        $upload_id = $this->initiate_multipart_upload($r2_key, $content_type);
        if (is_wp_error($upload_id)) {
            @fclose($file_handle);
            return $upload_id;
        }

        $parts       = array();
        $part_number = 1;

        while (!feof($file_handle)) {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $chunk_bytes = fread($file_handle, self::MULTIPART_CHUNK_SIZE);
            if ($chunk_bytes === false) {
                $this->abort_multipart_upload($r2_key, $upload_id);
                @fclose($file_handle);
                return new WP_Error(
                    'r2_read_chunk_failed',
                    sprintf(esc_html__('Failed reading file chunk at part %d.', 'astropress'), $part_number)
                );
            }

            if (strlen($chunk_bytes) === 0) {
                break;
            }

            $etag = $this->upload_part($r2_key, $upload_id, $part_number, $chunk_bytes);
            unset($chunk_bytes);

            if (is_wp_error($etag)) {
                $this->abort_multipart_upload($r2_key, $upload_id);
                @fclose($file_handle);
                return $etag;
            }

            $parts[] = array(
                'PartNumber' => $part_number,
                'ETag'       => $etag,
            );

            $part_number++;
        }

        @fclose($file_handle);

        // S3 requires at least one part even for empty files
        if (empty($parts)) {
            $etag = $this->upload_part($r2_key, $upload_id, 1, '');
            if (is_wp_error($etag)) {
                $this->abort_multipart_upload($r2_key, $upload_id);
                return $etag;
            }
            $parts[] = array(
                'PartNumber' => 1,
                'ETag'       => $etag,
            );
        }

        $complete_res = $this->complete_multipart_upload($r2_key, $upload_id, $parts);
        if (is_wp_error($complete_res)) {
            $this->abort_multipart_upload($r2_key, $upload_id);
            return $complete_res;
        }

        $final_etag = !empty($complete_res['etag']) ? $complete_res['etag'] : '';

        $custom_domain = function_exists('get_option') ? get_option('astropress_r2_custom_domain', get_option('r2g_custom_domain', '')) : '';
        if (!empty($custom_domain)) {
            $cdn_base = rtrim($custom_domain, '/');
            if (!preg_match('#^https?://#i', $cdn_base)) {
                $cdn_base = 'https://' . $cdn_base;
            }
            $url = $cdn_base . '/' . ltrim($r2_key, '/');
        } else {
            $url = "https://{$this->get_endpoint_host()}/{$this->bucket}/" . ltrim($r2_key, '/');
        }

        return array(
            'success'     => true,
            'url'         => $url,
            'etag'        => $final_etag,
            'parts_count' => count($parts),
            'code'        => 200,
        );
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
        if ($this->has_s3_keys()) {
            $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
            return $this->request('DELETE', $uri);
        }

        if ($this->has_cf_token()) {
            $clean_segments = array_map('rawurlencode', explode('/', ltrim(preg_replace('#/+#', '/', $r2_key), '/')));
            $clean_key = implode('/', $clean_segments);
            $endpoint  = "r2/buckets/" . rawurlencode($this->bucket) . "/objects/" . $clean_key;
            $res = $this->cf_rest_request('DELETE', $endpoint);
            return array(
                'success' => $res['success'],
                'code'    => $res['code'],
                'body'    => $res['body'],
                'error'   => $res['error'],
            );
        }

        return array('success' => false, 'code' => 0, 'error' => esc_html__('No credentials available to delete object.', 'astropress'));
    }

    public function object_exists($r2_key) {
        if ($this->has_s3_keys()) {
            $uri = "/{$this->bucket}/" . ltrim($r2_key, '/');
            $res = $this->request('HEAD', $uri);
            return $res['success'];
        }

        if ($this->has_cf_token()) {
            $clean_segments = array_map('rawurlencode', explode('/', ltrim(preg_replace('#/+#', '/', $r2_key), '/')));
            $clean_key = implode('/', $clean_segments);
            $endpoint  = "r2/buckets/" . rawurlencode($this->bucket) . "/objects/" . $clean_key;
            $res = $this->cf_rest_request('HEAD', $endpoint);
            return ($res['code'] === 200);
        }

        return false;
    }

    public function test_connection($custom_domain = '') {
        if (empty($this->bucket)) {
            return array(
                'success' => false,
                'message' => esc_html__('Bucket name is required to run test connection. Please select or enter a bucket.', 'astropress'),
            );
        }

        $test_key = '.astropress-test-verification-' . wp_generate_password(8, false) . '.json';
        $test_payload = wp_json_encode(array(
            'status'     => 'ok',
            'plugin'     => 'AstroPress',
            'timestamp'  => current_time('mysql'),
            'bucket'     => $this->bucket,
            'auth_mode'  => $this->get_auth_mode(),
        ));

        // 1. Create temporary file and test upload
        $tmp_file = wp_tempnam('astropress_test_');
        if (!$tmp_file) {
            return array('success' => false, 'message' => esc_html__('Could not create temporary test file.', 'astropress'));
        }
        file_put_contents($tmp_file, $test_payload);

        $t1 = microtime(true);
        $put_res = $this->put_object($tmp_file, $test_key, 'application/json');
        $put_time = round((microtime(true) - $t1) * 1000);
        @unlink($tmp_file);

        if (!$put_res['success']) {
            return array(
                'success'    => false,
                'stage'      => 'r2_upload',
                'latency_ms' => $put_time,
                'message'    => sprintf(
                    esc_html__("Failed to upload test object to R2 bucket '%s'. Check Account ID, credentials, or API Token permissions. (%s)", 'astropress'),
                    $this->bucket,
                    $put_res['error']
                ),
            );
        }

        // 2. Test CDN Fetch (if custom domain provided)
        $cdn_verified = false;
        $cdn_message  = '';
        if (!empty($custom_domain)) {
            $cdn_host = preg_replace('#^https?://#', '', rtrim($custom_domain, '/'));
            $cdn_url  = 'https://' . $cdn_host . '/' . $test_key;
            $cdn_response = wp_remote_get($cdn_url, array(
                'timeout'   => 8,
                'sslverify' => true,
                'headers'   => array('User-Agent' => 'AstroPress-Verifier/1.0'),
            ));

            if (!is_wp_error($cdn_response) && wp_remote_retrieve_response_code($cdn_response) === 200) {
                $cdn_body = wp_remote_retrieve_body($cdn_response);
                if (strpos($cdn_body, 'AstroPress') !== false || strpos($cdn_body, 'ok') !== false) {
                    $cdn_verified = true;
                    $cdn_message  = sprintf(esc_html__("Custom CDN Domain (%s) is live, verified, and serving R2 objects!", 'astropress'), $cdn_host);
                } else {
                    $cdn_message = sprintf(esc_html__("CDN URL responded with 200, but content did not match. Ensure custom domain points to bucket '%s'.", 'astropress'), $this->bucket);
                }
            } else {
                $err = is_wp_error($cdn_response) ? $cdn_response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($cdn_response);
                $cdn_message = sprintf(esc_html__("R2 upload succeeded, but Custom CDN URL failed to fetch test object: %s. Check DNS and Cloudflare custom domain routing.", 'astropress'), $err);
            }
        }

        // 3. Clean up test object
        $this->delete_object($test_key);

        $mode_label = ($this->get_auth_mode() === 'bearer') ? esc_html__('Single-Token Bearer REST Mode', 'astropress') : esc_html__('S3 SigV4 Mode', 'astropress');

        return array(
            'success'      => true,
            'latency_ms'   => $put_time,
            'bucket'       => $this->bucket,
            'auth_mode'    => $this->get_auth_mode(),
            'cdn_verified' => $cdn_verified,
            'cdn_message'  => $cdn_message,
            'message'      => sprintf(
                esc_html__("Successfully connected to Cloudflare R2 bucket '%s' via %s (Latency: %dms).", 'astropress'),
                $this->bucket,
                $mode_label,
                $put_time
            ) . ($cdn_verified ? " {$cdn_message}" : ""),
        );
    }
}
