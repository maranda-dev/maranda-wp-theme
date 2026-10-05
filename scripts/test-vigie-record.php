<?php
/** Structural preservation checks for the legacy documentary renderer. */
define('ABSPATH', __DIR__);
function add_shortcode(...$args) {}
function add_action(...$args) {}
function get_post_field(...$args) { return $GLOBALS['fixture']; }
function apply_filters($hook, $value) { return $value; }
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
require __DIR__ . '/../inc/vigie-record.php';
$fixture = '<table><tr><th>Infrastructure</th><td>Ponceau P-123</td></tr></table>'
    . '<p>Cette fiche ne constitue pas une inspection.</p>'
    . '<p>Résumé</p><p>Résumé original conservé.</p>'
    . '<p>Chronologie synthétique</p><h2 id="stages-title">Étapes</h2><ol><li><strong>Suivi — octobre 2026</strong><p>Observation documentée.</p></li></ol>'
    . '<p>Chronologie détaillée</p><h2 id="timeline-title">Parcours</h2><ol><li><strong>5 octobre 2026</strong><p>Fait documenté.</p></li></ol>'
    . '<p>Références</p><h2 id="sources-title">Sources</h2><ol><li><strong>Municipalité</strong> — Document original. <a href="https://example.com/source" rel="noopener noreferrer">Source</a></li></ol>'
    . '<section id="suivi-ultérieur"><h2>Nouvelle mise à jour</h2><p>Le développement ajouté par une veille reste conservé.</p></section>';
$result = maranda_vigie_dynamic_markup(123);
check($result['meta']['Infrastructure'] === 'Ponceau P-123', 'Metadata must remain available for the banner');
check($result['disclaimer'] === 'Cette fiche ne constitue pas une inspection.', 'Disclaimer must remain available for the documentary rail');
check($result['summary'] === 'Résumé original conservé.', 'The original summary must be retained');
foreach (['class="stages"', 'class="timeline"', 'class="sources"', 'suivi-ultérieur', 'Le développement ajouté', 'https://example.com/source', 'noopener noreferrer', '5 octobre 2026', 'Observation documentée.'] as $expected) {
    check(str_contains($result['html'], $expected), 'Missing preserved content or structure: ' . $expected);
}
$fixture = '<h2>Fiche libre</h2><p>Sans structure documentaire obligatoire.</p><table><tr><td>Coût</td><td>100</td></tr></table>';
$result = maranda_vigie_dynamic_markup(123);
check(str_contains($result['html'], '<table'), 'An unrelated data table must not be removed');
check(str_contains($result['html'], 'Sans structure documentaire obligatoire.'), 'Simple dossiers must retain their content');
echo "Vigie documentary structure checks passed.\n";
