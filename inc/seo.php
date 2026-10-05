<?php
/** Personal-site SEO, with editable metadata and no business/service identity. */
defined('ABSPATH') || exit;

function maranda_seo_enabled(): bool {
    return !defined('WPSEO_VERSION') && !defined('RANK_MATH_VERSION') && !defined('AIOSEO_VERSION');
}
function maranda_seo_defaults(): array {
    return [
        'front-page' => ['Mario Maranda | Parcours, projets et carnet personnel', 'Découvrez le carnet personnel de Mario Maranda : parcours professionnel, projets numériques, observations du réseau routier et réflexions.'],
        'a-propos' => ['À propos de Mario Maranda | Passion, parcours et projets', 'Qui est Mario Maranda ? Découvrez mon parcours, ma façon de fonctionner et ce qui m’anime : le terrain, les routes et les outils numériques.'],
        'profil-professionnel' => ['Parcours professionnel | Mario Maranda', 'Mon parcours en transport, suivi de chantiers, coordination et informatique : emplois antérieurs, responsabilités, réalisations et formations.'],
        'works' => ['Projets et explorations numériques | Mario Maranda', 'Découvrez mes projets personnels, dont InspeKT et maranda.dev, ainsi que mes explorations en documentation terrain et en développement numérique.'],
        'inspekt' => ['InspeKT : relevés du réseau routier | Mario Maranda', 'InspeKT, mon outil pour réunir photos, positions GPS et observations du réseau routier. Découvrez son origine et ma démarche de documentation terrain.'],
        'blog' => ['Carnet personnel : routes, projets et idées | Mario Maranda', 'Mes textes sur les routes, mes projets, la technologie et les questions qui m’occupent. Le carnet personnel de Mario Maranda, à ma façon.'],
        'carte-des-releves' => ['Carte de mes observations routières | Mario Maranda', 'Explorez mes observations du réseau routier sur une carte interactive : photographies, localisations et relevés publics documentés avec InspeKT.'],
        'vigie-reseau' => ['Vigie Réseau : suivis et dossiers routiers | Mario Maranda', 'Ma veille personnelle sur le réseau routier : dossiers, chronologies, observations et sources publiques pour suivre les situations dans le temps.'],
        'contact' => ['Me contacter | Mario Maranda', 'Une question, une idée ou l’envie de discuter de mon parcours et de mes projets ? Contactez Mario Maranda avec le formulaire de ce site personnel.'],
        'confidentialite' => ['Confidentialité et protection des données | Mario Maranda', 'Comment mon site personnel traite les données du formulaire de contact et limite les messages indésirables. Consultez les informations de confidentialité.'],
    ];
}
function maranda_seo_legacy_pages(): array {
    return ['services', 'mandats', 'municipalites', 'methode', 'mission', 'surveillance-preventive', 'circulation', 'sample-page', 'sample-page-2'];
}
function maranda_seo_redirects(): array {
    return ['home' => '/', 'resume' => '/profil-professionnel/', 'skills' => '/profil-professionnel/', 'releves' => '/carte-des-releves/'];
}
function maranda_seo_legacy_types(): array { return ['mm_solution', 'mm_client', 'mm_proof']; }
function maranda_seo_preview(): bool {
    return is_preview() || isset($_GET['wpvibe_preview']) || str_contains(get_stylesheet(), '-wpvibe-draft');
}
function maranda_seo_text(string $text, int $limit = 160): string {
    $text = preg_replace('~</(?:p|h[1-6]|li|div|section)>~i', ' ', $text) ?? $text;
    $text = wp_strip_all_tags(strip_shortcodes($text));
    $text = preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
    return trim(wp_html_excerpt(trim($text), $limit, '…'));
}
function maranda_seo_post_data(WP_Post $post): array {
    $key = $post->ID === (int) get_option('page_on_front') ? 'front-page' : $post->post_name;
    $defaults = maranda_seo_defaults()[$key] ?? [get_the_title($post) . ' | Mario Maranda', ''];
    $title = trim((string) get_post_meta($post->ID, 'maranda_seo_title', true));
    $description = trim((string) get_post_meta($post->ID, 'maranda_seo_description', true));
    if ($description === '') {
        $description = $defaults[1];
        if ($description === '' && !post_password_required($post)) {
            $source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
            if ($post->post_type === 'mm_vigie' && function_exists('maranda_vigie_dynamic_markup')) {
                $record = maranda_vigie_dynamic_markup($post->ID);
                if (!empty($record['summary'])) $source = $record['summary'];
            }
            $description = maranda_seo_text($source);
        }
    }
    return ['title' => $title !== '' ? $title : $defaults[0], 'description' => $description];
}
function maranda_seo_context_post(): ?WP_Post {
    $posts_page = (int) get_option('page_for_posts');
    $post = is_home() ? ($posts_page > 0 ? get_post($posts_page) : null) : (is_singular() ? get_queried_object() : null);
    return $post instanceof WP_Post ? $post : null;
}
function maranda_seo_data(): array {
    $post = maranda_seo_context_post();
    if ($post) $data = maranda_seo_post_data($post);
    elseif (is_post_type_archive('mm_vigie')) {
        [$title, $description] = maranda_seo_defaults()['vigie-reseau'];
        $data = compact('title', 'description');
    } else {
        $name = is_search() ? 'Recherche' : (is_404() ? 'Page introuvable' : wp_strip_all_tags(get_the_archive_title()));
        $data = ['title' => ($name !== '' ? $name . ' | ' : '') . 'Mario Maranda', 'description' => maranda_seo_text(get_the_archive_description())];
    }
    $paged = max((int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1) $data['title'] .= ' | Page ' . $paged;
    return $data;
}
function maranda_seo_canonical(): string {
    $post = maranda_seo_context_post();
    if ($post) {
        $url = (string) (wp_get_canonical_url($post) ?: get_permalink($post));
        $paged = (int) get_query_var('paged');
        return is_home() && $paged > 1 ? trailingslashit($url) . user_trailingslashit('page/' . $paged, 'paged') : $url;
    }
    if (is_post_type_archive('mm_vigie')) $url = get_post_type_archive_link('mm_vigie');
    elseif (is_category() || is_tag() || is_tax()) $url = get_term_link(get_queried_object());
    elseif (is_post_type_archive()) $url = get_post_type_archive_link(get_query_var('post_type'));
    else return '';
    if (!is_string($url) || $url === '') return '';
    $paged = (int) get_query_var('paged');
    return $paged > 1 ? trailingslashit($url) . user_trailingslashit('page/' . $paged, 'paged') : $url;
}
function maranda_seo_noindex(): bool {
    if (maranda_seo_preview() || is_search() || is_404() || is_author() || is_date() || is_attachment()) return true;
    if (is_page(array_merge(maranda_seo_legacy_pages(), array_keys(maranda_seo_redirects())))) return true;
    if (is_singular(maranda_seo_legacy_types()) || is_post_type_archive(maranda_seo_legacy_types())) return true;
    $post = maranda_seo_context_post();
    return $post && (post_password_required($post) || (bool) get_post_meta($post->ID, 'maranda_seo_noindex', true));
}
add_action('init', static function () {
    if (maranda_seo_enabled()) remove_action('wp_head', 'rel_canonical');
    foreach (['page', 'post', 'mm_vigie', 'mm_project'] as $type) {
        foreach (['title', 'description'] as $field) register_post_meta($type, 'maranda_seo_' . $field, [
            'single' => true, 'type' => 'string', 'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => static fn($allowed, $key, $id) => current_user_can('edit_post', $id),
        ]);
        register_post_meta($type, 'maranda_seo_noindex', [
            'single' => true, 'type' => 'boolean', 'show_in_rest' => true,
            'auth_callback' => static fn($allowed, $key, $id) => current_user_can('edit_post', $id),
        ]);
    }
});
add_filter('pre_get_document_title', static fn($title) => maranda_seo_enabled() ? maranda_seo_data()['title'] : $title);
add_filter('wp_robots', static function (array $robots): array {
    if (maranda_seo_noindex()) { $robots['noindex'] = true; unset($robots['index']); }
    if (maranda_seo_preview()) { $robots['nofollow'] = true; unset($robots['follow']); }
    return $robots;
});
add_action('template_redirect', static function () {
    if (maranda_seo_preview() || is_admin() || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return;
    foreach (maranda_seo_redirects() as $old => $new) {
        if (is_page($old)) { wp_safe_redirect(home_url($new), 301); exit; }
    }
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($path === wp_parse_url(home_url('/sitemap.xml'), PHP_URL_PATH)) {
        wp_safe_redirect(home_url('/wp-sitemap.xml'), 301); exit;
    }
}, 1);
add_filter('wp_sitemaps_add_provider', static fn($provider, $name) => $name === 'users' ? false : $provider, 10, 2);
add_filter('wp_sitemaps_post_types', static function (array $types): array {
    foreach (maranda_seo_legacy_types() as $type) unset($types[$type]);
    return $types;
});
add_filter('wp_sitemaps_posts_query_args', static function (array $args, string $type): array {
    if ($type === 'page') {
        $ids = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            'post_name__in' => array_merge(maranda_seo_legacy_pages(), array_keys(maranda_seo_redirects()))]);
        $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'] ?? [], $ids)));
    }
    $visible = ['relation' => 'OR', ['key' => 'maranda_seo_noindex', 'compare' => 'NOT EXISTS'], ['key' => 'maranda_seo_noindex', 'value' => '1', 'compare' => '!=']];
    $args['meta_query'] = empty($args['meta_query']) ? $visible : ['relation' => 'AND', $args['meta_query'], $visible];
    $args['has_password'] = false;
    return $args;
}, 10, 2);
add_action('wp_head', static function () {
    if (!maranda_seo_enabled()) return;
    $data = maranda_seo_data(); $url = maranda_seo_canonical(); $post = maranda_seo_context_post();
    $image = $post ? get_the_post_thumbnail_url($post, 'full') : false;
    $fallback_image = !$image; $image = $image ?: get_theme_file_uri('screenshot.png');
    $article = $post && $post->post_type === 'post';
    if ($url !== '') echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    $tags = ['description' => $data['description'], 'og:title' => $data['title'], 'og:description' => $data['description'],
        'og:type' => $article ? 'article' : 'website', 'og:site_name' => 'Mario Maranda', 'og:locale' => 'fr_CA',
        'og:url' => $url, 'og:image' => $image, 'og:image:alt' => $fallback_image ? 'Le carnet personnel de Mario Maranda — maranda.dev' : get_the_title($post),
        'twitter:card' => 'summary_large_image', 'twitter:title' => $data['title'], 'twitter:description' => $data['description'], 'twitter:image' => $image];
    if ($fallback_image) { $tags['og:image:width'] = '1200'; $tags['og:image:height'] = '900'; }
    foreach ($tags as $name => $value) {
        if ($value === '') continue;
        $attribute = str_starts_with($name, 'og:') ? 'property' : 'name';
        echo '<meta ' . $attribute . '="' . esc_attr($name) . '" content="' . esc_attr((string) $value) . '">' . "\n";
    }
    if ($url === '' || maranda_seo_noindex()) return;
    $home = home_url('/'); $person = $home . '#mario-maranda'; $website = $home . '#website';
    $graph = [
        ['@type' => 'Person', '@id' => $person, 'name' => 'Mario Maranda', 'url' => home_url('/a-propos/'),
            'knowsAbout' => ['Réseau routier', 'Transport', 'Documentation terrain', 'Projets numériques']],
        ['@type' => 'WebSite', '@id' => $website, 'url' => $home, 'name' => 'Mario Maranda', 'alternateName' => 'maranda.dev',
            'description' => maranda_seo_defaults()['front-page'][1], 'inLanguage' => 'fr-CA', 'publisher' => ['@id' => $person]],
    ];
    $page = ['@type' => is_home() || is_archive() ? 'CollectionPage' : 'WebPage', '@id' => $url . '#webpage',
        'url' => $url, 'name' => $data['title'], 'description' => $data['description'], 'inLanguage' => 'fr-CA',
        'isPartOf' => ['@id' => $website], 'about' => ['@id' => $person]];
    if (is_page(['a-propos', 'profil-professionnel'])) { $page['@type'] = 'ProfilePage'; $page['mainEntity'] = ['@id' => $person]; }
    if (is_page('contact')) $page['@type'] = 'ContactPage';
    $graph[] = $page;
    if ($post && in_array($post->post_type, ['post', 'mm_vigie'], true)) {
        $graph[] = ['@type' => $article ? 'BlogPosting' : 'CreativeWork', '@id' => $url . '#article',
            'headline' => get_the_title($post), 'description' => $data['description'], 'url' => $url,
            'author' => ['@id' => $person], 'mainEntityOfPage' => ['@id' => $page['@id']],
            'datePublished' => get_post_time(DATE_W3C, true, $post), 'dateModified' => get_post_modified_time(DATE_W3C, true, $post), 'image' => $image];
    }
    echo '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>' . "\n";
}, 5);

add_action('add_meta_boxes', static function () {
    if (!maranda_seo_enabled()) return;
    foreach (['page', 'post', 'mm_vigie', 'mm_project'] as $type) add_meta_box('maranda-seo', 'Référencement et partage', 'maranda_seo_meta_box', $type, 'normal');
});
function maranda_seo_meta_box(WP_Post $post): void {
    $defaults = maranda_seo_post_data($post);
    wp_nonce_field('maranda_seo_save', 'maranda_seo_nonce');
    echo '<p>Ces champs changent l’aperçu pour les moteurs de recherche et les réseaux sociaux, sans modifier la présentation de la page. Laissez-les vides pour utiliser les valeurs automatiques.</p>';
    foreach (['title' => 'Titre pour les moteurs de recherche', 'description' => 'Description pour les moteurs de recherche'] as $key => $label) {
        $name = 'maranda_seo_' . $key;
        echo '<p><label for="' . esc_attr($name) . '"><strong>' . esc_html($label) . '</strong></label><br><input class="widefat" type="text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) get_post_meta($post->ID, $name, true)) . '" placeholder="' . esc_attr($defaults[$key]) . '"></p>';
    }
    echo '<p><label><input type="checkbox" name="maranda_seo_noindex" value="1" ' . checked((bool) get_post_meta($post->ID, 'maranda_seo_noindex', true), true, false) . '> Exclure cette page des résultats de recherche et du plan du site</label></p>';
    if (in_array($post->post_name, maranda_seo_legacy_pages(), true)) echo '<p>Cette ancienne page commerciale ou de démonstration est déjà exclue du référencement par le thème.</p>';
}
add_action('save_post', static function (int $id) {
    if (!isset($_POST['maranda_seo_nonce']) || !is_string($_POST['maranda_seo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['maranda_seo_nonce'])), 'maranda_seo_save')
        || !current_user_can('edit_post', $id) || wp_is_post_autosave($id) || wp_is_post_revision($id)) return;
    foreach (['title', 'description'] as $field) {
        $key = 'maranda_seo_' . $field;
        if (!isset($_POST[$key]) || !is_string($_POST[$key])) continue;
        $value = sanitize_text_field(wp_unslash($_POST[$key]));
        if ($value === '') delete_post_meta($id, $key); else update_post_meta($id, $key, $value);
    }
    if (!empty($_POST['maranda_seo_noindex'])) update_post_meta($id, 'maranda_seo_noindex', true);
    else delete_post_meta($id, 'maranda_seo_noindex');
});
add_action('admin_menu', static function () {
    add_theme_page('Référencement maranda', 'Référencement', 'manage_options', 'maranda-seo', 'maranda_seo_dashboard');
});
function maranda_seo_dashboard(): void {
    if (!current_user_can('manage_options')) wp_die('Accès refusé.');
    echo '<div class="wrap"><h1>Référencement du carnet personnel</h1><p>Positionnement : Mario Maranda, son parcours, ses projets et sa passion pour le réseau routier. L’identité structurée est une personne, sans offre de services commerciaux.</p>';
    if (!maranda_seo_enabled()) { echo '<p>Une extension SEO active gère les titres et les aperçus. Le thème lui laisse la priorité.</p></div>'; return; }
    echo '<p>Pour modifier une valeur, ouvrez la page et utilisez le panneau <strong>Référencement et partage</strong>. Les valeurs automatiques sont présentées ci-dessous.</p><table class="widefat striped"><thead><tr><th>Page</th><th>Titre</th><th>Description</th></tr></thead><tbody>';
    foreach (maranda_seo_defaults() as $slug => $defaults) {
        $post = $slug === 'front-page' ? get_post((int) get_option('page_on_front')) : get_page_by_path($slug);
        $data = $post instanceof WP_Post ? maranda_seo_post_data($post) : ['title' => $defaults[0], 'description' => $defaults[1]];
        echo '<tr><td>' . ($post instanceof WP_Post ? '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html(get_the_title($post)) . '</a>' : esc_html($slug)) . '</td><td>' . esc_html($data['title']) . '</td><td>' . esc_html($data['description']) . '</td></tr>';
    }
    echo '</tbody></table><h2>Indexation</h2><p>Les anciens contenus commerciaux et les pages de démonstration sont exclus des résultats de recherche et du plan du site : ' . esc_html(implode(', ', maranda_seo_legacy_pages())) . '.</p><p>Les anciennes adresses Home, Expériences, Skills et Relevés redirigent vers leurs pages actuelles correspondantes.</p><p><a href="' . esc_url(home_url('/wp-sitemap.xml')) . '">Ouvrir le plan du site WordPress</a></p><p>Les descriptions et titres sont proposés aux moteurs de recherche ; leur présentation finale dépend du moteur. Aucun résultat de classement n’est garanti.</p></div>';
}
