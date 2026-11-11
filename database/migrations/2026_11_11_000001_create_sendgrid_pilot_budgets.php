<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sendgrid_pilot_budgets', function (Blueprint $t) {
            $t->id();
            $t->date('day')->unique();
            $t->unsignedInteger('reserved')->default(0);
            $t->timestamps();
        });
        Schema::create('sendgrid_pilot_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('budget_id')->constrained('sendgrid_pilot_budgets');
            $t->char('correlation_hash', 64)->unique();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sendgrid_pilot_reservations');
        Schema::dropIfExists('sendgrid_pilot_budgets');
    }
};
