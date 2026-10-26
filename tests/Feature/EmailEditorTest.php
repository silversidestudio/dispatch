<?php

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\EmailContentService;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('renders five versioned original starters with responsive structure plain text and required footer', function () {
    $service = app(EmailContentService::class);
    $starters = $service->starters();
    expect($starters)->toHaveCount(5);
    foreach ($starters as $starter) {
        expect($starter['name'])->toContain('Studio v2');
        expect($starter['text_body'])->toContain('{{sender_address}}', '{{unsubscribe_url}}');
        $html = $service->render($starter['html'], ['first_name' => 'Alex', 'sender_name' => 'Acorn', 'sender_address' => '10 Test Road', 'unsubscribe_url' => 'https://example.test/unsubscribe/signed'], $starter['preview_text']);
        expect($html)->toContain('max-width:600px', '@media only screen', 'x-apple-disable-message-reformatting', '10 Test Road', 'clicktracking="off"')->not->toContain('{{sender_');
    }
});
it('preserves email CTA output and safely constrains linked images across sanitisation', function () {
    $s = app(EmailContentService::class);
    $html = '<table data-email-button="true" data-background="#21564b" data-color="#ffffff" data-align="center" data-radius="8"><tbody><tr><td align="center"><a href="https://example.test/details" style="display:inline-block;background-color:#21564b;color:#ffffff;border-radius:8px;padding:14px 24px;text-decoration:none;">Read the details</a></td></tr></tbody></table><div data-email-image="true" data-align="center"><a href="https://example.test/story"><img src="https://example.test/hero.png" alt="A useful description" width="900" style="width:900px;"></a></div>';
    $clean = $s->clean($html);
    expect($clean)->toContain('data-email-button="true"', 'border-radius:8px', 'display:inline-block', 'text-decoration:none', 'padding:14px 24px', 'A useful description', 'max-width:100%', 'height:auto');
    expect($s->clean($clean))->toBe($clean);
    expect($s->text($html))->toContain('Read the details (https://example.test/details)');
    config(['app.url' => 'http://127.0.0.1:8012']);
    expect($s->render('<img src="/email-assets/studio-hero.png" alt="Studio artwork">', []))->toContain('src="/email-assets/studio-hero.png"');
    config(['app.url' => 'https://dispatch.example.test']);
    expect($s->render('<img src="/email-assets/studio-hero.png" alt="Studio artwork">', []))->toContain('src="https://dispatch.example.test/email-assets/studio-hero.png"');
});
it('strips malicious HTML URLs and CSS while retaining legacy table text and unsubscribe tokens', function () {
    $s = app(EmailContentService::class);
    $html = '<script>alert(1)</script><form><input></form><iframe></iframe><img src="http://insecure.test/image.png" onerror="alert(1)"><a href="javascript:alert(1)" style="position:fixed;z-index:999;background-image:url(https://tracker.test);">Bad</a><table width="600"><tr><td>Original legacy content</td></tr></table><a href="{{unsubscribe_url}}">Unsubscribe</a>';
    $clean = $s->clean($html);
    expect($clean)->not->toContain('<script', '<form', '<input', '<iframe', 'onerror', 'javascript:', 'position:', 'z-index:', 'background-image:', 'http://insecure');
    expect($clean)->toContain('<table', 'Original legacy content', '{{unsubscribe_url}}');
    $rendered = $s->render($clean.'<p>Hello {{first_name}}</p>', ['first_name' => '<script>unsafe</script>', 'unsubscribe_url' => 'javascript:alert(1)']);
    expect($rendered)->not->toContain('<script>', 'href="javascript:')->toContain('&lt;script&gt;unsafe&lt;/script&gt;');
    expect($s->personaliseText('Hello {{first_name}} {{last_name}}', ['first_name' => null]))->toBe('Hello there ');
});
it('applies branding only to new designs and protects tenant editing duplication and preview', function () {
    $u = User::factory()->create();
    $o = workspaceFor($u);
    $base = "/organisations/$o->id";
    $this->actingAs($u)->post($base.'/templates/starter', ['starter' => 0])->assertRedirect();
    $old = EmailTemplate::first();
    $oldHtml = $old->html;
    $branding = ['primary_color' => '#294766', 'secondary_color' => '#eef2f6', 'button_color' => '#234567', 'logo_url' => 'https://example.test/logo.png', 'footer_text' => 'A thoughtful message from our team.'];
    $this->patch($base.'/email-branding', $branding)->assertRedirect();
    expect($old->fresh()->html)->toBe($oldHtml);
    $this->post($base.'/templates/starter', ['starter' => 0])->assertRedirect();
    $new = EmailTemplate::latest('id')->first();
    expect($new->html)->toContain('#234567', 'https://example.test/logo.png', 'A thoughtful message');
    $this->post($base.'/templates/'.$new->id.'/duplicate')->assertRedirect();
    expect(EmailTemplate::latest('id')->first()->html)->toBe($new->html);
    $this->get($base.'/starter-previews/2')->assertOk()->assertHeader('Content-Security-Policy', "default-src 'none'; img-src 'self' https:; style-src 'unsafe-inline'; sandbox");
    $this->get($base.'/templates/'.$new->id.'/preview')->assertOk();
    $this->get($base.'/templates')->assertInertia(fn (Assert $p) => $p->has('starters', 5)->has('templates', 3));
    $other = workspaceFor($u);
    $this->get("/organisations/$other->id/templates/$new->id/preview")->assertNotFound();
    $viewer = User::factory()->create();
    $o->members()->attach($viewer->id, ['role' => 'viewer']);
    $this->actingAs($viewer)->patch($base.'/email-branding', $branding)->assertForbidden();
    $this->post($base.'/templates/'.$new->id.'/duplicate')->assertForbidden();
    $this->patch($base.'/templates/'.$new->id, ['html' => 'changed'])->assertForbidden();
});
it('keeps existing campaign snapshots immutable when branding and new templates change', function () {
    Queue::fake();
    [$u, $o, $campaign] = preparedCampaign();
    $service = app(CampaignService::class);
    $service->ready($campaign);
    $service->schedule($campaign->fresh(), now());
    $before = $campaign->fresh()->snapshot;
    $this->actingAs($u)->patch("/organisations/$o->id/email-branding", ['primary_color' => '#294766', 'button_color' => '#234567'])->assertRedirect();
    $this->post("/organisations/$o->id/templates/starter", ['starter' => 2])->assertRedirect();
    expect($campaign->fresh()->snapshot)->toBe($before);
    $this->get("/organisations/$o->id/campaigns/$campaign->id/preview")->assertOk();
    expect($campaign->fresh()->snapshot)->toBe($before);
});
