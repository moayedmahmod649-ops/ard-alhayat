<?php
declare(strict_types=1);
/**
 * Allowlist HTML sanitizer (from the Tawasot v5 build): parse with the HTML5 parser, then emit a brand-new string
 * from allowed elements + text only. Attributes are discarded except a[href] (http/https/mailto/tel) and img[src] limited to /uploads/*.webp.
 */
const RT_ALLOWED = ['p','br','strong','b','em','i','u','h2','h3','ul','ol','li','blockquote','a','img'];
const RT_DROP    = ['script','style','iframe','object','embed','svg','math','template','noscript','form','input','button','textarea','select','option','link','meta','base','frame','frameset','head','title','xml','video','audio','picture','source','canvas'];
function sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    if (mb_strlen($html) > 200000) $html = mb_substr($html, 0, 200000);
    if (class_exists('Dom\HTMLDocument')) {
        $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body><div id="rt-root">' . $html . '</div></body></html>', LIBXML_NOERROR, 'UTF-8');
        $root = $doc->getElementById('rt-root');
    } else {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="rt-root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        $root = $doc->getElementById('rt-root');
    }
    if (!$root) return '';
    $out = rt_emit($root, 0);
    $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $out) ?? '';
    return trim($out);
}
function rt_emit(object $node, int $depth): string {
    if ($depth > 24) return '';
    $s = '';
    foreach ($node->childNodes as $n) {
        if ($n->nodeType === XML_TEXT_NODE) { $s .= htmlspecialchars($n->nodeValue ?? '', ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8'); continue; }
        if ($n->nodeType !== XML_ELEMENT_NODE) continue; // comments, PIs, CDATA dropped
        $tag = strtolower($n->localName ?? $n->nodeName);
        if (($n->namespaceURI ?? 'http://www.w3.org/1999/xhtml') !== 'http://www.w3.org/1999/xhtml' && $n->namespaceURI !== null) continue; // svg/mathml content
        if (in_array($tag, RT_DROP, true)) continue;
        if ($tag === 'div' || $tag === 'p') {
            $block = false;
            foreach ($n->childNodes as $c) if ($c->nodeType === XML_ELEMENT_NODE && in_array(strtolower($c->localName ?? $c->nodeName), ['p','div','h2','h3','ul','ol','blockquote','table','section','article'], true)) { $block = true; break; }
            if ($block) { $s .= rt_emit($n, $depth + 1); continue; }
            $tag = 'p';
        }
        if (!in_array($tag, RT_ALLOWED, true)) { $s .= rt_emit($n, $depth + 1); continue; }
        $tag = ['b' => 'strong', 'i' => 'em'][$tag] ?? $tag;
        if ($tag === 'br') { $s .= '<br>'; continue; }
        if ($tag === 'img') { // only our own re-encoded uploads
            $src = (string)$n->getAttribute('src');
            if (preg_match('#^/uploads/[a-z]+-[a-f0-9]{16}(-[mt])?\.webp$#', $src)) $s .= '<img src="' . e($src) . '" alt="' . e(mb_substr((string)$n->getAttribute('alt'), 0, 200)) . '" loading="lazy">';
            continue;
        }
        $attr = '';
        if ($tag === 'a') {
            $href = html_entity_decode((string)$n->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $href = preg_replace('/[\x00-\x20\x7F\x{200B}-\x{200F}\x{2028}\x{2029}\x{FEFF}]+/u', '', $href) ?? '';
            if (!preg_match('#^(https?://[^\s]+|mailto:[^\s]+|tel:[0-9+()\-.]+)$#i', $href)) { $s .= rt_emit($n, $depth + 1); continue; }
            $attr = ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" rel="noopener nofollow"' . (preg_match('#^https?://#i', $href) ? ' target="_blank"' : '');
        }
        $s .= "<$tag$attr>" . rt_emit($n, $depth + 1) . "</$tag>";
    }
    return $s;
}
/** Plain text (one paragraph per blank-line block) to safe HTML. */
function text_to_html(string $s): string {
    $parts = preg_split('/\R{2,}/u', trim($s)) ?: [];
    return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p)), false) . '</p>', array_filter($parts, fn($p) => trim($p) !== '')));
}
