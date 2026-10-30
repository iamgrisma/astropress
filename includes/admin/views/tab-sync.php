<?php
/**
 * Admin View: Media Sync & Batch Queue Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $client        = astropress()->get_r2_client();
        $is_configured = $client && $client->is_configured();
        $quality       = AstroPress_Optimizer::get_quality();
        $engine        = AstroPress_Optimizer::get_engine();
        ?>
        <div style="max-width:920px;">
            <?php if (!$is_configured): ?>
                <div class="notice notice-warning inline" style="margin: 0 0 20px 0; padding: 14px 18px; border-left-color: #d97706;">
                    <h3 style="margin: 0 0 6px 0; font-size: 15px; color: #92400e;">
                        <?php esc_html_e('Cloudflare R2 Credentials Incomplete', 'astropress'); ?>
                    </h3>
                    <p style="margin: 0; color: #78350f; font-size: 13px;">
                        <?php esc_html_e('Please enter your Cloudflare Account ID, Access Key ID, Secret Key, and Bucket Name in the R2 Storage tab before starting bulk sync.', 'astropress'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2')); ?>" class="button button-small" style="margin-left: 8px;">
                            <?php esc_html_e('Configure R2 Storage &rarr;', 'astropress'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <!-- 1. Stat Cards Grid -->
            <div class="r2g-stats-grid">
                <div class="r2g-stat-card">
                    <span class="r2g-stat-label"><?php esc_html_e('Total Media', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-total"><?php echo esc_html($stats['total_wp'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Attachments in Library', 'astropress'); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-success">
                    <span class="r2g-stat-label"><?php esc_html_e('Synced to R2', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-synced"><?php echo esc_html($stats['synced'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php printf(esc_html__('%d dual, %d cloud-only', 'astropress'), $stats['synced_both'] ?? 0, $stats['cloud_only'] ?? 0); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-info">
                    <span class="r2g-stat-label"><?php esc_html_e('Cloud Only', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-cloud"><?php echo esc_html($stats['cloud_only'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Server disk space freed', 'astropress'); ?></span>
                </div>

                <div class="r2g-stat-card r2g-card-warning">
                    <span class="r2g-stat-label"><?php esc_html_e('Local Only', 'astropress'); ?></span>
                    <div class="r2g-stat-value" id="r2g-stat-local"><?php echo esc_html($stats['local_only'] ?? 0); ?></div>
                    <span class="r2g-stat-sub"><?php esc_html_e('Awaiting push to R2', 'astropress'); ?></span>
                </div>
            </div>

            <!-- 2. Bulk Sync Tool Card -->
            <div class="astropress-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Bulk Media Sync Engine', 'astropress'); ?></h2>
                <p class="description"><?php esc_html_e('Push all unsynced media attachments to Cloudflare R2 in safe, progressive batches without server timeouts.', 'astropress'); ?></p>

                <!-- Batch Wildcard Configuration -->
                <div class="r2g-bulk-config-box" style="margin-bottom:18px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                    <h3 style="margin-top:0; font-size:13px; font-weight:700; color:#0f172a; margin-bottom:10px;"><?php esc_html_e('Batch Conversion & Sync Options', 'astropress'); ?></h3>
                    <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center;">
                        <div>
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Sync Preset:', 'astropress'); ?></label>
                            <select id="r2g-sync-preset" class="r2g-input" style="height:32px; font-size:12px; font-weight:600;">
                                <option value="keep_current"><?php esc_html_e('Keep Existing Files (Recommended)', 'astropress'); ?></option>
                                <option value="custom"><?php esc_html_e('Use Current Upload Defaults', 'astropress'); ?></option>
                                <option value="webp_balanced" selected><?php esc_html_e('WebP Balanced (82%, 1920px)', 'astropress'); ?></option>
                                <option value="webp_high"><?php esc_html_e('WebP High Quality (90%, 2560px)', 'astropress'); ?></option>
                                <option value="jpeg_balanced"><?php esc_html_e('JPEG Balanced (82%, 1920px)', 'astropress'); ?></option>
                                <option value="jpeg_high"><?php esc_html_e('JPEG High Quality (90%, 2560px)', 'astropress'); ?></option>
                                <option value="raw_lossless"><?php esc_html_e('Raw Offload (No Processing)', 'astropress'); ?></option>
                            </select>
                        </div>
                        <div id="r2g-sync-quality-wrap">
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Active Image Quality:', 'astropress'); ?></label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span id="r2g-sync-quality-val" class="r2g-quality-badge" style="font-weight:700; font-size:13px;"><?php echo esc_html($quality); ?>%</span>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=r2&sub=optimizer')); ?>" class="button button-small" style="font-size:11px;">
                                    <?php esc_html_e('Adjust in Optimizer &rarr;', 'astropress'); ?>
                                </a>
                                <input type="hidden" id="r2g-sync-quality-slider" value="<?php echo esc_attr($quality); ?>" />
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;"><?php esc_html_e('Processing Engine:', 'astropress'); ?></label>
                            <select id="r2g-sync-engine" class="r2g-input" style="height:32px; font-size:12px; font-weight:600;">
                                <option value="resmush" <?php selected($engine, 'resmush'); ?>><?php esc_html_e('reSmush.it Free API (Auto GD/Imagick fallback)', 'astropress'); ?></option>
                                <option value="server" <?php selected($engine, 'server'); ?>><?php esc_html_e('Server: PHP GD / Imagick (Native Background)', 'astropress'); ?></option>
                                <option value="none" <?php selected($engine, 'none'); ?>><?php esc_html_e('Raw Offload (Lossless)', 'astropress'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div id="r2g-sync-engine-notice" style="margin-top:12px; font-size:12px; color:#64748b; line-height:1.4;">
                        <span id="r2g-sync-notice-text"><?php esc_html_e('Server Engine: Media items are processed and pushed to R2 in safe batches of 5.', 'astropress'); ?></span>
                    </div>
                </div>

                <!-- Safety Guarantee Banner -->
                <div style="padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; margin-bottom:16px; font-size:12px; color:#1e40af; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-shield" style="font-size:18px; width:18px; height:18px; color:#2563eb;"></span>
                    <span><strong><?php esc_html_e('Data Safety Guarantee:', 'astropress'); ?></strong> <?php esc_html_e('Bulk sync NEVER deletes local files automatically. Your local server copies are strictly preserved until you verify and explicitly choose to clean them below.', 'astropress'); ?></span>
                </div>

                <div class="r2g-sync-container">
                    <div class="r2g-progress-wrap" style="display:none; margin-bottom:16px;" id="r2g-sync-progress-box">
                        <div class="r2g-progress-bar-bg">
                            <div class="r2g-progress-bar-fill" id="r2g-sync-progress-fill" style="width: 0%;"></div>
                        </div>
                        <div class="r2g-progress-meta" style="display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:6px;">
                            <span id="r2g-sync-status-text"><?php esc_html_e('Syncing media...', 'astropress'); ?></span>
                            <span id="r2g-sync-percentage" style="font-weight:600;">0%</span>
                        </div>
                    </div>

                    <div class="r2g-sync-actions" style="display:flex; gap:10px; align-items:center;">
                        <button type="button" id="r2g-btn-start-sync" class="button button-primary button-large" <?php echo !$is_configured ? 'disabled title="' . esc_attr__('Please complete R2 Storage configuration first', 'astropress') . '"' : ''; ?>>
                            <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Sync All Unsynced to R2', 'astropress'); ?>
                        </button>
                        <button type="button" id="r2g-btn-pause-sync" class="button button-secondary" style="display:none;">
                            <?php esc_html_e('Pause', 'astropress'); ?>
                        </button>
                        <button type="button" id="r2g-btn-cancel-sync" class="button button-link-delete" style="display:none; color:#dc2626; text-decoration:none;">
                            <?php esc_html_e('Cancel', 'astropress'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 3. Verified Storage Cleanup Card -->
            <div class="astropress-card" style="margin-top: 20px; border-left: 4px solid #10b981;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                    <div style="flex:1; min-width:280px;">
                        <h2 style="margin:0 0 6px 0; color:#065f46;"><?php esc_html_e('Verified Storage Cleanup (Free Web Hosting Disk Space)', 'astropress'); ?></h2>
                        <p class="description" style="margin-bottom:10px;">
                            <?php esc_html_e('Once attachments are verified on Cloudflare R2, you can safely clean local copies from your web hosting server to save disk space. Previews and responsive images will continue loading fast from your Cloudflare CDN domain.', 'astropress'); ?>
                        </p>
                        <div style="font-size:13px; color:#334155; font-weight:600; margin-bottom:12px;">
                            <?php esc_html_e('Verified on R2 with Local Copy:', 'astropress'); ?>
                            <span id="r2g-verified-count" style="font-size:14px; font-weight:700; color:#059669; padding:2px 8px; background:#ecfdf5; border-radius:4px; margin-left:4px;"><?php echo esc_html($stats['verified_with_local'] ?? 0); ?></span> <?php esc_html_e('attachments', 'astropress'); ?>
                        </div>
                    </div>
                    <div>
                        <button type="button" id="r2g-btn-clean-verified" class="button button-secondary button-large" <?php echo empty($stats['verified_with_local']) ? 'disabled' : ''; ?> style="border-color:#10b981; color:#065f46; font-weight:600;">
                            <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Clean Verified Local Files', 'astropress'); ?>
                        </button>
                    </div>
                </div>

                <div class="r2g-progress-wrap" style="display:none; margin-top:14px;" id="r2g-clean-progress-box">
                    <div class="r2g-progress-bar-bg">
                        <div class="r2g-progress-bar-fill" id="r2g-clean-progress-fill" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                    </div>
                    <div class="r2g-progress-meta" style="display:flex; justify-content:space-between; font-size:12px; color:#475569; margin-top:6px;">
                        <span id="r2g-clean-status-text"><?php esc_html_e('Cleaning verified local files...', 'astropress'); ?></span>
                        <span id="r2g-clean-percentage" style="font-weight:600;">0%</span>
                    </div>
                </div>
            </div>

            <!-- 4. Database Index Maintenance & Migration Card -->
            <div class="astropress-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Database Index Maintenance & Migration', 'astropress'); ?></h2>
                <p class="description"><?php esc_html_e('Tools to synchronize WordPress with existing offloaded media and rebuild the local index without costly Cloudflare R2 Class B list operations.', 'astropress'); ?></p>

                <div style="display:flex; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Import Existing Offload Records', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Scans previous plugin tables (wpmcs_items, r2_by_grisma) and existing attachment offload meta, registering them in AstroPress so CDN delivery and controls work instantly.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-import-legacy" class="button button-secondary">
                            <span class="dashicons dashicons-database-import" style="vertical-align:-2px;"></span> <?php esc_html_e('Import Existing Offloaded Media', 'astropress'); ?>
                        </button>
                        <div id="r2g-import-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>

                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Scan & Refresh Media Index', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Verifies local server files against the database table and repairs any missing records or desynced attachment states.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-reindex" class="button button-secondary">
                            <span class="dashicons dashicons-image-rotate" style="vertical-align:-2px;"></span> <?php esc_html_e('Scan & Refresh Index', 'astropress'); ?>
                        </button>
                        <div id="r2g-reindex-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>

                    <div style="flex:1; min-width:280px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#0f172a;"><?php esc_html_e('Live R2 Availability Audit', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#64748b; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Audits live CDN URLs via HEAD requests to confirm offloaded images exist in R2, and checks if server copies are still on disk.', 'astropress'); ?></p>
                        <button type="button" id="r2g-btn-audit-health" class="button button-secondary">
                            <span class="dashicons dashicons-heart" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit Media Health & Missing Files', 'astropress'); ?>
                        </button>
                        <div id="r2g-audit-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>

                    <div style="flex:1; min-width:280px; padding:16px; background:#fff1f2; border:1px solid #fecdd3; border-radius:6px;">
                        <h3 style="margin-top:0; font-size:14px; font-weight:600; color:#9f1239;"><?php esc_html_e('1-Click Legacy Database Cleanup', 'astropress'); ?></h3>
                        <p style="font-size:12px; color:#475569; margin-bottom:14px; line-height:1.5;"><?php esc_html_e('Safely prunes obsolete legacy plugin options (topnepali_*, r2g_*) and legacy postmeta after confirming modern AstroPress records exist.', 'astropress'); ?></p>
                        <button type="button" id="astropress-btn-clean-legacy" class="button button-secondary" style="border-color:#e11d48; color:#be123c;">
                            <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Clean Legacy Footprint', 'astropress'); ?>
                        </button>
                        <div id="astropress-legacy-clean-status" style="margin-top:8px; font-size:12px;"></div>
                    </div>
                </div>
            </div>
        </div>
