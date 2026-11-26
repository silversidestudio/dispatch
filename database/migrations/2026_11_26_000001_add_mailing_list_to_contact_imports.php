<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->foreignId('mailing_list_id')->nullable()->after('organisation_id')->constrained('mailing_lists')->nullOnDelete();
            $table->unsignedBigInteger('cursor_offset')->default(0)->after('mailing_list_id');
            $table->unsignedInteger('cursor_row')->default(1)->after('cursor_offset');
        });
    }

    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn(['cursor_offset', 'cursor_row']);
            $table->dropConstrainedForeignId('mailing_list_id');
        });
    }
};
