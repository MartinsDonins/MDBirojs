<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $category = DB::table('categories')->where('name', 'Skaidra nauda')->first();
        if (!$category) {
            return;
        }

        // Null out category_id on all transactions that reference this category
        DB::table('transactions')
            ->where('category_id', $category->id)
            ->update(['category_id' => null, 'status' => 'DRAFT']);

        // Now safe to delete
        DB::table('categories')->where('id', $category->id)->delete();
    }

    public function down(): void
    {
        // Not reversible
    }
};
