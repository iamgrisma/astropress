<?php
/**
 * Admin View: Cloudflare R2 Storage & Media Sync Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : '';
if (empty($sub) && isset($_GET['tab']) && $_GET['tab'] === 'sync') {
    $sub = 'sync';
}
if ($sub !== 'sync') {
    $sub = 'policies';
}

$account_id         = AstroPress_Admin::get_r2_option('account_id', '');
$bucket             = AstroPress_Admin::get_r2_option('bucket', '');
$custom_domain      = AstroPress_Admin::get_r2_option('custom_domain', '');
$storage_mode       = AstroPress_Admin::get_r2_option('storage_mode', 'both');
$stats              = AstroPress_Database::get_stats();
$is_r2_ready        = !empty($bucket);
$cached_discovery   = get_transient('astropress_cf_discovery_cache');
$cached_buckets     = (!empty($cached_discovery['buckets']) && is_array($cached_discovery['buckets'])) ? $cached_discovery['buckets'] : array();
$allowed_roots      = $this->get_allowed_domain_roots();
$allowed_roots_str  = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';
?>

<!-- R2 Sub-navigation Bar -->
<div class="astropress-tools-nav" style="display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=policies')); ?>" 
       class="button <?php echo ($sub === 'policies') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php esc_html_e('R2 Storage & Credentials', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=sync')); ?>" 
       class="button <?php echo ($sub === 'sync') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Media Library Sync & Bulk Offload', 'astropress'); ?>
    </a>
</div>

<?php if ($sub === 'sync'): ?>
    <?php $this->render_tab_sync($stats); ?>
<?php else: ?>
    <!-- Connected Pipeline Status Overview Card -->
    <div class="astropress-card" style="max-width:920px; margin-bottom:20px; background:#f8fafc; border-left:4px solid #0284c7;">
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
                        <strong><?php esc_html_e('Active Bucket:', 'astropress'); ?></strong> <code id="r2-active-bucket-label"><?php echo esc_html($bucket ?: esc_html__('None', 'astropress')); ?></code> &bull; 
                        <strong><?php esc_html_e('CDN Host:', 'astropress'); ?></strong> <code id="r2-active-domain-label"><?php echo esc_html($custom_domain ?: esc_html__('Not Set', 'astropress')); ?></code>
                        <?php if (!empty($account_id)): ?>
                            &bull; <strong><?php esc_html_e('Account:', 'astropress'); ?></strong> <code><?php echo esc_html(substr($account_id, 0, 8)); ?>••••</code>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <button type="button" class="button button-secondary" id="astropress-test-r2">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Connection', 'astropress'); ?>
                </button>
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

    <!-- Storage Configuration & Policies Form -->
    <div class="astropress-card" style="max-width:920px;">
        <h2><?php esc_html_e('R2 Storage Configuration & Delivery Policies', 'astropress'); ?></h2>
        <p><?php esc_html_e('Manage your active R2 bucket, public CDN subdomain, and offloading automation.', 'astropress'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('astropress_r2_action', 'astropress_r2_nonce'); ?>

            <table class="form-table">
                <!-- R2 Bucket Selection -->
                <tr>
                    <th scope="row"><label for="r2_bucket"><?php esc_html_e('R2 Bucket', 'astropress'); ?></label></th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:680px;">
                            <select id="r2_bucket" name="r2_bucket" class="regular-text" style="flex:1; min-width:220px;">
                                <?php if (!empty($bucket)): ?>
                                    <option value="<?php echo esc_attr($bucket); ?>" selected><?php echo esc_html($bucket); ?> (Current Active)</option>
                                <?php else: ?>
                                    <option value=""><?php esc_html_e('-- Select or Discover Bucket --', 'astropress'); ?></option>
                                <?php endif; ?>
                                <?php foreach ($cached_buckets as $cb): ?>
                                    <?php if ($cb !== $bucket): ?>
                                        <option value="<?php echo esc_attr($cb); ?>"><?php echo esc_html($cb); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="button button-secondary" id="astropress-fetch-buckets">
                                <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Discover Buckets', 'astropress'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="r2-btn-show-create-bucket">
                                <span class="dashicons dashicons-plus-alt2" style="vertical-align:-2px;"></span> <?php esc_html_e('+ Create Bucket', 'astropress'); ?>
                            </button>
                        </div>
                        <div id="r2-bucket-discover-status" style="margin-top:6px; font-size:12px;"></div>

                        <!-- Inline Create Bucket Drawer -->
                        <div id="r2-create-bucket-wrap" style="display:none; margin-top:10px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; max-width:650px;">
                            <label for="r2_new_bucket_name" style="display:block; margin-bottom:4px; font-weight:600; font-size:12px;">
                                <?php esc_html_e('New R2 Bucket Name (lowercase, numbers, hyphens):', 'astropress'); ?>
                            </label>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <input type="text" id="r2_new_bucket_name" placeholder="my-media-bucket" class="regular-text" style="flex:1;" />
                                <button type="button" id="r2-btn-confirm-create-bucket" class="button button-primary button-small"><?php esc_html_e('Create & Select', 'astropress'); ?></button>
                                <button type="button" id="r2-btn-cancel-create-bucket" class="button button-secondary button-small"><?php esc_html_e('Cancel', 'astropress'); ?></button>
                            </div>
                            <div id="r2-create-bucket-status" style="margin-top:6px; font-size:12px;"></div>
                        </div>
                    </td>
                </tr>

                <!-- Public CDN Custom Domain -->
                <tr>
                    <th scope="row"><label for="r2_custom_domain"><?php esc_html_e('Public CDN Domain', 'astropress'); ?></label></th>
                    <td>
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:680px;">
                            <input type="text" id="r2_custom_domain" name="r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" placeholder="media.example.com" style="flex:1; min-width:220px;" />
                            <button type="button" class="button button-secondary" id="r2-detect-cdn-btn">
                                <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Auto-Detect Attached', 'astropress'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="r2-attach-cname-btn">
                                <span class="dashicons dashicons-admin-links" style="vertical-align:-2px;"></span> <?php esc_html_e('Attach & Setup CNAME', 'astropress'); ?>
                            </button>
                        </div>
                        <div id="r2-cdn-detect-status" style="margin-top:8px;"></div>
                        <p class="description">
                            <?php printf(
                                esc_html__('Custom CDN subdomain linked to your R2 bucket. Must be a subdomain of %s (e.g. media.%s).', 'astropress'),
                                '<code>' . esc_html($allowed_roots_str) . '</code>',
                                esc_html($allowed_roots[0] ?? 'example.com')
                            ); ?>
                        </p>
                    </td>
                </tr>

                <!-- Storage Delivery Mode -->
                <tr>
                    <th scope="row"><?php esc_html_e('Storage Delivery Mode', 'astropress'); ?></th>
                    <td>
                        <label><input type="radio" name="r2_storage_mode" value="both" <?php checked($storage_mode, 'both'); ?> /> <strong><?php esc_html_e('Dual Storage (Recommended)', 'astropress'); ?></strong> — <?php esc_html_e('Store files on both local hosting server and Cloudflare R2. Global CDN delivery with zero local data loss risk.', 'astropress'); ?></label><br />
                        <label><input type="radio" name="r2_storage_mode" value="r2_only" <?php checked($storage_mode, 'r2_only'); ?> /> <strong><?php esc_html_e('R2 Only (Disk Saver)', 'astropress'); ?></strong> — <?php esc_html_e('Delete local media file after successful upload to R2 to conserve hosting server disk space.', 'astropress'); ?></label><br />
                        <label><input type="radio" name="r2_storage_mode" value="local_only" <?php checked($storage_mode, 'local_only'); ?> /> <strong><?php esc_html_e('Local Only', 'astropress'); ?></strong> — <?php esc_html_e('Bypass R2 uploads completely and serve directly from WordPress uploads directory.', 'astropress'); ?></label>
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
                <button type="submit" name="astropress_save_r2" class="button button-primary"><?php esc_html_e('Save R2 Configuration', 'astropress'); ?></button>
            </p>
        </form>
    </div>
<?php endif; ?>
