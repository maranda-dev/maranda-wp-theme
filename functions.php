<?php
defined('ABSPATH') || exit;
define('MARANDA_THEME_VERSION', '0.1.7');
require_once get_theme_file_path('inc/vigie-record.php');
require_once get_theme_file_path('inc/personal-note.php');
require_once get_theme_file_path('inc/github-updates.php');
/* Render the Posts page introduction without borrowing the current article context. */
add_shortcode('maranda_blog_introduction', static function (): string {
    $page = get_post((int) get_option('page_for_posts'));
    return $page instanceof WP_Post ? do_blocks($page->post_content) : '';
});
add_action('after_setup_theme', static function () {
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_theme_support('post-thumbnails');
    add_editor_style('assets/maranda.css');
});
add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style('maranda', get_theme_file_uri('assets/maranda.css'), [], MARANDA_THEME_VERSION);
});
/* Keep existing content types available; do not write settings or rewrite rules in a preview. */
add_action('init', static function () {
    // Preserve the existing site content types when switching from Mario Maranda's theme.
    if (!post_type_exists('mm_vigie')) {
        register_post_type('mm_vigie', [
            'labels' => ['name' => 'Vigie Réseau', 'singular_name' => 'Dossier Vigie'],
            'public' => true, 'show_in_rest' => true, 'has_archive' => 'vigie-reseau',
            'rewrite' => ['slug' => 'vigie-reseau', 'with_front' => false], 'menu_icon' => 'dashicons-visibility',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields']
        ]);
    }
    foreach (['mm_vigie_asset' => 'Actifs', 'mm_vigie_nature' => 'Natures', 'mm_vigie_intervention' => 'Interventions', 'mm_vigie_contract' => 'Contrats', 'mm_vigie_region' => 'Régions'] as $taxonomy => $label) {
        $slugs = ['mm_vigie_asset' => 'actif', 'mm_vigie_nature' => 'nature', 'mm_vigie_intervention' => 'intervention', 'mm_vigie_contract' => 'contrat', 'mm_vigie_region' => 'region'];
        if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy, 'mm_vigie', ['label' => $label, 'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'vigie-reseau/' . $slugs[$taxonomy], 'with_front' => false]]);
    }
    foreach (['mm_project' => 'Projets', 'mm_solution' => 'Solutions', 'mm_experience' => 'Expériences', 'mm_competency' => 'Perfectionnements', 'mm_client' => 'Clients', 'mm_proof' => 'Preuves professionnelles'] as $type => $label) {
        if (!post_type_exists($type)) {
            $public = in_array($type, ['mm_project', 'mm_solution'], true);
            $slug = $type === 'mm_project' ? 'projets' : 'solutions';
            register_post_type($type, ['label' => $label, 'public' => $public, 'publicly_queryable' => $public, 'show_ui' => true, 'show_in_rest' => true, 'has_archive' => $public ? $slug : false, 'rewrite' => $public ? ['slug' => $slug, 'with_front' => false] : false, 'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions']]);
        }
    }
    if (!taxonomy_exists('mm_sector')) register_taxonomy('mm_sector', ['mm_solution', 'mm_project'], ['label' => 'Secteurs', 'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rewrite' => ['slug' => 'secteur', 'with_front' => false]]);
}, 20);
add_filter('query_vars', static function ($vars) { $vars[] = 'bsir_route'; return $vars; });
/* Resolve routes left by the previous theme without changing the saved permalink rules. */
add_filter('request', static function ($vars) {
    if (!isset($vars['bsir_route'])) return $vars;
    $route = trim((string) $vars['bsir_route'], '/');
    $page = get_page_by_path($route);
    if ($route === 'carte-interactive') { unset($vars['bsir_route']); $vars['page_id'] = 8105; }
    elseif ($page) { unset($vars['bsir_route']); $vars['page_id'] = $page->ID; }
    elseif (str_starts_with($route, 'vigie-reseau/')) {
        unset($vars['bsir_route']);
        $vars['post_type'] = 'mm_vigie';
        $vars['name'] = basename($route);
    } elseif ($route === 'vigie-reseau') {
        unset($vars['bsir_route']); $vars['post_type'] = 'mm_vigie';
    }
    return $vars;
});

/* Preserve the existing blog URLs. This registers rules in memory only. */
add_action('init', static function () {
    add_rewrite_rule('^blog/([^/]+)/([0-9]+)/?$', 'index.php?post_type=post&name=$matches[1]&page=$matches[2]', 'top');
    add_rewrite_rule('^blog/([^/]+)/?$', 'index.php?post_type=post&name=$matches[1]', 'top');
}, 99);
add_filter('post_link', static function ($url, $post, $leavename) {
    if ($post->post_type !== 'post' || in_array($post->post_status, ['draft', 'pending', 'auto-draft'], true)) return $url;
    return home_url(user_trailingslashit('blog/' . ($leavename ? '%postname%' : $post->post_name), 'single'));
}, 10, 3);

require_once get_theme_file_path('inc/maranda-contact.php');
add_shortcode('maranda_map', static function () {
    wp_enqueue_style('maranda-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_style('maranda-map', get_theme_file_uri('assets/maranda-map.css'), ['maranda-leaflet'], '0.1.7');
    wp_enqueue_script('maranda-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    wp_enqueue_script('maranda-map', get_theme_file_uri('assets/carte-interactive.js'), ['maranda-leaflet'], '0.1.7', true);
    $endpoint = rest_url('bsir/v1/public-map');
    if (isset($_GET['wpvibe_preview']) && is_string($_GET['wpvibe_preview'])) $endpoint = add_query_arg('wpvibe_preview', sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])), $endpoint);
    ob_start(); ?>
<div class="rr-map-app" data-inspekt-map data-endpoint="<?php echo esc_url($endpoint); ?>">
  
  
  <div class="rr-map-layout">
    <aside class="rr-map-sidebar" id="rr-map-sidebar" aria-label="Filtres des relevés">
      <div class="rr-map-sidebar__heading">
        <p>Relevés terrain</p>
        <h2>Relevés terrain</h2>
        <p>Une carte de situations que j’ai documentées sur le réseau routier.</p>
      </div>
      <div class="rr-map-groups" data-groups></div>
      <div class="rr-map-sidebar__footer">
        <p data-count aria-live="polite">Chargement des relevés…</p>
        <button type="button" data-reset>Afficher tous les relevés</button>
      </div>
    </aside>
    <section class="rr-map-stage" aria-label="Carte des relevés publics">
      <button class="rr-map-mobile-filter" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="rr-map-sidebar">
        <span aria-hidden="true">☰</span> Filtrer les relevés
      </button>
      <div class="rr-map-basemaps" role="group" aria-label="Fond de carte">
        <button type="button" class="is-active" data-basemap="plan" aria-pressed="true">Plan</button>
        <button type="button" data-basemap="satellite" aria-pressed="false">Satellite</button>
      </div>
      <div class="rr-map-canvas" data-canvas role="region" aria-label="Carte interactive des relevés InspeKT"></div>
      <p class="rr-map-status" data-status role="status">Chargement des relevés…</p>
      <aside class="rr-map-detail" data-detail aria-label="Fiche du relevé sélectionné" hidden></aside>
      <button class="rr-map-fullscreen" type="button" data-fullscreen aria-label="Afficher la carte en plein écran" title="Plein écran">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"></path></svg>
      </button>
    </section>
  </div>
  <div class="rr-map-scrim" data-scrim hidden></div>
  <noscript>JavaScript doit être activé pour consulter la carte interactive.</noscript>
</div>

<?php return preg_replace('/>\s+</', '><', trim(ob_get_clean()));
});
add_action('rest_api_init', static function () {
    register_rest_route('bsir/v1', '/public-map', ['methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => static function () {
        $data = get_transient('bsir_public_map_v1');
        if (false === $data) {
            // Fixed public upstream: no credentials, arbitrary URLs or private reports.
            $remote = wp_remote_get('https://inspekt.mariomaranda.ca/api/public/map/reports.geojson', ['timeout' => 15, 'redirection' => 2, 'limit_response_size' => 2097152]);
            if (is_wp_error($remote) || wp_remote_retrieve_response_code($remote) !== 200) return new WP_Error('bsir_map_unavailable', 'Les relevés publics sont temporairement indisponibles.', ['status' => 502]);
            $data = json_decode(wp_remote_retrieve_body($remote), true);
            if (!is_array($data) || ($data['type'] ?? '') !== 'FeatureCollection' || !is_array($data['features'] ?? null)) return new WP_Error('bsir_map_invalid', 'La source des relevés a retourné un format invalide.', ['status' => 502]);
            set_transient('bsir_public_map_v1', $data, 60);
        }
        $response = new WP_REST_Response($data);
        $response->header('Cache-Control', 'public, max-age=60');
        return $response;
    }]);
});


add_action('wp_enqueue_scripts', static function () {
    if (!is_page('carte-des-releves')) return;
    wp_enqueue_style('maranda-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_style('maranda-map', get_theme_file_uri('assets/maranda-map.css'), ['maranda-leaflet'], '0.1.7');
});

/* Preserve the original Vigie cards and their editable WordPress fields. */
function maranda_original_vigie_field(int $post_id, string $key, string $fallback = ''): string {
    $value = trim((string) get_post_meta($post_id, 'rr_vigie_' . $key, true));
    return $value !== '' ? $value : $fallback;
}

function maranda_original_vigie_card_timestamp(int $post_id): int {
    $value = maranda_original_vigie_field($post_id, 'date');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        if ($date instanceof DateTimeImmutable) return $date->getTimestamp();
    }
    return get_post_timestamp($post_id);
}

function maranda_original_vigie_term_names(int $post_id, string $taxonomy): array {
    $terms = get_the_terms($post_id, $taxonomy);
    return (!is_wp_error($terms) && $terms) ? array_values(wp_list_pluck($terms, 'name')) : [];
}

function maranda_original_vigie_image(int $post_id): array {
    $image = get_the_post_thumbnail_url($post_id, 'large');
    $alt = trim((string) get_post_meta(get_post_thumbnail_id($post_id), '_wp_attachment_image_alt', true));
    $credit = '';

    if (!$image) {
        $image = maranda_original_vigie_field($post_id, 'image_url');
        $alt = maranda_original_vigie_field($post_id, 'image_alt');
        $credit = maranda_original_vigie_field($post_id, 'image_credit');
    }
    if (!$image && preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', (string) get_post_field('post_content', $post_id), $match)) {
        $image = esc_url_raw(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
    }

    return ['url' => $image ?: '', 'alt' => $alt, 'credit' => $credit];
}

function maranda_original_vigie_card_data(int $post_id): array {
    $assets = maranda_original_vigie_term_names($post_id, 'mm_vigie_asset');
    $natures = maranda_original_vigie_term_names($post_id, 'mm_vigie_nature');
    $regions = maranda_original_vigie_term_names($post_id, 'mm_vigie_region');
    $interventions = maranda_original_vigie_term_names($post_id, 'mm_vigie_intervention');
    $asset = $assets[0] ?? 'Réseau routier';
    $category_fallbacks = ['Pont' => 'Structure', 'Ponceau' => 'Drainage', 'Chaussée' => 'Chaussée'];
    $excerpt = trim((string) get_the_excerpt($post_id));
    if ($excerpt === '') $excerpt = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 34);

    return [
        'category' => maranda_original_vigie_field($post_id, 'category', $category_fallbacks[$asset] ?? ($natures[0] ?? $asset)),
        'infrastructure' => maranda_original_vigie_field($post_id, 'infrastructure', $asset),
        'territory' => maranda_original_vigie_field($post_id, 'territory', get_the_title($post_id)),
        'period' => maranda_original_vigie_field($post_id, 'period', get_the_date('j F Y', $post_id)),
        'status' => maranda_original_vigie_field($post_id, 'status', $interventions[0] ?? 'À suivre'),
        'verification' => maranda_original_vigie_field($post_id, 'verification', 'Sources publiques'),
        'summary' => $excerpt,
        'nature' => $natures[0] ?? 'Vigie réseau',
        'region' => $regions ? implode(' · ', $regions) : 'Réseau routier québécois',
        'image' => maranda_original_vigie_image($post_id),
    ];
}
function maranda_original_vigie_cards(): string {
    $query = new WP_Query([
        'post_type' => 'mm_vigie',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    ]);
    if (!$query->have_posts()) return '';

    $cards = '';
    $index = 0;
    while ($query->have_posts()) {
        $query->the_post();
        $index++;
        $post_id = get_the_ID();
        $data = maranda_original_vigie_card_data($post_id);
        $title = get_the_title();
        $number = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        $offset = (($index - 1) * 1.5) . 'rem';

        $scope = ' data-astro-cid-oqe7gpm4';
        $visual_class = $data['image']['url'] !== '' ? 'card__visual card__visual--image' : 'card__visual';
        $cards .= '<article class="card"' . $scope . '><div class="' . esc_attr($visual_class) . '" style="--offset:' . esc_attr($offset) . '"' . $scope . '>';
        if ($data['image']['url'] !== '') $cards .= '<img src="' . esc_url($data['image']['url']) . '" alt="' . esc_attr($data['image']['alt']) . '" loading="lazy" decoding="async" referrerpolicy="no-referrer"' . $scope . '>';
        $cards .= '<span' . $scope . '>' . esc_html($data['category']) . '</span><strong aria-hidden="true"' . $scope . '>' . esc_html($number) . '</strong>';
        $cards .= '<small' . $scope . '>' . esc_html($data['image']['url'] !== '' ? ($data['image']['credit'] ?: 'Image documentaire · crédit dans le dossier') : 'Image documentaire non disponible') . '</small></div><div class="card__body"' . $scope . '>';
        $cards .= '<p class="card__infra"' . $scope . '>' . esc_html($data['infrastructure']) . '</p>';
        $cards .= '<h3' . $scope . '>' . esc_html($data['territory']) . '</h3>';
        $cards .= '<p class="card__period"' . $scope . '>' . esc_html($data['period']) . '</p>';
        $cards .= '<p class="card__summary"' . $scope . '>' . esc_html($data['summary']) . '</p>';
        $cards .= '<dl' . $scope . '><div' . $scope . '><dt' . $scope . '>Statut</dt><dd' . $scope . '>' . esc_html($data['status']) . '</dd></div>';
        $cards .= '<div' . $scope . '><dt' . $scope . '>Vérification</dt><dd' . $scope . '>' . esc_html($data['verification']) . '</dd></div></dl>';
        $cards .= '<a href="' . esc_url(get_permalink()) . '" aria-label="' . esc_attr('Consulter le dossier ' . $title) . '"' . $scope . '>Consulter le dossier <span' . $scope . '>→</span></a>';
        $cards .= '</div></article>';
    }
    wp_reset_postdata();
    return $cards;
}

add_action('init', static function () {
    $fields = ['category', 'infrastructure', 'territory', 'period', 'date', 'status', 'verification', 'image_url', 'image_alt', 'image_credit'];
    foreach ($fields as $field) {
        register_post_meta('mm_vigie', 'rr_vigie_' . $field, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => $field === 'image_url' ? 'esc_url_raw' : 'sanitize_text_field',
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]);
    }
}, 25);

add_action('add_meta_boxes_mm_vigie', static function (): void {
    add_meta_box('rr-vigie-presentation', 'Présentation de la fiche Vigie', static function ($post): void {
        wp_nonce_field('rr_vigie_presentation', 'rr_vigie_presentation_nonce');
        $labels = [
            'category' => 'Catégorie visuelle',
            'infrastructure' => 'Infrastructure',
            'territory' => 'Territoire affiché',
            'period' => 'Période',
            'date' => 'Date affichée sur les cartes',
            'status' => 'Statut',
            'verification' => 'Niveau de vérification',
            'image_url' => 'URL de l’image documentaire',
            'image_alt' => 'Texte alternatif de l’image',
            'image_credit' => 'Crédit de l’image',
        ];
        echo '<p>Ces champs alimentent automatiquement les cartes de la page d’accueil et de la grille Vigie. Une image à la une demeure prioritaire.</p>';
        foreach ($labels as $key => $label) {
            $value = get_post_meta($post->ID, 'rr_vigie_' . $key, true);
            $type = $key === 'image_url' ? 'url' : ($key === 'date' ? 'date' : 'text');
            printf('<p><label for="rr-vigie-%1$s"><strong>%2$s</strong></label><br><input id="rr-vigie-%1$s" name="rr_vigie_%1$s" type="%3$s" value="%4$s" class="widefat"></p>', esc_attr($key), esc_html($label), esc_attr($type), esc_attr($value));
        }
    }, null, 'normal', 'high');
});

add_action('save_post_mm_vigie', static function (int $post_id): void {
    if (!isset($_POST['rr_vigie_presentation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rr_vigie_presentation_nonce'])), 'rr_vigie_presentation')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    foreach (['category', 'infrastructure', 'territory', 'period', 'date', 'status', 'verification', 'image_url', 'image_alt', 'image_credit'] as $field) {
        $value = isset($_POST['rr_vigie_' . $field]) ? wp_unslash($_POST['rr_vigie_' . $field]) : '';
        $value = $field === 'image_url' ? esc_url_raw($value) : sanitize_text_field($value);
        if ($value === '') delete_post_meta($post_id, 'rr_vigie_' . $field);
        else update_post_meta($post_id, 'rr_vigie_' . $field, $value);
    }
});
add_action('wp_enqueue_scripts', static function () {
 if (!is_post_type_archive('mm_vigie') && !is_singular('mm_vigie')) return;
 $vigie_css = <<<'CSS'
.card[data-astro-cid-oqe7gpm4] { min-width: 0px; overflow: hidden; border: 1px solid rgb(213, 218, 215); border-radius: var(--radius); background: rgb(255, 255, 255); box-shadow: rgba(34, 54, 84, 0.08) 0px 8px 24px; }
.card__visual[data-astro-cid-oqe7gpm4] { position: relative; height: 9.5rem; overflow: hidden; background: linear-gradient(145deg, rgb(9, 87, 151), rgb(34, 54, 84)); color: rgb(255, 255, 255); }
.card__visual[data-astro-cid-oqe7gpm4]::after { position: absolute; inset: auto -10% 1.5rem; height: 3.4rem; background: repeating-linear-gradient(115deg, rgb(0, 111, 169) 0px, rgb(0, 111, 169) 2rem, rgb(125, 135, 149) 2rem, rgb(125, 135, 149) 3rem, rgb(255, 255, 255) 3rem, rgb(255, 255, 255) 3.45rem, rgb(34, 54, 84) 3.45rem, rgb(34, 54, 84) 4.5rem); content: ""; transform: translate(var(--offset)) skewY(-5deg); opacity: 0.88; }
.card__visual[data-astro-cid-oqe7gpm4] > span[data-astro-cid-oqe7gpm4] { position: absolute; z-index: 2; top: 0.75rem; left: 0.75rem; padding: 0.3rem 0.55rem; background: var(--bsir-yellow); color: rgb(255, 255, 255); font-size: 0.6rem; font-weight: 900; text-transform: uppercase; }
.card__visual[data-astro-cid-oqe7gpm4] > strong[data-astro-cid-oqe7gpm4] { position: absolute; top: 0.3rem; right: 0.7rem; color: rgb(109, 140, 175); font-family: var(--font-display); font-size: 4rem; font-style: normal; }
.card__visual[data-astro-cid-oqe7gpm4] small[data-astro-cid-oqe7gpm4] { position: absolute; z-index: 2; right: 0.7rem; bottom: 0.35rem; color: rgb(216, 225, 234); font-size: 0.52rem; letter-spacing: 0.08em; text-transform: uppercase; }
.card__body[data-astro-cid-oqe7gpm4] { padding: 1.1rem; }
.card__infra[data-astro-cid-oqe7gpm4] { margin: 0px; color: rgb(105, 115, 110); font-size: 0.62rem; font-weight: 900; text-transform: uppercase; }
h3[data-astro-cid-oqe7gpm4] { margin: 0.2rem 0px; font-family: var(--font-display); font-size: 1.15rem; text-transform: uppercase; }
.card__period[data-astro-cid-oqe7gpm4] { margin: 0px; color: rgb(105, 115, 110); font-size: 0.68rem; text-transform: uppercase; }
.card__summary[data-astro-cid-oqe7gpm4] { min-height: 5.4rem; margin: 1rem 0px; color: rgb(48, 55, 51); font-size: 0.78rem; line-height: 1.45; }
dl[data-astro-cid-oqe7gpm4] { display: grid; gap: 0.4rem; margin: 0px; padding: 1rem 0px; border-block: 1px solid rgb(224, 228, 225); }
dl[data-astro-cid-oqe7gpm4] div[data-astro-cid-oqe7gpm4] { display: flex; justify-content: space-between; gap: 1rem; }
dt[data-astro-cid-oqe7gpm4] { color: rgb(112, 122, 117); font-size: 0.58rem; text-transform: uppercase; }
dd[data-astro-cid-oqe7gpm4] { margin: 0px; font-size: 0.62rem; font-weight: 900; text-align: right; text-transform: uppercase; }
a[data-astro-cid-oqe7gpm4] { display: flex; min-height: 3rem; align-items: center; justify-content: space-between; font-size: 0.66rem; font-weight: 900; text-decoration: none; text-transform: uppercase; }
a[data-astro-cid-oqe7gpm4] span[data-astro-cid-oqe7gpm4] { font-size: 1rem; }
.card__visual--image[data-astro-cid-oqe7gpm4]::after, .card__visual--image[data-astro-cid-oqe7gpm4] > strong[data-astro-cid-oqe7gpm4] { display: none; }
.card__visual--image[data-astro-cid-oqe7gpm4] img[data-astro-cid-oqe7gpm4] { width: 100%; height: 100%; object-fit: cover; }
.card__visual--image[data-astro-cid-oqe7gpm4] small[data-astro-cid-oqe7gpm4] { right: 0px; bottom: 0px; left: 0px; padding: 0.4rem 0.7rem; background: rgba(0, 0, 0, 0.8); color: rgb(255, 255, 255); font-size: 0.65rem; letter-spacing: 0px; }
.page-head[data-astro-cid-h2cqd4en] { padding-block: 5rem; }
.page-head[data-astro-cid-h2cqd4en] em[data-astro-cid-h2cqd4en] { color: var(--bsir-yellow); font-style: inherit; }
.page-head[data-astro-cid-h2cqd4en] p[data-astro-cid-h2cqd4en]:not(.eyebrow) { max-width: 52rem; }
.listing[data-astro-cid-h2cqd4en] { padding-block: 4.5rem; }
.listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] { display: grid; grid-template-columns: auto auto 1fr; align-items: center; gap: 1rem; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--bsir-line); }
.listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] strong[data-astro-cid-h2cqd4en] { color: var(--bsir-yellow-deep); font-family: var(--font-display); font-size: 2.5rem; }
.listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] span[data-astro-cid-h2cqd4en] { font-size: 0.72rem; font-weight: 900; text-transform: uppercase; }
.listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] p[data-astro-cid-h2cqd4en] { margin: 0px; text-align: right; color: var(--bsir-gray); font-size: 0.7rem; }
.listing__grid[data-astro-cid-h2cqd4en] { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
@media (max-width: 850px) {
  .listing__grid[data-astro-cid-h2cqd4en] { grid-template-columns: 1fr 1fr; }
  .listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] { grid-template-columns: auto 1fr; }
  .listing[data-astro-cid-h2cqd4en] > header[data-astro-cid-h2cqd4en] p[data-astro-cid-h2cqd4en] { grid-column: 1 / -1; text-align: left; }
}
@media (max-width: 560px) {
  .listing__grid[data-astro-cid-h2cqd4en] { grid-template-columns: 1fr; }
  .listing[data-astro-cid-h2cqd4en] { padding-block: 3rem; }
}
.page-head .vigie-subtitle { margin-bottom: 0.5rem; color: rgb(255, 255, 255); font-size: clamp(1.1rem, 2vw, 1.35rem); font-weight: 800; }
.page-head .vigie-head-note { max-width: 55rem; margin-top: 1rem; color: rgb(191, 203, 212); font-size: 0.9rem; }
.vigie-purpose { max-width: 62rem; padding-block: 4rem; }
.vigie-purpose h2, .vigie-nature h2, .vigie-editorial h2, .vigie-cta h2 { margin: 0px 0px 1rem; font-size: clamp(1.65rem, 3vw, 2.6rem); line-height: 1.1; }
.vigie-purpose > p:not(.eyebrow) { max-width: 54rem; }
.vigie-owner { margin-top: 2rem; padding: 1.25rem; border-left: .35rem solid var(--bsir-yellow); background: rgb(238, 241, 239); font-weight: 700; }
.listing[data-astro-cid-h2cqd4en] > .vigie-nature[data-astro-cid-h2cqd4en] { display: block; margin-bottom: 2.5rem; padding: 0px 0px 2rem; border-bottom: 1px solid rgb(210, 210, 210); }
.listing[data-astro-cid-h2cqd4en] > .vigie-nature[data-astro-cid-h2cqd4en] h2 { max-width: 54rem; }
.listing[data-astro-cid-h2cqd4en] > .vigie-nature[data-astro-cid-h2cqd4en] p { max-width: 58rem; margin: 0px 0px 1rem; text-align: left; font-size: 1rem; }
.listing[data-astro-cid-h2cqd4en] > .vigie-nature[data-astro-cid-h2cqd4en] .eyebrow { font-size: 0.78rem; }
.card dd { font-weight: 800; }
.vigie-editorial { margin-block: 4rem; padding: 2rem; background: rgb(238, 241, 239); }
.vigie-editorial p { max-width: 58rem; }
.vigie-cta { padding-block: 4rem; }
.vigie-cta p { max-width: 50rem; color: rgb(210, 216, 212); }
.vigie-cta .button { margin-top: 1rem; }
@media (max-width: 700px) {
  .vigie-purpose { padding-block: 3rem; }
  .vigie-editorial { margin-block: 3rem; padding: 1.25rem; }
}:root { --rr-blue: #095797; --rr-sky: #8dc6e8; --rr-navy: #17375e; --rr-charcoal: #3f403f; --rr-paper: #f4f5f4; --rr-line: #d9ddda; }
.rr-method, .rr-services, .rr-about, .rr-contact, .rr-vigie, .rr-profile { overflow: hidden; color: rgb(37, 42, 46); }
.rr-method .container, .rr-services .container, .rr-about .container, .rr-contact .container, .rr-vigie .container, .rr-profile .container { width: min(100% - 3rem, 90rem); }
.rr-method .eyebrow, .rr-services .eyebrow, .rr-about .eyebrow, .rr-contact .eyebrow, .rr-vigie .eyebrow, .rr-profile .eyebrow { color: var(--rr-blue); font-size: 0.7rem; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; }
.rr-method .inner-head, .rr-services .inner-head, .rr-about .inner-head, .rr-contact .inner-head, .rr-vigie .page-head { position: relative; isolation: isolate; min-height: 31rem; padding: clamp(5rem, 8vw, 8rem) 0px; background: var(--rr-charcoal); color: rgb(255, 255, 255); }
.rr-method .inner-head::before, .rr-services .inner-head::before, .rr-about .inner-head::before, .rr-contact .inner-head::before, .rr-vigie .page-head::before { position: absolute; z-index: -2; inset: 0px; background: linear-gradient(90deg, rgba(27, 35, 41, 0.95) 0%, rgba(27, 35, 41, 0.85) 45%, rgba(27, 35, 41, 0.4) 75%), url("./assets/astro/route-quebec-hero.CETkoF_9_Z15aTvx.webp") center 58% / cover no-repeat; content: ""; }
.rr-method .inner-head::after, .rr-services .inner-head::after, .rr-about .inner-head::after, .rr-contact .inner-head::after, .rr-vigie .page-head::after { position: absolute; z-index: -1; right: -12rem; bottom: -27rem; width: 48rem; height: 48rem; border: 1px dashed rgba(255, 255, 255, 0.4); border-radius: 50%; content: ""; }
.rr-method .inner-head .container, .rr-services .inner-head .container, .rr-about .inner-head .container, .rr-contact .inner-head .container, .rr-vigie .page-head .container { margin: 0px auto; }
.rr-method .inner-head .eyebrow, .rr-services .inner-head .eyebrow, .rr-about .inner-head .eyebrow, .rr-contact .inner-head .eyebrow, .rr-vigie .page-head .eyebrow { color: var(--rr-sky); }
.rr-method .display, .rr-services .display, .rr-about .display, .rr-contact .display, .rr-vigie .display { max-width: 65rem; margin: 0.7rem 0px 1.4rem; font-size: clamp(3.4rem, 6vw, 6.6rem); letter-spacing: -0.06em; line-height: 0.92; text-transform: uppercase; }
.rr-method .inner-head p:not(.eyebrow), .rr-services .inner-head p:not(.eyebrow), .rr-about .inner-head p:not(.eyebrow), .rr-contact .inner-head p:not(.eyebrow), .rr-vigie .page-head p:not(.eyebrow) { max-width: 49rem; margin: 0.75rem 0px; color: rgb(255, 255, 255); font-size: 1.03rem; line-height: 1.6; }
.rr-prevost-button, .rr-method .button, .rr-services .button, .rr-about .button, .rr-contact .button, .rr-vigie .button, .rr-profile .button { display: inline-flex; min-height: 3.6rem; align-items: center; justify-content: center; gap: 1.25rem; padding: 0.75rem 1.6rem 0.75rem 1.8rem; border: 0px; border-radius: 999px; background: var(--rr-blue); color: rgb(255, 255, 255); text-decoration: none; text-transform: none; font-size: 0.84rem; font-weight: 800; letter-spacing: 0px; transition: background 0.25s, transform 0.25s; }
.rr-method .button::after, .rr-services .button::after, .rr-about .button::after, .rr-contact .button::after, .rr-vigie .button::after, .rr-profile .button::after { margin: 0px; content: "›"; font-size: 1.55rem; font-weight: 400; line-height: 0.7; transition: transform 0.25s; }
.rr-method .button:hover, .rr-services .button:hover, .rr-about .button:hover, .rr-contact .button:hover, .rr-vigie .button:hover, .rr-profile .button:hover { background: var(--rr-charcoal); color: rgb(255, 255, 255); transform: translateY(-2px); }
.rr-method .button:hover::after, .rr-services .button:hover::after, .rr-about .button:hover::after, .rr-contact .button:hover::after, .rr-vigie .button:hover::after, .rr-profile .button:hover::after { transform: translateX(0.2rem); }
.rr-vigie .display em { color: var(--rr-sky); font-style: normal; }
.rr-vigie .vigie-purpose { display: grid; grid-template-columns: 0.65fr 1.35fr; gap: 1rem 4rem; padding: 7rem 0px; }
.rr-vigie .vigie-purpose .eyebrow { grid-row: 1 / 5; }
.rr-vigie .vigie-purpose h2 { margin: 0px 0px 1rem; font-size: clamp(2.5rem, 4.5vw, 4.8rem); line-height: 0.98; }
.rr-vigie .vigie-purpose p { grid-column: 2; margin: 0.2rem 0px; line-height: 1.65; }
.rr-vigie .vigie-owner { padding: 1.25rem; border-left: 4px solid var(--rr-blue); background: var(--rr-paper); margin-top: 1rem !important; }
.rr-vigie .listing { padding: 6rem 0px; }
.rr-vigie .vigie-nature { max-width: 58rem; margin-bottom: 4rem; }
.rr-vigie .vigie-nature h2 { font-size: clamp(2.4rem, 4vw, 4rem); line-height: 1; }
.rr-vigie .listing__grid { display: grid; grid-template-columns: repeat(2, minmax(0px, 1fr)); gap: 1.5rem; }
.rr-vigie .card { display: grid; grid-template-columns: 11rem minmax(0px, 1fr); min-height: 24rem; background: rgb(255, 255, 255); box-shadow: rgba(23, 55, 94, 0.08) 0px 1rem 3rem; }
.rr-vigie .card__visual { min-height: 100%; background: linear-gradient(145deg,var(--rr-blue),var(--rr-navy)); color: rgb(255, 255, 255); margin: 0px !important; }
.rr-vigie .card__visual strong { font-size: 4rem; }
.rr-vigie .card__body { padding: 2rem; }
.rr-vigie .card__body h3 { font-size: 1.35rem; line-height: 1.2; }
.rr-vigie .card__body a { color: var(--rr-blue); font-weight: 800; }
.rr-vigie .vigie-editorial { margin-block: 5rem; padding: 3rem; border-left: 5px solid var(--rr-blue); background: var(--rr-paper); }
.rr-vigie .vigie-cta { padding: 6rem 0px; background: var(--rr-charcoal); color: rgb(255, 255, 255); }
.rr-vigie .vigie-cta h2 { font-size: clamp(2.5rem, 4vw, 4rem); }
.rr-vigie .vigie-purpose .eyebrow { grid-area: 1 / 1 / 6; align-self: start; }
.rr-vigie .vigie-purpose h2 { grid-column: 2; }
@media (max-width: 1050px) {
  .rr-services .services-feature-grid { grid-template-columns: repeat(2, minmax(0px, 1fr)); padding-top: 4rem; }
  .rr-services .services-feature-grid .service-feature:nth-child(2) { margin-top: -2rem; }
  .rr-services .services-feature-grid .service-feature:nth-child(n+3) { margin-top: 0px; }
  .rr-vigie .listing__grid { grid-template-columns: 1fr; }
  .rr-profile-body { grid-template-columns: 1fr; }
  .rr-profile-summary { position: static; }
}
@media (max-width: 800px) {
  .rr-method .method-content::before, .rr-method .method-step::after { display: none; }
  .rr-method .method-step { width: 100%; margin: 0px 0px 1rem !important; }
  .rr-method .method-note, .rr-about .about-intro, .rr-contact .contact-layout, .rr-contact .contact-direct, .rr-vigie .vigie-purpose { grid-template-columns: 1fr; }
  .rr-method .method-note { gap: 1rem; }
  .rr-method .method-audience { width: 100%; margin-left: 0px; }
  .rr-method .method-audience h2 { white-space: normal; }
  .rr-services .services-content { grid-template-columns: 1fr; }
  .rr-services .service-block:nth-child(n) { grid-column: auto; }
  .rr-services .services-feature-grid { grid-column: auto; grid-template-columns: 1fr; margin-inline: -1rem; padding: 2rem 1rem; }
  .rr-services .services-feature-grid .service-feature:nth-child(n) { grid-column: auto; width: auto; min-height: 0px; margin: 0px; }
  .rr-services .services-tail .service-block--tool { width: 100%; margin-inline: 0px; padding-inline: 1.5rem; }
  .rr-services .services-tail #audience-title { width: auto; margin-inline: 0px; white-space: normal; }
  .rr-services .services-cta .container { grid-template-columns: 1fr; }
  .rr-services .services-cta .eyebrow, .rr-services .services-cta h2, .rr-services .services-cta p, .rr-services .services-cta .button { grid-area: auto / 1; }
  .rr-services .services-cta .eyebrow { margin-bottom: 1rem; }
  .rr-services .services-cta h2 { margin-bottom: 1.5rem; }
  .rr-services .services-cta .button { margin-top: 2rem; }
  .rr-about .about-section { padding: 2.25rem; }
  .rr-contact .contact-form { margin: 0px 1rem 1rem; }
  .rr-vigie .vigie-purpose .eyebrow, .rr-vigie .vigie-purpose p { grid-area: auto; }
  .rr-vigie .card { grid-template-columns: 8rem 1fr; }
}
@media (max-width: 600px) {
  .rr-method .container, .rr-services .container, .rr-about .container, .rr-contact .container, .rr-vigie .container, .rr-profile .container { width: min(100% - 2rem, 90rem); }
  .rr-method .display, .rr-services .display, .rr-about .display, .rr-contact .display, .rr-vigie .display { font-size: clamp(2.8rem, 14vw, 4.2rem); }
  .rr-services .service-block ul { columns: 1; }
  .rr-services .services-tail .deliverables { columns: 1; }
  .rr-profile-expertise, .rr-profile-training ul, .rr-contact .contact-form__row { grid-template-columns: 1fr; }
  .rr-about .about-section { grid-template-columns: 1fr; }
  .rr-about .about-section > small { grid-row: auto; }
  .rr-about .about-section h3, .rr-about .about-section p, .rr-about .about-section ul, .rr-about .about-tags { grid-column: 1; }
  .rr-vigie .card { grid-template-columns: 1fr; }
  .rr-vigie .card__visual { min-height: 10rem; }
  .rr-profile-head h1 { font-size: 3.5rem; }
  .rr-profile-experience article header { grid-template-columns: 1fr; }
  .rr-profile-role-major { padding: 1.5rem !important; }
}
@media (max-width: 800px) {
  .rr-vigie .vigie-purpose h2 { grid-column: auto; }
}
.rr-vigie{--rr-blue:#095797;--rr-sky:#8dc6e8;--rr-navy:#17375e;--rr-charcoal:#3f403f;--rr-paper:#fff;--rr-line:#d9ddda;--bsir-yellow:#095797;--bsir-line:#d9ddda;--radius:4px;--font-display:Arial,sans-serif;background:#fff;font-family:Arial,sans-serif}
.rr-vigie .container{margin-inline:auto}.rr-vigie .display{font-weight:800}.rr-vigie .vigie-owner,.rr-vigie .vigie-editorial{background:#fff}.rr-vigie .card{border-radius:4px}.rr-vigie .card__visual{height:auto}.rr-vigie .card__body{min-width:0}.rr-vigie .eyebrow{font-family:monospace}.rr-vigie .card__body h3{overflow-wrap:anywhere}.rr-vigie .listing{padding-top:2rem}.rr-vigie .mm-vigie-detail{max-width:58rem;padding-block:4rem;line-height:1.7}.rr-vigie .mm-vigie-detail img{max-width:100%;height:auto}.rr-vigie .mm-vigie-detail h2{margin-top:2.5rem;color:var(--rr-navy)}
CSS;
 wp_add_inline_style('maranda', str_replace('./assets/astro/route-quebec-hero.CETkoF_9_Z15aTvx.webp', esc_url_raw(get_theme_file_uri('assets/astro/route-quebec-hero.CETkoF_9_Z15aTvx.webp')), $vigie_css));
}, 30);
add_filter('render_block', static function (string $content, array $block): string {
 if (($block['blockName'] ?? '') !== 'core/group' || ($block['attrs']['tagName'] ?? '') !== 'main') return $content;
 if (is_post_type_archive('mm_vigie')) {
  $path=get_theme_file_path('data/vigie-reseau.html');
  if (!is_readable($path)) return $content;
  $html=file_get_contents($path);
  $html=preg_replace_callback('~(<div class="listing__grid"[^>]*>).*?(</div></section>)~s', static fn($m) => $m[1].maranda_original_vigie_cards().$m[2], $html, 1);
  $html=str_replace('Parlons d’une possibilité','Me contacter',$html);
  return '<main class="rr-vigie mm-main">'.$html.'</main>';
 }
 return $content;
}, 20, 2);
