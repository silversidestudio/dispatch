<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sending_identities', function (Blueprint $table) {
            $table->timestamp('dmarc_reviewed_at')->nullable()->after('dmarc_status');
            $table->foreignId('dmarc_reviewed_by_user_id')->nullable()->after('dmarc_reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sending_identities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dmarc_reviewed_by_user_id');
            $table->dropColumn('dmarc_reviewed_at');
        });
    }
};
