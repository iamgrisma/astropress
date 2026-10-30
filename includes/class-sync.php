<?php
/**
 * AstroPress Bulk Sync Engine
 *
 * Handles background batch sync of existing unsynced media to Cloudflare R2,
 * provides AJAX endpoints for progressive sync from the dashboard.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Sync {
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
        $actions = array(
            'bulk_sync_batch',
            'bulk_clean_verified_local',
            'get_stats',
            'reindex_media',
            'import_legacy',
            'audit_media_health',
        );

        foreach ($actions as $action) {
            add_action("wp_ajax_astropress_{$action}", array($this, "ajax_{$action}"));
            add_action("wp_ajax_r2g_{$action}", array($this, "ajax_{$action}"));
        }
    }

    private function verify_request() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? $_REQUEST['nonce'] ?? ''));
        if (!empty($nonce) && (
            wp_verify_nonce($nonce, 'astropress_admin_nonce') ||
            wp_verify_nonce($nonce, 'astropress_onboarding_nonce') ||
            wp_verify_nonce($nonce, 'r2g_admin_nonce')
        )) {
            return current_user_can('manage_options');
        }
        return false;
    }

    public function ajax_bulk_sync_batch() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $client = astropress()->get_r2_client();
        if (!$client || !$client->is_configured()) {
            wp_send_json_error(array(
                'message' => 'Cloudflare R2 is not configured. Please enter your Account ID, Access Key, Secret Key, and Bucket first.',
            ));
        }

        $batch_size = (int) ($_POST['batch_size'] ?? 5);
        $batch_size = max(1, min(20, $batch_size));

        $format  = sanitize_text_field($_POST['format'] ?? '');
        $preset  = sanitize_text_field($_POST['preset'] ?? '');
        $quality = isset($_POST['quality']) && (int)$_POST['quality'] > 0 ? (int)$_POST['quality'] : AstroPress_Optimizer::get_quality();
        $engine  = AstroPress_Optimizer::normalize_engine(sanitize_key($_POST['engine'] ?? ''));

        if ($engine === 'resmush') {
            $batch_size = min($batch_size, 2);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $batch_options = array();
        if ($format && $format !== 'keep' && $format !== 'keep_current' && $format !== 'none') {
            $batch_options = array(
                'format'    => $format,
                'quality'   => $quality > 0 ? $quality : 82,
                'max_width' => 1920,
                'compress'  => 1,
                'engine'    => $engine,
            );
        } elseif ($format === 'none') {
            $batch_options = array(
                'format'    => 'original',
                'compress'  => 0,
                'engine'    => 'none',
            );
        } elseif (!empty($preset) && $preset !== 'keep_current' && $preset !== 'none') {
            $presets = AstroPress_Media_Handler::get_presets();
            if (isset($presets[$preset])) {
                $batch_options = $presets[$preset];
            } else {
                $batch_options = array(
                    'format'    => $format ?: 'webp',
                    'quality'   => $quality ?: 82,
                    'max_width' => 1920,
                    'compress'  => 1,
                );
            }
            if ($quality > 0) {
                $batch_options['quality'] = $quality;
            }
            $batch_options['engine'] = $engine;
        }

        $exclude_ids = array();
        if (!empty($_POST['exclude_ids']) && is_array($_POST['exclude_ids'])) {
            $exclude_ids = array_map('intval', $_POST['exclude_ids']);
        }

        $unsynced_ids = AstroPress_Database::get_unsynced_ids($batch_size, $exclude_ids);

        if (empty($unsynced_ids)) {
            wp_send_json_success(array(
                'synced_count' => 0,
                'remaining'    => 0,
                'done'         => true,
                'stats'        => AstroPress_Database::get_stats(),
            ));
        }

        $synced = 0;
        $failed = 0;
        $results = array();

        foreach ($unsynced_ids as $id) {
            $ok = AstroPress_Media_Handler::sync_attachment_to_r2((int) $id, true, null, true, $batch_options);
            if ($ok) {
                $synced++;
                $results[] = array('id' => $id, 'status' => 'synced');
            } else {
                $failed++;
                AstroPress_Database::mark_failed((int) $id);
                $results[] = array('id' => $id, 'status' => 'failed');
            }
        }

        $remaining_count = AstroPress_Database::count_unsynced();

        wp_send_json_success(array(
            'synced_count'    => $synced,
            'failed_count'    => $failed,
            'remaining_count' => $remaining_count,
            'remaining'       => $remaining_count > 0,
            'done'            => $remaining_count === 0,
            'results'         => $results,
            'stats'           => AstroPress_Database::get_stats(),
        ));
    }

    public function ajax_bulk_clean_verified_local() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $batch_size = (int) ($_POST['batch_size'] ?? 15);
        $batch_size = max(1, min(50, $batch_size));

        $ids = AstroPress_Database::get_verified_synced_with_local_ids($batch_size);

        if (empty($ids)) {
            wp_send_json_success(array(
                'cleaned_count'       => 0,
                'remaining'           => 0,
                'done'                => true,
                'stats'               => AstroPress_Database::get_stats(),
                'verified_with_local' => 0,
            ));
        }

        $cleaned = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $attachment_id = (int) $id;
            $record = AstroPress_Database::get($attachment_id);
            if ($record && $record->status === 'synced' && !empty($record->r2_key)) {
                if (AstroPress_Media_Handler::delete_local_files($attachment_id)) {
                    $cleaned++;
                } else {
                    $failed++;
                }
            }
        }

        $remaining_count = AstroPress_Database::count_verified_synced_with_local();

        wp_send_json_success(array(
            'cleaned_count'       => $cleaned,
            'failed_count'        => $failed,
            'remaining'           => $remaining_count,
            'done'                => ($remaining_count === 0),
            'stats'               => AstroPress_Database::get_stats(),
            'verified_with_local' => $remaining_count,
        ));
    }

    public function ajax_get_stats() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        wp_send_json_success(array(
            'stats'    => AstroPress_Database::get_stats(),
            'activity' => AstroPress_Database::get_recent_activity(10),
        ));
    }

    public function ajax_reindex_media() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $res = AstroPress_Database::import_existing_offloaded();

        wp_send_json_success(array(
            'migrated' => $res['imported'],
            'stats'    => AstroPress_Database::get_stats(),
            'message'  => sprintf(
                esc_html__('Scanned library: Indexed %d media attachments (%d from Media Cloud Sync, %d offloaded).', 'astropress'),
                $res['imported'],
                $res['found_wpmcs'],
                $res['found_offloaded']
            ),
        ));
    }

    public function ajax_import_legacy() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $res = AstroPress_Database::import_existing_offloaded();

        wp_send_json_success(array(
            'imported' => $res['imported'],
            'stats'    => AstroPress_Database::get_stats(),
            'message'  => sprintf(
                esc_html__('Successfully imported %d media items (%d from previous sync table, %d offloaded). Previews and CDN links active!', 'astropress'),
                $res['imported'],
                $res['found_wpmcs'],
                $res['found_offloaded']
            ),
        ));
    }

    /**
     * AJAX: Live Media Health & R2 Availability Audit
     * Performs fast HEAD requests against CDN to verify file availability in R2
     * and audits presence of local server files.
     */
    public function ajax_audit_media_health() {
        if (!$this->verify_request()) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $offset = (int) ($_POST['offset'] ?? 0);
        $batch_size = (int) ($_POST['batch_size'] ?? 10);
        $batch_size = max(1, min(25, $batch_size));

        global $wpdb;
        $table = AstroPress_Database::table();
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT id, attachment_id, r2_key, status, has_local FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ));

        if (empty($records)) {
            wp_send_json_success(array(
                'done'        => true,
                'audited'     => 0,
                'stats'       => AstroPress_Database::get_stats(),
                'next_offset' => $offset,
            ));
        }

        $cdn_base = untrailingslashit(get_option('r2g_custom_domain', '') ?: get_option('r2g_public_url', ''));
        $audited = 0;
        $missing_local = 0;
        $missing_r2 = 0;
        $verified_ok = 0;

        foreach ($records as $row) {
            $audited++;
            $attachment_id = (int) $row->attachment_id;
            $file_path = get_attached_file($attachment_id);
            $local_exists = (!empty($file_path) && file_exists($file_path)) ? 1 : 0;

            if (!empty($row->r2_key) && !empty($cdn_base)) {
                $check_url = $cdn_base . '/' . ltrim($row->r2_key, '/');
                if (AstroPress_Security::is_safe_external_url($check_url)) {
                    $head_res = wp_remote_head($check_url, array(
                        'timeout'            => 4,
                        'sslverify'          => (bool) apply_filters('astropress_sslverify', true),
                        'reject_unsafe_urls' => true,
                    ));
                    $code = wp_remote_retrieve_response_code($head_res);
                    if ($code === 200 || $code === 304) {
                        $verified_ok++;
                    } elseif ($code === 404) {
                        $missing_r2++;
                        $wpdb->update($table, array('status' => 'failed'), array('id' => $row->id));
                    }
                }
            }

            if ((int) $row->has_local !== $local_exists) {
                $wpdb->update($table, array('has_local' => $local_exists), array('id' => $row->id));
            }
            if (!$local_exists) {
                $missing_local++;
            }
        }

        $total_in_table = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $next_offset = $offset + count($records);
        $done = ($next_offset >= $total_in_table);

        wp_send_json_success(array(
            'done'          => $done,
            'audited'       => $audited,
            'next_offset'   => $next_offset,
            'total'         => $total_in_table,
            'missing_local' => $missing_local,
            'missing_r2'    => $missing_r2,
            'verified_ok'   => $verified_ok,
            'stats'         => AstroPress_Database::get_stats(),
        ));
    }
}
