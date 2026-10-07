<?php
/** Import LibreOffice DOCX articles as editable WordPress blocks. */
defined('ABSPATH') || exit;

function maranda_docx_xml(string $xml): DOMDocument {
    if (stripos($xml, '<!DOCTYPE') !== false) throw new RuntimeException('Le document contient une déclaration XML non prise en charge.');
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    if (!$ok) throw new RuntimeException('Le document ne peut pas être lu.');
    return $dom;
}

function maranda_docx_blocks(string $path): string {
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) throw new RuntimeException('Cet hébergement doit activer les extensions PHP ZIP et DOM pour importer les articles.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Le fichier doit être un document .docx valide.');
    try {
        $read = static function (string $name) use ($zip): string {
            $stat = $zip->statName($name);
            if (!$stat) return '';
            if ($stat['size'] > 8 * 1024 * 1024) throw new RuntimeException('Le document est trop volumineux.');
            return (string) $zip->getFromName($name);
        };
        $document = $read('word/document.xml');
        if ($document === '') throw new RuntimeException('Le fichier ne contient pas de document Word compatible.');
        $dom = maranda_docx_xml($document); $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        if ($xp->query('//w:tbl | //w:drawing | //w:pict | //w:object | //w:footnoteReference | //w:endnoteReference')->length) throw new RuntimeException('Ce document contient des images, tableaux ou notes. Pour cet import, utilise une copie contenant seulement le texte, puis ajoute ces éléments dans le brouillon WordPress.');
        $links = []; $rels = $read('word/_rels/document.xml.rels');
        if ($rels !== '') foreach (maranda_docx_xml($rels)->getElementsByTagName('Relationship') as $rel) {
            if (str_ends_with($rel->getAttribute('Type'), '/hyperlink')) $links[$rel->getAttribute('Id')] = esc_url($rel->getAttribute('Target'), ['http', 'https', 'mailto']);
        }
        $styles = []; $styleXml = $read('word/styles.xml');
        if ($styleXml !== '') {
            $sx = new DOMXPath(maranda_docx_xml($styleXml)); $sx->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ($sx->query('//w:style') as $style) {
                $level = $sx->query('./w:pPr/w:outlineLvl', $style)->item(0);
                if ($level) $styles[$style->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'styleId')] = min(6, max(2, (int) $level->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') + 1));
            }
        }
        $formats = []; $numbering = $read('word/numbering.xml');
        if ($numbering !== '') {
            $nx = new DOMXPath(maranda_docx_xml($numbering)); $nx->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            foreach ($nx->query('//w:num') as $num) {
                $id = $num->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                $abstract = $nx->query('./w:abstractNumId', $num)->item(0);
                if (!$abstract) continue;
                $aid = $abstract->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
                if (!ctype_digit($aid)) continue;
                foreach ($nx->query('//w:abstractNum[@w:abstractNumId="' . $aid . '"]/w:lvl') as $lvl) {
                    $fmt = $nx->query('./w:numFmt', $lvl)->item(0);
                    $formats[$id][$lvl->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl')] = $fmt && $fmt->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') !== 'bullet';
                }
            }
        }
        $inline = static function (DOMNode $node) use ($xp, $links): string {
            $html = '';
            foreach ($xp->query('.//w:r', $node) as $run) {
                $text = '';
                foreach ($run->childNodes as $child) {
                    if ($child->localName === 't') $text .= esc_html($child->textContent);
                    elseif ($child->localName === 'br') $text .= '<br>';
                    elseif ($child->localName === 'tab') $text .= ' ';
                }
                foreach (['b' => 'strong', 'i' => 'em'] as $prop => $tag) {
                    $flag = $xp->query('./w:rPr/w:' . $prop, $run)->item(0);
                    if ($flag && !in_array($flag->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val'), ['0', 'false', 'off'], true)) $text = '<' . $tag . '>' . $text . '</' . $tag . '>';
                }
                $parent = $run->parentNode;
                if ($parent->localName === 'hyperlink') {
                    $url = $links[$parent->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')] ?? '';
                    if ($url !== '') $text = '<a href="' . esc_attr($url) . '">' . $text . '</a>';
                }
                $html .= $text;
            }
            return $html;
        };
        $out = ''; $listTag = ''; $listId = '';
        $closeList = static function () use (&$out, &$listTag, &$listId): void {
            if ($listTag !== '') $out .= '</' . $listTag . '><!-- /wp:list -->';
            $listTag = ''; $listId = '';
        };
        foreach ($xp->query('/w:document/w:body/w:p') as $p) {
            $html = $inline($p); if (trim(strip_tags($html)) === '') continue;
            $num = $xp->query('./w:pPr/w:numPr/w:numId', $p)->item(0);
            if ($num) {
                $id = $num->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val');
                $indent = $xp->query('./w:pPr/w:numPr/w:ilvl', $p)->item(0);
                $level = $indent ? $indent->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') : '0';
                if ((int) $level > 0) throw new RuntimeException('Les listes imbriquées ne sont pas encore prises en charge. Utilise une liste à un seul niveau pour cet import.');
                $tag = !empty($formats[$id][$level]) ? 'ol' : 'ul';
                if ($listTag !== $tag || $listId !== $id) { $closeList(); $out .= '<!-- wp:list' . ($tag === 'ol' ? ' {"ordered":true}' : '') . ' --><' . $tag . ' class="wp-block-list">'; $listTag = $tag; $listId = $id; }
                $out .= '<!-- wp:list-item --><li>' . $html . '</li><!-- /wp:list-item -->'; continue;
            }
            $closeList();
            $style = $xp->query('./w:pPr/w:pStyle', $p)->item(0);
            $sid = $style ? $style->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') : '';
            $outline = $xp->query('./w:pPr/w:outlineLvl', $p)->item(0);
            $level = $outline ? min(6, max(2, (int) $outline->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') + 1)) : ($styles[$sid] ?? 0);
            if (!$level && preg_match('/^(?:Heading|Titre)([1-6])$/i', $sid, $match)) $level = max(2, (int) $match[1]);
            $out .= $level ? '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . $html . '</h' . $level . '><!-- /wp:heading -->' : '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->';
        }
        $closeList(); if ($out === '') throw new RuntimeException('Le document ne contient pas de texte à importer.');
        return $out;
    } finally { $zip->close(); }
}

add_action('admin_menu', static function (): void {
    add_submenu_page('edit.php', 'Importer un article', 'Importer un article', 'edit_posts', 'maranda-import-article', static function (): void {
        if (!current_user_can('edit_posts')) return;
        echo '<div class="wrap"><h1>Importer un article LibreOffice</h1><p>Dans LibreOffice Writer, enregistre une copie au format Word 2007–365 (.docx). Les titres, paragraphes, listes simples, liens, gras et italiques seront convertis en blocs modifiables. Les images, tableaux, notes et listes imbriquées doivent être ajoutés séparément. L’article sera créé comme brouillon.</p>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('maranda_import_article');
            try {
                $file = $_FILES['article'] ?? [];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'docx' || ($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Choisis un fichier .docx de moins de 10 Mo.');
                $title = sanitize_text_field(wp_unslash($_POST['article_title'] ?? ''));
                if ($title === '') throw new RuntimeException('Indique le titre de l’article.');
                $content = maranda_docx_blocks($file['tmp_name']);
                $id = wp_insert_post(['post_title' => $title, 'post_content' => wp_slash($content), 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => get_current_user_id()], true);
                if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
                echo '<div class="notice notice-success"><p>Brouillon créé. <a href="' . esc_url(get_edit_post_link($id, 'raw')) . '">Relire et modifier l’article</a></p></div>';
            } catch (Throwable $error) { echo '<div class="notice notice-error"><p>' . esc_html($error->getMessage()) . '</p></div>'; }
        }
        echo '<form method="post" enctype="multipart/form-data">'; wp_nonce_field('maranda_import_article');
        echo '<p><label for="article_title">Titre de l’article</label><br><input class="regular-text" id="article_title" name="article_title" required></p><p><label for="article">Document LibreOffice (.docx, maximum 10 Mo)</label><br><input type="file" id="article" name="article" accept=".docx" required></p>';
        submit_button('Créer le brouillon'); echo '</form></div>';
    });
});
