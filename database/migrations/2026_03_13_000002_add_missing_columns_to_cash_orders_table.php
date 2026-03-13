<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_orders', 'date')) {
                $table->date('date')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('cash_orders', 'basis')) {
                $table->text('basis')->nullable()->after('date');
            }

            if (!Schema::hasColumn('cash_orders', 'person')) {
                $table->string('person')->nullable()->after('basis');
            }

            if (!Schema::hasColumn('cash_orders', 'notes')) {
                $table->text('notes')->nullable()->after('person');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_orders', function (Blueprint $table) {
            foreach (['date', 'basis', 'person', 'notes'] as $col) {
                if (Schema::hasColumn('cash_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
