<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Transaction $transaction) {
            if (empty($transaction->fingerprint)) {
                $transaction->fingerprint = 'manual-' . \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $casts = [
        'occurred_at' => 'date',
        'booked_at' => 'date',
        'amount' => 'decimal:2',
        'amount_eur' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'review_flags' => 'array',
        'raw_payload' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function cashOrder(): HasOne
    {
        return $this->hasOne(CashOrder::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transaction_id');
    }

    public function appliedRule(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Rule::class, 'applied_rule_id');
    }
}
