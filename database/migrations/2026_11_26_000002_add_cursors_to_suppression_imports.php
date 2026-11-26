<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppression_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('cursor_offset')->default(0)->after('failure');
            $table->unsignedInteger('cursor_row')->default(1)->after('cursor_offset');
        });
    }

    public function down(): void
    {
        Schema::table('suppression_imports', function (Blueprint $table) {
            $table->dropColumn(['cursor_offset', 'cursor_row']);
        });
    }
};
