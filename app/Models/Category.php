<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A project category. Managed in the admin (add / remove / reorder); projects
 * reference it by {@see $key}. Provides the option list for the project editor
 * and the bilingual labels for the public filter row.
 */
class Category extends Model
{
    protected $fillable = ['key', 'name', 'name_ku', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    /** @var array<string, string>|null */
    private static ?array $mapCache = null;

    /** @var array<string, string>|null */
    private static ?array $kuMapCache = null;

    protected static function booted(): void
    {
        $forget = function () {
            self::$mapCache = null;
            self::$kuMapCache = null;
        };
        static::saved($forget);
        static::deleted($forget);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** key => name, ordered — for Select options, table columns and filters. */
    public static function options(): array
    {
        return static::map();
    }

    /** key => name (memoised for the many look-ups during a page render). */
    public static function map(): array
    {
        return self::$mapCache ??= static::query()->ordered()->pluck('name', 'key')->all();
    }

    /** key => Kurdish name, for the ones that have one — used by grid search. */
    public static function kurdishMap(): array
    {
        return self::$kuMapCache ??= array_filter(
            static::query()->ordered()->pluck('name_ku', 'key')->all()
        );
    }
}
