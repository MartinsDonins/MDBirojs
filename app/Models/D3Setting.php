<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-year manual inputs for the VID D3 annex. See the migration for the
 * row-by-row mapping. Journal-derived rows (4, 5, 6) are NOT stored here.
 */
class D3Setting extends Model
{
    protected $fillable = [
        'year',
        'farm_income_agriculture',
        'farm_income_fishery',
        'farm_income_tourism',
        'farm_income_support',
        'farm_expenses',
        'farm_prior_losses',
        'other_prior_losses',
        'foreign_tax',
        'min_taxable_income',
    ];

    protected $casts = [
        'year'                    => 'integer',
        'farm_income_agriculture' => 'decimal:2',
        'farm_income_fishery'     => 'decimal:2',
        'farm_income_tourism'     => 'decimal:2',
        'farm_income_support'     => 'decimal:2',
        'farm_expenses'           => 'decimal:2',
        'farm_prior_losses'       => 'decimal:2',
        'other_prior_losses'      => 'decimal:2',
        'foreign_tax'             => 'decimal:2',
        'min_taxable_income'      => 'decimal:2',
    ];
}
