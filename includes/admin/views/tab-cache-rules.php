<?php
/**
 * Admin View: Cloudflare Cache Rules Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $token         = $this->core->get_cf_api_token();
        $zone_id       = $this->core->get_cf_zone_id();
        $frontend      = $this->core->get_frontend_url();
        $frontend_host = wp_parse_url($frontend, PHP_URL_HOST) ?: $frontend;
        $cdn_domain    = AstroPress_Admin::get_r2_option('custom_domain', '');
        ?>
        <div class="astropress-card" style="max-width:960px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0 0 4px 0;"><?php esc_html_e('Cloudflare Edge & Cache Rules Manager', 'astropress'); ?></h2>
                    <p style="margin:0; color:#64748b; font-size:13px;">
                        <?php esc_html_e('Manage modern Cloudflare Ruleset Engine Cache Rules (http_request_cache_settings) directly from WordPress. No Page Rules required.', 'astropress'); ?>
                    </p>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="button button-secondary" id="astropress-refresh-cache-rules-btn">
                        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Refresh Rules', 'astropress'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="astropress-purge-all-zone-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge All Zone Cache', 'astropress'); ?>
                    </button>
                </div>
            </div>

            <!-- 1-Click Presets -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h3 style="margin:0 0 10px 0; font-size:14px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-superhero" style="color:#2563eb;"></span>
                    <?php esc_html_e('1-Click Cache Rule Presets', 'astropress'); ?>
                </h3>
                <div class="astropress-preset-grid">
                    <!-- Preset 1: Astro Static Assets -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('Astro Static Assets (1-Year TTL)', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                <code>/_astro/*</code> &amp; <code>/assets/*</code> edge &amp; browser cache for 31,536,000s (1 year).
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="astro_assets">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Preset 2: Astro SSR Origin Headers -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('Astro SSR HTML Cache', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                Matches <code><?php echo esc_html($frontend_host); ?></code> and respects origin cache headers (s-maxage &amp; stale-while-revalidate).
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="astro_ssr">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Preset 3: R2 Media CDN -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('R2 Media CDN (1-Year TTL)', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                Matches <code><?php echo !empty($cdn_domain) ? esc_html($cdn_domain) : 'media.example.com'; ?></code> and forces 1-year Edge Cache Everything.
                            </p>
                        </div>
                        <button type="button" class="button button-secondary button-small astropress-deploy-preset-btn" data-preset="r2_cdn">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Preset', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Preset 4: Astro Full 1-Year Edge Cache -->
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="font-weight:700; font-size:13px; color:#0f172a; margin-bottom:4px;">
                                <?php esc_html_e('Astro Full 1-Year Edge Cache', 'astropress'); ?>
                            </div>
                            <p style="font-size:12px; color:#64748b; margin:0 0 8px 0;">
                                Matches <code><?php echo esc_html($frontend_host); ?></code> and forces 1-year Cloudflare Edge Cache (fixes DYNAMIC status for instant speed).
                            </p>
                        </div>
                        <button type="button" class="button button-primary button-small astropress-deploy-preset-btn" data-preset="astro_edge_override">
                            <span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy 1-Year Cache', 'astropress'); ?>
                        </button>
                    </div>
                </div>
                <div id="astropress-preset-deploy-status" style="margin-top:10px; font-size:12px;"></div>
            </div>

            <!-- Active Cache Rules Table -->
            <div style="margin-bottom:24px;">
                <h3 style="margin:0 0 10px 0; font-size:14px; color:#1e293b;">
                    <?php esc_html_e('Active Cloudflare Ruleset Cache Rules:', 'astropress'); ?>
                </h3>
                <div id="astropress-rules-table-container" class="astropress-table-responsive">
                    <p style="color:#64748b; font-size:13px;">
                        <span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span>
                        <?php esc_html_e('Loading active cache rules from Cloudflare Ruleset API...', 'astropress'); ?>
                    </p>
                </div>
            </div>

            <hr style="margin:24px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Direct Zone Cache Purge Controls -->
            <div>
                <h3 style="margin:0 0 8px 0; font-size:14px; color:#1e293b;">
                    <?php esc_html_e('Direct Cloudflare Zone Edge Purge', 'astropress'); ?>
                </h3>
                <p style="font-size:13px; color:#64748b; margin:0 0 14px 0;">
                    <?php esc_html_e('Purge specific URLs or your entire Cloudflare Zone edge cache instantly via Cloudflare API v4.', 'astropress'); ?>
                </p>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; max-width:700px;">
                    <input type="text" id="astropress-purge-urls-input" placeholder="e.g. /news, /district/kathmandu (comma-separated or full URLs)" class="regular-text" style="flex:1; min-width:280px;" />
                    <button type="button" class="button button-secondary" id="astropress-purge-urls-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge URLs', 'astropress'); ?>
                    </button>
                </div>
                <div id="astropress-direct-purge-status" style="margin-top:8px; font-size:12px;"></div>
            </div>
        </div>
