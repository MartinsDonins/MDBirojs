<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_log_id')->constrained('maintenance_logs')->cascadeOnDelete();
            $table->string('title');                           // Darba / detaļas nosaukums
            $table->decimal('cost', 10, 2)->default(0);        // Pozīcijas izmaksas (€)
            $table->boolean('is_completed')->default(false);   // Paveikts
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_items');
    }
};
