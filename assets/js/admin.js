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

    $('#onboard-gen-secret').on('click', function(e) {
      e.preventDefault();
      $('#onboard_secret').val(generateRandomSecret());
    });

    // ========================================================
    // 2. Secret Key Handshake Test
    // ========================================================
    const $testKeyBtn      = $('#astropress-test-key-btn');
    const $keyStatusBadge  = $('#astropress-key-badge');
    const $keyFeedback     = $('#astropress-key-test-feedback');

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
            $keyFeedback.html('<div style="padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> <strong>Key Verified:</strong> ' + res.data.message + ' (' + res.data.latency_ms + 'ms latency)</div>');
          } else {
            $keyStatusBadge.css({'background': '#fef2f2', 'color': '#991b1b', 'border-color': '#fecaca'}).text('Failed (HTTP ' + (res.data.code || 0) + ')');
            $keyFeedback.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-warning"></span> <strong>Handshake Failed:</strong> ' + res.data.message + ' <a href="admin.php?page=astropress&tab=onboarding" style="color:#b91c1c; text-decoration:underline; font-weight:600; margin-left:6px;">Update in Setup Wizard &rarr;</a></div>');
          }
        },
        error: function(xhr, status, err) {
          $keyFeedback.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning"></span> Server error while testing secret key: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).removeClass('updating-message');
        }
      });
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
    function handleFetchBuckets($btn, $status, accountId, accessKey, secretKey, $targetInput, $targetSelect, $toggleBtn, cfToken) {
      cfToken = cfToken || $('#onboard_cf_api_token').val()?.trim() || '';

      $btn.prop('disabled', true).text('Discovering...');
      $status.html('<span style="color:#64748b;">Connecting to Cloudflare R2 API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_fetch_buckets',
          account_id: accountId,
          access_key: accessKey,
          secret_key: secretKey,
          cf_api_token: cfToken,
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

    // Audit CDN in R2 Tab
    $(document).on('click', '#r2-audit-cdn-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const cdnDomain = $('#r2_custom_domain').val().trim();
      const bucket = ($('#r2_bucket_select').is(':visible') && $('#r2_bucket_select').val()) ? $('#r2_bucket_select').val() : $('#r2_bucket').val()?.trim();
      const accountId = $('#r2_account_id').val()?.trim();
      const $box = $('#r2-cdn-audit-box');

      if (!cdnDomain) {
        alert('Please enter a Public CDN Domain first (e.g. media.example.com).');
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Auditing...');
      $box.show().html('<div class="astropress-cdn-audit-loading"><span class="dashicons dashicons-update"></span> Auditing DNS records, R2 bucket domain attachment & Edge cache rules...</div>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_audit_cdn_domain',
          custom_domain: cdnDomain,
          account_id: accountId,
          bucket_name: bucket,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            const d = res.data;
            let html = '<div class="astropress-cdn-audit-wrap">';
            html += '<div style="font-weight:700; font-size:13px; color:#1e293b; margin-bottom:8px;">CDN Domain Audit Report for <code>' + d.host + '</code>:</div>';
            html += '<div class="astropress-cdn-audit-grid">';

            // DNS Status
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>DNS Routing:</strong> ' + (d.dns_found ? (d.dns_proxied ? 'Proxied via Cloudflare Edge (Orange Cloud)' : 'DNS Found (Unproxied)') : 'No DNS Record Found') + '</div>';
            html += '<span class="astropress-badge" style="' + (d.dns_proxied ? 'background:#ecfdf5;color:#065f46;' : 'background:#fffbeb;color:#b45309;') + '">' + (d.dns_proxied ? 'Optimal' : (d.dns_found ? 'DNS OK' : 'Pending')) + '</span>';
            html += '</div>';

            // R2 Custom Domain Attachment
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>R2 Bucket Attachment:</strong> ' + (d.r2_attached ? 'Domain Attached to Bucket (' + d.r2_status + ')' : 'Domain Not Attached to R2 Bucket') + '</div>';
            if (d.r2_attached) {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">Attached</span>';
            } else {
              html += '<button type="button" class="button button-primary button-small" id="onboard-attach-r2-domain-btn" data-host="' + d.host + '" data-bucket="' + bucket + '">Attach Domain to R2 (1-Click)</button>';
            }
            html += '</div>';

            // Cache Rule Status
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>Edge Cache Rule:</strong> ' + (d.rule_desc || 'No cache rule') + '</div>';
            if (d.rule_status === 'optimal') {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">1-Year Cache Everything Active</span>';
            } else {
              html += '<button type="button" class="button button-secondary button-small" id="astropress-btn-deploy-cache-rule">Deploy / Upgrade Cache Rule</button>';
            }
            html += '</div>';

            // Edge Cache Purge Row
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>CDN Edge Cache:</strong> Instant Cloudflare Zone & CDN Purge</div>';
            html += '<button type="button" class="button button-secondary button-small" id="r2-purge-cdn-btn"><span class="dashicons dashicons-trash" style="vertical-align:-2px;font-size:13px;width:13px;height:13px;"></span> Purge CDN Cache</button>';
            html += '</div>';

            html += '</div>';
            html += '<div id="onboard-attach-status" style="margin-top:6px; font-size:12px;"></div>';
            html += '</div>';

            $box.html(html);
          } else {
            $box.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Audit failed.') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $box.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> Audit request error: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> Audit CDN & Rules');
        }
      });
    });

    // Purge CDN Cache from R2 Tab
    $(document).on('click', '#r2-purge-cdn-btn', function(e) {
      e.preventDefault();
      if (!confirm('Purge all cached CDN assets on Cloudflare Edge for this zone?')) {
        return;
      }
      const $btn = $(this);
      const $status = $('#astropress-deploy-rule-status');

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Purging...');
      $status.html('<span style="color:#64748b;">Purging Cloudflare Edge cache...</span>').show();

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
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + (res.data?.message || 'CDN Edge cache purged successfully!') + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || 'Purge failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Purge CDN Cache');
        }
      });
    });

    // Client-side domain validation for CDN Domain inputs
    function validateCdnDomainInput($input, $hintContainer) {
      const raw = $input.val()?.trim() || '';
      if (!raw) return true;
      const host = raw.toLowerCase().replace(/^https?:\/\//, '').split('/')[0].split(':')[0];
      const allowedRoots = (typeof astropressConfig !== 'undefined' && astropressConfig.allowedRoots) ? astropressConfig.allowedRoots : [];
      if (!allowedRoots.length) return true;

      let isAllowed = false;
      for (let i = 0; i < allowedRoots.length; i++) {
        const root = allowedRoots[i].toLowerCase();
        if (host === root || host.endsWith('.' + root)) {
          isAllowed = true;
          break;
        }
      }

      if (!isAllowed) {
        if ($hintContainer && $hintContainer.length) {
          $hintContainer.html('<span style="color:#dc2626;font-weight:600;">⚠️ CDN Domain must be a subdomain of ' + allowedRoots.join(', ') + ' (e.g. media.' + allowedRoots[0] + ')</span>').show();
        }
        return false;
      } else {
        if ($hintContainer && $hintContainer.length) {
          $hintContainer.html('<span style="color:#059669;font-weight:600;">✓ Valid CDN subdomain for ' + allowedRoots[0] + '</span>').show();
        }
        return true;
      }
    }

    $(document).on('blur', '#onboard_r2_custom_domain', function() {
      validateCdnDomainInput($(this), $('#onboard-cache-rule-status'));
    });

    $(document).on('blur', '#r2_custom_domain', function() {
      validateCdnDomainInput($(this), $('#astropress-domain-hint'));
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
      const cfToken = $('#onboard_cf_api_token').val()?.trim() || '';

      if (!bucket) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please select or enter an R2 Bucket name.</span>');
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
          cf_api_token: cfToken,
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
    // 6. Interactive Batched Edge Cache Warmer & WP-Cron Background Worker
    // ========================================================
    var isWarming = false;
    var cancelRequested = false;
    var bgPollTimer = null;

    var $startWarmBtn   = $('#topnepali-start-warm-btn, #astropress-start-warmer');
    var $startBgWarmBtn = $('#topnepali-start-bg-warm-btn, #astropress-start-bg-warmer');
    var $cancelWarmBtn  = $('#topnepali-cancel-warm-btn, #astropress-cancel-warmer');
    var $progressBox    = $('#topnepali-warm-progress-box, #astropress-warmer-progress-container');
    var $countEl        = $('#topnepali-warm-current, #astropress-warmer-progress-count');
    var $totalEl        = $('#topnepali-warm-total, #astropress-warmer-progress-total');
    var $percentEl      = $('#topnepali-warm-percent, #astropress-warmer-progress-percent');
    var $barFill        = $('#topnepali-progress-bar-fill, #astropress-warmer-progress-bar');
    var $currentUrl     = $('#topnepali-warm-current-url, #astropress-warmer-progress-text');
    var $statusPill     = $('#topnepali-warm-status-pill, #astropress-warmer-status-pill');
    var $logConsole     = $('#topnepali-warm-log, #astropress-warmer-log');
    var $presetSelect   = $('#topnepali-warm-preset, #astropress-warmer-preset');
    var $delaySelect    = $('#topnepali-warm-delay, #astropress-warmer-delay');

    // Background Job UI Elements
    var $bgBox         = $('#topnepali-bg-warm-box');
    var $bgCurrent     = $('#topnepali-bg-warm-current');
    var $bgTotal       = $('#topnepali-bg-warm-total');
    var $bgBarFill     = $('#topnepali-bg-progress-bar-fill');
    var $bgStatusPill  = $('#topnepali-bg-warm-status-pill');
    var $bgMeta        = $('#topnepali-bg-warm-meta');
    var $pauseBgBtn    = $('#topnepali-pause-bg-btn');
    var $resumeBgBtn   = $('#topnepali-resume-bg-btn');
    var $cancelBgBtn   = $('#topnepali-cancel-bg-btn');
    var $clearBgBtn    = $('#topnepali-clear-bg-btn');

    function appendWarmLog(statusClass, path, metaText) {
      var $entry = $('<div class="topnepali-log-entry"></div>');
      var $pathSpan = $('<span class="topnepali-log-path"></span>').text(path);
      var $metaSpan = $('<span class="topnepali-log-meta ' + statusClass + '"></span>').text(metaText);
      $entry.append($pathSpan).append($metaSpan);
      $logConsole.append($entry);
      if ($logConsole[0]) {
        $logConsole.scrollTop($logConsole[0].scrollHeight);
      }
    }

    // --- 6.1 BROWSER-BASED LIVE WARMER ---
    $startWarmBtn.on('click', async function(e) {
      e.preventDefault();
      if (isWarming) return;

      var preset = $presetSelect.val() || 'standard';
      var throttleMs = parseInt($delaySelect.val(), 10) || 1000;
      var batchSize = config.defaultBatch || 2;
      if (batchSize < 1) batchSize = 1;

      isWarming = true;
      cancelRequested = false;

      // UI Initial State
      $startWarmBtn.prop('disabled', true).css('opacity', 0.6);
      $startBgWarmBtn.prop('disabled', true).css('opacity', 0.6);
      $cancelWarmBtn.show().prop('disabled', false);
      $progressBox.slideDown(200);
      $logConsole.empty();
      $barFill.css('width', '0%');
      $percentEl.text('0%');
      $countEl.text('0');
      $statusPill.text('FETCHING QUEUE...').css('background', '#0284c7');
      $currentUrl.text('Querying URLs to warm from WordPress / XML Sitemap...');

      appendWarmLog('topnepali-log-status-200', '— System Initialized —', 'Scope: ' + preset + ' | Gap: ' + throttleMs + 'ms');

      // Step 1: Fetch list of URLs to warm
      var queueResponse;
      try {
        queueResponse = await $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_get_warmup_urls',
            nonce: nonce,
            preset: preset
          }
        });
      } catch (err) {
        appendWarmLog('topnepali-log-status-err', 'Failed to fetch warmup list', err.statusText || 'Error');
        $statusPill.text('ERROR').css('background', '#ef4444');
        $startWarmBtn.prop('disabled', false).css('opacity', 1);
        $startBgWarmBtn.prop('disabled', false).css('opacity', 1);
        $cancelWarmBtn.hide();
        isWarming = false;
        return;
      }

      if (!queueResponse.success || !queueResponse.data.items || !queueResponse.data.items.length) {
        appendWarmLog('topnepali-log-status-err', 'No URLs found for selected scope', 'Empty');
        $statusPill.text('EMPTY').css('background', '#64748b');
        $startWarmBtn.prop('disabled', false).css('opacity', 1);
        $startBgWarmBtn.prop('disabled', false).css('opacity', 1);
        $cancelWarmBtn.hide();
        isWarming = false;
        return;
      }

      var items = queueResponse.data.items;
      var total = items.length;
      $totalEl.text(total);
      appendWarmLog('topnepali-log-status-200', 'Queue loaded successfully', total + ' URLs ready');

      var current = 0;
      $statusPill.text('WARMING (' + batchSize + '/batch)...').css('background', '#059669');

      // Step 2: Warm in polite sequential batches with user-defined delay
      for (var i = 0; i < items.length; i += batchSize) {
        if (cancelRequested) {
          appendWarmLog('topnepali-log-status-redirect', '— Warming process stopped by user —', 'Cancelled');
          $statusPill.text('CANCELLED').css('background', '#d97706');
          break;
        }

        var batch = items.slice(i, i + batchSize);
        var firstItem = batch[0];
        $currentUrl.text('Warming: ' + (firstItem.url || firstItem));

        try {
          var batchRes = await $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'astropress_warm_batch',
              nonce: nonce,
              batch: batch
            }
          });

          if (batchRes.success && batchRes.data.results) {
            batchRes.data.results.forEach(function(r) {
              var statusClass = 'topnepali-log-status-200';
              if (r.status >= 300 && r.status < 400) statusClass = 'topnepali-log-status-redirect';
              else if (r.status >= 400 || !r.ok) statusClass = 'topnepali-log-status-err';

              var metaStr = 'HTTP ' + r.status + ' | ' + (r.cf_cache || 'MISS') + ' | ' + r.duration_ms + 'ms';
              appendWarmLog(statusClass, r.url, metaStr);
            });
          }
        } catch (bErr) {
          appendWarmLog('topnepali-log-status-err', 'Batch network error', bErr.statusText || 'Error');
        }

        current = Math.min(total, i + batch.length);
        var percent = Math.round((current / total) * 100);

        $countEl.text(current);
        $percentEl.text(percent + '%');
        $barFill.css('width', percent + '%');

        if (i + batchSize < items.length && !cancelRequested) {
          await new Promise(function(resolve) { setTimeout(resolve, throttleMs); });
        }
      }

      // Step 3: Complete or Clean up
      if (!cancelRequested) {
        $statusPill.text('COMPLETED').css('background', '#059669');
        $currentUrl.text('All ' + total + ' edge cache URLs successfully refreshed and warmed!');
        appendWarmLog('topnepali-log-status-200', '— Process Finished —', '100% Pre-warmed');
      }

      $startWarmBtn.prop('disabled', false).css('opacity', 1);
      $startBgWarmBtn.prop('disabled', false).css('opacity', 1);
      $cancelWarmBtn.hide();
      isWarming = false;
    });

    $cancelWarmBtn.on('click', function(e) {
      e.preventDefault();
      cancelRequested = true;
      $(this).prop('disabled', true).text('Cancelling...');
    });

    // --- 6.2 WP-CRON BACKGROUND ASYNC WARMER ---
    $startBgWarmBtn.on('click', async function(e) {
      e.preventDefault();
      if ($startBgWarmBtn.prop('disabled')) return;

      var preset = $presetSelect.val() || 'standard';
      var throttleMs = parseInt($delaySelect.val(), 10) || 1000;
      var batchSize = config.defaultBatch || 2;

      $startBgWarmBtn.prop('disabled', true).addClass('updating-message');

      try {
        var res = await $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_start_bg_warm',
            nonce: nonce,
            preset: preset,
            delay: throttleMs,
            batch_size: batchSize
          }
        });

        if (res.success && res.data.job) {
          updateBgJobUI(res.data.job);
          startBgPolling();
        } else {
          alert('Could not start background job: ' + (res.data?.message || 'Unknown error'));
        }
      } catch (err) {
        alert('Server communication error while queueing background job.');
      } finally {
        $startBgWarmBtn.prop('disabled', false).removeClass('updating-message');
      }
    });

    function updateBgJobUI(job) {
      if (!job || !job.active) {
        if (!job || job.status === 'cancelled') {
          $bgBox.slideUp(150);
        }
        return;
      }

      $bgBox.slideDown(200);
      $bgCurrent.text(job.current || 0);
      $bgTotal.text(job.total || 0);

      var pct = (job.total > 0) ? Math.round((job.current / job.total) * 100) : 0;
      $bgBarFill.css('width', pct + '%');

      var st = (job.status || 'RUNNING').toUpperCase();
      $bgStatusPill.text(st);

      if (st === 'RUNNING') {
        $bgStatusPill.css('background', '#059669');
        $pauseBgBtn.show();
        $resumeBgBtn.hide();
        $cancelBgBtn.show();
        $clearBgBtn.hide();
        $bgMeta.text('Job active in background. Processing ~' + (job.batch_size || 2) + ' URLs per tick.');
      } else if (st === 'PAUSED') {
        $bgStatusPill.css('background', '#d97706');
        $pauseBgBtn.hide();
        $resumeBgBtn.show();
        $cancelBgBtn.show();
        $clearBgBtn.hide();
        $bgMeta.text('Job is paused. Click Resume Job to continue warming.');
      } else if (st === 'COMPLETED') {
        $bgStatusPill.css('background', '#0284c7');
        $pauseBgBtn.hide();
        $resumeBgBtn.hide();
        $cancelBgBtn.hide();
        $clearBgBtn.show();
        $bgMeta.text('Job finished! Successfully pre-warmed all ' + job.total + ' URLs.');
        stopBgPolling();
      } else if (st === 'CANCELLED') {
        $bgStatusPill.css('background', '#dc2626');
        $pauseBgBtn.hide();
        $resumeBgBtn.hide();
        $cancelBgBtn.hide();
        $clearBgBtn.show();
        $bgMeta.text('Job cancelled by user.');
        stopBgPolling();
      }
    }

    async function pollBgJobStatus() {
      try {
        var res = await $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_get_bg_warm_status',
            nonce: nonce
          }
        });
        if (res.success && res.data.job) {
          updateBgJobUI(res.data.job);
        }
      } catch (err) {}
    }

    function startBgPolling() {
      stopBgPolling();
      pollBgJobStatus();
      bgPollTimer = setInterval(pollBgJobStatus, 3000);
    }

    function stopBgPolling() {
      if (bgPollTimer) {
        clearInterval(bgPollTimer);
        bgPollTimer = null;
      }
    }

    $pauseBgBtn.on('click', async function(e) {
      e.preventDefault();
      $(this).prop('disabled', true);
      await $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'astropress_pause_bg_warm', nonce: nonce }
      });
      $(this).prop('disabled', false);
      pollBgJobStatus();
    });

    $resumeBgBtn.on('click', async function(e) {
      e.preventDefault();
      $(this).prop('disabled', true);
      await $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'astropress_resume_bg_warm', nonce: nonce }
      });
      $(this).prop('disabled', false);
      startBgPolling();
    });

    $cancelBgBtn.on('click', async function(e) {
      e.preventDefault();
      if (!confirm('Are you sure you want to cancel the background warmer job?')) return;
      $(this).prop('disabled', true);
      await $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'astropress_cancel_bg_warm', nonce: nonce }
      });
      $(this).prop('disabled', false);
      pollBgJobStatus();
    });

    $clearBgBtn.on('click', async function(e) {
      e.preventDefault();
      $(this).prop('disabled', true);
      await $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: { action: 'astropress_clear_bg_warm', nonce: nonce }
      });
      $(this).prop('disabled', false);
      $bgBox.slideUp(150);
    });

    // Automatically check for running background job on page load
    pollBgJobStatus();

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

    // 7c. Audit Media Health & Missing Files
    $('#r2g-btn-audit-health').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#r2g-audit-status');
      $btn.prop('disabled', true);

      let offset = 0;
      let totalMissingLocal = 0;
      let totalMissingR2 = 0;
      let totalVerified = 0;

      function runAuditBatch() {
        $status.css('color', '#64748b').text('Auditing items ' + offset + '... (Checking CDN HEAD & server disk)');
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'astropress_audit_media_health',
            nonce: nonce,
            offset: offset,
            batch_size: 15,
          },
          success: function(res) {
            if (res.success) {
              const d = res.data;
              totalMissingLocal += d.missing_local || 0;
              totalMissingR2 += d.missing_r2 || 0;
              totalVerified += d.verified_ok || 0;

              if (d.stats) {
                $('#r2g-stat-synced').text(d.stats.synced || 0);
                $('#r2g-stat-cloud').text(d.stats.cloud_only || 0);
                $('#r2g-stat-local').text(d.stats.local_only || 0);
              }

              if (d.done || d.audited === 0) {
                let msg = '✓ Audit complete! ' + totalVerified + ' healthy in R2.';
                if (totalMissingR2 > 0) {
                  msg += ' ⚠ ' + totalMissingR2 + ' missing in R2 (marked as failed).';
                }
                if (totalMissingLocal > 0) {
                  msg += ' ' + totalMissingLocal + ' cloud-only (no server copy).';
                }
                $status.css('color', totalMissingR2 > 0 ? '#b45309' : '#059669').html('<strong>' + msg + '</strong>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-heart" style="vertical-align:-2px;"></span> Audit Media Health');
              } else {
                offset = d.next_offset;
                runAuditBatch();
              }
            } else {
              $status.css('color', '#dc2626').text(res.data?.message || 'Audit encountered an error.');
              $btn.prop('disabled', false).html('<span class="dashicons dashicons-heart" style="vertical-align:-2px;"></span> Audit Media Health');
            }
          },
          error: function() {
            $status.css('color', '#dc2626').text('Audit request timed out or failed.');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-heart" style="vertical-align:-2px;"></span> Audit Media Health');
          }
        });
      }

      runAuditBatch();
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

              // Render Deep Abilities Audit
              if (data.abilities) {
                html += '<div style="margin-top:14px;">';
                html += '<h4 style="margin:0 0 8px 0; color:#334155; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Token Abilities & Permissions Health Audit:</h4>';
                html += '<div class="astropress-abilities-grid">';
                for (const key in data.abilities) {
                  const ab = data.abilities[key];
                  const isGranted = ab.granted;
                  const cardClass = isGranted ? 'is-granted' : 'is-missing';
                  const icon = isGranted ? 'dashicons-yes' : 'dashicons-warning';
                  html += '<div class="astropress-ability-card ' + cardClass + '">';
                  html += '<span class="dashicons ' + icon + ' astropress-ability-icon"></span>';
                  html += '<div>';
                  html += '<div class="astropress-ability-title">' + ab.label + ' <span class="astropress-badge" style="font-size:10px; padding:1px 6px;' + (isGranted ? 'background:#ecfdf5;color:#065f46;' : 'background:#fffbeb;color:#b45309;') + '">' + (isGranted ? 'Passed' : 'Missing Scope') + '</span></div>';
                  html += '<div class="astropress-ability-scope">' + ab.scope + '</div>';
                  html += '<div class="astropress-ability-desc">' + ab.description + '</div>';
                  html += '</div>';
                  html += '</div>';
                }
                html += '</div>';
                html += '</div>';
              }

              html += '<button type="button" class="button button-primary astropress-apply-cf-btn" data-token="' + encodeURIComponent(token) + '">Apply Discovered Settings to WordPress</button>';
              html += '</div>';

              $results.html(html);

              if (data.matched_account) {
                if ($('#r2_account_id').length) $('#r2_account_id').val(data.matched_account.id);
                if ($('#onboard_r2_account_id').length) $('#onboard_r2_account_id').val(data.matched_account.id);
              }
              if (data.matched_zone) {
                if ($('#astropress_cf_zone_id').length) $('#astropress_cf_zone_id').val(data.matched_zone.id);
                if ($('#onboard_cf_zone_id').length) $('#onboard_cf_zone_id').val(data.matched_zone.id);
                if ($('#onboard_r2_custom_domain').length && !$('#onboard_r2_custom_domain').val()) {
                  $('#onboard_r2_custom_domain').val('media.' + data.matched_zone.name);
                }
              }
              if (data.buckets && data.buckets.length > 0) {
                if ($('#r2_bucket').length && !$('#r2_bucket').val()) {
                  $('#r2_bucket').val(data.buckets[0]);
                }
                if ($('#onboard_r2_bucket_select').length) {
                  const $sel = $('#onboard_r2_bucket_select');
                  $sel.empty().append('<option value="">-- Choose Discovered Bucket --</option>');
                  data.buckets.forEach(function(b) {
                    $sel.append('<option value="' + b + '">' + b + '</option>');
                  });
                  $sel.val(data.buckets[0]).show();
                  $('#onboard_r2_bucket').hide();
                  $('#onboard-btn-toggle-bucket-input').show();
                }
              }
              if (data.pages_projects && data.pages_projects.length > 0) {
                const $pagesCard = $('#onboard-pages-sync-card');
                const $pagesSel = $('#onboard_cf_pages_project_select');
                if ($pagesCard.length) {
                  $pagesCard.slideDown(200);
                  if ($pagesSel.length) {
                    $pagesSel.empty().append('<option value="">-- Choose Pages Project --</option>');
                    data.pages_projects.forEach(function(p) {
                      $pagesSel.append('<option value="' + p.name + '">' + p.name + '</option>');
                    });
                    $pagesSel.show();
                    $pagesSel.val(data.pages_projects[0].name);
                    $('#onboard_cf_pages_project').val(data.pages_projects[0].name);
                  }
                }
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

    // handleQuickConnect('#astropress_quick_cf_token', '#astropress-quick-connect-results', '#astropress-quick-connect-btn');
    // handleQuickConnect('#astropress_quick_cf_token_r2', '#astropress-quick-connect-results-r2', '#astropress-quick-connect-btn-r2');
    handleQuickConnect('#onboard_cf_api_token', '#onboard-audit-results', '#onboard-quick-audit-btn');

    // ========================================================
    // SETUP WIZARD & MASKED CREDENTIAL INTERACTION
    // ========================================================

    // 1. Rotate Revalidation Secret Toggle & Drawer
    $(document).on('click', '#onboard-rotate-secret-btn, #onboard-change-secret-btn', function(e) {
      e.preventDefault();
      const $wrap = $('#onboard-secret-input-wrap');
      $wrap.slideToggle(150, function() {
        if ($wrap.is(':visible')) {
          $('#onboard_secret').focus();
        }
      });
    });

    $(document).on('click', '#onboard-cancel-rotate-btn, #onboard-cancel-change-secret', function(e) {
      e.preventDefault();
      $('#onboard-secret-input-wrap').slideUp(150);
      $('#onboard_secret').val('');
    });

    // 4. Permission Blueprint Recipe Box Toggle & Copy
    $('#onboard-toggle-blueprint-btn').on('click', function(e) {
      e.preventDefault();
      $('#onboard-blueprint-wrap').slideToggle(200);
    });

    $('#onboard-copy-blueprint-btn').on('click', function(e) {
      e.preventDefault();
      const recipe = $(this).data('recipe');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(recipe).then(function() {
          const $status = $('#onboard-blueprint-copy-status');
          $status.html('✓ Blueprint recipe copied to clipboard! Paste into your notes or token creation form.').fadeIn(150);
          setTimeout(function() { $status.fadeOut(400); }, 5000);
        });
      }
    });

    // 5. S3 Advanced Drawer Toggle
    $('#onboard-toggle-s3-drawer').on('click', function(e) {
      e.preventDefault();
      const $drawer = $('#onboard-s3-drawer-wrap');
      const $arrow = $('#onboard-s3-arrow');
      $drawer.slideToggle(200, function() {
        if ($drawer.is(':visible')) {
          $arrow.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
          $arrow.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
        }
      });
    });

    // 6. Deploy Cache Rule from Setup Wizard (Delegated)
    $(document).on('click', '#onboard-deploy-cache-rule-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const cdnDomain = $('#onboard_r2_custom_domain').val().trim();
      const token = $('#onboard_cf_api_token').val().trim();
      const zoneId = $('#onboard_cf_zone_id').val().trim();
      const $status = $('#onboard-cache-rule-status');

      if (!cdnDomain) {
        alert('Please enter a Public CDN Domain first (e.g. media.example.com).');
        return;
      }

      $btn.prop('disabled', true).text('Deploying Rule...');
      $status.html('<span style="color:#64748b;">Testing token permissions & deploying Edge rule...</span>').show();

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_deploy_cache_rule',
          cdn_domain: cdnDomain,
          cf_api_token: token,
          zone_id: zoneId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;">✓ ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">✗ ' + (res.data?.message || 'Deployment failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">✗ Network error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-cloud" style="vertical-align:-2px;"></span> Deploy 1-Year Cache Rule');
        }
      });
    });

    // 6b. Purge CDN Edge Cache from Setup Wizard (Delegated)
    $(document).on('click', '#onboard-purge-cdn-btn', function(e) {
      e.preventDefault();
      if (!confirm('Purge all cached CDN assets on Cloudflare Edge for this zone?')) {
        return;
      }
      const $btn = $(this);
      const token = $('#onboard_cf_api_token').val().trim();
      const zoneId = $('#onboard_cf_zone_id').val().trim();
      const $status = $('#onboard-cache-rule-status');

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Purging...');
      $status.html('<span style="color:#64748b;">Purging Cloudflare Edge cache...</span>').show();

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_purge_cf_zone',
          purge_everything: 1,
          cf_api_token: token,
          zone_id: zoneId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + (res.data?.message || 'CDN Edge cache purged successfully!') + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Purge failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Purge CDN Cache');
        }
      });
    });

    // 7. Setup Mode Selector (Full Architecture vs Modular)
    $('input[name="onboard_setup_mode"]').on('change', function() {
      const mode = $(this).val();
      $('.astropress-mode-card').removeClass('is-active').css({ 'border-color': '#e2e8f0', 'background': '#ffffff' });
      const $activeCard = $(this).closest('.astropress-mode-card');
      $activeCard.addClass('is-active').css({ 'border-color': '#2563eb', 'background': '#eff6ff' });

      if (mode === 'modular') {
        $('#onboard-modular-checklist').slideDown(200);
      } else {
        $('#onboard-modular-checklist').slideUp(200);
        $('#onboard-r2-section-wrap').slideDown(200);
      }
    });

    // Modular R2 Checkbox Toggle
    $('#onboard_enable_r2_toggle').on('change', function() {
      if ($(this).is(':checked')) {
        $('#onboard-r2-section-wrap').slideDown(200);
      } else {
        $('#onboard-r2-section-wrap').slideUp(200);
      }
    });

    // 8. Frontend URL Live Auto-Detection
    function detectFrontendUrl() {
      const url = $('#onboard_frontend_url').val().trim();
      const $badge = $('#onboard-frontend-detect-badge');
      const $btn = $('#onboard-detect-frontend-btn');

      if (!url || !url.startsWith('http')) {
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Pinging...');
      $badge.show().html('<div class="astropress-detect-pill is-loading"><span class="dashicons dashicons-update"></span> Pinging ' + url + ' & testing /api/revalidate...</div>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_detect_frontend',
          url: url,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            const d = res.data;
            let statusClass = d.reachable ? 'is-live' : 'is-error';
            let icon = d.reachable ? 'dashicons-yes' : 'dashicons-warning';

            let html = '<div class="astropress-detect-pill ' + statusClass + '">';
            html += '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
            html += '<div>';
            html += '<strong style="display:flex; align-items:center; gap:6px;"><span class="dashicons ' + icon + '"></span> ' + (d.is_astro ? 'Astro Frontend Confirmed' : (d.reachable ? 'Frontend Responding' : 'Connection Failed')) + '</strong>';
            html += '<div style="font-size:12px; margin-top:2px;">';
            html += 'HTTP ' + d.root_status + ' &bull; Latency: ' + d.latency_ms + 'ms &bull; Edge: ' + (d.is_cloudflare ? 'Cloudflare Active' : 'Direct Server');
            html += '</div>';
            html += '</div>';

            // Webhook route indicator
            if (d.webhook_reachable) {
              html += '<span class="astropress-badge" style="background:#ecfdf5; color:#065f46; font-size:11px;">✓ Webhook /api/revalidate Active</span>';
            } else {
              html += '<span class="astropress-badge" style="background:#fffbeb; color:#b45309; font-size:11px;">ℹ Webhook Route Not Mounted (HTTP ' + d.webhook_status + ')</span>';
            }
            html += '</div>';
            html += '</div>';

            $badge.html(html);
          } else {
            $badge.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Could not ping frontend.') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $badge.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> Ping request error: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Ping & Detect Frontend');
        }
      });
    }

    $(document).on('click', '#onboard-detect-frontend-btn', function(e) {
      e.preventDefault();
      detectFrontendUrl();
    });

    $('#onboard_frontend_url').on('blur', function() {
      if ($(this).val().trim().length > 7 && !$('#onboard-frontend-detect-badge').is(':visible')) {
        detectFrontendUrl();
      }
    });

    // 8b. Test Webhook Connection & Handshake Button (Test & Rotate pattern)
    $(document).on('click', '#onboard-test-handshake-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#onboard-handshake-status');
      const $badge = $('#onboard-secret-badge');
      const $icon = $('#onboard-secret-status-icon');
      const frontendUrl = $('#onboard_frontend_url').val()?.trim();
      const newSecret = $('#onboard_secret').val()?.trim() || '';

      if (!frontendUrl) {
        $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Please enter the Frontend URL above first.</div>').slideDown(150);
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Testing...');
      $status.html('<div style="padding:8px 12px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; color:#0369a1; font-size:12px;"><span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Testing handshake with ' + frontendUrl + '/api/revalidate...</div>').slideDown(150);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_test_key',
          frontend_url: frontendUrl,
          secret: newSecret,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            if ($badge.length) $badge.css({'background': '#ecfdf5', 'color': '#065f46', 'border-color': '#a7f3d0'}).text('Verified Active');
            if ($icon.length) $icon.removeClass('dashicons-warning dashicons-dismiss').addClass('dashicons-shield-alt').css('color', '#059669');
            $status.html('<div style="padding:8px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> <strong>Verified:</strong> ' + res.data.message + ' (' + res.data.latency_ms + 'ms latency)</div>');
          } else {
            const code = res.data?.code || 0;
            if ($badge.length) $badge.css({'background': '#fef2f2', 'color': '#991b1b', 'border-color': '#fecaca'}).text('Failed (HTTP ' + code + ')');
            if ($icon.length) $icon.removeClass('dashicons-shield-alt').addClass('dashicons-warning').css('color', '#dc2626');
            $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> <strong>Authentication Failed (HTTP ' + code + '):</strong> ' + (res.data?.message || 'Unauthorized') + ' Opening Rotate drawer below...</div>');
            // Auto open rotate drawer so user can rotate easily!
            const $wrap = $('#onboard-secret-input-wrap');
            if (!$wrap.is(':visible')) {
              $wrap.slideDown(200, function() {
                $('#onboard_secret').focus();
              });
            }
          }
        },
        error: function(xhr, status, err) {
          $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Network request failed: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align:-2px;"></span> Test Connection');
        }
      });
    });

    // 9. Zero-Touch Cloudflare Pages Secret Sync
    $(document).on('change', '#onboard_cf_pages_project_select', function() {
      const val = $(this).val();
      if (val) {
        $('#onboard_cf_pages_project').val(val);
      }
    });

    $(document).on('click', '#onboard-sync-pages-env-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const projName = ($('#onboard_cf_pages_project').val() || $('#onboard_cf_pages_project_select').val() || '').trim();
      const token = $('#onboard_cf_api_token').val().trim();
      const accountId = $('#onboard_r2_account_id').val().trim();
      const $status = $('#onboard-pages-sync-status');

      if (!projName) {
        alert('Please specify your Cloudflare Pages project name first.');
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Syncing...');
      $status.html('<span style="color:#64748b;">Injecting REVALIDATE_SECRET & WP_API_URL via Cloudflare API...</span>').show();

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_sync_pages_env',
          project_name: projName,
          account_id: accountId,
          token: token,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Sync failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> Push Secrets (1-Click)');
        }
      });
    });

    // 10. Public CDN Domain Audit
    $(document).on('click', '#onboard-audit-cdn-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const cdnDomain = $('#onboard_r2_custom_domain').val().trim();
      const token = $('#onboard_cf_api_token').val().trim();
      const zoneId = $('#onboard_cf_zone_id').val().trim();
      const accountId = $('#onboard_r2_account_id').val().trim();
      const bucket = ($('#onboard_r2_bucket').val() || $('#onboard_r2_bucket_select').val() || '').trim();
      const $box = $('#onboard-cdn-audit-box');

      if (!cdnDomain) {
        alert('Please enter a Public CDN Domain first (e.g. media.example.com).');
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Auditing...');
      $box.show().html('<div class="astropress-cdn-audit-loading"><span class="dashicons dashicons-update"></span> Auditing DNS records, R2 bucket domain attachment & Edge cache rules...</div>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_audit_cdn_domain',
          custom_domain: cdnDomain,
          token: token,
          zone_id: zoneId,
          account_id: accountId,
          bucket_name: bucket,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            const d = res.data;
            let html = '<div class="astropress-cdn-audit-wrap">';
            html += '<div style="font-weight:700; font-size:13px; color:#1e293b; margin-bottom:8px;">CDN Domain Audit Report for <code>' + d.host + '</code>:</div>';
            html += '<div class="astropress-cdn-audit-grid">';

            // DNS Status
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>DNS Routing:</strong> ' + (d.dns_found ? (d.dns_proxied ? 'Proxied via Cloudflare Edge (Orange Cloud)' : 'DNS Found (Unproxied)') : 'No DNS Record Found') + '</div>';
            html += '<span class="astropress-badge" style="' + (d.dns_proxied ? 'background:#ecfdf5;color:#065f46;' : 'background:#fffbeb;color:#b45309;') + '">' + (d.dns_proxied ? 'Optimal' : (d.dns_found ? 'DNS OK' : 'Pending')) + '</span>';
            html += '</div>';

            // R2 Custom Domain Attachment
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>R2 Bucket Attachment:</strong> ' + (d.r2_attached ? 'Domain Attached to Bucket (' + d.r2_status + ')' : 'Domain Not Attached to R2 Bucket') + '</div>';
            if (d.r2_attached) {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">Attached</span>';
            } else {
              html += '<button type="button" class="button button-primary button-small" id="onboard-attach-r2-domain-btn" data-host="' + d.host + '" data-bucket="' + bucket + '">Attach Domain to R2 (1-Click)</button>';
            }
            html += '</div>';

            // Cache Rule Status
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>Edge Cache Rule:</strong> ' + (d.rule_desc || 'No cache rule') + '</div>';
            if (d.rule_status === 'optimal') {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">1-Year Cache Everything Active</span>';
            } else {
              html += '<button type="button" class="button button-secondary button-small" id="onboard-deploy-cache-rule-btn">Deploy / Upgrade Cache Rule</button>';
            }
            html += '</div>';

            // Edge Cache Purge Row
            html += '<div class="astropress-cdn-audit-row">';
            html += '<div><strong>CDN Edge Cache:</strong> Instant Cloudflare Zone & CDN Purge</div>';
            html += '<button type="button" class="button button-secondary button-small" id="onboard-purge-cdn-btn"><span class="dashicons dashicons-trash" style="vertical-align:-2px;font-size:13px;width:13px;height:13px;"></span> Purge CDN Cache</button>';
            html += '</div>';

            html += '</div>';
            html += '<div id="onboard-attach-status" style="margin-top:6px; font-size:12px;"></div>';
            html += '</div>';

            $box.html(html);
          } else {
            $box.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Audit failed.') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $box.html('<div class="astropress-detect-pill is-error"><span class="dashicons dashicons-warning"></span> Audit request error: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> Audit CDN & Rules');
        }
      });
    });

    // 11. 1-Click Attach Domain to R2
    $(document).on('click', '#onboard-attach-r2-domain-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const host = $btn.data('host');
      const bucket = $btn.data('bucket') || ($('#onboard_r2_bucket').val() || $('#onboard_r2_bucket_select').val() || '').trim();
      const token = $('#onboard_cf_api_token').val().trim();
      const zoneId = $('#onboard_cf_zone_id').val().trim();
      const accountId = $('#onboard_r2_account_id').val().trim();
      const $status = $('#onboard-attach-status');

      $btn.prop('disabled', true).text('Attaching...');
      $status.html('<span style="color:#64748b;">Attaching domain ' + host + ' to bucket ' + bucket + ' via Cloudflare API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_attach_r2_custom_domain',
          custom_domain: host,
          bucket_name: bucket,
          zone_id: zoneId,
          account_id: accountId,
          token: token,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + res.data.message + '</span>');
            setTimeout(function() {
              $('#onboard-audit-cdn-btn').trigger('click');
            }, 1500);
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Attachment failed.') + '</span>');
            $btn.prop('disabled', false).text('Attach Domain to R2 (1-Click)');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Request error: ' + err + '</span>');
          $btn.prop('disabled', false).text('Attach Domain to R2 (1-Click)');
        }
      });
    });

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

    // ========================================================
    // ASTROPRESS v2.0 JAVASCRIPT PIPELINE
    // ========================================================

    // 1. Setup Wizard: Media Mode Toggle
    $('#onboard_media_mode').on('change', function() {
      if ($(this).val() === 'r2') {
        $('#onboard-r2-fields-wrap').slideDown(200);
      } else {
        $('#onboard-r2-fields-wrap').slideUp(200);
      }
    });

    // 2. Setup Wizard: Frontend URL Backend Collision Validator
    function validateFrontendUrl() {
      const val = ($('#onboard_frontend_url').val() || '').trim();
      const $warn = $('#onboard-frontend-collision-warning');
      const $submit = $('#onboard-submit-btn');

      if (!val) {
        $warn.hide();
        $submit.prop('disabled', false);
        return;
      }

      try {
        const parsed = new URL(val);
        if (parsed.hostname.toLowerCase() === window.location.hostname.toLowerCase()) {
          $warn.show();
          $submit.prop('disabled', true);
        } else {
          $warn.hide();
          $submit.prop('disabled', false);
        }
      } catch (e) {
        $warn.hide();
        $submit.prop('disabled', false);
      }
    }

    $('#onboard_frontend_url').on('input blur change', validateFrontendUrl);
    validateFrontendUrl();

    // 3. Setup Wizard: Quick Audit & Auto-Discover
    $('#onboard-quick-audit-btn').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const token = $('#onboard_cf_api_token').val().trim();
      const $results = $('#onboard-audit-results');

      $btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> Auditing...');
      $results.show().html('<div style="color:#64748b; font-size:12px;"><span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> Contacting Cloudflare API to audit token and discover resources...</div>');

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
            const d = res.data;
            if (d.matched_account && d.matched_account.id) {
              $('#onboard_r2_account_id').val(d.matched_account.id);
            }
            if (d.matched_zone && d.matched_zone.id) {
              $('#onboard_cf_zone_id').val(d.matched_zone.id);
            }

            // Populate discovered buckets
            if (d.buckets && d.buckets.length > 0) {
              const $sel = $('#onboard_r2_bucket_select');
              $sel.empty().append('<option value="">-- Discovered Buckets --</option>');
              d.buckets.forEach(function(b) {
                $sel.append('<option value="' + b + '">' + b + '</option>');
              });
              $sel.val(d.buckets[0]).show();
              $('#onboard_r2_bucket').val(d.buckets[0]);
            }

            let auditHtml = '<div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px; margin-top:10px;">';
            auditHtml += '<div style="font-weight:700; color:#166534; font-size:13px; margin-bottom:6px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Cloudflare Scoped Token Verified!</div>';
            auditHtml += '<div style="font-size:12px; color:#15803d; line-height:1.5;">';
            auditHtml += '<strong>Zone:</strong> ' + ((d.matched_zone && d.matched_zone.name) ? d.matched_zone.name : 'Detected') + ' (<code>' + ((d.matched_zone && d.matched_zone.id) ? d.matched_zone.id.substring(0, 10) + '••••' : 'Found') + '</code>)<br />';
            auditHtml += '<strong>Account:</strong> ' + ((d.matched_account && d.matched_account.name) ? d.matched_account.name : 'Detected') + '<br />';
            auditHtml += '<strong>R2 Buckets:</strong> ' + (d.buckets ? d.buckets.length : 0) + ' bucket(s) discovered.<br />';
            auditHtml += '<strong>Direct Cache Purge:</strong> ' + (d.can_purge ? '<span style="color:#059669; font-weight:600;">Authorized</span>' : '<span style="color:#dc2626;">Missing Scope</span>') + '<br />';
            auditHtml += '<strong>Modern Cache Rules:</strong> ' + (d.can_cache_rules ? '<span style="color:#059669; font-weight:600;">Authorized</span>' : '<span style="color:#dc2626;">Missing Scope</span>');
            auditHtml += '</div></div>';
            $results.html(auditHtml);
          } else {
            $results.html('<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px; color:#991b1b; font-size:12px;"><strong>Verification Failed:</strong> ' + (res.data ? res.data.message : 'Invalid API Token') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $results.html('<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px; color:#991b1b; font-size:12px;">Network error: ' + err + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Auto-Discover & Verify Token');
        }
      });
    });

    // 4. Setup Wizard: Blueprint Toggle
    $('#onboard-toggle-blueprint-btn').on('click', function(e) {
      e.preventDefault();
      $('#onboard-blueprint-wrap').slideToggle(200);
    });

    // 5. Cloudflare Ruleset Engine: Load Active Cache Rules Table
    function loadRulesetCacheRules() {
      const $container = $('#astropress-rules-table-container');
      if (!$container.length) return;

      $container.html('<p style="color:#64748b; font-size:13px;"><span class="spinner is-active" style="float:none; margin:0 6px 0 0;"></span> Fetching active cache rules from Cloudflare Ruleset API...</p>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_get_cache_rules',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            const rules = res.data.rules || [];
            if (!rules.length) {
              $container.html('<div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; padding:16px; text-align:center; color:#64748b; font-size:13px;">No cache rules deployed in Ruleset Engine yet. Deploy one of the 1-Click presets above to activate edge caching.</div>');
              return;
            }

            let tbl = '<table class="widefat striped" style="margin-top:6px;">';
            tbl += '<thead><tr>';
            tbl += '<th style="width:70px;">Active</th>';
            tbl += '<th>Description</th>';
            tbl += '<th>Expression</th>';
            tbl += '<th style="width:120px;">Edge TTL</th>';
            tbl += '<th style="width:120px;">Browser TTL</th>';
            tbl += '<th style="width:80px; text-align:right;">Actions</th>';
            tbl += '</tr></thead><tbody>';

            rules.forEach(function(r) {
              const isEnabled = r.enabled !== false;
              let edgeTtl = 'Default';
              if (r.action_parameters && r.action_parameters.edge_ttl) {
                edgeTtl = r.action_parameters.edge_ttl.default ? (r.action_parameters.edge_ttl.default + 's') : (r.action_parameters.edge_ttl.mode || 'Default');
              }
              let browserTtl = 'Default';
              if (r.action_parameters && r.action_parameters.browser_ttl) {
                browserTtl = r.action_parameters.browser_ttl.default ? (r.action_parameters.browser_ttl.default + 's') : (r.action_parameters.browser_ttl.mode || 'Default');
              }

              tbl += '<tr>';
              tbl += '<td><input type="checkbox" class="astropress-toggle-rule-switch" data-rule-id="' + r.id + '" ' + (isEnabled ? 'checked' : '') + ' /></td>';
              tbl += '<td><strong>' + (r.description || 'Custom Rule') + '</strong></td>';
              tbl += '<td><code>' + (r.expression || '') + '</code></td>';
              tbl += '<td><span class="astropress-badge" style="background:#eff6ff; color:#1d4ed8;">' + edgeTtl + '</span></td>';
              tbl += '<td><span class="astropress-badge" style="background:#f0fdf4; color:#166534;">' + browserTtl + '</span></td>';
              tbl += '<td style="text-align:right;"><button type="button" class="button button-link-delete astropress-delete-rule-btn" data-rule-id="' + r.id + '"><span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span></button></td>';
              tbl += '</tr>';
            });

            tbl += '</tbody></table>';
            $container.html(tbl);
          } else {
            $container.html('<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px; color:#991b1b; font-size:13px;"><strong>Failed to load rules:</strong> ' + (res.data ? res.data.message : 'Error') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $container.html('<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px; color:#991b1b; font-size:13px;">Error contacting server: ' + err + '</div>');
        }
      });
    }

    if ($('#astropress-rules-table-container').length) {
      loadRulesetCacheRules();
    }

    $('#astropress-refresh-cache-rules-btn').on('click', function(e) {
      e.preventDefault();
      loadRulesetCacheRules();
    });

    // 6. Deploy Preset Cache Rule
    $(document).on('click', '.astropress-deploy-preset-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const preset = $btn.data('preset');
      const $status = $('#astropress-preset-deploy-status');

      $btn.prop('disabled', true).text('Deploying...');
      $status.html('<span style="color:#64748b;"><span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> Deploying preset "' + preset + '" to Cloudflare Ruleset API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_deploy_cache_rule_preset',
          preset: preset,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + res.data.message + '</span>');
            loadRulesetCacheRules();
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data ? res.data.message : 'Deployment failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus" style="vertical-align:-2px;"></span> Deploy Preset');
        }
      });
    });

    // 7. Toggle Cache Rule Switch
    $(document).on('change', '.astropress-toggle-rule-switch', function() {
      const $switch = $(this);
      const ruleId = $switch.data('rule-id');
      const isEnabled = $switch.is(':checked') ? 1 : 0;

      $switch.prop('disabled', true);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_toggle_cache_rule',
          rule_id: ruleId,
          enabled: isEnabled,
          nonce: nonce,
        },
        success: function(res) {
          if (!res.success) {
            alert(res.data ? res.data.message : 'Failed to toggle rule.');
            $switch.prop('checked', !isEnabled);
          }
        },
        error: function(xhr, status, err) {
          alert('Error toggling rule: ' + err);
          $switch.prop('checked', !isEnabled);
        },
        complete: function() {
          $switch.prop('disabled', false);
        }
      });
    });

    // 8. Delete Cache Rule
    $(document).on('click', '.astropress-delete-rule-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const ruleId = $btn.data('rule-id');

      if (!confirm('Are you sure you want to delete this cache rule from Cloudflare Ruleset Engine?')) {
        return;
      }

      $btn.prop('disabled', true);

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_delete_cache_rule',
          rule_id: ruleId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            loadRulesetCacheRules();
          } else {
            alert(res.data ? res.data.message : 'Failed to delete rule.');
            $btn.prop('disabled', false);
          }
        },
        error: function(xhr, status, err) {
          alert('Error deleting rule: ' + err);
          $btn.prop('disabled', false);
        }
      });
    });

    // 9. Direct Purge All Zone Cache
    $('#astropress-purge-all-zone-btn').on('click', function(e) {
      e.preventDefault();
      if (!confirm('Are you sure you want to purge the ENTIRE Cloudflare Zone edge cache? This will clear all cached HTML, assets, and images globally.')) {
        return;
      }

      const $btn = $(this);
      $btn.prop('disabled', true).text('Purging All...');

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
          alert(res.data ? res.data.message : 'Zone cache purged successfully.');
        },
        error: function(xhr, status, err) {
          alert('Purge error: ' + err);
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Purge All Zone Cache');
        }
      });
    });

    // 10. Direct Purge Specific URLs
    $('#astropress-purge-urls-btn').on('click', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const raw = ($('#astropress-purge-urls-input').val() || '').trim();
      const $status = $('#astropress-direct-purge-status');

      if (!raw) {
        alert('Please enter at least one URL or path to purge.');
        return;
      }

      const urls = raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      $btn.prop('disabled', true).text('Purging...');
      $status.html('<span style="color:#64748b;">Sending purge request to Cloudflare API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_purge_cf_zone',
          urls: urls,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + res.data.message + '</span>');
            $('#astropress-purge-urls-input').val('');
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data ? res.data.message : 'Purge failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Request error: ' + err + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Purge URLs');
        }
      });
    });

    // 11. Danger Zone: Reset Connection
    $('#astropress-reset-connection-btn').on('click', function(e) {
      e.preventDefault();
      if (!confirm('DANGER: Are you sure you want to disconnect AstroPress and clear all stored credentials? This will lock all dashboard tabs and return to the Setup Wizard.')) {
        return;
      }

      const $btn = $(this);
      const $status = $('#astropress-reset-status');

      $btn.prop('disabled', true).text('Resetting Connection...');
      $status.html('<span style="color:#dc2626;"><span class="spinner is-active" style="float:none; margin:0 4px 0 0;"></span> Purging credentials and resetting connection...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_reset_connection',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + res.data.message + '</span>');
            setTimeout(function() {
              window.location.href = (res.data && res.data.redirect) ? res.data.redirect : (window.location.pathname + '?page=astropress&tab=onboarding');
            }, 1000);
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;">Reset failed: ' + (res.data ? res.data.message : '') + '</span>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Disconnect & Reset AstroPress');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Network error: ' + err + '</span>');
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Disconnect & Reset AstroPress');
        }
      });
    });

  });
})(jQuery);

