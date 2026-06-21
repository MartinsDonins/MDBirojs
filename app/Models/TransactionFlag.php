<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A user-defined colored work flag (e.g. "Pārbaudīts", "Jāsalabo pretdarījums")
 * that can be attached to any number of transactions to help review a month.
 */
class TransactionFlag extends Model
{
    protected $fillable = ['name', 'color', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'flag_transaction');
    }
}
