<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table    = 'app_settings';
    protected $fillable = ['key', 'value'];

    private const CACHE_TTL = 300; // 5 minutes

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember("app_setting:{$key}", self::CACHE_TTL, function () use ($key) {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : '__NOT_SET__';
        });

        if ($value === '__NOT_SET__') {
            return $default;
        }

        // Cast common booleans
        if ($value === '1' || $value === 'true')  return true;
        if ($value === '0' || $value === 'false') return false;

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        static::updateOrCreate(['key' => $key], ['value' => $stored]);

        Cache::forget("app_setting:{$key}");
    }

    public static function getRaw(string $key, string $default = ''): string
    {
        $row = static::where('key', $key)->first();
        return $row?->value ?? $default;
    }
}
