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
     * Get rendered Rank Math / Yoast head HTML for a post or page
     * Persistently cached in post meta to avoid executing heavy filters repeatedly.
     *
     * @param array|int $post_arr
     * @return string|null
     */
    public function get_rank_math_head($post_arr) {
        $post_id = is_array($post_arr) ? ($post_arr['id'] ?? 0) : $post_arr;
        if (!$post_id) {
            return null;
        }

        $frontend_url = $this->core->get_frontend_url();

        // Check persistent post meta cache first (instant microsecond lookup)
        $cached_head = get_post_meta($post_id, '_astropress_rm_head', true);
        if (empty($cached_head)) {
            $cached_head = get_post_meta($post_id, '_headless_rm_head', true);
        }

        if (!empty($cached_head)) {
            return str_replace($this->get_origin_hosts(), $frontend_url, $cached_head);
        }

        $target_post = get_post($post_id);
        if (!$target_post) {
            return null;
        }

        $orig_post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = $target_post;
        setup_postdata($target_post);

        $head = '';
        if (class_exists('\RankMath\Paper\Paper')) {
            ob_start();
            do_action('rank_math/head');
            $head = ob_get_clean();
        } elseif (function_exists('wp_head')) {
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

        $clean_head = !empty($head) ? trim($head) : null;
        if ($clean_head) {
            $clean_head = str_replace($this->get_origin_hosts(), $frontend_url, $clean_head);
            update_post_meta($post_id, '_astropress_rm_head', $clean_head);
            update_post_meta($post_id, '_headless_rm_head', $clean_head);
        }

        return $clean_head;
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

        // Check Rank Math metadata
        $raw_title     = get_post_meta($post_id, 'rank_math_title', true) ?: get_post_meta($post_id, '_yoast_wpseo_title', true);
        $raw_desc      = get_post_meta($post_id, 'rank_math_description', true) ?: get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $raw_canonical = get_post_meta($post_id, 'rank_math_canonical_url', true) ?: get_post_meta($post_id, '_yoast_wpseo_canonical', true);
        $raw_og_title  = get_post_meta($post_id, 'rank_math_facebook_title', true) ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
        $raw_og_desc   = get_post_meta($post_id, 'rank_math_facebook_description', true) ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
        $raw_tw_title  = get_post_meta($post_id, 'rank_math_twitter_title', true) ?: get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
        $raw_tw_desc   = get_post_meta($post_id, 'rank_math_twitter_description', true) ?: get_post_meta($post_id, '_yoast_wpseo_twitter-description', true);
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
        $table_name = $wpdb->prefix . 'rank_math_redirections';
        if (empty($redirect_to) && $target_post && $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $slug = $target_post->post_name;
            if ($slug) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT url_to, header_code FROM {$table_name} WHERE status = 'active' AND (object_id = %d OR sources LIKE %s) ORDER BY id DESC LIMIT 1",
                    $post_id,
                    '%' . $wpdb->esc_like($slug) . '%'
                ), ARRAY_A);
                if ($row && !empty($row['url_to'])) {
                    $redirect_to = $row['url_to'];
                    $redirect_code = $row['header_code'] ?: '301';
                }
            }
        }

        return array(
            'title'               => $this->resolve_seo_template_vars($raw_title, $target_post) ?: ($target_post ? $target_post->post_title : null),
            'description'         => $this->resolve_seo_template_vars($raw_desc, $target_post) ?: null,
            'canonical_url'       => $canonical,
            'focus_keyword'       => get_post_meta($post_id, 'rank_math_focus_keyword', true) ?: get_post_meta($post_id, '_yoast_wpseo_focuskw', true) ?: null,
            'robots'              => !empty($robots_list) ? $robots_list : null,
            'og_title'            => $this->resolve_seo_template_vars($raw_og_title, $target_post) ?: null,
            'og_description'      => $this->resolve_seo_template_vars($raw_og_desc, $target_post) ?: null,
            'og_image'            => get_post_meta($post_id, 'rank_math_facebook_image', true) ?: get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true) ?: null,
            'twitter_title'       => $this->resolve_seo_template_vars($raw_tw_title, $target_post) ?: null,
            'twitter_desc'        => $this->resolve_seo_template_vars($raw_tw_desc, $target_post) ?: null,
            'twitter_image'       => get_post_meta($post_id, 'rank_math_twitter_image', true) ?: get_post_meta($post_id, '_yoast_wpseo_twitter-image', true) ?: null,
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

        // Check Rank Math redirections table for term slug
        $redirect_to = null;
        $redirect_code = '301';
        global $wpdb;
        $table_name = $wpdb->prefix . 'rank_math_redirections';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $slug = $term->slug;
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT url_to, header_code FROM {$table_name} WHERE status = 'active' AND (object_id = %d OR sources LIKE %s) ORDER BY id DESC LIMIT 1",
                $term_id,
                '%' . $wpdb->esc_like($slug) . '%'
            ), ARRAY_A);
            if ($row && !empty($row['url_to'])) {
                $redirect_to = $row['url_to'];
                $redirect_code = $row['header_code'] ?: '301';
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
