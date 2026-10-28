<?php
/**
 * REST API Extensions & Edge Cache Delivery
 *
 * Exposes rendered head HTML, structured SEO metadata, estimated reading time,
 * dynamic redirection lookup, and 404 monitoring via REST API endpoints.
 * Injects public Edge Cache headers for Cloudflare CDN caching.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_REST {
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

        // REST API endpoints & query modifications
        add_action('rest_api_init', array($this, 'configure_rest_api'));
        add_filter('rest_post_dispatch', array($this, 'filter_rest_cache_headers'), 10, 3);
        add_filter('rest_post_query', array($this, 'filter_rest_category_children'), 10, 2);
    }

    /**
     * REST API Enhancements:
     * - Expose Rank Math rendered head & structured SEO metadata
     * - Register reading time calculation
     * - Expose custom endpoints (/head, /redirection, /404-log) under astropress/v1 and legacy headless/v1
     * - Block unauthenticated user enumeration
     */
    public function configure_rest_api() {
        // Enable Rank Math native REST API post meta and headless support
        add_filter('rank_math/rest/enable_post_meta', '__return_true');
        add_filter('rank_math/frontend/headless', '__return_true');

        // Expose rendered head HTML in /wp/v2/posts and /wp/v2/pages
        register_rest_field(array('post', 'page'), 'head', array(
            'get_callback' => array($this->core->seo, 'get_rank_math_head'),
            'schema'       => array(
                'description' => 'Rendered HTML head tags and JSON-LD schema',
                'type'        => 'string',
            ),
        ));

        // Expose structured SEO fields in /wp/v2/posts and /wp/v2/pages
        register_rest_field(array('post', 'page'), 'seo', array(
            'get_callback' => array($this->core->seo, 'get_rank_math_seo_fields'),
            'schema'       => array(
                'description' => 'Structured SEO metadata and social graph tags',
                'type'        => 'object',
            ),
        ));

        // Expose structured SEO fields in /wp/v2/categories and /wp/v2/tags
        register_rest_field(array('category', 'post_tag'), 'seo', array(
            'get_callback' => array($this->core->seo, 'get_rank_math_term_seo_fields'),
            'schema'       => array(
                'description' => 'Structured taxonomy SEO metadata',
                'type'        => 'object',
            ),
        ));

        // Expose dynamic reading time field in /wp/v2/posts and /wp/v2/pages
        register_rest_field(array('post', 'page'), 'reading_time', array(
            'get_callback' => array($this, 'get_reading_time'),
            'schema'       => array(
                'description' => 'Estimated reading time calculated server-side from full post content',
                'type'        => 'string',
            ),
        ));

        // Enable REST updating for Rank Math Redirection fields
        foreach (array('post', 'page') as $pt) {
            register_post_meta($pt, 'rank_math_redirection_url', array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => function() { return current_user_can('edit_posts'); },
            ));
            register_post_meta($pt, 'rank_math_redirection_type', array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => function() { return current_user_can('edit_posts'); },
            ));
        }

        // Register endpoints across current and legacy namespaces
        $namespaces = array('astropress/v1', 'headless/v1');

        foreach ($namespaces as $ns) {
            // Endpoint: /wp-json/{namespace}/head?slug=...
            register_rest_route($ns, '/head', array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_get_rendered_head'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_title',
                    ),
                ),
            ));

            // Endpoint: /wp-json/{namespace}/redirection?slug=...
            register_rest_route($ns, '/redirection', array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_get_redirection'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'slug' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ));

            // Endpoint: /wp-json/{namespace}/404-log
            register_rest_route($ns, '/404-log', array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_get_404_log'),
                'permission_callback' => array($this, 'check_404_log_permission'),
                'args'                => array(
                    'per_page' => array(
                        'default'           => 100,
                        'sanitize_callback' => 'absint',
                    ),
                    'page' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'orderby' => array(
                        'default'           => 'times_accessed',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'order' => array(
                        'default'           => 'DESC',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'search' => array(
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ));

            // Endpoint: /wp-json/{namespace}/verify-ticket?ticket=...
            register_rest_route($ns, '/verify-ticket', array(
                'methods'             => 'GET',
                'callback'            => array($this, 'rest_verify_revalidate_ticket'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'ticket' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ));
        }

        // Block unauthenticated user enumeration
        if (!is_user_logged_in()) {
            add_filter('rest_endpoints', function ($endpoints) {
                if (isset($endpoints['/wp/v2/users'])) {
                    unset($endpoints['/wp/v2/users']);
                }
                if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
                    unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
                }
                return $endpoints;
            });
        }
    }

    /**
     * Add public edge cache headers to unauthenticated REST API responses
     * Allows Cloudflare and LiteSpeed to cache WP REST responses, reducing API latency from ~1900ms to 20ms
     *
     * @param WP_REST_Response $response
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function filter_rest_cache_headers($response, $server, $request) {
        if (!is_a($response, 'WP_REST_Response')) {
            return $response;
        }

        // Only cache public GET requests (never cache authenticated, POST, PUT, or DELETE)
        if ($request->get_method() !== 'GET' || is_user_logged_in()) {
            return $response;
        }

        $route = $request->get_route();
        // Target posts, pages, categories, tags, media queries, and public AstroPress endpoints
        if (
            preg_match('#^/wp/v2/(posts|pages|categories|tags|media)#', $route) ||
            preg_match('#^/(astropress|headless)/v1/(head|redirection)#', $route)
        ) {
            $response->header('Cache-Control', 'public, max-age=60, s-maxage=120, stale-while-revalidate=300');
            $response->header('Cloudflare-CDN-Cache-Control', 'max-age=120, stale-while-revalidate=300');
            $response->header('X-AstroPress-Edge-Cache', 'ENABLED');
            $response->header('X-Headless-Edge-Cache', 'ENABLED');
        }

        return $response;
    }

    /**
     * Include child category posts in WordPress REST API queries, matching core WP_Query theme behavior
     *
     * @param array $args
     * @param WP_REST_Request $request
     * @return array
     */
    public function filter_rest_category_children($args, $request) {
        if (!empty($args['tax_query']) && is_array($args['tax_query'])) {
            foreach ($args['tax_query'] as $key => $clause) {
                if (is_array($clause) && isset($clause['taxonomy']) && $clause['taxonomy'] === 'category') {
                    $args['tax_query'][$key]['include_children'] = true;
                }
            }
        }
        return $args;
    }

    /**
     * Calculate dynamic reading time based on post content word count
     *
     * @param array|int $post_arr
     * @return string
     */
    public function get_reading_time($post_arr) {
        $post_id = is_array($post_arr) ? ($post_arr['id'] ?? 0) : $post_arr;
        if (!$post_id) {
            return '1 min read';
        }
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return '1 min read';
        }
        $clean = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $words = count(preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY));
        $minutes = max(1, (int) ceil($words / 200));
        return $minutes . ' min read';
    }

    /**
     * Dedicated REST Callback for /wp-json/{namespace}/head?slug=...
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_rendered_head($request) {
        $slug = $request->get_param('slug');
        $posts = get_posts(array(
            'name'        => $slug,
            'post_type'   => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => 1,
        ));

        if (empty($posts)) {
            return new WP_Error('not_found', 'Post or page not found', array('status' => 404));
        }

        $post = $posts[0];
        if (post_password_required($post) || !empty($post->post_password)) {
            return new WP_Error('post_password_required', 'This content is password protected.', array('status' => 403));
        }

        $head = $this->core->seo->get_rank_math_head(array('id' => $post->ID));
        $seo  = $this->core->seo->get_rank_math_seo_fields(array('id' => $post->ID));

        return rest_ensure_response(array(
            'id'   => $post->ID,
            'slug' => $post->post_name,
            'head' => $head,
            'seo'  => $seo,
        ));
    }

    /**
     * Dedicated REST Callback for /wp-json/{namespace}/redirection?slug=...
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_redirection($request) {
        $slug = trim($request->get_param('slug'), '/');
        if (empty($slug)) {
            return new WP_Error('invalid_slug', 'Missing slug parameter', array('status' => 400));
        }

        global $wpdb;
        $frontend_url = $this->core->get_frontend_url();
        $wp_hosts = $this->core->seo->get_origin_hosts();

        // 1. Check if matching post or page has redirect meta
        $post = get_page_by_path($slug, OBJECT, array('post', 'page'));
        if ($post) {
            $redirect_to = get_post_meta($post->ID, 'rank_math_redirection_url', true);
            $redirect_code = get_post_meta($post->ID, 'rank_math_redirection_type', true) ?: '301';
            if ($redirect_to) {
                return rest_ensure_response(array(
                    'redirect_url'  => str_replace($wp_hosts, $frontend_url, $redirect_to),
                    'redirect_type' => (int) $redirect_code,
                ));
            }
        }

        // 2. Check Rank Math redirections table with exact pattern matching
        $table_name = $wpdb->prefix . 'rank_math_redirections';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT sources, url_to, header_code FROM {$table_name} WHERE status = 'active' AND sources LIKE %s ORDER BY id DESC LIMIT 20",
                '%' . $wpdb->esc_like($slug) . '%'
            ), ARRAY_A);

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $sources = maybe_unserialize($row['sources']);
                    if (is_array($sources)) {
                        foreach ($sources as $source_item) {
                            $pattern = trim(isset($source_item['pattern']) ? $source_item['pattern'] : '', '/');
                            $comparison = isset($source_item['comparison']) ? $source_item['comparison'] : 'exact';
                            if ($comparison === 'exact' && strcasecmp($pattern, $slug) === 0) {
                                return rest_ensure_response(array(
                                    'redirect_url'  => str_replace($wp_hosts, $frontend_url, $row['url_to']),
                                    'redirect_type' => (int) ($row['header_code'] ?: 301),
                                ));
                            }
                        }
                    }
                }
            }
        }

        return new WP_Error('not_found', 'No redirection found for slug', array('status' => 404));
    }

    /**
     * Permission Callback for /wp-json/{namespace}/404-log
     * Enforces administrator capability or valid Cloudflare API Bearer token
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_404_log_permission($request) {
        if (current_user_can('manage_options')) {
            return true;
        }

        $token  = $this->core->get_cf_api_token();
        $secret = $this->core->get_secret();
        $auth_header = $request->get_header('authorization');
        $x_secret = $request->get_header('x-revalidate-secret') ?: $request->get_header('x-headless-secret');

        if (!empty($secret) && !empty($x_secret) && hash_equals($secret, trim($x_secret))) {
            return true;
        }

        if (!empty($auth_header) && stripos($auth_header, 'Bearer ') === 0) {
            $provided = trim(substr($auth_header, 7));
            if (!empty($secret) && hash_equals($secret, $provided)) {
                return true;
            }
            if (!empty($token) && hash_equals($token, $provided)) {
                return true;
            }
        }

        return new WP_Error(
            'rest_forbidden',
            'Unauthorized: Administrator login, AstroPress bridge secret, or valid Bearer token required.',
            array('status' => 403)
        );
    }

    /**
     * REST Callback: /wp-json/{namespace}/404-log
     * Reads the 404 monitor log from wp_rank_math_404_log table
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function rest_get_404_log($request) {
        global $wpdb;

        $possible_tables = array(
            $wpdb->prefix . 'rank_math_404_logs',
            $wpdb->prefix . 'rank_math_404_log',
        );
        $table_name = null;
        foreach ($possible_tables as $pt) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$pt}'") === $pt) {
                $table_name = $pt;
                break;
            }
        }

        if (!$table_name) {
            $all_tables = $wpdb->get_col("SHOW TABLES LIKE '%" . $wpdb->esc_like($wpdb->prefix) . "%'");
            $matched = array_values(array_filter($all_tables, function($t) {
                return stripos($t, '404') !== false || stripos($t, 'rank') !== false;
            }));
            return new WP_Error('no_table', '404 log table not found. Is 404 Monitor enabled?', array(
                'status' => 404,
                'available_related_tables' => $matched,
            ));
        }

        $per_page = min(absint($request->get_param('per_page') ?: 100), 500);
        $page     = max(absint($request->get_param('page') ?: 1), 1);
        $offset   = ($page - 1) * $per_page;

        $cols = $wpdb->get_col("DESCRIBE {$table_name}");
        $has_times = in_array('times_accessed', $cols, true);
        $has_accessed = in_array('accessed', $cols, true);
        $default_order = $has_times ? 'times_accessed' : ($has_accessed ? 'accessed' : (in_array('id', $cols, true) ? 'id' : $cols[0]));

        $orderby = in_array($request->get_param('orderby'), $cols, true)
            ? $request->get_param('orderby')
            : $default_order;

        $order = strtoupper($request->get_param('order')) === 'ASC' ? 'ASC' : 'DESC';

        $search = $request->get_param('search');
        $where  = '';
        $query_args = array();

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $search_clauses = array();
            foreach (array('uri', 'url', 'referer', 'referrer', 'user_agent') as $sc) {
                if (in_array($sc, $cols, true)) {
                    $search_clauses[] = "`{$sc}` LIKE %s";
                    $query_args[] = $search_like;
                }
            }
            if (!empty($search_clauses)) {
                $where = ' WHERE (' . implode(' OR ', $search_clauses) . ')';
            }
        }

        $escaped_table   = '`' . str_replace('`', '``', $table_name) . '`';
        $escaped_orderby = '`' . str_replace('`', '``', $orderby) . '`';
        $order           = ($order === 'ASC') ? 'ASC' : 'DESC';

        if (!empty($query_args)) {
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$escaped_table}{$where}", ...$query_args));
            $query_args[] = $per_page;
            $query_args[] = $offset;
            $query = $wpdb->prepare(
                "SELECT * FROM {$escaped_table}{$where} ORDER BY {$escaped_orderby} {$order} LIMIT %d OFFSET %d",
                ...$query_args
            );
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$escaped_table}");
            $query = $wpdb->prepare(
                "SELECT * FROM {$escaped_table} ORDER BY {$escaped_orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
        }
        $rows = $wpdb->get_results($query, ARRAY_A);

        return rest_ensure_response(array(
            'table'    => $table_name,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => ceil($total / $per_page),
            'columns'  => $cols,
            'logs'     => $rows ?: array(),
        ));
    }

    /**
     * Verify single-use authentication ticket for Astro revalidation
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function rest_verify_revalidate_ticket($request) {
        $ticket = trim($request->get_param('ticket') ?? '');
        if (empty($ticket) || strlen($ticket) < 32) {
            $res = new WP_REST_Response(array(
                'valid'   => false,
                'message' => 'Invalid or missing authentication ticket format',
            ), 400);
            $res->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            return $res;
        }

        $hash = hash('sha256', $ticket);
        $transient_key = 'astropress_tkt_' . $hash;
        $data = get_transient($transient_key);

        if (!$data) {
            $res = new WP_REST_Response(array(
                'valid'   => false,
                'message' => 'Ticket expired, already used, or unauthorized',
            ), 401);
            $res->header('Cache-Control', 'no-store, no-cache, must-revalidate');
            return $res;
        }

        // Single-use guarantee: burn ticket immediately so it can never be replayed
        delete_transient($transient_key);

        $res = new WP_REST_Response(array(
            'valid'     => true,
            'user_id'   => $data['user_id'] ?? 0,
            'action'    => $data['action'] ?? 'revalidate',
            'timestamp' => $data['time'] ?? time(),
            'message'   => 'Ticket verified and consumed successfully',
        ), 200);
        $res->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        return $res;
    }
}
