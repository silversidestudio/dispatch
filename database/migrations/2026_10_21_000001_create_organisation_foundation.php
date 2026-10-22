<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false);
        });
        Schema::create('organisations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status')->default('active')->index();
            $t->timestamps();
        });
        Schema::create('organisation_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['owner', 'administrator', 'campaign_manager', 'viewer']);
            $t->timestamps();
            $t->unique(['organisation_id', 'user_id']);
        });
        Schema::create('organisation_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->enum('role', ['administrator', 'campaign_manager', 'viewer']);
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['organisation_id', 'email']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['organisation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('organisation_invitations');
        Schema::dropIfExists('organisation_user');
        Schema::dropIfExists('organisations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};
