<?php

namespace App\Models;

use App\Models\Concerns\HasPageSections;
use App\Support\Articles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * An essay written in the admin for the journal (/journal/{slug}): a title,
 * a line and a cover photograph for its card, and a page composed of the
 * heritage pages' sections — text, photographs, galleries, quotations and the
 * rest — every text in English, Kurdish and Arabic. Essays with drawings of
 * their own are code instead (App\Support\Articles).
 */
class Essay extends Model
{
    use HasPageSections;   // its photographs: measured, resized, listed

    /** The kinds of section an essay can have: the landmarks', less the ones about a place. */
    public const BLOCKS = ['text', 'statement', 'image', 'gallery', 'stats', 'facts', 'timeline', 'quote', 'annotated', 'compare', 'sources'];

    protected $fillable = ['slug', 'date', 'title_en', 'title_ku', 'title_ar', 'summary_en', 'summary_ku', 'summary_ar',
        'image', 'image_credit', 'image_source', 'sections', 'is_published'];

    protected $casts = ['date' => 'date', 'sections' => 'array', 'is_published' => 'boolean'];

    protected static function booted(): void
    {
        // an address made from the English title, one no other journal page has
        static::saving(function (self $e) {
            if (blank($e->slug)) {
                $base = Str::slug($e->title_en) ?: 'essay';
                $slug = $base;
                for ($i = 2; ! self::slugIsFree($slug, $e->id); $i++) {
                    $slug = $base.'-'.$i;
                }
                $e->slug = $slug;
            }
        });
    }

    /** Whether /journal/{slug} is free for this essay: not another essay, an essay in code or a build entry. */
    public static function slugIsFree(string $slug, ?int $except = null): bool
    {
        return ! Articles::find($slug)
            && ! self::where('slug', $slug)->where('id', '!=', $except ?? 0)->exists()
            && ! ProjectUpdate::where('slug', $slug)->exists();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** A text in a language (default: the page's), the English where none was written. */
    public function tr(string $field, ?string $lang = null): ?string
    {
        $lang ??= site_lang();
        $field = $field === 'name' ? 'title' : $field;   // the heritage blocks ask for their page's name

        return Landmark::separators(bi_record($lang, $this->{$field.'_en'}, $this->{$field.'_ku'}, $this->{$field.'_ar'}), $lang);
    }

    /** @return array<int, array{type: string, data: array}> */
    public function sections(): array
    {
        $out = [];
        foreach ((array) $this->sections as $block) {
            if (in_array($block['type'] ?? null, self::BLOCKS, true)) {
                $out[] = ['type' => $block['type'], 'data' => (array) ($block['data'] ?? [])];
            }
        }

        return $out;
    }

    protected function coverAltField(): string
    {
        return 'title';
    }

    public function url(?string $lang = null): string
    {
        return site_url('/journal/'.$this->slug, $lang);
    }

    /** A signed link that shows the page even while it is a draft — for the admin's Preview. */
    public function previewUrl(string $lang = 'en', int $hours = 24): string
    {
        $route = ($lang === 'en' ? '' : $lang.'.').'journal.post';

        return URL::temporarySignedRoute($route, now()->addHours($hours), ['slug' => $this->slug]);
    }
}
