<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Exchange rate used when setting opening balance in a foreign currency
            // (1 EUR = X units of accounts.currency). NULL means EUR/rate=1.
            $table->decimal('balance_exchange_rate', 15, 6)->nullable()->after('balance');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Manual sort order within the same occurred_at date.
            // NULL = fall back to id-based ordering (natural import order).
            $table->unsignedInteger('sort_order')->nullable()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('balance_exchange_rate');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
