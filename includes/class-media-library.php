<?php
/**
 * AstroPress Media Library Integration
 *
 * Minimalist status badges, uncluttered column controls, bulk actions, and attachment modal details.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Media_Library {
    /**
     * Singleton instance
     */
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    private function __construct() {
        if (!is_admin()) {
            return;
        }

        // Add Columns
        add_filter('manage_media_columns', array($this, 'add_location_column'));
        add_action('manage_media_custom_column', array($this, 'render_location_column'), 10, 2);
        add_filter('manage_media_columns', array($this, 'add_compress_column'));
        add_action('manage_media_custom_column', array($this, 'render_compress_column'), 10, 2);

        // Filter Dropdown in Media Library
        add_action('restrict_manage_posts', array($this, 'render_location_filter'));
        add_action('pre_get_posts', array($this, 'apply_location_filter'));

        // Row Actions
        add_filter('media_row_actions', array($this, 'filter_media_row_actions'), 99, 2);

        // Bulk Actions
        add_filter('bulk_actions-upload', array($this, 'register_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);
        add_action('admin_notices', array($this, 'render_bulk_action_notices'));

        // Attachment Details Modal & Edit screen fields
        add_filter('attachment_fields_to_edit', array($this, 'add_attachment_modal_fields'), 10, 2);

        // Ajax Handlers (AstroPress & legacy R2G)
        $ajax_actions = array(
            'sync_single',
            'download_single',
            'delete_local_single',
            'delete_r2_single',
            'convert_webp_single',
            'recompress_attachment',
        );

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_astropress_{$action}", array($this, "ajax_{$action}"));
            add_action("wp_ajax_r2g_{$action}", array($this, "ajax_{$action}"));
        }
    }

    public function add_location_column($columns) {
        $columns['astropress_storage'] = esc_html__('R2 Cloud Storage', 'astropress');
        return $columns;
    }

    public function add_compress_column($columns) {
        $columns['astropress_compression'] = esc_html__('Optimization', 'astropress');
        return $columns;
    }

    public function render_compress_column($column_name, $post_id) {
        if ($column_name !== 'astropress_compression' && $column_name !== 'r2g_compression') {
            return;
        }

        if (!wp_attachment_is_image($post_id)) {
            echo '<span style="color:#94a3b8; font-size:12px;">—</span>';
            return;
        }

        $mime = get_post_mime_type($post_id);
        if (strpos($mime, 'svg') !== false) {
            echo '<span style="color:#94a3b8; font-size:12px;">Vector (SVG)</span>';
            return;
        }

        $file_path = get_attached_file($post_id);
        $ext = strtolower(pathinfo($file_path ?: '', PATHINFO_EXTENSION));
        $is_webp_or_avif = in_array($ext, array('webp', 'avif'), true) || in_array($mime, array('image/webp', 'image/avif'), true);
        $is_optimized = (bool) get_post_meta($post_id, '_astropress_optimized', true) || (bool) get_post_meta($post_id, '_r2g_optimized', true);
        $opt_info = get_post_meta($post_id, '_astropress_opt_info', true);
        if (empty($opt_info)) {
            $opt_info = get_post_meta($post_id, '_r2g_opt_info', true);
        }

        $engine_buttons = '<button type="button" class="button button-small astropress-btn-recompress" data-id="' . esc_attr($post_id) . '" data-engine="resmush" title="' . esc_attr__('Compress via reSmush.it (falls back to Server GD)', 'astropress') . '">' . esc_html__('reSmush', 'astropress') . '</button>'
            . '<button type="button" class="button button-small astropress-btn-recompress" data-id="' . esc_attr($post_id) . '" data-engine="server" title="' . esc_attr__('Compress using Server PHP GD / Imagick only', 'astropress') . '">' . esc_html__('Server GD', 'astropress') . '</button>';

        echo '<div class="astropress-compress-cell" id="astropress-compress-cell-' . esc_attr($post_id) . '">';

        if ($is_webp_or_avif || $is_optimized) {
            $format_label = strtoupper($ext ?: 'WebP');
            echo '<span class="astropress-badge astropress-badge-both" style="margin-bottom:4px;" title="' . esc_attr__('Optimized format with lightweight footprint', 'astropress') . '"><span class="dashicons dashicons-yes" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span> Optimal (' . esc_html($format_label) . ')</span>';

            if (is_array($opt_info) && !empty($opt_info['engine_used'])) {
                $orig = (int) ($opt_info['orig_bytes'] ?? 0);
                $final = (int) ($opt_info['final_bytes'] ?? 0);
                $saved_txt = ($orig > 0 && $final > 0 && $final < $orig) ? ' −' . round((($orig - $final) / $orig) * 100) . '%' : '';
                $engine_label = $opt_info['engine_used'] === 'resmush' ? 'reSmush.it' : 'Server GD';
                $is_fallback = (($opt_info['engine_requested'] ?? '') === 'resmush' && $opt_info['engine_used'] !== 'resmush');
                $line_color = $is_fallback ? '#b45309' : '#059669';
                echo '<div style="font-size:11px; color:' . esc_attr($line_color) . '; font-weight:600;" title="' . esc_attr($opt_info['note'] ?? '') . '">'
                    . esc_html($engine_label . $saved_txt . ($is_fallback ? ' (fallback)' : ''))
                    . '</div>';
            }

            echo '<div style="margin-top:4px;">
                    <a href="#" class="astropress-link-toggle-recompress" data-id="' . esc_attr($post_id) . '" style="font-size:11px; color:#64748b; text-decoration:none;">' . esc_html__('Re-compress ↻', 'astropress') . '</a>
                    <div class="astropress-recompress-opts" style="display:none; margin-top:4px; gap:4px; flex-wrap:wrap;">' . $engine_buttons . '</div>
                  </div>';
        } else {
            echo '<span style="font-size:11px; font-weight:600; color:#b45309; display:block; margin-bottom:4px;">' . esc_html(sprintf(__('Uncompressed (%s)', 'astropress'), strtoupper($ext))) . '</span>';
            echo '<div class="astropress-recompress-opts" style="display:flex; gap:4px; flex-wrap:wrap;">' . $engine_buttons . '</div>';
        }

        echo '</div>';
    }

    public function render_location_column($column_name, $post_id) {
        if ($column_name !== 'astropress_storage' && $column_name !== 'r2g_storage') {
            return;
        }

        $file_path = get_attached_file($post_id);
        $has_local = !empty($file_path) && file_exists($file_path);

        $record = class_exists('AstroPress_Database') ? AstroPress_Database::get($post_id) : null;
        $is_synced = false;

        if ($record && $record->status === 'synced') {
            $is_synced = true;
            $has_local = (bool) $record->has_local;
        } elseif (get_post_meta($post_id, '_astropress_synced', true) || get_post_meta($post_id, '_r2g_synced', true) || get_post_meta($post_id, '_mcs_synced', true)) {
            $is_synced = true;
            $has_local = $has_local && !get_post_meta($post_id, '_astropress_local_deleted', true) && !get_post_meta($post_id, '_r2g_local_deleted', true);
        }

        $rewriter = class_exists('AstroPress_URL_Rewriter') ? AstroPress_URL_Rewriter::instance() : null;
        $cdn_base = $rewriter ? $rewriter->get_cdn_base() : '';
        $raw_url = wp_get_attachment_url($post_id);
        $cdn_url = ($rewriter && !empty($cdn_base)) ? $rewriter->rewrite_url($raw_url) : $raw_url;

        echo '<div class="astropress-col-wrap">';

        if ($is_synced && $has_local) {
            echo '<span class="astropress-badge astropress-badge-both" title="' . esc_attr__('Synced on both Local Server and Cloudflare R2', 'astropress') . '">
                    <svg class="astropress-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                    <span>' . esc_html__('Synced (Both)', 'astropress') . '</span>
                  </span>';
        } elseif ($is_synced && !$has_local) {
            echo '<span class="astropress-badge astropress-badge-cloud" title="' . esc_attr__('Offloaded to Cloudflare R2 only (Local file deleted to save disk space)', 'astropress') . '">
                    <svg class="astropress-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                    <span>' . esc_html__('Cloud Only', 'astropress') . '</span>
                  </span>';
        } elseif ($has_local) {
            echo '<span class="astropress-badge astropress-badge-local" title="' . esc_attr__('Stored on local server only (Not yet synced to R2)', 'astropress') . '">
                    <svg class="astropress-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/></svg>
                    <span>' . esc_html__('Local Only', 'astropress') . '</span>
                  </span>';
        } else {
            echo '<span class="astropress-badge astropress-badge-missing" title="' . esc_attr__('File missing both locally and on R2', 'astropress') . '">
                    <svg class="astropress-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/></svg>
                    <span>' . esc_html__('Missing', 'astropress') . '</span>
                  </span>';
        }

        echo '<div class="astropress-col-btns">';

        if (!$is_synced && $has_local) {
            echo '<button type="button" class="button button-small astropress-row-action astropress-btn-push" data-action="astropress_sync_single" data-id="' . esc_attr($post_id) . '">' . esc_html__('Push to R2', 'astropress') . '</button>';
        }

        if ($is_synced && $has_local) {
            echo '<button type="button" class="button button-small astropress-row-action" data-action="astropress_delete_local_single" data-id="' . esc_attr($post_id) . '" data-confirm="' . esc_attr__('Delete local server copy? The file remains safe on Cloudflare R2.', 'astropress') . '" title="' . esc_attr__('Free server disk space', 'astropress') . '">' . esc_html__('Delete Local', 'astropress') . '</button>';
            echo '<button type="button" class="button button-small astropress-row-action astropress-btn-danger" data-action="astropress_delete_r2_single" data-id="' . esc_attr($post_id) . '" data-confirm="' . esc_attr__('Remove this file and its thumbnails from Cloudflare R2?', 'astropress') . '" title="' . esc_attr__('Remove cloud copy', 'astropress') . '">' . esc_html__('Remove R2', 'astropress') . '</button>';
        }

        if ($is_synced && !$has_local) {
            echo '<button type="button" class="button button-small astropress-row-action" data-action="astropress_download_single" data-id="' . esc_attr($post_id) . '" title="' . esc_attr__('Download from R2 to local server disk', 'astropress') . '">' . esc_html__('Restore Local', 'astropress') . '</button>';
            echo '<button type="button" class="button button-small astropress-row-action astropress-btn-danger" data-action="astropress_delete_r2_single" data-id="' . esc_attr($post_id) . '" data-confirm="' . esc_attr__('Delete from Cloudflare R2? WARNING: Local file is already deleted.', 'astropress') . '" title="' . esc_attr__('Remove cloud copy', 'astropress') . '">' . esc_html__('Remove R2', 'astropress') . '</button>';
        }

        if ($is_synced && !empty($cdn_url)) {
            echo '<button type="button" class="button button-small astropress-btn-copy-cdn" data-url="' . esc_url($cdn_url) . '" title="' . esc_attr__('Copy Public CDN URL', 'astropress') . '">' . esc_html__('Copy CDN', 'astropress') . '</button>';
        }

        echo '</div>';
        echo '</div>';
    }

    public function filter_media_row_actions($actions, $post) {
        if ($post->post_type !== 'attachment') {
            return $actions;
        }

        unset(
            $actions['astropress_push'],
            $actions['astropress_pull'],
            $actions['astropress_del_local'],
            $actions['r2g_push'],
            $actions['r2g_pull'],
            $actions['r2g_del_local']
        );

        return $actions;
    }

    public function render_location_filter() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload') {
            return;
        }

        $selected = isset($_GET['astropress_location']) ? sanitize_key($_GET['astropress_location']) : (isset($_GET['r2g_location']) ? sanitize_key($_GET['r2g_location']) : '');
        ?>
        <select name="astropress_location" id="filter-by-astropress-location">
            <option value="" <?php selected($selected, ''); ?>><?php esc_html_e('All Storage Locations', 'astropress'); ?></option>
            <option value="both" <?php selected($selected, 'both'); ?>><?php esc_html_e('Synced (Both Local & R2)', 'astropress'); ?></option>
            <option value="cloud_only" <?php selected($selected, 'cloud_only'); ?>><?php esc_html_e('Cloud Only (R2)', 'astropress'); ?></option>
            <option value="local_only" <?php selected($selected, 'local_only'); ?>><?php esc_html_e('Local Server Only', 'astropress'); ?></option>
        </select>
        <?php
    }

    public function apply_location_filter($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
            return;
        }

        $filter = isset($_GET['astropress_location']) ? sanitize_key($_GET['astropress_location']) : (isset($_GET['r2g_location']) ? sanitize_key($_GET['r2g_location']) : '');
        if (empty($filter)) {
            return;
        }

        global $wpdb;
        $table = class_exists('AstroPress_Database') ? AstroPress_Database::table() : '';

        switch ($filter) {
            case 'both':
                $ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE status = 'synced' AND has_local = 1");
                $query->set('post__in', !empty($ids) ? $ids : array(0));
                break;
            case 'cloud_only':
                $ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE status = 'synced' AND has_local = 0");
                $query->set('post__in', !empty($ids) ? $ids : array(0));
                break;
            case 'local_only':
                $synced_ids = $wpdb->get_col("SELECT attachment_id FROM {$table} WHERE status = 'synced'");
                if (!empty($synced_ids)) {
                    $query->set('post__not_in', $synced_ids);
                }
                break;
        }
    }

    public function register_bulk_actions($actions) {
        $actions['astropress_bulk_push']      = esc_html__('AstroPress: Push to Cloudflare R2', 'astropress');
        $actions['astropress_bulk_pull']      = esc_html__('AstroPress: Restore Local Files from R2', 'astropress');
        $actions['astropress_bulk_del_local'] = esc_html__('AstroPress: Delete Local Files (Keep Cloud)', 'astropress');
        $actions['astropress_bulk_del_r2']    = esc_html__('AstroPress: Remove from Cloudflare R2', 'astropress');
        return $actions;
    }

    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if (empty($post_ids) || !is_array($post_ids)) {
            return $redirect_to;
        }

        $success_count = 0;

        switch ($doaction) {
            case 'astropress_bulk_push':
            case 'r2g_bulk_push':
                foreach ($post_ids as $id) {
                    if (AstroPress_Media_Handler::sync_attachment_to_r2((int)$id, true)) {
                        $success_count++;
                    }
                }
                $redirect_to = add_query_arg('astropress_bulk_action', 'pushed', $redirect_to);
                $redirect_to = add_query_arg('astropress_count', $success_count, $redirect_to);
                break;

            case 'astropress_bulk_pull':
            case 'r2g_bulk_pull':
                foreach ($post_ids as $id) {
                    if (AstroPress_Media_Handler::download_from_r2_to_local((int)$id)) {
                        $success_count++;
                    }
                }
                $redirect_to = add_query_arg('astropress_bulk_action', 'pulled', $redirect_to);
                $redirect_to = add_query_arg('astropress_count', $success_count, $redirect_to);
                break;

            case 'astropress_bulk_del_local':
            case 'r2g_bulk_del_local':
                foreach ($post_ids as $id) {
                    if (AstroPress_Media_Handler::delete_local_files((int)$id)) {
                        $success_count++;
                    }
                }
                $redirect_to = add_query_arg('astropress_bulk_action', 'local_deleted', $redirect_to);
                $redirect_to = add_query_arg('astropress_count', $success_count, $redirect_to);
                break;

            case 'astropress_bulk_del_r2':
            case 'r2g_bulk_del_r2':
                foreach ($post_ids as $id) {
                    if (AstroPress_Media_Handler::delete_from_r2((int)$id)) {
                        $success_count++;
                    }
                }
                $redirect_to = add_query_arg('astropress_bulk_action', 'r2_deleted', $redirect_to);
                $redirect_to = add_query_arg('astropress_count', $success_count, $redirect_to);
                break;
        }

        return $redirect_to;
    }

    public function render_bulk_action_notices() {
        if (empty($_GET['astropress_bulk_action'])) {
            return;
        }

        $action = sanitize_key($_GET['astropress_bulk_action']);
        $count = (int)($_GET['astropress_count'] ?? 0);

        $messages = array(
            'pushed'        => sprintf(esc_html__('Successfully synced %d attachment(s) to Cloudflare R2.', 'astropress'), $count),
            'pulled'        => sprintf(esc_html__('Successfully restored %d file(s) from R2 to local server.', 'astropress'), $count),
            'local_deleted' => sprintf(esc_html__('Deleted local copies for %d attachment(s) after R2 verification.', 'astropress'), $count),
            'r2_deleted'    => sprintf(esc_html__('Removed %d attachment(s) from Cloudflare R2 bucket.', 'astropress'), $count),
        );

        if (isset($messages[$action])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$action]) . '</p></div>';
        }
    }

    public function add_attachment_modal_fields($form_fields, $post) {
        $rewriter = class_exists('AstroPress_URL_Rewriter') ? AstroPress_URL_Rewriter::instance() : null;
        $cdn_base = $rewriter ? $rewriter->get_cdn_base() : '';
        $raw_url = wp_get_attachment_url($post->ID);
        $cdn_url = ($rewriter && !empty($cdn_base)) ? $rewriter->rewrite_url($raw_url) : $raw_url;

        $record = class_exists('AstroPress_Database') ? AstroPress_Database::get($post->ID) : null;
        $is_synced = $record && $record->status === 'synced';

        $status_html = $is_synced
            ? '<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' . esc_html__('Synced on Cloudflare R2', 'astropress') . '</span>'
            : '<span style="color:#64748b;">' . esc_html__('Local Only (Not Synced)', 'astropress') . '</span>';

        $form_fields['astropress_status'] = array(
            'label' => esc_html__('R2 Status', 'astropress'),
            'input' => 'html',
            'html'  => $status_html,
        );

        if ($is_synced && !empty($cdn_url)) {
            $form_fields['astropress_cdn_url'] = array(
                'label' => esc_html__('R2 CDN URL', 'astropress'),
                'input' => 'html',
                'html'  => '<input type="text" readonly value="' . esc_attr($cdn_url) . '" class="widefat" onclick="this.select();" />',
            );
        }

        return $form_fields;
    }

    private function check_permission($id, $action = 'edit') {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_REQUEST['nonce'] ?? ''));
        if (!empty($nonce) && (
            wp_verify_nonce($nonce, 'astropress_admin_nonce') ||
            wp_verify_nonce($nonce, 'astropress_onboarding_nonce') ||
            wp_verify_nonce($nonce, 'r2g_admin_nonce')
        )) {
            return $action === 'delete' ? current_user_can('delete_post', $id) : current_user_can('edit_post', $id);
        }
        return false;
    }

    public function ajax_sync_single() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'edit')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized or invalid attachment ID.', 'astropress')));
        }

        $ok = AstroPress_Media_Handler::sync_attachment_to_r2($id, true);
        if ($ok) {
            wp_send_json_success(array('message' => esc_html__('Successfully uploaded to Cloudflare R2', 'astropress')));
        }
        wp_send_json_error(array('message' => esc_html__('Failed to upload to Cloudflare R2', 'astropress')));
    }

    public function ajax_download_single() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'edit')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized or invalid attachment ID.', 'astropress')));
        }

        $ok = AstroPress_Media_Handler::download_from_r2_to_local($id);
        if ($ok) {
            wp_send_json_success(array('message' => esc_html__('Successfully downloaded to local server', 'astropress')));
        }
        wp_send_json_error(array('message' => esc_html__('Failed to download from Cloudflare R2', 'astropress')));
    }

    public function ajax_delete_local_single() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'delete')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized: Permission denied.', 'astropress')));
        }

        $ok = AstroPress_Media_Handler::delete_local_files($id);
        if (!$ok) {
            wp_send_json_error(array('message' => esc_html__('Local files kept. R2 verification failed.', 'astropress')));
        }
        wp_send_json_success(array('message' => esc_html__('Local files deleted after R2 verification.', 'astropress')));
    }

    public function ajax_delete_r2_single() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'delete')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized: Permission denied.', 'astropress')));
        }

        $ok = AstroPress_Media_Handler::delete_from_r2($id);
        if ($ok) {
            wp_send_json_success(array('message' => esc_html__('File removed from Cloudflare R2 bucket.', 'astropress')));
        }
        wp_send_json_error(array('message' => esc_html__('Failed to remove from Cloudflare R2', 'astropress')));
    }

    public function ajax_convert_webp_single() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'edit')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized or invalid attachment ID.', 'astropress')));
        }

        $file_path = get_attached_file($id);
        if (!$file_path || !file_exists($file_path)) {
            AstroPress_Media_Handler::download_from_r2_to_local($id);
            $file_path = get_attached_file($id);
        }

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => 'File not found on server or R2.'));
        }

        $quality = (int) get_option('astropress_compress_quality', get_option('r2g_compress_quality', 82));
        $max_width = (int) get_option('astropress_max_width', get_option('r2g_max_width', 1920));

        $orig_size = (int) filesize($file_path);
        $opts = array(
            'format'    => 'webp',
            'quality'   => $quality,
            'max_width' => $max_width,
            'compress'  => true,
            'engine'    => AstroPress_Optimizer::get_default_engine(),
        );
        $opt_res = AstroPress_Optimizer::optimize_local_file($file_path, $opts);

        if (!empty($opt_res['success']) && !empty($opt_res['file_path'])) {
            $new_file = $opt_res['file_path'];
            update_attached_file($id, $new_file);
            wp_update_post(array(
                'ID'             => $id,
                'post_mime_type' => !empty($opt_res['mime']) ? $opt_res['mime'] : 'image/webp',
            ));

            AstroPress_Media_Handler::mark_processed($new_file);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $metadata = wp_generate_attachment_metadata($id, $new_file);
            wp_update_attachment_metadata($id, $metadata);
            update_post_meta($id, '_astropress_optimized', 1);
            update_post_meta($id, '_r2g_optimized', 1);
            update_post_meta($id, '_astropress_opt_info', AstroPress_Media_Handler::build_opt_info($opt_res, $opts, $orig_size, $new_file));
            update_post_meta($id, '_r2g_opt_info', AstroPress_Media_Handler::build_opt_info($opt_res, $opts, $orig_size, $new_file));

            AstroPress_Media_Handler::sync_attachment_to_r2($id, true, $metadata);

            $rewriter = class_exists('AstroPress_URL_Rewriter') ? AstroPress_URL_Rewriter::instance() : null;
            $new_url = $rewriter ? $rewriter->rewrite_url(wp_get_attachment_url($id)) : wp_get_attachment_url($id);

            wp_send_json_success(array(
                'message' => esc_html__('Successfully converted to WebP and synced to Cloudflare R2!', 'astropress'),
                'url'     => $new_url,
            ));
        }

        wp_send_json_error(array('message' => 'Failed to convert image to WebP.'));
    }

    public function ajax_recompress_attachment() {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || !$this->check_permission($id, 'edit')) {
            wp_send_json_error(array('message' => esc_html__('Unauthorized or invalid attachment ID.', 'astropress')));
        }

        $engine = AstroPress_Optimizer::normalize_engine(sanitize_text_field($_POST['engine'] ?? ''));
        $format = sanitize_text_field($_POST['format'] ?? 'webp');
        $quality = (int) ($_POST['quality'] ?? get_option('astropress_compress_quality', get_option('r2g_compress_quality', 82)));
        $max_width = (int) get_option('astropress_max_width', get_option('r2g_max_width', 1920));

        $file_path = get_attached_file($id);
        if (!$file_path || !file_exists($file_path)) {
            AstroPress_Media_Handler::download_from_r2_to_local($id);
            $file_path = get_attached_file($id);
        }

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(array('message' => 'File not found on server or R2.'));
        }

        $orig_size = (int) filesize($file_path);
        $opts = array(
            'format'    => $format,
            'quality'   => $quality,
            'max_width' => $max_width,
            'compress'  => true,
            'engine'    => $engine,
        );
        $opt_res = AstroPress_Optimizer::optimize_local_file($file_path, $opts);

        if (!empty($opt_res['success']) && !empty($opt_res['file_path'])) {
            $new_file = $opt_res['file_path'];
            update_attached_file($id, $new_file);
            $mime = !empty($opt_res['mime']) ? $opt_res['mime'] : 'image/' . ($format === 'jpg' ? 'jpeg' : $format);
            wp_update_post(array(
                'ID'             => $id,
                'post_mime_type' => $mime,
            ));

            AstroPress_Media_Handler::mark_processed($new_file);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $metadata = wp_generate_attachment_metadata($id, $new_file);
            wp_update_attachment_metadata($id, $metadata);
            update_post_meta($id, '_astropress_optimized', 1);
            update_post_meta($id, '_r2g_optimized', 1);
            update_post_meta($id, '_astropress_opt_info', AstroPress_Media_Handler::build_opt_info($opt_res, $opts, $orig_size, $new_file));
            update_post_meta($id, '_r2g_opt_info', AstroPress_Media_Handler::build_opt_info($opt_res, $opts, $orig_size, $new_file));

            AstroPress_Media_Handler::sync_attachment_to_r2($id, true, $metadata);

            $rewriter = class_exists('AstroPress_URL_Rewriter') ? AstroPress_URL_Rewriter::instance() : null;
            $new_url = $rewriter ? $rewriter->rewrite_url(wp_get_attachment_url($id)) : wp_get_attachment_url($id);

            $msg = !empty($opt_res['engine_note'])
                ? $opt_res['engine_note']
                : sprintf(esc_html__('Successfully compressed (%s) and synced to Cloudflare R2!', 'astropress'), strtoupper($format));

            wp_send_json_success(array(
                'message'     => $msg,
                'url'         => $new_url,
                'format'      => strtoupper($format),
                'engine_used' => $opt_res['engine_used'] ?? '',
                'bytes_saved' => $opt_res['bytes_saved'] ?? 0,
            ));
        }

        wp_send_json_error(array('message' => !empty($opt_res['message']) ? $opt_res['message'] : 'Optimization failed.'));
    }
}
