<?php
/**
 * Security & Subdomain Protection Shield
 *
 * Prevents search engine indexing of the backend WordPress host,
 * blocks user enumeration scans, rewrites post/page permalinks to the frontend Astro domain,
 * and 301-redirects public frontend traffic away from the headless WordPress origin.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Security {
    /**
     * Core orchestrator reference
     *
     * @var AstroPress
     */
    protected $core;

    /**
     * Prevent cloning of instance
     */
    private function __clone() {}

    /**
     * Constructor
     *
     * @param AstroPress $core
     */
    public function __construct(AstroPress $core) {
        $this->core = $core;

        // Preview & Permalink rewrites
        add_filter('post_link', array($this, 'filter_permalink'), 10, 2);
        add_filter('page_link', array($this, 'filter_permalink'), 10, 2);
        add_filter('preview_post_link', array($this, 'filter_preview_link'), 10, 2);

        // Subdomain & Backend Security Protection
        add_action('init', array($this, 'block_author_enumeration'));
        add_action('template_redirect', array($this, 'handle_frontend_redirect'), 1);
        add_action('send_headers', array($this, 'send_backend_security_headers'));

        // Harden XML-RPC by default to prevent brute-force amplification and DDoS pingbacks
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', '__return_empty_array');
    }

    /**
     * Block author query string enumeration (?author=N) across the entire site
     */
    public function block_author_enumeration() {
        if (!is_admin() && isset($_GET['author'])) {
            wp_die(
                esc_html__('Author enumeration is disabled on headless backend.', 'astropress'),
                esc_html__('Forbidden', 'astropress'),
                array('response' => 403)
            );
        }
    }

    /**
     * SSRF Guard: Validate that an external URL is safe and does not target internal services
     *
     * @param string $url
     * @return bool
     */
    public static function is_safe_external_url($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }

        if (!wp_http_validate_url($url)) {
            return false;
        }

        $parsed = parse_url($url);
        if (empty($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), array('http', 'https'), true)) {
            return false;
        }

        $host = strtolower($parsed['host'] ?? '');
        if (empty($host)) {
            return false;
        }

        // Allow localhost / local private IPs only if explicitly filtered or in local environment
        $allow_local = apply_filters('astropress_allow_local_http', (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local'));
        if ($allow_local) {
            return true;
        }

        // Block localhost and loopback domains
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return false;
        }

        // Resolve hostname to IP to protect against DNS rebinding and internal IP targeting
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false; // Could not resolve hostname
        }

        // Check if IP is in private or reserved ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    /**
     * Filter permalinks so "View Post" links point directly to Headless frontend
     *
     * @param string $permalink
     * @param WP_Post $post
     * @return string
     */
    public function filter_permalink($permalink, $post) {
        if (!$this->core->should_rewrite_links()) {
            return $permalink;
        }
        $post = get_post($post);
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return $permalink;
        }
        $frontend_url = $this->core->get_frontend_url();
        if (empty($frontend_url)) {
            return $permalink;
        }
        $path = '';
        if ($post->post_type === 'page') {
            $uri = get_page_uri($post);
            $path = !empty($uri) ? $uri : $post->post_name;
        } else {
            $path = $post->post_name;
        }
        if (empty($path)) {
            return $permalink;
        }
        return rtrim($frontend_url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Filter preview links so preview buttons point directly to Headless frontend
     *
     * @param string $preview_link
     * @param WP_Post $post
     * @return string
     */
    public function filter_preview_link($preview_link, $post) {
        if (!$this->core->should_rewrite_links()) {
            return $preview_link;
        }
        $post = get_post($post);
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            return $preview_link;
        }
        $frontend_url = $this->core->get_frontend_url();
        if (empty($frontend_url)) {
            return $preview_link;
        }
        $path = '';
        if ($post->post_type === 'page') {
            $uri = get_page_uri($post);
            $path = !empty($uri) ? $uri : $post->post_name;
        } else {
            $path = $post->post_name;
        }
        if (empty($path)) {
            return $preview_link;
        }
        $query = parse_url($preview_link, PHP_URL_QUERY);
        $url = rtrim($frontend_url, '/') . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url .= '?' . $query;
        }
        return $url;
    }

    /**
     * Redirect public frontend visitors from backend origin to production Astro frontend
     * Preserves WordPress Admin, REST API, Cron, and preview functions.
     */
    public function handle_frontend_redirect() {
        if (!$this->core->should_shield_subdomain()) {
            return;
        }
        if (is_admin()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (is_preview()) {
            return;
        }
        if (isset($_GET['preview']) && $_GET['preview'] === 'true') {
            return;
        }

        // Allow XML sitemaps and XSL stylesheets to render natively without frontend redirection
        $req_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $req_path = parse_url($req_uri, PHP_URL_PATH) ?: '';
        if (
            preg_match('/(sitemap.*\.xml|.*-sitemap.*\.xml|.*\.xsl)$/i', $req_path) ||
            isset($_GET['sitemap']) ||
            isset($_GET['sitemap_index']) ||
            isset($_GET['sitemap_n'])
        ) {
            return;
        }

        $frontend_url = $this->core->get_frontend_url();
        if (empty($frontend_url)) {
            return;
        }

        $home_host   = parse_url(home_url(), PHP_URL_HOST);
        $target_host = parse_url($frontend_url, PHP_URL_HOST);
        if ($home_host && $target_host && strtolower($home_host) === strtolower($target_host)) {
            return; // Prevent edge redirect loop if frontend equals backend domain
        }

        $safe_path = wp_sanitize_redirect(wp_unslash($req_uri));
        $target_url = esc_url_raw(rtrim($frontend_url, '/') . '/' . ltrim($safe_path, '/'));

        // 301 Permanent redirect to canonical frontend
        wp_redirect($target_url, 301);
        exit;
    }

    /**
     * Send noindex, nofollow and security headers on WordPress backend responses
     */
    public function send_backend_security_headers() {
        if (headers_sent()) {
            return;
        }

        // Baseline defense headers against MIME confusion and clickjacking
        header('X-Content-Type-Options: nosniff', true);
        header('X-Frame-Options: SAMEORIGIN', true);

        if ($this->core->should_noindex_backend()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true);
        }
    }
}
