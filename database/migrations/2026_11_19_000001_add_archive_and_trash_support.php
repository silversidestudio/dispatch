<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('mailing_lists', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->softDeletes();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('archived_at');
        });

        Schema::table('mailing_lists', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('archived_at');
        });

        Schema::table('contacts', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
