<?php

namespace App\Services;

use DOMDocument;
use DOMElement;

/** Email presentation is applied server-side, independently of the application UI. */
class EmailMarkup
{
    public function fragment(string $html, bool $presentation = false, array $values = []): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($presentation) {
            foreach (iterator_to_array($dom->getElementsByTagName('table')) as $table) {
                if ($table->hasAttribute('data-email-button')) {
                    $this->button($table);
                }
            }
        }
        $defaults = [
            'p' => 'margin:0 0 20px;line-height:1.7;',
            'h1' => 'margin:0 0 24px;font-size:32px;line-height:1.2;font-weight:700;letter-spacing:-0.5px;',
            'h2' => 'margin:28px 0 14px;font-size:24px;line-height:1.3;font-weight:700;',
            'h3' => 'margin:24px 0 12px;font-size:19px;line-height:1.4;font-weight:700;',
            'ul' => 'margin:0 0 24px;padding-left:24px;list-style-type:disc;',
            'ol' => 'margin:0 0 24px;padding-left:24px;list-style-type:decimal;',
            'li' => 'margin:0 0 8px;line-height:1.7;',
            'a' => 'color:#334e68;text-decoration:underline;',
            'hr' => 'border:0;border-top:1px solid #e2e8f0;margin:28px 0;',
            'blockquote' => 'margin:24px 0;padding:12px 20px;border-left:3px solid #cbd5e1;',
        ];
        foreach ($dom->getElementsByTagName('*') as $el) {
            if (! $el instanceof DOMElement) {
                continue;
            }
            if ($el->hasAttribute('href')) {
                $href = $el->getAttribute('href');
                if (! $this->safeUrl($href) && $href !== '{{unsubscribe_url}}') {
                    $el->removeAttribute('href');
                }
            }
            if ($el->tagName === 'img') {
                $src = $el->getAttribute('src');
                if (! $this->safeUrl($src, false) && ! preg_match('~^/email-assets/[a-z0-9-]+\.png$~', $src)) {
                    $el->removeAttribute('src');
                }
                if ($presentation && str_starts_with($src, '/email-assets/') && str_starts_with(config('app.url'), 'https://')) {
                    $el->setAttribute('src', rtrim(config('app.url'), '/').$src);
                }
                $el->removeAttribute('height');
                // Width is a hint; all images are constrained by their email cell.
                $this->styles($el, [], ['max-width' => '100%', 'height' => 'auto', 'display' => 'inline-block', 'border' => '0']);
            }
            if ($el->tagName === 'table') {
                $el->setAttribute('role', 'presentation');
                $el->setAttribute('cellspacing', '0');
                $el->setAttribute('cellpadding', '0');
                $this->styles($el, [], ['max-width' => '100%', 'border-collapse' => 'collapse']);
            }
            if ($presentation) {
                $this->styles($el, $this->declarations($defaults[$el->tagName] ?? ''));
            }
            if ($el->tagName === 'a' && $el->getAttribute('href') === '{{unsubscribe_url}}') {
                $el->setAttribute('clicktracking', 'off');
            }
            if ($values) {
                foreach (iterator_to_array($el->attributes) as $attr) {
                    $text = $this->replace($attr->value, $values);
                    // Never let a personalisation value introduce an unsafe URL.
                    if ($attr->name === 'href' && ! $this->safeUrl($text) && $text !== '#preview-only' && ! str_starts_with($text, '/unsubscribe/')) {
                        $el->removeAttribute('href');
                    } else {
                        $el->setAttribute($attr->name, $text);
                    }
                }
            }
        }
        if ($values) {
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//body//text()') as $node) {
                $node->nodeValue = $this->replace($node->nodeValue, $values);
            }
        }
        $out = '';
        foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $out .= $dom->saveHTML($node);
        }

        // libxml URL-encodes braces in href attributes; preserve the established tokens.
        foreach (['first_name', 'last_name', 'email', 'sender_name', 'sender_address', 'unsubscribe_url'] as $token) {
            $out = str_ireplace(rawurlencode('{{'.$token.'}}'), '{{'.$token.'}}', $out);
        }

        return $out;
    }

    /** Table-cell padding/background provide a usable classic Outlook fallback. */
    private function button(DOMElement $table): void
    {
        $a = $table->getElementsByTagName('a')->item(0);
        if (! $a instanceof DOMElement) {
            return;
        }
        $styles = $this->declarations($a->getAttribute('style'));
        $doc = $table->ownerDocument;
        $outer = $doc->createElement('tbody');
        $row = $outer->appendChild($doc->createElement('tr'));
        $container = $row->appendChild($doc->createElement('td'));
        $alignment = in_array($table->getAttribute('data-align'), ['left', 'center', 'right']) ? $table->getAttribute('data-align') : 'left';
        $container->setAttribute('align', $alignment);
        $inner = $container->appendChild($doc->createElement('table'));
        $inner->setAttribute('role', 'presentation');
        $inner->setAttribute('style', 'width:auto;');
        $cell = $inner->appendChild($doc->createElement('tbody'))->appendChild($doc->createElement('tr'))->appendChild($doc->createElement('td'));
        $cell->setAttribute('bgcolor', $styles['background-color'] ?? '#21564b');
        $this->styles($cell, ['background-color' => $styles['background-color'] ?? '#21564b', 'padding' => $styles['padding'] ?? '14px 24px', 'border-radius' => $styles['border-radius'] ?? '8px']);
        unset($styles['background-color'], $styles['border-radius']);
        $styles['padding'] = '0';
        $a->removeAttribute('style');
        $this->styles($a, $styles);
        $cell->appendChild($a);
        while ($table->firstChild) {
            $table->removeChild($table->firstChild);
        }
        $table->appendChild($outer);
    }

    private function declarations(string $style): array
    {
        $properties = [];
        foreach (explode(';', $style) as $declaration) {
            if (str_contains($declaration, ':')) {
                [$name, $value] = explode(':', $declaration, 2);
                $properties[trim(strtolower($name))] = trim($value);
            }
        }

        return $properties;
    }

    private function styles(DOMElement $el, array $defaults = [], array $overrides = []): void
    {
        $properties = array_replace($defaults, $this->declarations($el->getAttribute('style')), $overrides);
        if ($properties) {
            $el->setAttribute('style', implode(';', array_map(fn ($key, $value) => $key.':'.$value, array_keys($properties), $properties)).';');
        }
    }

    public function safeUrl(string $url, bool $mail = true): bool
    {
        if ($mail && preg_match('/^mailto:[^\s<>]+$/i', $url)) {
            return true;
        }
        $parts = parse_url($url);

        return $parts && strtolower($parts['scheme'] ?? '') === 'https' && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']) && ! preg_match('/[\x00-\x20<>]/', $url);
    }

    private function replace(string $text, array $values): string
    {
        $defaults = ['first_name' => 'there', 'last_name' => '', 'email' => '', 'sender_name' => '', 'sender_address' => '', 'unsubscribe_url' => ''];
        foreach ($defaults as $key => $fallback) {
            $value = trim((string) ($values[$key] ?? ''));
            $defaults['{{'.$key.'}}'] = $value !== '' ? $value : $fallback;
            unset($defaults[$key]);
        }

        return strtr($text, $defaults);
    }
}
