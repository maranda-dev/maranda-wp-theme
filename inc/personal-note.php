<?php
defined('ABSPATH') || exit;
/* Same setting IDs and legacy values, without copying unrelated theme settings. */
function maranda_announcement_setting(string $key, $fallback) {
    $legacy = get_option('theme_mods_bsir-wordpress', []);
    $default = is_array($legacy) && array_key_exists($key, $legacy) ? $legacy[$key] : $fallback;
    return get_theme_mod($key, $default);
}
function maranda_announcement_default(): string {
    return trim((string) file_get_contents(get_theme_file_path('data/announcement.txt')));
}

add_action('customize_register', static function ($customizer) {
    $customizer->add_section('mm_announcement', ['title' => 'Annonce temporaire', 'priority' => 34]);
    $customizer->add_setting('mm_announcement_enabled', ['default' => maranda_announcement_setting('mm_announcement_enabled', true), 'sanitize_callback' => 'rest_sanitize_boolean', 'transport' => 'refresh']);
    $customizer->add_control('mm_announcement_enabled', ['label' => 'Afficher l’annonce sur l’accueil', 'section' => 'mm_announcement', 'type' => 'checkbox']);
    $customizer->add_setting('mm_announcement_title', ['default' => maranda_announcement_setting('mm_announcement_title', 'Quelques mots sur ma démarche'), 'sanitize_callback' => 'sanitize_text_field', 'transport' => 'refresh']);
    $customizer->add_control('mm_announcement_title', ['label' => 'Titre de l’annonce', 'section' => 'mm_announcement', 'type' => 'text']);
    $customizer->add_setting('mm_announcement_message', ['default' => maranda_announcement_setting('mm_announcement_message', maranda_announcement_default()), 'sanitize_callback' => 'sanitize_textarea_field', 'transport' => 'refresh']);
    $customizer->add_control('mm_announcement_message', ['label' => 'Message', 'description' => 'Séparez les paragraphes par une ligne vide. Incluez votre signature. Après publication, purgez le cache du site si nécessaire. Une annonce modifiée réapparaît même si la précédente a été fermée.', 'section' => 'mm_announcement', 'type' => 'textarea']);

});
add_action('wp_enqueue_scripts', static function () {
    if (!is_front_page()) return;
    wp_enqueue_style('mm-personal-note', get_theme_file_uri('assets/personal-note.css'), [], MARANDA_THEME_VERSION);
    wp_enqueue_script('mm-personal-note', get_theme_file_uri('assets/personal-note.js'), [], MARANDA_THEME_VERSION, true);
});
add_action('wp_footer', static function () {
    if (is_front_page()) require get_theme_file_path('inc/personal-note-markup.php');
}, 15);
