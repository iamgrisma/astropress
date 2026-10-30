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

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'warmer';
if (!in_array($sub, array('warmer', 'rules', 'invalidation', 'purge'), true)) {
    $sub = 'warmer';
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
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=warmer')); ?>" 
       class="button <?php echo ($sub === 'warmer') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Edge Cache Warmer', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" 
       class="button <?php echo ($sub === 'rules') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-shield-alt" style="vertical-align:-2px;"></span> <?php esc_html_e('Cache Rules & Presets', 'astropress'); ?>
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
    <!-- Sub-tab 3: Invalidation Triggers & Live Webhook Console -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
            <h3 style="margin:0; font-size:15px; color:#1e293b; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-admin-network" style="color:#0284c7;"></span>
                <span><?php esc_html_e('Live Webhook Handshake & Edge Diagnostics', 'astropress'); ?></span>
            </h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding#onboard-stage-2')); ?>" class="button button-primary button-small">
                <span class="dashicons dashicons-edit" style="vertical-align:-2px;"></span> <?php esc_html_e('Edit Credentials in Setup Wizard &rarr;', 'astropress'); ?>
            </a>
        </div>
        <p style="margin:0 0 16px 0; color:#64748b; font-size:13px; line-height:1.5;">
            <?php esc_html_e('Test your live Astro /api/revalidate endpoint handshake and verify Cloudflare Pages Deploy Hook connectivity. To rotate secrets or modify credentials, use the Setup Wizard to prevent accidental desynchronization.', 'astropress'); ?>
        </p>

        <!-- Current Configuration Status Grid -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:16px;">
            <div>
                <span style="display:block; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Frontend Revalidate Route:', 'astropress'); ?></span>
                <code style="display:block; margin-top:4px; font-size:12px; word-break:break-all;"><?php echo esc_html(rtrim($frontend, '/') . '/api/revalidate'); ?></code>
            </div>
            <div>
                <span style="display:block; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Webhook Secret:', 'astropress'); ?></span>
                <span style="display:inline-block; margin-top:4px; font-size:12px; font-weight:600; color:#166534;">
                    <span class="dashicons dashicons-shield" style="font-size:14px; width:14px; height:14px; vertical-align:-2px;"></span>
                    <?php echo $this->core->has_custom_secret() ? esc_html__('AES-256 Protected & Active', 'astropress') : esc_html__('Auto-Generated Secret', 'astropress'); ?>
                </span>
            </div>
            <div>
                <span style="display:block; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Cloudflare Pages Project:', 'astropress'); ?></span>
                <code style="display:block; margin-top:4px; font-size:12px;"><?php echo esc_html($this->core->get_cf_pages_project() ?: 'Not linked'); ?></code>
            </div>
            <div>
                <span style="display:block; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Deploy Hook:', 'astropress'); ?></span>
                <span style="display:inline-block; margin-top:4px; font-size:12px; font-weight:600; color:<?php echo $this->core->has_cf_deploy_hook() ? '#166534' : '#b45309'; ?>;">
                    <span class="dashicons <?php echo $this->core->has_cf_deploy_hook() ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size:14px; width:14px; height:14px; vertical-align:-2px;"></span>
                    <?php echo $this->core->has_cf_deploy_hook() ? esc_html__('Configured (Encrypted)', 'astropress') : esc_html__('Not Configured', 'astropress'); ?>
                </span>
            </div>
        </div>

        <!-- Hidden Frontend URL & Secret for Ajax calls -->
        <input type="hidden" id="astropress_frontend_url" value="<?php echo esc_attr($frontend); ?>" />

        <!-- Diagnostic Testing Actions -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <strong style="display:block; font-size:13px; color:#1e293b;"><?php esc_html_e('Diagnostic Handshake & Connectivity Tests:', 'astropress'); ?></strong>
                    <span style="font-size:12px; color:#64748b;"><?php esc_html_e('Run immediate health checks against your live frontend endpoint and Pages deploy hook.', 'astropress'); ?></span>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" class="button button-primary" id="astropress-test-webhook-handshake-btn">
                        <span class="dashicons dashicons-admin-network" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Astro Handshake', 'astropress'); ?>
                    </button>
                    <?php if ($this->core->has_cf_deploy_hook()): ?>
                        <button type="button" class="button button-secondary" id="astropress-trigger-deploy-now">
                            <span class="dashicons dashicons-cloud-upload" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Deploy Hook', 'astropress'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="astropress-webhook-test-result" style="margin-top:12px; display:none;"></div>
            <div id="astropress-deploy-status" style="margin-top:12px; display:none;"></div>
        </div>

        <!-- Safe Guidance Notice -->
        <div style="padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; font-size:12px; color:#1e40af; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px; color:#2563eb;"></span>
                <span><?php esc_html_e('Need to change or rotate your webhook secret key or deploy hook?', 'astropress'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding#onboard-stage-2')); ?>" class="button button-secondary button-small" style="font-weight:600;">
                <?php esc_html_e('Open Setup Wizard (Stage 2) &rarr;', 'astropress'); ?>
            </a>
        </div>
    </div>

    <!-- Automatic Purge Triggers Card -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <h3 style="margin:0 0 4px 0; font-size:15px; color:#1e293b;">
            <?php esc_html_e('Automatic Edge Purge & Invalidation Triggers', 'astropress'); ?>
        </h3>
        <p style="margin:0 0 16px 0; color:#64748b; font-size:13px;">
            <?php esc_html_e('Select which WordPress publishing and editing actions should automatically purge Cloudflare Edge POP caches and dispatch invalidation webhooks.', 'astropress'); ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field('astropress_automation_action', 'astropress_automation_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Trigger Rules', 'astropress'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="purge_on_save" value="1" <?php checked($this->core->should_purge_on_save()); ?> /> <strong><?php esc_html_e('Post & Page Updates', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Purge cache on publish, update, trash, or restore', 'astropress'); ?></label>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="purge_feeds" value="1" <?php checked($this->core->should_purge_feeds()); ?> /> <strong><?php esc_html_e('RSS & Atom Feeds', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Purge feed URLs when new articles are published', 'astropress'); ?></label>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="purge_taxonomy" value="1" <?php checked($this->core->should_purge_taxonomy()); ?> /> <strong><?php esc_html_e('Taxonomy Archives', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Purge category and tag archives when terms are edited', 'astropress'); ?></label>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="purge_menu" value="1" <?php checked($this->core->should_purge_menu()); ?> /> <strong><?php esc_html_e('Navigation Menus', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Purge global frontend cache when menus change', 'astropress'); ?></label>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="debounce_enabled" value="1" <?php checked($this->core->should_debounce_webhooks()); ?> /> <strong><?php esc_html_e('Smart Debouncing', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Batch multiple rapid edits into a single webhook payload', 'astropress'); ?></label>
                            <label style="display:block; margin-bottom:8px;"><input type="checkbox" name="cf_cache_tags_enabled" value="1" <?php checked((bool) get_option('astropress_cf_cache_tags_enabled', false)); ?> /> <strong><?php esc_html_e('Cloudflare Cache-Tags', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Instant surrogate-key purging across Cloudflare Enterprise/Pro POPs', 'astropress'); ?></label>
                            <label style="display:block;"><input type="checkbox" name="lscwp_sync" value="1" <?php checked($this->core->should_sync_lscwp()); ?> /> <strong><?php esc_html_e('LiteSpeed Cache Sync', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Forward LiteSpeed Cache purge events to Cloudflare and Astro', 'astropress'); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p class="submit" style="margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9;">
                <button type="submit" name="astropress_save_automation" class="button button-primary"><?php esc_html_e('Save Invalidation Triggers', 'astropress'); ?></button>
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
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:12px; margin-bottom:18px;">
            <!-- Preset A: Astro SSR Edge Caching -->
            <div style="padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong style="color:#0f172a; font-size:13px;"><?php esc_html_e('Astro SSR Production Cache', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#e0f2fe; color:#0369a1; font-weight:700; font-size:10px;">Preset A</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.45; margin:0 0 10px 0;">
                        <?php printf(
                            esc_html__('Caches production SSR HTML on %s respecting origin headers and stale-while-revalidate.', 'astropress'),
                            '<code>' . esc_html($frontend_host) . '</code>'
                        ); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="astro_ssr" <?php disabled(!$is_cf_ready); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy Astro SSR Rule', 'astropress'); ?>
                </button>
            </div>

            <!-- Preset B: Static Assets & Media TTL -->
            <div style="padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong style="color:#0f172a; font-size:13px;"><?php esc_html_e('Static Assets Cache', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#fef3c7; color:#92400e; font-weight:700; font-size:10px;">Preset B</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.45; margin:0 0 10px 0;">
                        <?php esc_html_e('Caches immutable static assets (/_astro/* and /assets/*) for 1 year at Cloudflare Edge with 0 origin calls.', 'astropress'); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="static_assets" <?php disabled(!$is_cf_ready); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy Static Assets Rule', 'astropress'); ?>
                </button>
            </div>

            <!-- Preset C: R2 Custom CDN Domain Rule -->
            <div style="padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong style="color:#0f172a; font-size:13px;"><?php esc_html_e('R2 CDN Domain Rule', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#dcfce7; color:#166534; font-weight:700; font-size:10px;">Preset C</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.45; margin:0 0 10px 0;">
                        <?php if (!empty($cdn_domain)): ?>
                            <?php printf(esc_html__('1-year Edge and Browser TTL for active R2 CDN (%s). Zero egress cost.', 'astropress'), '<code>' . esc_html($cdn_domain) . '</code>'); ?>
                        <?php else: ?>
                            <?php esc_html_e('1-year Edge and Browser TTL for your custom R2 subdomain configured in Stage 3.', 'astropress'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="r2_cdn" <?php disabled(!$is_cf_ready || empty($cdn_domain)); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy R2 CDN Rule', 'astropress'); ?>
                </button>
            </div>

            <!-- Preset D: WP Admin & REST API Bypass -->
            <div style="padding:14px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <strong style="color:#0f172a; font-size:13px;"><?php esc_html_e('WP Admin & API Bypass', 'astropress'); ?></strong>
                        <span class="astropress-chip" style="background:#fee2e2; color:#991b1b; font-weight:700; font-size:10px;">Preset D</span>
                    </div>
                    <p style="color:#475569; font-size:12px; line-height:1.45; margin:0 0 10px 0;">
                        <?php esc_html_e('Strictly bypasses Cloudflare Edge caching for /wp-admin, /wp-login.php, and /wp-json/ REST API.', 'astropress'); ?>
                    </p>
                </div>
                <button type="button" class="button button-primary astropress-deploy-rule-preset" data-preset="wp_admin_bypass" <?php disabled(!$is_cf_ready); ?> style="width:100%; justify-content:center; display:flex; align-items:center; gap:6px; font-size:12px;">
                    <span class="dashicons dashicons-yes"></span> <?php esc_html_e('Deploy WP Bypass Rule', 'astropress'); ?>
                </button>
            </div>
        </div>

        <div id="astropress-rule-preset-status" style="margin-bottom:14px;"></div>

        <!-- Interactive Custom Cache Rule Builder -->
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                <h4 style="margin:0; font-size:14px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-settings" style="color:#0284c7;"></span>
                    <span><?php esc_html_e('Custom Cloudflare Cache Rule Builder', 'astropress'); ?></span>
                </h4>
                <span style="font-size:11px; color:#64748b;"><?php esc_html_e('Manual builder with dropdowns, toggles, and TTL controls', 'astropress'); ?></span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin-bottom:12px;">
                <!-- Match Scope -->
                <div>
                    <label for="astropress_rule_scope" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                        <?php esc_html_e('Traffic Match Scope:', 'astropress'); ?>
                    </label>
                    <select id="astropress_rule_scope" style="width:100%; font-size:12px;">
                        <option value="astro_frontend"><?php esc_html_e('Astro Frontend Host', 'astropress'); ?> (<?php echo esc_html($frontend_host); ?>)</option>
                        <option value="static_assets"><?php esc_html_e('Static Assets Path (/_astro/* and /assets/*)', 'astropress'); ?></option>
                        <option value="r2_cdn" <?php echo empty($cdn_domain) ? 'disabled' : ''; ?>><?php esc_html_e('R2 Media Subdomain', 'astropress'); ?> (<?php echo esc_html($cdn_domain ?: 'Not configured'); ?>)</option>
                        <option value="wp_bypass"><?php esc_html_e('WordPress Origin Paths (/wp-admin, /wp-json)', 'astropress'); ?></option>
                        <option value="custom"><?php esc_html_e('Custom Cloudflare Expression...', 'astropress'); ?></option>
                    </select>
                </div>

                <!-- Cache Behavior -->
                <div>
                    <label for="astropress_rule_action" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                        <?php esc_html_e('Edge Cache Behavior:', 'astropress'); ?>
                    </label>
                    <select id="astropress_rule_action" style="width:100%; font-size:12px;">
                        <option value="cache"><?php esc_html_e('Eligible for Edge Caching (Cache Active)', 'astropress'); ?></option>
                        <option value="bypass"><?php esc_html_e('Bypass Edge Cache (Do Not Cache)', 'astropress'); ?></option>
                    </select>
                </div>

                <!-- Edge TTL -->
                <div id="astropress_rule_edge_ttl_wrap">
                    <label for="astropress_rule_edge_ttl" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                        <?php esc_html_e('Edge Cache TTL:', 'astropress'); ?>
                    </label>
                    <select id="astropress_rule_edge_ttl" style="width:100%; font-size:12px;">
                        <option value="31536000"><?php esc_html_e('1 Year (31,536,000s) — Immutable', 'astropress'); ?></option>
                        <option value="2592000"><?php esc_html_e('30 Days (2,592,000s)', 'astropress'); ?></option>
                        <option value="604800"><?php esc_html_e('7 Days (604,800s)', 'astropress'); ?></option>
                        <option value="86400"><?php esc_html_e('1 Day (86,400s)', 'astropress'); ?></option>
                        <option value="3600"><?php esc_html_e('1 Hour (3,600s)', 'astropress'); ?></option>
                        <option value="respect"><?php esc_html_e('Respect Origin Cache-Control Headers', 'astropress'); ?></option>
                    </select>
                </div>

                <!-- Browser TTL -->
                <div id="astropress_rule_browser_ttl_wrap">
                    <label for="astropress_rule_browser_ttl" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                        <?php esc_html_e('Browser Cache TTL:', 'astropress'); ?>
                    </label>
                    <select id="astropress_rule_browser_ttl" style="width:100%; font-size:12px;">
                        <option value="31536000"><?php esc_html_e('1 Year (31,536,000s)', 'astropress'); ?></option>
                        <option value="2592000"><?php esc_html_e('30 Days (2,592,000s)', 'astropress'); ?></option>
                        <option value="604800"><?php esc_html_e('7 Days (604,800s)', 'astropress'); ?></option>
                        <option value="86400"><?php esc_html_e('1 Day (86,400s)', 'astropress'); ?></option>
                        <option value="respect"><?php esc_html_e('Respect Origin Cache-Control Headers', 'astropress'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Custom Expression text row (hidden by default) -->
            <div id="astropress_custom_expr_wrap" style="display:none; margin-bottom:12px;">
                <label for="astropress_rule_expression" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                    <?php esc_html_e('Cloudflare Rule Expression (Wirefilter syntax):', 'astropress'); ?>
                </label>
                <input type="text" id="astropress_rule_expression" class="regular-text" style="width:100%; font-family:monospace; font-size:12px;" placeholder='(http.host eq "<?php echo esc_attr($frontend_host); ?>")' />
            </div>

            <!-- Rule Description & Submit -->
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px;">
                    <input type="text" id="astropress_rule_desc" class="regular-text" style="width:100%; font-size:12px;" placeholder="<?php esc_attr_e('Rule description (e.g. AstroPress Custom Cache Rule)', 'astropress'); ?>" value="AstroPress Custom Cache Rule" />
                </div>
                <button type="button" class="button button-primary" id="astropress-deploy-custom-builder-btn" <?php disabled(!$is_cf_ready); ?> style="display:inline-flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-saved" style="vertical-align:-2px;"></span>
                    <span><?php esc_html_e('Deploy Rule to Cloudflare', 'astropress'); ?></span>
                </button>
            </div>
            <div id="astropress-builder-status" style="margin-top:8px; display:none;"></div>
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
