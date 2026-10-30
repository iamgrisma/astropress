<?php
/**
 * Admin View: Edge Cache Warmer Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$saved_delay = (int) $this->core->get_warm_delay();
$saved_batch = (int) $this->core->get_warm_batch_size();
?>
<div class="astropress-card" style="max-width:960px;">
    <div class="astropress-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-update" style="color:#0284c7;"></span>
            <span><?php esc_html_e('Live Edge Cache Warmer & Sitemap Crawler', 'astropress'); ?></span>
        </h2>
        <div style="display:flex; align-items:center; gap:8px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" class="astropress-badge astropress-badge-online" style="text-decoration:none; font-size:11px;">
                <span class="dashicons dashicons-shield" style="font-size:13px; width:13px; height:13px; vertical-align:-2px;"></span>
                <?php esc_html_e('View Cloudflare Cache Rules &rarr;', 'astropress'); ?>
            </a>
            <span class="astropress-badge" style="background:#e0f2fe; color:#0369a1; font-size:11px;"><?php esc_html_e('Sub-15ms Edge HITs', 'astropress'); ?></span>
        </div>
    </div>
    <p style="color:#64748b; font-size:13px; margin:0 0 16px 0; line-height:1.5;">
        <?php esc_html_e('Pre-warms Cloudflare Edge CDN POPs in polite, throttled batches so visitors receive instant sub-15ms cache HITs without cold-start latency. To manage edge caching TTL rules, configure them in the', 'astropress'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" style="color:#0284c7; text-decoration:none; font-weight:600;"><?php esc_html_e('Cache Rules & Presets tab', 'astropress'); ?> &rarr;</a>
    </p>

    <!-- Server Capacity & Resource Advisory -->
    <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:8px; margin-bottom:18px;">
        <div style="display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#92400e;">
            <span class="dashicons dashicons-warning" style="font-size:16px; width:16px; height:16px;"></span>
            <?php esc_html_e('Polite Warming Guidance', 'astropress'); ?>
        </div>
        <p style="margin:4px 0 0 0; font-size:12px; color:#78350f; line-height:1.5;">
            <?php esc_html_e('Edge warming dispatches sequential HTTP requests to your Astro frontend and origin WordPress. For shared hosts with limited PHP workers, choose a pacing gap of 1,000ms–2,000ms and batch size 1–2, or click "Run in Background" to process via WP-Cron without keeping this browser tab open.', 'astropress'); ?>
        </p>
    </div>

    <div class="astropress-preset-grid" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div style="flex:1 1 240px; min-width:200px;">
            <label for="astropress-warm-preset" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                <?php esc_html_e('Select Warming Scope / XML Sitemap:', 'astropress'); ?>
            </label>
            <select id="astropress-warm-preset" style="width:100%; font-size:13px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                <optgroup label="<?php esc_attr_e('Standard Presets', 'astropress'); ?>">
                    <option value="standard" selected><?php esc_html_e('Standard (Core Pages + Top Categories + Top 20 Posts ~ 35 URLs)', 'astropress'); ?></option>
                    <option value="deep"><?php esc_html_e('Comprehensive Deep (Core Pages + Top Categories + Top 50 Posts ~ 80 URLs)', 'astropress'); ?></option>
                    <option value="tools_only"><?php esc_html_e('Interactive Web Tools Only', 'astropress'); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Complete Database Content (100% Coverage)', 'astropress'); ?>">
                    <option value="all_posts"><?php esc_html_e('All Published Posts (100% Complete from Database)', 'astropress'); ?></option>
                    <option value="all_pages"><?php esc_html_e('All Published Pages (100% Complete from Database)', 'astropress'); ?></option>
                    <option value="all_taxonomies"><?php esc_html_e('All Categories & Tags', 'astropress'); ?></option>
                    <option value="entire_site"><?php esc_html_e('Entire Website (All Posts, Pages, Categories, Tags & Core Pages)', 'astropress'); ?></option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('XML Sitemaps (Deep Crawl & XML Files)', 'astropress'); ?>">
                    <option value="sitemap_crawler"><?php esc_html_e('Deep Sitemap Crawler (Extract & Warm ALL Page URLs inside XML Sitemaps)', 'astropress'); ?></option>
                    <option value="sitemap_files_only"><?php esc_html_e('XML Sitemap Files Only (Refresh XML Index & Sub-Sitemaps on Edge)', 'astropress'); ?></option>
                    <option value="sitemap_posts"><?php esc_html_e('Posts Sitemap (/post-sitemap.xml)', 'astropress'); ?></option>
                    <option value="sitemap_pages"><?php esc_html_e('Pages Sitemap (/page-sitemap.xml)', 'astropress'); ?></option>
                    <option value="sitemap_categories"><?php esc_html_e('Categories Sitemap (/category-sitemap.xml)', 'astropress'); ?></option>
                </optgroup>
            </select>
        </div>

        <div style="flex:1 1 200px; min-width:180px;">
            <label for="astropress-warm-delay" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                <?php esc_html_e('Gap Between Requests (Pacing):', 'astropress'); ?>
            </label>
            <select id="astropress-warm-delay" style="width:100%; font-size:13px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                <option value="200" <?php selected($saved_delay, 200); ?>><?php esc_html_e('200ms (0.2s - Ultra Turbo / High Performance)', 'astropress'); ?></option>
                <option value="300" <?php selected($saved_delay, 300); ?>><?php esc_html_e('300ms (0.3s - Very Fast)', 'astropress'); ?></option>
                <option value="500" <?php selected($saved_delay, 500); ?>><?php esc_html_e('500ms (0.5s - Fast / Normal Server)', 'astropress'); ?></option>
                <option value="1000" <?php selected($saved_delay, 1000); ?>><?php esc_html_e('1,000ms (1.0s - Recommended Balanced)', 'astropress'); ?></option>
                <option value="1500" <?php selected($saved_delay, 1500); ?>><?php esc_html_e('1,500ms (1.5s - Moderate Shared Host)', 'astropress'); ?></option>
                <option value="2000" <?php selected($saved_delay, 2000); ?>><?php esc_html_e('2,000ms (2.0s - Low Impact Server)', 'astropress'); ?></option>
                <option value="3000" <?php selected($saved_delay, 3000); ?>><?php esc_html_e('3,000ms (3.0s - Conservative Safe)', 'astropress'); ?></option>
            </select>
        </div>

        <div style="flex:1 1 180px; min-width:160px;">
            <label for="astropress-warm-batch-size" style="display:block; font-size:12px; font-weight:600; color:#334155; margin-bottom:6px;">
                <?php esc_html_e('Batch Size (URLs / Request):', 'astropress'); ?>
            </label>
            <select id="astropress-warm-batch-size" style="width:100%; font-size:13px; padding:8px 12px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                <option value="1" <?php selected($saved_batch, 1); ?>><?php esc_html_e('1 URL / Batch (Ultra Gentle)', 'astropress'); ?></option>
                <option value="2" <?php selected($saved_batch, 2); ?>><?php esc_html_e('2 URLs / Batch (Gentle)', 'astropress'); ?></option>
                <option value="5" <?php selected($saved_batch, 5); ?>><?php esc_html_e('5 URLs / Batch (Recommended)', 'astropress'); ?></option>
                <option value="10" <?php selected($saved_batch, 10); ?>><?php esc_html_e('10 URLs / Batch (Fast Turbo)', 'astropress'); ?></option>
            </select>
        </div>
    </div>

    <!-- Action Buttons Row -->
    <div style="display:flex; gap:10px; margin-bottom:18px; flex-wrap:wrap;">
        <button type="button" id="astropress-start-warm-btn" class="button button-primary button-hero" style="display:inline-flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-controls-play" style="vertical-align:-2px;"></span> <?php esc_html_e('Start Interactive Warmer (Browser Mode)', 'astropress'); ?>
        </button>
        <button type="button" id="astropress-start-bg-warm-btn" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> <?php esc_html_e('Run in Background (WP-Cron)', 'astropress'); ?>
        </button>
        <button type="button" id="astropress-cancel-warm-btn" class="button button-link-delete" style="display:none; padding:8px 14px;">
            <span class="dashicons dashicons-controls-pause" style="vertical-align:-2px;"></span> <?php esc_html_e('Pause / Cancel', 'astropress'); ?>
        </button>
    </div>

    <!-- Persistent Background Job Box -->
    <div id="astropress-bg-warm-box" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #16a34a; padding:16px; border-radius:8px; margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
            <span style="font-size:13px; font-weight:700; color:#15803d;">
                <span class="dashicons dashicons-admin-generic" style="vertical-align:text-bottom;"></span>
                <?php esc_html_e('Background Warmer Job (WP-Cron):', 'astropress'); ?> <span id="astropress-bg-warm-current">0</span> / <span id="astropress-bg-warm-total">0</span>
            </span>
            <span id="astropress-bg-warm-status-pill" class="astropress-badge astropress-badge-online" style="font-size:11px;">RUNNING</span>
        </div>
        <div class="astropress-progress-bar-bg" style="background:#dcfce7;">
            <div id="astropress-bg-progress-bar-fill" class="astropress-progress-bar-fill" style="width: 0%; background:#16a34a;"></div>
        </div>
        <div id="astropress-bg-warm-meta" style="font-size:12px; color:#166534; margin:8px 0;">
            <?php esc_html_e('Job active in background. You may safely close this browser window.', 'astropress'); ?>
        </div>
        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
            <button type="button" id="astropress-pause-bg-btn" class="button button-secondary button-small">
                <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause Job', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-resume-bg-btn" class="button button-secondary button-small" style="display:none;">
                <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Resume Job', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-cancel-bg-btn" class="button button-link-delete button-small">
                <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Cancel Job', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-clear-bg-btn" class="button button-secondary button-small" style="display:none;">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Dismiss Job', 'astropress'); ?>
            </button>
        </div>
    </div>

    <!-- Real-time Progress Display -->
    <div id="astropress-warm-progress-box" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:13px; font-weight:600; color:#334155; flex-wrap:wrap; gap:8px;">
            <span><?php esc_html_e('Warming Progress:', 'astropress'); ?> <span id="astropress-warm-current">0</span> / <span id="astropress-warm-total">0</span></span>
            <span id="astropress-warm-percent" style="color:#0284c7; font-weight:700;">0%</span>
        </div>

        <div class="astropress-progress-bar-bg">
            <div id="astropress-progress-bar-fill" class="astropress-progress-bar-fill" style="width: 0%;"></div>
        </div>

        <div id="astropress-warm-current-url" style="font-size:12px; color:#64748b; margin:10px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?php esc_html_e('Initializing queue...', 'astropress'); ?>
        </div>

        <div style="margin:12px 0 6px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <strong style="font-size:11px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
                <?php esc_html_e('Live Cloudflare Edge Activity Console:', 'astropress'); ?>
            </strong>
            <span id="astropress-warm-status-pill" class="astropress-badge" style="background:#0284c7; color:#fff; font-size:10px;">IDLE</span>
        </div>
        <div id="astropress-warm-log" class="astropress-log-console"></div>
    </div>
</div>
