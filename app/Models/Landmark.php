<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A landmark of Kurdistan's architectural heritage (/kurdistan-heritage):
 * a pin on the map of Greater Kurdistan and a page of its own, every text
 * in English, Kurdish and Arabic.
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

    protected $fillable = [
        'slug', 'sort_order', 'region', 'era',
        'name_en', 'name_ku', 'name_ar', 'place_en', 'place_ku', 'place_ar', 'period_en', 'period_ku', 'period_ar',
        'summary_en', 'summary_ku', 'summary_ar', 'body_en', 'body_ku', 'body_ar',
        'lat', 'lng', 'image', 'image_credit', 'unesco', 'is_published',
    ];

    protected $casts = ['is_published' => 'boolean', 'unesco' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'sort_order' => 'integer'];

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
        });
    }

    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /** A text in a language (default: the page's), the English where none was written. */
    public function tr(string $field, ?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->{$field.'_en'}, $this->{$field.'_ku'}, $this->{$field.'_ar'});
    }

    /** The body as paragraphs (a blank line between them). */
    public function paragraphs(?string $lang = null): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/u", (string) $this->tr('body', $lang)))));
    }

    public function url(?string $lang = null): string
    {
        return site_url(self::PATH.'/'.$this->slug, $lang);
    }

    public function imageUrl(): ?string
    {
        return Project::resolveImage($this->image);
    }

    public static function regionName(string $key, ?string $lang = null): string
    {
        $names = self::REGIONS[$key] ?? [$key, $key, $key];

        return bi_record($lang ?? site_lang(), ...$names);
    }

    public static function eraName(string $key, ?string $lang = null): string
    {
        $names = self::ERAS[$key] ?? [$key, $key, $key];

        return bi_record($lang ?? site_lang(), ...$names);
    }

    /** For the admin's select boxes. */
    public static function options(array $list): array
    {
        return array_map(fn ($names) => $names[0], $list);
    }
}
