<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceItem extends Model
{
    protected $fillable = [
        'maintenance_log_id',
        'title',
        'cost',
        'is_completed',
        'sort_order',
    ];

    protected $casts = [
        'cost'         => 'decimal:2',
        'is_completed' => 'boolean',
    ];

    public function maintenanceLog(): BelongsTo
    {
        return $this->belongsTo(MaintenanceLog::class);
    }
}
