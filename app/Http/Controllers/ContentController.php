<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmailTemplate;
use App\Models\Organisation;
use App\Models\SendingIdentity;
use App\Services\EmailContentService;
use App\Services\EmailMarkup;
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

        return Inertia::render('identities', ['company' => $organisation, 'identities' => SendingIdentity::where('organisation_id', $organisation->id)->get(), 'canManage' => Gate::allows('update', $organisation), 'readiness' => ['provider' => config('dispatch.provider'), 'enabled' => (bool) config('dispatch.sendgrid.enabled'), 'configured' => (bool) config('dispatch.sendgrid.api_key'), 'live_enabled' => (bool) config('dispatch.sendgrid.live_enabled')], 'canVerify' => Gate::allows('platform')]);
    }

    public function identitySave(Request $r, Organisation $organisation, ?SendingIdentity $identity = null)
    {
        Gate::authorize('update', $organisation);
        if ($identity) {
            abort_unless($identity->organisation_id === $organisation->id, 404);
        }$r->merge(['email' => strtolower(trim($r->input('email', '')))]);
        $d = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email:rfc', 'max:454', Rule::unique('sending_identities')->where('organisation_id', $organisation->id)->ignore($identity?->id)], 'reply_to' => 'nullable|email:rfc|max:454', 'is_default' => 'required|boolean']);
        DB::transaction(function () use ($organisation, $identity, $d) {
            Organisation::whereKey($organisation->id)->lockForUpdate()->firstOrFail();
            if ($d['is_default']) {
                SendingIdentity::where('organisation_id', $organisation->id)->update(['is_default' => false]);
            }$d['domain'] = substr(strrchr($d['email'], '@'), 1);
            $d['verification_status'] = 'unverified';
            if ($identity) {
                $identity->update($d);
            } else {
                SendingIdentity::create(['organisation_id' => $organisation->id, ...$d]);
            }AuditLog::record('identity.saved_unverified', $organisation->id);
        });

        return back()->with('success', 'Identity saved for simulation only. No DNS verification was performed.');
    }

    public function templates(Organisation $organisation)
    {
        Gate::authorize('view', $organisation);

        return Inertia::render('templates', ['company' => $organisation, 'templates' => EmailTemplate::where('organisation_id', $organisation->id)->latest()->get(['id', 'name', 'category', 'subject', 'preview_text', 'text_body']), 'starters' => app(EmailContentService::class)->starters($organisation->email_branding ?? []), 'canManage' => Gate::allows('manageCampaigns', $organisation)]);
    }

    public function starterPreview(Organisation $organisation, int $starter)
    {
        Gate::authorize('view', $organisation);
        abort_unless($starter >= 0 && $starter < 5, 404);
        $s = app(EmailContentService::class)->starters($organisation->email_branding ?? [])[$starter];

        return response(app(EmailContentService::class)->render($s['html'], ['first_name' => 'Alex', 'last_name' => 'Example', 'email' => 'preview@example.test', 'sender_name' => $organisation->name, 'sender_address' => $organisation->postal_address ?: '10 Example Street, London · demonstration address', 'unsubscribe_url' => '#preview-only'], $s['preview_text']))->header('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox")->header('Referrer-Policy', 'no-referrer');
    }

    public function branding(Request $r, Organisation $organisation)
    {
        Gate::authorize('update', $organisation);
        $d = $r->validate(['logo_url' => 'nullable|url:https|max:2000', 'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'button_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'footer_text' => 'nullable|string|max:1000']);
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
        $d = $r->validate(['starter' => 'required|integer|min:0|max:4']);
        $s = app(EmailContentService::class)->starters($organisation->email_branding ?? [])[$d['starter']];
        unset($s['description']);
        $s['html'] = app(EmailContentService::class)->clean($s['html']);
        $t = EmailTemplate::create(['organisation_id' => $organisation->id, ...$s, 'text_body' => app(EmailContentService::class)->text($s['html'])]);
        AuditLog::record('template.created', $organisation->id, ['template_id' => $t->id]);

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

    public function preview(Organisation $organisation, EmailTemplate $template)
    {
        Gate::authorize('view', $organisation);
        abort_unless($template->organisation_id === $organisation->id, 404);

        return response(app(EmailContentService::class)->render($template->html, ['first_name' => 'Alex', 'last_name' => 'Example', 'email' => 'preview@example.test', 'sender_name' => $organisation->name, 'sender_address' => $organisation->postal_address ?? 'Sender address required', 'unsubscribe_url' => '#preview-only'], $template->preview_text ?? ''))->header('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox")->header('Referrer-Policy', 'no-referrer');
    }
}
