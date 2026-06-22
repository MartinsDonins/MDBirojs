<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlan extends Model
{
    protected $fillable = [
        'vehicle_id',
        'title',
        'description',
        'interval_km',
        'interval_months',
        'last_done_odometer',
        'last_done_at',
        'is_active',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'last_done_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Nākamais apkopes odometrs (km), ja iestatīts km intervāls. */
    public function getNextDueOdometerAttribute(): ?int
    {
        if (! $this->interval_km || $this->last_done_odometer === null) {
            return null;
        }

        return (int) $this->last_done_odometer + (int) $this->interval_km;
    }

    /** Nākamais apkopes datums, ja iestatīts laika intervāls. */
    public function getNextDueDateAttribute(): ?Carbon
    {
        if (! $this->interval_months || ! $this->last_done_at) {
            return null;
        }

        return $this->last_done_at->copy()->addMonths((int) $this->interval_months);
    }

    /** Atlikušie km līdz nākamajai apkopei (var būt negatīvi = nokavēts). */
    public function getKmRemainingAttribute(): ?int
    {
        $due = $this->next_due_odometer;

        if ($due === null) {
            return null;
        }

        return $due - $this->vehicle->current_odometer;
    }

    /** Atlikušās dienas līdz nākamajai apkopei (var būt negatīvas = nokavēts). */
    public function getDaysRemainingAttribute(): ?int
    {
        $due = $this->next_due_date;

        if ($due === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($due, false);
    }

    /**
     * Statuss: 'overdue' (nokavēts), 'soon' (drīz), 'ok' (kārtībā).
     * Sliktākais no km un laika kritērijiem.
     */
    public function getDueStatusAttribute(): string
    {
        $statuses = [];

        $km = $this->km_remaining;
        if ($km !== null) {
            $statuses[] = $km < 0 ? 'overdue' : ($km <= 1000 ? 'soon' : 'ok');
        }

        $days = $this->days_remaining;
        if ($days !== null) {
            $statuses[] = $days < 0 ? 'overdue' : ($days <= 30 ? 'soon' : 'ok');
        }

        if (in_array('overdue', $statuses, true)) {
            return 'overdue';
        }
        if (in_array('soon', $statuses, true)) {
            return 'soon';
        }

        return 'ok';
    }

    public static function dueStatusLabel(string $status): string
    {
        return match ($status) {
            'overdue' => 'Nokavēts',
            'soon' => 'Drīz',
            'ok' => 'Kārtībā',
            default => $status,
        };
    }

    public static function dueStatusColor(string $status): string
    {
        return match ($status) {
            'overdue' => 'danger',
            'soon' => 'warning',
            'ok' => 'success',
            default => 'gray',
        };
    }
}
