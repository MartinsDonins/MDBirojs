<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();              // Pielāgots nosaukums (piem. "Ģimenes auto")
            $table->string('make')->nullable();              // Marka
            $table->string('model')->nullable();             // Modelis
            $table->integer('year')->nullable();             // Izlaiduma gads
            $table->string('reg_number')->nullable();        // Reģistrācijas numurs
            $table->string('vin')->nullable();               // VIN
            $table->string('color')->nullable();             // Krāsa

            // Degviela / gāze
            $table->string('primary_fuel')->default('petrol');   // petrol/diesel/lpg/hybrid/electric
            $table->boolean('has_lpg')->default(false);          // Aprīkots ar gāzes iekārtu
            $table->decimal('tank_capacity', 6, 1)->nullable();  // Degvielas tvertnes tilpums (L)
            $table->decimal('lpg_capacity', 6, 1)->nullable();   // Gāzes balona tilpums (L)

            // Nobraukums
            $table->integer('initial_odometer')->default(0);     // Sākuma odometrs (km)

            // Derīguma termiņi (atgādinājumi)
            $table->date('insurance_expires_at')->nullable();    // OCTA derīgs līdz
            $table->date('casco_expires_at')->nullable();        // KASKO derīgs līdz
            $table->date('inspection_expires_at')->nullable();   // Tehniskā apskate derīga līdz

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
