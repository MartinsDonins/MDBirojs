<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitLossSetting extends Model
{
    protected $fillable = ['year', 'tax_rate', 'min_wage', 'vsaa_full_rate', 'vsaa_reduced_rate'];

    protected $casts = [
        'year'             => 'integer',
        'tax_rate'         => 'decimal:2',
        'min_wage'         => 'decimal:2',
        'vsaa_full_rate'   => 'decimal:2',
        'vsaa_reduced_rate' => 'decimal:2',
    ];
}
