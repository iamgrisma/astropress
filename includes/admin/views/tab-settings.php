<?php
/**
 * Admin View: General Settings & Automation Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $cf_deploy_hook = $this->core->get_cf_deploy_hook();
        $cf_token       = $this->core->get_cf_api_token();
        $cf_zone_id     = $this->core->get_cf_zone_id();
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('Automation & Cache Invalidation Policies', 'astropress'); ?></h2>
            <p><?php esc_html_e('Configure automatic edge cache invalidation triggers and headless SEO headers.', 'astropress'); ?></p>

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
                        <th scope="row"><?php esc_html_e('SEO & Subdomain Shield', 'astropress'); ?></th>
                        <td>
                            <p class="description" style="margin-top:0;">
                                <?php esc_html_e('Permalink rewrites, View/Preview buttons, 301 Subdomain Shield, and Noindex headers are managed in the dedicated', 'astropress'); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=seo')); ?>"><strong><?php esc_html_e('SEO & Security tab &rarr;', 'astropress'); ?></strong></a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="warm_delay"><?php esc_html_e('Warmer Pacing (ms)', 'astropress'); ?></label></th>
                        <td>
                            <input type="number" id="warm_delay" name="warm_delay" value="<?php echo esc_attr($this->core->get_warm_delay()); ?>" class="small-text" min="200" max="5000" step="100" /> ms
                            <p class="description"><?php esc_html_e('Pause between warming requests to prevent edge CPU spikes.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="warm_batch_size"><?php esc_html_e('Warmer Batch Size', 'astropress'); ?></label></th>
                        <td>
                            <input type="number" id="warm_batch_size" name="warm_batch_size" value="<?php echo esc_attr($this->core->get_warm_batch_size()); ?>" class="small-text" min="1" max="10" />
                            <p class="description"><?php esc_html_e('URLs per concurrent warming request.', 'astropress'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="astropress_cf_deploy_hook"><?php esc_html_e('Cloudflare Pages Deploy Hook', 'astropress'); ?></label></th>
                        <td>
                            <div class="astropress-inline-form">
                                <input type="url" id="astropress_cf_deploy_hook" name="cf_deploy_hook" value="<?php echo esc_attr($cf_deploy_hook); ?>" class="regular-text" placeholder="https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/..." />
                                <button type="button" class="button" id="astropress-trigger-deploy-now"><?php esc_html_e('Trigger Deploy', 'astropress'); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Optional: Trigger a static site build in Cloudflare Pages when a full cache purge is requested.', 'astropress'); ?></p>
                            <div id="astropress-deploy-status" style="margin-top:8px;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="astropress_revalidate_secret"><?php esc_html_e('Astro Webhook Secret', 'astropress'); ?></label></th>
                        <td>
                            <div class="astropress-inline-form" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <input type="password" id="astropress_revalidate_secret" name="revalidate_secret" value="<?php echo $this->core->has_custom_secret() ? '••••••••••••••••' : ''; ?>" class="regular-text" placeholder="Auto-secured (topnepali_revalidate_secure_token)" autocomplete="new-password" />
                                <button type="button" class="button button-secondary" id="astropress-test-key-btn">
                                    <span class="dashicons dashicons-admin-network" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Secret Handshake', 'astropress'); ?>
                                </button>
                                <span id="astropress-key-badge" style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:600; border:1px solid #cbd5e1; background:#f8fafc; color:#64748b;">
                                    <?php echo $this->core->has_custom_secret() ? esc_html__('Custom Secret Stored', 'astropress') : esc_html__('Default Auto-Secured', 'astropress'); ?>
                                </span>
                            </div>
                            <p class="description"><?php esc_html_e('Optional: Zero-config default token is automatically active. Only enter a custom value if you explicitly defined REVALIDATE_SECRET in your Cloudflare Pages dashboard.', 'astropress'); ?></p>
                            <div id="astropress-key-test-feedback" style="margin-top:10px;"></div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_automation" class="button button-primary"><?php esc_html_e('Save Settings', 'astropress'); ?></button>
                </p>
            </form>

            <hr style="margin:35px 0 25px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Danger Zone: Disconnect & Reset -->
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:18px;">
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
        </div>
