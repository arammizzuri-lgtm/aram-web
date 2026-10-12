<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a project's construction: when, what happened, and a few site
 * photographs. Shown newest first on the project's page.
 */
class ProjectUpdate extends Model
{
    protected $fillable = ['project_id', 'date', 'title_en', 'title_ku', 'note_en', 'note_ku', 'images'];

    protected $casts = [
        'date' => 'date',
        'images' => 'array',
    ];

    protected static function booted(): void
    {
        // the same light WebP copies a project's own photos get
        static::saved(fn (ProjectUpdate $u) => Project::buildVariants((array) $u->images));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The photos at one derivative size, in their order.
     *
     * @return array<int, string>
     */
    public function imageUrls(string $size): array
    {
        return collect((array) $this->images)
            ->map(fn ($img) => Project::variantUrlOf($img, $size))
            ->filter()->values()->all();
    }

    public function title(string $lang): ?string
    {
        return $lang === 'ku' && filled($this->title_ku) ? $this->title_ku : $this->title_en;
    }

    public function note(string $lang): ?string
    {
        return $lang === 'ku' && filled($this->note_ku) ? $this->note_ku : $this->note_en;
    }
}
