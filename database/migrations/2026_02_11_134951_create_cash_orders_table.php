<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete(); // Kases konts
            $table->string('number')->unique(); // KIO-2024-001 or KII-2024-001
            $table->enum('type', ['INCOME', 'EXPENSE']); // Ienākums vai Izdevums
            $table->decimal('amount', 15, 2); // Summa
            $table->string('currency', 3)->default('EUR');
            $table->date('date'); // Orderu datums
            $table->text('basis')->nullable(); // Pamatojums
            $table->string('person')->nullable(); // Kam/No kā
            $table->text('notes')->nullable(); // Papildu piezīmes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_orders');
    }
};
