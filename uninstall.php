<?php
/**
 * Uninstall AstroPress
 *
 * Cleans up options, transients, temporary working directories, and optionally the
 * custom database table and postmeta when the plugin is deleted via WordPress Admin.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Execute cleanup tasks for a single site
 */
function astropress_uninstall_site() {
    global $wpdb;

    $delete_data = (int) get_option('astropress_delete_data_on_uninstall', get_option('r2g_delete_data_on_uninstall', 0));

    // 1. Delete all plugin options
    $options = array(
        'astropress_frontend_url',
        'astropress_secret',
        'astropress_connected',
        'astropress_rewrite_links',
        'astropress_auto_update',
        'astropress_purge_on_save',
        'astropress_purge_feeds',
        'astropress_purge_taxonomy',
        'astropress_purge_menu',
        'astropress_lscwp_sync',
        'astropress_warm_delay',
        'astropress_warm_batch_size',
        'astropress_warm_bg_job',
        'astropress_subdomain_shield',
        'astropress_subdomain_noindex',
        'astropress_setup_completed',
        'astropress_media_mode',
        'astropress_cf_api_token',
        'astropress_cf_zone_id',
        'astropress_cf_account_id',
        'astropress_cf_deploy_hook',
        'astropress_cf_purge_mode',
        'astropress_cf_cache_tags_enabled',
        'astropress_cf_ruleset_id',
        'astropress_cf_rule_ids',
        'astropress_webhook_logs',
        'astropress_failed_webhooks',
        'astropress_r2_account_id',
        'astropress_r2_access_key',
        'astropress_r2_secret_key',
        'astropress_r2_bucket',
        'astropress_r2_custom_domain',
        'astropress_retention_days',
        'astropress_last_prune_log',
        'astropress_path_structure',
        'astropress_path_prefix',
        'astropress_upload_sizes',
        'astropress_cleanup_scope',
        'astropress_auto_upload',
        'astropress_rewrite_urls',
        'astropress_delete_from_r2',
        'astropress_storage_mode',
        'astropress_compress_engine',
        'astropress_compress_enabled',
        'astropress_compress_format',
        'astropress_compress_quality',
        'astropress_max_width',
        'astropress_max_height',
        'astropress_strip_exif',
        'astropress_auto_orient',
        'astropress_backup_original',
        'astropress_smart_routing',
        'astropress_interceptor_enabled',
        'astropress_active_preset',
        'astropress_upload_workflow',
        'astropress_db_version',
        'astropress_404_logs',
        'astropress_last_known_latest_release',
        'astropress_last_known_releases',
        'astropress_v109_migrated',
        'astropress_v122_migrated',
        'astropress_postmeta_migrated',
        'astropress_redirects_synced_v1',
        'astropress_delete_data_on_uninstall',
        // Legacy options
        'topnepali_headless_frontend_url',
        'topnepali_headless_secret',
        'topnepali_headless_connected',
        'topnepali_headless_rewrite_links',
        'topnepali_headless_auto_update',
        'topnepali_headless_purge_on_save',
        'topnepali_headless_purge_feeds',
        'topnepali_headless_purge_taxonomy',
        'topnepali_headless_purge_menu',
        'topnepali_headless_lscwp_sync',
        'topnepali_headless_warm_delay',
        'topnepali_headless_warm_batch_size',
        'topnepali_headless_subdomain_shield',
        'topnepali_headless_subdomain_noindex',
        'r2g_account_id',
        'r2g_access_key',
        'r2g_secret_key',
        'r2g_bucket',
        'r2g_custom_domain',
        'r2g_path_structure',
        'r2g_path_prefix',
        'r2g_upload_sizes',
        'r2g_cleanup_scope',
        'r2g_auto_upload',
        'r2g_rewrite_urls',
        'r2g_delete_from_r2',
        'r2g_storage_mode',
        'r2g_compress_engine',
        'r2g_compress_enabled',
        'r2g_compress_format',
        'r2g_compress_quality',
        'r2g_max_width',
        'r2g_strip_exif',
        'r2g_interceptor_enabled',
        'r2g_active_preset',
        'r2g_upload_workflow',
        'r2g_db_version',
        'r2g_v109_migrated',
        'r2g_v122_migrated',
        'r2g_v200_migrated',
        'r2g_postmeta_migrated',
        'r2g_last_known_releases',
        'r2g_last_known_latest_release',
        'r2g_delete_data_on_uninstall',
    );

    foreach ($options as $opt) {
        delete_option($opt);
    }

    // 2. Delete transients
    delete_transient('astropress_github_latest_release');
    delete_transient('astropress_github_all_releases');
    delete_transient('r2g_github_latest_release');
    delete_transient('r2g_github_all_releases');
    delete_transient('astropress_resmush_last_call');
    delete_transient('astropress_resmush_backoff');
    delete_transient('r2g_resmush_last_call');
    delete_transient('r2g_resmush_backoff');
    delete_site_transient('update_plugins');

    // 3. Clear scheduled crons
    wp_clear_scheduled_hook('astropress_warmer_cron');
    wp_clear_scheduled_hook('astropress_warm_bg_worker');
    wp_clear_scheduled_hook('astropress_retry_webhooks');
    wp_clear_scheduled_hook('astropress_daily_media_retention');
    wp_clear_scheduled_hook('topnepali_warm_bg_worker');
    wp_clear_scheduled_hook('r2g_warmer_cron');

    // 4. Optional deep cleanup: drop custom tables and remove postmeta if opted in
    if ($delete_data === 1) {
        $tables = array(
            $wpdb->prefix . 'astropress_media',
            $wpdb->prefix . 'r2g_media',
        );
        foreach ($tables as $tbl) {
            $wpdb->query("DROP TABLE IF EXISTS `{$tbl}`");
        }

        $meta_keys = array(
            '_astropress_synced',
            '_astropress_synced_at',
            '_astropress_key',
            '_astropress_keys',
            '_astropress_local_deleted',
            '_astropress_optimized',
            '_astropress_opt_info',
            '_astropress_sizes_synced',
            '_astropress_rm_head',
            '_headless_rm_head',
            '_astropress_yoast_head',
            '_astropress_seopress_head',
            '_astropress_aioseo_head',
            '_r2g_synced',
            '_r2g_synced_at',
            '_r2g_key',
            '_r2g_keys',
            '_r2g_local_deleted',
            '_r2g_optimized',
            '_r2g_opt_info',
        );

        foreach ($meta_keys as $mk) {
            $wpdb->delete($wpdb->postmeta, array('meta_key' => $mk), array('%s'));
        }
    }

    // 5. Clean up temporary working directories in uploads
    $upload_dir = wp_upload_dir();
    $temp_dirs = array(
        trailingslashit($upload_dir['basedir']) . 'astropress-temp',
        trailingslashit($upload_dir['basedir']) . 'r2g-temp',
    );

    foreach ($temp_dirs as $td) {
        if (is_dir($td)) {
            $files = glob($td . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir($td);
        }
    }
}

// Support multisite network uninstall
if (is_multisite()) {
    $sites = get_sites(array('fields' => 'ids'));
    foreach ($sites as $site_id) {
        switch_to_blog($site_id);
        astropress_uninstall_site();
        restore_current_blog();
    }
} else {
    astropress_uninstall_site();
}
