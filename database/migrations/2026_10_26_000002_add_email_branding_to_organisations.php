<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', fn (Blueprint $t) => $t->json('email_branding')->nullable());
    }

    public function down(): void
    {
        Schema::table('organisations', fn (Blueprint $t) => $t->dropColumn('email_branding'));
    }
};
