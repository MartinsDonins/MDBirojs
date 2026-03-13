<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_orders', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('transaction_id')
                    ->constrained('accounts')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_orders', function (Blueprint $table) {
            if (Schema::hasColumn('cash_orders', 'account_id')) {
                $table->dropForeignIdFor(\App\Models\Account::class);
                $table->dropColumn('account_id');
            }
        });
    }
};
