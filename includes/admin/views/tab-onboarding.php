<?php
/**
 * Admin View: Streamlined Headless Setup Wizard
 *
 * 2-Stage Zero-Friction Pipeline:
 * Stage 1: Connect Cloudflare Edge & Astro Frontend
 * Stage 2: Media Storage & Cloudflare R2 CDN Offloading
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$checks            = $this->get_preflight_checks();
$cf_token          = $this->core->get_cf_api_token();
$cf_zone_id        = $this->core->get_cf_zone_id();
$account_id        = AstroPress_Admin::get_r2_option('account_id', '');
$bucket            = AstroPress_Admin::get_r2_option('bucket', '');
$custom_domain     = AstroPress_Admin::get_r2_option('custom_domain', '');
$media_mode        = get_option(AstroPress::OPTION_MEDIA_MODE, 'r2');
$pages_project     = $this->core->get_cf_pages_project();
$has_deploy_hook   = $this->core->has_cf_deploy_hook();
$allowed_roots     = $this->get_allowed_domain_roots();
$allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';

// Auto-derive root frontend URL if empty or identical to WP backend
if (empty($frontend_url) || $frontend_url === home_url()) {
    $frontend_url = $this->core->derive_frontend_url();
}

// Auto-suggest custom CDN domain if empty
if (empty($custom_domain) && !empty($allowed_roots[0])) {
    $custom_domain = 'media.' . $allowed_roots[0];
}

// Check cached discovery
$cached_discovery = get_transient('astropress_cf_discovery_cache');
$cached_buckets   = !empty($cached_discovery['buckets']) ? $cached_discovery['buckets'] : array();

$all_pass = true;
$failed_reasons = array();
foreach ($checks as $c) {
    if ($c['status'] === 'fail') {
        $all_pass = false;
        $failed_reasons[] = $c['label'] . ': ' . $c['message'];
    }
}
?>

<div class="astropress-wizard" style="max-width:880px; margin:20px auto;">
    <header class="wizard-header" style="text-align:center; margin-bottom:24px;">
        <span class="dashicons dashicons-cloud" style="font-size:48px; width:48px; height:48px; color:#0284c7;"></span>
        <h2 style="font-size:24px; margin:8px 0 4px 0; color:#0f172a;"><?php esc_html_e('AstroPress Headless Setup', 'astropress'); ?></h2>
        <p style="color:#64748b; font-size:14px; margin:0;"><?php esc_html_e('Connect WordPress to Cloudflare and your Astro Frontend in two automated steps.', 'astropress'); ?></p>
    </header>

    <?php if ($all_pass): ?>
        <div class="wizard-system-status is-pass" style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:8px 16px; border-radius:8px; margin-bottom:20px; font-size:13px; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-yes-alt text-success"></span>
            <span><strong><?php esc_html_e('Pre-Flight Verified:', 'astropress'); ?></strong> <?php echo esc_html(sprintf(__('PHP %s, cURL, OpenSSL & Image Engines active.', 'astropress'), PHP_VERSION)); ?></span>
        </div>
    <?php else: ?>
        <div class="wizard-system-status is-fail" style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:8px 16px; border-radius:8px; margin-bottom:20px; font-size:13px;">
            <span class="dashicons dashicons-warning"></span>
            <span><strong><?php esc_html_e('Environment Notice:', 'astropress'); ?></strong> <?php echo esc_html(implode('; ', $failed_reasons)); ?></span>
        </div>
    <?php endif; ?>

    <!-- STAGE 1 CARD: Connect Cloudflare & Astro Frontend -->
    <div class="astropress-card" id="onboard-stage-1" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <span style="background:#0284c7; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">1</span>
            <div>
                <h3 style="margin:0; font-size:17px; color:#0f172a;"><?php esc_html_e('Connect Cloudflare & Astro Frontend', 'astropress'); ?></h3>
                <p style="margin:2px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e('Authorizes Cloudflare Edge CDN cache purging and establishes the Astro SSR webhook handshake.', 'astropress'); ?></p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr; gap:16px;">
            <!-- Cloudflare API Token -->
            <div>
                <label for="onboard_cf_api_token" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Cloudflare Scoped API Token:', 'astropress'); ?>
                </label>
                <div style="display:flex; gap:8px;">
                    <input type="password" id="onboard_cf_api_token" name="onboard_cf_api_token" value="<?php echo !empty($cf_token) ? '••••••••••••••••••••••••••••••••' : ''; ?>" class="regular-text" placeholder="<?php esc_attr_e('Paste Cloudflare API Token here...', 'astropress'); ?>" autocomplete="new-password" style="width:100%; max-width:100%;" />
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">
                    <?php esc_html_e('Requires Zone Cache Purge, Cache Rules, DNS, Zone Read, and R2 Storage permissions.', 'astropress'); ?>
                    <a href="<?php echo esc_url($this->get_cf_token_template_url()); ?>" target="_blank" rel="noopener noreferrer" style="color:#0284c7; text-decoration:none; font-weight:600;">
                        <?php esc_html_e('Create pre-filled token on Cloudflare &rarr;', 'astropress'); ?>
                    </a>
                </p>
            </div>

            <!-- Astro Frontend URL -->
            <div>
                <label for="onboard_frontend_url" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Astro Frontend URL:', 'astropress'); ?>
                </label>
                <input type="url" id="onboard_frontend_url" name="onboard_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" placeholder="https://example.com" style="width:100%; max-width:100%;" required />
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Canonical URL of your public Astro website (e.g. https://topnepali.com).', 'astropress'); ?></p>
            </div>

            <!-- Collapsible Optional Settings (Deploy Hook & Pages Project) -->
            <div style="border-top:1px dashed #e2e8f0; padding-top:12px;">
                <button type="button" class="button button-link" id="onboard-toggle-advanced-btn" style="padding:0; font-size:12px; color:#0284c7; text-decoration:none;">
                    <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px; vertical-align:-2px;"></span>
                    <?php esc_html_e('Optional: Cloudflare Pages Deploy Hook & Project Settings', 'astropress'); ?>
                </button>
                <div id="onboard-advanced-wrap" style="display:none; margin-top:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px;">
                    <div style="margin-bottom:12px;">
                        <label for="onboard_cf_pages_project" style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:#334155;">
                            <?php esc_html_e('Cloudflare Pages Project Name (for 1-Click Environment Sync):', 'astropress'); ?>
                        </label>
                        <input type="text" id="onboard_cf_pages_project" name="onboard_cf_pages_project" value="<?php echo esc_attr($pages_project); ?>" placeholder="e.g. topnepali-election (auto-discovered if left blank)" class="regular-text" style="width:100%;" list="onboard-pages-projects-list" />
                        <datalist id="onboard-pages-projects-list"></datalist>
                    </div>
                    <div>
                        <label for="onboard_cf_deploy_hook" style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:#334155;">
                            <?php esc_html_e('Cloudflare Pages Deploy Hook (for Full Static Rebuilds):', 'astropress'); ?>
                        </label>
                        <input type="password" id="onboard_cf_deploy_hook" name="onboard_cf_deploy_hook" value="<?php echo $has_deploy_hook ? '••••••••••••••••••••••••••••••••' : ''; ?>" placeholder="https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/..." class="regular-text" style="width:100%;" autocomplete="new-password" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Hidden Discovery Fields -->
        <input type="hidden" id="onboard_cf_zone_id" value="<?php echo esc_attr($cf_zone_id); ?>" />
        <input type="hidden" id="onboard_r2_account_id" value="<?php echo esc_attr($account_id); ?>" />

        <!-- Primary Action Button -->
        <div style="margin-top:20px;">
            <button type="button" class="button button-primary button-hero" id="onboard-connect-all-btn" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-admin-network"></span>
                <span><?php esc_html_e('Connect & Auto-Configure', 'astropress'); ?></span>
            </button>
        </div>

        <!-- Live Status Results Container -->
        <div id="onboard-connect-status" style="margin-top:16px; display:none;"></div>
    </div>

    <!-- STAGE 2 CARD: Media Storage & Cloudflare R2 CDN -->
    <div class="astropress-card" id="onboard-stage-2" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); <?php echo ($is_connected && !empty($cf_token)) ? '' : 'opacity:0.85;'; ?>">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
            <span style="background:#0284c7; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">2</span>
            <div>
                <h3 style="margin:0; font-size:17px; color:#0f172a;"><?php esc_html_e('Media Storage & Cloudflare R2 Offloading', 'astropress'); ?></h3>
                <p style="margin:2px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e('Automatically offloads media uploads to Cloudflare R2 with zero egress fees and custom CDN delivery.', 'astropress'); ?></p>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label for="onboard_media_mode" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                <?php esc_html_e('Storage Mode:', 'astropress'); ?>
            </label>
            <select id="onboard_media_mode" name="onboard_media_mode" style="width:100%; max-width:100%;">
                <option value="r2" <?php selected($media_mode, 'r2'); ?>><?php esc_html_e('Cloudflare R2 Object Storage (Recommended - Zero Egress + Global CDN)', 'astropress'); ?></option>
                <option value="wp" <?php selected($media_mode, 'wp'); ?>><?php esc_html_e('WordPress Local Server Storage (Store on Hosting Server)', 'astropress'); ?></option>
            </select>
        </div>

        <!-- R2 Fields Container -->
        <div id="onboard-r2-container" style="<?php echo ($media_mode === 'r2') ? '' : 'display:none;'; ?>">
            <div style="display:grid; grid-template-columns:1fr; gap:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin-bottom:16px;">
                <!-- R2 Bucket Selection -->
                <div>
                    <label for="onboard_r2_bucket" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#334155;">
                        <?php esc_html_e('R2 Bucket Name:', 'astropress'); ?>
                    </label>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="onboard_r2_bucket" name="onboard_r2_bucket" value="<?php echo esc_attr($bucket); ?>" placeholder="e.g. topnepali-media or select discovered" class="regular-text" style="flex:1;" list="onboard-buckets-list" />
                        <datalist id="onboard-buckets-list">
                            <?php foreach ($cached_buckets as $cb): ?>
                                <option value="<?php echo esc_attr($cb); ?>"><?php echo esc_html($cb); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Discovered from your Cloudflare account or enter a new bucket name.', 'astropress'); ?></p>
                </div>

                <!-- Custom CDN Domain -->
                <div>
                    <label for="onboard_r2_custom_domain" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#334155;">
                        <?php esc_html_e('Custom CDN Domain:', 'astropress'); ?>
                    </label>
                    <input type="text" id="onboard_r2_custom_domain" name="onboard_r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" placeholder="media.example.com" class="regular-text" style="width:100%; max-width:100%;" />
                    <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">
                        <?php printf(esc_html__('AstroPress will automatically attach this domain to your bucket and configure the Cloudflare DNS CNAME record (e.g. %s).', 'astropress'), '<code>media.' . esc_html($allowed_roots[0] ?? 'example.com') . '</code>'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Primary Action Button -->
        <div>
            <button type="button" class="button button-primary button-hero" id="onboard-enable-r2-btn" style="width:100%; text-align:center; justify-content:center; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-database"></span>
                <span><?php esc_html_e('Enable Media Offload & Launch Dashboard', 'astropress'); ?> &rarr;</span>
            </button>
        </div>

        <div id="onboard-r2-result-status" style="margin-top:16px; display:none;"></div>
    </div>

    <!-- Scopes Reference Accordion -->
    <div style="text-align:center; margin-top:20px;">
        <button type="button" class="button button-link" id="onboard-toggle-blueprint-btn" style="color:#64748b; font-size:12px; text-decoration:none;">
            <span class="dashicons dashicons-editor-help" style="vertical-align:-2px;"></span>
            <?php esc_html_e('View Cloudflare Token Permissions Blueprint', 'astropress'); ?>
        </button>
        <div id="onboard-blueprint-wrap" style="display:none; text-align:left; margin-top:12px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
            <table class="widefat striped" style="border:none;">
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
    </div>
</div>
