<?php
/**
 * Admin View: Gated Setup Wizard Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $checks        = $this->get_preflight_checks();
        $cf_token      = $this->core->get_cf_api_token();
        $cf_zone_id    = $this->core->get_cf_zone_id();
        $account_id    = get_option('astropress_r2_account_id', get_option('r2g_account_id', ''));
        $bucket        = get_option('astropress_r2_bucket', get_option('r2g_bucket', ''));
        $custom_domain = get_option('astropress_r2_custom_domain', get_option('r2g_custom_domain', ''));
        $media_mode    = get_option(AstroPress::OPTION_MEDIA_MODE, 'wp');
        $allowed_roots = $this->get_allowed_domain_roots();
        $allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';

        // Auto-derive root frontend URL if empty or identical to WP backend
        if (empty($frontend_url) || $frontend_url === home_url()) {
            $frontend_url = $this->core->derive_frontend_url();
        }

        // Check cached discovery
        $cached_discovery = get_transient('astropress_cf_discovery_cache');
        $cached_buckets   = !empty($cached_discovery['buckets']) ? $cached_discovery['buckets'] : array();
        // System readiness check summary (Flat, 0-bloat, replaces bloated 3-column preflight grid)
        $all_pass = true;
        $failed_reasons = array();
        foreach ($checks as $c) {
            if ($c['status'] === 'fail') {
                $all_pass = false;
                $failed_reasons[] = $c['label'] . ': ' . $c['message'];
            }
        }
        ?>
        <div class="astropress-wizard">
            <header class="wizard-header">
                <span class="dashicons dashicons-cloud"></span>
                <h2><?php esc_html_e('Welcome to AstroPress v2.0 Setup Wizard', 'astropress'); ?></h2>
                <p><?php esc_html_e('Connect WordPress to Cloudflare Edge in 3 effortless steps.', 'astropress'); ?></p>
            </header>

            <?php if ($all_pass): ?>
                <div class="wizard-system-status is-pass">
                    <span class="dashicons dashicons-yes-alt text-success"></span>
                    <span><strong><?php esc_html_e('Pre-Flight Verified:', 'astropress'); ?></strong> <?php echo esc_html(sprintf(__('PHP %s, cURL, OpenSSL & Image Engines active.', 'astropress'), PHP_VERSION)); ?></span>
                </div>
            <?php else: ?>
                <div class="wizard-system-status is-fail">
                    <span class="dashicons dashicons-warning"></span>
                    <span><strong><?php esc_html_e('Environment Notice:', 'astropress'); ?></strong> <?php echo esc_html(implode('; ', $failed_reasons)); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="astropress-onboarding-form" class="wizard-form">
                <?php wp_nonce_field('astropress_onboarding_action', 'astropress_onboarding_nonce'); ?>

                <!-- STEP 1: Cloudflare Scoped Token -->
                <section class="wizard-section">
                    <header class="section-header">
                        <span class="step-badge">1</span>
                        <div>
                            <h3><?php esc_html_e('Cloudflare Scoped API Token', 'astropress'); ?></h3>
                            <p><?php esc_html_e('One token to power Edge Cache Purge, Cache Rules, DNS, and R2 Storage.', 'astropress'); ?></p>
                        </div>
                    </header>

                    <div class="wizard-actions">
                        <a href="<?php echo esc_url($this->get_cf_token_template_url()); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                            <span class="dashicons dashicons-external"></span> <?php esc_html_e('Create Scoped Token on Cloudflare', 'astropress'); ?> &rarr;
                        </a>
                        <button type="button" class="button button-link" id="onboard-toggle-blueprint-btn">
                            <span class="dashicons dashicons-editor-help"></span> <?php esc_html_e('View Scopes Blueprint', 'astropress'); ?>
                        </button>
                    </div>

                    <!-- Blueprint Table (Collapsible) -->
                    <div id="onboard-blueprint-wrap" class="wizard-blueprint" style="display:none;">
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Category', 'astropress'); ?></th>
                                    <th><?php esc_html_e('Scope', 'astropress'); ?></th>
                                    <th><?php esc_html_e('Access', 'astropress'); ?></th>
                                    <th><?php esc_html_e('Purpose in AstroPress', 'astropress'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->get_cf_permissions_blueprint() as $p): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($p['category']); ?></strong></td>
                                        <td><code><?php echo esc_html($p['name']); ?></code></td>
                                        <td><span class="astropress-badge"><?php echo esc_html($p['access']); ?></span></td>
                                        <td><?php echo esc_html($p['desc']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Token Connected Card (Shown when token is saved/verified) -->
                    <div id="onboard-token-connected-card" class="wizard-connected-card" style="<?php echo !empty($cf_token) ? '' : 'display:none;'; ?>">
                        <div class="connected-info">
                            <span class="dashicons dashicons-shield-alt text-success"></span>
                            <div>
                                <strong><?php esc_html_e('Cloudflare Scoped Token Active', 'astropress'); ?></strong>
                                <span class="astropress-badge is-connected"><?php esc_html_e('Connected', 'astropress'); ?></span>
                                <span class="token-mask">•••••••••••••••••••••••••••••••• <?php if (!empty($cf_zone_id)): ?> &bull; Zone: <?php echo esc_html(substr($cf_zone_id, 0, 8)); ?>••••<?php endif; ?></span>
                            </div>
                        </div>
                        <div class="connected-actions">
                            <button type="button" class="button button-secondary" id="onboard-verify-token-btn">
                                <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Check Connection', 'astropress'); ?>
                            </button>
                            <button type="button" class="button button-link" id="onboard-change-token-btn">
                                <span class="dashicons dashicons-edit"></span> <?php esc_html_e('Change Token', 'astropress'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Token Input Drawer (Hidden when connected, revealed on Change Token or empty) -->
                    <div id="onboard-token-input-drawer" class="wizard-input-drawer" style="<?php echo !empty($cf_token) ? 'display:none;' : ''; ?>">
                        <input type="password" id="onboard_cf_api_token" name="onboard_cf_api_token" placeholder="<?php echo !empty($cf_token) ? '••••••••••••••••••••••••••••••••' : 'Paste Cloudflare API Token here...'; ?>" class="regular-text" autocomplete="new-password" />
                        <button type="button" class="button button-primary" id="onboard-quick-audit-btn">
                            <span class="dashicons dashicons-search"></span> <?php esc_html_e('Auto-Discover & Verify Token', 'astropress'); ?>
                        </button>
                        <?php if (!empty($cf_token)): ?>
                            <button type="button" class="button button-link" id="onboard-cancel-change-token-btn">
                                <?php esc_html_e('Cancel', 'astropress'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="onboard_cf_zone_id" name="onboard_cf_zone_id" value="<?php echo esc_attr($cf_zone_id); ?>" />
                    <input type="hidden" id="onboard_r2_account_id" name="onboard_r2_account_id" value="<?php echo esc_attr($account_id); ?>" />

                    <!-- Live Audit Results Container -->
                    <div id="onboard-audit-results" style="<?php echo (!empty($cf_token) && !empty($cf_zone_id)) ? '' : 'display:none;'; ?>">
                        <?php if (!empty($cf_token) && !empty($cf_zone_id)): ?>
                            <div class="astropress-detect-pill is-cf">
                                <span class="dashicons dashicons-yes-alt text-success"></span>
                                <span>Cloudflare Scoped Token verified and Zone ID active (<code><?php echo esc_html(substr($cf_zone_id, 0, 10)); ?>••••</code>).</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- STEP 2: Headless Astro Frontend URL -->
                <section class="wizard-section">
                    <header class="section-header">
                        <span class="step-badge">2</span>
                        <div>
                            <h3><?php esc_html_e('Headless Astro Frontend URL', 'astropress'); ?></h3>
                            <p><?php esc_html_e('The public URL where your Astro website is hosted (e.g., https://example.com). Auto-derived from your root domain.', 'astropress'); ?></p>
                        </div>
                    </header>

                    <div class="wizard-input-row">
                        <input type="url" id="onboard_frontend_url" name="onboard_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" required />
                        <button type="button" class="button button-secondary" id="onboard-detect-frontend-btn">
                            <span class="dashicons dashicons-search"></span> <?php esc_html_e('Ping & Verify Reachability', 'astropress'); ?>
                        </button>
                    </div>
                    <div id="onboard-frontend-collision-warning" style="display:none;" class="wizard-alert-warning">
                        <strong><span class="dashicons dashicons-warning"></span> <?php esc_html_e('URL Collision Error:', 'astropress'); ?></strong>
                        <?php esc_html_e('Frontend URL cannot match your WordPress backend installation. WordPress should be on a subdomain (e.g. wp.example.com) and Astro on your canonical root domain (https://example.com).', 'astropress'); ?>
                    </div>
                    <div id="onboard-frontend-detect-badge" style="display:none;"></div>
                </section>

                <!-- STEP 3: Media Storage Mode -->
                <section class="wizard-section">
                    <header class="section-header">
                        <span class="step-badge">3</span>
                        <div>
                            <h3><?php esc_html_e('Media Storage & CDN Offloading', 'astropress'); ?></h3>
                            <p><?php esc_html_e('Choose how media library uploads should be handled.', 'astropress'); ?></p>
                        </div>
                    </header>

                    <div class="wizard-field">
                        <label for="onboard_media_mode"><strong><?php esc_html_e('Select Media Offloading Method:', 'astropress'); ?></strong></label>
                        <select id="onboard_media_mode" name="onboard_media_mode">
                            <option value="wp" <?php selected($media_mode, 'wp'); ?>><?php esc_html_e('WordPress Local Storage (Default - Store on Hosting Server)', 'astropress'); ?></option>
                            <option value="r2" <?php selected($media_mode, 'r2'); ?>><?php esc_html_e('Cloudflare R2 Object Storage (Zero Egress + Global CDN)', 'astropress'); ?></option>
                        </select>
                    </div>

                    <!-- R2 Config Fields (revealed when R2 chosen) -->
                    <div id="onboard-r2-fields-wrap" class="wizard-subpanel" style="<?php echo ($media_mode === 'r2') ? '' : 'display:none;'; ?>">
                        <div class="wizard-field">
                            <label for="onboard_r2_bucket"><strong><?php esc_html_e('R2 Bucket Name', 'astropress'); ?></strong></label>
                            <div class="wizard-input-row">
                                <input type="text" id="onboard_r2_bucket" name="onboard_r2_bucket" value="<?php echo esc_attr($bucket); ?>" class="regular-text" placeholder="e.g. topnepali" />
                                <select id="onboard_r2_bucket_select" name="onboard_r2_bucket_select" class="regular-text" style="<?php echo !empty($cached_buckets) ? '' : 'display:none;'; ?>">
                                    <option value=""><?php esc_html_e('-- Discovered Buckets --', 'astropress'); ?></option>
                                    <?php foreach ($cached_buckets as $cb): ?>
                                        <option value="<?php echo esc_attr($cb); ?>" <?php selected($bucket, $cb); ?>><?php echo esc_html($cb); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button button-secondary" id="onboard-fetch-buckets">
                                    <span class="dashicons dashicons-search"></span> <?php esc_html_e('Discover Buckets', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="onboard-btn-show-create-bucket">
                                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('+ Create Bucket', 'astropress'); ?>
                                </button>
                            </div>

                            <!-- Inline Create Bucket Box -->
                            <div id="onboard-create-bucket-wrap" class="wizard-subpanel-inner" style="display:none;">
                                <label for="onboard_new_bucket_name"><strong><?php esc_html_e('New Bucket Name:', 'astropress'); ?></strong></label>
                                <div class="wizard-input-row">
                                    <input type="text" id="onboard_new_bucket_name" placeholder="lowercase letters, numbers, hyphens" class="regular-text" />
                                    <button type="button" id="onboard-btn-confirm-create-bucket" class="button button-primary button-small"><?php esc_html_e('Create & Select', 'astropress'); ?></button>
                                    <button type="button" id="onboard-btn-cancel-create-bucket" class="button button-secondary button-small"><?php esc_html_e('Cancel', 'astropress'); ?></button>
                                </div>
                                <div id="onboard-create-bucket-status"></div>
                            </div>
                        </div>

                        <div class="wizard-field">
                            <label for="onboard_r2_custom_domain"><strong><?php esc_html_e('Public CDN Domain', 'astropress'); ?></strong></label>
                            <div class="wizard-input-row">
                                <input type="text" id="onboard_r2_custom_domain" name="onboard_r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" class="regular-text" placeholder="media.example.com" />
                                <button type="button" class="button button-secondary" id="onboard-audit-cdn-btn">
                                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Audit CDN & Rules', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="onboard-deploy-cache-rule-btn">
                                    <span class="dashicons dashicons-cloud"></span> <?php esc_html_e('Deploy Cache Rule', 'astropress'); ?>
                                </button>
                            </div>
                            <p class="field-help">
                                <?php printf(
                                    esc_html__('Custom CDN subdomain hooked to your R2 bucket. Must be a subdomain of %s (e.g. media.%s).', 'astropress'),
                                    '<code>' . esc_html($allowed_roots_str) . '</code>',
                                    esc_html($allowed_roots[0] ?? 'example.com')
                                ); ?>
                            </p>
                            <div id="onboard-cdn-audit-box" style="display:none;"></div>
                            <div id="onboard-cache-rule-status"></div>
                        </div>
                    </div>
                </section>

                <div class="wizard-footer">
                    <button type="submit" name="astropress_save_onboarding" id="onboard-submit-btn" class="button button-primary button-hero">
                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Complete Setup & Launch Dashboard', 'astropress'); ?> &rarr;
                    </button>
                </div>
            </form>
        </div>
