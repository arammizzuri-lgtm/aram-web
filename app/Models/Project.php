<?php

namespace App\Models;

use App\Support\ProjectImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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
        'related', 'imgs', 'sort_order', 'is_published', 'map_only',
    ];

    protected $casts = [
        'materials' => 'array',
        'related' => 'array',
        'imgs' => 'array',
        'is_published' => 'boolean',
        'map_only' => 'boolean',
        'sort_order' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function booted(): void
    {
        // Keep a light WebP thumbnail for every uploaded image so the grid
        // never has to download the multi-MB originals.
        static::saved(fn (Project $p) => $p->generateThumbnails());
    }

    /** Only published projects, in display order — used by the public site. */
    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** True for a stored upload (a bare disk path, not an external URL). */
    private static function isUpload(?string $img): bool
    {
        return is_string($img) && $img !== ''
            && ! preg_match('#^(https?:)?//#', $img)
            && ! str_starts_with($img, '/');
    }

    /** Relative disk path of an upload's thumbnail (projects/thumb/<name>.webp), or null. */
    public static function thumbRel(?string $img): ?string
    {
        if (! self::isUpload($img)) {
            return null;
        }

        return 'projects/thumb/'.pathinfo($img, PATHINFO_FILENAME).'.webp';
    }

    /** Build any missing thumbnails for this project's uploaded images. */
    public function generateThumbnails(): void
    {
        $disk = Storage::disk('public');
        foreach ((array) $this->imgs as $img) {
            $rel = self::thumbRel($img);
            if ($rel === null || $disk->exists($rel) || ! $disk->exists($img)) {
                continue;
            }
            ProjectImage::thumbnail($disk->path($img), $disk->path($rel));
        }
    }

    /** Grid/preview URL for one image — the small thumbnail when we have one. */
    public function thumbUrl(?string $img): ?string
    {
        $rel = self::thumbRel($img);
        if ($rel !== null && Storage::disk('public')->exists($rel)) {
            return self::resolveImage($rel);
        }

        return self::resolveImage($img);
    }

    public function categoryLabel(): string
    {
        return Category::map()[$this->category]
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
        return Status::meta()[$this->status]['tone'] ?? 'done';
    }

    public function statusBadge(): string
    {
        return Status::meta()[$this->status]['badge'] ?? (string) $this->status;
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

    /** First image — the grid card cover, full resolution. */
    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    /** First image as a light thumbnail — used on the projects grid. */
    public function coverThumbUrl(): ?string
    {
        $first = collect($this->imgs ?? [])->first();

        return $first ? $this->thumbUrl($first) : null;
    }
}
