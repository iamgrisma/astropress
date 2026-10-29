<?php
/**
 * Admin View: Image Optimizer & Live Tester Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $enabled         = (int) get_option('astropress_compress_enabled', get_option('r2g_compress_enabled', 1));
        $format          = get_option('astropress_compress_format', get_option('r2g_compress_format', 'webp'));
        $quality         = (int) get_option('astropress_compress_quality', get_option('r2g_compress_quality', 82));
        $engine          = AstroPress_Optimizer::get_engine();
        $smart_routing   = (int) get_option('astropress_smart_routing', get_option('r2g_smart_routing', 1));
        $max_width       = (int) get_option('astropress_max_width', get_option('r2g_max_width', 1920));
        $max_height      = (int) get_option('astropress_max_height', get_option('r2g_max_height', 0));
        $strip_exif      = (int) get_option('astropress_strip_exif', get_option('r2g_strip_exif', 1));
        $auto_orient     = (int) get_option('astropress_auto_orient', get_option('r2g_auto_orient', 1));
        $backup_orig     = (int) get_option('astropress_backup_original', get_option('r2g_backup_original', 0));
        $editor_workflow = get_option('astropress_editor_workflow', get_option('r2g_editor_workflow', 'both'));
        $interceptor     = (int) get_option('astropress_interceptor_enabled', get_option('r2g_interceptor_enabled', 1));

        $can_webp        = AstroPress_Optimizer::can_generate_webp();
        $can_avif        = AstroPress_Optimizer::can_generate_avif();
        $has_imagick     = extension_loaded('imagick');
        $has_gd          = extension_loaded('gd');
        $upload_max      = ini_get('upload_max_filesize');
        ?>
        <div class="astropress-card" style="max-width:920px;">
            <div class="astropress-card-header">
                <h2><span class="dashicons dashicons-images-alt2"></span> <?php esc_html_e('Image Optimization & Next-Gen Formats', 'astropress'); ?></h2>
                <span class="astropress-badge astropress-badge-online"><?php esc_html_e('Enterprise Media Engine', 'astropress'); ?></span>
            </div>
            <p style="color: #64748b; font-size: 13px; margin-top: 0;">
                <?php esc_html_e('Enterprise image optimization with dual-stage engine routing: external reSmush.it Cloud API for zero server CPU on standard uploads, and server GD/Imagick for high-resolution images exceeding 5MB.', 'astropress'); ?>
            </p>

            <!-- Server Capabilities & Diagnostics Bar -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                <span style="font-size:12px; font-weight:700; color:#334155; margin-right:4px;">
                    <?php esc_html_e('Server Diagnostics:', 'astropress'); ?>
                </span>
                <span class="astropress-chip" style="<?php echo $can_webp ? 'background:#dcfce7; color:#166534;' : 'background:#fee2e2; color:#991b1b;'; ?>">
                    <strong>WebP:</strong> <?php echo $can_webp ? esc_html__('Supported', 'astropress') : esc_html__('Unsupported', 'astropress'); ?>
                </span>
                <span class="astropress-chip" style="<?php echo $can_avif ? 'background:#dcfce7; color:#166534;' : 'background:#f1f5f9; color:#64748b;'; ?>">
                    <strong>AVIF:</strong> <?php echo $can_avif ? esc_html__('Supported', 'astropress') : esc_html__('Unavailable', 'astropress'); ?>
                </span>
                <span class="astropress-chip" style="background:#e0f2fe; color:#0369a1;">
                    <strong>Driver:</strong> <?php echo $has_imagick ? 'Imagick' : ($has_gd ? 'PHP GD' : esc_html__('None', 'astropress')); ?>
                </span>
                <span class="astropress-chip" style="background:#f3e8ff; color:#6b21a8;">
                    <strong>Max Upload:</strong> <?php echo esc_html($upload_max); ?>
                </span>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('astropress_optimizer_action', 'astropress_optimizer_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Automation Status', 'astropress'); ?></th>
                        <td>
                            <label style="font-size:14px; font-weight:600; color:#0f172a;">
                                <input type="checkbox" name="compress_enabled" value="1" <?php checked($enabled, 1); ?> />
                                <?php esc_html_e('Enable automated media compression on all uploads', 'astropress'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('When checked, all incoming image uploads are automatically compressed, optimized, and prepared for Cloudflare R2 delivery.', 'astropress'); ?></p>
                        </td>
                    </tr>

                    <!-- Section: Engine & Smart Routing -->
                    <tr>
                        <th scope="row"><label for="compress_engine"><?php esc_html_e('Compression Engine', 'astropress'); ?></label></th>
                        <td>
                            <select id="compress_engine" name="compress_engine" style="font-size:13px; min-width:320px;">
                                <option value="smart" <?php selected($engine, 'smart'); ?>><?php esc_html_e('Smart Dual Engine (reSmush < 5MB, Server GD >= 5MB) [Recommended]', 'astropress'); ?></option>
                                <option value="resmush" <?php selected($engine, 'resmush'); ?>><?php esc_html_e('reSmush.it Cloud API Only (External cloud compression)', 'astropress'); ?></option>
                                <option value="server" <?php selected($engine, 'server'); ?>><?php esc_html_e('Server GD / Imagick Only (100% Local on-premises processing)', 'astropress'); ?></option>
                                <option value="none" <?php selected($engine, 'none'); ?>><?php esc_html_e('Raw Binary Passthrough (No compression / Pure R2 offload)', 'astropress'); ?></option>
                            </select>

                            <div style="margin-top:10px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:10px 14px;">
                                <label style="font-weight:600; color:#1e40af; font-size:13px;">
                                    <input type="checkbox" name="smart_routing" value="1" <?php checked($smart_routing, 1); ?> />
                                    <?php esc_html_e('Smart 5MB Engine Routing (Zero API Rejection)', 'astropress'); ?>
                                </label>
                                <p style="margin:4px 0 0 0; font-size:12px; color:#1e3a8a; line-height:1.5;">
                                    <?php esc_html_e('The reSmush.it Free API strictly enforces a 5MB payload limit. When enabled, images under 5MB are routed to reSmush.it (zero origin server CPU load), while images 5MB or larger automatically route to local Server GD/Imagick without failing.', 'astropress'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <!-- Section: Output Format -->
                    <tr>
                        <th scope="row"><label for="compress_format"><?php esc_html_e('Output Format', 'astropress'); ?></label></th>
                        <td>
                            <select id="compress_format" name="compress_format" style="font-size:13px; min-width:280px;">
                                <option value="webp" <?php selected($format, 'webp'); ?>><?php esc_html_e('WebP (Recommended — 30-80% smaller, 98% browser support)', 'astropress'); ?></option>
                                <?php if ($can_avif): ?>
                                    <option value="avif" <?php selected($format, 'avif'); ?>><?php esc_html_e('AVIF (Ultra high compression — next-gen fidelity)', 'astropress'); ?></option>
                                <?php endif; ?>
                                <option value="jpg" <?php selected($format, 'jpg'); ?>><?php esc_html_e('JPEG / JPG (Progressive universal format)', 'astropress'); ?></option>
                                <option value="original" <?php selected($format, 'original'); ?>><?php esc_html_e('Keep Original Format (Lossy compression without extension change)', 'astropress'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('WebP is universally supported across modern browsers and delivers dramatic bandwidth reduction for Astro headless frontends.', 'astropress'); ?></p>
                        </td>
                    </tr>

                    <!-- Section: Quality Slider & Quick Presets -->
                    <tr>
                        <th scope="row"><label for="compress_quality"><?php esc_html_e('Quality Level (1 - 100)', 'astropress'); ?></label></th>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                                <input type="range" id="compress_quality" name="compress_quality" min="50" max="100" value="<?php echo esc_attr($quality); ?>" oninput="document.getElementById('quality_val').textContent = this.value + '%';" style="max-width:260px;" />
                                <span id="quality_val" class="astropress-chip" style="font-weight:700; font-size:13px; background:#e0f2fe; color:#0369a1;"><?php echo esc_html($quality); ?>%</span>
                            </div>

                            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:6px;">
                                <button type="button" class="button button-small astropress-preset-btn" data-quality="82" data-format="webp" data-width="1920">
                                    <?php esc_html_e('Balanced (82%)', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-small astropress-preset-btn" data-quality="90" data-format="webp" data-width="2560">
                                    <?php esc_html_e('High Quality (90%)', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-small astropress-preset-btn" data-quality="72" data-format="webp" data-width="1600">
                                    <?php esc_html_e('Aggressive (72%)', 'astropress'); ?>
                                </button>
                                <button type="button" class="button button-small astropress-preset-btn" data-quality="100" data-format="original" data-width="0">
                                    <?php esc_html_e('Lossless / Passthrough (100%)', 'astropress'); ?>
                                </button>
                            </div>
                            <p class="description" style="margin-top:6px;"><?php esc_html_e('82% provides the industry gold standard between crisp photographic fidelity and ultra-low file size.', 'astropress'); ?></p>
                        </td>
                    </tr>

                    <!-- Section: Dimensions & Scaling -->
                    <tr>
                        <th scope="row"><?php esc_html_e('Max Dimensions (Downscaling)', 'astropress'); ?></th>
                        <td>
                            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                                <div>
                                    <label for="max_width" style="font-size:12px; font-weight:600; color:#475569;"><?php esc_html_e('Max Width:', 'astropress'); ?></label><br />
                                    <input type="number" id="max_width" name="max_width" value="<?php echo esc_attr($max_width); ?>" class="small-text" style="width:100px;" /> px
                                </div>
                                <div>
                                    <label for="max_height" style="font-size:12px; font-weight:600; color:#475569;"><?php esc_html_e('Max Height (Optional):', 'astropress'); ?></label><br />
                                    <input type="number" id="max_height" name="max_height" value="<?php echo esc_attr($max_height); ?>" class="small-text" style="width:100px;" /> px
                                </div>
                            </div>
                            <p class="description"><?php esc_html_e('Downscales high-megapixel camera photos before compression. Set 0 to disable dimension downscaling.', 'astropress'); ?></p>
                        </td>
                    </tr>

                    <!-- Section: Metadata & Safety -->
                    <tr>
                        <th scope="row"><?php esc_html_e('EXIF, Orientation & Backups', 'astropress'); ?></th>
                        <td>
                            <fieldset style="display:flex; flex-direction:column; gap:8px;">
                                <label>
                                    <input type="checkbox" name="strip_exif" value="1" <?php checked($strip_exif, 1); ?> />
                                    <strong><?php esc_html_e('Strip EXIF & IPTC metadata', 'astropress'); ?></strong> — <?php esc_html_e('Removes camera serials, GPS coordinates, and exposure data for privacy and 5-15% extra byte reduction.', 'astropress'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="auto_orient" value="1" <?php checked($auto_orient, 1); ?> />
                                    <strong><?php esc_html_e('Auto-orient images using EXIF rotation', 'astropress'); ?></strong> — <?php esc_html_e('Ensures smartphone portrait and landscape photos render right-side up before stripping EXIF tags.', 'astropress'); ?>
                                </label>
                                <label>
                                    <input type="checkbox" name="backup_original" value="1" <?php checked($backup_orig, 1); ?> />
                                    <strong><?php esc_html_e('Backup original uncompressed file', 'astropress'); ?></strong> — <?php esc_html_e('Saves an untouched .original copy on local disk before transformation.', 'astropress'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Section: Workflow Gateways (Option A vs Option B) -->
                    <tr>
                        <th scope="row"><?php esc_html_e('Upload Gateways & UI Architecture', 'astropress'); ?></th>
                        <td>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:14px; margin-bottom:12px;">
                                <!-- Gateway A Card -->
                                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:14px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <h4 style="margin:0; font-size:13px; font-weight:700; color:#0f172a;">
                                            <span class="dashicons dashicons-admin-media" style="vertical-align:-2px; color:#2563eb;"></span>
                                            <?php esc_html_e('Gateway A: Media Library & Gates', 'astropress'); ?>
                                        </h4>
                                        <span class="astropress-chip" style="background:#dcfce7; color:#166534; font-size:11px;">
                                            <?php esc_html_e('Fully Automated', 'astropress'); ?>
                                        </span>
                                    </div>
                                    <p style="margin:0; font-size:12px; color:#64748b; line-height:1.5;">
                                        <?php esc_html_e('Media Library bulk drops, REST API, XML-RPC, and CLI uploads process automatically in the background without modal interruptions. Plupload queue never stalls.', 'astropress'); ?>
                                    </p>
                                </div>

                                <!-- Gateway B Card -->
                                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:14px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <h4 style="margin:0; font-size:13px; font-weight:700; color:#0f172a;">
                                            <span class="dashicons dashicons-edit" style="vertical-align:-2px; color:#f59e0b;"></span>
                                            <?php esc_html_e('Gateway B: Post & Block Editors', 'astropress'); ?>
                                        </h4>
                                        <span class="astropress-chip" style="background:#fef3c7; color:#92400e; font-size:11px;">
                                            <?php esc_html_e('Option B Active', 'astropress'); ?>
                                        </span>
                                    </div>
                                    <p style="margin:0 0 10px 0; font-size:12px; color:#64748b; line-height:1.5;">
                                        <?php esc_html_e('Gutenberg block inspector provides a per-image control panel in the editor sidebar showing compression savings and 1-click re-compression.', 'astropress'); ?>
                                    </p>
                                    <select name="editor_workflow" style="font-size:12px; width:100%;">
                                        <option value="both" <?php selected($editor_workflow, 'both'); ?>><?php esc_html_e('Gutenberg Inspector Sidebar (Option B) + Upload Modal', 'astropress'); ?></option>
                                        <option value="sidebar_only" <?php selected($editor_workflow, 'sidebar_only'); ?>><?php esc_html_e('Gutenberg Inspector Sidebar Only (Option B — Clean, zero popups)', 'astropress'); ?></option>
                                        <option value="modal_only" <?php selected($editor_workflow, 'modal_only'); ?>><?php esc_html_e('Upload Interceptor Modal Only (Canvas drop popup)', 'astropress'); ?></option>
                                        <option value="silent" <?php selected($editor_workflow, 'silent'); ?>><?php esc_html_e('Completely Silent Everywhere', 'astropress'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <label style="font-size:12px; color:#475569;">
                                <input type="checkbox" name="interceptor_enabled" value="1" <?php checked($interceptor, 1); ?> />
                                <?php esc_html_e('Enable interactive upload modal dialog when dropping images into post editors', 'astropress'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="astropress_save_optimizer" class="button button-primary" style="padding:6px 18px; font-size:14px;">
                        <span class="dashicons dashicons-saved" style="vertical-align:-3px;"></span>
                        <?php esc_html_e('Save Optimizer Settings', 'astropress'); ?>
                    </button>
                </p>
            </form>

            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;" />

            <!-- Live Compression Preview Tester -->
            <div class="astropress-card-header" style="margin-bottom:10px;">
                <h3 style="margin:0; font-size:16px;">
                    <span class="dashicons dashicons-visibility" style="vertical-align:-2px; color:#2563eb;"></span>
                    <?php esc_html_e('Live Compression Preview & Routing Tester', 'astropress'); ?>
                </h3>
            </div>
            <p style="color: #64748b; font-size: 13px; margin-top: 0;">
                <?php esc_html_e('Upload a sample image to verify the smart engine routing (reSmush.it for < 5MB vs Server GD for >= 5MB), inspect visual fidelity, and view exact byte savings before applying settings site-wide.', 'astropress'); ?>
            </p>

            <div class="astropress-preview-tester" style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; padding:18px;">
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                    <input type="file" id="astropress-test-image-input" accept="image/*" style="font-size:13px;" />
                    <button type="button" class="button button-secondary" id="astropress-run-preview-test" style="font-weight:600;">
                        <span class="dashicons dashicons-dashboard" style="vertical-align:-2px;"></span>
                        <?php esc_html_e('Test Image Compression', 'astropress'); ?>
                    </button>
                </div>
                <div id="astropress-preview-result" style="margin-top:16px; display:none;"></div>
            </div>
        </div>
