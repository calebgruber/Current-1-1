<?php

namespace App\Support\Html;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist HTML sanitizer for user-authored document templates and rich
 * text. Removes scripts, event handlers, frames, forms and dangerous URLs
 * while keeping the layout/styling markup documents need.
 */
final class HtmlSanitizer
{
    private const TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'i', 'img', 'ins',
        'li', 'main', 'mark', 'ol', 'p', 'pre', 's', 'section', 'small', 'span', 'strong', 'style', 'sub', 'sup',
        'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul', 'article', 'aside', 'svg', 'path', 'rect',
        'circle', 'line', 'polyline', 'polygon', 'g', 'text',
    ];

    private const DROP_WITH_CONTENT = ['script', 'iframe', 'object', 'embed', 'applet', 'frame', 'frameset', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'noscript', 'template'];

    private const ATTRIBUTES = [
        'class', 'id', 'style', 'title', 'align', 'valign', 'width', 'height', 'colspan', 'rowspan', 'href', 'src', 'alt',
        'target', 'rel', 'dir', 'lang', 'role', 'aria-label', 'data-block', 'data-field', 'border', 'cellpadding', 'cellspacing',
        'viewbox', 'd', 'fill', 'stroke', 'stroke-width', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'points',
        'transform', 'xmlns', 'font-size', 'text-anchor', 'span',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__root">'.$html.'</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }
        $this->clean($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('/@import[^;]*;?/i', '', $css) ?? '';
        $css = preg_replace('/expression\s*\(/i', '(', $css) ?? '';
        $css = preg_replace('/(javascript|vbscript)\s*:/i', '', $css) ?? '';
        $css = preg_replace('/-moz-binding|behavior\s*:/i', '', $css) ?? '';

        return str_ireplace('</style', '', $css);
    }

    private function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }
            if (! in_array($tag, self::TAGS, true)) {
                // unwrap unknown elements, keep their (cleaned) children
                $this->clean($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            if ($tag === 'style') {
                $child->textContent = $this->sanitizeCss($child->textContent);

                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (! in_array($name, self::ATTRIBUTES, true) && ! str_starts_with($name, 'data-')) {
                    $child->removeAttribute($attr->name);

                    continue;
                }
                if (in_array($name, ['href', 'src'], true) && ! $this->safeUrl($attr->value, $name === 'src')) {
                    $child->removeAttribute($attr->name);
                }
                if ($name === 'style') {
                    $child->setAttribute('style', $this->sanitizeCss($attr->value));
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $this->clean($child);
        }
    }

    private function safeUrl(string $url, bool $isImage): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }
        if ($isImage && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,#i', $url)) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }
}
