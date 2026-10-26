<?php

namespace App\Services;

/** Original, versioned designs. Creating one never updates an existing template. */
class EmailStarterTemplates
{
    public function all(array $branding = []): array
    {
        $primary = preg_match('/^#[0-9a-f]{6}$/i', $branding['primary_color'] ?? '') ? $branding['primary_color'] : '#21564b';
        $button = preg_match('/^#[0-9a-f]{6}$/i', $branding['button_color'] ?? '') ? $branding['button_color'] : $primary;
        $secondary = preg_match('/^#[0-9a-f]{6}$/i', $branding['secondary_color'] ?? '') ? $branding['secondary_color'] : '#edf3ef';
        $esc = fn ($v) => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logo = app(EmailMarkup::class)->safeUrl($branding['logo_url'] ?? '', false) ? '<p><img src="'.$esc($branding['logo_url']).'" width="120" alt="Company logo" style="max-width:120px;height:auto;"></p>' : '';
        $brand = $logo.'<p style="color:'.$primary.';font-size:14px;font-weight:700;letter-spacing:1px;">{{sender_name}}</p>';
        $label = fn ($text) => '<p style="font-size:11px;font-weight:700;letter-spacing:2px;color:'.$primary.';margin:32px 0 16px;">'.$text.'</p>';
        $cta = fn ($text, $align = 'left') => '<table data-email-button="true" data-label="'.$text.'" data-href="https://example.com" data-background="'.$button.'" data-color="#ffffff" data-align="'.$align.'" data-radius="8" style="margin:24px 0;width:100%;"><tbody><tr><td align="'.$align.'"><a href="https://example.com" style="display:inline-block;background-color:'.$button.';color:#ffffff;text-decoration:none;border-radius:8px;padding:14px 24px;font-size:15px;font-weight:700;line-height:1.4;">'.$text.'</a></td></tr></tbody></table>';
        $footerNote = trim($branding['footer_text'] ?? '') ?: 'A considered update from our team. To stop receiving these emails, use the unsubscribe link below.';
        $footer = '<hr><p style="font-size:12px;line-height:1.7;color:#64748b;">{{sender_name}}<br>{{sender_address}}</p><p style="font-size:12px;color:#64748b;">'.$esc($footerNote).'</p><p style="font-size:12px;"><a href="{{unsubscribe_url}}" style="color:#64748b;">Unsubscribe from these emails</a></p>';
        $items = [
            ['Business announcement', 'Announcement', 'Something good is taking shape', 'A confident announcement with a clear message and one next step.',
                $brand.$label('A NEW CHAPTER').'<h1 style="color:#173f38;">Good things.<br>A fresh beginning.</h1><p>Hello {{first_name}},</p><p>We have been working on something we think you will love. Today, we are delighted to share what comes next.</p><p style="background-color:'.$secondary.';padding:24px;border-radius:10px;">A considered update, built around the people who matter most: our customers. Here is what is changing, and why it is worth your attention.</p>'.$cta('Discover what is new').'<p style="font-size:14px;color:#64748b;">Have a question? Reply to this email. We would love to hear from you.</p>'],
            ['Newsletter', 'Newsletter', 'The monthly edit — ideas worth your time', 'An editorial digest with a featured story and two shorter reads.',
                $brand.$label('THE MONTHLY EDIT · ISSUE 01').'<h1 style="color:#173f38;">A little inspiration.<br>A useful perspective.</h1><p>Hello {{first_name}}, here is your thoughtful collection of news, ideas and practical advice from our team.</p><hr>'.$label('IN FOCUS').'<h2>The small changes that make a big difference</h2><p>Our featured story explores a better way to approach a familiar challenge, with practical ideas you can put to work today.</p><p><a href="https://example.com/stories/featured">Read the full story →</a></p><hr><h3>01 / Behind the scenes</h3><p>A closer look at the people and decisions behind our latest work.</p><p><a href="https://example.com/stories/team">Meet the team →</a></p><h3>02 / One thing to try</h3><p>A simple, useful idea to bring a fresh perspective to your week.</p><p><a href="https://example.com/stories/ideas">Explore the idea →</a></p>'],
            ['Product or service promotion', 'Promotion', 'Meet your next great idea', 'A warm product story with an original hero illustration and clear benefits.',
                $brand.'<p><img src="/email-assets/studio-hero.png" width="520" alt="Original abstract illustration of balanced shapes in warm neutral and green colours" style="width:100%;max-width:520px;height:auto;border-radius:10px;"></p>'.$label('MADE WITH YOU IN MIND').'<h1 style="color:#173f38;">Less friction.<br>More possibility.</h1><p>A thoughtful solution to help you spend less time on the everyday and more time on what matters.</p><ul><li><strong>Simple from the start.</strong> Get going with a clear, practical approach.</li><li><strong>Built around your needs.</strong> Useful details that make a difference.</li><li><strong>People you can count on.</strong> Friendly support when you need it.</li></ul>'.$cta('Explore the possibilities').'<p style="font-size:14px;"><a href="https://example.com/contact">Prefer a conversation? Talk to our team →</a></p>'],
            ['Event invitation', 'Event', 'Save your place — a conversation worth having', 'A focused invitation with event details and a registration button.',
                $brand.$label('YOU ARE INVITED').'<h1 style="color:#173f38;">Good people.<br>Fresh perspectives.</h1><p>Hello {{first_name}}, join us for a thoughtful conversation, new connections and ideas to take away.</p><div style="background-color:'.$secondary.';padding:24px;border-radius:10px;"><h3 style="margin:0 0 16px;">The details</h3><p><strong>When</strong><br>Thursday, 18 June · 10:00–11:30 BST<br>Demonstration date — replace before sending</p><p><strong>Where</strong><br>Your venue or online meeting location</p><p style="margin:0;"><strong>What to expect</strong><br>A short presentation, an open discussion and time for your questions.</p></div>'.$cta('Reserve your place').'<p style="font-size:14px;color:#64748b;">Let us know about any accessibility or dietary requirements when you register.</p>'],
            ['Simple professional email', 'Professional', 'A personal note from our team', 'A clean personal message with a quiet, optional call to action.',
                $brand.'<hr><p>Hello {{first_name}},</p><p>We wanted to share a quick update with you.</p><p>This is a space for a clear, personal message. Keep it useful, keep it focused, and make it easy for your reader to know what comes next.</p>'.$cta('Find out more').'<p>If you have any questions, simply reply. We are always happy to help.</p><p>With best wishes,<br><strong>{{sender_name}}</strong></p>'],
        ];

        return array_map(fn ($i) => ['name' => $i[0].' · Studio v2', 'category' => $i[1], 'subject' => $i[2], 'description' => $i[3], 'preview_text' => $i[3], 'html' => $i[4].$footer, 'text_body' => app(EmailContentService::class)->text($i[4].$footer)], $items);
    }
}
