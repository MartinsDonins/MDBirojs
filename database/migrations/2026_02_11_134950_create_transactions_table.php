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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            
            $table->string('external_id')->index()->nullable();
            $table->string('fingerprint')->unique()->comment('Hash of core fields for deduplication');
            
            $table->date('occurred_at');
            $table->date('booked_at')->nullable();
            
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount_eur', 15, 2);
            $table->decimal('exchange_rate', 15, 6)->nullable();
            
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account')->nullable();
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            
            $table->string('type')->index(); // INCOME, EXPENSE, TRANSFER, FEE, etc.
            $table->string('status')->default('DRAFT'); // DRAFT, COMPLETED, NEEDS_REVIEW
            $table->string('tax_treatment')->nullable();
            
            $table->json('review_flags')->nullable();
            $table->json('raw_payload')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
