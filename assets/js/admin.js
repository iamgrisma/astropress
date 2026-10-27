/**
 * AstroPress Admin Scripts
 *
 * Handles live Headless handshake testing, interactive Edge cache warming,
 * Cloudflare R2 bucket discovery, live connection testing, bulk media sync,
 * code snippet clipboard copying, and compression preview testing.
 *
 * @package AstroPress
 * @author  Grisma <https://grisma.com.np>
 */

(function($) {
  'use strict';

  $(document).ready(function() {
    const config = window.astropressConfig || {};
    const ajaxurl = config.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
    const nonce = config.nonce || '';

    // Helper: format bytes
    function formatBytes(bytes) {
      if (!bytes || bytes <= 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // ========================================================
    // 1. Revalidation Secret Toggle & Generator
    // ========================================================
    $('#astropress-toggle-secret').on('click', function(e) {
      e.preventDefault();
      const $input = $('#astropress_secret');
      if ($input.attr('type') === 'password') {
        $input.attr('type', 'text');
        $(this).text('Hide');
      } else {
        $input.attr('type', 'password');
        $(this).text('Show');
      }
    });

    function generateRandomSecret() {
      const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
      let secret = 'ap_sec_';
      for (let i = 0; i < 32; i++) {
        secret += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      return secret;
    }

    $('#astropress-generate-secret').on('click', function(e) {
      e.preventDefault();
      const secret = generateRandomSecret();
      $('#astropress_secret').attr('type', 'text').val(secret);
      $('#astropress-toggle-secret').text('Hide');
    });

    $('#onboard-gen-secret').on('click', function(e) {
      e.preventDefault();
      $('#onboard_secret').val(generateRandomSecret());
    });

    // ========================================================
    // 2. Headless Handshake Test
    // ========================================================
    $('#astropress-test-handshake').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-handshake-status');
      $btn.prop('disabled', true).text('Testing...');
      $status.html('<span style="color:#64748b;">Pinging frontend /api/revalidate...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_test_key',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#057a55;font-weight:600;">✓ ' + res.data.message + ' (' + res.data.latency_ms + 'ms)</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data.message || 'Handshake failed') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Test Webhook Handshake');
        }
      });
    });

    // ========================================================
    // 3. Cloudflare R2 Discovery & Connection Testing
    // ========================================================
    function handleFetchBuckets($btn, $status, accountId, accessKey, secretKey, $targetInput) {
      if (!accountId || !accessKey) {
        $status.html('<span style="color:#e02424;">Please enter Account ID and Access Key ID first.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Discovering...');
      $status.html('<span style="color:#64748b;">Connecting to Cloudflare R2 S3 API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_fetch_buckets',
          account_id: accountId,
          access_key: accessKey,
          secret_key: secretKey,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data?.buckets && res.data.buckets.length > 0) {
            const buckets = res.data.buckets;
            $targetInput.val(buckets[0]);
            $status.html('<span style="color:#057a55;font-weight:600;">✓ Discovered ' + buckets.length + ' bucket(s): ' + buckets.join(', ') + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data?.message || 'Could not discover buckets.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Discovery failed: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Discover Buckets');
        }
      });
    }

    $('#astropress-fetch-buckets').on('click', function(e) {
      e.preventDefault();
      let secretKey = $('#r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) secretKey = '';
      handleFetchBuckets(
        $(this),
        $('#astropress-r2-status'),
        $('#r2_account_id').val()?.trim(),
        $('#r2_access_key').val()?.trim(),
        secretKey,
        $('#r2_bucket')
      );
    });

    $('#onboard-fetch-buckets').on('click', function(e) {
      e.preventDefault();
      let secretKey = $('#onboard_r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) secretKey = '';
      handleFetchBuckets(
        $(this),
        $('#onboard-r2-status'),
        $('#onboard_r2_account_id').val()?.trim(),
        $('#onboard_r2_access_key').val()?.trim(),
        secretKey,
        $('#onboard_r2_bucket')
      );
    });

    $('#astropress-test-r2').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-r2-status');
      const accountId = $('#r2_account_id').val()?.trim();
      const accessKey = $('#r2_access_key').val()?.trim();
      let secretKey = $('#r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) {
        secretKey = '';
      }
      const bucket = $('#r2_bucket').val()?.trim();
      const customDomain = $('#r2_custom_domain').val()?.trim();

      if (!accountId || !accessKey || !bucket) {
        $status.html('<span style="color:#e02424;">Please enter Account ID, Access Key, and Bucket name.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Testing...');
      $status.html('<span style="color:#64748b;">Testing read/write permissions...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_test_connection',
          account_id: accountId,
          access_key: accessKey,
          secret_key: secretKey,
          bucket: bucket,
          custom_domain: customDomain,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#057a55;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data.message || 'Connection test failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Connection error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Test Connection');
        }
      });
    });

    // ========================================================
    // 4. Live Compression Preview Tester
    // ========================================================
    $('#astropress-run-preview-test').on('click', function(e) {
      e.preventDefault();
      const fileInput = document.getElementById('astropress-test-image-input');
      const $btn = $(this);
      const $result = $('#astropress-preview-result');

      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please choose an image file to test first.');
        return;
      }

      const file = fileInput.files[0];
      const formData = new FormData();
      formData.append('action', 'astropress_preview_compression');
      formData.append('nonce', nonce);
      formData.append('image', file);
      formData.append('format', $('#compress_format').val() || 'webp');
      formData.append('quality', $('#compress_quality').val() || 82);
      formData.append('max_width', $('#max_width').val() || 1920);
      formData.append('engine', $('#compress_engine').val() || 'resmush');

      $btn.prop('disabled', true).text('Compressing...');
      $result.show().html('<p style="color:#64748b;">Processing image with selected engine and format...</p>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res) {
          if (res.success && res.data) {
            const d = res.data;
            const html = `
              <div class="astropress-preview-result-card">
                <img src="${d.data_uri}" alt="Compressed Preview" class="astropress-preview-img" />
                <div>
                  <h4 style="margin:0 0 6px 0; color:#0f172a; font-size:15px;">✓ Compression Successful!</h4>
                  <table style="font-size:12px; line-height:1.6;">
                    <tr><td><strong>Original Size:</strong></td><td>${formatBytes(d.original_size)}</td></tr>
                    <tr><td><strong>Compressed Size:</strong></td><td>${formatBytes(d.compressed_size)}</td></tr>
                    <tr><td><strong>Reduction:</strong></td><td><span class="astropress-text-success" style="font-size:14px; font-weight:700;">-${d.percent}%</span> (${formatBytes(d.saved_bytes)} saved)</td></tr>
                    <tr><td><strong>Engine:</strong></td><td><code>${d.engine_used || 'reSmush'}</code></td></tr>
                  </table>
                </div>
              </div>
            `;
            $result.html(html);
          } else {
            $result.html('<p style="color:#e02424;">✗ ' + (res.data?.message || 'Compression failed.') + '</p>');
          }
        },
        error: function(xhr, status, err) {
          $result.html('<p style="color:#e02424;">✗ Request error: ' + err + '</p>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Test Image Compression');
        }
      });
    });

    // ========================================================
    // 5. Code Snippet Clipboard Copy
    // ========================================================
    $(document).on('click', '.astropress-copy-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const targetId = $btn.data('target');
      const targetEl = document.getElementById(targetId);

      if (targetEl) {
        const text = targetEl.textContent || targetEl.innerText;
        navigator.clipboard.writeText(text).then(function() {
          const orig = $btn.text();
          $btn.text('Copied!');
          setTimeout(function() {
            $btn.text(orig);
          }, 2000);
        }).catch(function() {
          alert('Could not copy to clipboard.');
        });
      }
    });

    // ========================================================
    // 6. Interactive Edge Cache Warmer
    // ========================================================
    let warmerUrls = [];
    let warmerIndex = 0;
    let warmerActive = false;
    let warmerPaused = false;

    $('#astropress-start-warmer').on('click', function(e) {
      e.preventDefault();
      const preset = $('#astropress-warmer-preset').val() || 'standard';
      const $btn = $(this);

      $btn.prop('disabled', true).text('Fetching URLs...');
      $('#astropress-warmer-progress-container').show();
      $('#astropress-warmer-log').empty();

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_get_warmup_urls',
          preset: preset,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data?.items && res.data.items.length > 0) {
            warmerUrls = res.data.items;
            warmerIndex = 0;
            warmerActive = true;
            warmerPaused = false;

            $btn.hide();
            $('#astropress-pause-warmer').show();
            $('#astropress-cancel-warmer').show();

            processWarmerBatch();
          } else {
            alert('No URLs found for selected preset.');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Start Interactive Warmer');
          }
        },
        error: function(xhr, status, err) {
          alert('Failed to get warmup URLs: ' + err);
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Start Interactive Warmer');
        }
      });
    });

    function processWarmerBatch() {
      if (!warmerActive || warmerPaused || warmerIndex >= warmerUrls.length) {
        if (warmerIndex >= warmerUrls.length && warmerUrls.length > 0) {
          $('#astropress-warmer-log').append('✓ Cache warming finished successfully!\n');
          finishWarmer();
        }
        return;
      }

      const batchSize = config.defaultBatch || 5;
      const batch = warmerUrls.slice(warmerIndex, warmerIndex + batchSize);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_warm_batch',
          batch: batch,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data?.results) {
            res.data.results.forEach(function(r) {
              const symbol = r.ok ? '✓' : '✗';
              $('#astropress-warmer-log').append(symbol + ' [' + (r.status || 'ERR') + '] ' + r.url + ' (' + (r.duration_ms || 0) + 'ms)\n');
            });
            const logBox = document.getElementById('astropress-warmer-log');
            if (logBox) logBox.scrollTop = logBox.scrollHeight;
          }

          warmerIndex += batch.length;
          const pct = Math.min(100, Math.round((warmerIndex / warmerUrls.length) * 100));
          $('#astropress-warmer-progress-bar').css('width', pct + '%');
          $('#astropress-warmer-progress-text').text(warmerIndex + ' / ' + warmerUrls.length + ' URLs (' + pct + '%)');

          const delay = config.defaultDelay || 1000;
          setTimeout(processWarmerBatch, delay);
        },
        error: function(xhr, status, err) {
          $('#astropress-warmer-log').append('✗ Batch request error: ' + err + '\n');
          warmerIndex += batch.length;
          setTimeout(processWarmerBatch, 1500);
        }
      });
    }

    function finishWarmer() {
      warmerActive = false;
      $('#astropress-pause-warmer').hide();
      $('#astropress-cancel-warmer').hide();
      $('#astropress-start-warmer').prop('disabled', false).show().html('<span class="dashicons dashicons-controls-play"></span> Start Interactive Warmer');
    }

    $('#astropress-pause-warmer').on('click', function(e) {
      e.preventDefault();
      warmerPaused = !warmerPaused;
      if (warmerPaused) {
        $(this).html('<span class="dashicons dashicons-controls-play"></span> Resume');
      } else {
        $(this).html('<span class="dashicons dashicons-controls-pause"></span> Pause');
        processWarmerBatch();
      }
    });

    $('#astropress-cancel-warmer').on('click', function(e) {
      e.preventDefault();
      warmerActive = false;
      finishWarmer();
      $('#astropress-warmer-log').append('— Warmer cancelled by user.\n');
    });

    // ========================================================
    // 7. Bulk Media Sync to Cloudflare R2
    // ========================================================
    let syncActive = false;
    let syncOffset = 0;

    $('#astropress-start-sync').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      syncActive = true;
      syncOffset = 0;

      $btn.hide();
      $('#astropress-cancel-sync').show();
      $('#astropress-sync-log').show().empty().append('Starting media sync to Cloudflare R2...\n');

      processSyncBatch();
    });

    function processSyncBatch() {
      if (!syncActive) return;

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_sync_batch',
          offset: syncOffset,
          batch_size: 10,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data) {
            const total = res.data.total || 0;
            const synced = res.data.synced || 0;
            const processed = res.data.processed || 0;

            if (res.data.logs && res.data.logs.length > 0) {
              res.data.logs.forEach(function(l) {
                $('#astropress-sync-log').append(l + '\n');
              });
              const syncLog = document.getElementById('astropress-sync-log');
              if (syncLog) syncLog.scrollTop = syncLog.scrollHeight;
            }

            const pct = (total > 0) ? Math.min(100, Math.round((synced / total) * 100)) : 100;
            $('#astropress-sync-progress-bar').css('width', pct + '%');
            $('#astropress-sync-counter').text(synced + ' / ' + total + ' synced (' + pct + '%)');

            if (res.data.complete || processed === 0) {
              $('#astropress-sync-log').append('✓ Bulk media sync complete!\n');
              finishSync();
            } else {
              syncOffset += processed;
              setTimeout(processSyncBatch, 300);
            }
          } else {
            $('#astropress-sync-log').append('✗ Sync error: ' + (res.data?.message || 'Unknown') + '\n');
            finishSync();
          }
        },
        error: function(xhr, status, err) {
          $('#astropress-sync-log').append('✗ Network error during sync: ' + err + '\n');
          finishSync();
        }
      });
    }

    function finishSync() {
      syncActive = false;
      $('#astropress-cancel-sync').hide();
      $('#astropress-start-sync').show().html('<span class="dashicons dashicons-upload"></span> Sync All to Cloudflare R2');
    }

    $('#astropress-cancel-sync').on('click', function(e) {
      e.preventDefault();
      syncActive = false;
      finishSync();
      $('#astropress-sync-log').append('— Sync stopped by user.\n');
    });

    // ========================================================
    // 8. Media Library Single Attachment AJAX Buttons
    // ========================================================
    $(document).on('click', '.astropress-media-action-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const action = $btn.data('action');
      const attachmentId = $btn.data('id');
      const originalText = $btn.text();

      $btn.prop('disabled', true).text('Working...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_media_action',
          media_action: action,
          attachment_id: attachmentId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            location.reload();
          } else {
            alert(res.data?.message || 'Media action failed.');
            $btn.prop('disabled', false).text(originalText);
          }
        },
        error: function(xhr, status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false).text(originalText);
        }
      });
    });

    // ========================================================
    // 9. Cloudflare API Token & Direct Purge Handlers
    // ========================================================
    $('#astropress-toggle-cf-token').on('click', function(e) {
      e.preventDefault();
      const $input = $('#astropress_cf_api_token');
      if ($input.attr('type') === 'password') {
        $input.attr('type', 'text');
        $(this).text('Hide');
      } else {
        $input.attr('type', 'password');
        $(this).text('Show');
      }
    });

    $('#astropress-test-cf-token').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const token = $('#astropress_cf_api_token').val();
      const $status = $('#astropress-cf-token-status');

      $btn.prop('disabled', true).text('Verifying...');
      $status.html('<span style="color:#64748b;">Testing token with Cloudflare API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_cf_quick_connect',
          cf_api_token: token,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            let msg = '✓ Token verified! ';
            if (res.data.matched_account) {
              msg += 'Account: ' + res.data.matched_account.name + '. ';
            }
            if (res.data.matched_zone) {
              msg += 'Zone: ' + res.data.matched_zone.name + ' (' + res.data.matched_zone.id + ').';
              if (!$('#astropress_cf_zone_id').val()) {
                $('#astropress_cf_zone_id').val(res.data.matched_zone.id);
              }
            }
            $status.html('<span style="color:#057a55;font-weight:600;">' + msg + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data?.message || 'Verification failed') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Test & Discover');
        }
      });
    });

    $('#astropress-purge-cf-now').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-cf-purge-status');
      if (!confirm('Purge entire Cloudflare Edge Zone Cache now?')) return;

      $btn.prop('disabled', true).text('Purging...');
      $status.html('<span style="color:#64748b;">Purging zone cache via Cloudflare API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_purge_cf_zone',
          purge_everything: 1,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#057a55;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data?.message || 'Purge failed') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Network error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Purge Edge Now');
        }
      });
    });

    $('#astropress-trigger-deploy-now').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-deploy-status');
      if (!confirm('Trigger Cloudflare Pages deploy hook now?')) return;

      $btn.prop('disabled', true).text('Triggering...');
      $status.html('<span style="color:#64748b;">Sending POST to deploy hook...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_trigger_deploy_hook',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#057a55;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;">✗ ' + (res.data?.message || 'Trigger failed') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;">✗ Network error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Trigger Deploy');
        }
      });
    });

    // ========================================================
    // 10. Webhook Delivery Audit Log Controls
    // ========================================================
    $('#astropress-refresh-logs').on('click', function(e) {
      e.preventDefault();
      location.reload();
    });

    $('#astropress-clear-logs').on('click', function(e) {
      e.preventDefault();
      if (!confirm('Are you sure you want to clear all webhook delivery audit logs?')) return;
      const $btn = $(this);
      $btn.prop('disabled', true);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_clear_webhook_logs',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            location.reload();
          } else {
            alert(res.data?.message || 'Failed to clear logs.');
            $btn.prop('disabled', false);
          }
        },
        error: function(xhr, status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false);
        }
      });
    });

    $(document).on('click', '.astropress-resend-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const logId = $btn.data('id');
      const originalHtml = $btn.html();

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_resend_webhook',
          log_id: logId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            alert('Webhook re-sent successfully!');
            location.reload();
          } else {
            alert(res.data?.message || 'Failed to resend webhook.');
            $btn.prop('disabled', false).html(originalHtml);
          }
        },
        error: function(xhr, status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false).html(originalHtml);
        }
      });
    });

    // ========================================================
    // 11. Cloudflare 1-Click Quick Connect Assistant
    // ========================================================
    function handleQuickConnect(tokenInputSelector, resultsContainerSelector, btnSelector) {
      $(btnSelector).on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const token = $(tokenInputSelector).val().trim();
        const $results = $(resultsContainerSelector);

        if (!token) {
          alert('Please paste your Cloudflare API Token first.');
          return;
        }

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Discovering...');
        $results.show().html('<div class="astropress-quick-results-box"><span style="color:#64748b;">Querying Cloudflare accounts, zones, and R2 buckets...</span></div>');

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_cf_quick_connect',
            cf_api_token: token,
            nonce: nonce,
          },
          success: function(res) {
            if (res.success) {
              const data = res.data;
              let html = '<div class="astropress-quick-results-box">';
              html += '<h4 style="margin:0 0 10px 0; color:#057a55;">✓ Cloudflare Resources Auto-Discovered:</h4>';
              html += '<div class="astropress-cf-resource-grid">';

              if (data.matched_account) {
                html += '<div class="astropress-cf-resource-card"><h4>Account</h4><p>' + data.matched_account.name + ' <br><small style="color:#64748b;font-weight:normal;">' + data.matched_account.id + '</small></p></div>';
              }
              if (data.matched_zone) {
                html += '<div class="astropress-cf-resource-card"><h4>Zone (Domain)</h4><p>' + data.matched_zone.name + ' <br><small style="color:#64748b;font-weight:normal;">' + data.matched_zone.id + '</small></p></div>';
              }
              if (data.buckets && data.buckets.length > 0) {
                html += '<div class="astropress-cf-resource-card"><h4>R2 Buckets (' + data.buckets.length + ')</h4><p>' + data.buckets.join(', ') + '</p></div>';
              }
              if (data.pages_projects && data.pages_projects.length > 0) {
                const projectNames = data.pages_projects.map(function(p) { return p.name; }).join(', ');
                html += '<div class="astropress-cf-resource-card"><h4>Pages Projects</h4><p>' + projectNames + '</p></div>';
              }

              html += '</div>';
              html += '<button type="button" class="button button-primary astropress-apply-cf-btn" data-token="' + encodeURIComponent(token) + '">Apply Discovered Settings to WordPress</button>';
              html += '</div>';

              $results.html(html);

              if (data.matched_account && $('#r2_account_id').length) {
                $('#r2_account_id').val(data.matched_account.id);
              }
              if (data.matched_zone && $('#astropress_cf_zone_id').length) {
                $('#astropress_cf_zone_id').val(data.matched_zone.id);
              }
              if (data.buckets && data.buckets.length > 0 && $('#r2_bucket').length && !$('#r2_bucket').val()) {
                $('#r2_bucket').val(data.buckets[0]);
              }
            } else {
              $results.html('<div class="astropress-quick-results-box" style="border-color:#fca5a5;background:#fff5f5;"><span style="color:#e02424;font-weight:600;">✗ ' + (res.data?.message || 'Discovery failed') + '</span></div>');
            }
          },
          error: function(xhr, status, err) {
            $results.html('<div class="astropress-quick-results-box" style="border-color:#fca5a5;background:#fff5f5;"><span style="color:#e02424;font-weight:600;">✗ Request error: ' + err + '</span></div>');
          },
          complete: function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Auto-Discover Resources');
          }
        });
      });
    }

    handleQuickConnect('#astropress_quick_cf_token', '#astropress-quick-connect-results', '#astropress-quick-connect-btn');
    handleQuickConnect('#astropress_quick_cf_token_r2', '#astropress-quick-connect-results-r2', '#astropress-quick-connect-btn-r2');

    $(document).on('click', '.astropress-apply-cf-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const token = decodeURIComponent($btn.data('token'));
      $btn.prop('disabled', true).text('Applying & Saving...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_cf_quick_connect',
          cf_api_token: token,
          apply: 1,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data?.applied) {
            alert('Settings applied successfully! Reloading...');
            location.reload();
          } else {
            alert(res.data?.message || 'Failed to apply settings.');
            $btn.prop('disabled', false).text('Apply Discovered Settings to WordPress');
          }
        },
        error: function(xhr, status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false).text('Apply Discovered Settings to WordPress');
        }
      });
    });

  });
})(jQuery);
