<?php
defined('ABSPATH') || exit;

function maranda_vigie_dynamic_markup(int $post_id): array {
    $content = apply_filters('the_content', get_post_field('post_content', $post_id));
    if (!class_exists('DOMDocument')) return ['html' => '<div class="record__content">' . $content . '</div>', 'meta' => [], 'summary' => '', 'disclaimer' => ''];
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?><div id="rr-vigie-source">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($dom);
    $source = $xpath->query('//*[@id="rr-vigie-source"]')->item(0);
    $meta = [];

    $table = $source ? $xpath->query('.//table[1]', $source)->item(0) : null;
    if ($table) {
        foreach ($xpath->query('.//tr', $table) as $row) {
            $label = trim((string) $xpath->evaluate('string(.//th[1])', $row));
            $value = trim((string) $xpath->evaluate('string(.//td[1])', $row));
            if ($label !== '' && $value !== '') $meta[$label] = $value;
        }
        if ($meta) $table->parentNode->removeChild($table);
    }

    $disclaimer = '';
    if ($source) {
        foreach (iterator_to_array($source->childNodes) as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE || strtolower($node->nodeName) !== 'p') continue;
            $text = trim($node->textContent);
            if (str_contains($text, 'ne constitue') || str_contains($text, 'ne remplace')) {
                $disclaimer = $text;
                $node->parentNode->removeChild($node);
                break;
            }
        }
    }

    $output = $dom->createElement('div');
    $output->setAttribute('class', 'record__content');
    $labels = ['Résumé', 'Chronologie synthétique', 'Chronologie détaillée', 'Faits documentés', 'Information publique', 'Lecture documentaire', 'Suivi', 'Information non déterminée', 'Prochain jalon', 'À garder à l’œil', 'Références', 'Contexte distinct', 'Intervention'];

    $next_element = static function (?DOMNode $node): ?DOMElement {
        for ($cursor = $node?->nextSibling; $cursor; $cursor = $cursor->nextSibling) {
            if ($cursor instanceof DOMElement) return $cursor;
        }
        return null;
    };

    while ($source && $source->firstChild) {
        $node = $source->firstChild;
        if (!($node instanceof DOMElement)) {
            $source->removeChild($node);
            continue;
        }
        $label = strtolower($node->nodeName) === 'p' ? trim($node->textContent) : '';
        $next = $next_element($node);
        if (in_array($label, $labels, true) && $next) {
            $tag = $label === 'Contexte distinct' ? 'aside' : 'section';
            $section = $dom->createElement($tag);
            $node->setAttribute('class', trim($node->getAttribute('class') . ' section-label'));
            $section->appendChild($node);

            if ($label === 'Résumé' && strtolower($next->nodeName) === 'p') {
                $section->setAttribute('class', 'summary');
                $section->appendChild($next);
            } elseif (in_array($label, ['Prochain jalon', 'À garder à l’œil'], true) && strtolower($next->nodeName) === 'p') {
                $section->setAttribute('class', 'milestone');
                $section->appendChild($next);
            } else {
                if ($next->hasAttribute('id')) {
                    $id = $next->getAttribute('id');
                    if ($id === 'reading-title') $section->setAttribute('class', 'reading');
                    elseif ($id === 'unknown-title') $section->setAttribute('class', 'unknowns');
                    elseif ($id === 'intervention-title') $section->setAttribute('class', 'intervention');
                    $section->setAttribute('aria-labelledby', $id);
                }
                if ($label === 'Contexte distinct') $section->setAttribute('class', 'context-note');
                $section->appendChild($next);
                while ($source->firstChild) {
                    $candidate = $source->firstChild;
                    if (!($candidate instanceof DOMElement)) {
                        $source->removeChild($candidate);
                        continue;
                    }
                    $candidate_label = strtolower($candidate->nodeName) === 'p' ? trim($candidate->textContent) : '';
                    if (in_array($candidate_label, $labels, true) && $next_element($candidate)) break;
                    $section->appendChild($candidate);
                }
            }
            $output->appendChild($section);
            continue;
        }
        $output->appendChild($node);
    }

    foreach ($xpath->query('.//*[@id="stages-title"]/following-sibling::ol[1]', $output) as $list) {
        $list->setAttribute('class', 'stages');
        foreach (iterator_to_array($list->getElementsByTagName('li')) as $item) {
            $strong = $item->getElementsByTagName('strong')->item(0);
            if (!$strong) continue;
            $parts = preg_split('/\s+(?:—|–|-)\s+/u', trim($strong->textContent), 2);
            $stage = $dom->createElement('span', $parts[0] ?? 'Étape');
            $time = $dom->createElement('time', $parts[1] ?? 'Non documenté');
            $item->insertBefore($stage, $strong);
            $item->insertBefore($time, $strong);
            $item->removeChild($strong);
            $item->appendChild($dom->createElement('small', 'Information publique'));
        }
    }
    foreach ($xpath->query('.//*[@id="timeline-title"]/following-sibling::ol[1]', $output) as $list) {
        $list->setAttribute('class', 'timeline');
        foreach (iterator_to_array($list->getElementsByTagName('li')) as $item) {
            $strong = $item->getElementsByTagName('strong')->item(0);
            if (!$strong) continue;
            $time = $dom->createElement('time', trim($strong->textContent));
            $details = $dom->createElement('div');
            $item->insertBefore($time, $strong);
            $item->removeChild($strong);
            while ($item->childNodes->length > 1) $details->appendChild($item->childNodes->item(1));
            $item->appendChild($details);
        }
    }
    foreach ($xpath->query('.//*[@id="facts-title"]/following-sibling::ul[1]', $output) as $list) $list->setAttribute('class', 'facts');
    foreach ($xpath->query('.//*[@id="sources-title"]/following-sibling::ol[1]', $output) as $list) {
        $list->setAttribute('class', 'sources');
        foreach (iterator_to_array($list->getElementsByTagName('li')) as $item) {
            $link = $item->getElementsByTagName('a')->item(0);
            if (!$link) continue;
            $details = $dom->createElement('div');
            while ($item->firstChild && $item->firstChild !== $link) $details->appendChild($item->firstChild);
            $item->insertBefore($details, $link);
        }
    }
    foreach ($xpath->query('.//*', $output) as $element) $element->setAttribute('data-astro-cid-wvdksmju', '');
    $output->setAttribute('data-astro-cid-wvdksmju', '');

    $summary = '';
    $summary_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " summary ")]/p[last()]', $output)->item(0);
    if ($summary_node) $summary = trim($summary_node->textContent);

    return ['html' => $dom->saveHTML($output), 'meta' => $meta, 'summary' => $summary, 'disclaimer' => $disclaimer];
}

function maranda_vigie_record(): string {
    if (!is_singular('mm_vigie')) return '';
    $post_id = get_queried_object_id();
    $parsed = maranda_vigie_dynamic_markup($post_id);
    $card = maranda_original_vigie_card_data($post_id);
    $meta = $parsed['meta'];
    $summary = $parsed['summary'] ?: $card['summary'];
    $scope = ' data-astro-cid-wvdksmju';
    ob_start();
?>
<main id="contenu" class="mm-vigie-record">
    <header class="record-head surface-dark"<?php echo $scope; ?>><div class="container"<?php echo $scope; ?>>
        <a class="back" href="<?php echo esc_url(home_url('/vigie-reseau/')); ?>"<?php echo $scope; ?>>← Retour à la Vigie réseau</a>
        <div class="record-head__tags"<?php echo $scope; ?>><span<?php echo $scope; ?>><?php echo esc_html($card['category']); ?></span><span<?php echo $scope; ?>>Dossier documentaire</span></div>
        <h1<?php echo $scope; ?>><?php echo esc_html(get_the_title($post_id)); ?></h1><p<?php echo $scope; ?>><?php echo esc_html($summary); ?></p>
    </div></header>
    <div class="record-meta"<?php echo $scope; ?>><dl class="container"<?php echo $scope; ?>>
        <?php foreach ([
            'Infrastructure' => ($meta['Infrastructure'] ?? $card['infrastructure']),
            'Territoire' => ($meta['Territoire'] ?? $card['territory']),
            'Période' => ($meta['Période'] ?? $card['period']),
            'Statut documentaire' => ($meta['Statut documentaire'] ?? $card['status']),
            'Niveau de vérification' => ($meta['Niveau de vérification'] ?? $card['verification']),
        ] as $label => $value) : ?><div<?php echo $scope; ?>><dt<?php echo $scope; ?>><?php echo esc_html($label); ?></dt><dd<?php echo $scope; ?>><?php echo esc_html($value); ?></dd></div><?php endforeach; ?>
    </dl></div>
    <article class="record container"<?php echo $scope; ?>><aside class="record__rail"<?php echo $scope; ?>>
        <div class="image-state"<?php echo $scope; ?>><?php if ($card['image']['url']) : ?><img src="<?php echo esc_url($card['image']['url']); ?>" alt="<?php echo esc_attr($card['image']['alt']); ?>" loading="lazy"<?php echo $scope; ?>><?php else : ?><strong<?php echo $scope; ?>>maranda.dev</strong><span<?php echo $scope; ?>>Image documentaire<br<?php echo $scope; ?>>non disponible</span><?php endif; ?></div>
        <div class="legend"<?php echo $scope; ?>><h2<?php echo $scope; ?>>Provenance de l’information</h2><span<?php echo $scope; ?>><i class="fact"<?php echo $scope; ?>></i>Information publique</span><span<?php echo $scope; ?>><i class="observation"<?php echo $scope; ?>></i>Observation terrain (si disponible)</span><span<?php echo $scope; ?>><i class="reading"<?php echo $scope; ?>></i>Évolution</span><span<?php echo $scope; ?>><i class="unknown"<?php echo $scope; ?>></i>Information non déterminée</span></div>
        <?php if ($parsed['disclaimer']) : ?><p class="disclaimer"<?php echo $scope; ?>><?php echo esc_html($parsed['disclaimer']); ?></p><?php endif; ?>
    </aside><?php echo $parsed['html']; // Content is filtered by WordPress before structural transformation. ?></article>
</main>
<?php
    return ob_get_clean();
}
add_shortcode('maranda_vigie_record', 'maranda_vigie_record');
add_action('wp_enqueue_scripts', static function () {
    if (is_singular('mm_vigie')) wp_enqueue_style('maranda-vigie-record', get_theme_file_uri('assets/maranda-vigie-record.css'), ['maranda'], MARANDA_THEME_VERSION);
}, 20);
