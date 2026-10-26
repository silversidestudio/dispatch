<?php

namespace App\Services;

class EmailContentService
{
    public function clean(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', 'p[style],br,strong,em,u,s,h1[style],h2[style],h3[style],ul,ol,li,blockquote,a[href|title|style],img[src|alt|width|height],table[width|cellpadding|cellspacing|style],tbody,tr,td[style|width],hr,span[style],div[style]');
        $config->set('URI.AllowedSchemes', ['https' => true, 'mailto' => true]);
        $config->set('HTML.SafeIframe', false);
        $config->set('CSS.AllowedProperties', ['color', 'background-color', 'font-size', 'font-family', 'font-weight', 'text-align', 'padding', 'margin', 'border', 'width', 'max-width', 'line-height']);
        $html = str_replace('{{unsubscribe_url}}', 'https://dispatch.invalid/unsubscribe-placeholder', $html);

        return str_replace('https://dispatch.invalid/unsubscribe-placeholder', '{{unsubscribe_url}}', (new \HTMLPurifier($config))->purify($html));
    }

    public function personaliseText(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }

    public function render(string $html, array $values): string
    {
        $html = $this->clean($html);
        foreach ($values as $key => $value) {
            $html = str_replace('{{'.$key.'}}', htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
        }

        if (isset($values['unsubscribe_url'])) {
            $href = 'href="'.htmlspecialchars((string) $values['unsubscribe_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
            $html = str_replace($href, 'clicktracking="off" '.$href, $html);
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f4f5f7;padding:24px;font-family:Arial,sans-serif;color:#202638"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center"><table role="presentation" width="600" cellpadding="24" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff"><tr><td>'.$html.'</td></tr></table></td></tr></table></body></html>';
    }

    public function starters(): array
    {
        $items = [
            ['Business announcement', 'Announcement', 'An important update from our team', '<h1 style="color:#26324a">An update worth sharing</h1><p>Hello {{first_name}},</p><p>Share a clear announcement and explain what it means for your audience.</p>'],
            ['Newsletter', 'Newsletter', 'Your latest news and insights', '<h1 style="color:#26324a">The monthly edit</h1><h2>One useful idea</h2><p>Share a practical insight your audience asked to receive.</p><hr><h2>From our team</h2><p>A short update with a helpful link.</p>'],
            ['Product or service promotion', 'Promotion', 'Discover what is new', '<h1 style="color:#26324a">A fresh perspective</h1><p>Introduce a useful product or service with a clear benefit.</p><p><a href="https://example.com" style="background-color:#4f46e5;color:#ffffff;padding:12px">Explore the details</a></p>'],
            ['Event invitation', 'Event', 'You are invited', '<h1 style="color:#26324a">Let us get together</h1><p>Hello {{first_name}},</p><p>Join us for a useful conversation.</p><h2>When and where</h2><p>Include the date, time, location and accessibility details.</p><p><a href="https://example.com" style="background-color:#4f46e5;color:#ffffff;padding:12px">View event details</a></p>'],
            ['Simple professional email', 'Professional', 'A note from our team', '<p>Hello {{first_name}},</p><p>Write a clear, personal message with one useful purpose.</p><p>Thank you,<br>{{sender_name}}</p>'],
        ];

        return array_map(fn ($i) => ['name' => $i[0], 'category' => $i[1], 'subject' => $i[2], 'preview_text' => 'News and updates from our team', 'html' => $i[3].'<hr><p style="font-size:12px;color:#667085">{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>'], $items);
    }

    public function text(string $html): string
    {
        return html_entity_decode(strip_tags(str_replace(['</p>', '</h1>', '</h2>', '<br>'], "\n", $html)))."\nUnsubscribe: {{unsubscribe_url}}";
    }
}
