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
    return String(str ?? '')
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

    const AP_TONES = {
      info: ['#f0f9ff', '#bae6fd', '#0369a1'],
      success: ['#f0fdf4', '#bbf7d0', '#15803d'],
      error: ['#fef2f2', '#fecaca', '#b91c1c'],
      warning: ['#fffbeb', '#fde68a', '#92400e'],
      slate: ['#f8fafc', '#e2e8f0', '#475569'],
    };

    function apIcon(cls, spin) {
      return '<span class="dashicons ' + cls + (spin ? ' spin' : '') + '" style="vertical-align:-2px;"></span> ';
    }

    function apAlert(tone, html, extraStyle) {
      const c = AP_TONES[tone] || AP_TONES.info;
      return '<div style="padding:8px 12px; background:' + c[0] + '; border:1px solid ' + c[1] + '; border-radius:6px; color:' + c[2] + '; font-size:12px' + (extraStyle ? '; ' + extraStyle : '') + '">' + html + '</div>';
    }

    function readUnmasked(sel) {
      const v = ($(sel).val() || '').trim();
      return v.startsWith('•••') ? '' : v;
    }

    function readCustomOrSelect(sel, customSel) {
      const v = ($(sel).val() || '').trim();
      return v === '__custom__' ? (($(customSel).val() || '').trim()) : v;
    }

    function apAjax(data) {
      return $.ajax({ url: ajaxurl, type: 'POST', dataType: 'json', data: Object.assign({ nonce: nonce }, data) });
    }

    function apRunAction($btn, opts) {
      const origHtml = $btn.html();
      if (opts.loadingBtnHtml) $btn.prop('disabled', true).html(opts.loadingBtnHtml);
      else $btn.prop('disabled', true);

      if (opts.$status && opts.loadingMsg) {
        opts.$status.show().html(apAlert('info', apIcon('dashicons-update', true) + escapeHtml(opts.loadingMsg)));
      }

      return apAjax(opts.data)
        .done(function(res) {
          if (opts.onDone) {
            opts.onDone(res, origHtml);
            return;
          }
          if (res && res.success) {
            if (opts.$status) {
              opts.$status.html(apAlert('success', apIcon('dashicons-yes-alt') + escapeHtml(res.data?.message || opts.successMsg || 'Success!'))).show();
            }
            if (opts.onSuccess) opts.onSuccess(res);
          } else if (opts.$status) {
            opts.$status.html(apAlert('error', apIcon('dashicons-warning') + escapeHtml(res?.data?.message || opts.errorMsg || 'Request failed.'))).show();
          }
        })
        .fail(function(_xhr, _status, err) {
          if (opts.onFail) {
            opts.onFail(err);
          } else if (opts.$status) {
            opts.$status.html(apAlert('error', apIcon('dashicons-warning') + 'Network error: ' + escapeHtml(err))).show();
          }
        })
        .always(function() {
          if (!opts.skipAlwaysRestore) {
            $btn.prop('disabled', false).html(opts.restoreHtml || origHtml);
          }
        });
    }

    function redirectToSubTab(tab, sub) {
      const targetUrl = new URL(window.location.href);
      targetUrl.searchParams.set('tab', tab);
      targetUrl.searchParams.set('sub', sub);
      targetUrl.hash = '';
      window.location.href = targetUrl.toString();
    }

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
            $status.html('<span style="color:#b91c1c; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + escapeHtml(res.data?.message || 'Cleanup completed with notices.') + '</span>');
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
              escapeHtml(d.message || ('Purged ' + (d.deleted_options || 0) + ' legacy options, ' + (d.deleted_postmeta || 0) + ' postmeta rows.')) + '</span>');
          } else {
            $status.html('<span style="color:#b91c1c; font-weight:600;"><span class="dashicons dashicons-warning"></span> ' + escapeHtml(res.data?.message || 'Cleanup encountered notices.') + '</span>');
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
        $statusDiv.html('<div style="font-size:12px; color:#0369a1; display:flex; align-items:center; gap:6px;">' + apIcon('dashicons-update', true) + 'Checking Cloudflare R2 custom domain for <code>' + escapeHtml(bucket) + '</code>...</div>').show();
      }

      apAjax({
        action: 'astropress_detect_r2_custom_domain',
        bucket_name: bucket,
        account_id: $('#onboard_r2_account_id').val() || '',
        token: $('#onboard_cf_api_token').val() || '',
      }).done(function(res) {
        let rootDomain = (config.allowedRoots && config.allowedRoots.length) ? config.allowedRoots[0] : '';
        if (!rootDomain && config.frontendUrl) {
          try { rootDomain = new URL(config.frontendUrl).hostname; } catch (e) {}
        }
        rootDomain = (rootDomain || window.location.hostname || 'example.com')
          .replace(/^https?:\/\//i, '').split(':')[0].replace(/^www\./i, '');

        if (res.success && res.data && res.data.primary) {
          const detectedDomain = res.data.primary;
          if ($domainInput.length) $domainInput.val(detectedDomain).removeData('auto-suggested');
          if ($statusDiv.length) {
            $statusDiv.html(apAlert('success', apIcon('dashicons-yes-alt') + 'Attached to Cloudflare R2: <strong>' + escapeHtml(detectedDomain) + '</strong> (Active)', 'padding:6px 10px; display:flex; align-items:center; gap:6px')).show();
          }
          if ($('#r2-active-domain-label').length) $('#r2-active-domain-label').text(detectedDomain);
        } else if (res.success) {
          const suggested = 'media.' + rootDomain;
          if ($domainInput.length) $domainInput.val(suggested).data('auto-suggested', true);
          if ($statusDiv.length) {
            $statusDiv.html(apAlert('slate', apIcon('dashicons-info') + 'No custom domain attached to bucket <code>' + escapeHtml(bucket) + '</code> yet. <strong>' + escapeHtml(suggested) + '</strong> has been auto-suggested. Click <strong>Attach &amp; Setup CNAME</strong> to link it.', 'padding:6px 10px')).show();
          }
        } else if ($statusDiv.length) {
          $statusDiv.html(apAlert('error', apIcon('dashicons-warning') + escapeHtml(res.data?.message || 'Could not query custom domains for this bucket.'), 'padding:6px 10px')).show();
        }
      }).fail(function(_xhr, _status, err) {
        if ($statusDiv.length) {
          $statusDiv.html(apAlert('error', apIcon('dashicons-warning') + 'Custom domain query failed: ' + escapeHtml(err), 'padding:6px 10px')).show();
        }
      });
    }

    // Unified Bucket Discovery Handler for Setup Wizard
    function handleFetchBuckets($btn, $status, $selectElement, $domainInput, $domainStatus) {
      apRunAction($btn, {
        loadingBtnHtml: 'Discovering...',
        restoreHtml: apIcon('dashicons-search') + 'Discover Buckets',
        data: { action: 'astropress_fetch_buckets' },
        onDone: function(res) {
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
            if (!matched) $selectElement.val(buckets[0]);
            const chosen = $selectElement.val();
            $status.html('<span style="color:#059669;font-weight:600;">' + apIcon('dashicons-yes-alt') + 'Discovered ' + buckets.length + ' bucket(s). Active: <code>' + escapeHtml(chosen) + '</code></span>');
            if ($('#r2-active-bucket-label').length && chosen) $('#r2-active-bucket-label').text(chosen);
            detectBucketCustomDomain(chosen, $domainInput, $domainStatus);
          } else {
            $status.html('<span style="color:#dc2626;font-weight:600;">' + apIcon('dashicons-dismiss') + escapeHtml(res.data?.message || 'No R2 buckets found. Create a bucket below.') + '</span>');
          }
        },
        onFail: function(err) {
          $status.html('<span style="color:#dc2626;font-weight:600;">' + apIcon('dashicons-dismiss') + 'Discovery failed: ' + escapeHtml(err) + '</span>');
        },
      });
      $status.html('<span style="color:#64748b;">' + apIcon('dashicons-update', true) + 'Connecting to Cloudflare R2 API...</span>');
    }

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
                <img src="${escapeHtml(d.data_uri)}" alt="Compressed Preview" class="astropress-preview-img" />
                <div>
                  <h4 style="margin:0 0 6px 0; color:#0f172a; font-size:15px;"><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> Compression Successful!</h4>
                  <table style="font-size:12px; line-height:1.6;">
                    <tr><td><strong>Original Size:</strong></td><td>${escapeHtml(formatBytes(d.original_size))}</td></tr>
                    <tr><td><strong>Compressed Size:</strong></td><td>${escapeHtml(formatBytes(d.compressed_size))}</td></tr>
                    <tr><td><strong>Reduction:</strong></td><td><span class="astropress-text-success" style="font-size:14px; font-weight:700;">-${escapeHtml(d.percent)}%</span> (${escapeHtml(formatBytes(d.saved_bytes))} saved)</td></tr>
                    <tr><td><strong>Engine Used:</strong></td><td><code>${escapeHtml(d.engine_used || 'smart')}</code>${d.engine_note ? `<div style="font-size:11px; color:#475569; margin-top:2px;">${escapeHtml(d.engine_note)}</div>` : ''}</td></tr>
                  </table>
                </div>
              </div>
            `;
            $result.html(html);
          } else {
            $result.html('<p style="color:#e02424;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || 'Compression failed.') + '</p>');
          }
        },
        error: function(xhr, status, err) {
          $result.html('<p style="color:#e02424;"><span class="dashicons dashicons-dismiss" style="vertical-align:-2px;"></span> Request error: ' + escapeHtml(err) + '</p>');
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
        $('#quality_number_input').val(quality);
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
                <span class="astropress-badge astropress-badge-both" style="margin-bottom:4px;"><span class="dashicons dashicons-yes" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span> Optimal (${escapeHtml(d.format || 'WEBP')})</span>
                <div style="font-size:11px; color:#059669; font-weight:600;" title="${escapeHtml(d.message || '')}">
                  ${d.engine_used === 'resmush' ? 'reSmush.it' : 'Server GD'} -${escapeHtml(d.percent)}%
                </div>
              `;
              $cell.html(html);
            } else {
              $btn.parent().html('<span style="color:#059669; font-size:12px; font-weight:600;"><span class="dashicons dashicons-yes"></span> Optimized (-' + escapeHtml(d.percent) + '%)</span>');
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
    // 5. Universal Clipboard Copy & Password Visibility Toggles
    // ========================================================
    $(document).on('click', '.astropress-copy-btn, #onboard-copy-blueprint-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const recipe = $btn.data('recipe');
      const target = $btn.data('target');
      let textToCopy = recipe || '';

      if (!textToCopy && target) {
        const $el = $(target.startsWith('#') || target.startsWith('.') ? target : '#' + target);
        if ($el.length) {
          textToCopy = $el.is('input, textarea') ? $el.val() : ($el.text() || '');
        }
      }

      if (textToCopy && !textToCopy.startsWith('•••')) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(textToCopy).then(function() {
            const $icon = $btn.find('.dashicons');
            if ($icon.length) {
              $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes-alt').css('color', '#15803d');
              setTimeout(function() {
                $icon.removeClass('dashicons-yes-alt').addClass('dashicons-clipboard').css('color', '');
              }, 1500);
            } else {
              const orig = $btn.text();
              $btn.text('Copied!');
              setTimeout(function() { $btn.text(orig); }, 2000);
            }
          }).catch(function() {
            alert('Could not copy to clipboard.');
          });
        }
      }
    });

    // Helper: Password Field Show/Hide Visibility Toggle
    $(document).on('click', '.astropress-toggle-pw', function(e) {
      e.preventDefault();
      const targetSel = $(this).data('target');
      const $target = $(targetSel);
      const $icon = $(this).find('.dashicons');
      if ($target.attr('type') === 'password') {
        $target.attr('type', 'text');
        $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
      } else {
        $target.attr('type', 'password');
        $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
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

              var metaStr = 'HTTP ' + r.status + ' | ' + (r.cf_cache || 'MISS') + ' | Latency: ' + r.duration_ms + 'ms';
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
            format: 'keep_current',
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
            $status.css('color', '#059669').html('<strong><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || '') + '</strong>');
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
            $status.css('color', '#059669').html('<strong><span class="dashicons dashicons-yes-alt" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || '') + '</strong>');
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
      const originalText = $btn.text();
      $btn.prop('disabled', true).text('Working...');

      apAjax({
        action: 'astropress_media_action',
        media_action: $btn.data('action'),
        attachment_id: $btn.data('id'),
      }).done(function(res) {
        if (res.success) {
          location.reload();
        } else {
          alert(res.data?.message || 'Media action failed.');
          $btn.prop('disabled', false).text(originalText);
        }
      }).fail(function(_xhr, _status, err) {
        alert('Network error: ' + err);
        $btn.prop('disabled', false).text(originalText);
      });
    });

    // ========================================================
    // 9. Cloudflare Deploy Hook Trigger (Concealed & Encrypted)
    // ========================================================
    $(document).on('click', '#astropress-trigger-deploy-now', function(e) {
      e.preventDefault();
      if (!confirm('Trigger Cloudflare Pages build now via deploy hook?')) return;

      const $btn = $(this);
      const $input = $('#astropress_cf_deploy_hook');
      const $status = $('#astropress-deploy-status');
      const $badge = $('#astropress-deploy-badge');

      apRunAction($btn, {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Triggering...',
        loadingMsg: 'Sending POST request to Cloudflare Pages deploy hook...',
        errorMsg: 'Deploy hook trigger failed.',
        data: { action: 'astropress_trigger_deploy_hook', deploy_hook: readUnmasked('#astropress_cf_deploy_hook') },
        onSuccess: function(res) {
          $input.val('••••••••••••••••••••••••••••••••');
          if ($badge.length) {
            $badge.css({'background': '#ecfdf5', 'color': '#166534', 'border-color': '#bbf7d0'}).text('Deploy Hook Active');
          }
          $status.html(apAlert(
            'success',
            apIcon('dashicons-yes-alt') + '<strong>Verified:</strong> ' + escapeHtml(res.data.message) +
            '<div style="margin-top:6px; padding-top:6px; border-top:1px dashed #86efac; color:#166534;">Build queued on Cloudflare Pages! Cloudflare compiles and deploys in approximately 1–2 minutes.</div>',
            'padding:10px 14px'
          ));
          $('#astropress-sync-pages-btn, #onboard-sync-pages-btn').prop('disabled', false).removeAttr('disabled');
          $('#astropress-sync-lock-notice, #onboard-sync-lock-notice').slideUp(150);
        },
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
      apAjax({ action: 'astropress_clear_webhook_logs' })
        .done(function(res) {
          if (res.success) location.reload();
          else { alert(res.data?.message || 'Failed to clear logs.'); $btn.prop('disabled', false); }
        })
        .fail(function(_xhr, _status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.astropress-resend-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const originalHtml = $btn.html();
      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span>');
      apAjax({ action: 'astropress_resend_webhook', log_id: $btn.data('id') })
        .done(function(res) {
          if (res.success) { alert('Webhook re-sent successfully!'); location.reload(); }
          else { alert(res.data?.message || 'Failed to resend webhook.'); $btn.prop('disabled', false).html(originalHtml); }
        })
        .fail(function(_xhr, _status, err) {
          alert('Network error: ' + err);
          $btn.prop('disabled', false).html(originalHtml);
        });
    });

    // 11b. Media & Storage Tab: CDN Rules Audit
    $(document).on('click', '#r2-audit-cdn-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const cdnDomain = $('input[name="r2_custom_domain"]').val() || $('#r2-active-domain-label').text().trim();
      const bucket = $('input[name="r2_bucket"]').val() || $('#r2-active-bucket-label').text().trim();
      const accountId = $('#r2_account_id').val() || '';
      const $box = $('#r2-cdn-audit-box');

      if (!cdnDomain || cdnDomain === 'Origin Uploads' || cdnDomain === 'None') {
        alert('Please configure an active R2 Custom CDN Domain in Setup Wizard first.');
        return;
      }

      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Auditing...');
      $box.show().html('<div class="astropress-cdn-audit-loading"><span class="dashicons dashicons-update spin"></span> Auditing DNS records, R2 bucket domain attachment & Edge cache rules for ' + escapeHtml(cdnDomain) + '...</div>');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_audit_cdn_domain',
          custom_domain: cdnDomain,
          bucket_name: bucket,
          account_id: accountId,
          nonce: nonce,
        },
        success: function(res) {
          if (res.success) {
            const d = res.data;
            let html = '<div class="astropress-cdn-audit-wrap" style="padding:16px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; margin-top:10px;">';
            html += '<div style="font-weight:700; font-size:13px; color:#1e293b; margin-bottom:10px;">CDN Domain Audit Report for <code>' + escapeHtml(d.host) + '</code>:</div>';
            html += '<div class="astropress-cdn-audit-grid" style="display:flex; flex-direction:column; gap:8px;">';

            // DNS Status
            html += '<div class="astropress-cdn-audit-row" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border-radius:6px;">';
            html += '<div><strong>DNS Routing:</strong> ' + (d.dns_found ? (d.dns_proxied ? 'Proxied via Cloudflare Edge (Orange Cloud)' : 'DNS Found (Unproxied)') : 'No DNS Record Found') + '</div>';
            html += '<span class="astropress-badge" style="' + (d.dns_proxied ? 'background:#ecfdf5;color:#065f46;' : 'background:#fffbeb;color:#b45309;') + '">' + (d.dns_proxied ? 'Optimal' : (d.dns_found ? 'DNS OK' : 'Pending')) + '</span>';
            html += '</div>';

            // R2 Custom Domain Attachment
            html += '<div class="astropress-cdn-audit-row" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border-radius:6px;">';
            html += '<div><strong>R2 Bucket Attachment:</strong> ' + (d.r2_attached ? 'Domain Attached to Bucket (' + escapeHtml(d.r2_status) + ')' : 'Domain Not Attached to R2 Bucket') + '</div>';
            if (d.r2_attached) {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">Attached</span>';
            } else {
              html += '<button type="button" class="button button-primary button-small" id="r2-tab-attach-domain-btn" data-host="' + escapeHtml(d.host) + '" data-bucket="' + escapeHtml(bucket) + '">Attach Domain to R2 (1-Click)</button>';
            }
            html += '</div>';

            // Cache Rule Status
            html += '<div class="astropress-cdn-audit-row" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border-radius:6px;">';
            html += '<div><strong>Edge Cache Rule:</strong> ' + escapeHtml(d.rule_desc || 'No cache rule') + '</div>';
            if (d.rule_status === 'optimal') {
              html += '<span class="astropress-badge" style="background:#ecfdf5;color:#065f46;">1-Year Cache Everything Active</span>';
            } else {
              html += '<button type="button" class="button button-secondary button-small astropress-deploy-rule-preset" data-preset="r2_cdn">Deploy / Upgrade Cache Rule</button>';
            }
            html += '</div>';

            html += '</div></div>';
            $box.html(html);
          } else {
            $box.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; margin-top:8px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> ' + escapeHtml(res.data?.message || 'Audit failed.') + '</div>');
          }
        },
        error: function(xhr, status, err) {
          $box.html('<div style="padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px; margin-top:8px;"><span class="dashicons dashicons-warning" style="vertical-align:-2px;"></span> Request error: ' + escapeHtml(err) + '</div>');
        },
        complete: function() {
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="vertical-align:-2px;"></span> Audit Rules');
        }
      });
    });

    // 11c. Media & Storage Tab: Attach Custom Domain
    $(document).on('click', '#r2-tab-attach-domain-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      $btn.prop('disabled', true).text('Attaching...');
      apAjax({
        action: 'astropress_attach_r2_custom_domain',
        custom_domain: $btn.data('host'),
        bucket_name: $btn.data('bucket'),
        account_id: $('#r2_account_id').val() || '',
      }).done(function(res) {
        if (res.success) {
          alert(res.data.message || 'Domain attached successfully!');
          $('#r2-audit-cdn-btn').trigger('click');
        } else {
          alert(res.data?.message || 'Attachment failed.');
          $btn.prop('disabled', false).text('Attach Domain to R2 (1-Click)');
        }
      }).fail(function(_xhr, _status, err) {
        alert('Request error: ' + err);
        $btn.prop('disabled', false).text('Attach Domain to R2 (1-Click)');
      });
    });

    // 11d. Media & Storage Tab: Purge CDN Cache
    $(document).on('click', '#r2-purge-cdn-btn', function(e) {
      e.preventDefault();
      if (!window.confirm(
        "CRITICAL CACHE PURGE WARNING:\n\n" +
        "Are you sure you want to purge all cached media assets from Cloudflare Edge POP servers?\n\n" +
        "- All cached images on your CDN domain will be re-fetched from R2 on their next request.\n" +
        "Do you wish to proceed?"
      )) return;

      apRunAction($(this), {
        $status: $('#astropress-r2-status'),
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Purging...',
        loadingMsg: 'Purging Cloudflare Edge cache for R2 CDN domain...',
        successMsg: 'CDN Edge cache purged successfully!',
        errorMsg: 'Purge failed.',
        restoreHtml: apIcon('dashicons-trash') + 'Purge CDN Cache',
        data: { action: 'astropress_purge_cf_zone', purge_everything: 1 },
      });
    });

    // 11e. Admin Bar Quick Action Purge Confirmation
    $(document).on('click', '#wp-admin-bar-astropress-purge-all a', function(e) {
      if (!window.confirm("CRITICAL CACHE PURGE WARNING:\n\nAre you sure you want to purge the ENTIRE edge cache across all Cloudflare edge locations?")) {
        e.preventDefault();
        return false;
      }
    });

    // Helper: Smart auto-match R2 Bucket based on selected Cloudflare Pages project or frontend domain
    function autoMatchR2Bucket(projectName, frontendDomain) {
      const $bSelect = $('#onboard_r2_bucket');
      if (!$bSelect.length || $bSelect.val()) return; // Don't override if already selected

      const pClean = (projectName || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      let dClean = '';
      if (frontendDomain) {
        try {
          const u = new URL(frontendDomain.startsWith('http') ? frontendDomain : ('https://' + frontendDomain));
          dClean = u.hostname.replace(/[^a-z0-9]/g, '').toLowerCase();
        } catch(e) {}
      }

      $bSelect.find('option').each(function() {
        const bName = $(this).val();
        if (!bName || bName === '__custom__') return;
        const bClean = bName.toLowerCase().replace(/[^a-z0-9]/g, '');

        if ((pClean && (bClean.includes(pClean) || pClean.includes(bClean))) ||
            (dClean && (bClean.includes(dClean) || dClean.includes(bClean)))) {
          $bSelect.val(bName).trigger('change');
          return false;
        }
      });
    }

    // Deploy Hook Tester in Wizard
    $(document).on('click', '#onboard-test-deploy-btn', function(e) {
      e.preventDefault();
      const rawHook = ($('#onboard_cf_deploy_hook').val() || '').trim();
      const hook = readUnmasked('#onboard_cf_deploy_hook');
      if (!hook && !rawHook.includes('•••')) {
        alert('Please enter a Cloudflare Pages Deploy Hook URL first.');
        $('#onboard_cf_deploy_hook').focus();
        return;
      }

      const $status = $('#onboard-deploy-quick-status');
      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Testing...',
        loadingMsg: 'Triggering Cloudflare Pages build...',
        restoreHtml: apIcon('dashicons-cloud-upload') + 'Test Deploy Hook',
        data: { action: 'astropress_trigger_deploy_hook', deploy_hook: hook },
        onDone: function(res) {
          if (res.success) {
            $status.html('<span style="color:#15803d; font-weight:600; font-size:12px;">' + apIcon('dashicons-yes-alt') + escapeHtml(res.data?.message || 'Deploy hook triggered successfully! Build queued on Cloudflare Pages.') + '</span>');
            $('#onboard-sync-pages-btn').prop('disabled', false).removeAttr('disabled');
            $('#onboard-sync-lock-notice').slideUp(150);
            $('#onboard-deploy-badge').css({'background': '#ecfdf5', 'color': '#166534', 'border-color': '#bbf7d0'});
            $('#onboard-deploy-badge .dashicons').removeClass('dashicons-warning').addClass('dashicons-yes-alt');
            $('#onboard-deploy-badge .status-text').text('Verified & Active (HTTP 200)');
          } else {
            $status.html('<span style="color:#b91c1c; font-weight:600; font-size:12px;">' + apIcon('dashicons-warning') + escapeHtml(res.data?.message || 'Deploy hook trigger failed.') + '</span>');
          }
        },
        onFail: function(err) {
          $status.html('<span style="color:#b91c1c; font-size:12px;">' + apIcon('dashicons-warning') + 'Error: ' + escapeHtml(err) + '</span>');
        },
      });
    });

    // R2 Bucket Dropdown change: auto-detect attached domains
    $(document).on('change', '#onboard_r2_bucket', function() {
      const val = $(this).val();
      const $status = $('#onboard-bucket-domain-status');
      if (val === '__custom__') {
        $('#onboard-custom-bucket-wrap').show();
        $status.hide();
        return;
      }
      $('#onboard-custom-bucket-wrap').hide();
      if (!val) {
        $status.hide();
        return;
      }

      $status.show().html('<span style="color:#0284c7;">' + apIcon('dashicons-update', true) + 'Checking attached domains for bucket ' + escapeHtml(val) + '...</span>');
      apAjax({
        action: 'astropress_detect_r2_custom_domain',
        bucket_name: val,
        account_id: $('#onboard_r2_account_id').val() || '',
        token: $('#onboard_cf_api_token').val() || '',
      }).done(function(res) {
        if (res.success && res.data && res.data.primary) {
          $('#onboard_r2_custom_domain').val(res.data.primary);
          $status.html('<span style="color:#15803d; font-weight:600;">' + apIcon('dashicons-yes-alt') + 'Custom domain <code>' + escapeHtml(res.data.primary) + '</code> is already attached to this bucket (Active on Cloudflare).</span>');
        } else {
          $status.html('<span style="color:#b45309; font-weight:600;">' + apIcon('dashicons-info') + 'No custom domain attached yet. Confirm below to attach and create proxied DNS CNAME.</span>');
        }
      }).fail(function() { $status.hide(); });
    });

    // ========================================================
    // Live Webhook Handshake Tester (Invalidation Tab & Setup Wizard)
    // ========================================================
    $(document).on('click', '#astropress-test-webhook-handshake-btn, #onboard-test-handshake-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const isWizard = $btn.attr('id') === 'onboard-test-handshake-btn';
      const $res = isWizard ? $('#onboard-handshake-quick-status') : $('#astropress-webhook-test-result');
      const frontendUrl = ($(isWizard ? '#onboard_frontend_url' : '#astropress_frontend_url').val() || '').trim();
      const secretVal = readUnmasked(isWizard ? '#onboard_secret' : '#astropress_revalidate_secret');

      apRunAction($btn, {
        $status: $res,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Testing Handshake...',
        loadingMsg: 'Dispatching test webhook to Astro /api/revalidate...',
        restoreHtml: isWizard
          ? (apIcon('dashicons-yes') + 'Test Connection')
          : (apIcon('dashicons-admin-network') + 'Send Test Handshake Webhook'),
        data: { action: 'astropress_test_key', frontend_url: frontendUrl, secret: secretVal },
        onDone: function(res) {
          if (res.success) {
            $res.html(apAlert(
              'success',
              '<div style="font-weight:700; font-size:13px;">' + apIcon('dashicons-yes-alt') + 'Webhook Handshake Verified (HTTP ' + (res.data?.code || 200) + ' OK)</div>' +
              '<div style="font-size:12px; margin-top:4px; color:#166534;">' + escapeHtml(res.data?.message || '') + ' &bull; <strong>Latency:</strong> ' + (res.data?.latency_ms || 0) + 'ms</div>',
              'padding:12px 14px'
            ));
          } else {
            const details = res.data?.guidance ? '<div style="margin-top:4px; font-size:11px; color:#7f1d1d;">' + escapeHtml(res.data.guidance) + '</div>' : '';
            $res.html(apAlert(
              'error',
              '<div style="font-weight:700; font-size:13px;">' + apIcon('dashicons-warning') + escapeHtml(res.data?.message || 'Handshake failed.') + '</div>' + details,
              'padding:12px 14px'
            ));
          }
        },
      });
    });

    // ========================================================
    // 3-Stage Progressive Setup Wizard Handlers
    // ========================================================

    // 1. Stage 1: Verify Token Capabilities & Probe Cloudflare Resources
    $(document).on('click', '#onboard-verify-token-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const rawToken = $('#onboard_cf_api_token').val()?.trim() || '';
      const token = (!rawToken.startsWith('•••')) ? rawToken : '';
      const $box = $('#onboard-permissions-box');
      const $warn = $('#onboard-perm-warning');

      const origHtml = $btn.html();
      $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Checking Access...');

      $.ajax({
        url: ajaxurl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'astropress_verify_token_capabilities',
          cf_api_token: token,
          nonce: nonce
        },
        success: function(res) {
          if (res.success && res.data) {
            const d = res.data;
            $box.slideDown(200);

            // Essential: Zone Read & Purge
            const zoneOk = d.permissions && d.permissions.zone_read_purge;
            const $zoneCard = $('#perm-zone-card');
            const $zoneIcon = $('#perm-zone-icon');
            if (zoneOk) {
              $zoneCard.css({'border-color': '#bbf7d0', 'background': '#f0fdf4'});
              $zoneIcon.removeClass('dashicons-warning dashicons-minus').addClass('dashicons-yes-alt').css('color', '#15803d');
              $warn.hide();
              $('#onboard-save-step1-btn, #onboard-save-all-btn').prop('disabled', false);
            } else {
              $zoneCard.css({'border-color': '#fecaca', 'background': '#fef2f2'});
              $zoneIcon.removeClass('dashicons-yes-alt dashicons-minus').addClass('dashicons-warning').css('color', '#dc2626');
              $warn.html('<strong>Essential Permission Missing:</strong> Zone Read & Zone Cache Purge permission is missing from your Cloudflare API token. Please edit your token in Cloudflare and grant Zone Cache Purge before proceeding.').show();
              $('#onboard-save-step1-btn, #onboard-save-all-btn').prop('disabled', true);
            }

            // Optional: Pages Projects
            const pagesOk = d.permissions && d.permissions.pages;
            const $pagesCard = $('#perm-pages-card');
            const $pagesIcon = $('#perm-pages-icon');
            if (pagesOk) {
              $pagesCard.css({'border-color': '#bbf7d0', 'background': '#f0fdf4'});
              $pagesIcon.removeClass('dashicons-minus').addClass('dashicons-yes-alt').css('color', '#15803d');
            } else {
              $pagesCard.css({'border-color': '#e2e8f0', 'background': '#fff'});
              $pagesIcon.removeClass('dashicons-yes-alt').addClass('dashicons-minus').css('color', '#64748b');
            }

            // Optional: R2 Storage
            const r2Ok = d.permissions && d.permissions.r2;
            const $r2Card = $('#perm-r2-card');
            const $r2Icon = $('#perm-r2-icon');
            if (r2Ok) {
              $r2Card.css({'border-color': '#bbf7d0', 'background': '#f0fdf4'});
              $r2Icon.removeClass('dashicons-minus').addClass('dashicons-yes-alt').css('color', '#15803d');
            } else {
              $r2Card.css({'border-color': '#e2e8f0', 'background': '#fff'});
              $r2Icon.removeClass('dashicons-yes-alt').addClass('dashicons-minus').css('color', '#64748b');
            }

            // Optional: DNS
            const dnsOk = d.permissions && d.permissions.dns;
            const $dnsCard = $('#perm-dns-card');
            const $dnsIcon = $('#perm-dns-icon');
            if (dnsOk) {
              $dnsCard.css({'border-color': '#bbf7d0', 'background': '#f0fdf4'});
              $dnsIcon.removeClass('dashicons-minus').addClass('dashicons-yes-alt').css('color', '#15803d');
            } else {
              $dnsCard.css({'border-color': '#e2e8f0', 'background': '#fff'});
              $dnsIcon.removeClass('dashicons-yes-alt').addClass('dashicons-minus').css('color', '#64748b');
            }

            // Persist discovered IDs
            if (d.zone_id) {
              $('#onboard_cf_zone_id').val(d.zone_id);
            }
            if (d.account_id) {
              $('#onboard_r2_account_id').val(d.account_id);
            }

            // Populate Pages project dropdown
            if (d.pages_projects && d.pages_projects.length > 0) {
              const $pSelect = $('#onboard_cf_pages_project');
              const prevProj = $pSelect.val() || d.saved_pages_project || '';
              $pSelect.find('option:not([value="__custom__"]):not([value=""])').remove();
              d.pages_projects.forEach(function(p) {
                const domStr = (p.domains || []).join(',');
                const primary = p.primary_domain || (p.domains && p.domains.length ? p.domains[0] : '');
                const label = p.name + (primary ? ' (' + primary + ')' : '');
                $pSelect.append('<option value="' + escapeHtml(p.name) + '" data-domains="' + escapeHtml(domStr) + '" data-primary="' + escapeHtml(primary) + '">' + escapeHtml(label) + '</option>');
              });
              const projToSelect = (prevProj && prevProj !== '__custom__' && $pSelect.find('option[value="' + prevProj + '"]').length)
                ? prevProj
                : (d.pages_projects[0].name);
              $pSelect.val(projToSelect).trigger('change');
            }

            // Populate R2 buckets dropdown
            if (d.buckets && d.buckets.length > 0) {
              const $bSelect = $('#onboard_r2_bucket');
              const prevBucket = $bSelect.val() || d.saved_bucket || '';
              $bSelect.find('option:not([value="__custom__"]):not([value=""])').remove();
              d.buckets.forEach(function(b) {
                $bSelect.append('<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>');
              });
              // If previously selected bucket exists in list, keep it!
              if (prevBucket && prevBucket !== '__custom__' && d.buckets.includes(prevBucket)) {
                $bSelect.val(prevBucket).trigger('change');
              } else {
                // Smart-select matching bucket based on project or domain (e.g. grisma -> grismawp, topnepali -> topnepali)
                const proj = d.pages_project || $('#onboard_cf_pages_project').val() || '';
                const fe = d.frontend_url || $('#onboard_frontend_url').val() || '';
                let bestBucket = d.buckets[0];
                const pLow = proj.toLowerCase();
                const feLow = fe.toLowerCase();
                for (let i = 0; i < d.buckets.length; i++) {
                  const bLow = d.buckets[i].toLowerCase();
                  if (pLow && (bLow === pLow || bLow === pLow + 'wp' || bLow.startsWith(pLow) || pLow.startsWith(bLow))) {
                    bestBucket = d.buckets[i];
                    break;
                  }
                  if (feLow && (feLow.includes(bLow) || (bLow.includes('grisma') && feLow.includes('grisma')) || (bLow.includes('topnepali') && feLow.includes('topnepali')))) {
                    bestBucket = d.buckets[i];
                    break;
                  }
                }
                $bSelect.val(bestBucket).trigger('change');
              }
            }

            $('#onboard-step1-status-pill').html('<span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-yes-alt"></span> Verified</span>');
          } else {
            alert(res.data?.message || 'Token verification failed.');
          }
        },
        error: function(xhr, status, err) {
          alert('Verification error: ' + err);
        },
        complete: function() {
          $btn.prop('disabled', false).html(origHtml);
        }
      });
    });

    // Hosting Target Radio Selector
    $(document).on('change', 'input[name="onboard_hosting_target"]', function() {
      const val = $(this).val();
      if (val === 'pages') {
        $('#onboard-pages-wrap').slideDown(150);
        $('#onboard-worker-wrap').slideUp(150);
      } else if (val === 'worker') {
        $('#onboard-pages-wrap').slideUp(150);
        $('#onboard-worker-wrap').slideDown(150);
      } else {
        $('#onboard-pages-wrap').slideUp(150);
        $('#onboard-worker-wrap').slideUp(150);
      }
    });

    // Pages Project dropdown change: Auto-detect custom domain (custom domain if exists, otherwise .pages.dev)
    $(document).on('change', '#onboard_cf_pages_project', function() {
      const val = $(this).val();
      if (val === '__custom__') {
        $('#onboard-pages-custom-wrap').slideDown(150);
      } else {
        $('#onboard-pages-custom-wrap').slideUp(150);
        const $opt = $(this).find('option:selected');
        let targetDom = $opt.data('primary') || '';
        if (!targetDom) {
          const domStr = $opt.data('domains') || '';
          if (domStr) {
            const doms = domStr.split(',').map(function(d) { return d.trim(); }).filter(Boolean);
            const customDom = doms.find(function(d) { return !d.endsWith('.pages.dev'); });
            targetDom = customDom || doms.find(function(d) { return d.endsWith('.pages.dev'); }) || doms[0] || '';
          }
        }
        if (targetDom) {
          let primaryUrl = targetDom.startsWith('http') ? targetDom : ('https://' + targetDom);
          $('#onboard_frontend_url').val(primaryUrl);
        }
        autoMatchR2Bucket(val, $('#onboard_frontend_url').val());
      }
    });

    // Stage 1 Save: Connection & Platform Target
    $(document).on('click', '#onboard-save-step1-btn', function(e) {
      e.preventDefault();
      apRunAction($(this), {
        $status: $('#onboard-step1-save-status'),
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Saving Stage 1...',
        loadingMsg: 'Saving connection & hosting target settings...',
        successMsg: 'Stage 1 saved!',
        errorMsg: 'Save failed',
        restoreHtml: apIcon('dashicons-saved') + 'Save Connection Settings (Stage 1)',
        data: {
          action: 'astropress_save_step1_connection',
          cf_api_token: readUnmasked('#onboard_cf_api_token'),
          hosting_target: $('input[name="onboard_hosting_target"]:checked').val() || 'pages',
          pages_project: readCustomOrSelect('#onboard_cf_pages_project', '#onboard_cf_pages_project_custom'),
          frontend_url: ($('#onboard_frontend_url').val() || '').trim(),
          zone_id: ($('#onboard_cf_zone_id').val() || '').trim(),
          account_id: ($('#onboard_r2_account_id').val() || '').trim(),
        },
        onSuccess: function() {
          $('#onboard-step1-status-pill').html('<span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;"><span class="dashicons dashicons-yes-alt"></span> Saved</span>');
        },
      });
    });

    // Stage 2: Reset & Deploy New Random Secret to Pages
    $(document).on('click', '#onboard-put-secret-btn', function(e) {
      e.preventDefault();
      const $status = $('#onboard-put-secret-status');
      const projectName = readCustomOrSelect('#onboard_cf_pages_project', '#onboard_cf_pages_project_custom');
      const rawDeploy = ($('#onboard_cf_deploy_hook').val() || '').trim();
      const deployVal = readUnmasked('#onboard_cf_deploy_hook');

      if (!projectName) {
        $status.show().html(apAlert('error', apIcon('dashicons-warning') + 'Please select or enter your Cloudflare Pages Project Name first.', 'padding:10px 14px'));
        $('#onboard_cf_pages_project').focus();
        return;
      }
      if (!deployVal && !rawDeploy.includes('•••')) {
        $status.show().html(apAlert('error', apIcon('dashicons-warning') + 'Please enter a Cloudflare Pages Deploy Hook above first so the new secret can be compiled into your Astro build.', 'padding:10px 14px'));
        $('#onboard_cf_deploy_hook').focus();
        return;
      }

      if (!confirm('Warning: This will generate a fresh cryptographic REVALIDATE_SECRET, securely encrypt it into your Cloudflare Pages production environment variables, and trigger a fresh deployment.\n\nCloudflare Pages compilation takes approximately 1-2 minutes. During rebuilding, webhook revalidation may be temporarily out of sync.\n\nDo you want to proceed?')) {
        return;
      }

      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Deploying New Secret & Triggering Build...',
        loadingMsg: 'Generating random secret, encrypting into Pages project ' + projectName + ', and dispatching Deploy Hook...',
        restoreHtml: '<span class="dashicons dashicons-update" style="vertical-align:-3px; margin-right:4px;"></span> <span class="btn-text">Reset & Deploy New Random Secret to Pages</span>',
        data: {
          action: 'astropress_put_secret_to_pages',
          project_name: projectName,
          force_generate: 1,
          deploy_hook: deployVal,
          token: ($('#onboard_cf_api_token').val() || '').trim(),
          account_id: ($('#onboard_r2_account_id').val() || '').trim(),
        },
        onDone: function(res) {
          if (res.success && res.data) {
            if (res.data.secret) {
              $('#onboard_secret').val(res.data.secret);
              $('#onboard-secret-badge').css({'background': '#ecfdf5', 'color': '#166534', 'border-color': '#bbf7d0'}).text('Custom Secret Stored');
            }
            $('#onboard-deploy-badge').css({'background': '#ecfdf5', 'color': '#166534', 'border-color': '#bbf7d0'}).text('Deploy Hook Active');
            $status.html(apAlert(
              'success',
              '<div style="font-weight:700; font-size:13px;">' + apIcon('dashicons-yes-alt') + escapeHtml(res.data.message || 'Secret deployed & build triggered!') + '</div>' +
              '<div style="margin-top:8px; padding:8px 10px; background:#ecfdf5; border-radius:4px; font-size:12px; color:#065f46; line-height:1.4;">' +
              '<strong>Next step:</strong> Cloudflare Pages will take approximately <strong>1 to 2 minutes</strong> to compile and deploy. Once the build finishes on Cloudflare, click the <strong>Test Connection</strong> button above to verify the handshake.</div>',
              'padding:12px 14px'
            ));
          } else {
            $status.html(apAlert(
              'error',
              '<div style="font-weight:700; font-size:13px;">' + apIcon('dashicons-warning') + 'Sync Failed</div>' +
              '<div style="margin-top:4px; font-size:12px;">' + escapeHtml(res.data?.message || 'Failed to sync secret to Cloudflare Pages.') + '</div>',
              'padding:12px 14px'
            ));
          }
        },
      });
    });

    // Stage 2 Save: Revalidation & Deploy Hook
    $(document).on('click', '#onboard-save-step2-btn', function(e) {
      e.preventDefault();
      apRunAction($(this), {
        $status: $('#onboard-step2-save-status'),
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Saving Stage 2...',
        loadingMsg: 'Saving revalidation & deploy hook settings...',
        successMsg: 'Stage 2 saved!',
        errorMsg: 'Save failed',
        restoreHtml: apIcon('dashicons-saved') + 'Save Revalidation Settings (Stage 2)',
        data: {
          action: 'astropress_save_step2_revalidation',
          secret: readUnmasked('#onboard_secret'),
          deploy_hook: readUnmasked('#onboard_cf_deploy_hook'),
        },
        onSuccess: function() {
          $('#onboard-step2-status-pill').html('<span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;"><span class="dashicons dashicons-yes-alt"></span> Saved</span>');
        },
      });
    });

    // 3. Stage 3: Storage mode toggle
    $(document).on('change', '#onboard_media_mode', function() {
      $('#onboard-r2-container')[$(this).val() === 'r2' ? 'slideDown' : 'slideUp'](150);
    });

    // Stage 3: Test R2 Storage Connection (Native Cloudflare REST Bearer)
    $(document).on('click', '#onboard-test-r2-btn', function(e) {
      e.preventDefault();
      const $status = $('#onboard-r2-quick-status');
      const bucket = readCustomOrSelect('#onboard_r2_bucket', '#onboard_r2_bucket_custom');
      if (!bucket) {
        $status.show().html(apAlert('error', apIcon('dashicons-warning') + 'Please select or enter an R2 bucket name first.'));
        return;
      }
      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Testing R2 Connection...',
        loadingMsg: 'Testing R2 bucket access via Cloudflare REST API...',
        successMsg: 'R2 connection verified!',
        errorMsg: 'R2 connection failed',
        restoreHtml: apIcon('dashicons-database') + 'Test R2 Storage Connection',
        data: {
          action: 'astropress_test_r2_connection',
          bucket: bucket,
          account_id: ($('#onboard_r2_account_id').val() || '').trim(),
          cf_api_token: readUnmasked('#onboard_cf_api_token'),
          custom_domain: ($('#onboard_r2_custom_domain').val() || '').trim(),
        },
      });
    });

    // Stage 3 Save: Media Settings
    $(document).on('click', '#onboard-save-step3-btn', function(e) {
      e.preventDefault();
      apRunAction($(this), {
        $status: $('#onboard-step3-save-status'),
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Saving Stage 3...',
        loadingMsg: 'Saving media storage settings...',
        successMsg: 'Stage 3 saved!',
        errorMsg: 'Save failed',
        restoreHtml: apIcon('dashicons-saved') + 'Save Media Settings (Stage 3)',
        data: {
          action: 'astropress_save_step3_media',
          media_mode: $('#onboard_media_mode').val() || 'r2',
          bucket: readCustomOrSelect('#onboard_r2_bucket', '#onboard_r2_bucket_custom'),
          custom_domain: ($('#onboard_r2_custom_domain').val() || '').trim(),
          access_key: readUnmasked('#onboard_r2_access_key'),
          secret_key: readUnmasked('#onboard_r2_secret_key'),
        },
        onSuccess: function() {
          $('#onboard-step3-status-pill').html('<span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;"><span class="dashicons dashicons-yes-alt"></span> Saved</span>');
        },
      });
    });

    // 4. Unified Final Save All
    $(document).on('click', '#onboard-save-all-btn', function(e) {
      e.preventDefault();
      const $status = $('#onboard-save-all-status');
      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Saving All Settings...',
        restoreHtml: apIcon('dashicons-yes-alt') + 'Save All Settings & Complete Setup',
        data: {
          action: 'astropress_save_all_wizard',
          cf_api_token: readUnmasked('#onboard_cf_api_token'),
          hosting_target: $('input[name="onboard_hosting_target"]:checked').val() || 'pages',
          pages_project: readCustomOrSelect('#onboard_cf_pages_project', '#onboard_cf_pages_project_custom'),
          frontend_url: ($('#onboard_frontend_url').val() || '').trim(),
          zone_id: ($('#onboard_cf_zone_id').val() || '').trim(),
          account_id: ($('#onboard_r2_account_id').val() || '').trim(),
          secret: readUnmasked('#onboard_secret'),
          deploy_hook: readUnmasked('#onboard_cf_deploy_hook'),
          media_mode: $('#onboard_media_mode').val() || 'r2',
          bucket: readCustomOrSelect('#onboard_r2_bucket', '#onboard_r2_bucket_custom'),
          custom_domain: ($('#onboard_r2_custom_domain').val() || '').trim(),
          access_key: readUnmasked('#onboard_r2_access_key'),
          secret_key: readUnmasked('#onboard_r2_secret_key'),
        },
        loadingMsg: 'Saving all 3 stages atomically to WordPress database...',
        onDone: function(res) {
          if (res.success) {
            $status.html('<div style="padding:12px 16px; background:#064e3b; border:1px solid #059669; border-radius:6px; color:#6ee7b7; font-size:13px;">' + apIcon('dashicons-yes-alt') + '<strong>' + escapeHtml(res.data?.message || 'All settings saved successfully!') + '</strong> All 3 progressive stages are stored.</div>');
            $('#onboard-step1-status-pill, #onboard-step2-status-pill, #onboard-step3-status-pill').html('<span style="background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; font-size:11px; font-weight:600; padding:3px 10px; border-radius:12px;"><span class="dashicons dashicons-yes-alt"></span> Saved</span>');
          } else {
            $status.html('<div style="padding:10px 14px; background:#7f1d1d; border:1px solid #dc2626; border-radius:6px; color:#fca5a5; font-size:13px;">' + apIcon('dashicons-warning') + escapeHtml(res.data?.message || 'Save all failed') + '</div>');
          }
        },
        onFail: function(err) {
          $status.html('<div style="padding:10px 14px; background:#7f1d1d; border:1px solid #dc2626; border-radius:6px; color:#fca5a5; font-size:13px;">' + apIcon('dashicons-warning') + 'Network error: ' + escapeHtml(err) + '</div>');
        },
      });
    });

    // Blueprint Permissions Toggle
    $(document).on('click', '#onboard-toggle-blueprint-btn', function(e) {
      e.preventDefault();
      $('#onboard-blueprint-wrap').slideToggle(150);
    });

    // Frontend URL Backend Collision Validator
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
              tbl += '<td><input type="checkbox" class="astropress-toggle-rule-switch" data-rule-id="' + escapeHtml(r.id) + '" ' + (isEnabled ? 'checked' : '') + ' /></td>';
              tbl += '<td><strong>' + escapeHtml(r.description || 'Custom Rule') + '</strong></td>';
              tbl += '<td><code style="word-break:break-all; white-space:pre-wrap; max-width:280px; display:inline-block;">' + escapeHtml(r.expression || '') + '</code></td>';
              tbl += '<td><span class="astropress-badge" style="background:#eff6ff; color:#1d4ed8;">' + escapeHtml(edgeTtl) + '</span></td>';
              tbl += '<td><span class="astropress-badge" style="background:#f0fdf4; color:#166534;">' + escapeHtml(browserTtl) + '</span></td>';
              tbl += '<td style="text-align:right;"><button type="button" class="button button-link-delete astropress-delete-rule-btn" data-rule-id="' + escapeHtml(r.id) + '"><span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span></button></td>';
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
              errHtml += '<a href="' + escapeHtml(res.data.fix_token_url) + '" target="_blank" rel="noopener noreferrer" class="button button-primary button-small" style="background:#dc2626; border-color:#b91c1c;">';
              errHtml += '<span class="dashicons dashicons-external" style="vertical-align:-2px;"></span> Re-create Scoped Token with Cache Rules &rarr;';
              errHtml += '</a>';
            }
            errHtml += '</div></div></div>';
            $container.html(errHtml);
          }
        },
        error: function(xhr, status, err) {
          $container.html('<div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:12px; color:#991b1b; font-size:13px;">Error contacting server: ' + escapeHtml(err) + '</div>');
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
    $(document).on('click', '.astropress-deploy-preset-btn, .astropress-deploy-rule-preset', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const preset = $btn.data('preset');
      const $status = $('#astropress-rule-preset-status').length ? $('#astropress-rule-preset-status') : $('#astropress-preset-deploy-status');

      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Deploying...',
        loadingMsg: 'Deploying preset "' + preset + '" to Cloudflare Ruleset API...',
        errorMsg: 'Deployment failed.',
        restoreHtml: apIcon('dashicons-plus-alt') + 'Deploy Preset',
        data: { action: 'astropress_deploy_cache_rule_preset', preset: preset },
        onSuccess: function() { loadRulesetCacheRules(); },
      });
    });

    // Custom Cache Rule Builder: Scope Toggle
    $(document).on('change', '#astropress_rule_scope', function() {
      if ($(this).val() === 'custom') {
        $('#astropress_custom_expr_wrap').slideDown(150);
      } else {
        $('#astropress_custom_expr_wrap').slideUp(150);
      }
    });

    // Custom Cache Rule Builder: Action Toggle (Cache vs Bypass)
    $(document).on('change', '#astropress_rule_action', function() {
      if ($(this).val() === 'bypass') {
        $('#astropress_rule_edge_ttl_wrap, #astropress_rule_browser_ttl_wrap').slideUp(150);
      } else {
        $('#astropress_rule_edge_ttl_wrap, #astropress_rule_browser_ttl_wrap').slideDown(150);
      }
    });

    // Custom Cache Rule Builder: Deploy Custom Rule
    $(document).on('click', '#astropress-deploy-custom-builder-btn', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $status = $('#astropress-builder-status');
      const scope = $('#astropress_rule_scope').val();
      const actionVal = $('#astropress_rule_action').val() || 'cache';
      const edgeTtl = $('#astropress_rule_edge_ttl').val() || '31536000';
      const browserTtl = $('#astropress_rule_browser_ttl').val() || '31536000';
      const desc = $('#astropress_rule_desc').val()?.trim() || 'AstroPress Custom Cache Rule';

      let expr = '';
      if (scope === 'custom') {
        expr = $('#astropress_rule_expression').val()?.trim() || '';
        if (!expr) {
          $status.show().html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning"></span> Please enter a Cloudflare Wirefilter expression.</div>');
          $('#astropress_rule_expression').focus();
          return;
        }
      } else if (scope === 'astro_frontend') {
        const rawF = $('#astropress_frontend_url').val()?.trim() || $('#onboard_frontend_url').val()?.trim() || '';
        const fHost = rawF ? rawF.replace(/^https?:\/\//i, '').split('/')[0] : '';
        if (!fHost) {
          $status.show().html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning"></span> Frontend host is not defined. Please configure Frontend URL first.</div>');
          return;
        }
        expr = '(http.host eq "' + fHost + '")';
      } else if (scope === 'static_assets') {
        expr = '(starts_with(http.request.uri.path, "/_astro/") or starts_with(http.request.uri.path, "/assets/"))';
      } else if (scope === 'r2_cdn') {
        const rawC = $('#onboard_r2_custom_domain').val()?.trim() || '';
        const cHost = rawC ? rawC.replace(/^https?:\/\//i, '').split('/')[0] : '';
        if (!cHost) {
          $status.show().html('<div style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; color:#b91c1c; font-size:12px;"><span class="dashicons dashicons-warning"></span> R2 CDN domain is not configured yet.</div>');
          return;
        }
        expr = '(http.host eq "' + cHost + '")';
      } else if (scope === 'wp_bypass') {
        expr = '(starts_with(http.request.uri.path, "/wp-admin") or starts_with(http.request.uri.path, "/wp-login.php") or starts_with(http.request.uri.path, "/wp-json/"))';
      }

      const edgeTtlMode = (edgeTtl === 'respect') ? 'respect_origin' : 'override_origin';
      const edgeTtlVal = (edgeTtl === 'respect') ? 0 : parseInt(edgeTtl, 10);
      const browserTtlMode = (browserTtl === 'respect') ? 'respect_origin' : 'override_origin';
      const browserTtlVal = (browserTtl === 'respect') ? 0 : parseInt(browserTtl, 10);

      apRunAction($(this), {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Deploying...',
        loadingMsg: 'Deploying rule to Cloudflare Ruleset API...',
        errorMsg: 'Deployment failed.',
        restoreHtml: apIcon('dashicons-saved') + 'Deploy Rule to Cloudflare',
        data: {
          action: 'astropress_deploy_cache_rule_preset',
          expression: expr,
          description: desc,
          cache_action: actionVal,
          edge_ttl_mode: edgeTtlMode,
          edge_ttl_default: edgeTtlVal,
          browser_ttl_mode: browserTtlMode,
          browser_ttl_default: browserTtlVal,
        },
        onSuccess: function() { loadRulesetCacheRules(); },
      });
    });

    // 7. Toggle Cache Rule Switch
    $(document).on('change', '.astropress-toggle-rule-switch', function() {
      const $switch = $(this);
      const isEnabled = $switch.is(':checked') ? 1 : 0;
      $switch.prop('disabled', true);
      apAjax({ action: 'astropress_toggle_cache_rule', rule_id: $switch.data('rule-id'), enabled: isEnabled })
        .done(function(res) {
          if (!res.success) {
            alert(res.data ? res.data.message : 'Failed to toggle rule.');
            $switch.prop('checked', !isEnabled);
          }
        })
        .fail(function(_xhr, _status, err) {
          alert('Error toggling rule: ' + err);
          $switch.prop('checked', !isEnabled);
        })
        .always(function() { $switch.prop('disabled', false); });
    });

    // 8. Delete Cache Rule
    $(document).on('click', '.astropress-delete-rule-btn', function(e) {
      e.preventDefault();
      if (!confirm('Are you sure you want to delete this cache rule from Cloudflare Ruleset Engine?')) return;
      const $btn = $(this);
      $btn.prop('disabled', true);
      apAjax({ action: 'astropress_delete_cache_rule', rule_id: $btn.data('rule-id') })
        .done(function(res) {
          if (res.success) loadRulesetCacheRules();
          else { alert(res.data ? res.data.message : 'Failed to delete rule.'); $btn.prop('disabled', false); }
        })
        .fail(function(_xhr, _status, err) {
          alert('Error deleting rule: ' + err);
          $btn.prop('disabled', false);
        });
    });

    // ========================================================
    // 9. Unified Cache Purge Bar (Overview Dashboard & Cloudflare Tab)
    // ========================================================
    function resolvePurgeStatusEl($btn, $form, primaryFirst) {
      let $status = $form.find(primaryFirst ? '#astropress-direct-purge-status, #astropress-purge-cf-status, .astropress-purge-feedback' : '#astropress-purge-cf-status, #astropress-direct-purge-status, .astropress-purge-feedback').first();
      if (!$status.length) $status = $('#astropress-direct-purge-status, #astropress-purge-cf-status');
      if (!$status.length) {
        $status = $('<div class="astropress-purge-feedback" style="margin-top:8px; font-size:12px;"></div>');
        (primaryFirst ? $btn.parent() : $btn).after($status);
      }
      return $status;
    }

    // 9.1 Purge Specific URLs / Paths
    $(document).on('click', '#astropress-purge-urls-btn, #astropress-purge-url-btn, button[name="astropress_purge_custom_url"]', function(e) {
      e.preventDefault();
      const $btn = $(this);
      const $form = $btn.closest('form, .astropress-inline-form, .astropress-purge-bar, #astropress-purge-bar, .astropress-card');
      let $input = $form.find('#astropress-purge-urls-input, #astropress-purge-url-input, input[name="astropress_custom_url"], input[type="text"]').first();
      if (!$input.length) $input = $('#astropress-purge-urls-input, #astropress-purge-url-input, input[name="astropress_custom_url"]').first();
      const $status = resolvePurgeStatusEl($btn, $form, true);

      const raw = ($input.val() || '').trim();
      if (!raw) {
        $status.html(apAlert('error', apIcon('dashicons-warning') + 'Please enter at least one URL or path to purge.', 'padding:6px 10px; display:flex; align-items:center; gap:6px')).show();
        $input.focus();
        return;
      }

      const urls = raw.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
      if (!urls.length) return;
      if (!window.confirm("CACHE PURGE CONFIRMATION:\n\nAre you sure you want to purge the following URL(s) from Cloudflare Edge POP cache?\n\n" + urls.join("\n"))) {
        return;
      }

      apRunAction($btn, {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Purging...',
        loadingMsg: 'Purging path from Cloudflare Edge...',
        successMsg: 'Path purged successfully from Cloudflare Edge!',
        errorMsg: 'Purge failed.',
        data: { action: 'astropress_purge_cf_zone', urls: urls },
        onSuccess: function() { $input.val(''); },
      });
    });

    // 9.2 Purge Entire Cloudflare Zone Edge Cache
    $(document).on('click', '#astropress-purge-all-zone-btn, .astropress-purge-all-zone-btn, #astropress-purge-cf-now, button[name="astropress_purge_global"]', function(e) {
      e.preventDefault();
      if (!window.confirm(
        "CRITICAL CACHE PURGE WARNING:\n\n" +
        "You are about to purge the ENTIRE Cloudflare Zone edge cache across all global edge locations.\n\n" +
        "- All cached static HTML, API responses, and assets will be instantly evicted from edge servers.\n" +
        "- This may cause a temporary spike in origin server CPU and traffic until edge caches warm up.\n\n" +
        "Are you sure you want to proceed with full edge eviction?"
      )) return;

      const $btn = $(this);
      const $form = $btn.closest('form, .astropress-inline-form, .astropress-purge-bar, #astropress-purge-bar, .astropress-card');
      const $status = resolvePurgeStatusEl($btn, $form, false);

      apRunAction($btn, {
        $status,
        loadingBtnHtml: apIcon('dashicons-update', true) + 'Purging All...',
        loadingMsg: 'Purging entire zone edge cache via Cloudflare API...',
        successMsg: 'Zone edge cache purged successfully!',
        errorMsg: 'Purge failed.',
        data: { action: 'astropress_purge_cf_zone', purge_everything: 1 },
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

      // 10.1 #warmer deep linking to Cloudflare Warmer sub-tab
      if (hash === 'warmer' && (activeTab !== 'cloudflare' || activeSub !== 'warmer')) {
        redirectToSubTab('cloudflare', 'warmer');
      } else if (hash === 'sync' && activeTab === 'r2' && activeSub !== 'sync') {
        redirectToSubTab('r2', 'sync');
      } else if ((hash === 'policies' || hash === 'r2') && activeTab === 'r2' && activeSub === 'sync') {
        redirectToSubTab('r2', 'policies');
      } else if (activeTab === 'tools' && ['seo', 'logs', 'integration', 'updates'].includes(hash) && activeSub !== hash) {
        redirectToSubTab('tools', hash);
      }
    }

    handleHashNavigation();
    $(window).on('hashchange', handleHashNavigation);

    // Click delegation for in-page anchors with #warmer or #sync
    $(document).on('click', 'a[href*="#warmer"], a[href*="#sync"]', function(e) {
      const href = $(this).attr('href');
      if (href && href.indexOf('#warmer') !== -1) {
        e.preventDefault();
        const base = config.ajaxUrl ? config.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=astropress&tab=cloudflare&sub=warmer') : 'admin.php?page=astropress&tab=cloudflare&sub=warmer';
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
            $status.html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes"></span> ' + escapeHtml(res.data?.message || 'Reset complete.') + '</span>');
            setTimeout(function() {
              window.location.href = (res.data && res.data.redirect) ? res.data.redirect : (window.location.pathname + '?page=astropress&tab=onboarding');
            }, 1000);
          } else {
            $status.html('<span style="color:#dc2626; font-weight:600;">Reset failed: ' + escapeHtml(res.data ? res.data.message : '') + '</span>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Disconnect & Reset AstroPress');
          }
        },
        error: function(xhr, status, err) {
          $status.html('<span style="color:#dc2626; font-weight:600;">Network error: ' + escapeHtml(err) + '</span>');
          $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash" style="vertical-align:-2px;"></span> Disconnect & Reset AstroPress');
        }
      });
    });

  });
})(jQuery);

