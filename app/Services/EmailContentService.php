<?php

namespace App\Services;

class EmailContentService
{
    public function clean(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p[style],br,strong,em,u,s,h1[style],h2[style],h3[style],ul[style],ol[style],li[style],blockquote[style],a[href|title|style],img[src|alt|width|height|style],table[role|width|cellpadding|cellspacing|style|data-email-button|data-label|data-href|data-background|data-color|data-align|data-radius],thead,tbody,tfoot,tr,th[style|width|align|bgcolor|colspan|rowspan],td[style|width|align|bgcolor|colspan|rowspan],hr,span[style],div[style|data-email-image|data-src|data-alt|data-align|data-link]');
        $config->set('URI.AllowedSchemes', ['https' => true, 'mailto' => true]);
        $config->set('HTML.SafeIframe', false);
        $config->set('URI.DefaultScheme', 'https');
        $config->set('CSS.AllowTricky', true);
        $config->set('CSS.Proprietary', true); // Border-radius only; the property allowlist excludes filters and positioning.
        $config->set('CSS.AllowedProperties', ['color', 'background-color', 'font-size', 'font-family', 'font-weight', 'text-align', 'padding', 'margin', 'border', 'width', 'max-width', 'line-height', 'height', 'display', 'border-radius', 'text-decoration', 'border-collapse', 'border-top', 'border-left', 'padding-left', 'letter-spacing', 'list-style-type']);
        $definition = $config->getHTMLDefinition(true);
        $definition->addAttribute('table', 'role', new \HTMLPurifier_AttrDef_Enum(['presentation']));
        foreach (['data-email-button', 'data-label', 'data-href', 'data-background', 'data-color', 'data-align', 'data-radius'] as $attribute) {
            $definition->addAttribute('table', $attribute, 'Text');
        }
        foreach (['data-email-image', 'data-src', 'data-alt', 'data-align', 'data-link'] as $attribute) {
            $definition->addAttribute('div', $attribute, 'Text');
        }
        $html = str_replace('{{unsubscribe_url}}', 'https://dispatch.invalid/unsubscribe-placeholder', $html);

        return app(EmailMarkup::class)->fragment(str_replace('https://dispatch.invalid/unsubscribe-placeholder', '{{unsubscribe_url}}', (new \HTMLPurifier($config))->purify($html)));
    }

    public function personaliseText(string $text, array $values): string
    {
        foreach (['first_name' => 'there', 'last_name' => '', 'email' => '', 'sender_name' => '', 'sender_address' => '', 'unsubscribe_url' => ''] as $key => $fallback) {
            $value = trim((string) ($values[$key] ?? ''));
            $text = str_replace('{{'.$key.'}}', $value !== '' ? $value : $fallback, $text);
        }

        return $text;
    }

    public function render(string $html, array $values, string $previewText = ''): string
    {
        $content = app(EmailMarkup::class)->fragment($this->clean($html), true, $values);

        return view('email.document', compact('content', 'previewText'))->render();
    }

    public function starters(array $branding = []): array
    {
        return app(EmailStarterTemplates::class)->all($branding);
    }

    public function text(string $html): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$this->clean($html).'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $a) {
            if ($a->hasAttribute('href')) {
                $a->appendChild($dom->createTextNode(' ('.rawurldecode($a->getAttribute('href')).')'));
            }
        }
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            if (in_array($el->tagName, ['p', 'h1', 'h2', 'h3', 'li', 'div', 'tr', 'br', 'hr'])) {
                $el->appendChild($dom->createTextNode("\n\n"));
            }
        }
        $text = trim(preg_replace('/\n{3,}/', "\n\n", $dom->getElementsByTagName('body')->item(0)->textContent));
        if (! str_contains($text, '{{unsubscribe_url}}')) {
            $text .= "\n\nUnsubscribe: {{unsubscribe_url}}";
        }

        return $text;
    }
}
