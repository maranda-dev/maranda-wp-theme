<?php
/** Isolated updater regression checks; no WordPress database or credentials. */
define('ABSPATH', __DIR__);
define('MARANDA_THEME_VERSION', '0.1.1');
$filters = [];
$requests = [];
$release_override = null;
class WP_Error { public function __construct(public string $code, public string $message) {} }
function get_transient($name) { return false; }
function set_transient($name, $value, $ttl) {}
function add_action(...$args) {}
function add_filter($name, $callback, ...$args) { global $filters; $filters[$name] = $callback; }
function get_template() { return 'maranda-wp-theme'; }
function absint($value) { return abs((int) $value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_get($url, $args) {
    global $requests, $release_override;
    $requests[] = [$url, $args];
    return ['code' => 200, 'body' => json_encode($release_override ?? [
        'tag_name' => 'v0.1.1', 'draft' => false, 'prerelease' => false,
        'assets' => [['id' => 123, 'state' => 'uploaded', 'name' => 'maranda-wp-theme-0.1.1.zip', 'digest' => 'sha256:' . str_repeat('a', 64)]],
    ])];
}
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
require __DIR__ . '/../inc/github-updates.php';
$release = maranda_github_release();
check(!is_wp_error($release), 'Valid release must be recognized');
check($requests[0][0] === 'https://api.github.com/repos/maranda-dev/maranda-wp-theme/releases/latest', 'Release lookup must use the renamed account');
check($requests[0][1]['redirection'] === 0, 'Authenticated API requests must not forward credentials via redirects');
check($release['package'] === 'https://api.github.com/repos/maranda-dev/maranda-wp-theme/releases/assets/123', 'Asset URL must use the renamed account');
$update = $filters['update_themes_github.com'](false, ['UpdateURI' => 'https://github.com/maranda-dev/maranda-wp-theme'], 'maranda-wp-theme');
check($update['version'] === '0.1.1', 'WordPress must receive the latest version');
$other = $filters['update_themes_github.com']('unchanged', ['UpdateURI' => 'https://github.com/maranda-dev/maranda-wp-theme'], 'another-theme');
check($other === 'unchanged', 'Other themes must remain untouched');
check(!isset($requests[0][1]['headers']['Authorization']), 'Public repository must not receive a private credential');
$base = ['tag_name' => 'v0.1.1', 'draft' => false, 'prerelease' => false, 'assets' => [['id' => 123, 'state' => 'uploaded', 'name' => 'maranda-wp-theme-0.1.1.zip', 'digest' => 'sha256:' . str_repeat('a', 64)]]];
foreach (['draft', 'prerelease'] as $flag) {
    $release_override = $base; $release_override[$flag] = true;
    check(is_wp_error(maranda_github_release()), 'Unstable releases must be rejected');
}
$release_override = $base; $release_override['tag_name'] = 'main';
check(is_wp_error(maranda_github_release()), 'A branch must never become an update');
$release_override = $base; unset($release_override['assets'][0]['digest']);
check(is_wp_error(maranda_github_release()), 'A ZIP without its hash must be rejected');
$release_override = $base; $release_override['assets'][0]['name'] = 'other-theme.zip';
check(is_wp_error(maranda_github_release()), 'A different ZIP must be rejected');
check($filters['upgrader_pre_download']('untouched', 'https://example.com/other.zip') === 'untouched', 'Downloads for other themes must be untouched');
echo "GitHub updater regression checks passed.\n";
