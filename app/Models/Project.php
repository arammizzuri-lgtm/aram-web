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
        'num', 'name', 'name_ku', 'category', 'categories', 'status', 'size', 'area', 'typology',
        'location', 'neighbourhood', 'city', 'country', 'lat', 'lng', 'year',
        'desc', 'desc_ku', 'narrative', 'materials',
        'related', 'imgs', 'cover', 'cover_x', 'cover_y',
        'sort_order', 'is_published', 'map_only',
    ];

    /** Focal point defaults to the centre of the cover image. */
    protected $attributes = [
        'cover_x' => 50,
        'cover_y' => 50,
    ];

    protected $casts = [
        'categories' => 'array',
        'materials' => 'array',
        'related' => 'array',
        'imgs' => 'array',
        'is_published' => 'boolean',
        'map_only' => 'boolean',
        'sort_order' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
        'cover_x' => 'integer',
        'cover_y' => 'integer',
    ];

    protected static function booted(): void
    {
        // Keep the display `location` string composed from the structured parts.
        static::saving(function (Project $p) {
            $composed = collect([$p->neighbourhood, $p->city, $p->country])
                ->filter(fn ($v) => filled($v))->implode(', ');
            if ($composed !== '') {
                $p->location = $composed;
            }

            // A form that never opened the cover picker sends these as null,
            // which would override the column default and break the insert.
            $p->cover_x ??= 50;
            $p->cover_y ??= 50;

            // Keep the category list and the primary `category` key in step:
            // the list is authoritative, its first entry is the primary.
            $keys = collect((array) $p->categories)
                ->map(fn ($k) => (string) $k)->filter()->unique()->values();
            if ($keys->isEmpty() && filled($p->category)) {
                $keys = collect([(string) $p->category]);
            }
            $p->categories = $keys->all();
            if ($keys->isNotEmpty()) {
                $p->category = $keys->first();
            }
        });

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

    /**
     * Every category key on the project, primary first. Falls back to the
     * single `category` column for rows saved before the list existed.
     *
     * @return array<int, string>
     */
    public function categoryKeys(): array
    {
        $keys = collect((array) $this->categories)->filter()->values();

        return $keys->isNotEmpty()
            ? $keys->all()
            : array_values(array_filter([$this->category]));
    }

    /** Human label for one category key ("mixed-use" -> "Mixed-Use"). */
    public static function categoryKeyLabel(?string $key): string
    {
        return Category::map()[$key]
            ?? Str::title(str_replace('-', ' ', (string) $key));
    }

    /** Label of the primary category — the one shown where space allows one. */
    public function categoryLabel(): string
    {
        return self::categoryKeyLabel($this->category);
    }

    /**
     * Labels of every category, primary first.
     *
     * @return array<int, string>
     */
    public function categoryLabels(): array
    {
        return array_map(fn ($k) => self::categoryKeyLabel($k), $this->categoryKeys());
    }

    /** City name — the structured field, falling back to the location string. */
    public function cityLabel(): string
    {
        return $this->city ?: trim(explode(',', (string) $this->location)[0] ?? '');
    }

    /** End year = last 4-digit number found in the year string. */
    public function endYear(): string
    {
        preg_match_all('/\d{4}/', (string) $this->year, $m);

        return end($m[0]) ?: (string) $this->year;
    }

    /** "Residential · Exterior · Erbil · 2024" — the meta line on each card. */
    public function metaLabel(): string
    {
        return collect([...$this->categoryLabels(), $this->cityLabel(), $this->endYear()])
            ->filter()->implode(' · ');
    }

    /**
     * Everything the grid's search box should match on: the name in both
     * languages, every category label (English and Kurdish), the typology,
     * place and year. Lower-cased and space-separated for a substring test.
     */
    public function searchText(): string
    {
        $categories = collect($this->categoryKeys())
            ->flatMap(fn ($k) => [self::categoryKeyLabel($k), Category::kurdishMap()[$k] ?? null, $k]);

        return collect([$this->name, $this->name_ku, $this->typology, $this->location, $this->year])
            ->merge($categories)
            ->filter()
            ->map(fn ($v) => mb_strtolower((string) $v))
            ->unique()
            ->implode(' ');
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

    /** Images with the chosen cover first — the order the overlay shows them in. */
    public function orderedImageUrls(): array
    {
        $imgs = collect($this->imgs ?? []);
        $cover = $this->coverImage();
        if ($cover !== null) {
            $imgs = $imgs->reject(fn ($i) => $i === $cover)->prepend($cover);
        }

        return $imgs->map(fn ($img) => self::resolveImage($img))->filter()->values()->all();
    }

    /** The chosen cover image reference, or the first image if none is set / valid. */
    public function coverImage(): ?string
    {
        $imgs = (array) ($this->imgs ?? []);
        if ($this->cover && in_array($this->cover, $imgs, true)) {
            return $this->cover;
        }

        return $imgs[0] ?? null;
    }

    /** Grid card cover, full resolution. */
    public function coverUrl(): ?string
    {
        $cover = $this->coverImage();

        return $cover ? self::resolveImage($cover) : null;
    }

    /** Grid card cover as a light thumbnail. */
    public function coverThumbUrl(): ?string
    {
        $cover = $this->coverImage();

        return $cover ? $this->thumbUrl($cover) : null;
    }

    /** CSS object-position for the cover's focal point, e.g. "50% 30%". */
    public function coverPosition(): string
    {
        return ($this->cover_x ?? 50).'% '.($this->cover_y ?? 50).'%';
    }
}
