<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('performed_at');                      // Datums
            $table->integer('odometer')->nullable();           // Odometrs (km)
            $table->string('type')->default('service');        // service/repair/inspection/tires/other
            $table->string('title');                           // Nosaukums
            $table->text('description')->nullable();           // Apraksts / paveiktie darbi
            $table->string('provider')->nullable();            // Serviss / izpildītājs

            // Budžets / apmaksa
            $table->decimal('total_cost', 10, 2)->default(0);  // Kopējā summa (€)
            $table->decimal('amount_paid', 10, 2)->default(0); // Samaksāts (€)
            // Atlikums = total_cost - amount_paid (aprēķina modelī)

            $table->string('status')->default('completed');    // planned/in_progress/completed
            $table->json('attachments')->nullable();           // Foto / dokumentu pielikumi
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_id', 'type', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
