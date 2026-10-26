<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $t) {
            $t->boolean('open_tracking_enabled')->default(false);
            $t->boolean('click_tracking_enabled')->default(false);
            $t->timestamp('tracking_reviewed_at')->nullable();
            $t->text('tracking_review_evidence')->nullable();
        });
        Schema::table('sending_identities', function (Blueprint $t) {
            $t->unsignedBigInteger('provider_domain_id')->nullable();
            $t->boolean('provider_authorised')->default(false);
            $t->timestamp('verified_at')->nullable();
        });
        Schema::table('campaigns', function (Blueprint $t) {
            $t->string('delivery_mode')->default('simulation');
            $t->boolean('open_tracking')->default(false);
            $t->boolean('click_tracking')->default(false);
            $t->timestamp('live_authorised_at')->nullable();
            $t->string('paused_from')->nullable();
        });
        Schema::table('campaign_recipients', function (Blueprint $t) {
            $t->string('delivery_status')->default('queued')->index();
            $t->timestamp('last_provider_event_at')->nullable();
        });
        Schema::table('delivery_attempts', function (Blueprint $t) {
            $t->string('provider')->default('simulation')->index();
            $t->uuid('correlation_id')->nullable()->unique();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('diagnostic_code')->nullable();
            $t->index(['outcome', 'started_at']);
        });
        Schema::table('delivery_events', function (Blueprint $t) {
            $t->string('provider')->default('simulation');
            $t->foreignId('delivery_attempt_id')->nullable()->constrained();
            $t->timestamp('occurred_at')->nullable();
            $t->string('provider_message_id')->nullable();
            $t->text('url')->nullable();
            $t->index(['campaign_id', 'provider', 'type', 'occurred_at'], 'campaign_provider_events');
            $t->index(['campaign_recipient_id', 'type', 'occurred_at'], 'recipient_event_timeline');
        });
        Schema::create('webhook_batches', function (Blueprint $t) {
            $t->id();
            $t->string('digest', 64)->unique();
            $t->longText('events');
            $t->string('status')->default('queued')->index();
            $t->timestamps();
        });
        Schema::create('webhook_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('event_key', 64)->unique();
            $t->foreignId('delivery_attempt_id')->nullable()->constrained();
            $t->string('status');
            $t->string('reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('webhook_batches');
        Schema::table('delivery_events', function (Blueprint $t) {
            $t->dropForeign(['delivery_attempt_id']);
            $t->dropIndex('campaign_provider_events');
            $t->dropIndex('recipient_event_timeline');
            $t->dropColumn(['provider', 'delivery_attempt_id', 'occurred_at', 'provider_message_id', 'url']);
        });
        Schema::table('delivery_attempts', fn (Blueprint $t) => $t->dropColumn(['provider', 'correlation_id', 'started_at', 'finished_at', 'http_status', 'diagnostic_code']));
        Schema::table('campaign_recipients', fn (Blueprint $t) => $t->dropColumn(['delivery_status', 'last_provider_event_at']));
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['delivery_mode', 'open_tracking', 'click_tracking', 'live_authorised_at', 'paused_from']));
        Schema::table('sending_identities', fn (Blueprint $t) => $t->dropColumn(['provider_domain_id', 'provider_authorised', 'verified_at']));
        Schema::table('organisations', fn (Blueprint $t) => $t->dropColumn(['open_tracking_enabled', 'click_tracking_enabled', 'tracking_reviewed_at', 'tracking_review_evidence']));
    }
};
