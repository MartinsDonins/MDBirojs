<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('filled_at');                         // Uzpildes datums
            $table->integer('odometer');                       // Odometrs uzpildes brīdī (km)
            $table->string('fuel_type')->default('petrol');    // petrol/diesel/lpg
            $table->decimal('liters', 8, 2);                   // Litri
            $table->decimal('price_per_liter', 8, 3)->nullable(); // Cena par litru (€)
            $table->decimal('total_cost', 10, 2);              // Kopā (€)
            $table->boolean('full_tank')->default(true);       // Pilna tvertne (patēriņa aprēķinam)
            $table->string('station')->nullable();             // DUS / vieta
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'fuel_type', 'odometer']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_logs');
    }
};
