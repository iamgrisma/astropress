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
    // 2. Secret Key Handshake & Rotation Management
    // ========================================================
    const $testKeyBtn      = $('#astropress-test-key-btn');
    const $changeKeyBtn    = $('#astropress-change-key-btn');
    const $cancelKeyBtn    = $('#astropress-cancel-change-key-btn');
    const $keyInputBox     = $('#astropress-key-input-container');
    const $keyStatusBadge  = $('#astropress-key-badge');
    const $keyFeedback     = $('#astropress-key-test-feedback');
    const $keyStatusIcon   = $('#astropress-key-status-icon');

    // Revalidate Key Handshake
    $testKeyBtn.on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      $btn.prop('disabled', true).addClass('updating-message');
      $keyFeedback.html('<div style="padding:10px 14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; color:#0369a1; font-size:12px; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-update spin"></span> Verifying secret key handshake with Cloudflare Pages/Workers...</div>').slideDown(150);

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
            $keyStatusBadge.css({'background': '#ecfdf5', 'color': '#065f46', 'border-color': '#a7f3d0'}).text('Verified Active');
            $keyStatusIcon.removeClass('dashicons-warning dashicons-dismiss').addClass('dashicons-shield-alt').css('color', '#059669');
            $keyFeedback.html('<div style="padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> <strong>Key Verified:</strong> ' + res.data.message + ' (' + res.data.latency_ms + 'ms latency)</div>');
            $keyInputBox.slideUp(150);
          } else {
            $keyStatusBadge.css({'background': '#fef2f2', 'color': '#991b1b', 'border-color': '#fecaca'}).text('Failed (HTTP ' + (res.data.code || 0) + ')');
            $keyStatusIcon.removeClass('dashicons-shield-alt').addClass('dashicons-warning').css('color', '#dc2626');
            $keyFeedback.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-warning"></span> <strong>Key Failed:</strong> ' + res.data.message + ' Please enter a new key below.</div>');
            $keyInputBox.slideDown(200);
            $('#astropress_secret').focus();
          }
        },
        error: function(xhr, status, err) {
          $keyFeedback.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning"></span> Server error while testing secret key: ' + err + '</div>');
          $keyInputBox.slideDown(200);
        },
        complete: function() {
          $btn.prop('disabled', false).removeClass('updating-message');
        }
      });
    });

    $changeKeyBtn.on('click', function(e) {
      e.preventDefault();
      $keyInputBox.slideToggle(150, function() {
        if ($keyInputBox.is(':visible')) {
          $('#astropress_secret').focus();
        }
      });
    });

    $cancelKeyBtn.on('click', function(e) {
      e.preventDefault();
      $keyInputBox.slideUp(150);
      $('#astropress_secret').val('');
    });

    // Cloudflare Token Change / Cancel
    $('#astropress-btn-change-cf-token').on('click', function(e) {
      e.preventDefault();
      $('#astropress-cf-token-display').hide();
      $('#astropress-cf-token-input-wrap').show().find('input').focus();
    });

    $('#astropress-btn-cancel-cf-token').on('click', function(e) {
      e.preventDefault();
      $('#astropress-cf-token-input-wrap').hide().find('input').val('');
      $('#astropress-cf-token-display').show();
    });

    // R2 Secret Key Change / Cancel
    $('#r2-btn-change-secret').on('click', function(e) {
      e.preventDefault();
      $('#r2-secret-display').hide();
      $('#r2-secret-input-wrap').show().find('input').focus();
    });

    $('#r2-btn-cancel-secret').on('click', function(e) {
      e.preventDefault();
      $('#r2-secret-input-wrap').hide().find('input').val('');
      $('#r2-secret-display').show();
    });

    // Old handshake button backwards compatibility
    $('#astropress-test-handshake').on('click', function(e) {
      e.preventDefault();
      if ($testKeyBtn.length) {
        $testKeyBtn.trigger('click');
      }
    });

    // ========================================================
    // 3. Cloudflare R2 Discovery, Selection, Creation & CDN
    // ========================================================
    function handleFetchBuckets($btn, $status, accountId, accessKey, secretKey, $targetInput, $targetSelect, $toggleBtn) {
      if (!accountId || !accessKey) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter Account ID and Access Key ID first.</span>');
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
            const currentBucket = $targetInput.val()?.trim() || '';

            if ($targetSelect && $targetSelect.length) {
              $targetSelect.empty().append('<option value="">-- Choose Discovered Bucket --</option>');
              let matched = false;

              buckets.forEach(function(b) {
                const isSelected = (b === currentBucket);
                if (isSelected) matched = true;
                $targetSelect.append('<option value="' + b + '" ' + (isSelected ? 'selected' : '') + '>' + b + '</option>');
              });

              if (!matched && buckets.length === 1) {
                $targetSelect.val(buckets[0]);
                $targetInput.val(buckets[0]);
              } else if (matched) {
                $targetSelect.val(currentBucket);
              }

              $targetInput.hide();
              $targetSelect.show();
              if ($toggleBtn && $toggleBtn.length) {
                $toggleBtn.show().text('Type Manually');
              }
            } else {
              $targetInput.val(buckets[0]);
            }

            const chosen = ($targetSelect && $targetSelect.val()) ? $targetSelect.val() : (currentBucket || buckets[0]);

            // Auto-suggest custom CDN domain if empty
            const $domainInput = $('#r2_custom_domain');
            if ($domainInput.length && !$domainInput.val()?.trim() && chosen === 'topnepali') {
              $domainInput.val('https://objects.topnepali.com');
              $('#astropress-domain-hint').html('<strong style="color:#059669;">Auto-suggested:</strong> https://objects.topnepali.com (Connected to bucket ' + chosen + ')');
            }

            $status.html('<span style="color:#059669;font-weight:600;">✓ Discovered ' + buckets.length + ' bucket(s). ' + (chosen ? 'Selected: <code>' + chosen + '</code>' : 'Please select a bucket from the dropdown.') + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || res.data?.error || 'Could not discover buckets. Verify credentials or create a bucket below.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Discovery failed: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Discover Buckets');
        }
      });
    }

    // Discover in R2 Tab
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
        $('#r2_bucket'),
        $('#r2_bucket_select'),
        $('#astropress-btn-toggle-bucket-input')
      );
    });

    // Select change sync (R2 Tab)
    $('#r2_bucket_select').on('change', function() {
      const val = $(this).val();
      $('#r2_bucket').val(val);
      const $domainInput = $('#r2_custom_domain');
      if ($domainInput.length && !$domainInput.val()?.trim() && val === 'topnepali') {
        $domainInput.val('https://objects.topnepali.com');
        $('#astropress-domain-hint').html('<strong style="color:#059669;">Auto-suggested:</strong> https://objects.topnepali.com (Connected to bucket ' + val + ')');
      }
    });

    // Toggle between Dropdown and Manual Text Input
    $('#astropress-btn-toggle-bucket-input').on('click', function(e) {
      e.preventDefault();
      const $select = $('#r2_bucket_select');
      const $input = $('#r2_bucket');
      if ($select.is(':visible')) {
        $select.hide();
        $input.show().focus();
        $(this).text('Choose from Discovered');
      } else {
        $input.hide();
        $select.show().focus();
        $(this).text('Type Manually');
      }
    });

    // Show / Hide Inline Create Bucket (R2 Tab)
    $('#astropress-btn-show-create-bucket').on('click', function(e) {
      e.preventDefault();
      $('#astropress-create-bucket-wrap').slideToggle(150, function() {
        if ($(this).is(':visible')) {
          $('#astropress_new_bucket_name').focus();
        }
      });
    });

    $('#astropress-btn-cancel-create-bucket').on('click', function(e) {
      e.preventDefault();
      $('#astropress-create-bucket-wrap').slideUp(150);
      $('#astropress_new_bucket_name').val('');
      $('#astropress-create-bucket-status').empty();
    });

    // Confirm Create Bucket (R2 Tab)
    $('#astropress-btn-confirm-create-bucket').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const bucketName = $('#astropress_new_bucket_name').val()?.trim().toLowerCase();
      const $status = $('#astropress-create-bucket-status');

      if (!bucketName) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter a bucket name.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Creating bucket on R2...');
      $status.html('<span style="color:#64748b;">Creating bucket via S3 SigV4 API...</span>');

      let secretKey = $('#r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) secretKey = '';

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_create_bucket',
          bucket_name: bucketName,
          account_id: $('#r2_account_id').val()?.trim(),
          access_key: $('#r2_access_key').val()?.trim(),
          secret_key: secretKey,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + res.data.message + '</span>');
            const created = res.data.bucket || bucketName;

            // Add to dropdown and select it
            const $select = $('#r2_bucket_select');
            $select.append('<option value="' + created + '" selected>' + created + '</option>');
            $select.val(created).show();
            $('#r2_bucket').val(created).hide();
            $('#astropress-btn-toggle-bucket-input').show().text('Type Manually');

            setTimeout(function() {
              $('#astropress-create-bucket-wrap').slideUp(200);
            }, 1800);
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || 'Bucket creation failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Create & Use Bucket');
        }
      });
    });

    // Discover in Onboarding Wizard
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
        $('#onboard_r2_bucket'),
        $('#onboard_r2_bucket_select'),
        $('#onboard-btn-toggle-bucket-input')
      );
    });

    $('#onboard_r2_bucket_select').on('change', function() {
      $('#onboard_r2_bucket').val($(this).val());
    });

    $('#onboard-btn-toggle-bucket-input').on('click', function(e) {
      e.preventDefault();
      const $select = $('#onboard_r2_bucket_select');
      const $input = $('#onboard_r2_bucket');
      if ($select.is(':visible')) {
        $select.hide();
        $input.show().focus();
        $(this).text('Choose from Discovered');
      } else {
        $input.hide();
        $select.show().focus();
        $(this).text('Type Manually');
      }
    });

    $('#onboard-btn-show-create-bucket').on('click', function(e) {
      e.preventDefault();
      $('#onboard-create-bucket-wrap').slideToggle(150, function() {
        if ($(this).is(':visible')) {
          $('#onboard_new_bucket_name').focus();
        }
      });
    });

    $('#onboard-btn-cancel-create-bucket').on('click', function(e) {
      e.preventDefault();
      $('#onboard-create-bucket-wrap').slideUp(150);
    });

    $('#onboard-btn-confirm-create-bucket').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const bucketName = $('#onboard_new_bucket_name').val()?.trim().toLowerCase();
      const $status = $('#onboard-create-bucket-status');

      if (!bucketName) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter a bucket name.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Creating...');
      let secretKey = $('#onboard_r2_secret_key').val()?.trim();
      if (secretKey && secretKey.indexOf('••••') !== -1) secretKey = '';

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_create_bucket',
          bucket_name: bucketName,
          account_id: $('#onboard_r2_account_id').val()?.trim(),
          access_key: $('#onboard_r2_access_key').val()?.trim(),
          secret_key: secretKey,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + res.data.message + '</span>');
            const created = res.data.bucket || bucketName;
            const $select = $('#onboard_r2_bucket_select');
            $select.append('<option value="' + created + '" selected>' + created + '</option>');
            $select.val(created).show();
            $('#onboard_r2_bucket').val(created).hide();
            $('#onboard-btn-toggle-bucket-input').show().text('Type Manually');

            setTimeout(function() {
              $('#onboard-create-bucket-wrap').slideUp(200);
            }, 1800);
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || 'Bucket creation failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Create & Select');
        }
      });
    });

    // Deploy Cloudflare "Cache Everything" Page Rule
    $('#astropress-btn-deploy-cache-rule').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-deploy-rule-status');
      const cdnDomain = $('#r2_custom_domain').val()?.trim();

      if (!cdnDomain) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter a Public CDN Domain first (e.g. objects.topnepali.com).</span>');
        return;
      }

      $btn.prop('disabled', true).text('Deploying Rule to Cloudflare Edge...');
      $status.html('<span style="color:#64748b;">Deploying "Cache Everything" rule with 1-year Edge TTL via Cloudflare API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_deploy_cache_rule',
          cdn_domain: cdnDomain,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || 'Failed to deploy rule.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Network error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic" style="vertical-align:-2px;"></span> Deploy Edge Cache Rule');
        }
      });
    });

    // Test R2 Connection Live
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
      const bucket = ($('#r2_bucket_select').is(':visible') && $('#r2_bucket_select').val()) ? $('#r2_bucket_select').val() : $('#r2_bucket').val()?.trim();
      const customDomain = $('#r2_custom_domain').val()?.trim();

      if (!accountId || !accessKey || !bucket) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter Account ID, Access Key, and Bucket name.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Testing...');
      $status.html('<span style="color:#64748b;">Testing read/write permissions & CDN resolution...</span>');

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
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data.message || 'Connection test failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Connection error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align:-2px;"></span> Test Connection');
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
    // 7. Bulk Media Sync Engine & Verified Storage Cleanup
    // ========================================================
    let syncPaused = false;
    let syncCancelled = false;

    $('#r2g-sync-quality-slider').on('input', function() {
      $('#r2g-sync-quality-val').text($(this).val() + '%');
    });

    $('#r2g-sync-engine').on('change', function() {
      const eng = $(this).val();
      const $notice = $('#r2g-sync-notice-text');
      if (eng === 'resmush') {
        $notice.text('reSmush.it Engine: Compresses via free reSmush API (auto-fallback to PHP GD/Imagick if >5MB or offline).');
      } else if (eng === 'none') {
        $notice.text('Lossless Engine: Pushes binary files directly to R2 without alteration.');
      } else {
        $notice.text('Server Engine: Media items are processed and pushed to R2 by PHP in safe batches of 5.');
      }
    });

    // Bulk Sync Execution
    $('#r2g-btn-start-sync, #astropress-start-sync').on('click', function(e) {
      e.preventDefault();
      if ($(this).is(':disabled')) return;

      syncPaused = false;
      syncCancelled = false;
      let failedIds = [];

      const $startBtn = $(this);
      const $pauseBtn = $('#r2g-btn-pause-sync');
      const $cancelBtn = $('#r2g-btn-cancel-sync');
      const $progBox = $('#r2g-sync-progress-box');
      const $progFill = $('#r2g-sync-progress-fill');
      const $progText = $('#r2g-sync-status-text');
      const $progPct = $('#r2g-sync-percentage');

      const syncPreset = $('#r2g-sync-preset').val() || 'keep_current';
      const syncQuality = parseInt($('#r2g-sync-quality-slider').val(), 10) || 82;
      const syncEngine = $('#r2g-sync-engine').val() || 'server';

      $startBtn.hide();
      $pauseBtn.show().text('Pause');
      $cancelBtn.show();
      $progBox.show();

      function processBatch() {
        if (syncPaused || syncCancelled) {
          return;
        }

        $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_bulk_sync_batch',
            batch_size: 5,
            format: 'keep',
            preset: syncPreset,
            quality: syncQuality,
            engine: syncEngine,
            exclude_ids: failedIds,
            nonce: nonce,
          },
          success: function(res) {
            if (!res.success) {
              $progText.text('Error: ' + (res.data?.message || 'Sync failed'));
              $pauseBtn.hide();
              $startBtn.show().text('Retry Bulk Sync');
              return;
            }

            if (res.data.results && Array.isArray(res.data.results)) {
              res.data.results.forEach(function(item) {
                if (item.status === 'failed' && failedIds.indexOf(item.id) === -1) {
                  failedIds.push(item.id);
                }
              });
            }

            const stats = res.data.stats || {};
            const total = stats.total_wp || 0;
            const synced = stats.synced || 0;
            const remaining = (typeof res.data.remaining_count !== 'undefined') ? res.data.remaining_count : Math.max(0, total - synced);
            const pct = total > 0 ? Math.min(100, Math.round((synced / total) * 100)) : 100;

            // Update stats cards live
            $('#r2g-stat-total').text(total);
            $('#r2g-stat-synced').text(stats.synced || 0);
            $('#r2g-stat-cloud').text(stats.cloud_only || 0);
            $('#r2g-stat-local').text(stats.local_only || 0);
            if (stats.verified_with_local !== undefined) {
              $('#r2g-verified-count').text(stats.verified_with_local);
              $('#r2g-btn-clean-verified').prop('disabled', stats.verified_with_local === 0);
            }

            $progFill.css('width', pct + '%');
            $progPct.text(pct + '% (' + synced + '/' + total + ')');
            $progText.text('Synced ' + synced + ' / ' + total + ' media items (' + remaining + ' remaining)...');

            if (res.data.done || !res.data.remaining || remaining <= failedIds.length) {
              $progFill.css('width', '100%');
              $progPct.text('100% (' + total + '/' + total + ')');
              if (failedIds.length > 0) {
                $progText.text('Bulk sync completed. ' + failedIds.length + ' item(s) could not be synced. Check R2 permissions and click below to retry.');
                $startBtn.show().text('Retry Failed Items (' + failedIds.length + ')');
              } else {
                $progText.html('<strong style="color:#059669;">✓ All media successfully synced to Cloudflare R2! (0 remaining). Local copies preserved safely.</strong>');
                $startBtn.show().text('Sync Finished (Run Again)');
              }
              $pauseBtn.hide();
              $cancelBtn.hide();
              return;
            }

            // Continue to next batch
            setTimeout(processBatch, 400);
          },
          error: function() {
            $progText.text('Network glitch during batch sync. Retrying in 3s...');
            setTimeout(processBatch, 3000);
          }
        });
      }

      processBatch();
    });

    $('#r2g-btn-pause-sync').on('click', function(e) {
      e.preventDefault();
      syncPaused = !syncPaused;
      $(this).text(syncPaused ? 'Resume' : 'Pause');
      if (!syncPaused) {
        $('#r2g-btn-start-sync').trigger('click');
      }
    });

    $('#r2g-btn-cancel-sync').on('click', function(e) {
      e.preventDefault();
      syncCancelled = true;
      syncPaused = true;
      $('#r2g-btn-pause-sync').hide();
      $('#r2g-btn-cancel-sync').hide();
      $('#r2g-btn-start-sync').show().text('Resume Bulk Sync');
      $('#r2g-sync-status-text').text('Sync paused by user.');
    });

    // Verified Local Storage Cleanup
    $('#r2g-btn-clean-verified').on('click', function(e) {
      e.preventDefault();
      if ($(this).is(':disabled')) return;
      if (!confirm('Clean local media files after verifying each object exists on Cloudflare R2? This leaves R2 as your media storage. Cancel if you need to take a server backup first.')) {
        return;
      }

      const $btn = $(this);
      const $box = $('#r2g-clean-progress-box');
      const $fill = $('#r2g-clean-progress-fill');
      const $text = $('#r2g-clean-status-text');
      const $pct = $('#r2g-clean-percentage');

      $btn.prop('disabled', true);
      $box.show();
      $fill.css('width', '0%');
      $pct.text('0%');
      $text.text('Cleaning local copies in safe batches...');

      let totalToClean = parseInt($('#r2g-verified-count').text(), 10) || 1;
      let cleanedSoFar = 0;

      function cleanBatch() {
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_bulk_clean_verified_local',
            batch_size: 15,
            nonce: nonce,
          },
          success: function(res) {
            if (!res.success) {
              $text.text('Error: ' + (res.data?.message || 'Cleanup failed'));
              $btn.prop('disabled', false);
              return;
            }

            const cleanedBatch = res.data.cleaned_count || 0;
            const failedBatch = res.data.failed_count || 0;
            const remaining = res.data.remaining || 0;
            cleanedSoFar += cleanedBatch;

            const pct = (cleanedSoFar + remaining) > 0 ? Math.min(100, Math.round((cleanedSoFar / (cleanedSoFar + remaining)) * 100)) : 100;
            $fill.css('width', pct + '%');
            $pct.text(pct + '%');
            $text.text('Cleaned ' + cleanedSoFar + ' local files. ' + remaining + ' remaining...');

            if (failedBatch > 0 && cleanedBatch === 0) {
              $text.text('Stopped: R2 could not verify remaining files, or server could not delete them. No further files removed.');
              $btn.prop('disabled', false);
              return;
            }

            // Update verified count in UI
            $('#r2g-verified-count').text(remaining);

            // Update stats cards live
            if (res.data.stats) {
              $('#r2g-stat-synced').text(res.data.stats.synced || 0);
              $('#r2g-stat-cloud').text(res.data.stats.cloud_only || 0);
              $('#r2g-stat-local').text(res.data.stats.local_only || 0);
            }

            if (res.data.done || remaining === 0) {
              $fill.css('width', '100%');
              $pct.text('100%');
              $text.html('<strong style="color:#059669;">✓ Verified local cleanup complete! Web hosting disk space freed.</strong>');
              $btn.prop('disabled', true);
              return;
            }

            setTimeout(cleanBatch, 300);
          },
          error: function() {
            $text.text('Network glitch. Retrying cleanup in 3s...');
            setTimeout(cleanBatch, 3000);
          }
        });
      }

      cleanBatch();
    });

    // Database Index Maintenance & Legacy Import
    $('#r2g-btn-import-legacy').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#r2g-import-status');

      $btn.prop('disabled', true).text('Scanning & Importing...');
      $status.css('color', '#64748b').text('Scanning Media Cloud Sync & previous offload records...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_import_legacy',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.css('color', '#059669').html('<strong>✓ ' + res.data.message + '</strong>');
            if (res.data.stats) {
              $('#r2g-stat-synced').text(res.data.stats.synced || 0);
              $('#r2g-stat-cloud').text(res.data.stats.cloud_only || 0);
              $('#r2g-stat-local').text(res.data.stats.local_only || 0);
            }
          } else {
            $status.css('color', '#dc2626').text(res.data?.message || 'Import failed.');
          }
        },
        error: function() {
          $status.css('color', '#dc2626').text('Network request failed.');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-import" style="vertical-align:-2px;"></span> Import Existing Offloaded Media');
        }
      });
    });

    $('#r2g-btn-reindex').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#r2g-reindex-status');

      $btn.prop('disabled', true).text('Scanning...');
      $status.css('color', '#64748b').text('Verifying local files and database index...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_reindex_media',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.css('color', '#059669').html('<strong>✓ ' + res.data.message + '</strong>');
            if (res.data.stats) {
              $('#r2g-stat-synced').text(res.data.stats.synced || 0);
              $('#r2g-stat-cloud').text(res.data.stats.cloud_only || 0);
              $('#r2g-stat-local').text(res.data.stats.local_only || 0);
            }
          } else {
            $status.css('color', '#dc2626').text(res.data?.message || 'Re-index failed.');
          }
        },
        error: function() {
          $status.css('color', '#dc2626').text('Network request failed.');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-image-rotate" style="vertical-align:-2px;"></span> Scan & Refresh Index');
        }
      });
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
