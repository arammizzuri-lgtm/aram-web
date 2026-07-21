<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Project
 *
 * One architecture project shown on the public portfolio. List columns
 * (materials / related / imgs) are stored as JSON and cast to arrays so the
 * front-end receives the same shape it had when PROJECT_DATA was hard-coded.
 */
class Project extends Model
{
    protected $fillable = [
        'num', 'name', 'name_ku', 'category', 'status', 'size', 'area', 'typology',
        'location', 'lat', 'lng', 'year', 'desc', 'desc_ku', 'narrative', 'materials',
        'related', 'imgs', 'sort_order', 'is_published',
    ];

    protected $casts = [
        'materials' => 'array',
        'related' => 'array',
        'imgs' => 'array',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
    ];

    /** Human label per category — mirrors the public filter buttons. */
    public const CATEGORY_LABELS = [
        'cultural' => 'Cultural',
        'mixed-use' => 'Mixed-Use',
        'hospitality' => 'Hospitality',
        'commercial' => 'Commercial',
        'residential' => 'Residential',
        'urban' => 'Master Planning',
    ];

    /** status text => [css modifier, short badge label] used on the grid card. */
    public const STATUS_BADGES = [
        'Completed' => ['done', 'Completed'],
        'Under Construction' => ['build', 'Under Construction'],
        'Concept / Planning' => ['concept', 'Concept'],
    ];

    /** Only published projects, in display order — used by the public site. */
    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category]
            ?? Str::title(str_replace('-', ' ', (string) $this->category));
    }

    /** City = first segment of the location string. */
    public function cityLabel(): string
    {
        return trim(explode(',', (string) $this->location)[0] ?? '');
    }

    /** End year = last 4-digit number found in the year string. */
    public function endYear(): string
    {
        preg_match_all('/\d{4}/', (string) $this->year, $m);

        return end($m[0]) ?: (string) $this->year;
    }

    /** "Cultural · Erbil · 2024" — the small meta line on each card. */
    public function metaLabel(): string
    {
        return collect([$this->categoryLabel(), $this->cityLabel(), $this->endYear()])
            ->filter()->implode(' · ');
    }

    public function statusClass(): string
    {
        return self::STATUS_BADGES[$this->status][0] ?? 'done';
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status][1] ?? (string) $this->status;
    }

    /**
     * Resolve a stored image reference to a public URL.
     * Full URLs (Unsplash etc.) and absolute paths pass through untouched;
     * bare paths are treated as files on the public storage disk.
     */
    public static function resolveImage(?string $img): ?string
    {
        if (! $img) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $img) || str_starts_with($img, '/')) {
            return $img;
        }

        return asset('storage/'.ltrim($img, '/'));
    }

    /** All images as resolved public URLs (for window.__SITE__). */
    public function imageUrls(): array
    {
        return collect($this->imgs ?? [])
            ->map(fn ($img) => self::resolveImage($img))
            ->filter()
            ->values()
            ->all();
    }

    /** First image — the grid card cover. */
    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }
}
