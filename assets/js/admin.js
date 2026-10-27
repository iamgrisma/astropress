/**
 * AstroPress Admin Scripts
 *
 * Handles live Headless handshake testing, interactive Edge cache warming,
 * Cloudflare R2 bucket discovery, live connection testing, and bulk media sync.
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

    $('#astropress-generate-secret').on('click', function(e) {
      e.preventDefault();
      const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';
      let secret = 'ap_sec_';
      for (let i = 0; i < 32; i++) {
        secret += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      $('#astropress_secret').attr('type', 'text').val(secret);
      $('#astropress-toggle-secret').text('Hide');
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
          $btn.prop('disabled', false).text('Test Handshake');
        }
      });
    });

    // ========================================================
    // 3. Cloudflare R2 Discovery & Connection Testing
    // ========================================================
    $('#astropress-fetch-buckets').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-r2-status');
      const accountId = $('#r2_account_id').val()?.trim();
      const accessKey = $('#r2_access_key').val()?.trim();
      let secretKey = $('#r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) {
        secretKey = '';
      }

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
            const current = $('#r2_bucket').val()?.trim();
            if (!current) {
              $('#r2_bucket').val(buckets[0]);
            }
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
    // 4. Interactive Edge Cache Warmer
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
    // 5. Bulk Media Sync to Cloudflare R2
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
    // 6. Media Library Single Attachment AJAX Buttons
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

  });
})(jQuery);
