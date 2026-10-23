<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('status')->default('active')->index());
        Schema::table('organisations', function (Blueprint $t) {
            $t->string('website')->nullable();
            $t->text('postal_address')->nullable();
        });
        Schema::table('organisation_invitations', fn (Blueprint $t) => $t->enum('role', ['owner', 'administrator', 'campaign_manager', 'viewer'])->change());
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $t) => $t->dropColumn(['website', 'postal_address']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('status'));
    }
};
