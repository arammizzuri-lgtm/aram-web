<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A partner or supplier the studio builds with (/partners): a brand or firm,
 * the trade it belongs to, a line in three languages, a logo and a link.
 */
class Partner extends Model
{
    protected $fillable = ['name', 'category', 'description_en', 'description_ku', 'description_ar', 'logo', 'website', 'sort_order', 'is_published'];

    protected $casts = ['is_published' => 'boolean', 'sort_order' => 'integer'];

    /** The trades, in the order the page shows them: key => [English, Kurdish, Arabic]. */
    public const CATEGORIES = [
        'stone' => ['Stone & marble', 'بەرد و مەڕمەڕ', 'الحجر والرخام'],
        'furniture' => ['Furniture', 'مۆبیلیا', 'الأثاث'],
        'lighting' => ['Lighting', 'ڕووناکی', 'الإضاءة'],
        'kitchen-bath' => ['Kitchens & bathrooms', 'چێشتخانە و گەرماو', 'المطابخ والحمّامات'],
        'glass' => ['Glass, doors & windows', 'شووشە، دەرگا و پەنجەرە', 'الزجاج والأبواب والنوافذ'],
        'finishes' => ['Finishes & surfaces', 'ڕووکار و تەواوکاری', 'التشطيبات والأسطح'],
        'materials' => ['Building materials', 'کەرەستەی بیناسازی', 'مواد البناء'],
        'tech' => ['Smart home & technology', 'خانووی زیرەک و تەکنەلۆژیا', 'المنزل الذكي والتقنية'],
        'contractors' => ['Contractors & craftspeople', 'بەڵێندەر و وەستا', 'المقاولون والحِرفيون'],
        'other' => ['Other', 'هیتر', 'أخرى'],
    ];

    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('name');
    }

    /** key => English name, for the admin's select. */
    public static function categoryOptions(): array
    {
        return array_map(fn ($names) => $names[0], self::CATEGORIES);
    }

    /** A trade's name in a language (default: the page's). */
    public static function categoryName(string $key, ?string $lang = null): string
    {
        $names = self::CATEGORIES[$key] ?? self::CATEGORIES['other'];

        return match ($lang ?? site_lang()) { 'ku' => $names[1], 'ar' => $names[2], default => $names[0] };
    }

    public function description(?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->description_en, $this->description_ku, $this->description_ar);
    }

    public function logoUrl(): ?string
    {
        return Project::resolveImage($this->logo);
    }

    /** The partner's website, as a full address (https:// added when left out). */
    public function websiteUrl(): ?string
    {
        $url = trim((string) $this->website);
        if ($url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
    }

    /** Whether there is anything to show — the page's links are hidden until there is. */
    public static function anyPublished(): bool
    {
        return rescue(fn () => static::query()->where('is_published', true)->exists(), false, false);
    }
}
