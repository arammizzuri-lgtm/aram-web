<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One step in a project's construction: when, what happened, and a few site
 * photographs. Shown newest first on the project's page — unless it is
 * private, when only the project's client rooms show it.
 *
 * Marked in_journal (and not private), it is also a post of the studio's
 * build journal, at /journal/{slug}.
 */
class ProjectUpdate extends Model
{
    protected $fillable = ['project_id', 'date', 'title_en', 'title_ku', 'title_ar', 'note_en', 'note_ku', 'note_ar', 'images', 'is_private', 'in_journal', 'slug'];

    protected $casts = [
        'date' => 'date',
        'images' => 'array',
        'is_private' => 'boolean',
        'in_journal' => 'boolean',
    ];

    protected static function booted(): void
    {
        // the same light WebP copies a project's own photos get
        static::saved(fn (ProjectUpdate $u) => Project::buildVariants((array) $u->images));

        // a journal post gets its address once, from its project and headline
        static::saving(function (ProjectUpdate $u) {
            if ($u->in_journal && blank($u->slug)) {
                $u->slug = $u->makeSlug();
            }
        });
    }

    /** Published in the journal: chosen, not private, its project published. */
    public function scopeInJournal($query)
    {
        return $query->where('in_journal', true)->where('is_private', false)
            ->whereHas('project', fn ($p) => $p->where('is_published', true));
    }

    /** The post's address, in a language (default: the page's). */
    public function journalUrl(?string $lang = null): string
    {
        return site_url('/journal/'.$this->slug, $lang);
    }

    /** project-headline (or project-date), unique among the posts. */
    public function makeSlug(): string
    {
        $project = $this->project ?? Project::find($this->project_id);
        $base = Str::slug(trim(($project->name ?? 'project').' '.($this->title_en ?: $this->date?->format('Y-m-d'))));
        $base = Str::limit($base ?: 'post', 120, '');
        $slug = $base; $i = 2;
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
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
        return bi_record($lang, $this->title_en, $this->title_ku, $this->title_ar);
    }

    public function note(string $lang): ?string
    {
        return bi_record($lang, $this->note_en, $this->note_ku, $this->note_ar);
    }
}
