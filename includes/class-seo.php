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
        $frontend_url = $this->core->get_frontend_url();
        return str_replace($this->get_origin_hosts(), $frontend_url, $url);
    }

    /**
     * Filter sitemap URL
     *
     * @param string $url
     * @param string $type
     * @return string
     */
    public function filter_rank_math_sitemap_url($url, $type = '') {
        if (empty($url) || !is_string($url)) {
            return $url;
        }
        $frontend_url = $this->core->get_frontend_url();
        return str_replace($this->get_origin_hosts(), $frontend_url, $url);
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
        $frontend_url = $this->core->get_frontend_url();
        if (!empty($entry['loc'])) {
            $entry['loc'] = str_replace($this->get_origin_hosts(), $frontend_url, $entry['loc']);
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
        $frontend_url = $this->core->get_frontend_url();
        return str_replace($this->get_origin_hosts(), $frontend_url, $xml);
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
        $frontend_url = $this->core->get_frontend_url();
        $json = wp_json_encode($data);
        if ($json) {
            $json = str_replace($this->get_origin_hosts(), $frontend_url, $json);
            $decoded = json_decode($json, true);
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

        // 1. Yoast SEO
        if ($engine === 'yoast') {
            if ($post_id) {
                $cached_head = get_post_meta($post_id, '_astropress_yoast_head', true);
                if (!empty($cached_head) && !$force) {
                    return array(
                        'head'        => str_replace($this->get_origin_hosts(), $frontend_url, $cached_head),
                        'engine_used' => 'yoast',
                    );
                }
            }

            $head = '';
            $orig_post = $GLOBALS['post'] ?? null;
            if ($target_post) {
                $GLOBALS['post'] = $target_post;
                setup_postdata($target_post);
            }

            // Official Surface API
            if (class_exists('\Yoast\WP\SEO\Surfaces\Meta_Surface') || function_exists('YoastSEO')) {
                try {
                    if (function_exists('YoastSEO') && isset(YoastSEO()->meta)) {
                        $meta_surface = YoastSEO()->meta;
                        if (!empty($full_url) && method_exists($meta_surface, 'for_url')) {
                            $meta = $meta_surface->for_url($full_url);
                            if ($meta && method_exists($meta, 'get_head')) {
                                $head = $meta->get_head();
                            } elseif ($meta && property_exists($meta, 'html')) {
                                $head = $meta->html;
                            }
                        }
                        if (empty($head) && $post_id && method_exists($meta_surface, 'for_post')) {
                            $meta = $meta_surface->for_post($post_id);
                            if ($meta && method_exists($meta, 'get_head')) {
                                $head = $meta->get_head();
                            } elseif ($meta && property_exists($meta, 'html')) {
                                $head = $meta->html;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $head = '';
                }
            }

            // Hook fallback
            if (empty($head) && (has_action('wpseo_head') || function_exists('wpseo_head'))) {
                ob_start();
                do_action('wpseo_head');
                $head = ob_get_clean();
            }

            if ($orig_post) {
                $GLOBALS['post'] = $orig_post;
                setup_postdata($orig_post);
            } else {
                wp_reset_postdata();
            }

            // Simulation fallback
            if (empty($head)) {
                $head = $this->simulate_yoast_head($target_post, $full_url);
            }

            $clean_head = !empty($head) ? trim($head) : '';
            if (!empty($clean_head)) {
                $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
                if ($post_id) {
                    update_post_meta($post_id, '_astropress_yoast_head', $clean_head);
                }
            }

            return array(
                'head'        => $clean_head,
                'engine_used' => 'yoast',
            );
        }

        // 2. SEOPress
        if ($engine === 'seopress') {
            if ($post_id) {
                $cached_head = get_post_meta($post_id, '_astropress_seopress_head', true);
                if (!empty($cached_head) && !$force) {
                    return array(
                        'head'        => str_replace($this->get_origin_hosts(), $frontend_url, $cached_head),
                        'engine_used' => 'seopress',
                    );
                }
            }

            $orig_post = $GLOBALS['post'] ?? null;
            if ($target_post) {
                $GLOBALS['post'] = $target_post;
                setup_postdata($target_post);
            }

            $head = $this->render_seopress_head($target_post, $full_url);

            if ($orig_post) {
                $GLOBALS['post'] = $orig_post;
                setup_postdata($orig_post);
            } else {
                wp_reset_postdata();
            }

            $clean_head = !empty($head) ? trim($head) : '';
            if (!empty($clean_head)) {
                $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
                if ($post_id) {
                    update_post_meta($post_id, '_astropress_seopress_head', $clean_head);
                }
            }

            return array(
                'head'        => $clean_head,
                'engine_used' => 'seopress',
            );
        }

        // 3. All in One SEO (AIOSEO)
        if ($engine === 'aioseo') {
            if ($post_id) {
                $cached_head = get_post_meta($post_id, '_astropress_aioseo_head', true);
                if (!empty($cached_head) && !$force) {
                    return array(
                        'head'        => str_replace($this->get_origin_hosts(), $frontend_url, $cached_head),
                        'engine_used' => 'aioseo',
                    );
                }
            }

            $orig_post = $GLOBALS['post'] ?? null;
            if ($target_post) {
                $GLOBALS['post'] = $target_post;
                setup_postdata($target_post);
            }

            $head = $this->render_aioseo_head($target_post, $full_url);

            if ($orig_post) {
                $GLOBALS['post'] = $orig_post;
                setup_postdata($orig_post);
            } else {
                wp_reset_postdata();
            }

            $clean_head = !empty($head) ? trim($head) : '';
            if (!empty($clean_head)) {
                $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
                if ($post_id) {
                    update_post_meta($post_id, '_astropress_aioseo_head', $clean_head);
                }
            }

            return array(
                'head'        => $clean_head,
                'engine_used' => 'aioseo',
            );
        }

        // 4. Rank Math
        if ($engine === 'rank_math') {
            if ($post_id) {
                $cached_head = get_post_meta($post_id, '_astropress_rm_head', true)
                    ?: get_post_meta($post_id, '_headless_rm_head', true);
                if (!empty($cached_head) && !$force) {
                    return array(
                        'head'        => str_replace($this->get_origin_hosts(), $frontend_url, $cached_head),
                        'engine_used' => 'rank_math',
                    );
                }
            }

            $head = '';
            // If on frontend where rank_math()->head is active, attempt official action
            if (class_exists('\RankMath\Paper\Paper') && function_exists('rank_math') && !empty(rank_math()->head)) {
                $orig_post = $GLOBALS['post'] ?? null;
                if ($target_post) {
                    $GLOBALS['post'] = $target_post;
                    setup_postdata($target_post);
                }
                ob_start();
                do_action('rank_math/head');
                $head = ob_get_clean();
                if ($orig_post) {
                    $GLOBALS['post'] = $orig_post;
                    setup_postdata($orig_post);
                } else {
                    wp_reset_postdata();
                }
            }

            if (empty($head)) {
                $head = $this->render_rank_math_head($target_post, $full_url);
            }

            $clean_head = !empty($head) ? trim($head) : '';
            if (!empty($clean_head)) {
                $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
                if ($post_id) {
                    update_post_meta($post_id, '_astropress_rm_head', $clean_head);
                    update_post_meta($post_id, '_headless_rm_head', $clean_head);
                }
            }

            return array(
                'head'        => $clean_head,
                'engine_used' => 'rank_math',
            );
        }

        // 5. Native Fallback
        if ($post_id) {
            $cached_head = get_post_meta($post_id, '_astropress_native_head', true);
            if (!empty($cached_head) && !$force) {
                return array(
                    'head'        => str_replace($this->get_origin_hosts(), $frontend_url, $cached_head),
                    'engine_used' => 'native',
                );
            }
        }

        $head = $this->render_native_head($target_post, $full_url);
        $clean_head = !empty($head) ? trim($head) : '';
        if (!empty($clean_head)) {
            $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
            if ($post_id) {
                update_post_meta($post_id, '_astropress_native_head', $clean_head);
            }
        }

        return array(
            'head'        => $clean_head,
            'engine_used' => 'native',
        );
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
     * Fallback simulator for Yoast SEO head HTML and JSON-LD schema
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function simulate_yoast_head($target_post, $full_url) {
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $site_desc    = get_bloginfo('description') ?: '';
        $post_id      = $target_post ? $target_post->ID : 0;

        $raw_title    = $post_id ? get_post_meta($post_id, '_yoast_wpseo_title', true) : '';
        $raw_desc     = $post_id ? get_post_meta($post_id, '_yoast_wpseo_metadesc', true) : '';
        $raw_canon    = $post_id ? get_post_meta($post_id, '_yoast_wpseo_canonical', true) : '';
        $raw_og_title = $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true) : '';
        $raw_og_desc  = $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true) : '';
        $raw_og_img   = $post_id ? get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true) : '';
        $raw_tw_title = $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-title', true) : '';
        $raw_tw_desc  = $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-description', true) : '';
        $raw_tw_img   = $post_id ? get_post_meta($post_id, '_yoast_wpseo_twitter-image', true) : '';

        // Title
        $title = $raw_title ? $this->resolve_seo_template_vars($raw_title, $target_post) : '';
        if (empty($title)) {
            $title = $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name;
        }

        // Description
        $desc = $raw_desc ? $this->resolve_seo_template_vars($raw_desc, $target_post) : '';
        if (empty($desc) && $target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        // Canonical
        $canonical = $raw_canon ?: ($full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url));
        $canonical = str_replace($this->get_origin_hosts(), $frontend_url, $canonical);

        // OpenGraph
        $og_title = $raw_og_title ? $this->resolve_seo_template_vars($raw_og_title, $target_post) : $title;
        $og_desc  = $raw_og_desc ? $this->resolve_seo_template_vars($raw_og_desc, $target_post) : $desc;
        $og_img   = $raw_og_img;
        if (empty($og_img) && $post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $og_img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }
        $og_img = $og_img ? str_replace($this->get_origin_hosts(), $frontend_url, $og_img) : '';

        // Twitter
        $tw_title = $raw_tw_title ? $this->resolve_seo_template_vars($raw_tw_title, $target_post) : $og_title;
        $tw_desc  = $raw_tw_desc ? $this->resolve_seo_template_vars($raw_tw_desc, $target_post) : $og_desc;
        $tw_img   = $raw_tw_img ?: $og_img;
        $tw_img   = $tw_img ? str_replace($this->get_origin_hosts(), $frontend_url, $tw_img) : '';

        // Robots
        $noindex = $post_id ? get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) : '0';
        $nofollow = $post_id ? get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true) : '0';
        $robots_directives = array();
        $robots_directives[] = ($noindex === '1') ? 'noindex' : 'index';
        $robots_directives[] = ($nofollow === '1') ? 'nofollow' : 'follow';
        $robots_directives[] = 'max-snippet:-1';
        $robots_directives[] = 'max-image-preview:large';
        $robots_directives[] = 'max-video-preview:-1';
        $robots_str = implode(', ', $robots_directives);

        $pub_time = $target_post ? get_the_date('c', $target_post) : '';
        $mod_time = $target_post ? get_the_modified_date('c', $target_post) : '';

        // Yoast Schema Graph
        $author_name = $target_post ? (get_the_author_meta('display_name', $target_post->post_author) ?: 'Editorial Team') : 'Editorial Team';
        $schema_graph = array(
            '@context' => 'https://schema.org',
            '@graph'   => array(
                array(
                    '@type'       => 'WebSite',
                    '@id'         => rtrim($frontend_url, '/') . '/#website',
                    'url'         => rtrim($frontend_url, '/') . '/',
                    'name'        => $site_name,
                    'description' => $site_desc,
                    'inLanguage'  => get_locale(),
                ),
                array(
                    '@type'              => 'WebPage',
                    '@id'                => $canonical . '#webpage',
                    'url'                => $canonical,
                    'name'               => $title,
                    'isPartOf'           => array('@id' => rtrim($frontend_url, '/') . '/#website'),
                    'datePublished'      => $pub_time,
                    'dateModified'       => $mod_time,
                    'description'        => $desc,
                    'breadcrumb'         => array('@id' => $canonical . '#breadcrumb'),
                    'inLanguage'         => get_locale(),
                ),
            ),
        );

        if (!empty($og_img)) {
            $schema_graph['@graph'][] = array(
                '@type'      => 'ImageObject',
                '@id'        => $canonical . '#primaryimage',
                'inLanguage' => get_locale(),
                'url'        => $og_img,
                'contentUrl' => $og_img,
            );
        }

        if ($target_post && $target_post->post_type === 'post') {
            $article_schema = array(
                '@type'            => 'Article',
                '@id'              => $canonical . '#article',
                'isPartOf'         => array('@id' => $canonical . '#webpage'),
                'author'           => array(
                    '@type' => 'Person',
                    'name'  => $author_name,
                ),
                'headline'         => $title,
                'datePublished'    => $pub_time,
                'dateModified'     => $mod_time,
                'mainEntityOfPage' => array('@id' => $canonical . '#webpage'),
                'publisher'        => array('@id' => rtrim($frontend_url, '/') . '/#website'),
                'inLanguage'       => get_locale(),
            );
            if (!empty($og_img)) {
                $article_schema['image'] = array('@id' => $canonical . '#primaryimage');
            }
            $schema_graph['@graph'][] = $article_schema;
        }

        return $this->assemble_head_tags(array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => $robots_str,
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $og_title,
            'og_description'         => $og_desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $site_name,
            'article_published_time' => $pub_time,
            'article_modified_time'  => $mod_time,
            'og_image'               => $og_img,
            'twitter_card'           => 'summary_large_image',
            'twitter_title'          => $tw_title,
            'twitter_description'    => $tw_desc,
            'twitter_image'          => $tw_img,
            'json_ld'                => $schema_graph,
            'schema_class'           => 'yoast-schema-graph',
        ));
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
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $post_id      = $target_post ? $target_post->ID : 0;

        $title = '';
        $desc  = '';
        $robots_directives = array();

        // 1. Try Rank Math Singular Paper if class exists
        if (class_exists('\RankMath\Paper\Singular') && $target_post) {
            try {
                $singular = new \RankMath\Paper\Singular();
                $singular->set_object($target_post);
                $title = (string) $singular->title();
                $desc  = (string) $singular->description();
                $rob   = $singular->robots();
                if (is_array($rob)) {
                    $robots_directives = array_keys(array_filter($rob));
                }
            } catch (\Throwable $e) {}
        }

        // 2. Direct PostMeta Resolution for Rank Math Custom Overrides
        if ($post_id) {
            if (empty($title)) {
                $raw_title = get_post_meta($post_id, 'rank_math_title', true);
                if (!empty($raw_title)) {
                    if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
                        $title = \RankMath\Helper::replace_vars($raw_title, $target_post);
                    } else {
                        $title = $this->resolve_seo_template_vars($raw_title, $target_post);
                    }
                }
            }

            if (empty($desc)) {
                $raw_desc = get_post_meta($post_id, 'rank_math_description', true);
                if (!empty($raw_desc)) {
                    if (class_exists('\RankMath\Helper') && method_exists('\RankMath\Helper', 'replace_vars')) {
                        $desc = \RankMath\Helper::replace_vars($raw_desc, $target_post);
                    } else {
                        $desc = $this->resolve_seo_template_vars($raw_desc, $target_post);
                    }
                }
            }
        }

        // 3. Fallback defaults if still empty
        if (empty($title)) {
            $title = $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name;
        }
        if (empty($desc) && $target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        // Canonical
        $raw_canon = $post_id ? get_post_meta($post_id, 'rank_math_canonical_url', true) : '';
        $canonical = $raw_canon ?: ($full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url));
        $canonical = str_replace($this->get_origin_hosts(), $frontend_url, $canonical);

        // Featured Image
        $thumb_img = '';
        if ($post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $thumb_img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }
        $thumb_img = $thumb_img ? str_replace($this->get_origin_hosts(), $frontend_url, $thumb_img) : '';

        // OpenGraph
        $og_title = ($post_id ? get_post_meta($post_id, 'rank_math_facebook_title', true) : '') ?: $title;
        $og_desc  = ($post_id ? get_post_meta($post_id, 'rank_math_facebook_description', true) : '') ?: $desc;
        $og_img   = ($post_id ? get_post_meta($post_id, 'rank_math_facebook_image', true) : '') ?: $thumb_img;
        $og_img   = $og_img ? str_replace($this->get_origin_hosts(), $frontend_url, $og_img) : '';

        // Twitter
        $tw_title = ($post_id ? get_post_meta($post_id, 'rank_math_twitter_title', true) : '') ?: $og_title;
        $tw_desc  = ($post_id ? get_post_meta($post_id, 'rank_math_twitter_description', true) : '') ?: $og_desc;
        $tw_img   = ($post_id ? get_post_meta($post_id, 'rank_math_twitter_image', true) : '') ?: $og_img;
        $tw_img   = $tw_img ? str_replace($this->get_origin_hosts(), $frontend_url, $tw_img) : '';
        $tw_card  = ($post_id ? get_post_meta($post_id, 'rank_math_twitter_card_type', true) : '') ?: 'summary_large_image';

        // Robots
        if (empty($robots_directives) && $post_id) {
            $raw_robots = get_post_meta($post_id, 'rank_math_robots', true);
            if (is_string($raw_robots) && strpos($raw_robots, 'a:') === 0) {
                $raw_robots = maybe_unserialize($raw_robots);
            }
            if (is_array($raw_robots)) {
                $robots_directives = array_filter($raw_robots);
            }
        }
        if (empty($robots_directives)) {
            $robots_directives = array('index', 'follow', 'max-image-preview:large');
        }
        $robots_str = implode(', ', array_unique($robots_directives));

        $pub_time = $target_post ? get_the_date('c', $target_post) : '';
        $mod_time = $target_post ? get_the_modified_date('c', $target_post) : '';

        return $this->assemble_head_tags(array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => $robots_str,
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $og_title,
            'og_description'         => $og_desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $site_name,
            'article_published_time' => $pub_time,
            'article_modified_time'  => $mod_time,
            'og_image'               => $og_img,
            'twitter_card'           => $tw_card,
            'twitter_title'          => $tw_title,
            'twitter_description'    => $tw_desc,
            'twitter_image'          => $tw_img,
        ));
    }

    /**
     * Render SEOPress head HTML for a post or page
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_seopress_head($target_post, $full_url) {
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $post_id      = $target_post ? $target_post->ID : 0;

        $title = '';
        if (function_exists('seopress_titles_title')) {
            $title = seopress_titles_title();
        }
        if (empty($title) && $post_id) {
            $title = get_post_meta($post_id, '_seopress_titles_title', true);
        }
        if (empty($title)) {
            $title = $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name;
        }

        $desc = '';
        if (function_exists('seopress_titles_desc')) {
            $desc = seopress_titles_desc();
        }
        if (empty($desc) && $post_id) {
            $desc = get_post_meta($post_id, '_seopress_titles_desc', true);
        }
        if (empty($desc) && $target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        $canonical = '';
        if (function_exists('seopress_titles_canonical_url')) {
            $canonical = seopress_titles_canonical_url();
        }
        if (empty($canonical) && $post_id) {
            $canonical = get_post_meta($post_id, '_seopress_titles_canonical', true);
        }
        if (empty($canonical)) {
            $canonical = $full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url);
        }
        $canonical = str_replace($this->get_origin_hosts(), $frontend_url, $canonical);

        $og_title = '';
        if (function_exists('seopress_social_fb_title')) {
            $og_title = seopress_social_fb_title();
        }
        if (empty($og_title) && $post_id) {
            $og_title = get_post_meta($post_id, '_seopress_social_fb_title', true);
        }
        if (empty($og_title)) {
            $og_title = $title;
        }

        $og_desc = '';
        if (function_exists('seopress_social_fb_desc')) {
            $og_desc = seopress_social_fb_desc();
        }
        if (empty($og_desc) && $post_id) {
            $og_desc = get_post_meta($post_id, '_seopress_social_fb_desc', true);
        }
        if (empty($og_desc)) {
            $og_desc = $desc;
        }

        $og_img = '';
        if (function_exists('seopress_social_fb_img')) {
            $og_img = seopress_social_fb_img();
        }
        if (empty($og_img) && $post_id) {
            $og_img = get_post_meta($post_id, '_seopress_social_fb_img', true);
        }
        if (empty($og_img) && $post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $og_img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }
        $og_img = $og_img ? str_replace($this->get_origin_hosts(), $frontend_url, $og_img) : '';

        $tw_title = '';
        if (function_exists('seopress_social_twitter_title')) {
            $tw_title = seopress_social_twitter_title();
        }
        if (empty($tw_title) && $post_id) {
            $tw_title = get_post_meta($post_id, '_seopress_social_twitter_title', true);
        }
        if (empty($tw_title)) {
            $tw_title = $og_title;
        }

        $tw_desc = '';
        if (function_exists('seopress_social_twitter_desc')) {
            $tw_desc = seopress_social_twitter_desc();
        }
        if (empty($tw_desc) && $post_id) {
            $tw_desc = get_post_meta($post_id, '_seopress_social_twitter_desc', true);
        }
        if (empty($tw_desc)) {
            $tw_desc = $og_desc;
        }

        $tw_img = '';
        if (function_exists('seopress_social_twitter_img')) {
            $tw_img = seopress_social_twitter_img();
        }
        if (empty($tw_img) && $post_id) {
            $tw_img = get_post_meta($post_id, '_seopress_social_twitter_img', true);
        }
        if (empty($tw_img)) {
            $tw_img = $og_img;
        }
        $tw_img = $tw_img ? str_replace($this->get_origin_hosts(), $frontend_url, $tw_img) : '';

        $tw_card = $post_id ? (get_post_meta($post_id, '_seopress_social_twitter_card', true) ?: 'summary_large_image') : 'summary_large_image';

        $robots = array();
        if ($post_id) {
            $noindex = get_post_meta($post_id, '_seopress_titles_robots_index', true);
            $nofollow = get_post_meta($post_id, '_seopress_titles_robots_follow', true);
            $robots[] = ($noindex === 'yes') ? 'noindex' : 'index';
            $robots[] = ($nofollow === 'yes') ? 'nofollow' : 'follow';
        } else {
            $robots = array('index', 'follow');
        }
        $robots_str = implode(', ', $robots);

        return $this->assemble_head_tags(array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => $robots_str,
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $og_title,
            'og_description'         => $og_desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $site_name,
            'article_published_time' => $target_post ? get_the_date('c', $target_post) : '',
            'article_modified_time'  => $target_post ? get_the_modified_date('c', $target_post) : '',
            'og_image'               => $og_img,
            'twitter_card'           => $tw_card,
            'twitter_title'          => $tw_title,
            'twitter_description'    => $tw_desc,
            'twitter_image'          => $tw_img,
        ));
    }

    /**
     * Render All in One SEO (AIOSEO) head HTML for a post or page
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_aioseo_head($target_post, $full_url) {
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $post_id      = $target_post ? $target_post->ID : 0;

        $aioseo_meta = null;
        if (function_exists('aioseo') && isset(aioseo()->meta->metaData) && $target_post) {
            try {
                $aioseo_meta = aioseo()->meta->metaData->getMetaData($target_post);
            } catch (\Throwable $e) {
                $aioseo_meta = null;
            }
        }

        $read_meta = function($prop) use ($aioseo_meta) {
            if (!$aioseo_meta) return null;
            if (is_object($aioseo_meta)) {
                if (isset($aioseo_meta->$prop)) return $aioseo_meta->$prop;
                if (method_exists($aioseo_meta, 'get') && $aioseo_meta->get($prop)) return $aioseo_meta->get($prop);
            } elseif (is_array($aioseo_meta) && isset($aioseo_meta[$prop])) {
                return $aioseo_meta[$prop];
            }
            return null;
        };

        // Title
        $title = $read_meta('title');
        if (empty($title) && $post_id) {
            $title = get_post_meta($post_id, '_aioseo_title', true);
        }
        if (empty($title)) {
            $title = $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name;
        }

        // Description
        $desc = $read_meta('description');
        if (empty($desc) && $post_id) {
            $desc = get_post_meta($post_id, '_aioseo_description', true);
        }
        if (empty($desc) && $target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        // Canonical
        $canonical = $read_meta('canonicalUrl') ?: $read_meta('canonical_url');
        if (empty($canonical) && $post_id) {
            $canonical = get_post_meta($post_id, '_aioseo_canonical_url', true);
        }
        if (empty($canonical)) {
            $canonical = $full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url);
        }
        $canonical = str_replace($this->get_origin_hosts(), $frontend_url, $canonical);

        // OpenGraph
        $og_title = $read_meta('og_title') ?: ($post_id ? get_post_meta($post_id, '_aioseo_og_title', true) : '') ?: $title;
        $og_desc  = $read_meta('og_description') ?: ($post_id ? get_post_meta($post_id, '_aioseo_og_description', true) : '') ?: $desc;
        $og_img   = $read_meta('og_image_custom_url') ?: $read_meta('og_image_url') ?: ($post_id ? get_post_meta($post_id, '_aioseo_og_image_custom_url', true) : '');
        if (empty($og_img) && $post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $og_img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }
        $og_img = $og_img ? str_replace($this->get_origin_hosts(), $frontend_url, $og_img) : '';

        // Twitter
        $tw_title = $read_meta('twitter_title') ?: ($post_id ? get_post_meta($post_id, '_aioseo_twitter_title', true) : '') ?: $og_title;
        $tw_desc  = $read_meta('twitter_description') ?: ($post_id ? get_post_meta($post_id, '_aioseo_twitter_description', true) : '') ?: $og_desc;
        $tw_img   = $read_meta('twitter_image_custom_url') ?: $read_meta('twitter_image_url') ?: ($post_id ? get_post_meta($post_id, '_aioseo_twitter_image_custom_url', true) : '') ?: $og_img;
        $tw_img   = $tw_img ? str_replace($this->get_origin_hosts(), $frontend_url, $tw_img) : '';
        $tw_card  = $read_meta('twitter_card') ?: ($post_id ? get_post_meta($post_id, '_aioseo_twitter_card', true) : '') ?: 'summary_large_image';

        return $this->assemble_head_tags(array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => 'index, follow, max-image-preview:large',
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $og_title,
            'og_description'         => $og_desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $site_name,
            'article_published_time' => $target_post ? get_the_date('c', $target_post) : '',
            'article_modified_time'  => $target_post ? get_the_modified_date('c', $target_post) : '',
            'og_image'               => $og_img,
            'twitter_card'           => $tw_card,
            'twitter_title'          => $tw_title,
            'twitter_description'    => $tw_desc,
            'twitter_image'          => $tw_img,
        ));
    }

    /**
     * Render native fallback head HTML when no third-party SEO plugin is active
     *
     * @param WP_Post|null $target_post
     * @param string $full_url
     * @return string
     */
    protected function render_native_head($target_post, $full_url) {
        $frontend_url = $this->core->get_frontend_url();
        $site_name    = get_bloginfo('name') ?: 'AstroPress';
        $post_id      = $target_post ? $target_post->ID : 0;

        $title = $target_post ? $target_post->post_title . ' — ' . $site_name : $site_name;
        $desc  = '';
        if ($target_post) {
            $desc = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 25);
        }

        $canonical = $full_url ?: ($target_post ? get_permalink($target_post) : $frontend_url);
        $canonical = str_replace($this->get_origin_hosts(), $frontend_url, $canonical);

        $img = '';
        if ($post_id) {
            $thumb_id = get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $img = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
            }
        }
        $img = $img ? str_replace($this->get_origin_hosts(), $frontend_url, $img) : '';

        return $this->assemble_head_tags(array(
            'title'                  => $title,
            'description'            => $desc,
            'robots'                 => 'index, follow, max-image-preview:large',
            'canonical'              => $canonical,
            'og_locale'              => get_locale(),
            'og_type'                => ($target_post && $target_post->post_type === 'post') ? 'article' : 'website',
            'og_title'               => $title,
            'og_description'         => $desc,
            'og_url'                 => $canonical,
            'og_site_name'           => $site_name,
            'article_published_time' => $target_post ? get_the_date('c', $target_post) : '',
            'article_modified_time'  => $target_post ? get_the_modified_date('c', $target_post) : '',
            'og_image'               => $img,
            'twitter_card'           => 'summary_large_image',
            'twitter_title'          => $title,
            'twitter_description'    => $desc,
            'twitter_image'          => $img,
        ));
    }

    /**
     * Resolve template variables (%title%, %currentyear%, %sep%, %author%, etc.)
     *
     * @param string $template
     * @param int|WP_Post $post_obj
     * @return string
     */
    public function resolve_seo_template_vars($template, $post_obj) {
        $target_post = is_numeric($post_obj) ? get_post($post_obj) : $post_obj;
        $title = $target_post ? $target_post->post_title : '';
        $site_name = get_bloginfo('name') ?: 'AstroPress';

        if (empty($template) || !is_string($template)) {
            return $title;
        }

        $stripped = trim(preg_replace('/[\(\)\[\]\-\—\s\|]/', '', $template));
        if (empty($stripped)) {
            return $title;
        }

        $excerpt = '';
        $author_name = '';
        $focuskw = '';
        if ($target_post) {
            $excerpt = !empty($target_post->post_excerpt)
                ? wp_strip_all_tags($target_post->post_excerpt)
                : wp_trim_words(wp_strip_all_tags($target_post->post_content), 30);
            $author_name = get_the_author_meta('display_name', $target_post->post_author) ?: '';
            $focuskw = get_post_meta($target_post->ID, 'rank_math_focus_keyword', true)
                ?: get_post_meta($target_post->ID, '_yoast_wpseo_focuskw', true)
                ?: '';
        }

        $cats = $target_post ? get_the_category($target_post->ID) : array();
        $cat_name = (!empty($cats) && !is_wp_error($cats)) ? $cats[0]->name : '';

        $resolved = str_ireplace(
            array(
                '%title%',
                '%currentyear%',
                '%currentmonth%',
                '%currentday%',
                '%sep%',
                '%sitename%',
                '%sitedesc%',
                '%page%',
                '%category%',
                '%excerpt%',
                '%focuskw%',
                '%author%',
                '%date%',
                '%modified%',
            ),
            array(
                $title,
                date('Y'),
                date('F'),
                date('j'),
                '—',
                $site_name,
                get_bloginfo('description') ?: '',
                '',
                $cat_name,
                $excerpt,
                $focuskw,
                $author_name,
                $target_post ? get_the_date('', $target_post) : '',
                $target_post ? get_the_modified_date('', $target_post) : '',
            ),
            $template
        );

        $resolved = preg_replace('/%[a-z0-9_-]+%/i', '', $resolved);
        $resolved = trim(preg_replace('/\s+/', ' ', $resolved));

        $test_without_punct = trim(str_ireplace($site_name, '', $resolved));
        $test_without_punct = trim(preg_replace('/[\(\)\[\]\-\—\s\|\:\,]/', '', $test_without_punct));

        if (empty($test_without_punct)) {
            return $title;
        }

        return $resolved;
    }

    /**
     * Resolve template variables for taxonomy terms
     *
     * @param string $template
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

        $resolved = str_ireplace(
            array(
                '%category%',
                '%tag%',
                '%term%',
                '%title%',
                '%term_description%',
                '%sitename%',
                '%sitedesc%',
                '%currentyear%',
                '%currentmonth%',
                '%currentday%',
                '%sep%',
                '%page%',
            ),
            array(
                $term_name,
                $term_name,
                $term_name,
                $term_name,
                $term_desc,
                $site_name,
                get_bloginfo('description') ?: '',
                date('Y'),
                date('F'),
                date('j'),
                '—',
                '',
            ),
            $template
        );

        $resolved = preg_replace('/%[a-z0-9_-]+%/i', '', $resolved);
        $resolved = trim(preg_replace('/\s+/', ' ', $resolved));

        $test_without_punct = trim(str_ireplace($site_name, '', $resolved));
        $test_without_punct = trim(preg_replace('/[\(\)\[\]\-\—\s\|\:\,]/', '', $test_without_punct));

        if (empty($test_without_punct)) {
            return $term_name;
        }

        return $resolved;
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

        $target_post = get_post($post_id);
        $frontend_url = $this->core->get_frontend_url();

        // Check metadata across Rank Math, Yoast SEO, SEOPress, and AIOSEO
        $raw_title     = get_post_meta($post_id, 'rank_math_title', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_title', true)
            ?: get_post_meta($post_id, '_seopress_titles_title', true)
            ?: get_post_meta($post_id, '_aioseo_title', true);

        $raw_desc      = get_post_meta($post_id, 'rank_math_description', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_metadesc', true)
            ?: get_post_meta($post_id, '_seopress_titles_desc', true)
            ?: get_post_meta($post_id, '_aioseo_description', true);

        $raw_canonical = get_post_meta($post_id, 'rank_math_canonical_url', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_canonical', true)
            ?: get_post_meta($post_id, '_seopress_titles_canonical', true)
            ?: get_post_meta($post_id, '_aioseo_canonical_url', true);

        $raw_og_title  = get_post_meta($post_id, 'rank_math_facebook_title', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true)
            ?: get_post_meta($post_id, '_seopress_social_fb_title', true)
            ?: get_post_meta($post_id, '_aioseo_og_title', true);

        $raw_og_desc   = get_post_meta($post_id, 'rank_math_facebook_description', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true)
            ?: get_post_meta($post_id, '_seopress_social_fb_desc', true)
            ?: get_post_meta($post_id, '_aioseo_og_description', true);

        $raw_tw_title  = get_post_meta($post_id, 'rank_math_twitter_title', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_twitter-title', true)
            ?: get_post_meta($post_id, '_seopress_social_twitter_title', true)
            ?: get_post_meta($post_id, '_aioseo_twitter_title', true);

        $raw_tw_desc   = get_post_meta($post_id, 'rank_math_twitter_description', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_twitter-description', true)
            ?: get_post_meta($post_id, '_seopress_social_twitter_desc', true)
            ?: get_post_meta($post_id, '_aioseo_twitter_description', true);

        $raw_robots    = get_post_meta($post_id, 'rank_math_robots', true);
        $raw_adv_rob   = get_post_meta($post_id, 'rank_math_advanced_robots', true);

        // Robots normalization
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

        $primary_cat_id = (int) get_post_meta($post_id, 'rank_math_primary_category', true)
            ?: (int) get_post_meta($post_id, '_yoast_wpseo_primary_category', true)
            ?: null;
        $breadcrumb_title = get_post_meta($post_id, 'rank_math_breadcrumb_title', true)
            ?: get_post_meta($post_id, '_yoast_wpseo_bctitle', true)
            ?: null;
        $pillar_content = get_post_meta($post_id, 'rank_math_pillar_content', true) === 'on'
            || get_post_meta($post_id, '_yoast_wpseo_is_cornerstone', true) === '1';

        $canonical = $raw_canonical ? str_replace($this->get_origin_hosts(), $frontend_url, $raw_canonical) : null;

        // Rank Math Redirection detection
        $redirect_to = get_post_meta($post_id, 'rank_math_redirection_url', true);
        $redirect_code = get_post_meta($post_id, 'rank_math_redirection_type', true) ?: '301';

        global $wpdb;
        if (!empty($wpdb) && !empty($wpdb->prefix)) {
            $table_name = $wpdb->prefix . 'rank_math_redirections';
            if (empty($redirect_to) && $target_post && $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $slug = ($target_post->post_type === 'page') ? get_page_uri($target_post->ID) : $target_post->post_name;
                if ($slug) {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT sources, url_to, header_code FROM {$table_name} WHERE status = 'active' AND sources LIKE %s ORDER BY id DESC LIMIT 10",
                        '%' . $wpdb->esc_like($target_post->post_name) . '%'
                    ), ARRAY_A);
                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $sources = maybe_unserialize($row['sources']);
                            if (is_array($sources)) {
                                foreach ($sources as $source_item) {
                                    $pattern = trim(isset($source_item['pattern']) ? $source_item['pattern'] : '', '/');
                                    $comparison = isset($source_item['comparison']) ? $source_item['comparison'] : 'exact';
                                    if ($comparison === 'exact' && strcasecmp($pattern, $slug) === 0) {
                                        $redirect_to = $row['url_to'];
                                        $redirect_code = $row['header_code'] ?: '301';
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return array(
            'engine_used'         => $this->detect_active_seo_engine(),
            'title'               => $this->resolve_seo_template_vars($raw_title, $target_post) ?: ($target_post ? $target_post->post_title : null),
            'description'         => $this->resolve_seo_template_vars($raw_desc, $target_post) ?: null,
            'canonical_url'       => $canonical,
            'focus_keyword'       => get_post_meta($post_id, 'rank_math_focus_keyword', true)
                ?: get_post_meta($post_id, '_yoast_wpseo_focuskw', true)
                ?: get_post_meta($post_id, '_seopress_analysis_target_kw', true)
                ?: null,
            'robots'              => !empty($robots_list) ? $robots_list : null,
            'og_title'            => $this->resolve_seo_template_vars($raw_og_title, $target_post) ?: null,
            'og_description'      => $this->resolve_seo_template_vars($raw_og_desc, $target_post) ?: null,
            'og_image'            => get_post_meta($post_id, 'rank_math_facebook_image', true)
                ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true)
                ?: get_post_meta($post_id, '_seopress_social_fb_img', true)
                ?: get_post_meta($post_id, '_aioseo_og_image_custom_url', true)
                ?: null,
            'twitter_title'       => $this->resolve_seo_template_vars($raw_tw_title, $target_post) ?: null,
            'twitter_desc'        => $this->resolve_seo_template_vars($raw_tw_desc, $target_post) ?: null,
            'twitter_image'       => get_post_meta($post_id, 'rank_math_twitter_image', true)
                ?: get_post_meta($post_id, '_yoast_wpseo_twitter-image', true)
                ?: get_post_meta($post_id, '_seopress_social_twitter_img', true)
                ?: get_post_meta($post_id, '_aioseo_twitter_image_custom_url', true)
                ?: null,
            'primary_category_id' => $primary_cat_id,
            'breadcrumb_title'    => $breadcrumb_title,
            'pillar_content'      => $pillar_content,
            'redirect_url'        => $redirect_to ? str_replace($this->get_origin_hosts(), $frontend_url, $redirect_to) : null,
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

        $frontend_url = $this->core->get_frontend_url();

        $raw_title     = get_term_meta($term_id, 'rank_math_title', true);
        $raw_desc      = get_term_meta($term_id, 'rank_math_description', true);
        $raw_canonical = get_term_meta($term_id, 'rank_math_canonical_url', true);
        $raw_robots    = get_term_meta($term_id, 'rank_math_robots', true);
        $raw_adv_rob   = get_term_meta($term_id, 'rank_math_advanced_robots', true);
        $raw_focuskw   = get_term_meta($term_id, 'rank_math_focus_keyword', true);
        $raw_og_title  = get_term_meta($term_id, 'rank_math_facebook_title', true);
        $raw_og_desc   = get_term_meta($term_id, 'rank_math_facebook_description', true);
        $raw_og_image  = get_term_meta($term_id, 'rank_math_facebook_image', true);
        $raw_tw_title  = get_term_meta($term_id, 'rank_math_twitter_title', true);
        $raw_tw_desc   = get_term_meta($term_id, 'rank_math_twitter_description', true);
        $raw_tw_image  = get_term_meta($term_id, 'rank_math_twitter_image', true);

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

        $canonical = $raw_canonical ? str_replace($this->get_origin_hosts(), $frontend_url, $raw_canonical) : null;

        // Check Rank Math redirections table for exact term path match
        $redirect_to = null;
        $redirect_code = '301';
        global $wpdb;
        if (!empty($wpdb) && !empty($wpdb->prefix)) {
            $table_name = $wpdb->prefix . 'rank_math_redirections';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
                $term_path = trim($this->core->cache->get_term_path($term), '/');
                $term_slug = trim($term->slug, '/');
                $category_prefix_path = 'category/' . $term_slug;

                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT sources, url_to, header_code FROM {$table_name} WHERE status = 'active' AND (sources LIKE %s OR sources LIKE %s) ORDER BY id DESC LIMIT 20",
                    '%' . $wpdb->esc_like($term_slug) . '%',
                    '%' . $wpdb->esc_like($category_prefix_path) . '%'
                ), ARRAY_A);

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $sources = maybe_unserialize($row['sources']);
                        if (is_array($sources)) {
                            foreach ($sources as $source_item) {
                                $pattern = trim(isset($source_item['pattern']) ? $source_item['pattern'] : '', '/');
                                $comparison = isset($source_item['comparison']) ? $source_item['comparison'] : 'exact';
                                if ($comparison === 'exact' && (
                                    strcasecmp($pattern, $term_slug) === 0 ||
                                    strcasecmp($pattern, $term_path) === 0 ||
                                    strcasecmp($pattern, $category_prefix_path) === 0
                                )) {
                                    $redirect_to = $row['url_to'];
                                    $redirect_code = $row['header_code'] ?: '301';
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array(
            'title'          => $this->resolve_term_seo_template_vars($raw_title, $term) ?: null,
            'description'    => $this->resolve_term_seo_template_vars($raw_desc, $term) ?: ($term->description ?: null),
            'canonical_url'  => $canonical,
            'focus_keyword'  => $raw_focuskw ?: null,
            'robots'         => !empty($robots_list) ? $robots_list : null,
            'og_title'       => $this->resolve_term_seo_template_vars($raw_og_title, $term) ?: null,
            'og_description' => $this->resolve_term_seo_template_vars($raw_og_desc, $term) ?: null,
            'og_image'       => $raw_og_image ? str_replace($this->get_origin_hosts(), $frontend_url, $raw_og_image) : null,
            'twitter_title'  => $this->resolve_term_seo_template_vars($raw_tw_title, $term) ?: null,
            'twitter_desc'   => $this->resolve_term_seo_template_vars($raw_tw_desc, $term) ?: null,
            'twitter_image'  => $raw_tw_image ? str_replace($this->get_origin_hosts(), $frontend_url, $raw_tw_image) : null,
            'redirect_url'   => $redirect_to ? str_replace($this->get_origin_hosts(), $frontend_url, $redirect_to) : null,
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
