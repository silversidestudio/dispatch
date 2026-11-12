<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->text('sendgrid_api_key')->nullable();
            $table->text('sendgrid_webhook_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $table) => $table->dropColumn(['sendgrid_api_key', 'sendgrid_webhook_key']));
    }
};
