<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * SiteSetting
 *
 * Simple key/value store for every editable text/image block on the public
 * site. Reads are memoised for the request (and cached) so rendering the
 * portfolio never fires dozens of tiny queries.
 */
class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public const CACHE_KEY = 'site_settings_all';

    /** Whole table as [key => value], cached until a setting changes. */
    public static function allKeyed(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->toArray();
        });
    }

    /** Read one value with a fallback default. */
    public static function get(string $key, $default = null)
    {
        return static::allKeyed()[$key] ?? $default;
    }

    /** Write one value and bust the cache. */
    public static function put(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget(self::CACHE_KEY);
    }

    /** Clear the cached settings (call after bulk admin saves). */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        // Any direct model write keeps the cache honest.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
