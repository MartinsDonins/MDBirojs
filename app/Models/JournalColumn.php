<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalColumn extends Model
{
    protected $fillable = [
        'group',
        'name',
        'abbr',
        'vid_columns',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'vid_columns' => 'array',
        'is_visible'  => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * Get visible columns for a group, ordered by sort_order.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function visibleForGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group', $group)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Return all VID column numbers that are mapped (visible) across all groups.
     *
     * @return int[]
     */
    public static function allMappedVidColumns(): array
    {
        return static::where('is_visible', true)
            ->get()
            ->flatMap(fn ($col) => array_map('intval', $col->vid_columns ?? []))
            ->unique()
            ->values()
            ->all();
    }
}
