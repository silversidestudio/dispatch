<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_template_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('name', 120);
            $table->string('category', 120)->default('Client designs');
            $table->string('subject', 455);
            $table->string('preview_text', 455)->nullable();
            $table->string('description', 500);
            $table->longText('html');
            $table->longText('text_body');
            $table->timestamps();
            $table->unique(['organisation_id', 'slug', 'version']);
            $table->index(['organisation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_template_catalog_items');
    }
};
