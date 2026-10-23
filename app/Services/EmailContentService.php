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

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:#f4f5f7;padding:24px;font-family:Arial,sans-serif;color:#202638"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center"><table role="presentation" width="600" cellpadding="24" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff"><tr><td>'.$html.'</td></tr></table></td></tr></table></body></html>';
    }

    public function starters(): array
    {
        return [
            ['name' => 'A warm welcome', 'category' => 'Welcome', 'subject' => 'Welcome to our community', 'preview_text' => 'A thoughtful introduction', 'html' => '<h1>Welcome, {{first_name}}</h1><p>We are glad you are here. Discover the stories, ideas and updates we have prepared for you.</p><p><a href="https://example.com" style="background-color:#4f46e5;color:#ffffff;padding:12px">Explore what is new</a></p><hr><p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>'],
            ['name' => 'The monthly edit', 'category' => 'Newsletter', 'subject' => 'Your monthly edit', 'preview_text' => 'Fresh ideas, carefully selected', 'html' => '<h1>The monthly edit</h1><p>Hello {{first_name}},</p><h2>One idea worth sharing</h2><p>Use this space for a useful update your audience asked to receive.</p><h2>What is next</h2><p>Share an upcoming event or a helpful resource.</p><hr><p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>'],
            ['name' => 'A product announcement', 'category' => 'Announcement', 'subject' => 'Something new to explore', 'preview_text' => 'A closer look at our latest update', 'html' => '<h1>Meet our latest update</h1><p>A clear introduction to what changed and why it matters to your audience.</p><ul><li>A useful improvement</li><li>A thoughtful detail</li></ul><p><a href="https://example.com">Take a closer look</a></p><hr><p>{{sender_name}} · {{sender_address}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>'],
        ];
    }

    public function text(string $html): string
    {
        return html_entity_decode(strip_tags(str_replace(['</p>', '</h1>', '</h2>', '<br>'], "\n", $html)))."\nUnsubscribe: {{unsubscribe_url}}";
    }
}
