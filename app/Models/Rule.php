<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'criteria' => 'array',
        'action' => 'array',
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(\App\Models\Transaction::class, 'applied_rule_id');
    }
}
