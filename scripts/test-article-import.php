<?php
/** Exercise DOCX conversion without WordPress or a live database. */
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s, $protocols = []) { return preg_match('/^(https?:|mailto:)/', $s) ? esc_attr($s) : ''; }
require dirname(__DIR__) . '/inc/article-import.php';
function check_import($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$file = tempnam(sys_get_temp_dir(), 'docx-test');
try {
 $zip = new ZipArchive(); $zip->open($file, ZipArchive::OVERWRITE);
 $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Mon titre</w:t></w:r></w:p><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Texte &amp; suite</w:t></w:r><w:hyperlink r:id="link1"><w:r><w:t>Lien</w:t></w:r></w:hyperlink></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Premier</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Deuxième</w:t></w:r></w:p></w:body></w:document>');
 $zip->addFromString('word/_rels/document.xml.rels', '<Relationships><Relationship Id="link1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/article"/></Relationships>');
 $zip->close(); $out = maranda_docx_blocks($file);
 check_import(str_contains($out, '<h2 class="wp-block-heading">Mon titre</h2>'), 'Heading style lost');
 check_import(str_contains($out, '<strong>Texte &amp; suite</strong>'), 'Bold or escaping lost');
 check_import(str_contains($out, '<a href="https://example.com/article">Lien</a>'), 'Link lost');
 check_import(substr_count($out, '<!-- wp:list -->') === 1 && substr_count($out, '<!-- wp:list-item -->') === 2, 'List grouping failed');
 $zip->open($file); $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl/></w:body></w:document>'); $zip->close();
 $rejected = false; try { maranda_docx_blocks($file); } catch (RuntimeException $e) { $rejected = true; }
 check_import($rejected, 'Unsupported table must be reported');
 $rejected = false; try { maranda_docx_xml('<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>'); } catch (RuntimeException $e) { $rejected = true; }
 check_import($rejected, 'External entity document must be rejected');
 echo "Article import checks passed\n";
} finally { unlink($file); }
