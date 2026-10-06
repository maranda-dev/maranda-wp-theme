<?php
/** Regression checks for indexing, canonical pagination and safe structured metadata. */
define('ABSPATH', __DIR__);
$hooks = []; $context = []; $meta = []; $posts = [];
class WP_Post {
    public function __construct(public int $ID, public string $post_name, public string $post_type = 'page', public string $post_content = '', public string $post_excerpt = '') {}
}
function add_action($name, $callback, ...$args) { global $hooks; $hooks[$name] = $callback; }
function add_filter($name, $callback, ...$args) { add_action($name, $callback); }
function flag($key) { global $context; return $context[$key] ?? false; }
function is_home() { return flag('home'); }
function is_singular($types = null) { $post = get_queried_object(); return flag('singular') && (!$types || in_array($post->post_type, (array) $types, true)); }
function is_page($slugs = null) { $post = get_queried_object(); return is_singular() && $post->post_type === 'page' && (!$slugs || in_array($post->post_name, (array) $slugs, true)); }
function is_post_type_archive($types = null) { return flag('archive') && (!$types || in_array(flag('post_type'), (array) $types, true)); }
function is_archive() { return flag('archive'); }
function is_search() { return flag('search'); }
function is_404() { return flag('404'); }
function is_author() { return flag('author'); }
function is_date() { return flag('date'); }
function is_attachment() { return false; }
function is_preview() { return flag('preview'); }
function is_category() { return false; }
function is_tag() { return false; }
function is_tax() { return false; }
function get_stylesheet() { return flag('stylesheet') ?: 'bsir-wordpress'; }
function get_option($key) { return ['page_on_front' => 40, 'page_for_posts' => 50][$key] ?? ''; }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function get_queried_object() { return get_post(flag('id')); }
function get_post_meta($id, $key, $single) { global $meta; return $meta[$id][$key] ?? ''; }
function get_the_title($post) { return ['accueil' => 'Accueil', 'blog' => 'Blog', 'test' => 'Mon récit'][$post->post_name] ?? $post->post_name; }
function post_password_required($post) { return flag('password'); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function strip_shortcodes($s) { return preg_replace('/\[[^\]]*\]/', '', $s); }
function wp_html_excerpt($s, $limit, $more) { return mb_strlen($s) > $limit ? mb_substr($s, 0, $limit) . $more : $s; }
function get_query_var($key) { return flag($key); }
function get_permalink($post) { return home_url($post->ID === 40 ? '/' : '/' . $post->post_name . '/'); }
function wp_get_canonical_url($post) { return get_permalink($post); }
function get_post_type_archive_link($type) { return home_url('/vigie-reseau/'); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function user_trailingslashit($s, $type) { return trailingslashit($s); }
function get_the_archive_title() { return 'Archives'; }
function get_the_archive_description() { return ''; }
function home_url($path) { return 'https://www.maranda.dev' . $path; }
function get_the_post_thumbnail_url($post, $size) { return false; }
function get_theme_file_uri($file) { return home_url('/wp-content/themes/bsir-wordpress/' . $file); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return esc_attr($s); }
function wp_json_encode($value, $flags) { return json_encode($value, $flags); }
function get_posts($args) { return [8057, 8059]; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
require __DIR__ . '/../inc/seo.php';
$posts[40] = new WP_Post(40, 'accueil'); $posts[50] = new WP_Post(50, 'blog');
$posts[51] = new WP_Post(51, 'test', 'post', '<h2>Mon récit</h2><p>Le texte.</p>');
$posts[8057] = new WP_Post(8057, 'services');
$context = ['singular' => true, 'id' => 40];
check(maranda_seo_data()['title'] === 'Mario Maranda | Parcours, projets et carnet personnel', 'Home identity must be personal.');
check(!maranda_seo_noindex(), 'Public homepage must stay indexable.');
$context['id'] = 8057;
check(maranda_seo_noindex(), 'Old services must be noindex.');
$robots = $hooks['wp_robots'](['index' => true, 'max-image-preview' => 'large']);
check(isset($robots['noindex']) && !isset($robots['index']), 'No contradictory indexing directives.');
$context = ['archive' => true, 'post_type' => 'mm_vigie', 'paged' => 2];
check(maranda_seo_canonical() === home_url('/vigie-reseau/page/2/'), 'Archive pagination must have its own canonical.');
check(!maranda_seo_noindex(), 'Vigie archive must be indexable.');
$context = ['home' => true, 'id' => 51, 'paged' => 2];
check(str_starts_with(maranda_seo_data()['title'], 'Carnet personnel'), 'Blog metadata must use its page, never the first article.');
check(maranda_seo_canonical() === home_url('/blog/page/2/'), 'Blog pagination must not canonicalize to page one.');
$context = ['singular' => true, 'id' => 40, 'stylesheet' => 'bsir-wordpress-wpvibe-draft'];
check(maranda_seo_noindex(), 'Draft theme preview must be noindex.');
$context = ['singular' => true, 'id' => 40]; $_GET['wpvibe_preview'] = 'preview';
check(maranda_seo_noindex(), 'Preview URLs must be noindex.'); unset($_GET['wpvibe_preview']);
$args = $hooks['wp_sitemaps_posts_query_args'](['post__not_in' => [1], 'meta_query' => ['key' => 'existing']], 'page');
check($args['post__not_in'] === [1, 8057, 8059], 'Sitemap exclusions must preserve other exclusions.');
check($args['meta_query']['relation'] === 'AND' && $args['has_password'] === false, 'Sitemap must preserve filters and exclude password-protected content.');
check($hooks['wp_sitemaps_add_provider']('users', 'users') === false, 'Duplicate author archives must not be advertised.');
check(maranda_seo_text('<p>Une route</p><p>Une idée</p>') === 'Une route Une idée', 'Metadata must not join adjacent paragraphs.');
$meta[40] = ['maranda_seo_title' => '</script><script>alert(1)</script>', 'maranda_seo_description' => 'Mon texte'];
check(maranda_seo_data()['description'] === 'Mon texte', 'Editable metadata must win over defaults.');
ob_start(); $hooks['wp_head'](); $html = ob_get_clean();
check(str_contains($html, 'assets/social-card.png') && str_contains($html, 'content="630"'), 'Sharing fallback must use the brand image in 1200×630 format.');
check(substr_count($html, 'rel="canonical"') === 1, 'Only one canonical may be emitted.');
check(!str_contains($html, '<script>alert(1)</script>'), 'Metadata must not break out of JSON or attributes.');
preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
$graph = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
check($graph[0]['@type'] === 'Person' && $graph[1]['@type'] === 'WebSite', 'Structured identity must describe a person and personal website.');
check(!str_contains(json_encode($graph), 'LocalBusiness'), 'No commercial structured identity.');
define('WPSEO_VERSION', 'test');
check($hooks['pre_get_document_title']('Plugin title') === 'Plugin title', 'An SEO plugin must take priority.');
ob_start(); $hooks['wp_head'](); check(ob_get_clean() === '', 'Do not duplicate an SEO plugin metadata.');
echo "SEO regression checks passed.\n";
