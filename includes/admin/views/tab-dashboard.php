<?php
/**
 * Admin View: Dashboard & System Diagnostics Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $total_media = $stats['total_wp'] ?? ($stats['total'] ?? 0);
        $offloaded   = $stats['synced'] ?? ($stats['offloaded'] ?? 0);
        $percentage  = ($total_media > 0) ? round(($offloaded / $total_media) * 100, 1) : 0;
        $saved_space = size_format($stats['savings_bytes'] ?? ($stats['saved_bytes'] ?? 0), 2);
        ?>
        <div class="astropress-grid-2">
            <div class="astropress-card">
                <h2><?php esc_html_e('System Status & Diagnostics', 'astropress'); ?></h2>
                <table class="astropress-status-table">
                    <tr>
                        <td><strong><?php esc_html_e('Headless Frontend URL', 'astropress'); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url($frontend_url); ?>" target="_blank" rel="noopener">
                                <code><?php echo esc_html($frontend_url); ?></code> <span class="dashicons dashicons-external" style="font-size:13px;"></span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Bridge Status', 'astropress'); ?></strong></td>
                        <td>
                            <?php if ($is_connected): ?>
                                <span class="astropress-text-success"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Connected & Handshake Verified', 'astropress'); ?></span>
                            <?php else: ?>
                                <span class="astropress-text-danger"><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Disconnected or Pending Verification', 'astropress'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Cloudflare R2 Bucket', 'astropress'); ?></strong></td>
                        <td><code><?php echo esc_html(AstroPress_Admin::get_r2_option('bucket') ?: esc_html__('None', 'astropress')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Custom CDN Host', 'astropress'); ?></strong></td>
                        <td><code><?php echo esc_html(AstroPress_Admin::get_r2_option('custom_domain') ?: esc_html__('Origin Uploads', 'astropress')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Image Engine', 'astropress'); ?></strong></td>
                        <td><span class="astropress-pill"><?php echo esc_html(strtoupper(AstroPress_Optimizer::get_engine())); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Subdomain Shield', 'astropress'); ?></strong></td>
                        <td>
                            <?php if ($this->core->should_shield_subdomain()): ?>
                                <span class="astropress-pill" style="background:#def7ec;color:#03543f;">301 Redirect Active</span>
                            <?php else: ?>
                                <span class="astropress-pill" style="background:#f3f4f6;color:#4b5563;">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="astropress-btn-group" style="margin-top:20px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <button type="button" class="button button-primary" id="astropress-purge-cf-now">
                        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge Cloudflare Cache', 'astropress'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="astropress-dash-test-handshake-btn">
                        <span class="dashicons dashicons-admin-network" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Handshake', 'astropress'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-generic" style="vertical-align:-2px;"></span> <?php esc_html_e('Setup Wizard', 'astropress'); ?>
                    </a>
                </div>
                <div id="astropress-purge-cf-status" style="margin-top:10px;"></div>
                <div id="astropress-dash-handshake-status" style="margin-top:10px;"></div>
            </div>

            <div class="astropress-card">
                <h2><?php esc_html_e('Storage & Optimization Metrics', 'astropress'); ?></h2>
                <div class="astropress-metrics">
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($percentage); ?>%</span>
                        <span class="astropress-metric-label"><?php esc_html_e('Media Offloaded to R2', 'astropress'); ?></span>
                    </div>
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($offloaded); ?> / <?php echo esc_html($total_media); ?></span>
                        <span class="astropress-metric-label"><?php esc_html_e('Attachments Synced', 'astropress'); ?></span>
                    </div>
                    <div class="astropress-metric-box">
                        <span class="astropress-metric-val"><?php echo esc_html($saved_space); ?></span>
                        <span class="astropress-metric-label"><?php esc_html_e('Server Disk Space Saved', 'astropress'); ?></span>
                    </div>
                </div>

                <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;" />

                <h3><?php esc_html_e('Instant Edge Cache Purge', 'astropress'); ?></h3>
                <form method="post" action="">
                    <?php wp_nonce_field('astropress_purge_custom_action', 'astropress_purge_custom_nonce'); ?>
                    <div class="astropress-inline-form">
                        <input type="text" name="astropress_custom_url" placeholder="/blog/my-post or full URL" style="flex:1;" />
                        <button type="submit" name="astropress_purge_custom_url" class="button button-secondary"><?php esc_html_e('Purge Path', 'astropress'); ?></button>
                    </div>
                </form>

                <form method="post" action="" style="margin-top:10px;">
                    <?php wp_nonce_field('astropress_purge_global_action', 'astropress_purge_global_nonce'); ?>
                    <button type="submit" name="astropress_purge_global" class="button button-link-delete" onclick="return confirm('Purge entire website cache across all Cloudflare Edge POPs?');">
                        <?php esc_html_e('Purge Global Cache (All URLs)', 'astropress'); ?>
                    </button>
                </form>
            </div>
        </div>

        <?php
        $cf_token      = $this->core->get_cf_api_token();
        $zone_id       = $this->core->get_cf_zone_id();
        $r2_bucket     = AstroPress_Admin::get_r2_option('bucket', '');
        $is_configured = (!empty($cf_token) && !empty($frontend_url));
        ?>
        <div class="astropress-card" style="margin-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:15px;">
                <div style="display:flex; gap:12px; align-items:flex-start;">
                    <span class="dashicons dashicons-cloud" style="font-size:32px; width:32px; height:32px; color:<?php echo $is_configured ? '#059669' : '#f6821f'; ?>; margin-top:2px;"></span>
                    <div>
                        <h3 style="margin:0 0 4px 0;">
                            <?php echo $is_configured ? esc_html__('Cloudflare & Astro Architecture Status', 'astropress') : esc_html__('Setup & Architecture Incomplete', 'astropress'); ?>
                        </h3>
                        <p style="margin:0; color:#64748b;">
                            <?php if ($is_configured): ?>
                                <?php esc_html_e('Your headless WordPress instance is unified with Cloudflare Edge and Astro. Manage all credentials and pipeline settings via the Setup Wizard.', 'astropress'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Configure your single Cloudflare Scoped API Token, Astro frontend sync, and R2 media offloading through the unified Setup Wizard.', 'astropress'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="astropress-btn-group" style="display:flex; gap:8px; align-items:center;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php echo $is_configured ? esc_html__('Configure in Setup Wizard', 'astropress') : esc_html__('Launch Setup Wizard &rarr;', 'astropress'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=logs')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-list-view" style="vertical-align:-2px;"></span> <?php esc_html_e('Webhook Logs', 'astropress'); ?>
                    </a>
                </div>
            </div>

            <?php if ($is_configured): ?>
                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
                    <div style="flex:1 1 180px;">
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Astro Frontend', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a; word-break:break-all;"><?php echo esc_html($frontend_url); ?></span>
                    </div>
                    <div style="flex:1 1 180px;">
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Cloudflare Zone ID', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a;"><?php echo !empty($zone_id) ? esc_html(substr($zone_id, 0, 10) . '••••') : esc_html__('Not Configured', 'astropress'); ?></span>
                    </div>
                    <div style="flex:1 1 180px;">
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('R2 Bucket', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#0f172a;"><?php echo !empty($r2_bucket) ? esc_html($r2_bucket) : esc_html__('Local Only (Not Enabled)', 'astropress'); ?></span>
                    </div>
                    <div style="flex:1 1 180px;">
                        <strong style="font-size:11px; text-transform:uppercase; color:#64748b; display:block;"><?php esc_html_e('Cloudflare Scoped Token', 'astropress'); ?></strong>
                        <span style="font-size:13px; color:#059669; font-weight:600;"><span class="dashicons dashicons-shield-alt" style="font-size:15px; width:15px; height:15px; vertical-align:-2px;"></span> <?php esc_html_e('Connected & Masked', 'astropress'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
