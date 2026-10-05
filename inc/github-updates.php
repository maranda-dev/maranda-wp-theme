<?php
/** Stable public GitHub releases only; official asset SHA-256 checked before installation. */
defined('ABSPATH') || exit;

function maranda_github_headers(string $accept = 'application/vnd.github+json'): array {
    return ['Accept' => $accept, 'User-Agent' => 'Maranda-WordPress/' . MARANDA_THEME_VERSION];
}

function maranda_github_release() {
    $cached = get_transient('maranda_theme_github_release');
    if (false !== $cached) return $cached;
    $response = wp_remote_get('https://api.github.com/repos/maranda-dev/maranda-wp-theme/releases/latest', ['headers' => maranda_github_headers(), 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 1048576]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return new WP_Error('rr_release_unavailable', 'GitHub indisponible ou aucune release stable publiée.');
    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) || !preg_match('/^v(\d+\.\d+\.\d+)$/', $release['tag_name'] ?? '', $match)) return new WP_Error('rr_release_invalid', 'La version GitHub n’est pas une version stable reconnue.');
    foreach ($release['assets'] ?? [] as $asset) {
        $id = absint($asset['id'] ?? 0);
        if (!$id || ($asset['state'] ?? '') !== 'uploaded' || ($asset['name'] ?? '') !== 'maranda-wp-theme-' . $match[1] . '.zip') continue;
        if (!preg_match('/^sha256:([a-f0-9]{64})$/', $asset['digest'] ?? '', $digest)) continue;
        $data = ['version' => $match[1], 'package' => 'https://api.github.com/repos/maranda-dev/maranda-wp-theme/releases/assets/' . $id, 'sha256' => $digest[1], 'url' => 'https://github.com/maranda-dev/maranda-wp-theme/releases/tag/' . $release['tag_name']];
        set_transient('maranda_theme_github_release', $data, 300);
        return $data;
    }
    return new WP_Error('rr_release_asset_missing', 'Le ZIP de cette version ou son empreinte SHA-256 est manquant.');
}

add_filter('update_themes_github.com', static function ($update, $theme_data, $stylesheet) {
    if ($stylesheet !== get_template() || ($theme_data['UpdateURI'] ?? '') !== 'https://github.com/maranda-dev/maranda-wp-theme') return $update;
    $release = maranda_github_release();
    if (is_wp_error($release)) return false;
    return ['id' => $theme_data['UpdateURI'], 'theme' => $stylesheet, 'version' => $release['version'], 'url' => $release['url'], 'package' => $release['package'], 'requires' => '6.6', 'requires_php' => '8.1'];
}, 10, 3);

add_filter('upgrader_pre_download', static function ($reply, $package) {
    if (!preg_match('#^https://api\.github\.com/repos/maranda-dev/maranda-wp-theme/releases/assets/[1-9][0-9]*$#', $package)) return $reply;
    if (false !== $reply) return $reply;
    $release = maranda_github_release();
    if (is_wp_error($release)) return $release;
    if ($package !== $release['package']) return new WP_Error('rr_release_changed', 'La version publiée a changé; relancez la recherche des mises à jour.');
    $temporary = wp_tempnam('reseau-routier.zip');
    if (!$temporary) return new WP_Error('rr_temp_failed', 'Impossible de créer le fichier temporaire.');
    // Never forward Authorization to a redirect destination.
    $response = wp_remote_get($package, ['headers' => maranda_github_headers('application/octet-stream'), 'timeout' => 60, 'redirection' => 0, 'stream' => true, 'filename' => $temporary, 'limit_response_size' => 67108864]);
    if (is_wp_error($response)) { wp_delete_file($temporary); return new WP_Error('rr_download_failed', 'Le téléchargement GitHub a échoué.'); }
    $code = wp_remote_retrieve_response_code($response);
    if (in_array($code, [301, 302, 303, 307, 308], true)) {
        $location = wp_remote_retrieve_header($response, 'location');
        $parts = wp_parse_url($location);
        wp_delete_file($temporary);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['release-assets.githubusercontent.com', 'objects.githubusercontent.com'], true) || isset($parts['user']) || isset($parts['pass'])) return new WP_Error('rr_redirect_rejected', 'Destination de téléchargement non reconnue.');
        $temporary = download_url($location, 120);
        if (is_wp_error($temporary)) return new WP_Error('rr_download_failed', 'Le téléchargement de la version a échoué.');
    } elseif ($code !== 200) {
        wp_delete_file($temporary); return new WP_Error('rr_download_denied', 'GitHub a refusé le téléchargement de la release.');
    }
    $checksum = is_readable($temporary) ? hash_file('sha256', $temporary) : false;
    if (!is_string($checksum) || !hash_equals($release['sha256'], $checksum)) { wp_delete_file($temporary); return new WP_Error('rr_checksum_failed', 'Le ZIP ne correspond pas à l’empreinte publiée. Mise à jour annulée.'); }
    return $temporary;
}, 10, 2);


add_action('admin_menu', static function () {
    add_theme_page('Mises à jour maranda', 'Mises à jour maranda', 'update_themes', 'maranda-updates', 'maranda_github_settings');
});
function maranda_github_settings(): void {
    if (!current_user_can('update_themes') || !current_user_can('manage_options')) wp_die('Accès refusé.');
    $slug = get_template();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        check_admin_referer('maranda_github_settings');
        $themes = array_values(array_diff((array) get_site_option('auto_update_themes', []), [$slug]));
        if (!empty($_POST['maranda_automatic'])) $themes[] = $slug;
        update_site_option('auto_update_themes', $themes);
        delete_transient('maranda_theme_github_release');
        delete_site_transient('update_themes');
        echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>';
    }
    $release = maranda_github_release();
    $automatic = in_array($slug, (array) get_site_option('auto_update_themes', []), true);
    echo '<div class="wrap"><h1>Mises à jour maranda</h1><p>Version installée : <strong>' . esc_html(MARANDA_THEME_VERSION) . '</strong>.</p><p>Seules les releases GitHub stables accompagnées du ZIP officiel et de son empreinte SHA-256 sont proposées. Un commit ou un tag sans release ne déclenche aucune mise à jour. Le dépôt est public : aucun jeton GitHub n’est nécessaire.</p>';
    echo is_wp_error($release) ? '<p>' . esc_html($release->get_error_message()) . '</p>' : '<p>Version officielle disponible : <strong>' . esc_html($release['version']) . '</strong>.</p>';
    echo '<p>Les dossiers Vigie, articles, images et taxonomies restent dans WordPress. Les modifications manuelles des fichiers du thème sont remplacées par la prochaine release.</p><form method="post">';
    wp_nonce_field('maranda_github_settings');
    echo '<p><label><input type="checkbox" name="maranda_automatic" value="1" ' . checked($automatic, true, false) . '> Installer automatiquement les nouvelles releases stables</label></p>';
    submit_button('Enregistrer et vérifier');
    echo '</form><p><a href="' . esc_url(admin_url('update-core.php')) . '">Rechercher les mises à jour WordPress</a></p></div>';
}
