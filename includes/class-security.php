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
        add_action('template_redirect', array($this, 'handle_frontend_redirect'), 1);
        add_action('send_headers', array($this, 'send_backend_security_headers'));
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
        return rtrim($frontend_url, '/') . '/' . ltrim($post->post_name, '/');
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
        return rtrim($frontend_url, '/') . '/' . ltrim($post->post_name, '/');
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

        // Block author enumeration via ?author=1
        if (isset($_GET['author'])) {
            wp_die('Author enumeration is disabled on Headless backend.', 'Forbidden', array('response' => 403));
        }

        $frontend_url = $this->core->get_frontend_url();
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
        if (!$this->core->should_noindex_backend()) {
            return;
        }

        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
            header('X-Content-Type-Options: nosniff', true);
        }
    }
}

// Register backwards compatibility alias
if (!class_exists('TopNepali_Headless_Security')) {
    class_alias('AstroPress_Security', 'TopNepali_Headless_Security');
}
