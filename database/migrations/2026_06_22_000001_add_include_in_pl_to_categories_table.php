<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Whether this category's transactions count toward the economic-activity (SD)
            // profit/loss calculation. Income categories add to SD income, expense
            // categories add to SD expenses; the transaction type decides the side.
            $table->boolean('include_in_pl')->default(false)->after('vid_column');
        });

        // Backfill: pre-tick the standard SD columns so existing mappings keep working.
        // Income SD = VID 4,5,6 ; Expense SD = VID 19-23.
        DB::table('categories')
            ->whereIn('vid_column', [4, 5, 6, 19, 20, 21, 22, 23])
            ->update(['include_in_pl' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('include_in_pl');
        });
    }
};
