<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'account_id',
        'number',
        'type',
        'amount',
        'currency',
        'date',
        'basis',
        'person',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Generate next cash order number
     */
    public static function generateNumber(string $type, int $year = null): string
    {
        $year = $year ?? now()->year;
        $prefix = $type === 'INCOME' ? 'KII' : 'KIO';
        
        $lastNumber = static::where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        if ($lastNumber) {
            $parts = explode('-', $lastNumber);
            $nextNumber = (int)$parts[2] + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $nextNumber);
    }
}
