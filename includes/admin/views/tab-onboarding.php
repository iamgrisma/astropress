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
$custom_secret     = $this->core->get_custom_secret();
$has_custom_secret = $this->core->has_custom_secret();
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
$cached_projects  = !empty($cached_discovery['pages_projects']) ? $cached_discovery['pages_projects'] : array();

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
                    <?php esc_html_e('Requires Zone Cache Purge, Cache Rules, DNS, Zone Read, Pages, and R2 Storage permissions.', 'astropress'); ?>
                    <a href="<?php echo esc_url($this->get_cf_token_template_url()); ?>" target="_blank" rel="noopener noreferrer" style="color:#0284c7; text-decoration:none; font-weight:600;">
                        <?php esc_html_e('Create pre-filled token on Cloudflare &rarr;', 'astropress'); ?>
                    </a>
                </p>
            </div>

            <!-- Cloudflare Pages Project Selection -->
            <div>
                <label for="onboard_cf_pages_project" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Cloudflare Pages Project:', 'astropress'); ?>
                </label>
                <select id="onboard_cf_pages_project" name="onboard_cf_pages_project" style="width:100%; max-width:100%;">
                    <option value=""><?php esc_html_e('-- Select Cloudflare Pages Project (Auto-Discovered) --', 'astropress'); ?></option>
                    <?php foreach ($cached_projects as $cp): ?>
                        <?php 
                        $p_name = is_array($cp) ? ($cp['name'] ?? '') : $cp;
                        $p_domains = is_array($cp) ? implode(',', ($cp['domains'] ?? array())) : '';
                        $p_first_domain = !empty($p_domains) ? explode(',', $p_domains)[0] : '';
                        ?>
                        <option value="<?php echo esc_attr($p_name); ?>" data-domains="<?php echo esc_attr($p_domains); ?>" <?php selected($pages_project, $p_name); ?>>
                            <?php echo esc_html($p_name); ?><?php echo !empty($p_first_domain) ? ' (' . esc_html($p_first_domain) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="__custom__" <?php selected($pages_project === '__custom__'); ?>><?php esc_html_e('+ Custom Frontend / Cloudflare Worker', 'astropress'); ?></option>
                </select>
                <div id="onboard-pages-custom-wrap" style="display:none; margin-top:8px;">
                    <input type="text" id="onboard_cf_pages_project_custom" placeholder="e.g. topnepali-election" class="regular-text" style="width:100%;" />
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">
                    <?php esc_html_e('Selecting your project automatically syncs REVALIDATE_SECRET and pre-selects your frontend domain.', 'astropress'); ?>
                </p>
            </div>

            <!-- Astro Frontend URL -->
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
                    <label for="onboard_frontend_url" style="font-weight:600; color:#334155;">
                        <?php esc_html_e('Astro Frontend URL:', 'astropress'); ?>
                    </label>
                    <button type="button" class="button button-secondary button-small" id="onboard-test-handshake-btn">
                        <span class="dashicons dashicons-admin-network" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Astro Handshake', 'astropress'); ?>
                    </button>
                </div>
                <input type="url" id="onboard_frontend_url" name="onboard_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" placeholder="https://example.com" style="width:100%; max-width:100%;" required />
                <div id="onboard-handshake-quick-status" style="margin-top:6px; display:none;"></div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Canonical URL of your public Astro website (e.g. https://topnepali.com).', 'astropress'); ?></p>
            </div>

            <!-- Cloudflare Pages Deploy Hook (Required for Secret Sync) -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                    <label for="onboard_cf_deploy_hook" style="font-weight:600; font-size:13px; color:#1e293b; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-cloud-upload" style="color:#0284c7;"></span>
                        <span><?php esc_html_e('Cloudflare Pages Deploy Hook (Required for Secret Sync):', 'astropress'); ?></span>
                    </label>
                    <span id="onboard-deploy-badge" style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; background:<?php echo $has_deploy_hook ? '#ecfdf5; color:#166534; border:1px solid #bbf7d0;' : '#fef3c7; color:#92400e; border:1px solid #fde68a;'; ?>">
                        <span class="dashicons <?php echo $has_deploy_hook ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <span class="status-text"><?php echo $has_deploy_hook ? esc_html__('Configured (Test to Verify)', 'astropress') : esc_html__('Verification Required', 'astropress'); ?></span>
                    </span>
                </div>

                <!-- Guidance / Where to get deploy hook alert -->
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; margin-bottom:12px; font-size:12px; color:#1e40af; line-height:1.5;">
                    <div style="font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></span>
                        <span><?php esc_html_e('Where to get your Cloudflare Pages Deploy Hook:', 'astropress'); ?></span>
                    </div>
                    <ol style="margin:4px 0 6px 18px; padding:0;">
                        <li><?php esc_html_e('Log into Cloudflare Dashboard &rarr; Workers & Pages &rarr; click your Astro project.', 'astropress'); ?></li>
                        <li><?php esc_html_e('Go to Settings &rarr; Builds & deployments &rarr; scroll to Deploy hooks.', 'astropress'); ?></li>
                        <li><?php esc_html_e('Click "Add deploy hook", choose your production branch (e.g. main), and copy the URL.', 'astropress'); ?></li>
                    </ol>
                    <div style="color:#2563eb; font-size:11px;">
                        <strong><?php esc_html_e('Why is this required?', 'astropress'); ?></strong> <?php esc_html_e('Cloudflare Pages only loads newly updated environment variables into your live Astro frontend when a new build is deployed. Having a verified Deploy Hook ensures that syncing secrets will immediately trigger a rebuild, activating your revalidation bridge with zero downtime and no 401 errors.', 'astropress'); ?>
                    </div>
                </div>

                <div style="display:flex; gap:8px; align-items:center;">
                    <div style="position:relative; flex:1;">
                        <input type="password" id="onboard_cf_deploy_hook" name="onboard_cf_deploy_hook" value="<?php echo $has_deploy_hook ? '••••••••••••••••••••••••••••••••' : ''; ?>" placeholder="https://api.cloudflare.com/client/v4/pages/webhooks/deploy_hooks/..." class="regular-text" style="width:100%; padding-right:32px;" autocomplete="new-password" />
                        <button type="button" class="astropress-toggle-pw" data-target="#onboard_cf_deploy_hook" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; color:#64748b; cursor:pointer;" title="<?php esc_attr_e('Show / Hide', 'astropress'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <button type="button" class="button button-secondary" id="onboard-test-deploy-btn">
                        <span class="dashicons dashicons-cloud-upload" style="vertical-align:-2px;"></span> <?php esc_html_e('Test Deploy Hook', 'astropress'); ?>
                    </button>
                </div>
                <div id="onboard-deploy-quick-status" style="margin-top:8px; display:none;"></div>
            </div>

            <!-- Astro Webhook Secret (REVALIDATE_SECRET) & Cloudflare Pages Sync -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                    <label for="onboard_secret" style="font-weight:600; font-size:13px; color:#1e293b; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-shield" style="color:#16a34a;"></span>
                        <span><?php esc_html_e('Astro Webhook Secret (REVALIDATE_SECRET):', 'astropress'); ?></span>
                    </label>
                    <span id="onboard-secret-badge" style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; background:<?php echo $has_custom_secret ? '#ecfdf5; color:#166534; border:1px solid #bbf7d0;' : '#fef3c7; color:#92400e; border:1px solid #fde68a;'; ?>">
                        <span class="dashicons <?php echo $has_custom_secret ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <span><?php echo $has_custom_secret ? esc_html__('Custom Secret Stored', 'astropress') : esc_html__('Auto-Generated Secret', 'astropress'); ?></span>
                    </span>
                </div>
                <p style="font-size:12px; color:#64748b; margin:0 0 10px 0; line-height:1.4;">
                    <?php esc_html_e('Shared secret between WordPress and your Astro /api/revalidate endpoint. Protects your frontend from unauthorized cache purging.', 'astropress'); ?>
                </p>

                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:240px;">
                        <input type="password" id="onboard_secret" name="onboard_revalidate_secret" value="<?php echo esc_attr($custom_secret ?: $this->core->get_secret()); ?>" class="regular-text" style="width:100%; padding-right:58px;" autocomplete="new-password" />
                        <div style="position:absolute; right:6px; top:50%; transform:translateY(-50%); display:flex; gap:2px;">
                            <button type="button" class="astropress-toggle-pw" data-target="#onboard_secret" style="background:none; border:none; color:#64748b; cursor:pointer;" title="<?php esc_attr_e('Show / Hide', 'astropress'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="astropress-copy-btn" data-target="#onboard_secret" style="background:none; border:none; color:#64748b; cursor:pointer;" title="<?php esc_attr_e('Copy Secret', 'astropress'); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="button button-secondary" id="onboard-generate-secret-btn" title="<?php esc_attr_e('Generate or rotate a new cryptographically secure 32-character secret', 'astropress'); ?>">
                        <span class="dashicons dashicons-randomize" style="vertical-align:-2px;"></span> <?php esc_html_e('Rotate / Generate Secret', 'astropress'); ?>
                    </button>
                </div>

                <!-- Rotate notice -->
                <div style="margin-top:8px; font-size:11px; color:#64748b;">
                    <span class="dashicons dashicons-info" style="font-size:14px; width:14px; height:14px; vertical-align:-2px; color:#0284c7;"></span>
                    <strong><?php esc_html_e('Secret Exposed or Needs Rotation?', 'astropress'); ?></strong> <?php esc_html_e('Click "Rotate / Generate Secret", then click "Sync REVALIDATE_SECRET to Cloudflare Pages" below.', 'astropress'); ?>
                </div>

                <!-- 1-Click Sync to Cloudflare Pages Action -->
                <div style="margin-top:14px; padding-top:14px; border-top:1px dashed #cbd5e1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <strong style="display:block; font-size:13px; color:#0f172a;">
                                <span class="dashicons dashicons-cloud" style="color:#0284c7; vertical-align:-2px;"></span> <?php esc_html_e('Cloudflare Pages Environment Variable Sync:', 'astropress'); ?>
                            </strong>
                            <span style="font-size:12px; color:#64748b;">
                                <?php esc_html_e('Pushes REVALIDATE_SECRET (as encrypted secret_text) & WP_API_URL to Pages, then auto-triggers Deploy Hook.', 'astropress'); ?>
                            </span>
                        </div>
                        <div>
                            <button type="button" class="button button-primary astropress-sync-pages-btn" id="onboard-sync-pages-btn" data-input="#onboard_cf_pages_project" data-secret="#onboard_secret" data-deploy="#onboard_cf_deploy_hook" data-status="#onboard-pages-sync-status" <?php echo $has_deploy_hook ? '' : 'disabled="disabled"'; ?> style="display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-update"></span>
                                <span class="btn-text"><?php esc_html_e('Sync REVALIDATE_SECRET to Cloudflare Pages', 'astropress'); ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Deploy Hook Gating Notice -->
                    <div id="onboard-sync-lock-notice" style="margin-top:8px; font-size:11px; color:#b45309; <?php echo $has_deploy_hook ? 'display:none;' : ''; ?>">
                        <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; vertical-align:-2px;"></span>
                        <strong><?php esc_html_e('Deploy Hook Verification Required:', 'astropress'); ?></strong>
                        <?php esc_html_e('Please enter and test your Cloudflare Pages Deploy Hook above to unlock 1-click secret synchronization.', 'astropress'); ?>
                    </div>

                    <!-- Safe Sync Guarantee -->
                    <div style="margin-top:6px; font-size:11px; color:#15803d; display:flex; align-items:center; gap:4px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <span><?php esc_html_e('Safe Patch Guarantee: Modifies only REVALIDATE_SECRET and WP_API_URL. All existing Cloudflare Pages environment variables (PUBLIC_*, custom secrets, etc.) are strictly preserved.', 'astropress'); ?></span>
                    </div>

                    <div id="onboard-pages-sync-status" style="margin-top:10px; display:none;"></div>
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
                    <select id="onboard_r2_bucket" name="onboard_r2_bucket" style="width:100%; max-width:100%;">
                        <?php if (!empty($bucket)): ?>
                            <option value="<?php echo esc_attr($bucket); ?>" selected><?php echo esc_html($bucket); ?> (Current)</option>
                        <?php else: ?>
                            <option value=""><?php esc_html_e('-- Select Discovered R2 Bucket --', 'astropress'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($cached_buckets as $cb): ?>
                            <?php if ($cb !== $bucket): ?>
                                <option value="<?php echo esc_attr($cb); ?>"><?php echo esc_html($cb); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <option value="__custom__"><?php esc_html_e('+ Enter Custom / New Bucket Name', 'astropress'); ?></option>
                    </select>
                    <div id="onboard-custom-bucket-wrap" style="display:none; margin-top:8px;">
                        <input type="text" id="onboard_r2_bucket_custom" placeholder="my-media-bucket" class="regular-text" style="width:100%;" />
                    </div>
                    <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Discovered from your Cloudflare account or specify a new bucket.', 'astropress'); ?></p>
                </div>

                <!-- Custom CDN Domain -->
                <div>
                    <label for="onboard_r2_custom_domain" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#334155;">
                        <?php esc_html_e('Custom CDN Domain:', 'astropress'); ?>
                    </label>
                    <input type="text" id="onboard_r2_custom_domain" name="onboard_r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" placeholder="media.example.com" class="regular-text" style="width:100%; max-width:100%;" />
                    <div id="onboard-bucket-domain-status" style="margin-top:6px; font-size:12px;"></div>
                    
                    <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:12px; color:#334155; cursor:pointer;">
                        <input type="checkbox" id="onboard_confirm_attach_domain" name="onboard_confirm_attach_domain" value="1" checked />
                        <span><?php esc_html_e('Attach this domain to R2 bucket & automatically create proxied DNS CNAME on Cloudflare', 'astropress'); ?></span>
                    </label>
                    <p style="font-size:11px; color:#64748b; margin:4px 0 0 0;">
                        <?php printf(esc_html__('Must be a subdomain of your root zone (e.g. %s). Uncheck if DNS is managed externally.', 'astropress'), '<code>media.' . esc_html($allowed_roots[0] ?? 'example.com') . '</code>'); ?>
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
