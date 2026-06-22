<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'filled_at',
        'odometer',
        'fuel_type',
        'liters',
        'price_per_liter',
        'total_cost',
        'full_tank',
        'station',
        'notes',
    ];

    protected $casts = [
        'filled_at'       => 'date',
        'liters'          => 'decimal:2',
        'price_per_liter' => 'decimal:3',
        'total_cost'      => 'decimal:2',
        'full_tank'       => 'boolean',
    ];

    public const FUEL_TYPES = [
        'petrol' => 'Benzīns',
        'diesel' => 'Dīzelis',
        'lpg'    => 'Gāze (LPG)',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Iepriekšējā tā paša veida uzpilde šim auto (zemāks odometrs). */
    public function previousLog(): ?FuelLog
    {
        return static::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->where('fuel_type', $this->fuel_type)
            ->where('odometer', '<', $this->odometer)
            ->where('id', '!=', $this->id)
            ->orderByDesc('odometer')
            ->first();
    }

    /** Nobraukums kopš iepriekšējās šī veida uzpildes (km). */
    public function getDistanceSincePreviousAttribute(): ?int
    {
        $prev = $this->previousLog();

        return $prev ? $this->odometer - $prev->odometer : null;
    }

    /** Patēriņš L/100km šajā posmā (ja šī un iepriekšējā ir pilnas tvertnes). */
    public function getConsumptionAttribute(): ?float
    {
        $distance = $this->distance_since_previous;

        if (! $distance || $distance <= 0 || ! $this->full_tank) {
            return null;
        }

        return round((float) $this->liters / $distance * 100, 2);
    }

    /** Izmaksas uz 100 km šajā posmā (€). */
    public function getCostPer100kmAttribute(): ?float
    {
        $distance = $this->distance_since_previous;

        if (! $distance || $distance <= 0) {
            return null;
        }

        return round((float) $this->total_cost / $distance * 100, 2);
    }
}
