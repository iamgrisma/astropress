<?php
/**
 * Admin View: Webhook Audit Logs Tab
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

        $logs = $this->core->cache->get_webhook_logs(50);
        ?>
        <div class="astropress-card" style="max-width:1050px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div>
                    <h2 style="margin:0;"><?php esc_html_e('Webhook Delivery & Invalidation Audit Log', 'astropress'); ?></h2>
                    <p style="margin:5px 0 0 0; color:#64748b;"><?php esc_html_e('Inspect the last 50 revalidation events sent to your Astro frontend and Cloudflare Edge.', 'astropress'); ?></p>
                </div>
                <?php if (!empty($logs)): ?>
                    <form method="post" action="" onsubmit="return confirm('Clear webhook delivery logs?');">
                        <?php wp_nonce_field('astropress_clear_logs_action', 'astropress_clear_logs_nonce'); ?>
                        <button type="submit" name="astropress_clear_logs" class="button button-secondary">
                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Logs', 'astropress'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($logs)): ?>
                <div class="notice notice-info inline" style="margin:20px 0;"><p><?php esc_html_e('No webhook events recorded yet. Save or publish a post/page to see live revalidation records here.', 'astropress'); ?></p></div>
            <?php else: ?>
                <div class="astropress-table-responsive">
                    <table class="widefat striped astropress-table" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th style="width:160px;"><?php esc_html_e('Timestamp', 'astropress'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Event', 'astropress'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Status', 'astropress'); ?></th>
                                <th><?php esc_html_e('Purged Paths', 'astropress'); ?></th>
                                <th style="width:180px;"><?php esc_html_e('Response Details', 'astropress'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $log_status = strtolower((string) ($log['status'] ?? ''));
                                $is_ok = array_key_exists('success', $log)
                                    ? (bool) $log['success']
                                    : in_array($log_status, array('success', 'sent', 'delivered'), true);
                                $status_class = $is_ok ? 'astropress-badge-online' : 'astropress-badge-offline';
                                $status_text  = $is_ok ? __('Succeeded', 'astropress') : __('Failed', 'astropress');
                                $timestamp    = $log['timestamp'] ?? $log['time'] ?? '';
                                $event         = $log['event'] ?? $log['trigger'] ?? 'post_updated';
                                $paths         = (array) ($log['paths'] ?? $log['urls'] ?? array());
                                $details       = $log['details'] ?? $log['message'] ?? '';
                                $http_code     = isset($log['http_code']) ? (int) $log['http_code'] : 0;
                                if ($http_code > 0 && strpos((string) $details, (string) $http_code) === false) {
                                    $details = trim((string) $details . ' (HTTP ' . $http_code . ')');
                                }
                                ?>
                                <tr>
                                    <td><small><code><?php echo esc_html($timestamp); ?></code></small></td>
                                    <td><span class="astropress-pill"><?php echo esc_html($event); ?></span></td>
                                    <td><span class="astropress-badge <?php echo esc_attr($status_class); ?>"><span class="astropress-dot"></span> <?php echo esc_html($status_text); ?></span></td>
                                    <td>
                                        <?php
                                        if (empty($paths)) {
                                            echo '<em>' . esc_html($log['target'] ?? __('No URL details recorded', 'astropress')) . '</em>';
                                        } else {
                                            echo '<code>' . esc_html(implode(', ', array_slice($paths, 0, 5))) . (count($paths) > 5 ? ' ... (+' . (count($paths) - 5) . ' more)' : '') . '</code>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <small><?php echo esc_html($details ?: ($is_ok ? 'Request succeeded' : 'No error details recorded')); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
