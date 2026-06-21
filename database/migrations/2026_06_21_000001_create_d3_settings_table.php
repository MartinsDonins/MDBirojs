<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-year manual inputs for the VID "D3" annex (Ienākumi no saimnieciskās darbības).
 *
 * Only the rows that CANNOT be derived from the income/expense journal live here:
 * farming/rural-tourism income & expenses (rows 1.1–1.4, 2, 3), prior-year losses
 * (rows 3 & 7), foreign tax paid (row 9) and the minimum taxable income (row 10).
 *
 * The taxable business income (row 5), deductible expenses (row 6) and non-taxable
 * income (row 4) are read live from the journal and are NOT stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('d3_settings', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->unique();

            // Row 1.x — farming / rural tourism income
            $table->decimal('farm_income_agriculture', 12, 2)->default(0); // 1.1
            $table->decimal('farm_income_fishery', 12, 2)->default(0);     // 1.2
            $table->decimal('farm_income_tourism', 12, 2)->default(0);     // 1.3
            $table->decimal('farm_income_support', 12, 2)->default(0);     // 1.4

            // Row 2 / 3 — farming expenses & prior-year farming losses
            $table->decimal('farm_expenses', 12, 2)->default(0);           // 2
            $table->decimal('farm_prior_losses', 12, 2)->default(0);       // 3

            // Row 7 — prior-year losses from other activities
            $table->decimal('other_prior_losses', 12, 2)->default(0);      // 7

            // Row 9 / 10 — foreign tax paid & minimum taxable income
            $table->decimal('foreign_tax', 12, 2)->default(0);             // 9
            $table->decimal('min_taxable_income', 12, 2)->default(0);      // 10

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('d3_settings');
    }
};
