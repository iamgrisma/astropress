<?php
/**
 * Admin View: Cloudflare R2 Credentials & Storage Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $account_id    = AstroPress_Admin::get_r2_option('account_id', '');
        $access_key    = AstroPress_Admin::get_r2_option('access_key', '');
        $raw_secret    = AstroPress_Admin::get_r2_option('secret_key', '');
        $secret_key    = !empty($raw_secret) ? AstroPress_Encryption::decrypt($raw_secret) : '';
        $bucket        = AstroPress_Admin::get_r2_option('bucket', '');
        $custom_domain = AstroPress_Admin::get_r2_option('custom_domain', '');
        $storage_mode  = AstroPress_Admin::get_r2_option('storage_mode', 'both');
        $cf_token      = $this->core->get_cf_api_token();
        $allowed_roots = $this->get_allowed_domain_roots();
        $allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';
        ?>
        <div class="astropress-card" style="max-width:850px; margin-bottom:20px; background:#f8fafc; border-left:4px solid #f6821f;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span class="dashicons dashicons-cloud" style="font-size:28px; width:28px; height:28px; color:#f6821f;"></span>
                    <div>
                        <h4 style="margin:0; font-size:14px;"><?php esc_html_e('Unified Cloudflare Token Pipeline', 'astropress'); ?></h4>
                        <p style="margin:2px 0 0 0; color:#64748b; font-size:12px;">
                            <?php if (!empty($cf_token)): ?>
                                <?php esc_html_e('Cloudflare Scoped Token is active. R2 media storage is authenticated natively without requiring AWS S3 keys.', 'astropress'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Connect your single Cloudflare Scoped Token in the Setup Wizard for zero-config media offloading.', 'astropress'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=onboarding')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-admin-settings" style="vertical-align:-2px;"></span> <?php esc_html_e('Setup Wizard &rarr;', 'astropress'); ?>
                </a>
            </div>
        </div>

        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Cloudflare R2 Media Storage & CDN Delivery', 'astropress'); ?></h2>
            <p><?php esc_html_e('Offload media library uploads to zero-egress Cloudflare R2 object storage with fast public CDN delivery.', 'astropress'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_r2_action', 'astropress_r2_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="r2_account_id"><?php esc_html_e('Cloudflare Account ID', 'astropress'); ?></label></th>
                        <td>
                            <input type="text" id="r2_account_id" name="r2_account_id" value="<?php echo esc_attr($account_id); ?>" class="regular-text" placeholder="32-character hex Account ID" />
                            <p class="description"><?php esc_html_e('Auto-detected from your Cloudflare Scoped Token or found in Cloudflare Overview.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_bucket"><?php esc_html_e('R2 Bucket Name', 'astropress'); ?></label></th>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                                <input type="text" id="r2_bucket" name="r2_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" style="flex:1; min-width:200px;" placeholder="e.g. topnepali" />
                                <select id="r2_bucket_select" class="regular-text" style="display:none; flex:1; min-width:200px;">
                                    <option value=""><?php esc_html_e('-- Choose Discovered Bucket --', 'astropress'); ?></option>
                                    <?php if (!empty($bucket)): ?>
                                        <option value="<?php echo esc_attr($bucket); ?>" selected><?php echo esc_html($bucket); ?></option>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="button button-secondary" id="astropress-fetch-buckets">
                                    <span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> <?php esc_html_e('Discover Buckets', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-btn-show-create-bucket">
                                    <span class="dashicons dashicons-plus-alt2" style="vertical-align:-2px;"></span> <?php esc_html_e('+ Create Bucket', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-link" id="astropress-btn-toggle-bucket-input" style="display:none; text-decoration:none; font-size:12px;">
                                    <?php esc_html_e('Type Manually', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-test-r2">
                                    <span class="dashicons dashicons-yes" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Connection', 'astropress'); ?>
                                </button>
                            </div>

                            <!-- Inline Create Bucket Box -->
                            <div id="astropress-create-bucket-wrap" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:14px; margin-top:10px; max-width:650px;">
                                <label for="astropress_new_bucket_name" style="display:block; font-size:13px; font-weight:600; color:#166534; margin-bottom:6px;">
                                    <?php esc_html_e('Create New Cloudflare R2 Bucket:', 'astropress'); ?>
                                </label>
                                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                    <input type="text" id="astropress_new_bucket_name" placeholder="lowercase letters, numbers, hyphens" class="regular-text" style="flex:1; min-width:200px;" />
                                    <button type="button" id="astropress-btn-confirm-create-bucket" class="button button-primary">
                                        <?php esc_html_e('Create & Use Bucket', 'astropress'); ?>
                                    </button>
                                    <button type="button" id="astropress-btn-cancel-create-bucket" class="button button-secondary">
                                        <?php esc_html_e('Cancel', 'astropress'); ?>
                                    </button>
                                </div>
                                <div id="astropress-create-bucket-status" style="margin-top:8px; font-size:12px;"></div>
                            </div>

                            <div id="astropress-r2-status" style="margin-top:8px; font-size:12px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="r2_custom_domain"><?php esc_html_e('Public CDN Domain', 'astropress'); ?></label></th>
                        <td>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; max-width:650px;">
                                <input type="text" id="r2_custom_domain" name="r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" placeholder="media.example.com" style="flex:1; min-width:240px;" />
                                <button type="button" class="button button-secondary" id="r2-audit-cdn-btn">
                                    <span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> <?php esc_html_e('Audit CDN & Rules', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="astropress-btn-deploy-cache-rule">
                                    <span class="dashicons dashicons-cloud" style="vertical-align:-2px;"></span> <?php esc_html_e('Deploy Cache Rule', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="r2-purge-cdn-btn">
                                    <span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> <?php esc_html_e('Purge CDN Cache', 'astropress'); ?>
                                </button>
                            </div>
                            <p class="description" id="astropress-domain-hint">
                                <?php printf(
                                    esc_html__('Custom CDN subdomain hooked to your R2 bucket. Must be a subdomain of %s (e.g. media.%s).', 'astropress'),
                                    '<code>' . esc_html($allowed_roots_str) . '</code>',
                                    esc_html($allowed_roots[0] ?? 'example.com')
                                ); ?>
                            </p>
                            <div id="r2-cdn-audit-box" style="display:none; margin-top:10px; max-width:650px;"></div>
                            <div id="astropress-deploy-rule-status" style="margin-top:8px; font-size:12px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Storage Delivery Mode', 'astropress'); ?></th>
                        <td>
                            <label><input type="radio" name="r2_storage_mode" value="both" <?php checked($storage_mode, 'both'); ?> /> <strong><?php esc_html_e('Dual Storage (Recommended)', 'astropress'); ?></strong> — <?php esc_html_e('Store files on both local server and R2. Fast CDN delivery with zero local data loss risk.', 'astropress'); ?></label><br />
                            <label><input type="radio" name="r2_storage_mode" value="r2_only" <?php checked($storage_mode, 'r2_only'); ?> /> <strong><?php esc_html_e('R2 Only (Disk Saver)', 'astropress'); ?></strong> — <?php esc_html_e('Delete local media file after successful upload to R2 to conserve hosting disk space.', 'astropress'); ?></label><br />
                            <label><input type="radio" name="r2_storage_mode" value="local_only" <?php checked($storage_mode, 'local_only'); ?> /> <strong><?php esc_html_e('Local Only', 'astropress'); ?></strong> — <?php esc_html_e('Bypass R2 uploads completely.', 'astropress'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Local Retention Policy', 'astropress'); ?></th>
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

                <!-- Optional Collapsible Details for Legacy S3 Credentials -->
                <details style="margin:20px 0; border:1px dashed #cbd5e1; border-radius:6px; padding:12px 14px; background:#f8fafc; max-width:850px;">
                    <summary style="cursor:pointer; font-size:12px; font-weight:600; color:#475569;">
                        <?php esc_html_e('Advanced: Manual AWS S3 Credentials (Optional - Not needed when using Scoped Token)', 'astropress'); ?>
                    </summary>
                    <table class="form-table" style="margin-top:10px;">
                        <tr>
                            <th scope="row"><label for="r2_access_key"><?php esc_html_e('R2 Access Key ID', 'astropress'); ?></label></th>
                            <td>
                                <input type="text" id="r2_access_key" name="r2_access_key" value="<?php echo esc_attr($access_key); ?>" class="regular-text" placeholder="Optional" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="r2_secret_key"><?php esc_html_e('R2 Secret Access Key', 'astropress'); ?></label></th>
                            <td>
                                <?php if (!empty($secret_key)): ?>
                                    <div id="r2-secret-display" style="display:flex; align-items:center; gap:8px;">
                                        <input type="text" value="••••••••••••••••••••••••" class="regular-text" disabled style="background:#f8fafc;" />
                                        <button type="button" id="r2-btn-change-secret" class="button button-small"><?php esc_html_e('Change Key', 'astropress'); ?></button>
                                    </div>
                                    <div id="r2-secret-input-wrap" style="display:none; margin-top:8px;">
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <input type="password" id="r2_secret_key" name="r2_secret_key" class="regular-text" placeholder="<?php esc_attr_e('Enter new Secret Access Key', 'astropress'); ?>" autocomplete="new-password" />
                                            <button type="button" id="r2-btn-cancel-secret" class="button button-secondary button-small"><?php esc_html_e('Cancel', 'astropress'); ?></button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="password" id="r2_secret_key" name="r2_secret_key" class="regular-text" placeholder="<?php esc_attr_e('Optional', 'astropress'); ?>" autocomplete="new-password" />
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </details>

                <p class="submit">
                    <button type="submit" name="astropress_save_r2" class="button button-primary"><?php esc_html_e('Save R2 Configuration', 'astropress'); ?></button>
                </p>
            </form>
        </div>
