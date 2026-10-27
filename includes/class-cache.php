<?php
/**
 * AstroPress Cache Invalidation & Webhook Revalidation
 *
 * Provides automatic, granular Cloudflare Edge cache invalidation on content changes,
 * taxonomy updates, navigation menu modifications, and site branding edits.
 * Dispatches non-blocking webhook requests to Astro's /api/revalidate endpoint.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Cache {
    /**
     * Master Plugin Instance
     *
     * @var AstroPress
     */
    protected $core;

    public function __construct(AstroPress $core) {
        $this->core = $core;

        // Content Lifecycle Hooks: Granular automatic cache purging
        add_action('save_post', array($this, 'on_save_post'), 10, 3);
        add_action('wp_trash_post', array($this, 'on_trash_post'));
        add_action('untrash_post', array($this, 'on_trash_post'));

        // Taxonomy Lifecycle Hooks
        add_action('create_category', array($this, 'on_taxonomy_change'));
        add_action('edited_category', array($this, 'on_taxonomy_change'));
        add_action('delete_category', array($this, 'on_taxonomy_change'));
        add_action('create_post_tag', array($this, 'on_taxonomy_change'));
        add_action('edited_post_tag', array($this, 'on_taxonomy_change'));
        add_action('delete_post_tag', array($this, 'on_taxonomy_change'));

        // Navigation Menu & Site Branding updates
        add_action('wp_update_nav_menu', array($this, 'on_menu_change'));
        add_action('update_option_blogname', array($this, 'on_site_info_change'));
        add_action('update_option_blogdescription', array($this, 'on_site_info_change'));
    }

    /**
     * Compute canonical stripped URL path for taxonomy term
     */
    public function get_term_path($term) {
        if (is_numeric($term)) {
            $term = get_term($term);
        }
        if (!$term || is_wp_error($term)) {
            return '';
        }

        $link = get_term_link($term);
        if (!is_wp_error($link)) {
            $path = wp_parse_url($link, PHP_URL_PATH);
            if ($path) {
                return '/' . trim($path, '/');
            }
        }
        return ($term->taxonomy === 'category') ? '/' . $term->slug : '/tag/' . $term->slug;
    }

    /**
     * Trigger non-blocking revalidation request to Astro Cloudflare worker and warm edge cache
     *
     * @param string|null $slug
     * @param string $type
     * @param string $action
     * @param array $extra_urls
     * @return bool
     */
    public function dispatch_revalidation($slug = null, $type = 'post', $action = 'update', $extra_urls = array()) {
        $frontend_url = $this->core->get_frontend_url();
        $secret = $this->core->get_secret();

        if (empty($frontend_url) || empty($secret)) {
            return false;
        }

        $endpoint = rtrim($frontend_url, '/') . '/api/revalidate';

        $payload = array(
            'slug'   => $slug,
            'type'   => $type,
            'action' => $action,
            'urls'   => $extra_urls,
            'time'   => current_time('mysql'),
            'warm'   => true,
        );

        // Fire-and-forget non-blocking HTTP request
        wp_remote_post($endpoint, array(
            'method'      => 'POST',
            'timeout'     => 5,
            'blocking'    => false,
            'sslverify'   => true,
            'headers'     => array(
                'Content-Type'        => 'application/json',
                'Origin'              => $frontend_url,
                'Referer'             => $frontend_url,
                'x-revalidate-secret' => $secret,
            ),
            'body'        => wp_json_encode($payload),
            'data_format' => 'body',
        ));

        // Purge LiteSpeed Cache if plugin is active on WP and setting is enabled
        if ($this->core->should_sync_litespeed() && defined('LSCWP_V')) {
            do_action('litespeed_purge_all');
        }

        return true;
    }

    /**
     * Purge entire website edge cache on Astro
     */
    public function purge_entire_cache() {
        return $this->dispatch_revalidation(null, 'all', 'purge', array('/*'));
    }

    /**
     * Hook: On Post / Page Save & Publish (Granular Purging)
     */
    public function on_save_post($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Invalidate cached Rank Math head so changes take effect immediately
        delete_post_meta($post_id, '_astropress_rm_head');
        delete_post_meta($post_id, '_headless_rm_head');

        if ($post->post_status !== 'publish') return;
        if (!in_array($post->post_type, array('post', 'page'))) return;

        if (!$this->core->should_purge_on_save()) return;

        $extra_urls = array();
        if ($post->post_type === 'post') {
            // Category archives
            $categories = get_the_category($post_id);
            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $extra_urls[] = $this->get_term_path($cat);
                    if ($cat->parent) {
                        $parent = get_category($cat->parent);
                        if ($parent && !is_wp_error($parent)) {
                            $extra_urls[] = $this->get_term_path($parent);
                        }
                    }
                }
            }
            // Tag archives
            $tags = get_the_tags($post_id);
            if (!empty($tags) && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $extra_urls[] = '/tag/' . $tag->slug;
                }
            }
        }

        // Purge RSS feeds and sitemaps so feed readers & search engines see updates immediately
        if ($this->core->should_purge_feeds()) {
            $extra_urls[] = '/sitemap.xml';
            $extra_urls[] = '/sitemap_index.xml';
            $extra_urls[] = '/rss.xml';
            $extra_urls[] = '/feed.xml';
            $extra_urls[] = '/blogs';
            $extra_urls[] = '/blog';
        }

        $this->dispatch_revalidation($post->post_name, $post->post_type, $update ? 'update' : 'publish', array_unique(array_filter($extra_urls)));
    }

    /**
     * Hook: On Trash / Delete Post
     */
    public function on_trash_post($post_id) {
        delete_post_meta($post_id, '_astropress_rm_head');
        delete_post_meta($post_id, '_headless_rm_head');

        $post = get_post($post_id);
        if (!$post) return;
        if (!$this->core->should_purge_on_save()) return;

        $extra_urls = array();
        if ($post->post_type === 'post') {
            $categories = get_the_category($post_id);
            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $extra_urls[] = $this->get_term_path($cat);
                }
            }
            $tags = get_the_tags($post_id);
            if (!empty($tags) && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $extra_urls[] = '/tag/' . $tag->slug;
                }
            }
        }

        if ($this->core->should_purge_feeds()) {
            $extra_urls[] = '/sitemap.xml';
            $extra_urls[] = '/sitemap_index.xml';
            $extra_urls[] = '/rss.xml';
            $extra_urls[] = '/feed.xml';
            $extra_urls[] = '/blogs';
            $extra_urls[] = '/blog';
        }

        $this->dispatch_revalidation($post->post_name, $post->post_type, 'delete', array_unique(array_filter($extra_urls)));
    }

    /**
     * Hook: On Taxonomy (Category/Tag) Create, Edit, or Delete
     */
    public function on_taxonomy_change($term_id) {
        if (!$this->core->should_purge_taxonomy()) return;

        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) return;

        $path = $this->get_term_path($term);
        $this->dispatch_revalidation(null, 'taxonomy', 'update', array_filter(array(
            $path,
            '/blogs',
            '/blog',
            '/sitemap.xml',
            '/sitemap_index.xml',
            '/rss.xml',
            '/feed.xml',
        )));
    }

    /**
     * Hook: On Navigation Menu Change
     */
    public function on_menu_change($menu_id) {
        if (!$this->core->should_purge_menu()) return;
        $this->dispatch_revalidation(null, 'menu', 'update', array('/', '/blogs', '/blog', '/rss.xml'));
    }

    /**
     * Hook: On Site Info / Branding Change
     */
    public function on_site_info_change() {
        $this->dispatch_revalidation(null, 'site_info', 'update', array('/', '/blogs', '/blog', '/rss.xml', '/sitemap.xml'));
    }
}

// Backward Compatibility Class Alias
if (!class_exists('TopNepali_Headless_Cache')) {
    class_alias('AstroPress_Cache', 'TopNepali_Headless_Cache');
}
