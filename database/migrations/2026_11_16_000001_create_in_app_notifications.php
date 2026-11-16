<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->string('event_key', 190);
                $table->json('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['notifiable_type', 'notifiable_id', 'event_key']);
            });
        }

        if (! Schema::hasIndex('notifications', 'notifications_inbox_lookup_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(
                    ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                    'notifications_inbox_lookup_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
