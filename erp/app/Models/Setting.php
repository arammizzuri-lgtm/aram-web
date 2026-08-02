<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }

    /** @return Collection<string, string|null> */
    public static function values(): Collection
    {
        return Cache::rememberForever(
            'settings.all',
            fn () => static::query()->pluck('value', 'key')
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::values()->get($key, $default);
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
