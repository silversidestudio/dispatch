<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->json('headers');
            $table->unsignedInteger('email_column')->nullable();
            $table->string('source')->default('Legacy global unsubscribe list');
            $table->text('evidence')->nullable();
            $table->string('status')->default('preview');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('existing')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->text('failure')->nullable();
            $table->timestamps();
        });

        Schema::create('suppression_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suppression_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status');
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['suppression_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_import_rows');
        Schema::dropIfExists('suppression_imports');
    }
};
