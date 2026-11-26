<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateCatalogItem;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Services\EmailContentService;
use App\Services\EmailMarkup;
use App\Services\EmailStarterTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ContentController extends Controller
{
    public function identities(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        $canManage = Gate::allows('update', $organisation);
        $readiness = [
            'provider' => config('dispatch.provider'),
            'enabled' => (bool) config('dispatch.sendgrid.enabled'),
            'configured' => (bool) $organisation->sendgrid_api_key,
            'webhook_configured' => (bool) $organisation->sendgrid_webhook_key,
            'live_enabled' => (bool) config('dispatch.sendgrid.live_enabled'),
        ];

        return Inertia::render('identities', [
            'company' => $organisation,
            'identities' => SendingIdentity::where('organisation_id', $organisation->id)->whereNull('archived_at')->with('dmarcReviewedBy:id,name')->get(),
            'archivedIdentities' => SendingIdentity::where('organisation_id', $organisation->id)->whereNotNull('archived_at')->with('dmarcReviewedBy:id,name')->latest('archived_at')->get(),
            'webhookUrl' => rtrim(config('app.url'), '/').'/webhooks/sendgrid/'.$organisation->id,
            'canManage' => $canManage,
            'readiness' => $readiness,
            'canVerify' => $canManage || Gate::allows('platform'),
        ]);
    }

    public function identitySave(Request $r, Organisation $organisation, ?SendingIdentity $identity = null)
    {
        Gate::authorize('update', $organisation);
        if ($identity) {
            abort_unless($identity->organisation_id === $organisation->id, 404);
            abort_if($identity->archived_at, 404);
        }$r->merge(['email' => strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email:rfc', 'max:454', Rule::unique('sending_identities')->where('organisation_id', $organisation->id)->ignore($identity?->id)], 'reply_to' => 'nullable|email:rfc|max:454', 'is_default' => 'required|boolean']);
        DB::transaction(function () use ($organisation, $identity, $d) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            if ($d['is_default']) {
                SendingIdentity::where('organisation_id', $organisation->id)->update(['is_default' => false]);
            }$d['domain'] = substr(strrchr($d['email'], '@'), 1);
            $d['verification_status'] = 'unverified';
            if ($identity) {
                if (strtolower($identity->domain) !== strtolower($d['domain'])) {
                    $d += [
                        'provider_authorised' => false,
                        'provider_domain_id' => null,
                        'verified_at' => null,
                        'spf_status' => 'not_checked',
                        'dkim_status' => 'not_checked',
                        'dmarc_status' => 'not_checked',
                        'dmarc_reviewed_at' => null,
                        'dmarc_reviewed_by_user_id' => null,
                    ];
                }
                $identity->update($d);
            } else {
                SendingIdentity::create(['organisation_id' => $organisation->id, ...$d]);
            }AuditLog::record('identity.saved_unverified', $organisation->id);
        });

        return back()->with('success', 'Identity saved for simulation only. No DNS verification was performed.');
    }

    public function identityArchive(Organisation $organisation, SendingIdentity $identity)
    {
        Gate::authorize('update', $organisation);
        abort_unless($identity->organisation_id === $organisation->id, 404);

        DB::transaction(function () use ($organisation, $identity) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $current = SendingIdentity::where('organisation_id', $organisation->id)->whereKey($identity->id)->lockForUpdate()->firstOrFail();
            if ($current->archived_at) {
                return;
            }

            $active = SendingIdentity::where('organisation_id', $organisation->id)->whereNull('archived_at');
            if ($active->count() <= 1) {
                throw ValidationException::withMessages(['identity' => 'Keep at least one active sender identity in this workspace.']);
            }

            $needsDefault = $current->is_default || ! (clone $active)->where('is_default', true)->exists();
            if ($needsDefault) {
                $replacement = (clone $active)->whereKeyNot($current->id)->orderBy('id')->lockForUpdate()->firstOrFail();
                $replacement->update(['is_default' => true]);
            }

            $current->update(['archived_at' => now(), 'is_default' => false]);
            AuditLog::record('identity.archived', $organisation->id, ['identity_id' => $current->id, 'email' => $current->email]);
        });

        return back()->with('success', 'Sender identity archived. It is no longer available for new campaigns; existing campaign history is preserved.');
    }

    public function identityRestore(Organisation $organisation, SendingIdentity $identity)
    {
        Gate::authorize('update', $organisation);
        abort_unless($identity->organisation_id === $organisation->id, 404);

        DB::transaction(function () use ($organisation, $identity) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            $current = SendingIdentity::where('organisation_id', $organisation->id)->whereKey($identity->id)->lockForUpdate()->firstOrFail();
            if (! $current->archived_at) {
                return;
            }

            $makeDefault = ! SendingIdentity::where('organisation_id', $organisation->id)->whereNull('archived_at')->where('is_default', true)->exists();
            $current->update(['archived_at' => null, 'is_default' => $makeDefault]);
            AuditLog::record('identity.restored', $organisation->id, ['identity_id' => $current->id, 'email' => $current->email]);
        });

        return back()->with('success', 'Sender identity restored. Check its verification status before using it in a live campaign.');
    }

    public function templates(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);
        $gallery = $this->galleryTemplates($organisation);

        return Inertia::render('templates', ['company' => $organisation, 'templates' => EmailTemplate::where('organisation_id', $organisation->id)->latest()->get(['id', 'name', 'category', 'subject', 'preview_text', 'text_body']), 'basic' => app(EmailStarterTemplates::class)->basic($organisation->email_branding ?? []), 'starters' => $gallery['items'], 'customGallery' => $gallery['custom'], 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function basic(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $s = app(EmailStarterTemplates::class)->basic($organisation->email_branding ?? []);
        $s['html'] = app(EmailContentService::class)->clean($s['html']);
        $t = EmailTemplate::create(['organisation_id' => $organisation->id, ...$s]);
        AuditLog::record('template.created', $organisation->id, ['template_id' => $t->id, 'design' => 'basic']);

        return redirect("/organisations/$organisation->id/templates/$t->id");
    }

    public function plainText(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $r->validate(['html' => 'required|string|max:400000']);

        return response()->json(['text' => app(EmailContentService::class)->text($d['html'])])->header('Cache-Control', 'private, no-store');
    }

    public function starterPreview(Organisation $organisation, int $starter)
    {
        Gate::authorize('view', $organisation);
        $gallery = $this->galleryTemplates($organisation)['items'];
        abort_unless(isset($gallery[$starter]), 404);
        $s = $gallery[$starter];

        return response(app(EmailContentService::class)->render($s['html'], ['first_name' => 'Alex', 'last_name' => 'Example', 'email' => 'preview@example.test', 'sender_name' => $organisation->name, 'sender_address' => $organisation->postal_address ?: '10 Example Street, London · demonstration address', 'unsubscribe_url' => '#preview-only'], $s['preview_text']))->header('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox")->header('Referrer-Policy', 'no-referrer');
    }

    public function branding(Request $r, Organisation $organisation)
    {
        Gate::authorize('update', $organisation);
        $d = $r->validate(['logo_url' => 'nullable|url:https|max:2000', 'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'button_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'footer_text' => 'nullable|string|max:1000', 'signature_text' => 'nullable|string|max:2000']);
        if (($d['logo_url'] ?? '') && ! app(EmailMarkup::class)->safeUrl($d['logo_url'], false)) {
            throw ValidationException::withMessages(['logo_url' => 'Use a public HTTPS URL without credentials.']);
        }
        $organisation->forceFill(['email_branding' => $d])->save();
        AuditLog::record('email_branding.updated', $organisation->id);

        return back()->with('success', 'Email branding saved. Existing templates and campaign snapshots are unchanged.');
    }

    public function starter(Request $r, Organisation $organisation)
    {
        Gate::authorize('manageCampaigns', $organisation);
        $d = $r->validate(['starter' => 'required|integer|min:0']);
        $gallery = $this->galleryTemplates($organisation);
        abort_unless(isset($gallery['items'][$d['starter']]), 404);
        $s = $gallery['items'][$d['starter']];
        $catalogItemId = $s['catalog_item_id'] ?? null;
        unset($s['description']);
        unset($s['catalog_item_id']);
        $s['html'] = app(EmailContentService::class)->clean($s['html']);
        $t = EmailTemplate::create(['organisation_id' => $organisation->id, ...$s, 'text_body' => app(EmailContentService::class)->text($s['html'])]);
        AuditLog::record('template.created', $organisation->id, ['template_id' => $t->id, 'catalog_item_id' => $catalogItemId]);

        return redirect("/organisations/$organisation->id/templates/$t->id");
    }

    public function templateShow(Organisation $organisation, EmailTemplate $template)
    {
        Gate::authorize('view', $organisation);
        abort_unless($template->organisation_id === $organisation->id, 404);

        return Inertia::render('template-editor', ['company' => $organisation, 'template' => $template, 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function templateSave(Request $r, Organisation $organisation, ?EmailTemplate $template = null)
    {
        Gate::authorize('manageCampaigns', $organisation);
        if ($template) {
            abort_unless($template->organisation_id === $organisation->id, 404);
        }$d = $r->validate(['name' => 'required|string|max:120', 'category' => 'required|string|max:120', 'subject' => 'required|string|max:455', 'preview_text' => 'nullable|string|max:455', 'html' => 'required|string|max:400000', 'text_body' => 'required|string|max:100000']);
        $d['html'] = app(EmailContentService::class)->clean($d['html']);
        if ($template) {
            $template->update($d);
        } else {
            $template = EmailTemplate::create(['organisation_id' => $organisation->id, ...$d]);
        }AuditLog::record('template.saved', $organisation->id, ['template_id' => $template->id]);

        return redirect("/organisations/$organisation->id/templates/$template->id")->with('success', 'Template saved and sanitised.');
    }

    public function duplicate(Organisation $organisation, EmailTemplate $template)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($template->organisation_id === $organisation->id, 404);
        $copy = $template->replicate();
        $copy->name .= ' (copy)';
        $copy->save();
        AuditLog::record('template.duplicated', $organisation->id, ['template_id' => $copy->id]);

        return redirect("/organisations/$organisation->id/templates/$copy->id");
    }

    public function templateDelete(Organisation $organisation, EmailTemplate $template)
    {
        Gate::authorize('manageCampaigns', $organisation);
        abort_unless($template->organisation_id === $organisation->id, 404);

        $templateId = $template->id;
        $templateName = $template->name;
        DB::transaction(function () use ($organisation, $template, $templateId, $templateName) {
            $template->delete();
            AuditLog::record('template.deleted', $organisation->id, ['template_id' => $templateId, 'name' => $templateName]);
        });

        return redirect("/organisations/$organisation->id/templates")->with('success', 'Template deleted. Campaign content is unchanged.');
    }

    public function preview(Organisation $organisation, EmailTemplate $template)
    {
        Gate::authorize('view', $organisation);
        abort_unless($template->organisation_id === $organisation->id, 404);

        return response(app(EmailContentService::class)->render($template->html, ['first_name' => 'Alex', 'last_name' => 'Example', 'email' => 'preview@example.test', 'sender_name' => $organisation->name, 'sender_address' => $organisation->postal_address ?? 'Sender address required', 'unsubscribe_url' => '#preview-only'], $template->preview_text ?? ''))->header('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox")->header('Referrer-Policy', 'no-referrer');
    }

    /** @return array{items: array<int, array<string, mixed>>, custom: bool} */
    private function galleryTemplates(Organisation $organisation): array
    {
        $catalog = EmailTemplateCatalogItem::where('organisation_id', $organisation->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($catalog->isNotEmpty()) {
            return [
                'items' => $catalog->values()->map(fn (EmailTemplateCatalogItem $item) => [
                    'catalog_item_id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category,
                    'subject' => $item->subject,
                    'preview_text' => $item->preview_text,
                    'description' => $item->description,
                    'html' => $item->html,
                    'text_body' => $item->text_body,
                ])->all(),
                'custom' => true,
            ];
        }

        return ['items' => app(EmailContentService::class)->starters($organisation->email_branding ?? []), 'custom' => false];
    }
}
