<?php
/**
 * Admin View: Streamlined Headless Setup Wizard (v2.0)
 *
 * 3-Stage Progressive Pipeline:
 * Stage 1: Cloudflare Edge Connection & Hosting Platform Target
 * Stage 2: Revalidation Bridge & Cloudflare Pages Deploy Hook
 * Stage 3: Media Storage & Cloudflare R2 CDN Offloading
 *
 * Supports independent progressive saving per stage + unified final save.
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
$access_key        = AstroPress_Admin::get_r2_option('access_key', '');
$has_secret_key    = !empty(AstroPress_Admin::get_r2_option('secret_key', ''));
$media_mode        = get_option(AstroPress::OPTION_MEDIA_MODE, 'r2');
$pages_project     = $this->core->get_cf_pages_project();
$hosting_target    = $this->core->get_hosting_target();
$has_deploy_hook   = $this->core->has_cf_deploy_hook();
$custom_secret     = $this->core->get_custom_secret();
$has_custom_secret = $this->core->has_custom_secret();
$allowed_roots     = $this->get_allowed_domain_roots();
$allowed_roots_str = !empty($allowed_roots) ? implode(', ', $allowed_roots) : 'your site domain';

// Auto-derive root frontend URL if empty or identical to WP backend
$frontend_url = !empty($frontend_url) ? $frontend_url : $this->core->get_frontend_url();
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
$has_cached_disc  = !empty($cached_discovery);

$all_pass = true;
$failed_reasons = array();
foreach ($checks as $c) {
    if ($c['status'] === 'fail') {
        $all_pass = false;
        $failed_reasons[] = $c['label'] . ': ' . $c['message'];
    }
}
?>

<div class="astropress-wizard" style="max-width:920px; margin:20px auto;">
    <header class="wizard-header" style="text-align:center; margin-bottom:24px;">
        <span class="dashicons dashicons-cloud" style="font-size:48px; width:48px; height:48px; color:#0284c7;"></span>
        <h2 style="font-size:24px; margin:8px 0 4px 0; color:#0f172a;"><?php esc_html_e('AstroPress Headless Setup', 'astropress'); ?></h2>
        <p style="color:#64748b; font-size:14px; margin:0;"><?php esc_html_e('Follow the 3-step progressive setup to connect WordPress, Astro, and Cloudflare R2.', 'astropress'); ?></p>
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

    <!-- ======================================================== -->
    <!-- STAGE 1: Cloudflare Edge Connection & Hosting Platform  -->
    <!-- ======================================================== -->
    <div class="astropress-card" id="onboard-stage-1" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="background:#0284c7; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">1</span>
                <div>
                    <h3 style="margin:0; font-size:18px; color:#0f172a;"><?php esc_html_e('Stage 1: Connection & Hosting Target', 'astropress'); ?></h3>
                    <p style="margin:2px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e('Authorize Cloudflare API, inspect granted capabilities, and select your Astro hosting target.', 'astropress'); ?></p>
                </div>
            </div>
            <div id="onboard-step1-status-pill">
                <?php if (!empty($cf_token)): ?>
                    <span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <?php esc_html_e('Token Stored', 'astropress'); ?>
                    </span>
                <?php else: ?>
                    <span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;">
                        <?php esc_html_e('Configuration Required', 'astropress'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr; gap:16px;">
            <!-- Cloudflare API Token -->
            <div>
                <label for="onboard_cf_api_token" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Cloudflare Scoped API Token:', 'astropress'); ?>
                </label>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:280px;">
                        <input type="password" id="onboard_cf_api_token" name="onboard_cf_api_token" value="<?php echo !empty($cf_token) ? '••••••••••••••••••••••••••••••••' : ''; ?>" class="regular-text" placeholder="<?php esc_attr_e('Paste Cloudflare API Token here...', 'astropress'); ?>" autocomplete="new-password" style="width:100%; padding-right:32px;" />
                        <button type="button" class="astropress-toggle-pw" data-target="#onboard_cf_api_token" style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; color:#64748b; cursor:pointer;" title="<?php esc_attr_e('Show / Hide', 'astropress'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <button type="button" class="button button-secondary" id="onboard-verify-token-btn" style="display:flex; align-items:center; gap:4px;">
                        <span class="dashicons dashicons-shield"></span>
                        <span><?php esc_html_e('Verify Token & Check Access', 'astropress'); ?></span>
                    </button>
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">
                    <?php esc_html_e('Essential: Zone Read & Cache Purge. Optional: Pages, R2 Storage, DNS.', 'astropress'); ?>
                    <a href="<?php echo esc_url($this->get_cf_token_template_url()); ?>" target="_blank" rel="noopener noreferrer" style="color:#0284c7; text-decoration:none; font-weight:600;">
                        <?php esc_html_e('Create pre-filled token on Cloudflare &rarr;', 'astropress'); ?>
                    </a>
                </p>
            </div>

            <!-- Visual Permissions & Capabilities Box -->
            <div id="onboard-permissions-box" style="display:<?php echo (!empty($cf_token) && !empty($cf_zone_id)) ? 'block' : 'none'; ?>; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                <h4 style="margin:0 0 10px 0; font-size:13px; color:#1e293b; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-network" style="color:#0284c7;"></span>
                    <span><?php esc_html_e('Token Capabilities & Cloudflare Access:', 'astropress'); ?></span>
                </h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px;">
                    <!-- Essential: Zone Purge -->
                    <div id="perm-zone-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; display:flex; align-items:center; gap:8px;">
                        <span id="perm-zone-icon" class="dashicons <?php echo !empty($cf_zone_id) ? 'dashicons-yes-alt text-success' : 'dashicons-warning text-warning'; ?>" style="font-size:20px; width:20px; height:20px;"></span>
                        <div>
                            <div style="font-weight:600; font-size:12px; color:#0f172a;"><?php esc_html_e('Zone & Cache Purge', 'astropress'); ?></div>
                            <span style="font-size:10px; font-weight:700; color:#dc2626;"><?php esc_html_e('Essential', 'astropress'); ?></span>
                        </div>
                    </div>
                    <!-- Optional: Cloudflare Pages -->
                    <div id="perm-pages-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; display:flex; align-items:center; gap:8px;">
                        <span id="perm-pages-icon" class="dashicons <?php echo !empty($cached_projects) ? 'dashicons-yes-alt text-success' : 'dashicons-minus'; ?>" style="font-size:20px; width:20px; height:20px; color:#64748b;"></span>
                        <div>
                            <div style="font-weight:600; font-size:12px; color:#0f172a;"><?php esc_html_e('Cloudflare Pages', 'astropress'); ?></div>
                            <span style="font-size:10px; color:#64748b;"><?php esc_html_e('Optional', 'astropress'); ?></span>
                        </div>
                    </div>
                    <!-- Optional: R2 Storage -->
                    <div id="perm-r2-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; display:flex; align-items:center; gap:8px;">
                        <span id="perm-r2-icon" class="dashicons <?php echo !empty($cached_buckets) ? 'dashicons-yes-alt text-success' : 'dashicons-minus'; ?>" style="font-size:20px; width:20px; height:20px; color:#64748b;"></span>
                        <div>
                            <div style="font-weight:600; font-size:12px; color:#0f172a;"><?php esc_html_e('R2 Object Storage', 'astropress'); ?></div>
                            <span style="font-size:10px; color:#64748b;"><?php esc_html_e('Optional', 'astropress'); ?></span>
                        </div>
                    </div>
                    <!-- Optional: DNS Records -->
                    <div id="perm-dns-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; display:flex; align-items:center; gap:8px;">
                        <span id="perm-dns-icon" class="dashicons dashicons-yes-alt text-success" style="font-size:20px; width:20px; height:20px;"></span>
                        <div>
                            <div style="font-weight:600; font-size:12px; color:#0f172a;"><?php esc_html_e('DNS CNAME Routing', 'astropress'); ?></div>
                            <span style="font-size:10px; color:#64748b;"><?php esc_html_e('Optional', 'astropress'); ?></span>
                        </div>
                    </div>
                </div>
                <div id="onboard-perm-warning" style="display:none; margin-top:10px; padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#991b1b; font-size:12px;"></div>
            </div>

            <!-- Hosting Platform Selection -->
            <div>
                <label style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Astro Hosting Platform:', 'astropress'); ?>
                </label>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                    <label style="display:flex; align-items:center; gap:8px; padding:12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; cursor:pointer;">
                        <input type="radio" name="onboard_hosting_target" value="pages" <?php checked($hosting_target, 'pages'); ?> />
                        <div>
                            <strong style="display:block; font-size:13px; color:#0f172a;"><?php esc_html_e('Cloudflare Pages', 'astropress'); ?></strong>
                            <span style="font-size:11px; color:#64748b;"><?php esc_html_e('Automated Git CI/CD & Deploy Hooks', 'astropress'); ?></span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; cursor:pointer;">
                        <input type="radio" name="onboard_hosting_target" value="worker" <?php checked($hosting_target, 'worker'); ?> />
                        <div>
                            <strong style="display:block; font-size:13px; color:#0f172a;"><?php esc_html_e('Cloudflare Worker', 'astropress'); ?></strong>
                            <span style="font-size:11px; color:#64748b;"><?php esc_html_e('Edge Worker SSR Routing', 'astropress'); ?></span>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; padding:12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; cursor:pointer;">
                        <input type="radio" name="onboard_hosting_target" value="custom" <?php checked($hosting_target, 'custom'); ?> />
                        <div>
                            <strong style="display:block; font-size:13px; color:#0f172a;"><?php esc_html_e('Custom / Node VPS', 'astropress'); ?></strong>
                            <span style="font-size:11px; color:#64748b;"><?php esc_html_e('Docker, Node or external host', 'astropress'); ?></span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Target Resource Detail: Pages Project Selection -->
            <div id="onboard-pages-wrap" style="<?php echo ($hosting_target === 'pages') ? '' : 'display:none;'; ?>">
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
                    <option value="__custom__" <?php selected($pages_project === '__custom__'); ?>><?php esc_html_e('+ Specify Custom Project Name', 'astropress'); ?></option>
                </select>
                <div id="onboard-pages-custom-wrap" style="display:none; margin-top:8px;">
                    <input type="text" id="onboard_cf_pages_project_custom" placeholder="e.g. topnepali" class="regular-text" style="width:100%;" />
                </div>
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;">
                    <?php esc_html_e('Discovered from your Cloudflare account. Selecting a project auto-detects its custom domains.', 'astropress'); ?>
                </p>
            </div>

            <!-- Target Resource Detail: Worker Route -->
            <div id="onboard-worker-wrap" style="<?php echo ($hosting_target === 'worker') ? '' : 'display:none;'; ?>">
                <label for="onboard_cf_worker_route" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Cloudflare Worker Route / Subdomain:', 'astropress'); ?>
                </label>
                <input type="text" id="onboard_cf_worker_route" name="onboard_cf_worker_route" placeholder="e.g. topnepali.workers.dev" class="regular-text" style="width:100%;" />
            </div>

            <!-- Astro Frontend URL -->
            <div>
                <label for="onboard_frontend_url" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                    <?php esc_html_e('Astro Frontend URL:', 'astropress'); ?>
                </label>
                <input type="url" id="onboard_frontend_url" name="onboard_frontend_url" value="<?php echo esc_attr($frontend_url); ?>" class="regular-text" placeholder="https://topnepali.com" style="width:100%; max-width:100%;" required />
                <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Public URL of your Astro website (e.g. https://topnepali.com or https://grisma.com.np).', 'astropress'); ?></p>
            </div>
        </div>

        <!-- Hidden Cloudflare Zone and Account ID -->
        <input type="hidden" id="onboard_cf_zone_id" value="<?php echo esc_attr($cf_zone_id); ?>" />
        <input type="hidden" id="onboard_r2_account_id" value="<?php echo esc_attr($account_id); ?>" />

        <!-- Stage 1 Dedicated Save Action -->
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:12px; color:#64748b;">
                <?php esc_html_e('Saves your API token, zone, and hosting target independently without touching media/R2 settings.', 'astropress'); ?>
            </div>
            <button type="button" class="button button-primary" id="onboard-save-step1-btn" style="display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-saved"></span>
                <span><?php esc_html_e('Save Connection Settings (Stage 1)', 'astropress'); ?></span>
            </button>
        </div>
        <div id="onboard-step1-save-status" style="margin-top:10px; display:none;"></div>
    </div>

    <!-- ======================================================== -->
    <!-- STAGE 2: Revalidation Bridge & Cloudflare Deploy Hook    -->
    <!-- ======================================================== -->
    <div class="astropress-card" id="onboard-stage-2" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="background:#0284c7; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">2</span>
                <div>
                    <h3 style="margin:0; font-size:18px; color:#0f172a;"><?php esc_html_e('Stage 2: Revalidation Bridge & Deploy Hook', 'astropress'); ?></h3>
                    <p style="margin:2px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e('Connect the secure webhook handshake and configure Cloudflare Pages deploy hook for automatic rebuilds.', 'astropress'); ?></p>
                </div>
            </div>
            <div id="onboard-step2-status-pill">
                <?php if ($has_custom_secret && $has_deploy_hook): ?>
                    <span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <?php esc_html_e('Ready & Configured', 'astropress'); ?>
                    </span>
                <?php else: ?>
                    <span style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;">
                        <?php esc_html_e('Hook or Secret Pending', 'astropress'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr; gap:16px;">
            <!-- Revalidation Secret (REVALIDATE_SECRET) -->
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
                    <?php esc_html_e('Shared secret between WordPress and your Astro /api/revalidate endpoint to prevent unauthorized cache purging.', 'astropress'); ?>
                </p>

                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:260px;">
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
                    <button type="button" class="button button-secondary" id="onboard-generate-secret-btn" title="<?php esc_attr_e('Generate a random cryptographically secure 32-character secret', 'astropress'); ?>">
                        <span class="dashicons dashicons-randomize" style="vertical-align:-2px;"></span> <?php esc_html_e('Generate Random Key', 'astropress'); ?>
                    </button>
                </div>
            </div>

            <!-- Cloudflare Pages Deploy Hook -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                    <label for="onboard_cf_deploy_hook" style="font-weight:600; font-size:13px; color:#1e293b; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-cloud-upload" style="color:#0284c7;"></span>
                        <span><?php esc_html_e('Cloudflare Pages Deploy Hook (Required for Secret Sync):', 'astropress'); ?></span>
                    </label>
                    <span id="onboard-deploy-badge" style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; background:<?php echo $has_deploy_hook ? '#ecfdf5; color:#166534; border:1px solid #bbf7d0;' : '#fef3c7; color:#92400e; border:1px solid #fde68a;'; ?>">
                        <span class="dashicons <?php echo $has_deploy_hook ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <span class="status-text"><?php echo $has_deploy_hook ? esc_html__('Deploy Hook Configured', 'astropress') : esc_html__('Not Configured', 'astropress'); ?></span>
                    </span>
                </div>

                <!-- Tutorial: How to get Deploy Hook in Cloudflare -->
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:12px; margin-bottom:12px; font-size:12px; color:#1e40af; line-height:1.5;">
                    <div style="font-weight:700; margin-bottom:4px; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></span>
                        <span><?php esc_html_e('Where to get your Cloudflare Pages Deploy Hook:', 'astropress'); ?></span>
                    </div>
                    <ol style="margin:4px 0 6px 18px; padding:0;">
                        <li><?php esc_html_e('Go to Cloudflare Dashboard &rarr; Workers & Pages &rarr; click your Astro project.', 'astropress'); ?></li>
                        <li><?php esc_html_e('Navigate to Settings &rarr; Builds & deployments &rarr; scroll down to Deploy hooks.', 'astropress'); ?></li>
                        <li><?php esc_html_e('Click "Add deploy hook", select your production branch (e.g. main), and copy the URL.', 'astropress'); ?></li>
                    </ol>
                    <div style="color:#2563eb; font-size:11px;">
                        <strong><?php esc_html_e('Why is this necessary?', 'astropress'); ?></strong> <?php esc_html_e('Cloudflare Pages only loads updated environment variables into your live Astro frontend when a new build finishes. A verified Deploy Hook triggers that build automatically.', 'astropress'); ?>
                    </div>
                </div>

                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <div style="position:relative; flex:1; min-width:260px;">
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

            <!-- Action: Put Key to Pages & Trigger Deploy -->
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <strong style="display:block; font-size:13px; color:#14532d;">
                            <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Push Secret to Cloudflare Pages & Trigger Deploy:', 'astropress'); ?>
                        </strong>
                        <span style="font-size:12px; color:#166534;">
                            <?php esc_html_e('Encodes REVALIDATE_SECRET as secret_text on Pages, then triggers Deploy Hook so your Astro site recompiles with the new key.', 'astropress'); ?>
                        </span>
                    </div>
                    <button type="button" class="button button-primary" id="onboard-put-secret-btn" style="display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-cloud-upload"></span>
                        <span class="btn-text"><?php esc_html_e('Put Key to Pages & Trigger Deploy', 'astropress'); ?></span>
                    </button>
                </div>

                <!-- 1-2 minute build warning notice -->
                <div style="margin-top:12px; padding:10px 12px; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; font-size:12px; color:#92400e; display:flex; align-items:flex-start; gap:8px;">
                    <span class="dashicons dashicons-clock" style="font-size:16px; width:16px; height:16px; margin-top:2px; color:#b45309;"></span>
                    <div>
                        <strong><?php esc_html_e('Important note on build timing:', 'astropress'); ?></strong>
                        <?php esc_html_e('Cloudflare Pages takes 1–2 minutes to compile and build your Astro site. After clicking "Put Key to Pages & Trigger Deploy", please wait a couple minutes before testing the handshake below to allow the deployment to become live.', 'astropress'); ?>
                    </div>
                </div>

                <div id="onboard-put-secret-status" style="margin-top:10px; display:none;"></div>
            </div>

            <!-- Test Astro Handshake -->
            <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; flex-wrap:wrap; gap:10px;">
                <div>
                    <strong style="display:block; font-size:13px; color:#1e293b;"><?php esc_html_e('Test Astro SSR Handshake:', 'astropress'); ?></strong>
                    <span style="font-size:12px; color:#64748b;"><?php esc_html_e('Sends a live test ping to /api/revalidate on your frontend to confirm HMAC / ticket authentication.', 'astropress'); ?></span>
                </div>
                <button type="button" class="button button-secondary" id="onboard-test-handshake-btn" style="display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-network"></span>
                    <span><?php esc_html_e('Test Astro Handshake', 'astropress'); ?></span>
                </button>
            </div>
            <div id="onboard-handshake-quick-status" style="display:none;"></div>
        </div>

        <!-- Stage 2 Dedicated Save Action -->
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:12px; color:#64748b;">
                <?php esc_html_e('Saves your revalidation secret and deploy hook independently to protect your progress.', 'astropress'); ?>
            </div>
            <button type="button" class="button button-primary" id="onboard-save-step2-btn" style="display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-saved"></span>
                <span><?php esc_html_e('Save Revalidation Settings (Stage 2)', 'astropress'); ?></span>
            </button>
        </div>
        <div id="onboard-step2-save-status" style="margin-top:10px; display:none;"></div>
    </div>

    <!-- ======================================================== -->
    <!-- STAGE 3: Media Storage & Cloudflare R2 CDN Offloading   -->
    <!-- ======================================================== -->
    <div class="astropress-card" id="onboard-stage-3" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; margin-bottom:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="background:#0284c7; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px;">3</span>
                <div>
                    <h3 style="margin:0; font-size:18px; color:#0f172a;"><?php esc_html_e('Stage 3: Media Storage & Cloudflare R2 Offloading', 'astropress'); ?></h3>
                    <p style="margin:2px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e('Store media on Cloudflare R2 with zero egress fees and custom global CDN delivery, or keep local.', 'astropress'); ?></p>
                </div>
            </div>
            <div id="onboard-step3-status-pill">
                <?php if ($media_mode === 'r2' && !empty($bucket)): ?>
                    <span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                        <?php echo esc_html(sprintf(__('R2: %s', 'astropress'), $bucket)); ?>
                    </span>
                <?php else: ?>
                    <span style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;">
                        <?php echo ($media_mode === 'wp') ? esc_html__('Local Storage Mode', 'astropress') : esc_html__('R2 Storage Pending', 'astropress'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label for="onboard_media_mode" style="display:block; font-weight:600; margin-bottom:6px; color:#334155;">
                <?php esc_html_e('Storage Mode:', 'astropress'); ?>
            </label>
            <select id="onboard_media_mode" name="onboard_media_mode" style="width:100%; max-width:100%;">
                <option value="r2" <?php selected($media_mode, 'r2'); ?>><?php esc_html_e('Cloudflare R2 Object Storage (Recommended - Zero Egress + Global CDN)', 'astropress'); ?></option>
                <option value="wp" <?php selected($media_mode, 'wp'); ?>><?php esc_html_e('WordPress Local Server Storage (Store uploads on local server)', 'astropress'); ?></option>
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
                    <p style="font-size:12px; color:#64748b; margin:4px 0 0 0;"><?php esc_html_e('Discovered from your Cloudflare account, or specify a new bucket.', 'astropress'); ?></p>
                </div>

                <!-- Custom CDN Domain -->
                <div>
                    <label for="onboard_r2_custom_domain" style="display:block; font-weight:600; font-size:13px; margin-bottom:4px; color:#334155;">
                        <?php esc_html_e('Custom CDN Domain for Media:', 'astropress'); ?>
                    </label>
                    <input type="text" id="onboard_r2_custom_domain" name="onboard_r2_custom_domain" value="<?php echo esc_attr($custom_domain); ?>" placeholder="media.topnepali.com" class="regular-text" style="width:100%; max-width:100%;" />
                    <div id="onboard-bucket-domain-status" style="margin-top:6px; font-size:12px;"></div>
                    
                    <label style="display:flex; align-items:center; gap:8px; margin-top:8px; font-size:12px; color:#334155; cursor:pointer;">
                        <input type="checkbox" id="onboard_confirm_attach_domain" name="onboard_confirm_attach_domain" value="1" checked />
                        <span><?php esc_html_e('Attach this domain to R2 bucket & automatically create proxied DNS CNAME on Cloudflare', 'astropress'); ?></span>
                    </label>
                    <p style="font-size:11px; color:#64748b; margin:4px 0 0 0;">
                        <?php printf(esc_html__('Must be a subdomain of your root zone (e.g. %s).', 'astropress'), '<code>media.' . esc_html($allowed_roots[0] ?? 'topnepali.com') . '</code>'); ?>
                    </p>
                </div>

                <!-- Optional S3 API Keys -->
                <div style="border-top:1px dashed #cbd5e1; padding-top:12px; margin-top:4px;">
                    <button type="button" class="button button-link" id="onboard-toggle-s3-creds-btn" style="font-size:12px; color:#0284c7; text-decoration:none; padding:0;">
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:14px; width:14px; height:14px; vertical-align:-2px;"></span>
                        <?php esc_html_e('Optional: Configure R2 S3 API Credentials (Access Key & Secret Key)', 'astropress'); ?>
                    </button>
                    <div id="onboard-s3-creds-wrap" style="display:none; margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px;">
                        <div>
                            <label for="onboard_r2_access_key" style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;"><?php esc_html_e('R2 Access Key ID:', 'astropress'); ?></label>
                            <input type="text" id="onboard_r2_access_key" name="onboard_r2_access_key" value="<?php echo esc_attr($access_key); ?>" class="regular-text" style="width:100%;" placeholder="e.g. 7c9d..." />
                        </div>
                        <div>
                            <label for="onboard_r2_secret_key" style="display:block; font-size:12px; font-weight:600; margin-bottom:4px;"><?php esc_html_e('R2 Secret Access Key:', 'astropress'); ?></label>
                            <input type="password" id="onboard_r2_secret_key" name="onboard_r2_secret_key" value="<?php echo $has_secret_key ? '••••••••••••••••••••••••••••••••' : ''; ?>" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('Paste S3 Secret Key...', 'astropress'); ?>" autocomplete="new-password" />
                        </div>
                    </div>
                </div>

                <!-- Test R2 Connection button -->
                <div style="margin-top:6px;">
                    <button type="button" class="button button-secondary" id="onboard-test-r2-btn" style="display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-database"></span>
                        <span><?php esc_html_e('Test R2 Storage Connection', 'astropress'); ?></span>
                    </button>
                    <div id="onboard-r2-quick-status" style="margin-top:8px; display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Stage 3 Dedicated Save Action -->
        <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="font-size:12px; color:#64748b;">
                <?php esc_html_e('Saves your media storage method and bucket settings without modifying edge connection or secrets.', 'astropress'); ?>
            </div>
            <button type="button" class="button button-primary" id="onboard-save-step3-btn" style="display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-saved"></span>
                <span><?php esc_html_e('Save Media Settings (Stage 3)', 'astropress'); ?></span>
            </button>
        </div>
        <div id="onboard-step3-save-status" style="margin-top:10px; display:none;"></div>
    </div>

    <!-- ======================================================== -->
    <!-- UNIFIED GLOBAL ACTION: Save All Settings & Complete      -->
    <!-- ======================================================== -->
    <div style="background:#0f172a; border-radius:12px; padding:24px; text-align:center; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom:24px;">
        <h3 style="color:#fff; margin:0 0 6px 0; font-size:18px;"><?php esc_html_e('Ready to Finalize Configuration?', 'astropress'); ?></h3>
        <p style="color:#94a3b8; font-size:13px; margin:0 0 16px 0;"><?php esc_html_e('Save all 3 stages simultaneously to ensure complete edge and frontend integration.', 'astropress'); ?></p>
        <button type="button" class="button button-primary button-hero" id="onboard-save-all-btn" style="background:#0284c7; border-color:#0284c7; font-size:15px; height:auto; padding:10px 28px; display:inline-flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-yes-alt"></span>
            <span><?php esc_html_e('Save All Settings & Complete Setup', 'astropress'); ?></span>
        </button>
        <div id="onboard-save-all-status" style="margin-top:14px; display:none;"></div>
    </div>

    <!-- Cloudflare Token Scopes Reference Accordion -->
    <div style="text-align:center; margin-top:16px;">
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
