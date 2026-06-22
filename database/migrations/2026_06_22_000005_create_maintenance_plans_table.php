<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('title');                           // Piem. "Eļļas maiņa", "Tehniskā apskate"
            $table->text('description')->nullable();

            // Intervāls — pēc km un/vai pēc laika (vismaz viens jāaizpilda)
            $table->integer('interval_km')->nullable();        // Ik pēc X km
            $table->integer('interval_months')->nullable();    // Ik pēc X mēnešiem

            // Pēdējoreiz veikts
            $table->integer('last_done_odometer')->nullable(); // km
            $table->date('last_done_at')->nullable();          // datums

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
