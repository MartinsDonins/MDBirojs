<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the built-in "Skaidra nauda / ATM" rule
        DB::table('rules')->where('name', '⚙ Skaidra nauda / ATM')->delete();

        // Remove the "Skaidra nauda" category only if no transactions reference it
        $category = DB::table('categories')->where('name', 'Skaidra nauda')->first();
        if ($category) {
            $inUse = DB::table('transactions')->where('category_id', $category->id)->exists();
            if (!$inUse) {
                DB::table('categories')->where('id', $category->id)->delete();
            }
            // If in use, leave it — user can re-assign manually via the journal
        }
    }

    public function down(): void
    {
        // Not reversible — intentional removal
    }
};
