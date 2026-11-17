<?php

namespace Database\Seeders;

use App\Models\EmailTemplateCatalogItem;
use App\Models\Organisation;
use App\Services\EmailContentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RelaxaTemplateCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $organisationId = filter_var(getenv('RELAXA_ORGANISATION_ID') ?: null, FILTER_VALIDATE_INT);
        if (! $organisationId || $organisationId < 1) {
            throw new RuntimeException('Set RELAXA_ORGANISATION_ID to the active Relaxa organisation ID before running this seeder.');
        }

        $organisation = Organisation::whereKey($organisationId)->where('status', 'active')->firstOrFail();
        $content = app(EmailContentService::class);
        $definitions = $this->designs();

        DB::transaction(function () use ($organisation, $definitions, $content) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();

            foreach ($definitions as $definition) {
                $html = $content->clean($definition['html']);

                EmailTemplateCatalogItem::updateOrCreate(
                    [
                        'organisation_id' => $organisation->id,
                        'slug' => $definition['slug'],
                        'version' => 1,
                    ],
                    [
                        'sort_order' => $definition['sort_order'],
                        'name' => $definition['name'],
                        'category' => 'Relaxa webinars',
                        'subject' => 'OCT (Breast Care Awareness Month) & NOV (Men’s Health Awareness Month) — BOOK YOUR STAFF WEBINARS NOW',
                        'preview_text' => 'Four live staff webinars, held just for your company at a time to suit you.',
                        'description' => $definition['description'],
                        'html' => $html,
                        'text_body' => $content->text($html),
                    ],
                );
            }
        });
    }

    /** @return array<int, array{slug: string, sort_order: int, name: string, description: string, html: string}> */
    private function designs(): array
    {
        $footer = <<<'HTML'
<hr>
<p style="font-size:12px;color:#666666;">{{sender_name}}<br>{{sender_address}}</p>
<p style="font-size:12px;"><a href="{{unsubscribe_url}}">Unsubscribe from these emails</a></p>
HTML;

        $classic = '<div style="width:100%;max-width:600px;margin:0 auto;font-family:Verdana,Geneva,sans-serif;font-size:14px;line-height:1.6;color:#323131;">'
            .'<p style="font-size:18px;color:#0000cd;">Help raise awareness in your offices on <strong>Men’s and Women’s Health.</strong> October is <strong><em>Breast Cancer Awareness month</em></strong> + ‘Movember’ is <strong><em>Men’s Health Awareness month.</em></strong></p>'
            .'<p style="color:#ff0000;"><strong>4 webinars to select from – held live just for your company at a time to suit you. Sessions can be recorded for any employees unable to attend the live sessions.</strong></p>'
            .'<p><strong>October – Breast Care Awareness Month</strong><br>Breast Care Awareness Webinar (50mins)<br>Women’s Health &amp; Wellbeing Webinar (50mins)</p>'
            .'<p><strong>November – Movember Men’s Health Month</strong><br>Men’s Health &amp; Wellbeing Webinar (50mins)<br>Men’s Mental Health Webinar (50mins)<br>Prostate Health Tests - £9.99+VAT(+P&amp;P) - a rapid, certified self-test with 98% accuracy detecting PSA (prostate specific antigen) to help diagnose prostate cancer and prostate abnormalities You can order any number of tests.</p>'
            .'<p style="color:#230ef4;"><strong>Prices:<br>1 webinar - £389+VAT<br>2 webinars - £759+VAT</strong></p>'
            .'<p><strong><em>Please let us know if you would like more information or wish to make a booking?</em></strong></p>'
            .'<p><span style="color:#230ef4;"><strong>The Relaxa Health Promotion Team</strong></span><br><strong>Relaxa UK Limited</strong><br><strong>Tel: <span style="color:#230ef4;">020 3303 0980</span></strong><br><strong>Email:</strong> <a href="mailto:info@relaxa.co.uk" style="color:#323131;">info@relaxa.co.uk</a> or simply reply to this email</p>'
            .'<p style="color:#230ef4;"><strong>In the subject line of your email copy the text below:</strong><br>Please send me more information about the Breast Cancer Awareness / Men\'s Health Awareness Webinars.</p>'
            .'<p style="color:#230ef4;"><strong><em>Experts in Employee Health &amp; Wellness Services....</em></strong></p>'.$footer.'</div>';

        $sections = '<div style="width:100%;max-width:600px;margin:0 auto;font-family:Verdana,Geneva,sans-serif;font-size:14px;line-height:1.6;color:#323131;">'
            .'<p style="font-size:20px;line-height:1.35;color:#0000cd;font-weight:bold;">Help raise awareness in your offices on Men’s and Women’s Health.</p>'
            .'<p style="color:#cc2929;font-weight:bold;">4 webinars to select from – held live just for your company at a time to suit you. Sessions can be recorded for any employees unable to attend the live sessions.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;"><tbody>'
            .'<tr><td style="padding:16px;background-color:#f4f2ff;border-left:4px solid #230ef4;"><h2 style="margin:0 0 8px;color:#230ef4;font-size:17px;">October – Breast Care Awareness Month</h2><p style="margin:0;">Breast Care Awareness Webinar (50mins)<br>Women’s Health &amp; Wellbeing Webinar (50mins)</p></td></tr>'
            .'<tr><td style="height:12px;line-height:12px;">&nbsp;</td></tr>'
            .'<tr><td style="padding:16px;background-color:#f1f5ff;border-left:4px solid #0000cd;"><h2 style="margin:0 0 8px;color:#0000cd;font-size:17px;">November – Movember Men’s Health Month</h2><p style="margin:0;">Men’s Health &amp; Wellbeing Webinar (50mins)<br>Men’s Mental Health Webinar (50mins)<br>Prostate Health Tests - £9.99+VAT(+P&amp;P) - a rapid, certified self-test with 98% accuracy detecting PSA (prostate specific antigen) to help diagnose prostate cancer and prostate abnormalities. You can order any number of tests.</p></td></tr>'
            .'</tbody></table>'
            .'<p style="color:#230ef4;font-weight:bold;">Prices:<br>1 webinar - £389+VAT<br>2 webinars - £759+VAT</p>'
            .'<p><strong><em>Please let us know if you would like more information or wish to make a booking?</em></strong></p>'
            .'<p><strong>The Relaxa Health Promotion Team</strong><br><strong>Relaxa UK Limited</strong><br><strong>Tel: 020 3303 0980</strong><br><strong>Email:</strong> <a href="mailto:info@relaxa.co.uk">info@relaxa.co.uk</a> or simply reply to this email</p>'
            .'<p><strong>In the subject line of your email copy the text below:</strong><br>Please send me more information about the Breast Cancer Awareness / Men’s Health Awareness Webinars.</p>'
            .'<p style="color:#230ef4;"><strong><em>Experts in Employee Health &amp; Wellness Services....</em></strong></p>'.$footer.'</div>';

        $invitation = '<div style="width:100%;max-width:600px;margin:0 auto;font-family:Verdana,Geneva,sans-serif;font-size:15px;line-height:1.65;color:#323131;">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;background-color:#230ef4;"><tbody><tr><td style="padding:24px 20px;color:#ffffff;font-size:13px;font-weight:bold;letter-spacing:1px;">RELAXA HEALTH PROMOTION</td></tr></tbody></table>'
            .'<div style="padding:24px 8px;"><p style="color:#0000cd;font-size:18px;font-weight:bold;">Help raise awareness in your offices on <strong>Men’s and Women’s Health.</strong> October is <strong><em>Breast Cancer Awareness month</em></strong> + ‘Movember’ is <strong><em>Men’s Health Awareness month.</em></strong></p>'
            .'<p style="color:#cc2929;font-weight:bold;">4 webinars to select from – held live just for your company at a time to suit you. Sessions can be recorded for any employees unable to attend the live sessions.</p>'
            .'<h2 style="color:#230ef4;font-size:18px;">October – Breast Care Awareness Month</h2><p>Breast Care Awareness Webinar (50mins)<br>Women’s Health &amp; Wellbeing Webinar (50mins)</p>'
            .'<h2 style="color:#0000cd;font-size:18px;">November – Movember Men’s Health Month</h2><p>Men’s Health &amp; Wellbeing Webinar (50mins)<br>Men’s Mental Health Webinar (50mins)<br>Prostate Health Tests - £9.99+VAT(+P&amp;P) - a rapid, certified self-test with 98% accuracy detecting PSA (prostate specific antigen) to help diagnose prostate cancer and prostate abnormalities You can order any number of tests.</p>'
            .'<p style="padding:16px;background-color:#f4f2ff;color:#230ef4;font-weight:bold;">1 webinar - £389+VAT<br>2 webinars - £759+VAT</p>'
            .'<p><strong><em>Please let us know if you would like more information or wish to make a booking?</em></strong></p>'
            .'<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tbody><tr><td bgcolor="#230ef4" style="padding:13px 20px;border-radius:4px;"><a href="mailto:info@relaxa.co.uk?subject=Webinar%20booking%20enquiry" style="color:#ffffff;text-decoration:none;font-weight:bold;">Ask Relaxa for more information</a></td></tr></tbody></table>'
            .'<p style="font-size:13px;">The Relaxa Health Promotion Team<br>Relaxa UK Limited<br>Tel: 020 3303 0980<br><strong>Email:</strong> <a href="mailto:info@relaxa.co.uk" style="color:#0000cd;">info@relaxa.co.uk</a> or simply reply to this email</p>'
            .'<p style="font-size:13px;">In the subject line of your email copy the text below:<br>Please send me more information about the Breast Cancer Awareness / Men’s Health Awareness Webinars.</p>'
            .'<p style="color:#230ef4;font-weight:bold;"><em>Experts in Employee Health &amp; Wellness Services....</em></p></div>'.$footer.'</div>';

        return [
            ['slug' => 'relaxa-classic', 'sort_order' => 10, 'name' => 'Relaxa Classic · Purple text', 'description' => 'A close match to the original email, with the familiar blue, purple and red accents and a narrow responsive content area.', 'html' => $classic],
            ['slug' => 'relaxa-sections', 'sort_order' => 20, 'name' => 'Relaxa Clear Sections', 'description' => 'The same message arranged into distinct October and November sections for easier scanning on mobile.', 'html' => $sections],
            ['slug' => 'relaxa-invitation', 'sort_order' => 30, 'name' => 'Relaxa Webinar Invitation', 'description' => 'A more structured invitation with the same supplied colours, webinar details, prices and a direct email enquiry button.', 'html' => $invitation],
        ];
    }
}
