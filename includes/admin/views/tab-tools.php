<?php
/**
 * Admin View: Unified Tools & Logs Tab
 *
 * Unifies Cache Warmer, Webhook Logs, Astro Snippets, and Plugin Updates.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'warmer';
if (!in_array($sub, array('warmer', 'logs', 'integration', 'updates'), true)) {
    $sub = 'warmer';
}

$frontend_url = $this->core->get_frontend_url();
?>
<div class="astropress-tools-nav" style="display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=tools&sub=warmer')); ?>" 
       class="button <?php echo ($sub === 'warmer') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-performance" style="vertical-align:-2px;"></span> <?php esc_html_e('Cache Warmer', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=tools&sub=logs')); ?>" 
       class="button <?php echo ($sub === 'logs') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-list-view" style="vertical-align:-2px;"></span> <?php esc_html_e('Webhook Logs', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=tools&sub=integration')); ?>" 
       class="button <?php echo ($sub === 'integration') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-editor-code" style="vertical-align:-2px;"></span> <?php esc_html_e('Astro Snippets', 'astropress'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=tools&sub=updates')); ?>" 
       class="button <?php echo ($sub === 'updates') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-cloud-saved" style="vertical-align:-2px;"></span> <?php esc_html_e('Updates & Rollback', 'astropress'); ?>
    </a>
</div>

<div class="astropress-tool-content">
    <?php
    switch ($sub) {
        case 'logs':
            $this->render_tab_logs();
            break;
        case 'integration':
            $this->render_tab_integration($frontend_url);
            break;
        case 'updates':
            $this->render_tab_updates();
            break;
        case 'warmer':
        default:
            $this->render_tab_warmer();
            break;
    }
    ?>
</div>
