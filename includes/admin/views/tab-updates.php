<?php
/**
 * Admin View: GitHub Core Updates & Rollback Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $updater = AstroPress_Updater::instance();
        $latest  = $updater->get_latest_release();
        $all     = $updater->get_all_releases();
        $current = ASTROPRESS_VERSION;
        ?>
        <div class="astropress-card" style="max-width:850px;">
            <h2><?php esc_html_e('GitHub Core Updates & Rollback Pipeline', 'astropress'); ?></h2>
            <p><?php esc_html_e('AstroPress updates directly via GitHub Releases using WordPress core native Plugin_Upgrader sandbox.', 'astropress'); ?></p>

            <?php if (!empty($_GET['checked'])): ?>
                <div class="notice notice-success inline" style="margin:12px 0 16px; padding:10px 14px; border-left-color:#10b981; background:#f0fdf4;">
                    <p style="margin:0; font-weight:600; color:#166534;"><span class="dashicons dashicons-yes-alt" style="color:#10b981; vertical-align:middle;"></span> <?php esc_html_e('Checked GitHub for latest releases successfully! Cache refreshed.', 'astropress'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['nonce_expired'])): ?>
                <div class="notice notice-warning inline" style="margin:12px 0 16px; padding:10px 14px; border-left-color:#f59e0b; background:#fffbeb;">
                    <p style="margin:0; font-weight:600; color:#b45309;"><span class="dashicons dashicons-warning" style="color:#f59e0b; vertical-align:middle;"></span> <?php esc_html_e('Security session token refreshed. Please click "Check for Updates Now" again.', 'astropress'); ?></p>
                </div>
            <?php endif; ?>

            <table class="astropress-status-table">
                <tr>
                    <td><strong><?php esc_html_e('Installed Version', 'astropress'); ?></strong></td>
                    <td><code>v<?php echo esc_html($current); ?></code></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Latest Available Version', 'astropress'); ?></strong></td>
                    <td>
                        <?php if (!empty($latest['version'])): ?>
                            <code>v<?php echo esc_html($latest['version']); ?></code>
                            <?php if (version_compare($current, $latest['version'], '<')): ?>
                                <span class="astropress-pill" style="background:#e02424; color:#fff; margin-left:6px;"><?php esc_html_e('Update Available', 'astropress'); ?></span>
                            <?php else: ?>
                                <span class="astropress-pill" style="background:#057a55; color:#fff; margin-left:6px;"><?php esc_html_e('Up to date', 'astropress'); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em><?php esc_html_e('Checking GitHub...', 'astropress'); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="astropress-btn-group" style="margin-top:20px;">
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=astropress&tab=tools&sub=updates&astropress_check_updates=1'), 'astropress_manual_check_nonce')); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Check for Updates Now', 'astropress'); ?>
                </a>

                <?php if (!empty($latest['version']) && version_compare($current, $latest['version'], '<')): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($updater->get_plugin_basename())), 'upgrade-plugin_' . $updater->get_plugin_basename())); ?>" class="button button-primary">
                        <?php esc_html_e('Upgrade to v' . $latest['version'], 'astropress'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <hr style="margin:30px 0; border:0; border-top:1px solid #e2e8f0;" />

            <h3><?php esc_html_e('Rollback / Switch Version', 'astropress'); ?></h3>
            <p><?php esc_html_e('If an update introduces unexpected behavior, you can instantly rollback to any previous release without manual FTP uploads.', 'astropress'); ?></p>

            <?php if (!empty($all) && is_array($all)): ?>
                <form method="get" action="<?php echo esc_url(self_admin_url('update.php')); ?>" onsubmit="return confirm('Rollback plugin to selected version?');">
                    <input type="hidden" name="action" value="upgrade-plugin" />
                    <input type="hidden" name="plugin" value="<?php echo esc_attr($updater->get_plugin_basename()); ?>" />
                    <?php wp_nonce_field('upgrade-plugin_' . $updater->get_plugin_basename()); ?>

                    <div class="astropress-inline-form">
                        <select name="astropress_target_version">
                            <?php foreach ($all as $rel): ?>
                                <option value="<?php echo esc_attr($rel['version']); ?>" <?php selected($rel['version'], $current); ?>>
                                    v<?php echo esc_html($rel['version']); ?> (<?php echo esc_html(date_i18n('Y-m-d', strtotime($rel['published']))); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button"><?php esc_html_e('Switch Version', 'astropress'); ?></button>
                    </div>
                </form>
            <?php else: ?>
                <p><em><?php esc_html_e('No previous releases found in cache.', 'astropress'); ?></em></p>
            <?php endif; ?>
        </div>
