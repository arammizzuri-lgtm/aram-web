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

    /**
     * Settings that are the studio's business, not the page's: the e-mail
     * alert recipients and the mailbox the alerts are sent from, and the
     * key to the Kurdish read-aloud service. Anything under these prefixes
     * is kept out of what the public site is given.
     */
    public const PRIVATE_PREFIXES = ['alerts_', 'reader_api_'];

    /** Every setting the public site may see — allKeyed() minus the private ones. */
    public static function publicKeyed(): array
    {
        return array_filter(
            static::allKeyed(),
            fn ($key) => ! \Illuminate\Support\Str::startsWith((string) $key, self::PRIVATE_PREFIXES),
            ARRAY_FILTER_USE_KEY,
        );
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
