<?php
/**
 * AstroPress Image Optimizer Engine
 *
 * Provides intelligent dual-stage image optimization:
 * 1. reSmush.it Cloud API for lossless/lossy compression with circuit-breaker rate-limiting.
 * 2. Server GD/Imagick fallback with on-the-fly WebP/AVIF format conversion, max dimension scaling, and EXIF orientation.
 *
 * @package AstroPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class AstroPress_Optimizer {
    /**
     * Singleton instance
     *
     * @var AstroPress_Optimizer|null
     */
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    const RESMUSH_ENDPOINT  = 'https://api.resmush.it/';
    const RESMUSH_MAX_BYTES = 5242880;
    const RESMUSH_TIMEOUT   = 60;

    const RESMUSH_LAST_CALL_KEY   = 'astropress_resmush_last_call';
    const RESMUSH_BACKOFF_KEY     = 'astropress_resmush_backoff';
    const RESMUSH_MIN_INTERVAL_MS = 1000;
    const RESMUSH_BACKOFF_SECONDS = 300;

    /**
     * Normalize engine slug
     */
    public static function normalize_engine($engine) {
        $engine = sanitize_key((string) $engine);
        if ($engine === 'browser' || $engine === '' || $engine === 'smart' || $engine === 'auto') {
            return 'smart';
        }
        if (!in_array($engine, array('smart', 'resmush', 'server', 'none'), true)) {
            return 'smart';
        }
        return $engine;
    }

    /**
     * Site default engine
     */
    public static function get_default_engine() {
        $engine = get_option('astropress_compress_engine', null);
        if ($engine === null) {
            $engine = get_option('r2g_compress_engine', 'smart');
        }
        return self::normalize_engine($engine);
    }

    /**
     * Alias for get_default_engine
     *
     * @return string
     */
    public static function get_engine() {
        return self::get_default_engine();
    }

    /**
     * Get authoritative image compression quality level (50 - 100)
     *
     * @return int
     */
    public static function get_quality() {
        $q = (int) get_option('astropress_compress_quality', get_option('r2g_compress_quality', 82));
        return max(50, min(100, $q ?: 82));
    }

    /**
     * Get authoritative compression target format (webp, avif, jpg, original)
     *
     * @return string
     */
    public static function get_format() {
        return (string) get_option('astropress_compress_format', get_option('r2g_compress_format', 'webp'));
    }

    /**
     * Compress an image file (helper for AJAX preview & test tools)
     *
     * @param string $file_path
     * @param string $format
     * @param int $quality
     * @param int $max_width
     * @param string $engine
     * @return array
     */
    public function compress_image($file_path, $format = 'webp', $quality = 82, $max_width = 1920, $engine = 'smart', $max_height = 0, $original_name = '') {
        $res = self::optimize_local_file($file_path, array(
            'format'        => $format,
            'quality'       => $quality,
            'max_width'     => $max_width,
            'max_height'    => $max_height,
            'engine'        => $engine,
            'filename'      => $original_name,
            'original_name' => $original_name,
            'compress'      => true,
        ));
        if (!empty($res['success'])) {
            return array(
                'success'     => true,
                'output_path' => $res['file_path'] ?? $file_path,
                'engine_used' => $res['engine_used'] ?? $engine,
                'engine_note' => $res['engine_note'] ?? '',
                'bytes_saved' => $res['bytes_saved'] ?? 0,
            );
        }
        return array(
            'success' => false,
            'error'   => $res['error'] ?? ($res['message'] ?? __('Optimization failed.', 'astropress')),
        );
    }

    /**
     * Check if server has WebP generation support via GD or Imagick
     */
    public static function can_generate_webp() {
        if (function_exists('imagick_supports_format') && imagick_supports_format('WEBP')) {
            return true;
        }
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            return true;
        }
        if (function_exists('wp_image_editor_supports')) {
            return wp_image_editor_supports(array('methods' => array('rotate', 'resize', 'save'), 'mime_type' => 'image/webp'));
        }
        return false;
    }

    /**
     * Check if server has AVIF generation support
     */
    public static function can_generate_avif() {
        if (function_exists('wp_image_editor_supports')) {
            return wp_image_editor_supports(array('methods' => array('rotate', 'resize', 'save'), 'mime_type' => 'image/avif'));
        }
        if (function_exists('imagecreatefromavif') && function_exists('imageavif')) {
            return true;
        }
        return false;
    }

    /**
     * Get list of supported conversion formats
     */
    public static function get_supported_formats() {
        $formats = array(
            'webp'     => 'WebP (' . (self::can_generate_webp() ? __('Supported', 'astropress') : __('Unsupported', 'astropress')) . ')',
            'jpg'      => 'JPEG / JPG',
            'png'      => 'PNG',
            'original' => __('Preserve Original', 'astropress'),
        );
        if (self::can_generate_avif()) {
            $formats['avif'] = 'AVIF (' . __('Supported', 'astropress') . ')';
        }
        return $formats;
    }

    private static function fail($file_path, $message = '', $extra = array()) {
        return array_merge(array(
            'success'     => false,
            'file_path'   => $file_path,
            'mime'        => '',
            'width'       => 0,
            'height'      => 0,
            'bytes_saved' => 0,
            'message'     => $message,
        ), $extra);
    }

    private static function describe_engine($engine, $resmush_result, $gd_ran, $auto_routed = false, $orig_size = 0) {
        if ($engine === 'none') {
            return array('engine_used' => 'none', 'engine_note' => __('Raw offload (no processing).', 'astropress'));
        }
        if ($auto_routed) {
            $size_str = $orig_size > 0 ? ' (' . size_format($orig_size) . ')' : '';
            return array(
                'engine_used' => 'server',
                'engine_note' => sprintf(__('Smart 5MB Routing: Image%s routed to Server GD/Imagick (bypasses 5MB reSmush limit).', 'astropress'), $size_str),
            );
        }
        if ($engine === 'server') {
            return array('engine_used' => 'server', 'engine_note' => __('Optimized locally with Server GD/Imagick.', 'astropress'));
        }
        if (is_array($resmush_result) && !empty($resmush_result['success'])) {
            $note = !empty($resmush_result['message']) ? $resmush_result['message'] : __('Optimized via reSmush.it.', 'astropress');
            if ($gd_ran) {
                $note .= ' ' . __('Final format conversion by Server GD/Imagick.', 'astropress');
            }
            return array('engine_used' => 'resmush', 'engine_note' => $note);
        }
        $reason = (is_array($resmush_result) && !empty($resmush_result['message'])) ? $resmush_result['message'] : __('reSmush.it was not used.', 'astropress');
        return array(
            'engine_used' => 'server',
            'engine_note' => sprintf(__('Fallback to Server GD/Imagick. %s', 'astropress'), $reason),
        );
    }

    /**
     * Reliably detect image extension from filename hint, path, or image magic bytes/mime
     *
     * @param string $file_path Absolute path to the image
     * @param string $fallback_name Original filename hint (e.g. from $_FILES)
     * @return string Normalized extension ('jpg', 'png', 'webp', 'gif', 'avif', etc.)
     */
    public static function detect_image_extension($file_path, $fallback_name = '') {
        $ext = '';
        if (!empty($fallback_name)) {
            $ext = strtolower((string) pathinfo($fallback_name, PATHINFO_EXTENSION));
        }
        if (empty($ext) && !empty($file_path)) {
            $ext = strtolower((string) pathinfo($file_path, PATHINFO_EXTENSION));
        }

        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (in_array($ext, array('jpg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff'), true)) {
            return $ext;
        }

        // Sniff image magic bytes via getimagesize
        if (!empty($file_path) && file_exists($file_path) && is_readable($file_path)) {
            $info = @getimagesize($file_path);
            if (!empty($info['mime'])) {
                $mime = strtolower($info['mime']);
                $mime_map = array(
                    'image/jpeg'     => 'jpg',
                    'image/jpg'      => 'jpg',
                    'image/pjpeg'    => 'jpg',
                    'image/png'      => 'png',
                    'image/x-png'    => 'png',
                    'image/webp'     => 'webp',
                    'image/gif'      => 'gif',
                    'image/avif'     => 'avif',
                    'image/bmp'      => 'bmp',
                    'image/x-ms-bmp' => 'bmp',
                    'image/tiff'     => 'tif',
                );
                if (isset($mime_map[$mime])) {
                    return $mime_map[$mime];
                }
            }

            // Fallback to exif_imagetype
            if (function_exists('exif_imagetype')) {
                $type = @exif_imagetype($file_path);
                switch ($type) {
                    case IMAGETYPE_JPEG: return 'jpg';
                    case IMAGETYPE_PNG:  return 'png';
                    case IMAGETYPE_GIF:  return 'gif';
                    case IMAGETYPE_WEBP: return 'webp';
                    case IMAGETYPE_BMP:  return 'bmp';
                    case IMAGETYPE_TIFF_II:
                    case IMAGETYPE_TIFF_MM: return 'tif';
                }
            }
        }

        return $ext;
    }

    /**
     * Compress and optionally convert an image file.
     *
     * @param string $file_path Absolute path to the image
     * @param array $options
     * @return array
     */
    public static function optimize_local_file($file_path, $options = array()) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return self::fail($file_path);
        }

        $default_engine = get_option('astropress_compress_engine', null);
        if ($default_engine === null) $default_engine = get_option('r2g_compress_engine', 'smart');

        $engine = self::normalize_engine($options['engine'] ?? $default_engine);
        $format = strtolower($options['format'] ?? get_option('astropress_compress_format', get_option('r2g_compress_format', 'webp')));
        if ($format === 'jpeg') {
            $format = 'jpg';
        }
        $compress = isset($options['compress']) ? (bool) $options['compress'] : (bool) get_option('astropress_compress_enabled', get_option('r2g_compress_enabled', 1));
        $max_width = (int) ($options['max_width'] ?? get_option('astropress_max_width', get_option('r2g_max_width', 1920)));
        $max_height = (int) ($options['max_height'] ?? get_option('astropress_max_height', get_option('r2g_max_height', 0)));
        $smart_routing = isset($options['smart_routing']) ? (bool) $options['smart_routing'] : (bool) get_option('astropress_smart_routing', get_option('r2g_smart_routing', 1));
        $backup_original = !empty($options['backup_original']) || (bool) get_option('astropress_backup_original', get_option('r2g_backup_original', 0));
        $auto_orient = isset($options['auto_orient']) ? (bool) $options['auto_orient'] : (bool) get_option('astropress_auto_orient', get_option('r2g_auto_orient', 1));

        if ($engine === 'none') {
            $compress = false;
            $format = 'original';
            $max_width = 0;
            $max_height = 0;
        }
        $quality = max(50, min(100, (int) ($options['quality'] ?? get_option('astropress_compress_quality', get_option('r2g_compress_quality', 82)))));

        $path_info = pathinfo($file_path);
        $original_name = $options['filename'] ?? ($options['original_name'] ?? '');
        $current_ext = self::detect_image_extension($file_path, $original_name);

        if ($format === 'webp' && $current_ext !== 'webp' && !self::can_generate_webp()) {
            return self::fail($file_path, __('WebP conversion is required but this server cannot create WebP images.', 'astropress'));
        }

        $needs_format_change = false;
        if ($format === 'webp' && $current_ext !== 'webp') {
            $needs_format_change = true;
        } elseif ($format === 'jpg' && $current_ext !== 'jpg') {
            $needs_format_change = true;
        } elseif ($format === 'png' && $current_ext !== 'png') {
            $needs_format_change = true;
        } elseif ($format === 'avif' && $current_ext !== 'avif' && self::can_generate_avif()) {
            $needs_format_change = true;
        }

        if (!$needs_format_change && !$compress && $max_width <= 0 && $max_height <= 0) {
            $d = self::describe_engine($engine, null, false);
            return array('success' => true, 'file_path' => $file_path, 'mime' => '', 'width' => 0, 'height' => 0, 'bytes_saved' => 0, 'engine_used' => $d['engine_used'], 'engine_note' => $d['engine_note'], 'resmush_result' => null);
        }

        $original_size = (int) filesize($file_path);
        $auto_routed_server = false;
        $should_use_resmush = false;

        // Smart 5MB Routing:
        // reSmush.it Free API strictly enforces a 5MB payload limit.
        // Route images < 5MB to external API (zero server CPU) and images >= 5MB to Server GD/Imagick.
        if ($compress) {
            if ($engine === 'smart') {
                if ($original_size < self::RESMUSH_MAX_BYTES) {
                    $should_use_resmush = true;
                } else {
                    $auto_routed_server = true;
                }
            } elseif ($engine === 'resmush') {
                if ($original_size >= self::RESMUSH_MAX_BYTES && $smart_routing) {
                    $auto_routed_server = true;
                } else {
                    $should_use_resmush = true;
                }
            }
        }

        $resmush_result = null;
        $resmush_succeeded = false;

        if ($should_use_resmush) {
            $resmush_result = self::resmush_stage($file_path, $quality, $max_width, $needs_format_change, $current_ext, $original_name);
            $resmush_succeeded = !empty($resmush_result['success']);
        }

        clearstatcache(true, $file_path);
        $source_info = @getimagesize($file_path);
        $resize_w = $max_width > 0 && !empty($source_info[0]) && $source_info[0] > $max_width;
        $resize_h = $max_height > 0 && !empty($source_info[1]) && $source_info[1] > $max_height;
        $resize_required = $resize_w || $resize_h;

        if ($resmush_succeeded && !$needs_format_change && !$resize_required) {
            $d = self::describe_engine($engine, $resmush_result, false, $auto_routed_server, $original_size);
            return array(
                'success'        => true,
                'file_path'      => $file_path,
                'mime'           => !empty($source_info['mime']) ? $source_info['mime'] : '',
                'width'          => (int) ($source_info[0] ?? 0),
                'height'         => (int) ($source_info[1] ?? 0),
                'bytes_saved'    => max(0, $original_size - filesize($file_path)),
                'engine_used'    => $d['engine_used'],
                'engine_note'    => $d['engine_note'],
                'resmush_result' => $resmush_result,
            );
        }

        $dirname = $path_info['dirname'];
        $clean_filename = !empty($original_name) ? pathinfo($original_name, PATHINFO_FILENAME) : preg_replace('/-scaled$/i', '', $path_info['filename']);

        // Optional backup of uncompressed original file before transformation
        if ($backup_original && !empty($file_path) && file_exists($file_path)) {
            $backup_file = $dirname . '/' . $clean_filename . '.original.' . $current_ext;
            if (!file_exists($backup_file)) {
                @copy($file_path, $backup_file);
            }
        }

        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return self::fail($file_path, $editor->get_error_message(), array('resmush_result' => $resmush_result));
        }

        if ($auto_orient && $current_ext === 'jpg' && method_exists($editor, 'maybe_exif_rotate')) {
            $editor->maybe_exif_rotate();
        }

        $size = $editor->get_size();
        $resize_w = $max_width > 0 && !empty($size['width']) && $size['width'] > $max_width;
        $resize_h = $max_height > 0 && !empty($size['height']) && $size['height'] > $max_height;
        $resize_required = $resize_w || $resize_h;

        if ($compress) {
            $editor->set_quality($quality);
        }
        if ($resize_required) {
            $editor->resize($max_width > 0 ? $max_width : null, $max_height > 0 ? $max_height : null, false);
        }

        $target_mime = null;
        $target_ext = null;
        if ($format === 'webp') {
            $target_mime = 'image/webp';
            $target_ext = 'webp';
        } elseif ($format === 'jpg') {
            $target_mime = 'image/jpeg';
            $target_ext = 'jpg';
        } elseif ($format === 'png') {
            $target_mime = 'image/png';
            $target_ext = 'png';
        } elseif ($format === 'avif' && self::can_generate_avif()) {
            $target_mime = 'image/avif';
            $target_ext = 'avif';
        } elseif ($format === 'original') {
            $target_ext = $current_ext ?: 'jpg';
            if ($target_ext === 'jpg') $target_mime = 'image/jpeg';
            elseif ($target_ext === 'png') $target_mime = 'image/png';
            elseif ($target_ext === 'webp') $target_mime = 'image/webp';
            elseif ($target_ext === 'avif') $target_mime = 'image/avif';
        }

        $target_file = $file_path;
        if ($target_ext) {
            $target_name = $clean_filename . '.' . $target_ext;
            if (file_exists($dirname . '/' . $target_name) && ($dirname . '/' . $target_name) !== $file_path) {
                $target_name = wp_unique_filename($dirname, $target_name);
            }
            $target_file = $dirname . '/' . $target_name;
        }

        $saved = $editor->save($target_file, $target_mime);
        if (is_wp_error($saved)) {
            return self::fail($file_path, $saved->get_error_message(), array('resmush_result' => $resmush_result));
        }

        $final_path = $saved['path'] ?? $target_file;

        if ($final_path !== $file_path && file_exists($file_path)) {
            @unlink($file_path);
        }

        $new_size = file_exists($final_path) ? filesize($final_path) : $original_size;
        $final_size = $editor->get_size();
        $d = self::describe_engine($engine, $resmush_result, true, $auto_routed_server, $original_size);

        return array(
            'success'        => true,
            'file_path'      => $final_path,
            'mime'           => $saved['mime-type'] ?? ($target_mime ?: ''),
            'width'          => $final_size['width'] ?? ($size['width'] ?? 0),
            'height'         => $final_size['height'] ?? ($size['height'] ?? 0),
            'bytes_saved'    => max(0, $original_size - $new_size),
            'engine_used'    => $d['engine_used'],
            'engine_note'    => $d['engine_note'],
            'resmush_result' => $resmush_result,
        );
    }

    private static function get_exif_orientation($file_path) {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($file_path);
        return (is_array($exif) && !empty($exif['Orientation'])) ? (int) $exif['Orientation'] : 1;
    }

    private static function resmush_stage($file_path, $quality, $max_width, $will_convert, $ext = '', $original_name = '') {
        if (empty($ext)) {
            $ext = self::detect_image_extension($file_path, $original_name);
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, array('jpg', 'png', 'gif', 'bmp', 'tif', 'tiff'), true)) {
            return array(
                'success' => false,
                'status'  => 'unsupported_format',
                'message' => sprintf(__('%s files cannot be sent to reSmush.it (it accepts JPG, PNG, GIF, BMP, TIFF only).', 'astropress'), strtoupper($ext ?: 'Unknown')),
            );
        }

        $size = filesize($file_path);
        $info = @getimagesize($file_path);
        $too_wide = $max_width > 0 && !empty($info[0]) && $info[0] > $max_width;
        $is_jpeg = in_array($ext, array('jpg', 'jpeg'), true);
        $needs_rotate = $is_jpeg && self::get_exif_orientation($file_path) > 1;
        $too_big = $size > self::RESMUSH_MAX_BYTES;

        if (($too_wide || $needs_rotate || $too_big) && $ext !== 'gif') {
            self::prepare_for_resmush($file_path, $max_width, $too_wide, $is_jpeg, $too_wide || $needs_rotate, $ext);
            clearstatcache(true, $file_path);
            $size = filesize($file_path);
        }

        if ($size > self::RESMUSH_MAX_BYTES || $size === 0) {
            return array(
                'success' => false,
                'status'  => 'exceeds_size',
                'message' => __('Image is still above 5 MB reSmush.it limit after resizing.', 'astropress'),
            );
        }

        $api_quality = $will_convert ? max($quality, 92) : $quality;
        return self::resmush_file($file_path, $api_quality, $ext, $original_name);
    }

    private static function prepare_for_resmush($file_path, $max_width, $too_wide, $is_jpeg, $force = false, $ext = '') {
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return false;
        }
        if ($is_jpeg && method_exists($editor, 'maybe_exif_rotate')) {
            $editor->maybe_exif_rotate();
        }
        if ($too_wide) {
            $editor->resize($max_width, null, false);
        }
        $editor->set_quality(92);

        if (empty($ext)) {
            $ext = self::detect_image_extension($file_path);
        }
        $safe_ext = ($ext === 'jpeg') ? 'jpg' : ($ext ?: 'jpg');
        $tmp = dirname($file_path) . '/astropress-prep-' . wp_generate_password(8, false, false) . '.' . $safe_ext;
        $saved = $editor->save($tmp);
        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) {
            return false;
        }
        if (!$force && filesize($saved['path']) >= filesize($file_path)) {
            @unlink($saved['path']);
            return false;
        }
        if (@rename($saved['path'], $file_path)) {
            return true;
        }
        @unlink($saved['path']);
        return false;
    }

    public static function resmush_file($file_path, $quality = 82, $ext = '', $original_name = '') {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array('success' => false, 'status' => 'missing_file', 'message' => __('Local file not found.', 'astropress'));
        }

        $size = filesize($file_path);
        if ($size > self::RESMUSH_MAX_BYTES || $size === 0) {
            return array('success' => false, 'status' => 'exceeds_size', 'message' => __('File exceeds 5 MB reSmush.it limit.', 'astropress'));
        }

        if (empty($ext)) {
            $ext = self::detect_image_extension($file_path, $original_name);
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $mimes = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
        );
        if (!isset($mimes[$ext])) {
            return array(
                'success' => false,
                'status'  => 'unsupported_format',
                'message' => sprintf(__('%s files cannot be sent to reSmush.it (it accepts JPG, PNG, GIF, BMP, TIFF only).', 'astropress'), strtoupper($ext ?: 'Unknown')),
            );
        }

        $file_content = @file_get_contents($file_path);
        if ($file_content === false) {
            return array('success' => false, 'status' => 'read_error', 'message' => __('Could not read image file.', 'astropress'));
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $backoff_until = (int) get_transient(self::RESMUSH_BACKOFF_KEY);
        if ($backoff_until > time()) {
            return array(
                'success' => false,
                'status'  => 'rate_limited',
                'message' => sprintf(__('reSmush.it is temporarily paused after rate limiting (retry in %d s).', 'astropress'), $backoff_until - time()),
            );
        }

        self::throttle_resmush();

        $boundary = wp_generate_password(24, false);
        $raw_name = !empty($original_name) ? $original_name : basename($file_path);
        $name_without_ext = pathinfo($raw_name, PATHINFO_FILENAME);
        if (empty($name_without_ext) || $name_without_ext === '.' || $name_without_ext === 'php') {
            $name_without_ext = 'image';
        }
        $filename = function_exists('sanitize_file_name')
            ? sanitize_file_name($name_without_ext . '.' . ($ext === 'jpeg' ? 'jpg' : $ext))
            : preg_replace('/[^A-Za-z0-9._-]/', '_', $name_without_ext . '.' . ($ext === 'jpeg' ? 'jpg' : $ext));
        if (empty($filename)) {
            $filename = 'image.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        }

        $payload = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"files\"; filename=\"{$filename}\"\r\n"
            . "Content-Type: {$mimes[$ext]}\r\n\r\n"
            . $file_content . "\r\n"
            . "--{$boundary}--\r\n";
        unset($file_content);

        $url = add_query_arg('qlty', max(50, min(100, (int) $quality)), self::RESMUSH_ENDPOINT);
        $site_url = home_url();
        $user_agent = 'WordPress/' . get_bloginfo('version') . '; ' . $site_url;

        $data = null;
        $last_error = '';
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_remote_post($url, array(
                'timeout'    => self::RESMUSH_TIMEOUT,
                'user-agent' => $user_agent,
                'headers'    => array(
                    'Referer'      => $site_url,
                    'Content-Type' => "multipart/form-data; boundary={$boundary}",
                ),
                'body'       => $payload,
            ));

            if (is_wp_error($response)) {
                $last_error = sprintf(__('network error (%s)', 'astropress'), $response->get_error_message());
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);

            if ($code === 429 || $code === 503) {
                self::start_resmush_backoff(wp_remote_retrieve_header($response, 'retry-after'));
                return array(
                    'success' => false,
                    'status'  => 'rate_limited',
                    'message' => sprintf(__('reSmush.it rate limit reached (HTTP %d); pausing API calls.', 'astropress'), $code),
                );
            }

            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 500 || !is_array($decoded)) {
                $last_error = sprintf(__('HTTP %d', 'astropress'), $code);
                if ($attempt < 2) {
                    usleep(1500000);
                }
                continue;
            }

            if (!empty($decoded['error'])) {
                $err_code = is_numeric($decoded['error']) ? (int) $decoded['error'] : 0;
                $err_msg = $decoded['error_long'] ?? ($decoded['message'] ?? __('unknown error', 'astropress'));
                $last_error = $err_msg;
                if (in_array($err_code, array(501, 503, 504), true)) {
                    continue;
                }
                return array(
                    'success' => false,
                    'status'  => 'api_error',
                    'message' => sprintf(__('reSmush.it refused image: %s.', 'astropress'), $err_msg),
                );
            }

            $data = $decoded;
            break;
        }

        if ($data === null) {
            self::start_resmush_backoff('');
            return array(
                'success' => false,
                'status'  => 'network_error',
                'message' => sprintf(__('reSmush.it unreachable or slow: %s.', 'astropress'), $last_error),
            );
        }

        if (empty($data['dest'])) {
            return array('success' => false, 'status' => 'no_dest', 'message' => __('reSmush.it returned no optimized file.', 'astropress'));
        }

        $parsed_dest = wp_parse_url($data['dest']);
        if (empty($parsed_dest['host']) || !preg_match('/(^|\.)resmush\.it$/i', $parsed_dest['host'])) {
            return array(
                'success' => false,
                'status'  => 'untrusted_host',
                'message' => __('Untrusted file source returned by optimization API.', 'astropress'),
            );
        }

        $new_bytes = '';
        $download_urls = array($data['dest']);
        if (strpos($data['dest'], 'http://') === 0) {
            $download_urls[] = 'https://' . substr($data['dest'], 7);
        }
        foreach ($download_urls as $dl_url) {
            $compressed_img = wp_remote_get($dl_url, array(
                'timeout'            => 30,
                'user-agent'         => $user_agent,
                'reject_unsafe_urls' => true,
                'headers'            => array('Referer' => $site_url),
            ));
            if (!is_wp_error($compressed_img) && (int) wp_remote_retrieve_response_code($compressed_img) === 200) {
                $new_bytes = wp_remote_retrieve_body($compressed_img);
                if ($new_bytes !== '' && @getimagesizefromstring($new_bytes) !== false) {
                    break;
                }
                $new_bytes = '';
            }
        }

        if ($new_bytes === '') {
            return array('success' => false, 'status' => 'download_error', 'message' => __('Could not download optimized image from reSmush.it.', 'astropress'));
        }

        $new_len = strlen($new_bytes);
        if ($new_len < $size) {
            if (@file_put_contents($file_path, $new_bytes) === false) {
                return array('success' => false, 'status' => 'write_error', 'message' => __('Could not save reSmush.it result to disk.', 'astropress'));
            }
            $percent = (int) round((($size - $new_len) / $size) * 100);
            return array(
                'success' => true,
                'status'  => 'success',
                'message' => sprintf(__('Optimized via reSmush.it (-%d%%).', 'astropress'), $percent),
                'percent' => $percent,
            );
        }

        return array(
            'success' => true,
            'status'  => 'already_optimal',
            'message' => __('reSmush.it: image was already optimal.', 'astropress'),
            'percent' => 0,
        );
    }

    private static function throttle_resmush() {
        $min_ms = (int) apply_filters('astropress_resmush_min_interval_ms', self::RESMUSH_MIN_INTERVAL_MS);
        if ($min_ms <= 0) {
            return;
        }
        $min_ms = min($min_ms, 10000);

        $last = (float) get_transient(self::RESMUSH_LAST_CALL_KEY);
        if ($last > 0) {
            $elapsed_ms = (microtime(true) - $last) * 1000;
            if ($elapsed_ms >= 0 && $elapsed_ms < $min_ms) {
                usleep((int) (($min_ms - $elapsed_ms) * 1000));
            }
        }
        set_transient(self::RESMUSH_LAST_CALL_KEY, (string) microtime(true), MINUTE_IN_SECONDS);
    }

    private static function start_resmush_backoff($retry_after) {
        $seconds = is_numeric($retry_after) ? (int) $retry_after : self::RESMUSH_BACKOFF_SECONDS;
        $seconds = max(60, min(HOUR_IN_SECONDS, $seconds));
        $seconds = (int) apply_filters('astropress_resmush_backoff_seconds', $seconds);
        set_transient(self::RESMUSH_BACKOFF_KEY, time() + $seconds, $seconds);
    }
}
