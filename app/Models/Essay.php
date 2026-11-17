<?php

namespace App\Models;

use App\Models\Concerns\HasPageSections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * An essay written in the admin for the journal (/journal/{slug}): a title,
 * a line and a cover photograph for its card, and a page composed of the
 * heritage pages' sections — text, photographs, galleries, quotations and the
 * rest — every text in English, Kurdish and Arabic. An essay on buildings
 * also has chapters and plates drawn as the journal draws them (the Citadel's
 * houses, the Qaysari), and `extras`: its card's drawing sheet in place of a
 * photograph, a link at its end, and the place it is about.
 */
class Essay extends Model
{
    use HasPageSections;   // its photographs: measured, resized, listed

    /** The kinds of section an essay can have: the landmarks', less the ones about a place. */
    public const BLOCKS = ['text', 'statement', 'image', 'gallery', 'stats', 'facts', 'timeline', 'quote', 'annotated', 'compare', 'drawing', 'sources', 'count', 'chapter', 'plates'];

    protected $fillable = ['slug', 'date', 'title_en', 'title_ku', 'title_ar', 'summary_en', 'summary_ku', 'summary_ar',
        'image', 'image_credit', 'image_source', 'sections', 'extras', 'is_published'];

    protected $casts = ['date' => 'date', 'sections' => 'array', 'extras' => 'array', 'is_published' => 'boolean'];

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

    /** Whether /journal/{slug} is free for this essay: not another essay or a build entry. */
    public static function slugIsFree(string $slug, ?int $except = null): bool
    {
        return ! in_array($slug, ['feed', 'glossary'], true)   // the journal's feed and glossary
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

    /** One of `extras` in a language (default: the page's): a text in three, as the sections have them (kicker_en …). */
    public function extra(string $field, ?string $lang = null): ?string
    {
        return Landmark::t((array) $this->extras, $field, $lang);
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
