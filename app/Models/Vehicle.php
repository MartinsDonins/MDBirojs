<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'make',
        'model',
        'year',
        'reg_number',
        'vin',
        'color',
        'primary_fuel',
        'has_lpg',
        'tank_capacity',
        'lpg_capacity',
        'initial_odometer',
        'insurance_expires_at',
        'casco_expires_at',
        'inspection_expires_at',
        'is_active',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'has_lpg' => 'boolean',
        'is_active' => 'boolean',
        'tank_capacity' => 'decimal:1',
        'lpg_capacity' => 'decimal:1',
        'insurance_expires_at' => 'date',
        'casco_expires_at' => 'date',
        'inspection_expires_at' => 'date',
    ];

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(FuelLog::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /** Cilvēkam lasāms auto nosaukums. */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }

        $parts = array_filter([$this->make, $this->model, $this->reg_number]);

        return $parts ? implode(' ', $parts) : 'Auto #'.$this->id;
    }

    /** Pašreizējais nobraukums — jaunākais zināmais odometra rādījums. */
    public function getCurrentOdometerAttribute(): int
    {
        $fuelMax = (int) $this->fuelLogs()->max('odometer');
        $maintMax = (int) $this->maintenanceLogs()->max('odometer');

        return max($fuelMax, $maintMax, (int) $this->initial_odometer);
    }

    /** Kopējais nobraukums kopš sākuma odometra (km). */
    public function getTotalDistanceAttribute(): int
    {
        return max(0, $this->current_odometer - (int) $this->initial_odometer);
    }

    /**
     * Vidējais patēriņš L/100km pa degvielas veidu.
     * Aprēķina starp pilnām uzpildēm (full_tank), atsevišķi katram veidam.
     */
    public function averageConsumption(string $fuelType): ?float
    {
        $logs = $this->fuelLogs()
            ->where('fuel_type', $fuelType)
            ->where('full_tank', true)
            ->orderBy('odometer')
            ->get(['odometer', 'liters']);

        if ($logs->count() < 2) {
            return null;
        }

        $first = $logs->first();
        $last = $logs->last();
        $distance = $last->odometer - $first->odometer;

        if ($distance <= 0) {
            return null;
        }

        // Litri, kas izlietoti starp pirmo un pēdējo pilno uzpildi
        // (pirmā uzpilde tikai "atskaites" — tās litrus neskaita).
        $liters = $logs->slice(1)->sum('liters');

        return round($liters / $distance * 100, 2);
    }

    /** Kopējā nesamaksātā summa par apkopēm/remontiem (€). */
    public function getOutstandingAmountAttribute(): float
    {
        return (float) $this->maintenanceLogs()
            ->get(['total_cost', 'amount_paid'])
            ->sum(fn ($m) => max(0, (float) $m->total_cost - (float) $m->amount_paid));
    }
}
