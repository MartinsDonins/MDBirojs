<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'performed_at',
        'odometer',
        'type',
        'title',
        'description',
        'provider',
        'total_cost',
        'amount_paid',
        'status',
        'attachments',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'total_cost'   => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'attachments'  => 'array',
    ];

    public const TYPES = [
        'service'    => 'Apkope',
        'repair'     => 'Remonts',
        'inspection' => 'Tehniskā apskate',
        'tires'      => 'Riepu maiņa',
        'other'      => 'Cits',
    ];

    public const STATUSES = [
        'planned'     => 'Plānots',
        'in_progress' => 'Procesā',
        'completed'   => 'Pabeigts',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceItem::class)->orderBy('sort_order');
    }

    /** Atlikums, kas vēl jāsamaksā (€). */
    public function getOutstandingAttribute(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->amount_paid);
    }

    /** Apmaksas statuss. */
    public function getPaymentStatusAttribute(): string
    {
        if ((float) $this->total_cost <= 0) {
            return 'paid';
        }
        if ((float) $this->amount_paid <= 0) {
            return 'unpaid';
        }

        return $this->outstanding > 0 ? 'partial' : 'paid';
    }

    public static function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid'    => 'Apmaksāts',
            'partial' => 'Daļēji',
            'unpaid'  => 'Nesamaksāts',
            default   => $status,
        };
    }

    public static function paymentStatusColor(string $status): string
    {
        return match ($status) {
            'paid'    => 'success',
            'partial' => 'warning',
            'unpaid'  => 'danger',
            default   => 'gray',
        };
    }
}
