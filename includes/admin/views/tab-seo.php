<?php
/**
 * Admin View: Headless SEO & Subdomain Protection Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $rewrite_links    = $this->core->should_rewrite_links();
        $subdomain_shield = $this->core->should_shield_subdomain();
        $subdomain_noindex= $this->core->should_noindex_backend();
        $rank_math_active = class_exists('RankMath');
        $rank_math_pro    = defined('RANK_MATH_PRO_VERSION');
        ?>
        <div style="max-width:920px;">
            <div class="astropress-card">
                <div class="astropress-card-header">
                    <h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e('Multi-Engine Headless SEO & Subdomain Protection Shield', 'astropress'); ?></h2>
                    <?php
                    $detected_engine = $this->core->seo ? $this->core->seo->detect_active_seo_engine() : 'native';
                    $engine_label = ($detected_engine === 'rank_math') ? ($rank_math_pro ? 'Rank Math Pro Active' : 'Rank Math Active')
                        : (($detected_engine === 'yoast') ? 'Yoast SEO Active'
                        : (($detected_engine === 'seopress') ? 'SEOPress Active'
                        : (($detected_engine === 'aioseo') ? 'AIOSEO Active' : 'Native SEO Ready')));
                    ?>
                    <span class="astropress-badge astropress-badge-online">
                        <span class="astropress-dot"></span> <?php echo esc_html($engine_label); ?>
                    </span>
                </div>
                <p style="color: #64748b; font-size: 13px; margin-top: 0;">
                    <?php esc_html_e('Manage search engine indexation, Rank Math JSON-LD schema bridge, preview/view permalink rewrites, and origin subdomain shield.', 'astropress'); ?>
                </p>

                <form method="post" action="">
                    <?php wp_nonce_field('astropress_seo_action', 'astropress_seo_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Preview & View Links', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="rewrite_links" id="seo_rewrite_links" value="1" <?php checked($rewrite_links); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Rewrite Preview & View Links to Astro Frontend', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Points "View Post", post previews, taxonomy links, and author archives directly to your canonical Astro frontend domain instead of the WordPress backend.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Subdomain 301 Shield', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="subdomain_shield" id="seo_subdomain_shield" value="1" <?php checked($subdomain_shield); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Subdomain 301 Protection Shield', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Automatically issues an HTTP 301 Moved Permanently redirect for public human visitors from the WordPress origin subdomain (e.g. wp.topnepali.com) to the production Astro website (topnepali.com). Excludes WP-Admin, REST API endpoints, Cron jobs, and webhooks.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Search Engine Noindex', 'astropress'); ?></th>
                            <td>
                                <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                    <input type="checkbox" name="subdomain_noindex" id="seo_subdomain_noindex" value="1" <?php checked($subdomain_noindex); ?> style="margin-top:3px;" />
                                    <div>
                                        <strong><?php esc_html_e('Backend Noindex HTTP Headers', 'astropress'); ?></strong>
                                        <p class="description"><?php esc_html_e('Emits X-Robots-Tag: noindex, nofollow on the WordPress backend to prevent duplicate content indexing by Google, Bing, and other crawlers.', 'astropress'); ?></p>
                                    </div>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top:20px;">
                        <button type="submit" name="astropress_save_seo" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save SEO & Security Settings', 'astropress'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEO Architecture Blueprint & REST Bridge Card -->
            <div class="astropress-card" style="margin-top:20px;">
                <div class="astropress-card-header">
                    <h2><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e('Rank Math Schema & REST API Integration', 'astropress'); ?></h2>
                </div>
                <p style="font-size:13px; color:#475569; line-height:1.6;">
                    <?php esc_html_e('AstroPress automatically registers deep Rank Math SEO fields into the WordPress REST API responses for Posts, Pages, Categories, and Tags. Your Astro templates can extract and render rich metadata and Google-compliant JSON-LD structured data without any complex plugins.', 'astropress'); ?>
                </p>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px; margin-top:12px;">
                    <h4 style="margin:0 0 8px 0; font-size:13px; color:#0f172a;"><?php esc_html_e('Exposed REST API Fields:', 'astropress'); ?></h4>
                    <ul style="margin:0; padding-left:18px; font-size:12px; color:#334155; line-height:1.8;">
                        <li><code>rank_math_seo.title</code> &mdash; <?php esc_html_e('Custom SEO Title with fallback to post title', 'astropress'); ?></li>
                        <li><code>rank_math_seo.description</code> &mdash; <?php esc_html_e('Custom Meta Description with fallback to excerpt', 'astropress'); ?></li>
                        <li><code>rank_math_seo.canonical_url</code> &mdash; <?php esc_html_e('Canonical production frontend URL', 'astropress'); ?></li>
                        <li><code>rank_math_seo.og_image</code> &mdash; <?php esc_html_e('OpenGraph social card image URL (via Cloudflare R2 CDN)', 'astropress'); ?></li>
                        <li><code>rank_math_seo.json_ld</code> &mdash; <?php esc_html_e('Rich Article, BreadcrumbList, and Organization schema graph', 'astropress'); ?></li>
                        <li><code>rank_math_seo.robots</code> &mdash; <?php esc_html_e('Robots indexing directives (index, follow, noindex)', 'astropress'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
