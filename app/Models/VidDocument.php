<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single document submitted to the VID EDS system for a given year.
 */
class VidDocument extends Model
{
    protected $fillable = [
        'year',
        'status_code',
        'status',
        'document_name',
        'submitted_at',
        'notes',
        'link',
    ];

    protected $casts = [
        'year'         => 'integer',
        'submitted_at' => 'date',
    ];

    /**
     * Real EDS document statuses (textual labels). The numeric code is entered
     * separately per document (e.g. "05" for "Pieņemts").
     *
     * @var string[]
     */
    public const STATUSES = [
        'Sagatave',
        'Iesniegts',
        'Apstrādē',
        'Daļēji pieņemts',
        'Pieņemts',
        'Noraidīts',
        'Anulēts',
    ];

    /**
     * Filament badge color for a given status label.
     */
    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'Pieņemts'        => 'success',
            'Iesniegts'       => 'info',
            'Apstrādē'        => 'warning',
            'Daļēji pieņemts' => 'warning',
            'Noraidīts'       => 'danger',
            default           => 'gray',
        };
    }
}
