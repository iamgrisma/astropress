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
<div class="astropress-card" style="max-width:960px; padding:18px 20px;">
    <div class="astropress-card-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
        <h2 style="margin:0; font-size:17px; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-update" style="color:#0284c7;"></span>
            <span><?php esc_html_e('Live Edge Cache Warmer & Sitemap Crawler', 'astropress'); ?></span>
        </h2>
        <div style="display:flex; align-items:center; gap:6px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" class="astropress-badge astropress-badge-online" style="text-decoration:none; font-size:11px; padding:2px 8px;">
                <span class="dashicons dashicons-shield" style="font-size:12px; width:12px; height:12px; vertical-align:-1px;"></span>
                <?php esc_html_e('Cache Rules &rarr;', 'astropress'); ?>
            </a>
            <span class="astropress-badge" style="background:#e0f2fe; color:#0369a1; font-size:11px; padding:2px 8px;"><?php esc_html_e('Sub-15ms Edge HITs', 'astropress'); ?></span>
        </div>
    </div>
    <p style="color:#64748b; font-size:12px; margin:0 0 12px 0; line-height:1.4;">
        <?php esc_html_e('Pre-warms Cloudflare Edge CDN POPs in polite, throttled batches so visitors receive instant sub-15ms cache HITs without cold-start latency. To manage edge caching TTL rules, configure them in the', 'astropress'); ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=cloudflare&sub=rules')); ?>" style="color:#0284c7; text-decoration:none; font-weight:600;"><?php esc_html_e('Cache Rules & Presets tab', 'astropress'); ?> &rarr;</a>
    </p>

    <!-- Server Capacity & Resource Advisory -->
    <div style="background:#fffbeb; border:1px solid #fde68a; border-left:3px solid #f59e0b; padding:8px 12px; border-radius:6px; margin-bottom:12px;">
        <div style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#92400e;">
            <span class="dashicons dashicons-warning" style="font-size:14px; width:14px; height:14px;"></span>
            <span><?php esc_html_e('Polite Warming Guidance:', 'astropress'); ?></span>
            <span style="font-weight:400; color:#78350f; font-size:11px;">
                <?php esc_html_e('Sequential requests to Astro frontend. For shared hosts, choose 1,000ms pacing and batch 1–2, or run in background via WP-Cron.', 'astropress'); ?>
            </span>
        </div>
    </div>

    <div class="astropress-preset-grid" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <div style="flex:2 1 240px; min-width:200px;">
            <label for="astropress-warm-preset" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                <?php esc_html_e('Select Warming Scope / XML Sitemap:', 'astropress'); ?>
            </label>
            <select id="astropress-warm-preset" style="width:100%; font-size:12px; padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
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

        <div style="flex:1 1 160px; min-width:140px;">
            <label for="astropress-warm-delay" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                <?php esc_html_e('Pacing Gap:', 'astropress'); ?>
            </label>
            <select id="astropress-warm-delay" style="width:100%; font-size:12px; padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                <option value="200" <?php selected($saved_delay, 200); ?>><?php esc_html_e('200ms (Ultra Fast)', 'astropress'); ?></option>
                <option value="300" <?php selected($saved_delay, 300); ?>><?php esc_html_e('300ms (Fast)', 'astropress'); ?></option>
                <option value="500" <?php selected($saved_delay, 500); ?>><?php esc_html_e('500ms (Normal)', 'astropress'); ?></option>
                <option value="1000" <?php selected($saved_delay, 1000); ?>><?php esc_html_e('1,000ms (Balanced)', 'astropress'); ?></option>
                <option value="1500" <?php selected($saved_delay, 1500); ?>><?php esc_html_e('1,500ms (Shared Host)', 'astropress'); ?></option>
                <option value="2000" <?php selected($saved_delay, 2000); ?>><?php esc_html_e('2,000ms (Conservative)', 'astropress'); ?></option>
                <option value="3000" <?php selected($saved_delay, 3000); ?>><?php esc_html_e('3,000ms (Gentle)', 'astropress'); ?></option>
            </select>
        </div>

        <div style="flex:1 1 150px; min-width:130px;">
            <label for="astropress-warm-batch-size" style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:4px;">
                <?php esc_html_e('Batch Size:', 'astropress'); ?>
            </label>
            <select id="astropress-warm-batch-size" style="width:100%; font-size:12px; padding:6px 10px; border-radius:6px; border:1px solid #cbd5e1; background:#fff;">
                <option value="1" <?php selected($saved_batch, 1); ?>><?php esc_html_e('1 URL / Batch', 'astropress'); ?></option>
                <option value="2" <?php selected($saved_batch, 2); ?>><?php esc_html_e('2 URLs / Batch', 'astropress'); ?></option>
                <option value="5" <?php selected($saved_batch, 5); ?>><?php esc_html_e('5 URLs (Balanced)', 'astropress'); ?></option>
                <option value="10" <?php selected($saved_batch, 10); ?>><?php esc_html_e('10 URLs (Turbo)', 'astropress'); ?></option>
            </select>
        </div>
    </div>

    <!-- Action Buttons Row -->
    <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; align-items:center;">
        <button type="button" id="astropress-start-warm-btn" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px; padding:4px 14px;">
            <span class="dashicons dashicons-controls-play" style="vertical-align:-1px;"></span> <?php esc_html_e('Start Interactive Warmer', 'astropress'); ?>
        </button>
        <button type="button" id="astropress-start-bg-warm-btn" class="button button-secondary" style="display:inline-flex; align-items:center; gap:6px; padding:4px 14px;">
            <span class="dashicons dashicons-update" style="vertical-align:-1px;"></span> <?php esc_html_e('Run in Background (WP-Cron)', 'astropress'); ?>
        </button>
        <button type="button" id="astropress-cancel-warm-btn" class="button button-link-delete" style="display:none; padding:4px 10px;">
            <span class="dashicons dashicons-controls-pause" style="vertical-align:-1px;"></span> <?php esc_html_e('Pause / Cancel', 'astropress'); ?>
        </button>
    </div>

    <!-- Persistent Background Job Box -->
    <div id="astropress-bg-warm-box" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-left:3px solid #16a34a; padding:12px; border-radius:6px; margin-bottom:12px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">
            <span style="font-size:12px; font-weight:700; color:#15803d;">
                <span class="dashicons dashicons-admin-generic" style="vertical-align:text-bottom;"></span>
                <?php esc_html_e('Background Warmer Job (WP-Cron):', 'astropress'); ?> <span id="astropress-bg-warm-current">0</span> / <span id="astropress-bg-warm-total">0</span>
            </span>
            <span id="astropress-bg-warm-status-pill" class="astropress-badge astropress-badge-online" style="font-size:10px;">RUNNING</span>
        </div>
        <div class="astropress-progress-bar-bg" style="background:#dcfce7; height:8px;">
            <div id="astropress-bg-progress-bar-fill" class="astropress-progress-bar-fill" style="width: 0%; background:#16a34a; height:8px;"></div>
        </div>
        <div id="astropress-bg-warm-meta" style="font-size:11px; color:#166534; margin:6px 0;">
            <?php esc_html_e('Job active in background. You may safely close this browser window.', 'astropress'); ?>
        </div>
        <div style="display:flex; gap:6px; margin-top:8px; flex-wrap:wrap;">
            <button type="button" id="astropress-pause-bg-btn" class="button button-secondary button-small">
                <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-resume-bg-btn" class="button button-secondary button-small" style="display:none;">
                <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Resume', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-cancel-bg-btn" class="button button-link-delete button-small">
                <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Cancel', 'astropress'); ?>
            </button>
            <button type="button" id="astropress-clear-bg-btn" class="button button-secondary button-small" style="display:none;">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Dismiss', 'astropress'); ?>
            </button>
        </div>
    </div>

    <!-- Real-time Progress Display -->
    <div id="astropress-warm-progress-box" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:6px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:12px; font-weight:600; color:#334155; flex-wrap:wrap; gap:6px;">
            <span><?php esc_html_e('Warming Progress:', 'astropress'); ?> <span id="astropress-warm-current">0</span> / <span id="astropress-warm-total">0</span></span>
            <span id="astropress-warm-percent" style="color:#0284c7; font-weight:700;">0%</span>
        </div>

        <div class="astropress-progress-bar-bg" style="height:8px;">
            <div id="astropress-progress-bar-fill" class="astropress-progress-bar-fill" style="width: 0%; height:8px;"></div>
        </div>

        <div id="astropress-warm-current-url" style="font-size:11px; color:#64748b; margin:6px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?php esc_html_e('Initializing queue...', 'astropress'); ?>
        </div>

        <div style="margin:8px 0 4px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
            <strong style="font-size:10px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;">
                <?php esc_html_e('Live Cloudflare Edge Activity Console:', 'astropress'); ?>
            </strong>
            <span id="astropress-warm-status-pill" class="astropress-badge" style="background:#0284c7; color:#fff; font-size:9px; padding:1px 6px;">IDLE</span>
        </div>
        <div id="astropress-warm-log" class="astropress-log-console" style="max-height:220px; font-size:11px; line-height:1.4;"></div>
    </div>
</div>
