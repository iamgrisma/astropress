<?php
/**
 * SEO Bridge for Rank Math, Yoast SEO & Structured Schema
 *
 * Rewrites canonical URLs, OpenGraph, Twitter Cards, XML Sitemaps,
 * JSON-LD schema, and rendered head tags directly to the Astro frontend domain.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_SEO {
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

        // Rank Math URL & Schema Sanitizers
        add_filter('rank_math/frontend/canonical', array($this, 'filter_rank_math_url'));
        add_filter('rank_math/opengraph/url', array($this, 'filter_rank_math_url'));
        add_filter('rank_math/json_ld', array($this, 'filter_rank_math_json_ld'), 99, 1);
        add_filter('rank_math/sitemap/url', array($this, 'filter_rank_math_sitemap_url'), 99, 2);
        add_filter('rank_math/sitemap/entry', array($this, 'filter_rank_math_sitemap_entry'), 99, 3);
        add_filter('rank_math/sitemap/xml_text', array($this, 'filter_rank_math_sitemap_xml_text'), 99, 1);

        // Yoast SEO Filters (when Yoast is active)
        add_filter('wpseo_canonical', array($this, 'filter_rank_math_url'));
        add_filter('wpseo_opengraph_url', array($this, 'filter_rank_math_url'));
        add_filter('wpseo_schema_graph', array($this, 'filter_rank_math_json_ld'), 99, 1);
        add_filter('wpseo_sitemap_entry', array($this, 'filter_rank_math_sitemap_entry'), 99, 3);

        // SEOPress Filters
        add_filter('seopress_titles_canonical', array($this, 'filter_rank_math_url'));
        add_filter('seopress_social_fb_url', array($this, 'filter_rank_math_url'));
        add_filter('seopress_sitemaps_url', array($this, 'filter_rank_math_url'));

        // All in One SEO (AIOSEO) Filters
        add_filter('aioseo_canonical_url', array($this, 'filter_rank_math_url'));
        add_filter('aioseo_facebook_tags', array($this, 'filter_rank_math_json_ld'));
        add_filter('aioseo_sitemap_post', array($this, 'filter_rank_math_sitemap_entry'), 99, 2);

        // Cache invalidation hooks for rendered head meta across engines
        add_action('save_post', array($this, 'invalidate_post_seo_cache'), 10, 2);
        add_action('deleted_post', array($this, 'invalidate_post_seo_cache'), 10, 1);
        add_action('clean_post_cache', array($this, 'invalidate_post_seo_cache'), 10, 1);

        // Dynamic Redirection Sync hook
        add_action('init', array($this, 'sync_default_redirections'));
    }

    /**
     * Get array of origin WordPress host variations to replace
     * Dynamically constructed from site options with zero hardcoded domains.
     *
     * @return array
     */
    public function get_origin_hosts() {
        $hosts = array(
            home_url(),
            site_url(),
            untrailingslashit(home_url()),
            untrailingslashit(site_url()),
        );

        $custom_origin = get_option('astropress_backend_url', get_option('topnepali_backend_url', ''));
        if (!empty($custom_origin)) {
            $hosts[] = $custom_origin;
            $hosts[] = untrailingslashit($custom_origin);
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * Filter canonical and OpenGraph URLs to production frontend
     *
     * @param string $url
     * @return string
     */
    public function filter_rank_math_url($url) {
        if (empty($url) || !is_string($url)) {
            return $url;
        }
        return $this->rewrite_origin_urls($url);
    }

    /**
     * Rewrite origin WordPress URLs to the production frontend without trailing-slash truncation bugs.
     *
     * @param string $value
     * @param string|null $frontend_url
     * @return string
     */
    public function rewrite_origin_urls($value, $frontend_url = null) {
        if (empty($value) || !is_string($value)) {
            return (string) $value;
        }
        $target = untrailingslashit((string) ($frontend_url !== null ? $frontend_url : $this->core->get_frontend_url()));
        if ($target === '') {
            return $value;
        }
        $origins = array();
        foreach ($this->get_origin_hosts() as $origin) {
            $clean = untrailingslashit((string) $origin);
            if ($clean !== '') {
                $origins[$clean] = true;
            }
        }
        if (empty($origins)) {
            return $value;
        }
        // Sort longest-first so subpath origins match before bare root origins.
        $origin_list = array_keys($origins);
        usort($origin_list, static function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        return str_replace($origin_list, $target, $value);
    }

    /**
     * Filter sitemap URL
     *
     * @param string $url
     * @param string $type
     * @return string
     */
    public function filter_rank_math_sitemap_url($url, $type = '') {
        return $this->filter_rank_math_url($url);
    }

    /**
     * Filter sitemap single entry
     *
     * @param array $entry
     * @param string $type
     * @param mixed $item
     * @return array
     */
    public function filter_rank_math_sitemap_entry($entry, $type = '', $item = null) {
        if (!is_array($entry)) {
            return $entry;
        }
        if (!empty($entry['loc'])) {
            $entry['loc'] = $this->rewrite_origin_urls($entry['loc']);
        }
        return $entry;
    }

    /**
     * Filter sitemap XML text
     *
     * @param string $xml
     * @return string
     */
    public function filter_rank_math_sitemap_xml_text($xml) {
        if (empty($xml) || !is_string($xml)) {
            return $xml;
        }
        return $this->rewrite_origin_urls($xml);
    }

    /**
     * Filter JSON-LD structured data to ensure production frontend URLs
     *
     * @param array $data
     * @return array
     */
    public function filter_rank_math_json_ld($data) {
        if (empty($data)) {
            return $data;
        }
        $json = wp_json_encode($data);
        if ($json) {
            $decoded = json_decode($this->rewrite_origin_urls($json), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $data;
    }

    /**
     * Detect the currently active SEO engine
     *
     * @return string 'rank_math'|'yoast'|'seopress'|'aioseo'|'native'
     */
    public function detect_active_seo_engine() {
        if (defined('RANK_MATH_VERSION') || class_exists('\RankMath\Paper\Paper')) {
            return 'rank_math';
        }
        if (defined('WPSEO_VERSION') || defined('WPSEO_FILE') || class_exists('WPSEO_Options')) {
            return 'yoast';
        }
        if (defined('SEOPRESS_VERSION') || function_exists('seopress_titles_title')) {
            return 'seopress';
        }
        if (defined('AIOSEO_VERSION') || function_exists('aioseo')) {
            return 'aioseo';
        }
        return 'native';
    }

    /**
     * Invalidate rendered SEO head cache on post save or deletion
     *
     * @param int $post_id
     * @param WP_Post|null $post
     */
    public function invalidate_post_seo_cache($post_id, $post = null) {
        if (empty($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        delete_post_meta($post_id, '_astropress_yoast_head');
        delete_post_meta($post_id, '_astropress_rm_head');
        delete_post_meta($post_id, '_headless_rm_head');
        delete_post_meta($post_id, '_astropress_seopress_head');
        delete_post_meta($post_id, '_astropress_aioseo_head');
        delete_post_meta($post_id, '_astropress_native_head');
    }

    /**
     * Get rendered SEO head HTML tags and JSON-LD schema across active SEO engines
     * (Rank Math, Yoast SEO, SEOPress, All in One SEO, or native fallback)
     *
     * @param string|int|WP_Post|array $url Target URL, path, slug, post ID, or post object
     * @param array $options Additional options (force => bool, post_id => int, post => WP_Post)
     * @return array Array containing 'head' (string) and 'engine_used' (string)
     */
    public function get_rendered_head($url, $options = array()) {
        $post_id     = 0;
        $target_post = null;
        $full_url    = '';

        if (is_numeric($url)) {
            $post_id     = (int) $url;
            $target_post = get_post($post_id);
            if ($target_post) {
                $full_url = get_permalink($target_post);
            }
        } elseif (is_object($url) && isset($url->ID)) {
            $target_post = $url;
            $post_id     = (int) $url->ID;
            $full_url    = get_permalink($target_post);
        } elseif (is_array($url)) {
            $post_id = (int) ($url['id'] ?? $url['ID'] ?? 0);
            if ($post_id) {
                $target_post = get_post($post_id);
                $full_url    = get_permalink($target_post);
            }
        } elseif (is_string($url) && !empty($url)) {
            if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                $full_url = $url;
                $post_id  = function_exists('url_to_postid') ? url_to_postid($url) : 0;
                if ($post_id) {
                    $target_post = get_post($post_id);
                } else {
                    $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
                    if (!empty($path)) {
                        $target_post = get_page_by_path($path, OBJECT, array('post', 'page'));
                        if ($target_post) {
                            $post_id = $target_post->ID;
                        }
                    }
                }
            } else {
                $slug = trim($url, '/');
                $target_post = get_page_by_path($slug, OBJECT, array('post', 'page'));
                if ($target_post) {
                    $post_id  = $target_post->ID;
                    $full_url = get_permalink($target_post);
                } else {
                    $full_url = home_url('/' . $slug . '/');
                }
            }
        }

        // Check fallback options
        if (!$post_id && !empty($options['post_id'])) {
            $post_id = (int) $options['post_id'];
            if (!$target_post) {
                $target_post = get_post($post_id);
            }
        }
        if (!$target_post && !empty($options['post']) && is_object($options['post'])) {
            $target_post = $options['post'];
            $post_id     = (int) $target_post->ID;
        }
        if (empty($full_url) && $target_post) {
            $full_url = get_permalink($target_post);
        }

        $force        = !empty($options['force']);
        $engine       = $this->detect_active_seo_engine();
        $frontend_url = $this->core->get_frontend_url();

        $engine_meta = array(
            'yoast'     => array('meta_key' => '_astropress_yoast_head', 'legacy_key' => ''),
            'seopress'  => array('meta_key' => '_astropress_seopress_head', 'legacy_key' => ''),
            'aioseo'    => array('meta_key' => '_astropress_aioseo_head', 'legacy_key' => ''),
            'rank_math' => array('meta_key' => '_astropress_rm_head', 'legacy_key' => '_headless_rm_head'),
            'native'    => array('meta_key' => '_astropress_native_head', 'legacy_key' => ''),
        );
        $meta_cfg = $engine_meta[$engine] ?? $engine_meta['native'];

        if ($post_id && !$force) {
            $cached_head = get_post_meta($post_id, $meta_cfg['meta_key'], true);
            if (empty($cached_head) && $meta_cfg['legacy_key'] !== '') {
                $cached_head = get_post_meta($post_id, $meta_cfg['legacy_key'], true);
            }
            if (!empty($cached_head)) {
                return array(
                    'head'        => $this->rewrite_origin_urls($cached_head, $frontend_url),
                    'engine_used' => $engine,
                );
            }
        }

        $orig_post = $GLOBALS['post'] ?? null;
        if ($target_post) {
            $GLOBALS['post'] = $target_post;
            setup_postdata($target_post);
        }

        $head = $this->render_engine_head($engine, $target_post, $post_id, $full_url);

        if ($orig_post) {
            $GLOBALS['post'] = $orig_post;
            setup_postdata($orig_post);
        } else {
            wp_reset_postdata();
        }

        $clean_head = !empty($head) ? $this->rewrite_origin_urls(trim($head), $frontend_url) : '';
        if ($clean_head !== '' && $post_id) {
            update_post_meta($post_id, $meta_cfg['meta_key'], $clean_head);
            if ($meta_cfg['legacy_key'] !== '') {
                update_post_meta($post_id, $meta_cfg['legacy_key'], $clean_head);
            }
        }

        return array(
            'head'        => $clean_head,
            'engine_used' => $engine,
        );
    }

    /**
     * Render raw head tags for the active SEO engine while global post context is active.
     *
     * @param string       $engine
     * @param WP_Post|null $target_post
     * @param int          $post_id
     * @param string       $full_url
     * @return string
     */
    protected function render_engine_head($engine, $target_post, $post_id, $full_url) {
        if ($engine === 'yoast') {
            $head = '';
            if (class_exists('\Yoast\WP\SEO\Surfaces\Meta_Surface') || function_exists('YoastSEO')) {
                try {
                    if (function_exists('YoastSEO') && isset(YoastSEO()->meta)) {
                        $meta_surface = YoastSEO()->meta;
                        $targets = array(
                            !empty($full_url) && method_exists($meta_surface, 'for_url') ? $meta_surface->for_url($full_url) : null,
                            $post_id && method_exists($meta_surface, 'for_post') ? $meta_surface->for_post($post_id) : null,
                        );
                        foreach ($targets as $meta) {
                            if (!$meta) {
                                continue;
                            }
                            if (method_exists($meta, 'get_head')) {
                                $head = $meta->get_head();
                            } elseif (property_exists($meta, 'html')) {
                                $head = $meta->html;
                            }
                            if (!empty($head)) {
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $head = '';
                }
            }
            if (empty($head) && (has_action('wpseo_head') || function_exists('wpseo_head'))) {
                ob_start();
                do_action('wpseo_head');
                $head = ob_get_clean();
            }
            return !empty($head) ? $head : $this->simulate_yoast_head($target_post, $full_url);
        }

        if ($engine === 'seopress') {
            return $this->render_seopress_head($target_post, $full_url);
        }

        if ($engine === 'aioseo') {
            return $this->render_aioseo_head($target_post, $full_url);
        }

        if ($engine === 'rank_math') {
            $head = '';
            if (class_exists('\RankMath\Paper\Paper') && function_exists('rank_math') && !empty(rank_math()->head)) {
                ob_start();
                do_action('rank_math/head');
                $head = ob_get_clean();
            }
            return !empty($head) ? $head : $this->render_rank_math_head($target_post, $full_url);
        }

        return $this->render_native_head($target_post, $full_url);
    }

    /**
     * Get rendered Rank Math / Yoast head HTML for a post or page
     * Persistently cached in post meta to avoid executing heavy filters repeatedly.
     * Backwards-compatible facade that routes directly through get_rendered_head.
     *
     * @param array|int|WP_Post $post_arr
     * @return string|null
     */
    public function get_rank_math_head($post_arr) {
        $result = $this->get_rendered_head($post_arr);
        if (is_array($result) && isset($result['head'])) {
            return $result['head'];
        }
        return is_string($result) ? $result : null;
    }

    /**
     * Assemble HTML head tag string from structured key-value descriptors
     *
     * @param array $tags
     * @return string
     */
    protected function assemble_head_tags($tags) {
        $lines = array();
        if (!empty($tags['title'])) {
            $lines[] = '<title>' . esc_html($tags['title']) . '</title>';
        }
        if (!empty($tags['description'])) {
            $lines[] = '<meta name="description" content="' . esc_attr($tags['description']) . '" />';
        }
        if (!empty($tags['robots'])) {
            $lines[] = '<meta name="robots" content="' . esc_attr($tags['robots']) . '" />';
        }
        if (!empty($tags['canonical'])) {
            $lines[] = '<link rel="canonical" href="' . esc_url($tags['canonical']) . '" />';
        }
        if (!empty($tags['og_locale'])) {
            $lines[] = '<meta property="og:locale" content="' . esc_attr($tags['og_locale']) . '" />';
        }
        if (!empty($tags['og_type'])) {
            $lines[] = '<meta property="og:type" content="' . esc_attr($tags['og_type']) . '" />';
        }
        if (!empty($tags['og_title'])) {
            $lines[] = '<meta property="og:title" content="' . esc_attr($tags['og_title']) . '" />';
        }
        if (!empty($tags['og_description'])) {
            $lines[] = '<meta property="og:description" content="' . esc_attr($tags['og_description']) . '" />';
        }
        if (!empty($tags['og_url'])) {
            $lines[] = '<meta property="og:url" content="' . esc_url($tags['og_url']) . '" />';
        }
        if (!empty($tags['og_site_name'])) {
            $lines[] = '<meta property="og:site_name" content="' . esc_attr($tags['og_site_name']) . '" />';
        }
        if (!empty($tags['article_published_time'])) {
            $lines[] = '<meta property="article:published_time" content="' . esc_attr($tags['article_published_time']) . '" />';
        }
        if (!empty($tags['article_modified_time'])) {
            $lines[] = '<meta property="article:modified_time" content="' . esc_attr($tags['article_modified_time']) . '" />';
        }
        if (!empty($tags['og_image'])) {
            $lines[] = '<meta property="og:image" content="' . esc_url($tags['og_image']) . '" />';
        }
        if (!empty($tags['twitter_card'])) {
            $lines[] = '<meta name="twitter:card" content="' . esc_attr($tags['twitter_card']) . '" />';
        }
        if (!empty($tags['twitter_title'])) {
            $lines[] = '<meta name="twitter:title" content="' . esc_attr($tags['twitter_title']) . '" />';
        }
        if (!empty($tags['twitter_description'])) {
            $lines[] = '<meta name="twitter:description" content="' . esc_attr($tags['twitter_description']) . '" />';
        }
        if (!empty($tags['twitter_image'])) {
            $lines[] = '<meta name="twitter:image" content="' . esc_url($tags['twitter_image']) . '" />';
        }
        if (!empty($tags['json_ld'])) {
            $json = is_array($tags['json_ld']) ? wp_json_encode($tags['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $tags['json_ld'];
            $class_attr = !empty($tags['schema_class']) ? ' class="' . esc_attr($tags['schema_class']) . '"' : '';
            $lines[] = '<script type="application/ld+json"' . $class_attr . '>' . $json . '</script>';
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve shared base post/site metadata for SEO head fallback renderers.
     *
     * @param WP_Post|null $target_post
     * @param string       $full_url
     * @return array
     */
    protected function get_post_head_base($target_post, $full_url) {
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $site_desc    = get_bloginfo('description') ?: '';
        $post_id      = $target_post ? $target_post->ID : 0;

        $desc = '';
        if ($target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        $canonical = $full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url);
        $thumb_img = '';
        if ($post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $thumb_img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }

        return array(
            'frontend_url' => $frontend_url,
            'site_name'    => $site_name,
            'site_desc'    => $site_desc,
            'post_id'      => $post_id,
            'title'        => $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name,
            'desc'         => $desc,
            'canonical'    => $this->rewrite_origin_urls($canonical, $frontend_url),
            'thumb_img'    => $thumb_img ? $this->rewrite_origin_urls($thumb_img, $frontend_url) : '',
        );
    }

    /**
     * Normalize SEO engine head overrides onto shared fallback values and rewrite origin URLs.
     *
     * @param WP_Post|null $target_post
     * @param array        $base
     * @param array        $overrides
     * @return array Normalized descriptor array ready for assemble_head_tags().
     */
    protected function normalize_post_head_data($target_post, array $base, array $overrides = array()) {
        $frontend_url = $base['frontend_url'];
        $title        = !empty($overrides['title']) ? $overrides['title'] : $base['title'];
        $desc         = !empty($overrides['desc']) ? $overrides['desc'] : $base['desc'];
        $canonical    = !empty($overrides['canonical'])
            ? $this->rewrite_origin_urls($overrides['canonical'], $frontend_url)
            : $base['canonical'];

        $og_title = !empty($overrides['og_title']) ? $overrides['og_title'] : $title;
        $og_desc  = !empty($overrides['og_desc']) ? $overrides['og_desc'] : $desc;
        $og_img   = !empty($overrides['og_img']) ? $overrides['og_img'] : $base['thumb_img'];
        $og_img   = $og_img ? $this->rewrite_origin_urls($og_img, $frontend_url) : '';

        $tw_title = !empty($overrides['tw_title']) ? $overrides['tw_title'] : $og_title;
        $tw_desc  = !empty($overrides['tw_desc']) ? $overrides['tw_desc'] : $og_desc;
        $tw_img   = !empty($overrides['tw_img']) ? $overrides['tw_img'] : $og_img;
        $tw_img   = $tw_img ? $this->rewrite_origin_urls($tw_img, $frontend_url) : '';

        return array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => !empty($overrides['robots']) ? $overrides['robots'] : 'index, follow, max-image-preview:large',
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $og_title,
            'og_description'         => $og_desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $base['site_name'],
            'article_published_time' => $target_post ? get_the_date('c', $target_post) : '',
            'article_modified_time'  => $target_post ? get_the_modified_date('c', $target_post) : '',
            'og_image'               => $og_img,
            'twitter_card'           => !empty($overrides['tw_card']) ? $overrides['tw_card'] : 'summary_large_image',
            'twitter_title'          => $tw_title,
            'twitter_description'    => $tw_desc,
            'twitter_image'          => $tw_img,
        );
    }

    /**
     * Fallback simulator for Yoast SEO head HTML and JSON-LD schema
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function simulate_yoast_head($target_post, $full_url) {
        $base    = $this->get_post_head_base($target_post, $full_url);
        $post_id = $base['post_id'];

        $raw_title    = $post_id ? get_post_meta($post_id, '_yoast_wpseo_title', true) : '';
        $raw_desc     = $post_id ? get_post_meta($post_id, '_yoast_wpseo_metadesc', true) : '';
        $raw_og_title = $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true) : '';
        $raw_og_desc  = $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true) : '';
        $raw_tw_title = $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-title', true) : '';
        $raw_tw_desc  = $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-description', true) : '';

        $noindex  = $post_id ? get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) : '0';
        $nofollow = $post_id ? get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true) : '0';
        $robots   = implode(', ', array(
            ($noindex === '1') ? 'noindex' : 'index',
            ($nofollow === '1') ? 'nofollow' : 'follow',
            'max-snippet:-1',
            'max-image-preview:large',
            'max-video-preview:-1',
        ));

        $tags = $this->normalize_post_head_data($target_post, $base, array(
            'title'     => $raw_title ? $this->resolve_seo_template_vars($raw_title, $target_post) : '',
            'desc'      => $raw_desc ? $this->resolve_seo_template_vars($raw_desc, $target_post) : '',
            'canonical' => $post_id ? get_post_meta($post_id, '_yoast_wpseo_canonical', true) : '',
            'og_title'  => $raw_og_title ? $this->resolve_seo_template_vars($raw_og_title, $target_post) : '',
            'og_desc'   => $raw_og_desc ? $this->resolve_seo_template_vars($raw_og_desc, $target_post) : '',
            'og_img'    => $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true) : '',
            'tw_title'  => $raw_tw_title ? $this->resolve_seo_template_vars($raw_tw_title, $target_post) : '',
            'tw_desc'   => $raw_tw_desc ? $this->resolve_seo_template_vars($raw_tw_desc, $target_post) : '',
            'tw_img'    => $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-image', true) : '',
            'robots'    => $robots,
        ));

        $website_id   = rtrim($base['frontend_url'], '/') . '/#website';
        $canonical    = $tags['canonical'];
        $author_name  = $target_post ? (get_the_author_meta('display_name', $target_post->post_author) ?: 'Editorial Team') : 'Editorial Team';
        $schema_graph = array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'       => 'WebSite',
                    '@id'         => $website_id,
                    'url'         => rtrim($base['frontend_url'], '/') . '/',
                    'name'        => $base['site_name'],
                    'description' => $base['site_desc'],
                    'inLanguage'  => $tags['og_locale'],
                ),
                array(
                    '@type'         => 'WebPage',
                    '@id'           => $canonical . '#webpage',
                    'url'           => $canonical,
                    'name'          => $tags['title'],
                    'isPartOf'      => array('@id' => $website_id),
                    'datePublished' => $tags['article_published_time'],
                    'dateModified'  => $tags['article_modified_time'],
                    'description'   => $tags['description'],
                    'breadcrumb'    => array('@id' => $canonical . '#breadcrumb'),
                    'inLanguage'    => $tags['og_locale'],
                ),
            ),
        );

        if (!empty($tags['og_image'])) {
            $schema_graph['@graph'][] = array(
                '@type'      => 'ImageObject',
                '@id'        => $canonical . '#primaryimage',
                'inLanguage' => $tags['og_locale'],
                'url'        => $tags['og_image'],
                'contentUrl' => $tags['og_image'],
            );
        }

        if ($target_post && $target_post->post_type === 'post') {
            $article_schema = array(
                '@type'            => 'Article',
                '@id'              => $canonical . '#article',
                'isPartOf'         => array('@id' => $canonical . '#webpage'),
                'author'           => array('@type' => 'Person', 'name' => $author_name),
                'headline'         => $tags['title'],
                'datePublished'    => $tags['article_published_time'],
                'dateModified'     => $tags['article_modified_time'],
                'mainEntityOfPage' => array('@id' => $canonical . '#webpage'),
                'publisher'        => array('@id' => $website_id),
                'inLanguage'       => $tags['og_locale'],
            );
            if (!empty($tags['og_image'])) {
                $article_schema['image'] = array('@id' => $canonical . '#primaryimage');
            }
            $schema_graph['@graph'][] = $article_schema;
        }

        $tags['json_ld']      = $schema_graph;
        $tags['schema_class'] = 'yoast-schema-graph';
        return $this->assemble_head_tags($tags);
    }

    /**
     * Render Rank Math head HTML for a post or page with high-fidelity meta extraction
     * Supports Singular Paper, Rank Math helper API, and direct postmeta fallbacks
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_rank_math_head($target_post, $full_url) {
        $base              = $this->get_post_head_base($target_post, $full_url);
        $post_id           = $base['post_id'];
        $title             = '';
        $desc              = '';
        $robots_directives = array();

        if (class_exists('\RankMath\Paper\Singular') && $target_post) {
            try {
                $singular = new \RankMath\Paper\Singular();
                $singular->set_object($target_post);
                $cand_title = (string) $singular->title();
                $cand_desc  = (string) $singular->description();
                if (!empty($cand_title) && trim(preg_replace('/[\(\)\[\]\-\—\s\|]/', '', $cand_title)) !== '') {
                    $title = $cand_title;
                }
                if (!empty($cand_desc) && trim($cand_desc) !== '') {
                    $desc = $cand_desc;
                }
                $rob = $singular->robots();
                if (is_array($rob)) {
                    $robots_directives = array_keys(array_filter($rob));
                }
            } catch (\Throwable $e) {}
        }

        if ($post_id) {
            $raw_title = get_post_meta($post_id, 'rank_math_title', true);
            if (!empty($raw_title)) {
                $title = $this->resolve_seo_template_vars($raw_title, $target_post) ?: $title;
            }
            $raw_desc = get_post_meta($post_id, 'rank_math_description', true);
            if (!empty($raw_desc)) {
                $desc = $this->resolve_seo_template_vars($raw_desc, $target_post) ?: $desc;
            }
        }

        if (empty($robots_directives) && $post_id) {
            $robots_directives = $this->normalize_robots_list(get_post_meta($post_id, 'rank_math_robots', true));
        }

        $tags = $this->normalize_post_head_data($target_post, $base, array(
            'title'     => $title,
            'desc'      => $desc,
            'canonical' => $post_id ? get_post_meta($post_id, 'rank_math_canonical_url', true) : '',
            'og_title'  => $post_id ? get_post_meta($post_id, 'rank_math_facebook_title', true) : '',
            'og_desc'   => $post_id ? get_post_meta($post_id, 'rank_math_facebook_description', true) : '',
            'og_img'    => $post_id ? get_post_meta($post_id, 'rank_math_facebook_image', true) : '',
            'tw_title'  => $post_id ? get_post_meta($post_id, 'rank_math_twitter_title', true) : '',
            'tw_desc'   => $post_id ? get_post_meta($post_id, 'rank_math_twitter_description', true) : '',
            'tw_img'    => $post_id ? get_post_meta($post_id, 'rank_math_twitter_image', true) : '',
            'tw_card'   => $post_id ? get_post_meta($post_id, 'rank_math_twitter_card_type', true) : '',
            'robots'    => !empty($robots_directives) ? implode(', ', array_unique($robots_directives)) : '',
        ));

        return $this->assemble_head_tags($tags);
    }

    /**
     * Render SEOPress head HTML for a post or page
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_seopress_head($target_post, $full_url) {
        $base    = $this->get_post_head_base($target_post, $full_url);
        $post_id = $base['post_id'];
        $read    = static function ($fn, $meta_key) use ($post_id) {
            $val = function_exists($fn) ? $fn() : '';
            if (empty($val) && $post_id) {
                $val = get_post_meta($post_id, $meta_key, true);
            }
            return $val;
        };

        $robots = 'index, follow';
        if ($post_id) {
            $noindex  = get_post_meta($post_id, '_seopress_titles_robots_index', true);
            $nofollow = get_post_meta($post_id, '_seopress_titles_robots_follow', true);
            $robots   = (($noindex === 'yes') ? 'noindex' : 'index') . ', ' . (($nofollow === 'yes') ? 'nofollow' : 'follow');
        }

        $tags = $this->normalize_post_head_data($target_post, $base, array(
            'title'     => $read('seopress_titles_title', '_seopress_titles_title'),
            'desc'      => $read('seopress_titles_desc', '_seopress_titles_desc'),
            'canonical' => $read('seopress_titles_canonical_url', '_seopress_titles_canonical'),
            'og_title'  => $read('seopress_social_fb_title', '_seopress_social_fb_title'),
            'og_desc'   => $read('seopress_social_fb_desc', '_seopress_social_fb_desc'),
            'og_img'    => $read('seopress_social_fb_img', '_seopress_social_fb_img'),
            'tw_title'  => $read('seopress_social_twitter_title', '_seopress_social_twitter_title'),
            'tw_desc'   => $read('seopress_social_twitter_desc', '_seopress_social_twitter_desc'),
            'tw_img'    => $read('seopress_social_twitter_img', '_seopress_social_twitter_img'),
            'tw_card'   => $post_id ? get_post_meta($post_id, '_seopress_social_twitter_card', true) : '',
            'robots'    => $robots,
        ));

        return $this->assemble_head_tags($tags);
    }

    /**
     * Render All in One SEO (AIOSEO) head HTML for a post or page
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_aioseo_head($target_post, $full_url) {
        $base        = $this->get_post_head_base($target_post, $full_url);
        $post_id     = $base['post_id'];
        $aioseo_meta = null;
        if (function_exists('aioseo') && isset(aioseo()->meta->metaData) && $target_post) {
            try {
                $aioseo_meta = aioseo()->meta->metaData->getMetaData($target_post);
            } catch (\Throwable $e) {
                $aioseo_meta = null;
            }
        }

        $read = static function (array $props, $meta_keys) use ($aioseo_meta, $post_id) {
            if ($aioseo_meta) {
                foreach ($props as $prop) {
                    if (is_object($aioseo_meta)) {
                        if (!empty($aioseo_meta->$prop)) return $aioseo_meta->$prop;
                        if (method_exists($aioseo_meta, 'get') && $aioseo_meta->get($prop)) return $aioseo_meta->get($prop);
                    } elseif (is_array($aioseo_meta) && !empty($aioseo_meta[$prop])) {
                        return $aioseo_meta[$prop];
                    }
                }
            }
            if ($post_id) {
                foreach ((array) $meta_keys as $k) {
                    $v = get_post_meta($post_id, $k, true);
                    if (!empty($v)) return $v;
                }
            }
            return '';
        };

        $tags = $this->normalize_post_head_data($target_post, $base, array(
            'title'     => $read(array('title'), '_aioseo_title'),
            'desc'      => $read(array('description'), '_aioseo_description'),
            'canonical' => $read(array('canonicalUrl', 'canonical_url'), '_aioseo_canonical_url'),
            'og_title'  => $read(array('og_title'), '_aioseo_og_title'),
            'og_desc'   => $read(array('og_description'), '_aioseo_og_description'),
            'og_img'    => $read(array('og_image_custom_url', 'og_image_url'), '_aioseo_og_image_custom_url'),
            'tw_title'  => $read(array('twitter_title'), '_aioseo_twitter_title'),
            'tw_desc'   => $read(array('twitter_description'), '_aioseo_twitter_description'),
            'tw_img'    => $read(array('twitter_image_custom_url', 'twitter_image_url'), '_aioseo_twitter_image_custom_url'),
            'tw_card'   => $read(array('twitter_card'), '_aioseo_twitter_card'),
        ));

        return $this->assemble_head_tags($tags);
    }

    /**
     * Render native fallback head HTML when no third-party SEO plugin is active
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_native_head($target_post, $full_url) {
        $base = $this->get_post_head_base($target_post, $full_url);
        return $this->assemble_head_tags($this->normalize_post_head_data($target_post, $base));
    }

    /**
     * Resolve template variables (%title%, %currentyear%, %sep%, %author%, etc.)
     *
     * @param string $template
     * @param int|WP_Post $post_obj
     * @return string
     */
    /**
     * Resolve SEO template placeholders (%title%, %sitename%, etc.) uniformly.
     *
     * @param string $template
     * @param array  $replacements Map of '%var%' => value.
     * @param string $fallback
     * @param string $site_name
     * @return string
     */
    protected function finalize_template_resolution($template, array $replacements, $fallback, $site_name) {
        $resolved = str_ireplace(array_keys($replacements), array_values($replacements), $template);
        $resolved = preg_replace('/%[a-z0-9_-]+%/i', '', $resolved);
        $resolved = trim(preg_replace('/\s+/', ' ', $resolved));

        $without_site  = trim(str_ireplace($site_name, '', $resolved));
        $without_punct = trim(preg_replace('/[\(\)\[\]\-\—\s\|\:\,]/', '', $without_site));

        return $without_punct === '' ? $fallback : $resolved;
    }

    /**
     * Query active Rank Math exact redirection rules matching any candidate path.
     *
     * @param array $candidate_paths Normalized trim('/', $path) candidates.
     * @param array $like_terms      SQL LIKE search hints.
     * @return array|null ['url' => string, 'code' => int]
     */
    protected function lookup_rank_math_redirection(array $candidate_paths, array $like_terms) {
        global $wpdb;
        $candidate_paths = array_values(array_unique(array_filter(array_map(static function ($p) {
            return trim((string) $p, '/');
        }, $candidate_paths))));

        if (empty($candidate_paths) || empty($like_terms) || empty($wpdb) || empty($wpdb->prefix)) {
            return null;
        }

        $table_name = $wpdb->prefix . 'rank_math_redirections';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) !== $table_name) {
            return null;
        }

        $like_clauses = array();
        $params       = array();
        foreach (array_unique(array_filter($like_terms)) as $hint) {
            $like_clauses[] = 'sources LIKE %s';
            $params[]       = '%' . $wpdb->esc_like((string) $hint) . '%';
        }
        if (empty($like_clauses)) {
            return null;
        }

        $sql  = "SELECT sources, url_to, header_code FROM {$table_name} WHERE status = 'active' AND (" . implode(' OR ', $like_clauses) . ') ORDER BY id DESC LIMIT 20';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        if (empty($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            $sources = maybe_unserialize($row['sources']);
            if (!is_array($sources)) {
                continue;
            }
            foreach ($sources as $source_item) {
                $pattern    = trim((string) ($source_item['pattern'] ?? ''), '/');
                $comparison = $source_item['comparison'] ?? 'exact';
                if ($comparison !== 'exact') {
                    continue;
                }
                foreach ($candidate_paths as $candidate) {
                    if (strcasecmp($pattern, $candidate) === 0) {
                        return array(
                            'url'  => $row['url_to'],
                            'code' => (int) ($row['header_code'] ?: 301),
                        );
                    }
                }
            }
        }
        return null;
    }

    /**
     * Resolve SEO template variables
     *
     * @param string          $template
     * @param int|WP_Post     $post_obj
     * @return string
     */
    public function resolve_seo_template_vars($template, $post_obj) {
        $target_post = is_numeric($post_obj) ? get_post($post_obj) : $post_obj;
        $title       = $target_post ? $target_post->post_title : '';
        $site_name   = get_bloginfo('name') ?: 'AstroPress';

        if (empty($template) || !is_string($template)) {
            return $title;
        }

        $stripped = trim(preg_replace('/[\(\)\[\]\-\—\s\|]/', '', $template));
        if (empty($stripped)) {
            return $title;
        }

        $excerpt     = '';
        $author_name = '';
        $focuskw     = '';
        if ($target_post) {
            $excerpt = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 30);
            $author_name = get_the_author_meta('display_name', $target_post->post_author) ?: '';
            $focuskw     = get_post_meta($target_post->ID, 'rank_math_focus_keyword', true)
                ?: get_post_meta($target_post->ID, '_yoast_wpseo_focuskw', true)
                ?: '';
        }

        $cats     = $target_post ? get_the_category($target_post->ID) : array();
        $cat_name = (!empty($cats) && !is_wp_error($cats)) ? $cats[0]->name : '';

        return $this->finalize_template_resolution($template, array(
            '%title%'        => $title,
            '%currentyear%'  => date('Y'),
            '%currentmonth%' => date('F'),
            '%currentday%'   => date('j'),
            '%sep%'          => '—',
            '%sitename%'     => $site_name,
            '%sitedesc%'     => get_bloginfo('description') ?: '',
            '%page%'         => '',
            '%category%'     => $cat_name,
            '%excerpt%'      => $excerpt,
            '%focuskw%'      => $focuskw,
            '%author%'       => $author_name,
            '%date%'         => $target_post ? get_the_date('', $target_post) : '',
            '%modified%'     => $target_post ? get_the_modified_date('', $target_post) : '',
        ), $title, $site_name);
    }

    /**
     * Resolve template variables for taxonomy terms
     *
     * @param string      $template
     * @param int|WP_Term $term_obj
     * @return string
     */
    public function resolve_term_seo_template_vars($template, $term_obj) {
        $term = is_numeric($term_obj) ? get_term($term_obj) : $term_obj;
        if (empty($template) || !is_string($template)) {
            return '';
        }

        $term_name = $term ? $term->name : '';
        $term_desc = $term ? wp_strip_all_tags($term->description) : '';
        $site_name = get_bloginfo('name') ?: 'AstroPress';

        return $this->finalize_template_resolution($template, array(
            '%category%'         => $term_name,
            '%tag%'              => $term_name,
            '%term%'             => $term_name,
            '%title%'            => $term_name,
            '%term_description%' => $term_desc,
            '%sitename%'         => $site_name,
            '%sitedesc%'         => get_bloginfo('description') ?: '',
            '%currentyear%'      => date('Y'),
            '%currentmonth%'     => date('F'),
            '%currentday%'       => date('j'),
            '%sep%'              => '—',
            '%page%'             => '',
        ), $term_name, $site_name);
    }

    /**
     * Read the first non-empty post meta value from a prioritized key list.
     *
     * @param int   $post_id
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    protected function get_first_post_meta($post_id, array $keys, $default = null) {
        foreach ($keys as $key) {
            $val = get_post_meta($post_id, $key, true);
            if ($val !== '' && $val !== null && $val !== false && $val !== array()) {
                return $val;
            }
        }
        return $default;
    }

    /**
     * Normalize Rank Math basic and advanced robots meta values into a directive list.
     *
     * @param mixed $raw_robots
     * @param mixed $raw_adv_rob
     * @return array
     */
    protected function normalize_robots_list($raw_robots, $raw_adv_rob = '') {
        if (is_string($raw_robots) && strpos($raw_robots, 'a:') === 0) {
            $raw_robots = maybe_unserialize($raw_robots);
        }
        $robots_list = array();
        if (is_array($raw_robots)) {
            foreach ($raw_robots as $r) {
                if ($r && is_string($r)) {
                    $robots_list[] = trim($r);
                }
            }
        } elseif (is_string($raw_robots) && !empty($raw_robots)) {
            $robots_list[] = trim($raw_robots);
        }

        if (is_string($raw_adv_rob) && strpos($raw_adv_rob, 'a:') === 0) {
            $raw_adv_rob = maybe_unserialize($raw_adv_rob);
        }
        if (is_array($raw_adv_rob)) {
            foreach ($raw_adv_rob as $directive => $val) {
                if ($val !== '' && $val !== false) {
                    $robots_list[] = "{$directive}:{$val}";
                }
            }
        }

        return $robots_list;
    }

    /**
     * Get structured SEO fields for a post or page
     *
     * @param array|int $post_arr
     * @return array|null
     */
    public function get_rank_math_seo_fields($post_arr) {
        $post_id = is_array($post_arr) ? ($post_arr['id'] ?? 0) : $post_arr;
        if (!$post_id) {
            return null;
        }

        $target_post  = get_post($post_id);
        $frontend_url = $this->core->get_frontend_url();

        $raw_title     = $this->get_first_post_meta($post_id, array('rank_math_title', '_yoast_wpseo_title', '_seopress_titles_title', '_aioseo_title'), '');
        $raw_desc      = $this->get_first_post_meta($post_id, array('rank_math_description', '_yoast_wpseo_metadesc', '_seopress_titles_desc', '_aioseo_description'), '');
        $raw_canonical = $this->get_first_post_meta($post_id, array('rank_math_canonical_url', '_yoast_wpseo_canonical', '_seopress_titles_canonical', '_aioseo_canonical_url'), '');
        $raw_og_title  = $this->get_first_post_meta($post_id, array('rank_math_facebook_title', '_yoast_wpseo_opengraph-title', '_seopress_social_fb_title', '_aioseo_og_title'), '');
        $raw_og_desc   = $this->get_first_post_meta($post_id, array('rank_math_facebook_description', '_yoast_wpseo_opengraph-description', '_seopress_social_fb_desc', '_aioseo_og_description'), '');
        $raw_tw_title  = $this->get_first_post_meta($post_id, array('rank_math_twitter_title', '_yoast_wpseo_twitter-title', '_seopress_social_twitter_title', '_aioseo_twitter_title'), '');
        $raw_tw_desc   = $this->get_first_post_meta($post_id, array('rank_math_twitter_description', '_yoast_wpseo_twitter-description', '_seopress_social_twitter_desc', '_aioseo_twitter_description'), '');

        $robots_list = $this->normalize_robots_list(
            get_post_meta($post_id, 'rank_math_robots', true),
            get_post_meta($post_id, 'rank_math_advanced_robots', true)
        );

        $primary_cat_id   = (int) $this->get_first_post_meta($post_id, array('rank_math_primary_category', '_yoast_wpseo_primary_category'), 0) ?: null;
        $breadcrumb_title = $this->get_first_post_meta($post_id, array('rank_math_breadcrumb_title', '_yoast_wpseo_bctitle'));
        $pillar_content   = get_post_meta($post_id, 'rank_math_pillar_content', true) === 'on'
            || get_post_meta($post_id, '_yoast_wpseo_is_cornerstone', true) === '1';

        $canonical = $raw_canonical ? $this->rewrite_origin_urls($raw_canonical, $frontend_url) : null;

        // Rank Math Redirection detection
        $redirect_to   = get_post_meta($post_id, 'rank_math_redirection_url', true);
        $redirect_code = (int) (get_post_meta($post_id, 'rank_math_redirection_type', true) ?: 301);

        if (empty($redirect_to) && $target_post) {
            $slug  = ($target_post->post_type === 'page') ? get_page_uri($target_post->ID) : $target_post->post_name;
            $match = $this->lookup_rank_math_redirection(array($slug), array($target_post->post_name));
            if ($match) {
                $redirect_to   = $match['url'];
                $redirect_code = $match['code'];
            }
        }

        return array(
            'engine_used'         => $this->detect_active_seo_engine(),
            'title'               => $this->resolve_seo_template_vars($raw_title, $target_post) ?: ($target_post ? $target_post->post_title : null),
            'description'         => $this->resolve_seo_template_vars($raw_desc, $target_post) ?: null,
            'canonical_url'       => $canonical,
            'focus_keyword'       => $this->get_first_post_meta($post_id, array('rank_math_focus_keyword', '_yoast_wpseo_focuskw', '_seopress_analysis_target_kw')),
            'robots'              => !empty($robots_list) ? $robots_list : null,
            'og_title'            => $this->resolve_seo_template_vars($raw_og_title, $target_post) ?: null,
            'og_description'      => $this->resolve_seo_template_vars($raw_og_desc, $target_post) ?: null,
            'og_image'            => $this->get_first_post_meta($post_id, array('rank_math_facebook_image', '_yoast_wpseo_opengraph-image', '_seopress_social_fb_img', '_aioseo_og_image_custom_url')),
            'twitter_title'       => $this->resolve_seo_template_vars($raw_tw_title, $target_post) ?: null,
            'twitter_desc'        => $this->resolve_seo_template_vars($raw_tw_desc, $target_post) ?: null,
            'twitter_image'       => $this->get_first_post_meta($post_id, array('rank_math_twitter_image', '_yoast_wpseo_twitter-image', '_seopress_social_twitter_img', '_aioseo_twitter_image_custom_url')),
            'primary_category_id' => $primary_cat_id,
            'breadcrumb_title'    => $breadcrumb_title,
            'pillar_content'      => $pillar_content,
            'redirect_url'        => $redirect_to ? $this->rewrite_origin_urls($redirect_to, $frontend_url) : null,
            'redirect_type'       => $redirect_to ? (int) $redirect_code : null,
        );
    }

    /**
     * Get structured SEO fields for a taxonomy term (category / post_tag)
     *
     * @param array|int|WP_Term $term_arr
     * @return array|null
     */
    public function get_rank_math_term_seo_fields($term_arr) {
        $term_id = is_array($term_arr) ? ($term_arr['id'] ?? 0) : (is_object($term_arr) ? ($term_arr->term_id ?? 0) : (int) $term_arr);
        if (!$term_id) {
            return null;
        }

        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return null;
        }

        $frontend_url  = $this->core->get_frontend_url();
        $raw_canonical = get_term_meta($term_id, 'rank_math_canonical_url', true);
        $raw_og_image  = get_term_meta($term_id, 'rank_math_facebook_image', true);
        $raw_tw_image  = get_term_meta($term_id, 'rank_math_twitter_image', true);

        $robots_list = $this->normalize_robots_list(
            get_term_meta($term_id, 'rank_math_robots', true),
            get_term_meta($term_id, 'rank_math_advanced_robots', true)
        );

        // Check Rank Math redirections table for exact term path match
        $term_path            = trim($this->core->cache->get_term_path($term), '/');
        $term_slug            = trim($term->slug, '/');
        $category_prefix_path = 'category/' . $term_slug;
        $match                = $this->lookup_rank_math_redirection(
            array($term_slug, $term_path, $category_prefix_path),
            array($term_slug, $category_prefix_path)
        );
        $redirect_to   = $match ? $match['url'] : null;
        $redirect_code = $match ? $match['code'] : 301;

        return array(
            'title'          => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_title', true), $term) ?: null,
            'description'    => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_description', true), $term) ?: ($term->description ?: null),
            'canonical_url'  => $raw_canonical ? $this->rewrite_origin_urls($raw_canonical, $frontend_url) : null,
            'focus_keyword'  => get_term_meta($term_id, 'rank_math_focus_keyword', true) ?: null,
            'robots'         => !empty($robots_list) ? $robots_list : null,
            'og_title'       => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_facebook_title', true), $term) ?: null,
            'og_description' => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_facebook_description', true), $term) ?: null,
            'og_image'       => $raw_og_image ? $this->rewrite_origin_urls($raw_og_image, $frontend_url) : null,
            'twitter_title'  => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_twitter_title', true), $term) ?: null,
            'twitter_desc'   => $this->resolve_term_seo_template_vars(get_term_meta($term_id, 'rank_math_twitter_description', true), $term) ?: null,
            'twitter_image'  => $raw_tw_image ? $this->rewrite_origin_urls($raw_tw_image, $frontend_url) : null,
            'redirect_url'   => $redirect_to ? $this->rewrite_origin_urls($redirect_to, $frontend_url) : null,
            'redirect_type'  => $redirect_to ? (int) $redirect_code : null,
        );
    }

    /**
     * Synchronize configured default redirections to Rank Math
     * Configurable via the 'astropress_default_redirections' filter or option.
     */
    public function sync_default_redirections() {
        if (get_option('astropress_redirects_synced_v1')) {
            return;
        }

        $redirections = apply_filters('astropress_default_redirections', array());
        if (empty($redirections) || !is_array($redirections)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'rank_math_redirections';
        $has_rm_table = ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name);

        foreach ($redirections as $slug => $dest) {
            $post = get_page_by_path($slug, OBJECT, array('post', 'page'));
            if ($post) {
                update_post_meta($post->ID, 'rank_math_redirection_url', $dest);
                update_post_meta($post->ID, 'rank_math_redirection_type', '301');
                update_post_meta($post->ID, 'rank_math_has_redirect', 'yes');
            }

            if ($has_rm_table) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE sources LIKE %s LIMIT 1",
                    '%' . $wpdb->esc_like($slug) . '%'
                ));
                if (!$exists) {
                    $sources = serialize(array(
                        array(
                            'pattern'    => $slug,
                            'comparison' => 'exact',
                            'ignore'     => '1',
                        )
                    ));
                    $wpdb->insert($table_name, array(
                        'sources'     => $sources,
                        'url_to'      => $dest,
                        'header_code' => 301,
                        'hits'        => 0,
                        'status'      => 'active',
                        'created'     => current_time('mysql'),
                        'updated'     => current_time('mysql'),
                        'object_id'   => $post ? $post->ID : 0,
                        'object_type' => $post ? $post->post_type : 'post',
                    ));
                }
            }
        }

        update_option('astropress_redirects_synced_v1', 1);
    }
}
