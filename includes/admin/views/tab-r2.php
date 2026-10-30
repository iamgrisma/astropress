<?php
/**
 * Admin View: Cloudflare R2 Storage & Media Management Tab
 *
 * Unified hub for R2 Storage Policies, Image Optimization & Formats,
 * and Bulk Media Library Sync & Cleanup.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'policies';
if (!in_array($sub, array('policies', 'optimizer', 'sync'), true)) {
    $sub = 'policies';
}

$account_id        = AstroPress_Admin::get_r2_option('account_id', '');
$bucket            = AstroPress_Admin::get_r2_option('bucket', '');
$custom_domain     = AstroPress_Admin::get_r2_option('custom_domain', '');
$storage_mode      = AstroPress_Admin::get_r2_option('storage_mode', 'both');
$stats             = AstroPress_Database::get_stats();
$is_r2_ready       = !empty($bucket);
$mode_labels       = array(
    'both'       => __('Dual Storage (Local + Cloud R2)', 'astropress'),
    'r2_only'    => __('R2 Only (Disk Saver)', 'astropress'),
    'local_only' => __('Local Only', 'astropress'),
);
$active_mode_label = $mode_labels[$storage_mode] ?? __('Dual Storage', 'astropress');
?>

<!-- Media & Storage Sub-navigation Bar -->
<div class="astropress-tools-nav" style="display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px; flex-wrap:wrap;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=policies')); ?>" 
       class="button <?php echo ($sub === 'policies') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-database" style="vertical-align:-2px;"></span> <?php esc_html_e('R2 Pipeline & Delivery Policies', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=optimizer')); ?>" 
       class="button <?php echo ($sub === 'optimizer') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-format-image" style="vertical-align:-2px;"></span> <?php esc_html_e('Image Optimizer & Formats', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=sync')); ?>" 
       class="button <?php echo ($sub === 'sync') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Bulk Media Sync & Cleanup', 'astropress'); ?>
    </a>
</div>

<?php if ($sub === 'optimizer'): ?>
    <?php include ASTROPRESS_PATH . 'includes/admin/views/tab-optimizer.php'; ?>
<?php elseif ($sub === 'sync'): ?>
    <div id="sync" style="scroll-margin-top:20px; max-width:960px;">
        <?php include ASTROPRESS_PATH . 'includes/admin/views/tab-sync.php'; ?>
    </div>
<?php else: ?>
    <!-- Section 1: Read-Only Pipeline Status Summary -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px; background:#f8fafc; border-left:4px solid #0284c7;">
        <input type="hidden" id="r2_account_id" value="<?php echo esc_attr($account_id); ?>" />
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="dashicons dashicons-database" style="font-size:32px; width:32px; height:32px; color:#0284c7;"></span>
                <div>
                    <h4 style="margin:0; font-size:15px; display:flex; align-items:center; gap:8px;">
                        <span><?php esc_html_e('Cloudflare R2 Object Storage Pipeline', 'astropress'); ?></span>
                        <?php if ($is_r2_ready): ?>
                            <span class="astropress-badge is-connected" style="font-size:11px;"><?php esc_html_e('Connected', 'astropress'); ?></span>
                        <?php else: ?>
                            <span class="astropress-badge" style="font-size:11px; background:#fef2f2; color:#991b1b;"><?php esc_html_e('Not Configured', 'astropress'); ?></span>
                        <?php endif; ?>
                    </h4>
                    <p style="margin:4px 0 0 0; color:#475569; font-size:13px;">
                        <strong><?php esc_html_e('Active R2 Bucket:', 'astropress'); ?></strong> <code id="r2-active-bucket-label"><?php echo esc_html($bucket ?: esc_html__('None', 'astropress')); ?></code> &bull; 
                        <strong><?php esc_html_e('Active CDN Domain:', 'astropress'); ?></strong> <code id="r2-active-domain-label"><?php echo esc_html($custom_domain ?: esc_html__('Origin Uploads', 'astropress')); ?></code> &bull; 
                        <strong><?php esc_html_e('Storage Mode:', 'astropress'); ?></strong> <span><?php echo esc_html($active_mode_label); ?></span>
                    </p>
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding#step-3')); ?>" class="button button-secondary">
                    <?php esc_html_e('Reconfigure R2 in Setup Wizard &rarr;', 'astropress'); ?>
                </a>
                <?php if ($is_r2_ready): ?>
                    <button type="button" class="button button-secondary" id="r2-purge-cdn-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge CDN Cache', 'astropress'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="r2-audit-cdn-btn">
                        <span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit Rules', 'astropress'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div id="astropress-r2-status" style="margin-top:10px; font-size:12px;"></div>
        <div id="r2-cdn-audit-box" style="display:none; margin-top:10px;"></div>
    </div>

    <!-- Section 2: Storage Delivery Policies & Automation -->
    <div class="astropress-card" style="max-width:960px; margin-bottom:20px;">
        <h2><?php esc_html_e('Storage Delivery Policies & Automation', 'astropress'); ?></h2>
        <p style="color:#64748b; font-size:13px; margin-top:0;"><?php esc_html_e('Control media storage delivery, local disk retention pruning, and automatic synchronization.', 'astropress'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('astropress_r2_action', 'astropress_r2_nonce'); ?>
            <input type="hidden" name="r2_bucket" value="<?php echo esc_attr($bucket); ?>" />
            <input type="hidden" name="r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" />

            <table class="form-table">
                <!-- Storage Delivery Mode -->
                <tr>
                    <th scope="row"><?php esc_html_e('Storage Delivery Mode', 'astropress'); ?></th>
                    <td>
                        <label><input type="radio" name="r2_storage_mode" value="both" <?php checked($storage_mode, 'both'); ?> /> <strong><?php esc_html_e('Dual Storage (Recommended)', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Store files on both local hosting server and Cloudflare R2. Global CDN delivery with zero local data loss risk.', 'astropress'); ?></label><br />
                        <label><input type="radio" name="r2_storage_mode" value="r2_only" <?php checked($storage_mode, 'r2_only'); ?> /> <strong><?php esc_html_e('R2 Only (Disk Saver)', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Delete local media file after successful upload to R2 to conserve hosting server disk space.', 'astropress'); ?></label><br />
                        <label><input type="radio" name="r2_storage_mode" value="local_only" <?php checked($storage_mode, 'local_only'); ?> /> <strong><?php esc_html_e('Local Only', 'astropress'); ?></strong> &mdash; <?php esc_html_e('Bypass R2 uploads completely and serve directly from WordPress uploads directory.', 'astropress'); ?></label>
                    </td>
                </tr>

                <!-- Local Retention Policy -->
                <tr>
                    <th scope="row"><label for="astropress_retention_days"><?php esc_html_e('Local Retention Policy', 'astropress'); ?></label></th>
                    <td>
                        <?php $retention_days = (int) get_option('astropress_retention_days', 0); ?>
                        <select name="astropress_retention_days" id="astropress_retention_days">
                            <option value="0" <?php selected($retention_days, 0); ?>><?php esc_html_e('Disabled (Retain local files indefinitely)', 'astropress'); ?></option>
                            <option value="7" <?php selected($retention_days, 7); ?>><?php esc_html_e('7 Days (Prune local media older than 1 week after R2 sync)', 'astropress'); ?></option>
                            <option value="14" <?php selected($retention_days, 14); ?>><?php esc_html_e('14 Days (Prune local media older than 2 weeks after R2 sync)', 'astropress'); ?></option>
                            <option value="30" <?php selected($retention_days, 30); ?>><?php esc_html_e('30 Days (Prune local media older than 1 month after R2 sync)', 'astropress'); ?></option>
                            <option value="60" <?php selected($retention_days, 60); ?>><?php esc_html_e('60 Days (Prune local media older than 2 months after R2 sync)', 'astropress'); ?></option>
                            <option value="90" <?php selected($retention_days, 90); ?>><?php esc_html_e('90 Days (Prune local media older than 3 months after R2 sync)', 'astropress'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Automatically prunes local server files via daily WP-Cron once verified safely offloaded to Cloudflare R2.', 'astropress'); ?></p>
                    </td>
                </tr>

                <!-- Automation Policies -->
                <tr>
                    <th scope="row"><?php esc_html_e('Automation Policies', 'astropress'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="r2_auto_upload" value="1" <?php checked((bool) AstroPress_Admin::get_r2_option('auto_upload', 1)); ?> /> <?php esc_html_e('Automatically upload new media attachments to R2 on upload', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="r2_rewrite_urls" value="1" <?php checked((bool) AstroPress_Admin::get_r2_option('rewrite_urls', 1)); ?> /> <?php esc_html_e('Rewrite attachment URLs and srcset to use the R2 Custom CDN Domain', 'astropress'); ?></label><br />
                            <label><input type="checkbox" name="r2_delete_from_r2" value="1" <?php checked((bool) AstroPress_Admin::get_r2_option('delete_from_r2', 1)); ?> /> <?php esc_html_e('Delete file from R2 when deleted permanently from WordPress Media Library', 'astropress'); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="astropress_save_r2" class="button button-primary"><?php esc_html_e('Save Delivery Policies', 'astropress'); ?></button>
            </p>
        </form>
    </div>
<?php endif; ?>
