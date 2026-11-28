<?php
/**
 * Standalone CLI unit test suite for AstroPress core utilities.
 * Usage: php tests/run-tests.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ABSPATH', dirname(__DIR__) . '/');
define('AUTH_KEY', 'test-auth-key-0123456789abcdefghijklmnopqrstuvwxyz');
define('SECURE_AUTH_KEY', 'test-secure-auth-key-0123456789abcdefghijklmnopqr');

$GLOBALS['__wp_options'] = array(
    'home' => 'https://wp.example.com.np/subsite',
    'siteurl' => 'https://wp.example.com.np/subsite',
    'blogname' => 'Example Hub',
    'blogdescription' => 'Fast Headless Publishing',
);
$GLOBALS['__wp_post_meta'] = array();
$GLOBALS['__wp_transients'] = array();

function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['__wp_options']) ? $GLOBALS['__wp_options'][$key] : $default;
}

function update_option($key, $value, $autoload = null) {
    $GLOBALS['__wp_options'][$key] = $value;
    return true;
}

function delete_option($key) {
    unset($GLOBALS['__wp_options'][$key]);
    return true;
}

function get_transient($key) {
    return $GLOBALS['__wp_transients'][$key] ?? false;
}

function set_transient($key, $value, $expiration = 0) {
    $GLOBALS['__wp_transients'][$key] = $value;
    return true;
}

function delete_transient($key) {
    unset($GLOBALS['__wp_transients'][$key]);
    return true;
}

function add_action($hook, $cb, $priority = 10, $args = 1) {}
function add_filter($hook, $cb, $priority = 10, $args = 1) {}
function apply_filters($hook, $value) { return $value; }
function do_action($hook, ...$args) {}
function is_admin() { return false; }
function is_wp_error($thing) { return false; }
function untrailingslashit($val) { return rtrim((string)$val, "/\\"); }
function trailingslashit($val) { return untrailingslashit($val) . '/'; }
function home_url($path = '') { return untrailingslashit($GLOBALS['__wp_options']['home'] ?? 'https://wp.example.com.np') . $path; }
function site_url($path = '') { return untrailingslashit($GLOBALS['__wp_options']['siteurl'] ?? 'https://wp.example.com.np') . $path; }
function wp_parse_url($url, $component = -1) { return parse_url((string)$url, $component); }
function sanitize_text_field($str) { return trim(strip_tags((string)$str)); }
function wp_unslash($val) { return is_string($val) ? stripslashes($val) : $val; }
function wp_strip_all_tags($str) { return trim(strip_tags((string)$str)); }
function wp_trim_words($text, $num = 55) { return implode(' ', array_slice(preg_split('/\s+/', trim((string)$text)), 0, $num)); }
function wp_salt($scheme = 'auth') { return 'salt-' . $scheme . '-' . AUTH_KEY; }
function get_bloginfo($show = '') {
    if ($show === 'name') return $GLOBALS['__wp_options']['blogname'] ?? 'AstroPress';
    if ($show === 'description') return $GLOBALS['__wp_options']['blogdescription'] ?? '';
    return '';
}
function get_post($id = null) { return $GLOBALS['__wp_posts'][$id] ?? null; }
function get_the_category($id = 0) { return array(); }
function get_the_author_meta($field = '', $user_id = 0) { return 'Editorial'; }
function get_post_meta($id, $key = '', $single = false) {
    $val = $GLOBALS['__wp_post_meta'][$id][$key] ?? null;
    if ($single) {
        return $val === null ? '' : $val;
    }
    return $val === null ? array() : (is_array($val) ? $val : array($val));
}
function get_the_date($d = '', $post = null) { return '2026-11-27'; }
function get_the_modified_date($d = '', $post = null) { return '2026-11-27'; }
function get_locale() { return 'en_US'; }
function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return filter_var((string)$url, FILTER_SANITIZE_URL) ?: ''; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function get_permalink($post = 0) {
    $p = is_object($post) ? $post : get_post($post);
    return $p && !empty($p->post_name) ? home_url('/' . $p->post_name) : home_url('/');
}
function get_page_uri($post = 0) {
    $p = is_object($post) ? $post : get_post($post);
    return $p->post_name ?? '';
}
function get_post_thumbnail_id($post_id = 0) { return 0; }
function wp_get_attachment_image_url($id, $size = 'full') { return ''; }
function maybe_unserialize($data) {
    if (!is_string($data)) return $data;
    $trimmed = trim($data);
    if ($trimmed === 'N;') return null;
    return @unserialize($trimmed, array('allowed_classes' => false)) ?: $data;
}
function wp_http_validate_url($url) {
    $parsed = parse_url((string)$url);
    return $parsed && !empty($parsed['scheme']) && !empty($parsed['host']) ? $url : false;
}
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid-' . $action ? 1 : false; }
function current_user_can($cap) { return true; }

require_once ABSPATH . 'includes/class-encryption.php';
require_once ABSPATH . 'includes/class-security.php';
require_once ABSPATH . 'includes/class-astropress.php';
require_once ABSPATH . 'includes/class-seo.php';

$failures = 0;
$assertions = 0;

function assert_same($expected, $actual, string $label): void {
    global $failures, $assertions;
    $assertions++;
    if ($expected !== $actual) {
        $failures++;
        fwrite(STDERR, "FAIL [$label]: expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n");
    }
}

// 1. Domain extraction tests
assert_same('grisma.com.np', AstroPress::get_root_domain('https://wp.grisma.com.np/wp-json'), 'multi-part TLD .com.np');
assert_same('topnepali.com', AstroPress::get_root_domain('www.topnepali.com:8443/path'), 'host with port & path');
assert_same('bbc.co.uk', AstroPress::get_root_domain('news.bbc.co.uk'), 'multi-part TLD .co.uk');
assert_same('', AstroPress::get_root_domain(''), 'empty host');

// 2. Encryption & masking tests
$secret = 'super-secret-token-value-1234';
$encrypted = AstroPress_Encryption::encrypt($secret);
assert_same(true, AstroPress_Encryption::is_encrypted($encrypted), 'ciphertext has prefix');
assert_same($secret, AstroPress_Encryption::decrypt($encrypted), 'decrypt matches original plaintext');
$tampered = substr($encrypted, 0, -2) . (substr($encrypted, -2) === 'AA' ? 'BB' : 'AA');
assert_same('', AstroPress_Encryption::decrypt($tampered), 'tampered ciphertext rejected');
assert_same('supe••••••••••••••••1234', AstroPress_Encryption::mask($secret), 'mask preserves ends only');
assert_same('', AstroPress_Encryption::encrypt(''), 'empty plaintext returns empty string');

// 3. Compat option & custom secret accuracy (no hardcoded secret fallback)
$coreRef = new ReflectionClass('AstroPress');
$core = $coreRef->newInstanceWithoutConstructor();
assert_same(false, $core->has_custom_secret(), 'unset secret reports has_custom_secret() === false');
assert_same('', $core->get_custom_secret(), 'unset secret returns empty string');

$GLOBALS['__wp_options']['topnepali_headless_secret'] = AstroPress_Encryption::encrypt('legacy-secret-99');
assert_same(true, $core->has_custom_secret(), 'legacy encrypted secret detected');
assert_same('legacy-secret-99', $core->get_custom_secret(), 'legacy secret decrypted');
$GLOBALS['__wp_options']['astropress_secret'] = AstroPress_Encryption::encrypt('primary-secret-01');
assert_same('primary-secret-01', $core->get_custom_secret(), 'primary option overrides legacy');

// 4. R2 discovery cache fallback via get_r2_option()
$GLOBALS['__wp_options']['astropress_cf_discovery_cache'] = array('buckets' => array('media-prod'), 'account_id' => 'acc123', 'zone_id' => 'zone456');
assert_same('acc123', $core->get_r2_option('account_id'), 'discovery cache account_id fallback');
assert_same('zone456', $core->get_r2_option('zone_id'), 'discovery cache zone_id fallback');
assert_same('media-prod', $core->get_r2_option('bucket'), 'discovery cache bucket fallback');

// 5. SSRF URL validation guard
assert_same(false, AstroPress_Security::is_safe_external_url('http://localhost/hook'), 'blocks localhost');
assert_same(false, AstroPress_Security::is_safe_external_url('http://127.0.0.1/hook'), 'blocks 127.0.0.1');
assert_same(false, AstroPress_Security::is_safe_external_url('http://169.254.169.254/latest/meta-data/'), 'blocks metadata IP');
assert_same(false, AstroPress_Security::is_safe_external_url('https://10.0.0.5/internal'), 'blocks RFC1918 10.x');
assert_same(false, AstroPress_Security::is_safe_external_url('https://192.168.1.1/admin'), 'blocks RFC1918 192.168.x');
assert_same(false, AstroPress_Security::is_safe_external_url('ftp://1.1.1.1/file'), 'blocks non-HTTP scheme');
assert_same(true, AstroPress_Security::is_safe_external_url('https://1.1.1.1/api/revalidate'), 'allows public IP HTTPS URL');

// 6. SEO origin rewriting & template variable resolution
$GLOBALS['__wp_options'][AstroPress::OPTION_FRONTEND_URL] = 'https://example.com.np';
$seoRef = new ReflectionClass('AstroPress_SEO');
$seo = $seoRef->newInstanceWithoutConstructor();
$prop = $seoRef->getProperty('core');
$prop->setValue($seo, $core);

$rewritten = $seo->rewrite_origin_urls('https://wp.example.com.np/subsite/hello-world');
assert_same('https://example.com.np/hello-world', $rewritten, 'longest-first subpath origin replacement preserves slug');
assert_same('https://example.com.np/post-a', $seo->filter_rank_math_url('https://wp.example.com.np/subsite/post-a'), 'filter_rank_math_url works cleanly without undefined var');

$postStub = (object) array(
    'ID' => 42,
    'post_title' => 'Sample Headless Post',
    'post_excerpt' => 'Short summary here',
    'post_content' => 'Body content',
    'post_author' => 1,
);
assert_same('Sample Headless Post — Example Hub', $seo->resolve_seo_template_vars('%title% %sep% %sitename%', $postStub), 'resolves title/sep/sitename template vars');
assert_same('Sample Headless Post', $seo->resolve_seo_template_vars('()', $postStub), 'falls back to post title on empty punctuation template');

// 7. Nonce+capability helper
$_POST['nonce'] = 'valid-astropress_admin_nonce';
assert_same(true, AstroPress::user_can_admin_ajax('manage_options'), 'valid admin nonce accepted');
$_POST['nonce'] = 'invalid-nonce';
assert_same(false, AstroPress::user_can_admin_ajax('manage_options'), 'invalid admin nonce rejected');

// 8. Shared SEO head renderers & metadata extraction
$postStub->post_name = 'sample-headless-post';
$postStub->post_type = 'post';
$GLOBALS['__wp_posts'][42] = $postStub;
$GLOBALS['__wp_post_meta'][42] = array(
    'rank_math_title'           => '%title% | %sitename%',
    'rank_math_description'     => 'Meta %excerpt%',
    'rank_math_canonical_url'   => 'https://wp.example.com.np/subsite/custom-canon',
    'rank_math_robots'          => serialize(array('index', 'follow')),
    'rank_math_advanced_robots' => serialize(array('max-snippet' => -1)),
    'rank_math_facebook_image'  => 'https://wp.example.com.np/subsite/og.webp',
);

$seoFields = $seo->get_rank_math_seo_fields(42);
assert_same('Sample Headless Post | Example Hub', $seoFields['title'], 'extracts resolved Rank Math title');
assert_same('https://example.com.np/custom-canon', $seoFields['canonical_url'], 'rewrites canonical URL to frontend origin');
assert_same(array('index', 'follow', 'max-snippet:-1'), $seoFields['robots'], 'normalizes serialized robots and advanced directives');

$invokeProtected = static function (ReflectionClass $ref, object $obj, string $method, array $args = array()) {
    $m = $ref->getMethod($method);
    if (PHP_VERSION_ID < 80100) {
        $m->setAccessible(true);
    }
    return $m->invokeArgs($obj, $args);
};

$nativeHtml = $invokeProtected($seoRef, $seo, 'render_native_head', array($postStub, 'https://wp.example.com.np/subsite/sample-headless-post'));
assert_same(true, strpos($nativeHtml, '<link rel="canonical" href="https://example.com.np/sample-headless-post" />') !== false, 'native head rewrites canonical href');
assert_same(true, strpos($nativeHtml, '<meta property="og:type" content="article" />') !== false, 'native head emits article og:type');

$yoastHtml = $invokeProtected($seoRef, $seo, 'simulate_yoast_head', array($postStub, 'https://wp.example.com.np/subsite/sample-headless-post'));
assert_same(true, strpos($yoastHtml, 'class="yoast-schema-graph"') !== false, 'yoast simulator emits schema graph script');

$rmHtml = $invokeProtected($seoRef, $seo, 'render_rank_math_head', array($postStub, 'https://wp.example.com.np/subsite/sample-headless-post'));
assert_same(true, strpos($rmHtml, '<meta property="og:image" content="https://example.com.np/og.webp" />') !== false, 'rank math head rewrites og:image URL');

if ($failures > 0) {
    fwrite(STDERR, "FAILED: {$failures} of {$assertions} assertions failed.\n");
    exit(1);
}

echo "PASS: {$assertions}/{$assertions} AstroPress unit assertions passed.\n";
