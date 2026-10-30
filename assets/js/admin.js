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

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }


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
    // Clean, flat single-tier navigation (no category filtering bloat)
    // All 7 tabs render directly with full visibility and zero DOM manipulation.

    // ========================================================
    // Retention Period Controls (Slider / Select / Presets)
    // ========================================================
    $(document).on('input change', '.astropress-retention-slider', function() {
      const $slider = $(this);
      const val = parseInt($slider.val(), 10) || 30;
      const $wrap = $slider.closest('.astropress-retention-control');
      $wrap.find('.astropress-retention-badge, .astropress-retention-value').text(val + ' Days');
      $wrap.find('.astropress-retention-select').val(val);
      $wrap.find('.astropress-retention-preset-btn').each(function() {
        $(this).toggleClass('active', String($(this).data('days')) === String(val));
      });
    });

    $(document).on('change', '.astropress-retention-select', function() {
      const $select = $(this);
      const val = parseInt($select.val(), 10) || 30;
      const $wrap = $select.closest('.astropress-retention-control');
      $wrap.find('.astropress-retention-slider').val(val).trigger('input');
    });

    $(document).on('click', '.astropress-retention-preset-btn', function(e) {
      e.preventDefault();
      const days = $(this).data('days');
      if (days !== undefined) {
        const $wrap = $(this).closest('.astropress-retention-control');
        $wrap.find('.astropress-retention-slider').val(days).trigger('input');
      }
    });

    // ========================================================
    // 1-Click Database & Legacy Cleanup Tool
    // ========================================================
    $(document).on('click', '.astropress-cleanup-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $card = $btn.closest('.astropress-legacy-cleanup-card');
      const $status = $card.find('.astropress-cleanup-status');
      const $progressBox = $card.find('.astropress-cleanup-progress-box');
      const $progressFill = $card.find('.astropress-cleanup-progress-fill');
      const $cleanCount = $card.find('.astropress-cleanup-cleanable-count');

      if ($btn.hasClass('updating-message') || $btn.prop('disabled')) {
        return;
      }

      if (!confirm('Proceed with safe database and orphaned index cleanup? This cleans stale entries and sync markers while preserving all active Cloudflare R2 assets.')) {
        return;
      }

      $btn.prop('disabled', true).addClass('updating-message');
      $progressBox.slideDown(150);
      $progressFill.css('width', '25%');
      $status.html('<span class="dashicons dashicons-update spin"></span> Optimizing database tables and removing stale records...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_bulk_clean_verified_local',
          nonce: nonce,
          batch_size: 25,
        },
        success: function(res) {
          $progressFill.css('width', '100%');
          if (res.success) {
            $status.html('<span style="color:#15803d; font-weight:600;"><span class="dashicons dashicons-yes-alt"></span> Cleanup completed successfully! Database optimized.</span>');
            if ($cleanCount.length) {
              $cleanCount.text('0');
            }
          } else {
            $status.html('<span style="color:#b91c1c; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Cleanup completed with notices.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $progressFill.css('width', '100%').css('background', '#ef4444');
          $status.html('<span style="color:#b91c1c; font-weight:600;">Request error: ' + escapeHtml(err) + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).removeClass('updating-message');
        }
      });
    });

    // 1-Click Legacy Database Options & Postmeta Purge
    $(document).on('click', '#astropress-btn-clean-legacy', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-legacy-clean-status');

      if ($btn.hasClass('updating-message') || $btn.prop('disabled')) {
        return;
      }

      if (!confirm('Proceed with safe cleanup of legacy database options and postmeta (topnepali_*, r2g_*)? Modern AstroPress records will be preserved.')) {
        return;
      }

      $btn.prop('disabled', true).addClass('updating-message');
      $status.html('<span class="dashicons dashicons-update spin"></span> Scanning and purging legacy database options...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_clean_legacy_database',
          nonce: nonce,
        },
        success: function(res) {
          $btn.prop('disabled', false).removeClass('updating-message');
          if (res.success && res.data) {
            const d = res.data;
            $status.html('<span style="color:#15803d; font-weight:600;"><span class="dashicons dashicons-yes-alt"></span> ' +
              (d.message || ('Purged ' + (d.deleted_options || 0) + ' legacy options, ' + (d.deleted_postmeta || 0) + ' postmeta rows.')) + '</span>');
          } else {
            $status.html('<span style="color:#b91c1c; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + (res.data?.message || 'Cleanup encountered notices.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $btn.prop('disabled', false).removeClass('updating-message');
          $status.html('<span style="color:#b91c1c; font-weight:600;">Request error: ' + escapeHtml(err) + '</span>');
        }
      });
    });

    // ========================================================
    // 3. Cloudflare R2 Bucket Discovery, Custom Domain & CNAME Setup
    // Single source of truth is Setup Wizard (#onboard-*)
    // ========================================================

    // Auto-detect attached custom domain for a selected bucket
    function detectBucketCustomDomain(bucket, $domainInput, $statusDiv) {
      if (!bucket) return;
      $domainInput = ($domainInput && $domainInput.length) ? $domainInput : $('#onboard_r2_custom_domain');
      $statusDiv = ($statusDiv && $statusDiv.length) ? $statusDiv : $('#onboard-cdn-domain-status');

      if ($statusDiv.length) {
        $statusDiv.html('<div style="font-size:12px; color:#0369a1; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-update spin"></span> Checking Cloudflare R2 custom domain for <code>' + escapeHtml(bucket) + '</code>...</div>').show();
      }

      const accountId = $('#onboard_r2_account_id').val() || '';
      const token = $('#onboard_cf_api_token').val() || '';

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_detect_r2_custom_domain',
          bucket_name: bucket,
          account_id: accountId,
          token: token,
          nonce: nonce
        },
        success: function(res) {
          // Resolve root domain for auto-suggestions
          let rootDomain = '';
          if (config.allowedRoots && config.allowedRoots.length) {
            rootDomain = config.allowedRoots[0];
          } else if (config.frontendUrl) {
            try {
              rootDomain = new URL(config.frontendUrl).hostname;
            } catch (e) {
              rootDomain = window.location.hostname;
            }
          } else {
            rootDomain = window.location.hostname || 'example.com';
          }
          rootDomain = rootDomain.replace(/^https?:\/\//i, '').split(':')[0].replace(/^www\./i, '');

          if (res.success && res.data && res.data.primary) {
            const detectedDomain = res.data.primary;
            if ($domainInput.length) {
              $domainInput.val(detectedDomain).removeData('auto-suggested');
            }
            if ($statusDiv.length) {
              $statusDiv.html(
                '<div style="padding:6px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px; display:flex; align-items:center; gap:6px;">' +
                '<span class="dashicons dashicons-yes-alt" style="vertical-align:-2px; color:#16a34a;"></span> ' +
                'Attached to Cloudflare R2: <strong>' + escapeHtml(detectedDomain) + '</strong> (Active)' +
                '</div>'
              ).show();
            }
            if ($('#r2-active-domain-label').length) {
              $('#r2-active-domain-label').text(detectedDomain);
            }
          } else if (res.success) {
            // Cloudflare API query succeeded, but no custom domain attached to bucket yet
            const suggested = 'media.' + rootDomain;
            if ($domainInput.length) {
              $domainInput.val(suggested).data('auto-suggested', true);
            }
            if ($statusDiv.length) {
              $statusDiv.html(
                '<div style="padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; color:#475569; font-size:12px;">' +
                '<span class="dashicons dashicons-info" style="vertical-align:-2px; color:#0284c7;"></span> ' +
                'No custom domain attached to bucket <code>' + escapeHtml(bucket) + '</code> yet. ' +
                '<strong>' + escapeHtml(suggested) + '</strong> has been auto-suggested. Click <strong>Attach &amp; Setup CNAME</strong> to link it.' +
                '</div>'
              ).show();
            }
          } else {
            // API or authentication error
            const errMsg = res.data?.message || 'Could not query custom domains for this bucket.';
            if ($statusDiv.length) {
              $statusDiv.html(
                '<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#991b1b; font-size:12px;">' +
                '<span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> ' +
                escapeHtml(errMsg) +
                '</div>'
              ).show();
            }
          }
        },
        error: function(xhr, status, err) {
          if ($statusDiv.length) {
            $statusDiv.html(
              '<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#991b1b; font-size:12px;">' +
              '<span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' +
              'Custom domain query failed: ' + escapeHtml(err) +
              '</div>'
            ).show();
          }
        }
      });
    }

    // Unified Bucket Discovery Handler for Setup Wizard
    function handleFetchBuckets($btn, $status, $selectElement, $domainInput, $domainStatus) {
      $btn.prop('disabled', true).text('Discovering...');
      $status.html('<span style="color:#64748b;"><span class="dashicons dashicons-update spin"></span> Connecting to Cloudflare R2 API...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_fetch_buckets',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data?.buckets && res.data.buckets.length > 0) {
            const buckets = res.data.buckets;
            const currentVal = $selectElement.val() || '';
            $selectElement.empty();

            let matched = false;
            buckets.forEach(function(b) {
              const isSelected = (b === currentVal);
              if (isSelected) matched = true;
              $selectElement.append('<option value="' + escapeHtml(b) + '" ' + (isSelected ? 'selected' : '') + '>' + escapeHtml(b) + (isSelected ? ' (Selected)' : '') + '</option>');
            });

            if (!matched && buckets.length > 0) {
              $selectElement.val(buckets[0]);
            }

            const chosen = $selectElement.val();
            $status.html('<span style="color:#059669;font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Discovered ' + buckets.length + ' bucket(s). Active: <code>' + escapeHtml(chosen) + '</code></span>');

            if ($('#r2-active-bucket-label').length && chosen) {
              $('#r2-active-bucket-label').text(chosen);
            }

            // Auto-detect custom domain for chosen bucket
            detectBucketCustomDomain(chosen, $domainInput, $domainStatus);
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + (res.data?.message || 'No R2 buckets found. Create a bucket below.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Discovery failed: ' + escapeHtml(err) + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Discover Buckets');
        }
      });
    }

    // Discover in Onboarding Wizard
    $('#onboard-fetch-buckets').on('click', function(e) {
      e.preventDefault();
      handleFetchBuckets(
        $(this),
        $('#onboard-r2-status'),
        $('#onboard_r2_bucket'),
        $('#onboard_r2_custom_domain'),
        $('#onboard-cdn-domain-status')
      );
    });

    // Bucket Change & Input: Auto-detect domain in Setup Wizard
    $(document).on('change input', '#onboard_r2_bucket', function() {
      const val = $(this).val();
      if (val) {
        detectBucketCustomDomain(val, $('#onboard_r2_custom_domain'), $('#onboard-cdn-domain-status'));
      }
    });

    // Auto-detect on page load if bucket is already populated in Setup Wizard
    if ($('#onboard_r2_bucket').length && $('#onboard_r2_bucket').val()) {
      detectBucketCustomDomain($('#onboard_r2_bucket').val(), $('#onboard_r2_custom_domain'), $('#onboard-cdn-domain-status'));
    }

    // Create Bucket Toggle (Onboarding Wizard)
    $(document).on('click', '#onboard-btn-show-create-bucket', function(e) {
      e.preventDefault();
      const $wrap = $('#onboard-create-bucket-wrap');
      const $input = $('#onboard_new_bucket_name');
      $wrap.slideToggle(150, function() {
        if ($(this).is(':visible')) {
          $input.focus();
        }
      });
    });

    $(document).on('click', '#onboard-btn-cancel-create-bucket', function(e) {
      e.preventDefault();
      $('#onboard-create-bucket-wrap').slideUp(150);
      $('#onboard_new_bucket_name').val('');
      $('#onboard-create-bucket-status').empty();
    });

    // Confirm Create Bucket in Wizard
    $(document).on('click', '#onboard-btn-confirm-create-bucket', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $input = $('#onboard_new_bucket_name');
      const $status = $('#onboard-create-bucket-status');
      const $wrap = $('#onboard-create-bucket-wrap');
      const $bucketSelect = $('#onboard_r2_bucket');
      const $domainInput = $('#onboard_r2_custom_domain');
      const $domainStatus = $('#onboard-cdn-domain-status');
      const bucketName = $input.val()?.trim().toLowerCase();

      if (!bucketName) {
        $status.html('<span style="color:#dc2626;font-weight:600;">Please enter a bucket name.</span>');
        return;
      }

      $btn.prop('disabled', true).text('Creating bucket...');
      $status.html('<span style="color:#64748b;"><span class="dashicons dashicons-update spin"></span> Creating bucket on Cloudflare R2...</span>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_create_bucket',
          bucket_name: bucketName,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#059669;font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data.message) + '</span>');
            const created = res.data.bucket || bucketName;
            $bucketSelect.append('<option value="' + escapeHtml(created) + '" selected>' + escapeHtml(created) + ' (New)</option>');
            $bucketSelect.val(created);

            if ($('#r2-active-bucket-label').length) {
              $('#r2-active-bucket-label').text(created);
            }

            detectBucketCustomDomain(created, $domainInput, $domainStatus);

            setTimeout(function() {
              $wrap.slideUp(200);
            }, 1800);
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || 'Bucket creation failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">Request error: ' + escapeHtml(err) + '</span>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Create & Select');
        }
      });
    });

    // Auto-detect CDN domain button in Setup Wizard
    $(document).on('click', '#onboard-detect-cdn-btn', function(e) {
      e.preventDefault();
      const bucket = $('#onboard_r2_bucket').val()?.trim();
      if (!bucket) {
        $('#onboard-cdn-domain-status').html('<div style="font-size:12px; color:#dc2626;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Please select an R2 bucket first.</div>');
        return;
      }
      detectBucketCustomDomain(bucket, $('#onboard_r2_custom_domain'), $('#onboard-cdn-domain-status'));
    });

    // Attach & Setup CNAME button (Setup Wizard)
    $(document).on('click', '#onboard-attach-cname-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $input = $('#onboard_r2_custom_domain');
      const $status = $('#onboard-cdn-domain-status');
      const bucket = $('#onboard_r2_bucket').val()?.trim();
      const zoneId = $('#onboard_cf_zone_id').val() || '';
      const accountId = $('#onboard_r2_account_id').val() || '';
      const token = $('#onboard_cf_api_token').val() || '';
      let host = $input.val()?.trim() || '';
      if (host) {
        host = host.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
      }

      if (!bucket) {
        $status.html('<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:12px; color:#dc2626;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Please select an R2 bucket first.</div>').show();
        return;
      }
      if (!host) {
        $status.html('<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; font-size:12px; color:#dc2626;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Please enter your desired CDN domain (e.g. <code>media.' + (config.allowedRoots?.[0] || 'example.com') + '</code>).</div>').show();
        $input.focus();
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Attaching...');
      $status.html('<div style="font-size:12px; color:#0369a1; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-update spin"></span> Linking custom domain <strong>' + escapeHtml(host) + '</strong> to bucket <strong>' + escapeHtml(bucket) + '</strong> and creating proxied DNS CNAME...</div>').show();

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
          nonce: nonce
        },
        success: function(res) {
          if (res.success) {
            $input.val(host);
            if ($('#r2-active-domain-label').length) {
              $('#r2-active-domain-label').text(host);
            }
            $status.html(
              '<div style="padding:8px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px; display:flex; align-items:center; gap:8px;">' +
              '<span class="dashicons dashicons-yes-alt" style="vertical-align:-2px; color:#16a34a;"></span> ' +
              '<strong>Custom Domain Active:</strong> ' + escapeHtml(res.data.message || (host + ' linked successfully to ' + bucket)) +
              '</div>'
            ).show();
            // Auto trigger rule deployment if button present
            const $deployBtn = $('#onboard-deploy-cache-rule-btn');
            if ($deployBtn.length) {
              $deployBtn.trigger('click');
            }
          } else {
            $status.html(
              '<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:8px;">' +
              '<span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> ' +
              '<span>' + escapeHtml(res.data?.message || 'Attachment failed.') + '</span>' +
              '</div>'
            ).show();
          }
        },
        error: function(xhr, status, err) {
          $status.html(
            '<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:8px;">' +
            '<span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' +
            '<span>Request failed: ' + escapeHtml(err) + '</span>' +
            '</div>'
          ).show();
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-links" style="vertical-align:-2px;"></span> Attach & Setup CNAME');
        }
      });
    });

    // Client-side domain validation for CDN Domain input in Setup Wizard
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
          $hintContainer.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> CDN Domain must be a subdomain of ' + allowedRoots.join(', ') + ' (e.g. media.' + allowedRoots[0] + ')</span>').show();
        }
        return false;
      } else {
        if ($hintContainer && $hintContainer.length) {
          $hintContainer.html('<span style="color:#059669;font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Valid CDN subdomain for ' + allowedRoots[0] + '</span>').show();
        }
        return true;
      }
    }

    $(document).on('blur', '#onboard_r2_custom_domain', function() {
      validateCdnDomainInput($(this), $('#onboard-cache-rule-status'));
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
      formData.append('max_height', $('#max_height').val() || 0);
      formData.append('engine', $('#compress_engine').val() || 'smart');
      formData.append('smart_routing', $('input[name="smart_routing"]').is(':checked') ? 1 : 0);

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
                  <h4 style="margin:0 0 6px 0; color:#0f172a; font-size:15px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Compression Successful!</h4>
                  <table style="font-size:12px; line-height:1.6;">
                    <tr><td><strong>Original Size:</strong></td><td>${formatBytes(d.original_size)}</td></tr>
                    <tr><td><strong>Compressed Size:</strong></td><td>${formatBytes(d.compressed_size)}</td></tr>
                    <tr><td><strong>Reduction:</strong></td><td><span class="astropress-text-success" style="font-size:14px; font-weight:700;">-${d.percent}%</span> (${formatBytes(d.saved_bytes)} saved)</td></tr>
                    <tr><td><strong>Engine Used:</strong></td><td><code>${d.engine_used || 'smart'}</code>${d.engine_note ? `<div style="font-size:11px; color:#475569; margin-top:2px;">${d.engine_note}</div>` : ''}</td></tr>
                  </table>
                </div>
              </div>
            `;
            $result.html(html);
          } else {
            $result.html('<p style="color:#e02424;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + (res.data?.message || 'Compression failed.') + '</p>');
          }
        },
        error: function(xhr, status, err) {
          $result.html('<p style="color:#e02424;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Request error: ' + err + '</p>');
        },
        complete: function() {
          $btn.prop('disabled', false).text('Test Image Compression');
        }
      });
    });

    // Quick Presets Selector
    $(document).on('click', '.astropress-preset-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const quality = $btn.data('quality');
      const format = $btn.data('format');
      const width = $btn.data('width');

      if (quality !== undefined) {
        $('#compress_quality').val(quality);
        $('#quality_val').text(quality + '%');
      }
      if (format !== undefined) {
        $('#compress_format').val(format);
      }
      if (width !== undefined) {
        $('#max_width').val(width);
      }
    });

    // Media Library & Modal Attachment Re-compression
    $(document).on('click', '.astropress-btn-recompress', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const id = $btn.data('id');
      const reqEngine = $btn.data('engine') || 'smart';
      const $cell = $('#astropress-compress-cell-' + id);

      $btn.prop('disabled', true).text('Compressing...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_recompress_attachment',
          id: id,
          engine: reqEngine,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success && res.data) {
            const d = res.data;
            if ($cell.length) {
              const html = `
                <span class="astropress-badge astropress-badge-both" style="margin-bottom:4px;"><span class="dashicons dashicons-yes" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span> Optimal (${d.format || 'WEBP'})</span>
                <div style="font-size:11px; color:#059669; font-weight:600;" title="${d.message || ''}">
                  ${d.engine_used === 'resmush' ? 'reSmush.it' : 'Server GD'} -${d.percent}%
                </div>
              `;
              $cell.html(html);
            } else {
              $btn.parent().html('<span style="color:#059669; font-size:12px; font-weight:600;"><span class="dashicons dashicons-yes"></span> Optimized (-' + d.percent + '%)</span>');
            }
          } else {
            alert((res.data && res.data.message) ? res.data.message : 'Re-compression failed.');
            $btn.prop('disabled', false).text('Retry');
          }
        },
        error: function(xhr, status, err) {
          alert('Network request error: ' + err);
          $btn.prop('disabled', false).text('Retry');
        }
      });
    });

    $(document).on('click', '.astropress-link-toggle-recompress', function(e) {
      e.preventDefault();
      const id = $(this).data('id');
      $('#astropress-compress-cell-' + id).find('.astropress-recompress-opts').toggle();
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

    var $startWarmBtn   = $('#astropress-start-warm-btn, #topnepali-start-warm-btn, #astropress-start-warmer');
    var $startBgWarmBtn = $('#astropress-start-bg-warm-btn, #topnepali-start-bg-warm-btn, #astropress-start-bg-warmer');
    var $cancelWarmBtn  = $('#astropress-cancel-warm-btn, #topnepali-cancel-warm-btn, #astropress-cancel-warmer');
    var $progressBox    = $('#astropress-warm-progress-box, #topnepali-warm-progress-box, #astropress-warmer-progress-container');
    var $countEl        = $('#astropress-warm-current, #topnepali-warm-current, #astropress-warmer-progress-count');
    var $totalEl        = $('#astropress-warm-total, #topnepali-warm-total, #astropress-warmer-progress-total');
    var $percentEl      = $('#astropress-warm-percent, #topnepali-warm-percent, #astropress-warmer-progress-percent');
    var $barFill        = $('#astropress-progress-bar-fill, #topnepali-progress-bar-fill, #astropress-warmer-progress-bar');
    var $currentUrl     = $('#astropress-warm-current-url, #topnepali-warm-current-url, #astropress-warmer-progress-text');
    var $statusPill     = $('#astropress-warm-status-pill, #topnepali-warm-status-pill, #astropress-warmer-status-pill');
    var $logConsole     = $('#astropress-warm-log, #topnepali-warm-log, #astropress-warmer-log');
    var $presetSelect   = $('#astropress-warm-preset, #topnepali-warm-preset, #astropress-warmer-preset');
    var $delaySelect    = $('#astropress-warm-delay, #topnepali-warm-delay, #astropress-warmer-delay');

    // Background Job UI Elements
    var $bgBox         = $('#astropress-bg-warm-box, #topnepali-bg-warm-box');
    var $bgCurrent     = $('#astropress-bg-warm-current, #topnepali-bg-warm-current');
    var $bgTotal       = $('#astropress-bg-warm-total, #topnepali-bg-warm-total');
    var $bgBarFill     = $('#astropress-bg-progress-bar-fill, #topnepali-bg-progress-bar-fill');
    var $bgStatusPill  = $('#astropress-bg-warm-status-pill, #topnepali-bg-warm-status-pill');
    var $bgMeta        = $('#astropress-bg-warm-meta, #topnepali-bg-warm-meta');
    var $pauseBgBtn    = $('#astropress-pause-bg-btn, #topnepali-pause-bg-btn');
    var $resumeBgBtn   = $('#astropress-resume-bg-btn, #topnepali-resume-bg-btn');
    var $cancelBgBtn   = $('#astropress-cancel-bg-btn, #topnepali-cancel-bg-btn');
    var $clearBgBtn    = $('#astropress-clear-bg-btn, #topnepali-clear-bg-btn');

    function appendWarmLog(statusClass, path, metaText) {
      var $entry = $('<div class="astropress-log-entry"></div>');
      var $pathSpan = $('<span class="astropress-log-path"></span>').text(path);
      var $metaSpan = $('<span class="astropress-log-meta ' + statusClass + '"></span>').text(metaText);
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
      var throttleMs = parseInt($delaySelect.val(), 10) || 500;
      var batchSize = parseInt($('#astropress-warm-batch-size, #topnepali-warm-batch-size').val(), 10) || config.defaultBatch || 5;
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

      appendWarmLog('astropress-log-status-200', '— System Initialized —', 'Scope: ' + preset + ' | Gap: ' + throttleMs + 'ms');

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
        appendWarmLog('astropress-log-status-err', 'Failed to fetch warmup list', err.statusText || 'Error');
        $statusPill.text('ERROR').css('background', '#ef4444');
        $startWarmBtn.prop('disabled', false).css('opacity', 1);
        $startBgWarmBtn.prop('disabled', false).css('opacity', 1);
        $cancelWarmBtn.hide();
        isWarming = false;
        return;
      }

      var items = (queueResponse && queueResponse.data && (queueResponse.data.items || queueResponse.data.urls)) || [];
      if (!queueResponse || !queueResponse.success || !items.length) {
        appendWarmLog('astropress-log-status-err', 'No URLs found for selected scope', 'Empty');
        $statusPill.text('EMPTY').css('background', '#64748b');
        $startWarmBtn.prop('disabled', false).css('opacity', 1);
        $startBgWarmBtn.prop('disabled', false).css('opacity', 1);
        $cancelWarmBtn.hide();
        isWarming = false;
        return;
      }
      var total = items.length;
      $totalEl.text(total);
      appendWarmLog('astropress-log-status-200', 'Queue loaded successfully', total + ' URLs ready');

      var current = 0;
      $statusPill.text('WARMING (' + batchSize + '/batch)...').css('background', '#059669');

      // Step 2: Warm in polite sequential batches with user-defined delay
      for (var i = 0; i < items.length; i += batchSize) {
        if (cancelRequested) {
          appendWarmLog('astropress-log-status-redirect', '— Warming process stopped by user —', 'Cancelled');
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
              var statusClass = 'astropress-log-status-200';
              if (r.status >= 300 && r.status < 400) statusClass = 'astropress-log-status-redirect';
              else if (r.status >= 400 || !r.ok) statusClass = 'astropress-log-status-err';

              var metaStr = 'HTTP ' + r.status + ' | ' + (r.cf_cache || 'MISS') + ' | ' + r.duration_ms + 'ms';
              appendWarmLog(statusClass, r.url, metaStr);
            });
          }
        } catch (bErr) {
          appendWarmLog('astropress-log-status-err', 'Batch network error', bErr.statusText || 'Error');
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
        appendWarmLog('astropress-log-status-200', '— Process Finished —', '100% Pre-warmed');
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

    // Auto-save unified warmer pacing & batch size preferences on change
    $(document).on('change', '#astropress-warm-delay, #astropress-warm-batch-size', function() {
      var d = parseInt($('#astropress-warm-delay').val(), 10) || 1000;
      var b = parseInt($('#astropress-warm-batch-size').val(), 10) || 5;
      $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'astropress_save_warmer_settings',
          warm_delay: d,
          warm_batch_size: b,
          nonce: nonce
        }
      });
    });

    // --- 6.2 WP-CRON BACKGROUND ASYNC WARMER ---
    $startBgWarmBtn.on('click', async function(e) {
      e.preventDefault();
      if ($startBgWarmBtn.prop('disabled')) return;

      var preset = $presetSelect.val() || 'standard';
      var throttleMs = parseInt($delaySelect.val(), 10) || 500;
      var batchSize = parseInt($('#astropress-warm-batch-size, #topnepali-warm-batch-size').val(), 10) || config.defaultBatch || 5;

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
      var currentProcessed = job.processed || job.current || 0;
      $bgCurrent.text(currentProcessed);
      $bgTotal.text(job.total || 0);

      var pct = (job.total > 0) ? Math.round((currentProcessed / job.total) * 100) : 0;
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
          if (res.data.job.status === 'running') {
            $.post(ajaxurl, { action: 'astropress_ping_cron', nonce: nonce });
          }
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
                $progText.html('<strong style="color:#059669;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> All media successfully synced to Cloudflare R2! (0 remaining). Local copies preserved safely.</strong>');
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
              $text.html('<strong style="color:#059669;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Verified local cleanup complete! Web hosting disk space freed.</strong>');
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
            $status.css('color', '#059669').html('<strong><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + res.data.message + '</strong>');
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
            $status.css('color', '#059669').html('<strong><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + res.data.message + '</strong>');
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
                let msg = '<span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Audit complete! ' + totalVerified + ' healthy in R2.';
                if (totalMissingR2 > 0) {
                  msg += ' <span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' + totalMissingR2 + ' missing in R2 (marked as failed).';
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
    // 9. Cloudflare Deploy Hook Trigger
    // ========================================================
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
          deploy_hook: $('#astropress_cf_deploy_hook').val()?.trim() || '',
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            $status.html('<span style="color:#057a55;font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#e02424;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + (res.data?.message || 'Trigger failed') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#e02424;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Network error: ' + err + '</span>');
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
        let token = $(tokenInputSelector).val() ? $(tokenInputSelector).val().trim() : '';
        const $results = $(resultsContainerSelector);
        const hasSavedToken = $('#onboard-token-connected-card').length > 0 && $('#onboard-token-connected-card').is(':visible');

        // Only prompt if both the input field AND the saved token are absent
        if (!token && !hasSavedToken) {
          alert('Please paste your Cloudflare API Token first.');
          return;
        }

        const $verifyBtn = $('#onboard-verify-token-btn');
        let origVerifyHtml = '';
        if ($verifyBtn.length) {
          origVerifyHtml = $verifyBtn.html();
          $verifyBtn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align:-2px;"></span> Verifying...');
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
              html += '<h4 style="margin:0 0 8px 0; color:#057a55;"><span class="dashicons dashicons-yes-alt"></span> Cloudflare Resources Auto-Discovered:</h4>';
              html += '<div class="astropress-flat-list">';

              if (data.matched_account) {
                html += '<div class="astropress-flat-row"><span class="flat-label">Account:</span> <span class="flat-val"><strong>' + escapeHtml(data.matched_account.name) + '</strong> <code>' + escapeHtml(data.matched_account.id) + '</code></span></div>';
              }
              if (data.matched_zone) {
                html += '<div class="astropress-flat-row"><span class="flat-label">Zone:</span> <span class="flat-val"><strong>' + escapeHtml(data.matched_zone.name) + '</strong> <code>' + escapeHtml(data.matched_zone.id) + '</code></span></div>';
              }
              if (data.buckets && data.buckets.length > 0) {
                html += '<div class="astropress-flat-row"><span class="flat-label">R2 Buckets:</span> <span class="flat-val">' + escapeHtml(data.buckets.join(', ')) + '</span></div>';
              }


              html += '</div>';

              // 1-Click Remediation for Missing Cloudflare Scopes
              if (!data.all_granted && data.fix_token_url) {
                var missingLabels = [];
                if (data.abilities) {
                  for (var k in data.abilities) {
                    if (!data.abilities[k].granted && data.abilities[k].required !== false && !data.abilities[k].optional) {
                      missingLabels.push(data.abilities[k].label || data.abilities[k].scope);
                    }
                  }
                }
                var missingDesc = missingLabels.length ? ': <strong>' + escapeHtml(missingLabels.join(', ')) + '</strong>' : '';
                html += '<div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:12px; margin-top:10px;">';
                html += '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
                html += '<div>';
                html += '<strong style="color:#b45309; font-size:13px;"><span class="dashicons dashicons-warning"></span> Incomplete Cloudflare Permissions</strong>';
                html += '<p style="margin:2px 0 0 0; font-size:12px; color:#92400e;">Missing ' + (data.missing_scopes ? data.missing_scopes.length : 'required') + ' permission(s)' + missingDesc + '. Click to re-generate with required scopes pre-filled.</p>';
                html += '</div>';
                html += '<a href="' + data.fix_token_url + '" target="_blank" rel="noopener noreferrer" class="button button-primary button-small" style="background:#d97706; border-color:#b45309;">';
                html += '<span class="dashicons dashicons-external"></span> Re-Generate Token &rarr;';
                html += '</a>';
                html += '</div>';
                html += '</div>';
              } else {
                html += '<div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:6px; padding:10px 12px; margin-top:10px; color:#065f46; font-size:13px; font-weight:600;">';
                html += '<span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> All required Cloudflare permissions verified successfully!';
                html += '</div>';
              }

              // Render Deep Abilities Audit (Flat Checklist - Zero 3-Column Bloat)
              if (data.abilities) {
                html += '<div style="margin-top:12px;">';
                html += '<h4 style="margin:0 0 6px 0; color:#334155; font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">Permissions Health Checklist:</h4>';
                html += '<div class="astropress-audit-checklist">';
                for (const key in data.abilities) {
                  const ab = data.abilities[key];
                  const isGranted = ab.granted;
                  const isOptional = ab.optional || ab.required === false;
                  const icon = isGranted ? 'dashicons-yes' : (isOptional ? 'dashicons-info' : 'dashicons-warning');
                  const badgeStyle = isGranted 
                    ? 'background:#ecfdf5;color:#065f46;' 
                    : (isOptional ? 'background:#f1f5f9;color:#475569;' : 'background:#fffbeb;color:#b45309;');
                  const badgeText = isGranted ? 'Granted' : (isOptional ? 'Optional' : 'Missing');
                  const rowClass = isGranted ? 'is-pass' : (isOptional ? 'is-optional' : 'is-fail');

                  html += '<div class="astropress-audit-row ' + rowClass + '">';
                  html += '<span class="dashicons ' + icon + '"></span>';
                  html += '<span class="audit-name">' + escapeHtml(ab.label) + '</span>';
                  html += '<code class="audit-scope">' + escapeHtml(ab.scope) + '</code>';
                  html += '<span class="audit-badge" style="' + badgeStyle + '">' + badgeText + '</span>';
                  html += '</div>';
                }
                html += '</div>';
                html += '</div>';
              }

              html += '<div style="margin-top:12px;"><button type="button" class="button button-primary astropress-apply-cf-btn" data-token="' + encodeURIComponent(token) + '">Apply Discovered Settings to WordPress</button></div>';
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

                if ($('#onboard_r2_bucket').length) {
                  const $sel = $('#onboard_r2_bucket');
                  $sel.empty();
                  data.buckets.forEach(function(b) {
                    $sel.append('<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>');
                  });
                  $sel.val(data.buckets[0]);
                  detectBucketCustomDomain(data.buckets[0], $('#onboard_r2_custom_domain'), $('#onboard-cdn-domain-status'));
                }
              }


              // On verified success, keep field closed and update connected card
              if ($('#onboard-token-connected-card').length) {
                $('#onboard-token-connected-card').slideDown(150);
                $('#onboard-token-input-drawer').slideUp(150);
                $(tokenInputSelector).val('');
              }
            } else {
              // On error, reveal drawer and focus token input so user can replace/fix it
              if ($('#onboard-token-input-drawer').length) {
                $('#onboard-token-input-drawer').slideDown(150);
                $('#onboard-token-connected-card').slideUp(150);
                $('#onboard_cf_api_token').focus();
              }
              $results.html('<div class="astropress-quick-results-box" style="border-color:#fca5a5;background:#fff5f5;"><span style="color:#e02424;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + (res.data?.message || 'Discovery failed') + '</span></div>');
            }
          },
          error: function(xhr, status, err) {
            if ($('#onboard-token-input-drawer').length) {
              $('#onboard-token-input-drawer').slideDown(150);
              $('#onboard-token-connected-card').slideUp(150);
            }
            $results.html('<div class="astropress-quick-results-box" style="border-color:#fca5a5;background:#fff5f5;"><span style="color:#e02424;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Request error: ' + err + '</span></div>');
          },
          complete: function() {
            if ($verifyBtn.length && origVerifyHtml) {
              $verifyBtn.prop('disabled', false).html(origVerifyHtml);
            }
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Auto-Discover & Verify Token');
          }
        });
      });
    }

    handleQuickConnect('#onboard_cf_api_token', '#onboard-audit-results', '#onboard-quick-audit-btn');

    // ========================================================
    // SETUP WIZARD & MASKED CREDENTIAL INTERACTION
    // ========================================================



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
          $status.html('<span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Blueprint recipe copied to clipboard! Paste into your notes or token creation form.').fadeIn(150);
          setTimeout(function() { $status.fadeOut(400); }, 5000);
        });
      }
    });



    // 6. Deploy Cache Rule from Setup Wizard / Audit (Delegated)
    $(document).on('click', '#onboard-deploy-cache-rule-btn, #astropress-btn-deploy-cache-rule', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const cdnDomain = ($('#onboard_r2_custom_domain').val() || $('#r2g_custom_domain').val() || '').trim();
      const token = ($('#onboard_cf_api_token').val() || '').trim();
      const zoneId = ($('#onboard_cf_zone_id').val() || '').trim();
      const $status = $('#onboard-cache-rule-status').length ? $('#onboard-cache-rule-status') : $('#onboard-attach-status');

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
            $status.html('<span style="color:#059669;font-weight:600;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + res.data.message + '</span>');
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + (res.data?.message || 'Deployment failed.') + '</span>');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626;font-weight:600;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Network error: ' + err + '</span>');
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



    // 8. Frontend URL Live Auto-Detection
    function detectFrontendUrl() {
      const url = $('#onboard_frontend_url').val().trim();
      const $badge = $('#onboard-frontend-detect-badge');
      const $btn = $('#onboard-detect-frontend-btn');

      if (!url || !url.startsWith('http')) {
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Pinging...');
      $badge.show().html('<div class="astropress-detect-pill is-loading"><span class="dashicons dashicons-update"></span> Pinging ' + url + ' & verifying Edge deployment...</div>');

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

            if (d.reachable) {
              html += '<span class="astropress-badge" style="background:#ecfdf5; color:#065f46; font-size:11px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Edge Architecture Verified</span>';
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
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:-2px;"></span> Ping & Verify Reachability');
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

    // 8b. Test Webhook Connection & Handshake Button
    $(document).on('click', '#onboard-test-handshake-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#onboard-handshake-status');
      const $badge = $('#onboard-secret-badge');
      const frontendUrl = $('#onboard_frontend_url').val()?.trim();
      const rawSecret = ($('#onboard_secret').length ? $('#onboard_secret').val() : $('#onboard_revalidate_secret').val())?.trim() || '';
      const newSecret = (rawSecret && !rawSecret.startsWith('•••')) ? rawSecret : '';

      if (!frontendUrl) {
        $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Please enter the Frontend URL above first.</div>').slideDown(150);
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Testing...');
      $status.html('<div style="padding:8px 12px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; color:#0369a1; font-size:12px;"><span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Testing handshake with ' + escapeHtml(frontendUrl) + '/api/revalidate...</div>').slideDown(150);

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
            const hasSecret = Boolean(newSecret || ($('#onboard_secret').val()?.includes('•••')) || ($('#astropress_revalidate_secret').val()?.includes('•••')));
            if ($badge.length) {
              $badge.css({'background': '#ecfdf5', 'color': '#065f46', 'border-color': '#a7f3d0'})
                    .text(hasSecret ? 'Secret Verified' : 'Online (Open Endpoint)');
            }
            $status.html('<div style="padding:8px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> <strong>Verified:</strong> ' + escapeHtml(res.data.message) + ' (' + res.data.latency_ms + 'ms latency)</div>');
          } else {
            const code = res.data?.code || 0;
            if ($badge.length) $badge.css({'background': '#fef2f2', 'color': '#991b1b', 'border-color': '#fecaca'}).text('Failed (HTTP ' + code + ')');
            let failHtml = '<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;">';
            failHtml += '<div style="font-weight:700;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || ('HTTP Error ' + code)) + '</div>';
            if (res.data?.guidance) {
              failHtml += '<div style="margin-top:6px; padding-top:6px; border-top:1px dashed #fca5a5; color:#7f1d1d; line-height:1.45;"><strong>Resolution:</strong> ' + escapeHtml(res.data.guidance) + '</div>';
            }
            failHtml += '</div>';
            $status.html(failHtml);
          }
        },
        error: function(xhr, status, err) {
          $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Network request failed: ' + escapeHtml(err) + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align:-2px;"></span> Test Connection');
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
      const bucket = $('#onboard_r2_bucket').val()?.trim() || '';
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
      const bucket = $btn.data('bucket') || $('#onboard_r2_bucket').val()?.trim() || '';
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

    // 3. Setup Wizard: Check Connection & Token Drawer Toggle
    $(document).on('click', '#onboard-verify-token-btn', function(e) {
      e.preventDefault();
      $('#onboard-quick-audit-btn').trigger('click');
    });

    $(document).on('click', '#onboard-change-token-btn', function(e) {
      e.preventDefault();
      $('#onboard-token-connected-card').slideUp(150);
      $('#onboard-token-input-drawer').slideDown(150, function() {
        $('#onboard_cf_api_token').focus();
      });
    });

    $(document).on('click', '#onboard-cancel-change-token-btn', function(e) {
      e.preventDefault();
      $('#onboard-token-input-drawer').slideUp(150);
      $('#onboard-token-connected-card').slideDown(150);
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

            let tbl = '<div class="astropress-table-responsive"><table class="widefat striped" style="margin-top:6px; min-width:680px;">';
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
              tbl += '<td><strong>' + escapeHtml(r.description || 'Custom Rule') + '</strong></td>';
              tbl += '<td><code style="word-break:break-all; white-space:pre-wrap; max-width:280px; display:inline-block;">' + escapeHtml(r.expression || '') + '</code></td>';
              tbl += '<td><span class="astropress-badge" style="background:#eff6ff; color:#1d4ed8;">' + edgeTtl + '</span></td>';
              tbl += '<td><span class="astropress-badge" style="background:#f0fdf4; color:#166534;">' + browserTtl + '</span></td>';
              tbl += '<td style="text-align:right;"><button type="button" class="button button-link-delete astropress-delete-rule-btn" data-rule-id="' + r.id + '"><span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span></button></td>';
              tbl += '</tr>';
            });

            tbl += '</tbody></table></div>';
            $container.html(tbl);
          } else {
            const errMsg = res.data ? res.data.message : 'Error';
            let errHtml = '<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px; color:#991b1b; font-size:13px;">';
            errHtml += '<div style="display:flex; align-items:flex-start; gap:10px;">';
            errHtml += '<span class="dashicons dashicons-warning" style="font-size:22px; width:22px; height:22px; color:#dc2626; flex-shrink:0;"></span>';
            errHtml += '<div style="flex:1;">';
            errHtml += '<strong style="display:block; font-size:14px; margin-bottom:4px;">Failed to load rules: ' + escapeHtml(errMsg) + '</strong>';
            if (res.data && res.data.fix_token_url) {
              errHtml += '<p style="margin:8px 0 10px 0; color:#7f1d1d; font-size:12px;">Your Cloudflare API Token needs the <strong>Zone &gt; Cache Rules: Edit</strong> permission (internal key: <code>cache_settings</code>). Click below to re-generate your token with all required permissions in 1 click.</p>';
              errHtml += '<a href="' + res.data.fix_token_url + '" target="_blank" rel="noopener noreferrer" class="button button-primary button-small" style="background:#dc2626; border-color:#b91c1c;">';
              errHtml += '<span class="dashicons dashicons-external" style="vertical-align:-2px;"></span> Re-create Scoped Token with Cache Rules &rarr;';
              errHtml += '</a>';
            }
            errHtml += '</div></div></div>';
            $container.html(errHtml);
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

    // ========================================================
    // 9. Unified Cache Purge Bar (Overview Dashboard & Cloudflare Tab)
    // ========================================================

    // 9.1 Purge Specific URLs / Paths
    $(document).on('click', '#astropress-purge-urls-btn, #astropress-purge-url-btn, button[name="astropress_purge_custom_url"]', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $form = $btn.closest('form, .astropress-inline-form, .astropress-purge-bar, #astropress-purge-bar, .astropress-card');

      let $input = $form.find('#astropress-purge-urls-input, #astropress-purge-url-input, input[name="astropress_custom_url"], input[type="text"]').first();
      if (!$input.length) {
        $input = $('#astropress-purge-urls-input, #astropress-purge-url-input, input[name="astropress_custom_url"]').first();
      }

      let $status = $form.find('#astropress-direct-purge-status, #astropress-purge-cf-status, .astropress-purge-feedback').first();
      if (!$status.length) {
        $status = $('#astropress-direct-purge-status, #astropress-purge-cf-status');
      }
      if (!$status.length) {
        $status = $('<div class="astropress-purge-feedback" style="margin-top:8px; font-size:12px;"></div>');
        $btn.parent().after($status);
      }

      const raw = ($input.val() || '').trim();
      if (!raw) {
        $status.html('<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> Please enter at least one URL or path to purge.</div>').show();
        $input.focus();
        return;
      }

      const urls = raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      const origHtml = $btn.html();
      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Purging...');
      $status.html('<div style="color:#0284c7; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-update spin"></span> Purging path from Cloudflare Edge...</div>').show();

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
            $status.html('<div style="padding:6px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px; color:#16a34a;"></span> ' + escapeHtml(res.data?.message || 'Path purged successfully from Cloudflare Edge!') + '</div>').show();
            $input.val('');
          } else {
            $status.html('<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> ' + escapeHtml(res.data?.message || 'Purge failed.') + '</div>').show();
          }
        },
        error: function(xhr, status, err) {
          $status.html('<div style="padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> Request error: ' + escapeHtml(err) + '</div>').show();
        },
        complete: function() {
          $btn.prop('disabled', false).html(origHtml);
        }
      });
    });

    // 9.2 Purge Entire Cloudflare Zone Edge Cache
    $(document).on('click', '#astropress-purge-all-zone-btn, #astropress-purge-cf-now, button[name="astropress_purge_global"]', function(e) {
      e.preventDefault();
      const confirmed = window.confirm(
        "CRITICAL CACHE PURGE WARNING:\n\n" +
        "You are about to purge the ENTIRE Cloudflare Zone edge cache across all global edge locations.\n\n" +
        "- All cached static HTML, API responses, and assets will be instantly evicted from edge servers.\n" +
        "- This may cause a temporary spike in origin server CPU and traffic until edge caches warm up.\n\n" +
        "Are you sure you want to proceed with full edge eviction?"
      );
      if (!confirmed) {
        return;
      }

      const $btn = $(this);
      const $form = $btn.closest('form, .astropress-inline-form, .astropress-purge-bar, #astropress-purge-bar, .astropress-card');
      let $status = $form.find('#astropress-purge-cf-status, #astropress-direct-purge-status, .astropress-purge-feedback').first();
      if (!$status.length) {
        $status = $('#astropress-purge-cf-status, #astropress-direct-purge-status');
      }
      if (!$status.length) {
        $status = $('<div class="astropress-purge-feedback" style="margin-top:8px; font-size:12px;"></div>');
        $btn.after($status);
      }

      const origHtml = $btn.html();
      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align:-2px;"></span> Purging All...');
      $status.html('<div style="color:#0284c7; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-update spin"></span> Purging entire zone edge cache via Cloudflare API...</div>').show();

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
            $status.html('<div style="padding:8px 12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; color:#15803d; font-size:12px; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px; color:#16a34a;"></span> ' + escapeHtml(res.data?.message || 'Zone edge cache purged successfully!') + '</div>').show();
          } else {
            $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> ' + escapeHtml(res.data?.message || 'Purge failed.') + '</div>').show();
          }
        },
        error: function(xhr, status, err) {
          $status.html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; display:flex; align-items:center; gap:6px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px; color:#dc2626;"></span> Request error: ' + escapeHtml(err) + '</div>').show();
        },
        complete: function() {
          $btn.prop('disabled', false).html(origHtml);
        }
      });
    });

    // ========================================================
    // 10. Deep Linking & URL Hash Navigation
    // Support: #warmer on Cloudflare tab, #sync on R2 tab, sub-nav on Tools
    // ========================================================
    function handleHashNavigation() {
      const hash = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
      if (!hash) return;

      const urlParams = new URLSearchParams(window.location.search);
      const activeTab = urlParams.get('tab') || 'dashboard';
      const activeSub = urlParams.get('sub') || '';

      // 10.1 #warmer deep linking from Cloudflare tab or tools
      if (hash === 'warmer') {
        if (activeTab === 'cloudflare' || (activeTab === 'tools' && activeSub !== 'warmer')) {
          const targetUrl = new URL(window.location.href);
          targetUrl.searchParams.set('tab', 'tools');
          targetUrl.searchParams.set('sub', 'warmer');
          targetUrl.hash = '';
          window.location.href = targetUrl.toString();
          return;
        }
      }

      // 10.2 #sync deep linking on R2 tab
      if (hash === 'sync' && activeTab === 'r2' && activeSub !== 'sync') {
        const targetUrl = new URL(window.location.href);
        targetUrl.searchParams.set('tab', 'r2');
        targetUrl.searchParams.set('sub', 'sync');
        targetUrl.hash = '';
        window.location.href = targetUrl.toString();
        return;
      }

      // 10.3 #policies deep linking on R2 tab
      if ((hash === 'policies' || hash === 'r2') && activeTab === 'r2' && activeSub === 'sync') {
        const targetUrl = new URL(window.location.href);
        targetUrl.searchParams.set('tab', 'r2');
        targetUrl.searchParams.set('sub', 'policies');
        targetUrl.hash = '';
        window.location.href = targetUrl.toString();
        return;
      }

      // 10.4 Sub-navigation on Developer Tools (tab=tools)
      const validToolsSubs = ['warmer', 'logs', 'integration', 'updates'];
      if (activeTab === 'tools' && validToolsSubs.indexOf(hash) !== -1) {
        if (activeSub !== hash) {
          const targetUrl = new URL(window.location.href);
          targetUrl.searchParams.set('tab', 'tools');
          targetUrl.searchParams.set('sub', hash);
          targetUrl.hash = '';
          window.location.href = targetUrl.toString();
          return;
        }
      }
    }

    handleHashNavigation();
    $(window).on('hashchange', handleHashNavigation);

    // Click delegation for in-page anchors with #warmer or #sync
    $(document).on('click', 'a[href*="#warmer"], a[href*="#sync"]', function(e) {
      const href = $(this).attr('href');
      if (href && href.indexOf('#warmer') !== -1) {
        e.preventDefault();
        const base = config.ajaxUrl ? config.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=astropress&tab=tools&sub=warmer') : 'admin.php?page=astropress&tab=tools&sub=warmer';
        window.location.href = base;
      } else if (href && href.indexOf('#sync') !== -1) {
        e.preventDefault();
        const base = config.ajaxUrl ? config.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=astropress&tab=r2&sub=sync') : 'admin.php?page=astropress&tab=r2&sub=sync';
        window.location.href = base;
      }
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

