/**
 * AstroPress Gutenberg Block Inspector Extension (Option B)
 *
 * Injects a dedicated AstroPress media control panel into core/image blocks in Gutenberg.
 * Displays live compression statistics, Cloudflare R2 sync status, and allows 1-click
 * re-processing with custom format/quality directly from the editor sidebar.
 *
 * @package AstroPress
 */
(function(wp) {
  'use strict';

  if (!wp || !wp.hooks || !wp.element || !wp.components || !wp.compose) {
    return;
  }

  const { createElement: el, useState, useEffect } = wp.element;
  const { addFilter } = wp.hooks;
  const { createHigherOrderComponent } = wp.compose;
  const { PanelBody, PanelRow, SelectControl, RangeControl, Button, Spinner, Notice } = wp.components;
  const InspectorControls = (wp.blockEditor && wp.blockEditor.InspectorControls) || (wp.editor && wp.editor.InspectorControls);

  const config = window.astropressEditorConfig || {
    ajax_url: (typeof window !== 'undefined' && window.ajaxurl) ? window.ajaxurl : '/wp-admin/admin-ajax.php',
    nonce: '',
    r2_nonce: '',
    engine: 'smart',
    format: 'webp',
    quality: 82,
    storageMode: 'both',
    canWebP: true,
    canAVIF: false,
  };

  /**
   * Helper to format bytes into readable strings
   */
  function formatBytes(bytes) {
    if (!bytes || bytes <= 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  /**
   * Component for the AstroPress Inspector Panel
   */
  function AstroPressImageInspector(props) {
    const { attributes, setAttributes } = props;
    const attachmentId = attributes.id;

    if (!attachmentId) {
      return el(
        PanelBody,
        { title: 'AstroPress Media & R2 Storage', initialOpen: false },
        el('p', { style: { color: '#64748b', fontSize: '12px', margin: 0 } }, 'Select or upload an image from the Media Library to inspect compression & R2 status.')
      );
    }

    const [engine, setEngine] = useState(config.engine || 'smart');
    const [format, setFormat] = useState(config.format || 'webp');
    const [quality, setQuality] = useState(parseInt(config.quality, 10) || 82);
    const [isBusy, setIsBusy] = useState(false);
    const [notice, setNotice] = useState(null);
    const [stats, setStats] = useState(null);

    // Format options based on server capabilities
    const formatOptions = [
      { label: 'WebP (' + (config.canWebP ? 'Supported' : 'Unsupported') + ')', value: 'webp' },
      { label: 'JPEG / JPG', value: 'jpg' },
      { label: 'Keep Original Format', value: 'original' },
    ];
    if (config.canAVIF) {
      formatOptions.splice(1, 0, { label: 'AVIF (High Compression)', value: 'avif' });
    }

    const engineOptions = [
      { label: 'Smart Dual Engine (< 5MB reSmush, >= 5MB Server GD)', value: 'smart' },
      { label: 'reSmush.it Cloud API Only', value: 'resmush' },
      { label: 'Server GD / Imagick Only', value: 'server' },
    ];

    /**
     * Run re-compression on this attachment
     */
    function handleRecompress() {
      setIsBusy(true);
      setNotice(null);

      const data = new FormData();
      data.append('action', 'astropress_recompress_attachment');
      data.append('id', attachmentId);
      data.append('format', format);
      data.append('quality', quality);
      data.append('engine', engine);
      data.append('nonce', config.nonce || config.r2_nonce || '');

      fetch(config.ajax_url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
      })
        .then(function(res) { return res.json(); })
        .then(function(res) {
          setIsBusy(false);
          if (res.success && res.data) {
            const d = res.data;
            setStats({
              format: d.format,
              engine: d.engine_used,
              savedBytes: d.bytes_saved,
              percent: d.percent,
              compressedSize: d.compressed_size,
              originalSize: d.original_size,
            });

            // Update block URL dynamically so the editor reloads the optimized asset
            if (d.url && setAttributes) {
              setAttributes({ url: d.url });
            }

            setNotice({
              status: 'success',
              message: d.message || 'Image optimized and synced to R2 successfully!',
            });
          } else {
            setNotice({
              status: 'error',
              message: (res.data && res.data.message) ? res.data.message : 'Re-compression failed.',
            });
          }
        })
        .catch(function(err) {
          setIsBusy(false);
          setNotice({
            status: 'error',
            message: 'Network error: ' + (err.message || 'Could not communicate with server.'),
          });
        });
    }

    /**
     * Sync single image to Cloudflare R2
     */
    function handleSyncR2() {
      setIsBusy(true);
      setNotice(null);

      const data = new FormData();
      data.append('action', 'astropress_sync_single');
      data.append('id', attachmentId);
      data.append('nonce', config.nonce || config.r2_nonce || '');

      fetch(config.ajax_url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
      })
        .then(function(res) { return res.json(); })
        .then(function(res) {
          setIsBusy(false);
          if (res.success) {
            setNotice({
              status: 'success',
              message: 'Attachment synced to Cloudflare R2 successfully!',
            });
          } else {
            setNotice({
              status: 'error',
              message: (res.data && res.data.message) ? res.data.message : 'R2 sync failed.',
            });
          }
        })
        .catch(function(err) {
          setIsBusy(false);
          setNotice({
            status: 'error',
            message: 'Network error: ' + (err.message || 'Could not communicate with server.'),
          });
        });
    }

    return el(
      PanelBody,
      { title: 'AstroPress Media & R2 Storage (Option B)', initialOpen: true },

      // Notice message
      notice ? el(Notice, { status: notice.status, isDismissible: true, onRemove: function() { setNotice(null); } }, notice.message) : null,

      // Live Stats Box
      stats ? el('div', {
        style: {
          background: '#f0fdf4',
          border: '1px solid #bbf7d0',
          borderRadius: '6px',
          padding: '10px 12px',
          marginBottom: '12px',
          fontSize: '12px',
          color: '#166534',
          lineHeight: '1.5'
        }
      },
        el('div', { style: { fontWeight: '700', marginBottom: '4px' } }, 'Optimal (' + (stats.format || 'WEBP') + ') — ' + (stats.engine === 'resmush' ? 'reSmush.it' : 'Server GD')),
        el('div', null, 'Reduced from ' + formatBytes(stats.originalSize) + ' to ' + formatBytes(stats.compressedSize) + ' (-' + stats.percent + '%)')
      ) : null,

      // Format Selector
      el(SelectControl, {
        label: 'Target Output Format',
        value: format,
        options: formatOptions,
        onChange: function(val) { setFormat(val); },
        disabled: isBusy,
      }),

      // Engine Selector
      el(SelectControl, {
        label: 'Compression Engine',
        value: engine,
        options: engineOptions,
        onChange: function(val) { setEngine(val); },
        disabled: isBusy,
        help: 'Smart routing uses reSmush for files < 5MB and Server GD for >= 5MB.',
      }),

      // Quality Slider
      el(RangeControl, {
        label: 'Quality Level (%)',
        value: quality,
        onChange: function(val) { setQuality(val); },
        min: 50,
        max: 100,
        disabled: isBusy,
      }),

      // Action Buttons
      el('div', { style: { display: 'flex', gap: '8px', marginTop: '12px', flexWrap: 'wrap' } },
        el(Button, {
          isPrimary: true,
          isBusy: isBusy,
          disabled: isBusy,
          onClick: handleRecompress,
          style: { flex: '1 1 auto', justifyContent: 'center' }
        }, isBusy ? 'Processing...' : 'Re-process Image'),
        el(Button, {
          isSecondary: true,
          disabled: isBusy,
          onClick: handleSyncR2,
          style: { flex: '1 1 auto', justifyContent: 'center' }
        }, 'Push to R2')
      )
    );
  }

  /**
   * Higher-Order Component to wrap core/image BlockEdit
   */
  const withAstroPressImageControls = createHigherOrderComponent(function(BlockEdit) {
    return function(props) {
      if (props.name !== 'core/image') {
        return el(BlockEdit, props);
      }

      return el(
        wp.element.Fragment,
        null,
        el(BlockEdit, props),
        el(
          InspectorControls,
          null,
          el(AstroPressImageInspector, props)
        )
      );
    };
  }, 'withAstroPressImageControls');

  // Register filter in Gutenberg
  addFilter(
    'editor.BlockEdit',
    'astropress/image-inspector-controls',
    withAstroPressImageControls
  );

})(window.wp);
