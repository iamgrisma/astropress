<?php
/**
 * Admin View: Unified Cloudflare & Edge Cache Tab
 *
 * Structured sub-tabs for Cache Rules & Presets, Edge Cache Warmer,
 * Invalidation Triggers & Deploy Hooks, and Direct Edge Purge.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'rules';
if (!in_array($sub, array('rules', 'warmer', 'invalidation', 'purge'), true)) {
    $sub = 'rules';
}

$token          = $this->core->get_cf_api_token();
$zone_id        = $this->core->get_cf_zone_id();
$frontend       = $this->core->get_frontend_url();
$frontend_host  = wp_parse_url($frontend, PHP_URL_HOST) ?: $frontend;
$cdn_domain     = AstroPress_Admin::get_r2_option('custom_domain', '');
$cf_deploy_hook = $this->core->get_cf_deploy_hook();
$is_cf_ready    = (!empty($token) && !empty($zone_id));
?>

<div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
        <div>
            <h2 style="margin:0 0 4px 0; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-cloud" style="color:#0284c7;"></span>
                <span><?php esc_html_e('Cloudflare Edge & Cache Rules Manager', 'astropress'); ?></span>
                <?php if ($is_cf_ready): ?>
                    <span class="astropress-badge is-connected" style="font-size:11px;"><?php esc_html_e('Edge Active', 'astropress'); ?></span>
                <?php else: ?>
                    <span class="astropress-badge" style="font-size:11px; background:#fef2f2; color:#991b1b;"><?php esc_html_e('Setup Incomplete', 'astropress'); ?></span>
                <?php endif; ?>
            </h2>
            <p style="margin:0; color:#64748b; font-size:13px;">
                <?php if ($is_cf_ready): ?>
                    <strong><?php esc_html_e('Zone ID:', 'astropress'); ?></strong> <code><?php echo esc_html(substr($zone_id, 0, 10)); ?>••••</code> &bull; 
                    <strong><?php esc_html_e('Frontend Host:', 'astropress'); ?></strong> <code><?php echo esc_html($frontend_host); ?></code>
                <?php else: ?>
                    <?php esc_html_e('Manage Cloudflare Ruleset Engine Cache Rules, invalidation webhooks, and edge warming directly from WordPress.', 'astropress'); ?>
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <?php if ($is_cf_ready): ?>
                <button type="button" class="button button-secondary" id="astropress-refresh-cache-rules-btn">
                    <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Refresh Rules', 'astropress'); ?>
                </button>
                <button type="button" class="button button-primary" id="astropress-purge-all-zone-btn">
                    <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge All Edge Cache', 'astropress'); ?>
                </button>
            <?php else: ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-generic" style="vertical-align:-2px;"></span> <?php esc_html_e('Connect Cloudflare in Setup Wizard &rarr;', 'astropress'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_cf_ready): ?>
    <div class="notice notice-warning inline" style="margin:0 0 20px 0; padding:14px 16px; border-left:4px solid #f59e0b; background:#fffbeb;">
        <h4 style="margin:0 0 6px 0; color:#b45309; font-size:14px;">
            <span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span>
            <?php esc_html_e('Cloudflare Scoped Token or Zone ID Not Configured', 'astropress'); ?>
        </h4>
        <p style="margin:0 0 10px 0; color:#78350f; font-size:13px; line-height:1.45;">
            <?php esc_html_e('To enable Cloudflare Ruleset Engine cache rules, automatic purge on publish, and direct edge clearing, connect your Cloudflare account in the Setup Wizard. Pre-flight auto-discovery will automatically detect your Zone ID, Account ID, and R2 buckets.', 'astropress'); ?>
        </p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-primary">
            <span class="dashicons dashicons-welcome-learn-more" style="vertical-align:-2px;"></span> <?php esc_html_e('Launch Setup Wizard &rarr;', 'astropress'); ?>
        </a>
    </div>
<?php endif; ?>

<!-- Cloudflare & Cache Sub-navigation Bar -->
<div class="astropress-tools-nav" style="display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px; flex-wrap:wrap;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" 
       class="button <?php echo ($sub === 'rules') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-shield-alt" style="vertical-align:-2px;"></span> <?php esc_html_e('Cache Rules & Presets', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=warmer')); ?>" 
       class="button <?php echo ($sub === 'warmer') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Edge Cache Warmer', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=invalidation')); ?>" 
       class="button <?php echo ($sub === 'invalidation') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php esc_html_e('Invalidation & Deploy Hooks', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=purge')); ?>" 
       class="button <?php echo ($sub === 'purge') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Direct Edge Purge', 'astropress'); ?>
    </a>
</div>

<?php if ($sub === 'warmer'): ?>
    <!-- Sub-tab 2: Edge Cache Warmer -->
    <div id="warmer" style="max-width:960px; margin-bottom:20px;">
        <?php include ASTROPRESS_PATH . 'includes/admin/views/tab-warmer.php'; ?>
    </div>

<?php elseif ($sub === 'invalidation'): ?>
    <!-- Sub-tab 3: Invalidation Triggers & Deploy Hooks -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <h3 style="margin:0 0 4px 0; font-size:15px; color:#1e293b;">
            <?php esc_html_e('Cache Invalidation Triggers & Webhook Policies', 'astropress'); ?>
        </h3>
        <p style="margin:0 0 16px 0; color:#64748b; font-size:13px;">
            <?php esc_html_e('Define when WordPress should dispatch cache invalidation webhooks to your Astro frontend and purge Cloudflare Edge.', 'astropress'); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('astropress_automation_action', 'astropress_automation_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic Purge Triggers', 'astropress'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="purge_on_save" value="1" <?php checked($this->core->should_purge_on_save()); ?> /> <?php esc_html_e('Purge post / page cache on publish, update or trash', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="purge_feeds" value="1" <?php checked($this->core->should_purge_feeds()); ?> /> <?php esc_html_e('Purge RSS and Atom feeds on new post publication', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="purge_taxonomy" value="1" <?php checked($this->core->should_purge_taxonomy()); ?> /> <?php esc_html_e('Purge category and tag archives when terms are edited', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="purge_menu" value="1" <?php checked($this->core->should_purge_menu()); ?> /> <?php esc_html_e('Purge global cache when navigation menus are updated', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="debounce_enabled" value="1" <?php checked($this->core->should_debounce_webhooks()); ?> /> <?php esc_html_e('Debounce requests (batch multiple invalidations into a single payload)', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="cf_cache_tags_enabled" value="1" <?php checked((bool) get_option('astropress_cf_cache_tags_enabled', false)); ?> /> <?php esc_html_e('Purge Cloudflare Cache-Tags (Surrogate-Key instant purging)', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="lscwp_sync" value="1" <?php checked($this->core->should_sync_lscwp()); ?> /> <?php esc_html_e('Synchronize with LiteSpeed Cache purge events', 'astropress'); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="astropress_revalidate_secret"><?php esc_html_e('Astro Webhook Secret', 'astropress'); ?></label></th>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; max-width:680px;">
                            <input type="password" id="astropress_revalidate_secret" name="revalidate_secret" value="<?php echo $this->core->has_custom_secret() ? '••••••••••••••••' : ''; ?>" class="regular-text" placeholder="<?php esc_attr_e('Leave blank if not set in Cloudflare', 'astropress'); ?>" autocomplete="new-password" style="flex:1; min-width:240px;" />
                            <span id="astropress-key-badge" style="display:inline-block; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:600; border:1px solid #cbd5e1; background:#f8fafc; color:#64748b;">
                                <?php echo $this->core->has_custom_secret() ? esc_html__('Custom Secret Stored', 'astropress') : esc_html__('No Secret (Open Endpoint)', 'astropress'); ?>
                            </span>
                        </div>
                        <p class="description"><?php esc_html_e('Shared secret between WordPress and your Astro /api/revalidate endpoint. Matches the REVALIDATE_SECRET environment variable in Cloudflare Pages/Workers. If not using a secret, leave blank.', 'astropress'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="astropress_cf_deploy_hook"><?php esc_html_e('Cloudflare Pages Deploy Hook', 'astropress'); ?></label></th>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; max-width:680px;">
                            <input type="url" id="astropress_cf_deploy_hook" name="cf_deploy_hook" value="<?php echo esc_attr($cf_deploy_hook); ?>" class="regular-text" placeholder="https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/..." style="flex:1; min-width:240px;" />
                            <button type="button" class="button button-secondary" id="astropress-trigger-deploy-now"><?php esc_html_e('Save & Trigger Deploy', 'astropress'); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e('Optional: Trigger a static site build in Cloudflare Pages when a full cache purge is requested.', 'astropress'); ?></p>
                        <div id="astropress-deploy-status" style="margin-top:8px;"></div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="astropress_save_automation" class="button button-primary"><?php esc_html_e('Save Invalidation Policies', 'astropress'); ?></button>
            </p>
        </form>
    </div>

<?php elseif ($sub === 'purge'): ?>
    <!-- Sub-tab 4: Direct Edge Purge & Danger Zone -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <h3 style="margin:0 0 4px 0; font-size:15px; color:#1e293b;">
            <?php esc_html_e('Direct Cloudflare Zone Edge Purge', 'astropress'); ?>
        </h3>
        <p style="margin:0 0 14px 0; color:#64748b; font-size:13px;">
            <?php esc_html_e('Purge specific URLs or your entire Cloudflare Zone edge cache instantly via Cloudflare API v4.', 'astropress'); ?>
        </p>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; max-width:700px;">
            <input type="text" id="astropress-purge-urls-input" placeholder="e.g. /news, /district/kathmandu (comma-separated or full URLs)" class="regular-text" style="flex:1; min-width:280px;" />
            <button type="button" class="button button-secondary" id="astropress-purge-urls-btn">
                <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge Specific URLs', 'astropress'); ?>
            </button>
            <button type="button" class="button button-primary" id="astropress-purge-all-zone-btn" style="background:#dc2626; border-color:#b91c1c;">
                <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge Everything (Entire Zone)', 'astropress'); ?>
            </button>
        </div>
        <div id="astropress-direct-purge-status" style="margin-top:8px; font-size:12px;"></div>
    </div>

    <!-- Danger Zone -->
    <div class="astropress-card" style="max-width:960px; background:#fef2f2; border:1px solid #fecaca;">
        <h3 style="margin:0 0 6px 0; color:#991b1b; font-size:15px; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-warning" style="color:#dc2626;"></span>
            <?php esc_html_e('Danger Zone: Disconnect & Reset Connection', 'astropress'); ?>
        </h3>
        <p style="margin:0 0 14px 0; color:#7f1d1d; font-size:13px; line-height:1.5;">
            <?php esc_html_e('Disconnects WordPress from Cloudflare, purges encrypted tokens and stored zone configurations, and resets AstroPress to a fresh, unconfigured state. The dashboard will lock until the Setup Wizard is completed again.', 'astropress'); ?>
        </p>
        <button type="button" class="button button-secondary" id="astropress-reset-connection-btn" style="color:#b91c1c; border-color:#f87171; background:#ffffff;">
            <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Disconnect & Reset AstroPress', 'astropress'); ?>
        </button>
        <div id="astropress-reset-status" style="margin-top:10px; font-size:12px;"></div>
    </div>

<?php else: ?>
    <!-- Sub-tab 1 (Default): Cache Rules & 1-Click Presets -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <h3 style="margin:0 0 4px 0; font-size:15px; color:#1e293b;">
            <?php esc_html_e('Cloudflare Ruleset Cache Rules (HTTP Request Cache Settings)', 'astropress'); ?>
        </h3>
        <p style="margin:0 0 16px 0; color:#64748b; font-size:13px;">
            <?php esc_html_e('Deploy 1-click enterprise edge caching rules directly into your Cloudflare Zone via Rulesets API v4. Rules run entirely in memory on Cloudflare Edge with 0ms origin latency.', 'astropress'); ?>
        </p>

        <!-- 1-Click Presets Grid -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-bottom:20px;">
            <!-- Preset A: Astro SSR Edge Caching -->
            <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="color:#0f172a; font-size:14px;"><?php esc_html_e('Astro SSR Production Cache', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#e0f2fe; color:#0369a1; font-weight:700;">Preset A</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.5; margin:0 0 12px 0;">
                        <?php printf(
                            esc_html__('Caches production frontend HTML on %s for 1 year (31,536,000s) on Cloudflare Edge with stale-while-revalidate. Bypasses cache on preview queries.', 'astropress'),
                            '<code>' . esc_html($frontend_host) . '</code>'
                        ); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="astro_ssr" <?php disabled(!$is_cf_ready); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy Astro SSR Rule', 'astropress'); ?>
                </button>
            </div>

            <!-- Preset B: Static Assets & Media TTL -->
            <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="color:#0f172a; font-size:14px;"><?php esc_html_e('Static Assets Cache', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#fef3c7; color:#92400e; font-weight:700;">Preset B</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.5; margin:0 0 12px 0;">
                        <?php esc_html_e('Caches immutable static assets (JS, CSS, fonts, SVG, WebP, AVIF) for 1 year at Cloudflare Edge. Strips Origin Set-Cookie headers for 100% cache hit ratios.', 'astropress'); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="static_assets" <?php disabled(!$is_cf_ready); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy Static Assets Rule', 'astropress'); ?>
                </button>
            </div>

            <!-- Preset C: R2 Custom CDN Domain Rule -->
            <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <strong style="color:#0f172a; font-size:14px;"><?php esc_html_e('R2 CDN Domain Rule', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#dcfce7; color:#166534; font-weight:700;">Preset C</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.5; margin:0 0 12px 0;">
                        <?php if (!empty($cdn_domain)): ?>
                            <?php printf(esc_html__('Configures 1-year Edge and Browser TTL for your active R2 CDN hostname (%s). Ensures 0 egress cost from Cloudflare.', 'astropress'), '<code>' . esc_html($cdn_domain) . '</code>'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Applies to your custom R2 subdomain once configured in the Media tab. Enforces 1-year edge TTL with zero origin revalidations.', 'astropress'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="r2_cdn" <?php disabled(!$is_cf_ready || empty($cdn_domain)); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy R2 CDN Rule', 'astropress'); ?>
                </button>
            </div>
        </div>

        <div id="astropress-rule-preset-status" style="margin-bottom:14px;"></div>

        <!-- Active Rules Table Container -->
        <h4 style="margin:20px 0 8px 0; font-size:13px; color:#334155; text-transform:uppercase; letter-spacing:0.5px;">
            <?php esc_html_e('Active Cloudflare Ruleset Cache Rules:', 'astropress'); ?>
        </h4>

        <?php if ($is_cf_ready): ?>
            <div id="astropress-rules-table-container" class="astropress-table-responsive">
                <p style="color:#64748b; font-size:13px;">
                    <span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span>
                    <?php esc_html_e('Loading active cache rules from Cloudflare Ruleset API...', 'astropress'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="astropress-table-responsive" style="padding:16px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; color:#64748b; font-size:13px;">
                <span class="dashicons dashicons-info" style="vertical-align:-2px;"></span>
                <?php esc_html_e('Rules will be listed once Cloudflare Scoped Token and Zone ID are connected in the Setup Wizard.', 'astropress'); ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
