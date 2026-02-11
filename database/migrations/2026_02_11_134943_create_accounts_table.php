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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // BANK, PAYPAL, CASH
            $table->string('currency', 3)->default('EUR');
            $table->string('account_number')->nullable(); // IBAN or Email
            $table->string('bank_name')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status')->default('ACTIVE'); // ACTIVE, CLOSED
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
