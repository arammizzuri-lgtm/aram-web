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

    protected static function booted(): void
    {
        $forget = fn () => self::$mapCache = null;
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
}
