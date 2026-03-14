<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitLossSetting extends Model
{
    protected $fillable = ['year', 'tax_rate'];

    protected $casts = [
        'year'     => 'integer',
        'tax_rate' => 'decimal:2',
    ];
}
