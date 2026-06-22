<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-year container for the annual income declaration (GID): manual / EDS-adopted
 * field values plus the last imported EDS XML for comparison.
 *
 * @property array<string,mixed>|null $data
 * @property array<string,mixed>|null $eds_data
 * @property array<string,mixed>|null $eds_meta
 */
class GidDeclaration extends Model
{
    protected $fillable = ['year', 'data', 'eds_data', 'eds_meta'];

    protected $casts = [
        'year'     => 'integer',
        'data'     => 'array',
        'eds_data' => 'array',
        'eds_meta' => 'array',
    ];

    /** Get a single manual/adopted field value. */
    public function field(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
