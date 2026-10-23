<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('company_name')->nullable();
            $t->string('telephone')->nullable();
            $t->string('status')->default('active');
            $t->string('source')->nullable();
            $t->string('eligibility')->default('unknown');
            $t->json('attributes')->nullable();
            $t->timestamps();
            $t->unique(['organisation_id', 'email']);
            $t->index(['organisation_id', 'status', 'eligibility']);
        });
        Schema::create('eligibility_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('contact_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('basis');
            $t->text('evidence');
            $t->string('method');
            $t->string('policy_version')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('recorded_at');
            $t->timestamps();
        });
        Schema::create('suppressions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->string('email');
            $t->string('reason');
            $t->text('evidence')->nullable();
            $t->timestamps();
            $t->unique(['organisation_id', 'email']);
        });
        Schema::create('mailing_lists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->string('name');
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('contact_mailing_list', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_id')->constrained();
            $t->foreignId('mailing_list_id')->constrained();
            $t->timestamps();
            $t->unique(['contact_id', 'mailing_list_id']);
        });
        Schema::create('segments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->string('name');
            $t->json('rules');
            $t->timestamps();
        });
        Schema::create('contact_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('path');
            $t->json('headers');
            $t->json('mapping')->nullable();
            $t->string('status')->default('preview');
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('processed')->default(0);
            $t->unsignedInteger('successful')->default(0);
            $t->unsignedInteger('rejected')->default(0);
            $t->string('source')->default('CSV import');
            $t->text('failure')->nullable();
            $t->timestamps();
        });
        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contact_import_id')->constrained();
            $t->unsignedInteger('row_number');
            $t->string('status');
            $t->string('email')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
            $t->unique(['contact_import_id', 'row_number']);
        });
        Schema::create('sending_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->string('name');
            $t->string('email');
            $t->string('reply_to')->nullable();
            $t->string('domain');
            $t->string('verification_status')->default('unverified');
            $t->string('spf_status')->default('not_checked');
            $t->string('dkim_status')->default('not_checked');
            $t->string('dmarc_status')->default('not_checked');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->unique(['organisation_id', 'email']);
        });
        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->string('name');
            $t->string('category')->default('General');
            $t->string('subject');
            $t->string('preview_text')->nullable();
            $t->longText('html');
            $t->longText('text_body');
            $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name');
            $t->string('subject');
            $t->string('preview_text')->nullable();
            $t->foreignId('sending_identity_id')->constrained();
            $t->string('reply_to')->nullable();
            $t->string('audience_type');
            $t->unsignedBigInteger('audience_id');
            $t->longText('html');
            $t->longText('text_body');
            $t->string('status')->default('draft');
            $t->timestamp('scheduled_at')->nullable();
            $t->json('snapshot')->nullable();
            $t->unsignedInteger('audience_count')->default(0);
            $t->unsignedInteger('excluded_count')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'scheduled_at']);
            $t->index(['organisation_id', 'status']);
        });
        Schema::create('campaign_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('campaign_id')->constrained();
            $t->foreignId('contact_id')->constrained();
            $t->string('email');
            $t->json('personalisation');
            $t->string('status')->default('pending');
            $t->unsignedInteger('attempt_count')->default(0);
            $t->timestamp('next_attempt_at')->nullable();
            $t->string('provider_message_id')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'contact_id']);
            $t->index(['status', 'next_attempt_at']);
        });
        Schema::create('delivery_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('campaign_recipient_id')->constrained();
            $t->unsignedInteger('number');
            $t->string('outcome');
            $t->string('provider_message_id');
            $t->timestamps();
            $t->unique(['campaign_recipient_id', 'number']);
        });
        Schema::create('delivery_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained();
            $t->foreignId('campaign_id')->constrained();
            $t->foreignId('campaign_recipient_id')->constrained();
            $t->string('type');
            $t->boolean('simulated')->default(true);
            $t->string('event_key')->unique();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['organisation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['delivery_events', 'delivery_attempts', 'campaign_recipients', 'campaigns', 'email_templates', 'sending_identities', 'import_rows', 'contact_imports', 'segments', 'contact_mailing_list', 'mailing_lists', 'suppressions', 'eligibility_records', 'contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
