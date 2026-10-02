<?php
declare(strict_types=1);

/**
 * Sanitize HTML stored in kanban_cards.description (whitelist tags/attributes).
 */
function kanban_sanitize_card_description_html(string $html): string {
    $html = trim(str_replace("\0", '', $html));
    if ($html === '') {
        return '';
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $wrapped = '<?xml encoding="UTF-8"><div id="cg-desc-root">' . $html . '</div>';
    if (@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD) === false) {
        libxml_clear_errors();
        return '';
    }

    $root = $dom->getElementById('cg-desc-root');
    if (!$root) {
        libxml_clear_errors();
        return '';
    }

    $children = iterator_to_array($root->childNodes);
    foreach ($children as $child) {
        kanban_sanitize_desc_clean_node($child, $dom);
    }

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    libxml_clear_errors();
    return trim($out);
}

function kanban_sanitize_desc_allowed_tag(string $tag): bool {
    static $allowed = [
        'p' => true, 'div' => true, 'br' => true,
        'strong' => true, 'b' => true, 'em' => true, 'i' => true,
        'u' => true, 's' => true, 'strike' => true, 'del' => true,
        'ul' => true, 'ol' => true, 'li' => true,
        'a' => true, 'span' => true,
    ];
    return isset($allowed[strtolower($tag)]);
}

/**
 * Keep only safe font-family / font-size declarations from an inline style string.
 */
function kanban_sanitize_desc_font_style(string $style): string {
    $style = trim(str_replace(["\0", "\r", "\n"], '', $style));
    if ($style === '' || strlen($style) > 400) {
        return '';
    }
    if (preg_match('/expression\s*\(|url\s*\(|@import|javascript:|behavior\s*:/i', $style)) {
        return '';
    }

    $kept = [];
    foreach (explode(';', $style) as $part) {
        $part = trim($part);
        if ($part === '' || strpos($part, ':') === false) {
            continue;
        }
        [$prop, $val] = array_map('trim', explode(':', $part, 2));
        $propLower = strtolower($prop);
        $val = trim($val, " \t\"'");

        if ($propLower === 'font-family') {
            if (
                $val !== ''
                && strlen($val) <= 160
                && preg_match('/\A[a-zA-Z0-9\s\-_\'\",\.]+\z/', $val)
                && !preg_match('/[<>{};]/', $val)
            ) {
                $kept[] = 'font-family: ' . $val;
            }
        } elseif ($propLower === 'font-size') {
            if (preg_match('/\A\d{1,3}(\.\d{1,2})?(px|pt|em|rem|%)\z/i', $val)) {
                $kept[] = 'font-size: ' . strtolower($val);
            }
        }
    }

    return implode('; ', $kept);
}

function kanban_sanitize_desc_clean_node(DOMNode $node, DOMDocument $dom): void {
    if ($node->nodeType === XML_COMMENT_NODE) {
        $node->parentNode?->removeChild($node);
        return;
    }
    if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
        return;
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return;
    }

    /** @var DOMElement $el */
    $el = $node;
    $tag = strtolower($el->tagName);

    if (!kanban_sanitize_desc_allowed_tag($tag)) {
        $parent = $el->parentNode;
        if ($parent) {
            while ($el->firstChild) {
                $parent->insertBefore($el->firstChild, $el);
            }
            $parent->removeChild($el);
        }
        return;
    }

    $href = $tag === 'a' ? trim((string)$el->getAttribute('href')) : '';
    $oldClass = $tag === 'ul' || $tag === 'span' || $tag === 'li' ? (string)$el->getAttribute('class') : '';
    $oldStyle = $tag === 'span' ? (string)$el->getAttribute('style') : '';
    $dataChecked = $tag === 'li' ? (string)$el->getAttribute('data-checked') : '';

    $attrNames = [];
    if ($el->hasAttributes()) {
        foreach ($el->attributes as $attr) {
            $attrNames[] = $attr->name;
        }
    }
    foreach ($attrNames as $name) {
        $el->removeAttribute($name);
    }

    if ($tag === 'a') {
        if (!preg_match('#\Ahttps?://#i', $href) || strlen($href) > 2048) {
            $frag = $dom->createDocumentFragment();
            while ($el->firstChild) {
                $frag->appendChild($el->firstChild);
            }
            $el->parentNode?->replaceChild($frag, $el);
            return;
        }
        $el->setAttribute('href', $href);
        $el->setAttribute('rel', 'noopener noreferrer');
        $el->setAttribute('target', '_blank');
    } elseif ($tag === 'span') {
        if ($oldClass === 'cg-desc-emoji') {
            $el->setAttribute('class', 'cg-desc-emoji');
        }
        $safeStyle = kanban_sanitize_desc_font_style($oldStyle);
        if ($safeStyle !== '') {
            $el->setAttribute('style', $safeStyle);
        }
    } elseif ($tag === 'ul' && strpos(' ' . $oldClass . ' ', ' cg-desc-checklist ') !== false) {
        $el->setAttribute('class', 'cg-desc-checklist');
    } elseif ($tag === 'li') {
        $parent = $el->parentNode;
        if ($parent instanceof DOMElement && strtolower($parent->nodeName) === 'ul'
            && strpos(' ' . $parent->getAttribute('class') . ' ', ' cg-desc-checklist ') !== false) {
            $el->setAttribute('class', 'cg-desc-task');
            $el->setAttribute('data-checked', $dataChecked === '1' ? '1' : '0');
        }
    }

    $kids = iterator_to_array($el->childNodes);
    foreach ($kids as $ch) {
        kanban_sanitize_desc_clean_node($ch, $dom);
    }
}

/**
 * Fetch <title> from a public URL (HTTPS/HTTP). Used for paste-link labels; keep limits tight (SSRF mitigation).
 */
function kanban_fetch_page_title(string $url): string {
    if (!function_exists('curl_init')) {
        return '';
    }
    $ch = curl_init($url);
    if ($ch === false) {
        return '';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'CineGridKanban/1.0 (+https://cinegrid.net)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_RANGE => '0-393215',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return '';
    }
    if (preg_match('/<title[^>]*>([^<]{1,500})<\/title>/is', $body, $m)) {
        $t = html_entity_decode(trim(preg_replace('/\s+/u', ' ', $m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $t !== '' ? mb_substr($t, 0, 200) : '';
    }
    return '';
}
