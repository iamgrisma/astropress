<?php
/**
 * Admin View: Unified Developer Tools & SEO Tab
 *
 * Unifies Headless SEO & Subdomain Shield, Revalidation Webhook Logs,
 * Astro Code Snippets, and GitHub Updates & Rollback.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$sub = isset($_GET['sub']) ? sanitize_key($_GET['sub']) : 'seo';
if (!in_array($sub, array('seo', 'logs', 'integration', 'updates'), true)) {
    $sub = 'seo';
}

$frontend_url = $this->core->get_frontend_url();
?>
<div class="astropress-tools-nav" style="display:flex; gap:8px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:12px; flex-wrap:wrap;">
    <a href="<?php echo esc_url(admin_url('admin.php?page=astropress&tab=tools&sub=seo')); ?>" 
       class="button <?php echo ($sub === 'seo') ? 'button-primary' : 'button-secondary'; ?>">
        <span class="dashicons dashicons-shield" style="vertical-align:-2px;"></span> <?php esc_html_e('SEO & Subdomain Shield', 'astropress'); ?>
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
        <span class="dashicons dashicons-cloud-saved" style="vertical-align:-2px;"></span> <?php esc_html_e('GitHub Updates & Rollback', 'astropress'); ?>
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
        case 'seo':
        default:
            $this->render_tab_seo();
            break;
    }
    ?>
</div>
