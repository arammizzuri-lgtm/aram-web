<?php

namespace App\Models;

use App\Support\HeritageImage;
use App\Support\SiteText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A landmark of Kurdistan's architectural heritage (/kurdistan-heritage):
 * a pin on the map of Greater Kurdistan and an editorial page of its own,
 * every text in English, Kurdish and Arabic.
 *
 * The page is an opening (a hero in one of a few styles, the name, an
 * introduction) followed by sections — reorderable blocks of different kinds
 * that the admin composes per landmark, so no two pages need the same shape:
 * text, a photograph, a gallery, statistics, facts, a timeline, a quotation,
 * an annotated photograph, a before/after pair, the map, the UNESCO panel,
 * related landmarks and sources. Each block's data carries its texts in the
 * three languages (field_en / field_ku / field_ar) and its photographs as
 * files on the public disk (see HeritageImage).
 */
class Landmark extends Model
{
    public const PATH = '/kurdistan-heritage';

    /** The four parts of Kurdistan: [English, Kurdish, Arabic]. */
    public const REGIONS = [
        'bashur' => ['South Kurdistan · Bashur', 'باشووری کوردستان', 'جنوب كوردستان · باشور'],
        'bakur' => ['North Kurdistan · Bakur', 'باکووری کوردستان', 'شمال كوردستان · باكور'],
        'rojhelat' => ['East Kurdistan · Rojhelat', 'ڕۆژهەڵاتی کوردستان', 'شرق كوردستان · روجهلات'],
        'rojava' => ['West Kurdistan · Rojava', 'ڕۆژئاوای کوردستان', 'غرب كوردستان · روج آفا'],
    ];

    /** The ages, oldest first: [English, Kurdish, Arabic]. */
    public const ERAS = [
        'prehistory' => ['Prehistory', 'پێش مێژوو', 'ما قبل التاريخ'],
        'mesopotamia' => ['Ancient Mesopotamia', 'میزۆپۆتامیای کۆن', 'بلاد الرافدين القديمة'],
        'empires' => ['Persian, Roman & Sasanian', 'فارسی، ڕۆمانی و ساسانی', 'الفارسية والرومانية والساسانية'],
        'medieval' => ['Medieval & Islamic', 'سەدەکانی ناوەڕاست و ئیسلامی', 'العصور الوسطى والإسلامية'],
        'principalities' => ['Kurdish principalities', 'میرنشینە کوردییەکان', 'الإمارات الكردية'],
        'living' => ['Living heritage', 'میراتی زیندوو', 'تراث حيّ'],
    ];

    /**
     * What a landmark is built of, and the colour each has on the map and in
     * the tags — the stone's or the clay's own, near enough. The names and the
     * line on each are site texts (landmarks_mat_<key>, …_note), so the admin
     * can reword them.
     */
    public const MATERIALS = [
        'limestone' => '#E9D9A6',
        'basalt' => '#8892A4',
        'sandstone' => '#DE9160',
        'stone' => '#BDB5A4',
        'brick' => '#D4553B',
        'mudbrick' => '#B27F3F',
        'rock' => '#6FB8B0',
        'timber' => '#86B257',
    ];

    /** How a page opens — the admin's choices, with what each looks like. */
    public const HERO_STYLES = [
        'cinematic' => 'Cinematic — the photograph fills the screen, the name over it',
        'split' => 'Split — the name beside a tall photograph',
        'editorial' => 'Editorial — the name, then a wide photograph',
        'frame' => 'Frame — a photograph set in wide margins, the name above',
        'dark' => 'Dark — the name and a large figure, the photograph further down',
    ];

    /** The kinds of section a page can be built from. */
    public const BLOCKS = ['text', 'statement', 'image', 'gallery', 'stats', 'facts', 'timeline', 'quote', 'annotated', 'compare', 'map', 'unesco', 'related', 'sources'];

    protected $fillable = [
        'slug', 'sort_order', 'region', 'era', 'hero_style',
        'name_en', 'name_ku', 'name_ar', 'place_en', 'place_ku', 'place_ar', 'period_en', 'period_ku', 'period_ar',
        'summary_en', 'summary_ku', 'summary_ar', 'intro_en', 'intro_ku', 'intro_ar', 'body_en', 'body_ku', 'body_ar',
        'sections', 'year_from', 'year_to', 'materials',
        'lat', 'lng', 'image', 'image_credit', 'image_source',
        'unesco', 'unesco_year', 'unesco_ref', 'unesco_criteria', 'unesco_name', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean', 'unesco' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'sort_order' => 'integer',
        'sections' => 'array', 'materials' => 'array', 'year_from' => 'integer', 'year_to' => 'integer', 'unesco_year' => 'integer', 'unesco_ref' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $l) {
            if (blank($l->slug)) {
                $base = Str::slug($l->name_en) ?: 'landmark';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->where('id', '!=', $l->id ?? 0)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $l->slug = $slug;
            }
            if (! array_key_exists($l->hero_style, self::HERO_STYLES)) {
                $l->hero_style = 'editorial';
            }
            $l->measureImages();
        });

        // the three sizes of every photograph, built once, after the save
        static::saved(fn (self $l) => HeritageImage::buildVariants($l->imageFiles()));
    }

    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /* ── Texts ──────────────────────────────────────────────────────── */

    /** A text in a language (default: the page's), the English where none was written. */
    public function tr(string $field, ?string $lang = null): ?string
    {
        $lang ??= site_lang();

        return self::separators(bi_record($lang, $this->{$field.'_en'}, $this->{$field.'_ku'}, $this->{$field.'_ar'}), $lang);
    }

    /** A block's text in a language (default: the page's) — field_en / field_ku / field_ar. */
    public static function t(array $data, string $field, ?string $lang = null): ?string
    {
        $lang ??= site_lang();

        return self::separators(bi_record($lang, $data[$field.'_en'] ?? null, $data[$field.'_ku'] ?? null, $data[$field.'_ar'] ?? null), $lang);
    }

    /**
     * A number's separators as Kurdish and Arabic write them: ٬ between the
     * thousands (٤٦٬٠٠٠, not ٤٦,٠٠٠) and ٫ before the decimals (١٫٥). Only a
     * comma or point between Eastern Arabic digits is touched — a comma
     * followed by exactly three digits, a point followed by any — so English
     * text, and the Kurdish comma (،) in a sentence, are left alone.
     */
    public static function separators(?string $s, ?string $lang = null): ?string
    {
        if ($s === null || ($lang ?? site_lang()) === 'en' || ! preg_match('/[٠-٩]/u', $s)) {
            return $s;
        }
        $s = preg_replace('/(?<=[٠-٩]),(?=[٠-٩]{3}(?![٠-٩]))/u', '٬', $s);

        return preg_replace('/(?<=[٠-٩])\.(?=[٠-٩])/u', '٫', $s);
    }

    /** The body as paragraphs (a blank line between them). */
    public function paragraphs(?string $lang = null): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/u", (string) $this->tr('body', $lang)))));
    }

    /**
     * A section's text as HTML, from the plain format the admin writes in:
     * a blank line between paragraphs, "- " for a list, "> " for a quotation,
     * "## " for a small heading, **bold**, *italic* and [a link](https://…).
     */
    public static function rich(?string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return '';
        }
        $inline = function (string $s): string {
            $s = e($s);
            $s = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $s);
            $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/su', '<em>$1</em>', $s);
            $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s);

            return nl2br($s, false);
        };
        $out = [];
        foreach (preg_split("/\n\s*\n/u", $text) as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^(-\s.+\n?)+$/u', $block)) {
                $items = array_map(fn ($l) => '<li>'.$inline(trim(substr(trim($l), 1))).'</li>', explode("\n", $block));
                $out[] = '<ul>'.implode('', $items).'</ul>';
            } elseif (str_starts_with($block, '> ')) {
                $out[] = '<blockquote>'.$inline(preg_replace('/^>\s?/mu', '', $block)).'</blockquote>';
            } elseif (str_starts_with($block, '## ')) {
                $out[] = '<h3>'.$inline(substr($block, 3)).'</h3>';
            } else {
                $out[] = '<p>'.$inline($block).'</p>';
            }
        }

        return implode("\n", $out);
    }

    /** A number's digits in the page's language — Eastern Arabic in Kurdish and Arabic, with their separators. */
    public static function digits(string|int|null $s, ?string $lang = null): string
    {
        $s = (string) $s;
        $lang ??= site_lang();

        return $lang === 'en' ? $s
            : (string) self::separators(strtr($s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']), $lang);
    }

    /* ── The page's sections ─────────────────────────────────────────── */

    /**
     * The page's sections in order, each ['type' => …, 'data' => […]], only
     * the kinds the page knows. A landmark written before the sections
     * existed shows its body as one text section.
     *
     * @return array<int, array{type: string, data: array}>
     */
    public function sections(): array
    {
        $out = [];
        foreach ((array) $this->sections as $block) {
            $type = $block['type'] ?? null;
            if (in_array($type, self::BLOCKS, true)) {
                $out[] = ['type' => $type, 'data' => (array) ($block['data'] ?? [])];
            }
        }
        if ($out === [] && filled($this->body_en)) {
            $out[] = ['type' => 'text', 'data' => ['layout' => 'standard', 'body_en' => $this->body_en, 'body_ku' => $this->body_ku, 'body_ar' => $this->body_ar]];
        }

        return $out;
    }

    public function hasSection(string $type): bool
    {
        return collect($this->sections())->contains('type', $type);
    }

    /**
     * Every photograph on the page in order, the cover first: each with its
     * file, size, alt, caption and credit as the image partial wants them.
     *
     * @return array<int, array>
     */
    public function images(): array
    {
        $images = [];
        if ($this->image) {
            $images[] = ['file' => $this->image, 'w' => null, 'h' => null, 'alt_en' => $this->name_en, 'alt_ku' => $this->name_ku, 'alt_ar' => $this->name_ar,
                'credit' => $this->image_credit, 'source' => $this->image_source];
        }
        foreach ($this->sections() as ['type' => $type, 'data' => $d]) {
            foreach (self::imagesIn($type, $d) as $img) {
                $images[] = $img;
            }
        }

        return $images;
    }

    /** The image arrays inside one block, by its kind. */
    private static function imagesIn(string $type, array $d): array
    {
        return match ($type) {
            'image', 'annotated' => filled($d['file'] ?? null) ? [$d] : [],
            'gallery' => array_values(array_filter((array) ($d['images'] ?? []), fn ($i) => filled($i['file'] ?? null))),
            'compare' => array_values(array_filter([$d['before'] ?? null, $d['after'] ?? null], fn ($i) => filled($i['file'] ?? null))),
            default => [],
        };
    }

    /** Every photograph's file reference, cover included (for the resizer). */
    public function imageFiles(): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($i) => $i['file'] ?? null, $this->images()))));
    }

    /**
     * Before a save: the width and height of any photograph that does not
     * carry them yet, read from the file, so the page can reserve the right
     * space before the picture arrives.
     */
    private function measureImages(): void
    {
        $sections = $this->sections;
        if (! is_array($sections)) {
            return;
        }
        $measure = function (array $img): array {
            if (filled($img['file'] ?? null) && (empty($img['w']) || empty($img['h']) || ($img['_file'] ?? null) !== $img['file'])) {
                if ($dims = HeritageImage::dimensions($img['file'])) {
                    [$img['w'], $img['h']] = $dims;
                }
            }
            unset($img['_file']);

            return $img;
        };
        foreach ($sections as &$block) {
            $type = $block['type'] ?? '';
            $d = &$block['data'];
            if (! is_array($d)) {
                continue;
            }
            if (in_array($type, ['image', 'annotated'], true)) {
                $d = $measure($d);
            } elseif ($type === 'gallery' && is_array($d['images'] ?? null)) {
                $d['images'] = array_map($measure, $d['images']);
            } elseif ($type === 'compare') {
                foreach (['before', 'after'] as $k) {
                    if (is_array($d[$k] ?? null)) {
                        $d[$k] = $measure($d[$k]);
                    }
                }
            }
        }
        unset($block, $d);
        $this->sections = $sections;
    }

    /* ── Images ──────────────────────────────────────────────────────── */

    /** The cover photograph at a size: lg (default), md or sm. */
    public function imageUrl(string $size = 'lg'): ?string
    {
        return HeritageImage::url($this->image, $size);
    }

    /* ── Addresses ───────────────────────────────────────────────────── */

    public function url(?string $lang = null): string
    {
        return site_url(self::PATH.'/'.$this->slug, $lang);
    }

    /** A signed link that shows the page even while it is unpublished — for the admin's Preview. */
    public function previewUrl(string $lang = 'en', int $hours = 24): string
    {
        $route = ($lang === 'en' ? '' : $lang.'.').'landmark';

        return URL::temporarySignedRoute($route, now()->addHours($hours), ['slug' => $this->slug]);
    }

    /* ── Relations between landmarks ─────────────────────────────────── */

    /**
     * Other landmarks to go on to: the ones the page names in a "related"
     * section, else the same age, then the same part of Kurdistan.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function related(int $n = 3, ?array $slugs = null)
    {
        $all = static::publishedOrdered()->where('id', '!=', $this->id)->get();
        if ($slugs === null) {
            foreach ($this->sections() as ['type' => $type, 'data' => $d]) {
                if ($type === 'related' && filled($d['slugs'] ?? null)) {
                    $slugs = (array) $d['slugs'];
                    break;
                }
            }
        }
        $picked = collect($slugs ?? [])->map(fn ($s) => $all->firstWhere('slug', $s))->filter()->values();
        if ($picked->count() >= $n) {
            return $picked->take($n);
        }
        $rest = $all->reject(fn ($l) => $picked->contains('id', $l->id))
            ->sortBy(fn ($l) => ($l->era === $this->era ? 0 : 2) + ($l->region === $this->region ? 0 : 1));

        return $picked->concat($rest)->take($n)->values();
    }

    /* ── Names of the ages and parts ─────────────────────────────────── */

    public static function regionName(string $key, ?string $lang = null): string
    {
        $names = self::REGIONS[$key] ?? [$key, $key, $key];

        return bi_record($lang ?? site_lang(), ...$names);
    }

    /** The landmark's materials, the main one first — only the ones the site knows. */
    public function materialKeys(): array
    {
        return array_values(array_intersect((array) $this->materials, array_keys(self::MATERIALS)));
    }

    /** A material's name in a language (default: the page's). */
    public static function materialName(string $key, ?string $lang = null): string
    {
        return match ($lang ?? site_lang()) {
            'ku' => SiteText::ku('landmarks_mat_'.$key),
            'ar' => SiteText::ar('landmarks_mat_'.$key),
            default => SiteText::en('landmarks_mat_'.$key),
        } ?: $key;
    }

    public static function eraName(string $key, ?string $lang = null): string
    {
        $names = self::ERAS[$key] ?? [$key, $key, $key];

        return bi_record($lang ?? site_lang(), ...$names);
    }

    /** For the admin's select boxes. */
    public static function options(array $list): array
    {
        return array_map(fn ($names) => is_array($names) ? $names[0] : $names, $list);
    }

    /**
     * A year as people write it: "9600 BC", "AD 349", "1784" — digits in
     * the page's language, the era word in it too.
     */
    public static function yearLabel(?int $year, ?string $lang = null): string
    {
        if ($year === null) {
            return '';
        }
        $lang ??= site_lang();
        $n = self::digits(number_format(abs($year), 0, '.', $year <= -10000 ? ',' : ''), $lang);
        if ($year < 0) {
            return match ($lang) { 'ku' => $n.' پ.ز', 'ar' => $n.' ق.م', default => $n.' BC' };
        }
        if ($year < 1000) {
            return match ($lang) { 'ku' => $n.' ز', 'ar' => $n.' م', default => 'AD '.$n };
        }

        return $n;
    }
}
