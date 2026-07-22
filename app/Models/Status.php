<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A project status (e.g. "Completed"). Managed in the admin (add / remove /
 * reorder); projects reference it by {@see $name}. `tone` selects the card
 * badge colour (done | build | concept) reusing the existing CSS.
 */
class Status extends Model
{
    protected $fillable = ['name', 'badge', 'tone', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    /** Badge colours offered in the admin; keys are the CSS modifiers. */
    public const TONES = [
        'done' => 'Green (completed)',
        'build' => 'Amber (in progress)',
        'concept' => 'Grey (concept / planning)',
    ];

    /** @var array<string, array{tone: string, badge: string}>|null */
    private static ?array $metaCache = null;

    protected static function booted(): void
    {
        $forget = fn () => self::$metaCache = null;
        static::saved($forget);
        static::deleted($forget);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** name => name, ordered — for the project editor Select. */
    public static function options(): array
    {
        return static::query()->ordered()->pluck('name', 'name')->all();
    }

    /** name => ['tone', 'badge'] (memoised for per-card look-ups). */
    public static function meta(): array
    {
        return self::$metaCache ??= static::query()->ordered()->get()
            ->mapWithKeys(fn (Status $s) => [$s->name => [
                'tone' => $s->tone ?: 'done',
                'badge' => $s->badge ?: $s->name,
            ]])->all();
    }
}
