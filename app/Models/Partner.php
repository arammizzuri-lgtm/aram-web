<?php

namespace App\Models;

use App\Support\LogoMono;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A partner or supplier the studio builds with (/partners): a brand or firm,
 * its trade, a line in three languages, a logo (and the one-colour version the
 * logo wall shows), how to reach it, and the projects done together.
 */
class Partner extends Model
{
    protected $fillable = [
        'name', 'category', 'description_en', 'description_ku', 'description_ar', 'logo', 'logo_mono', 'website',
        'phone', 'whatsapp', 'address_en', 'address_ku', 'address_ar', 'lat', 'lng', 'sort_order', 'is_published',
        'quote_en', 'quote_ku', 'quote_ar', 'quote_by', 'quote_role_en', 'quote_role_ku', 'quote_role_ar',
        'is_application', 'contact_person', 'email', 'message', 'lang', 'applied_at',
    ];

    protected $casts = ['is_published' => 'boolean', 'is_application' => 'boolean', 'applied_at' => 'datetime', 'whatsapp' => 'boolean', 'sort_order' => 'integer', 'lat' => 'float', 'lng' => 'float'];

    /** The trades, in the order the filters show them: key => [English, Kurdish, Arabic]. */
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

    protected static function booted(): void
    {
        // an application stops being one the moment it is shown on the website
        static::saving(function (Partner $partner) {
            if ($partner->is_published && $partner->is_application) {
                $partner->is_application = false;
            }
        });

        // the one-colour logo, rebuilt whenever the logo changes (as for clients)
        static::saved(function (Partner $partner) {
            $touched = $partner->wasChanged('logo') || ($partner->wasRecentlyCreated && $partner->logo);
            if (! $touched) {
                return;
            }
            if ($partner->logo) {
                $partner->regenerateMono();
            } elseif ($partner->logo_mono) {
                $partner->logo_mono = null;
                $partner->saveQuietly();
            }
        });
    }

    public function regenerateMono(): void
    {
        $disk = Storage::disk('public');
        if (! $this->logo || preg_match('#^(https?:)?//#', $this->logo)) {
            return;
        }
        $rel = 'partners/mono/'.pathinfo($this->logo, PATHINFO_FILENAME).'.png';
        if (LogoMono::generate($disk->path($this->logo), $disk->path($rel))) {
            $this->logo_mono = $rel;
            $this->saveQuietly();
        }
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PartnerEvent::class);
    }

    /** The partner's words about working with the studio, in a language. */
    public function quote(?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->quote_en, $this->quote_ku, $this->quote_ar);
    }

    public function quoteRole(?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->quote_role_en, $this->quote_role_ku, $this->quote_role_ar);
    }

    /** The address their badge links to: counted, then on to their panel. */
    public function badgeUrl(): string
    {
        return url('/p/'.$this->id);
    }

    /**
     * The snippet a partner pastes on their own website: their badge,
     * linking to their panel here. Plain HTML, nothing loads but the image.
     */
    public function badgeCode(string $style = 'dark'): string
    {
        $img = url('/badges/partner-'.($style === 'light' ? 'light' : 'dark').'.svg');

        return '<a href="'.$this->badgeUrl().'" target="_blank" rel="noopener" title="Official partner of Aram Mizuri Architecture">'
            .'<img src="'.$img.'" width="232" height="64" alt="Official partner of Aram Mizuri Architecture" style="border:0" loading="lazy">'
            .'</a>';
    }

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

    public function address(?string $lang = null): ?string
    {
        return bi_record($lang ?? site_lang(), $this->address_en, $this->address_ku, $this->address_ar);
    }

    public function logoUrl(): ?string
    {
        return Project::resolveImage($this->logo);
    }

    /** The one-colour logo for the wall (none: the wall writes the name instead). */
    public function monoUrl(): ?string
    {
        return $this->logo_mono ? Project::resolveImage($this->logo_mono) : null;
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

    /** tel: and wa.me links, from the phone number as written. */
    public function telUrl(): ?string
    {
        return filled($this->phone) ? 'tel:'.preg_replace('/[^\d+]/', '', str_replace('(0)', '', $this->phone)) : null;
    }

    public function whatsappUrl(): ?string
    {
        return $this->whatsapp && filled($this->phone) ? 'https://wa.me/'.phone_digits($this->phone) : null;
    }

    public function mapUrl(): ?string
    {
        return $this->lat !== null ? 'https://www.google.com/maps/search/?api=1&query='.$this->lat.','.$this->lng : null;
    }

    /** The anchor on /partners that opens this partner. */
    public function anchor(): string
    {
        return 'partner-'.$this->id;
    }

    /** Whether there is anything to show — the page's links are hidden until there is. */
    public static function anyPublished(): bool
    {
        return rescue(fn () => static::query()->where('is_published', true)->exists(), false, false);
    }
}
