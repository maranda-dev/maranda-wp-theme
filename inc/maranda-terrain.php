<?php
defined('ABSPATH') || exit;
add_shortcode('maranda_map', static function () {
    wp_enqueue_style('maranda-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_style('maranda-map', get_theme_file_uri('assets/maranda-map.css'), ['maranda-leaflet'], '0.1.3');
    wp_enqueue_script('maranda-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    wp_enqueue_script('maranda-map', get_theme_file_uri('assets/carte-interactive.js'), ['maranda-leaflet'], '0.1.3', true);
    $endpoint = rest_url('bsir/v1/public-map');
    if (isset($_GET['wpvibe_preview']) && is_string($_GET['wpvibe_preview'])) $endpoint = add_query_arg('wpvibe_preview', sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])), $endpoint);
    ob_start(); ?>
<div class="rr-map-app" data-inspekt-map data-endpoint="<?php echo esc_url($endpoint); ?>">
  
  
  <div class="rr-map-layout">
    <aside class="rr-map-sidebar" id="rr-map-sidebar" aria-label="Filtres des relevés">
      <div class="rr-map-sidebar__heading">
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
