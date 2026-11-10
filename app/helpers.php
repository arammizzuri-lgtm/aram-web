<?php

use App\Models\SiteSetting;
use App\Support\SiteText;

if (! function_exists('setting')) {
    /**
     * Read an editable site-content value by key, with a fallback default.
     * Backed by SiteSetting's per-request cache, so it is cheap to call often.
     */
    function setting(string $key, string $default = ''): string
    {
        $value = SiteSetting::get($key, $default);

        return $value === null ? $default : (string) $value;
    }
}

if (! function_exists('site_lang')) {
    /**
     * The language this page is rendered in: 'ku' for everything under /ku,
     * 'ar' under /ar (set by the SiteLanguage middleware), 'en' everywhere else.
     */
    function site_lang(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['ku', 'ar'], true) ? $locale : 'en';
    }
}

if (! function_exists('site_langs')) {
    /**
     * Every language the public site is in, with the name each is known by
     * in itself — for the language switcher.
     *
     * @return array<string, string>
     */
    function site_langs(): array
    {
        return ['en' => 'English', 'ku' => 'کوردی', 'ar' => 'العربية'];
    }
}

if (! function_exists('site_rtl')) {
    /** Whether a language (default: this page's) is written right to left. */
    function site_rtl(?string $lang = null): bool
    {
        return in_array($lang ?? site_lang(), ['ku', 'ar'], true);
    }
}

if (! function_exists('text_dir')) {
    /**
     * A dir attribute for a piece of text, only when its script runs the
     * other way from the page — an English fallback on a Kurdish page, an
     * Arabic name on an English one. Text with no letters (a year, "1685–1784",
     * a figure) and text in the page's own script get nothing and follow the
     * page. (dir="auto" made digit-only text left-to-right on Kurdish and
     * Arabic pages, which threw dates to the wrong side.) The script with
     * more letters wins, so a Kurdish sentence that opens with "UNESCO"
     * stays right-to-left.
     */
    function text_dir(?string $text, ?string $lang = null): \Illuminate\Support\HtmlString
    {
        $text = (string) $text;
        $rtl = preg_match_all('/(?=\p{L})[\p{Arabic}\p{Hebrew}]/u', $text);
        $ltr = preg_match_all('/\p{Latin}/u', $text);
        if (! $rtl && ! $ltr) {
            return new \Illuminate\Support\HtmlString('');
        }
        $isRtl = $rtl >= $ltr;

        return new \Illuminate\Support\HtmlString($isRtl === site_rtl($lang) ? '' : ' dir="'.($isRtl ? 'rtl' : 'ltr').'"');
    }
}

if (! function_exists('site_lang_urls')) {
    /**
     * This page's address in every language — the path the visitor is on,
     * under /, /ku or /ar, with its query string kept: a category, a page
     * number, a search. Only parameters that read as one (a plain name, and
     * a value of letters, digits, spaces, - _ . ,) are kept, so whatever else
     * is typed into the address (?type=<script>) is not repeated into the
     * language links and the hreflang tags.
     *
     * @return array<string, string>
     */
    function site_lang_urls(): array
    {
        $path = '/'.ltrim(request()->path(), '/');
        $path = preg_replace('#^/(ku|ar)(?=/|$)#', '', $path) ?: '/';
        $query = http_build_query(array_filter(
            request()->query(),
            fn ($v, $k) => is_string($v) && preg_match('/^[\w-]+$/', (string) $k) && preg_match('/^[\p{L}\p{N} _.,-]*$/u', $v),
            ARRAY_FILTER_USE_BOTH,
        ));

        return collect(array_keys(site_langs()))->mapWithKeys(fn ($l) => [
            $l => site_url($path, $l).($query !== '' ? '?'.$query : ''),
        ])->all();
    }
}

if (! function_exists('site_url')) {
    /**
     * Absolute URL of a public path in the given language (default: the
     * current one) — "/projects/x" becomes "/ku/projects/x" in Kurdish.
     */
    function site_url(string $path = '/', ?string $lang = null): string
    {
        $path = '/'.ltrim($path, '/');
        $lang ??= site_lang();

        return url(in_array($lang, ['ku', 'ar'], true) ? rtrim('/'.$lang.$path, '/') : $path);
    }
}

if (! function_exists('carto_tiles')) {
    /**
     * Leaflet URL template for a CARTO raster basemap style, carrying the
     * studio's key — or null without one, since keyless CARTO tiles are only
     * an "API KEY REQUIRED" watermark. The browser maps build theirs the same
     * way (cartoTiles in script.js).
     */
    function carto_tiles(string $style = 'dark_all'): ?string
    {
        $key = config('services.carto.basemaps_key');

        return $key
            ? 'https://{s}.basemaps.cartocdn.com/rastertiles/'.$style.'/{z}/{x}/{y}{r}.png?key='.rawurlencode($key)
            : null;
    }
}

if (! function_exists('phone_digits')) {
    /**
     * A phone number as wa.me and tel: links want it: international digits
     * only. "+964 (0) 750…", "00964 750…" and a local "0750…" all become
     * "964750…"; anything without digits is null.
     */
    function phone_digits(?string $phone): ?string
    {
        $raw = (string) $phone;
        // the "(0)" in "+964 (0) 750…" is the trunk prefix, dialled only at home
        $digits = preg_replace('/\D+/', '', str_replace('(0)', '', $raw));
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        if (str_starts_with($digits, '07') && strlen($digits) === 11) {
            return '964'.substr($digits, 1);
        }

        return $digits;
    }
}

if (! function_exists('whatsapp_url')) {
    /**
     * A wa.me link that opens a chat with the studio, its first message
     * already written — or null while no WhatsApp number is set (Site Content
     * → Contact), so every WhatsApp button simply stays hidden until then.
     */
    function whatsapp_url(?string $text = null): ?string
    {
        $digits = phone_digits(setting('contact_whatsapp'));

        return $digits ? 'https://wa.me/'.$digits.(filled($text) ? '?text='.rawurlencode($text) : '') : null;
    }
}

if (! function_exists('site_date')) {
    /**
     * A date as the page's reader writes it: "12 October 2026", or in
     * Kurdish "١٢ی تشرینی یەکەمی ٢٠٢٦" — Sorani month names and the
     * Eastern Arabic digits the rest of the Kurdish site uses.
     * $monthOnly gives "October 2026" / "تشرینی یەکەمی ٢٠٢٦".
     */
    function site_date(\DateTimeInterface $date, ?string $lang = null, bool $monthOnly = false): string
    {
        $lang ??= site_lang();
        if ($lang === 'en') {
            return $date->format($monthOnly ? 'F Y' : 'j F Y');
        }

        $digits = fn (string $s) => strtr($s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

        // Arabic as written in Iraq: the Syriac month names, "١٢ تشرين الأول ٢٠٢٦"
        if ($lang === 'ar') {
            $months = ['كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران',
                'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'];
            $text = $months[(int) $date->format('n') - 1].' '.$digits($date->format('Y'));

            return $monthOnly ? $text : $digits($date->format('j')).' '.$text;
        }

        $months = ['کانوونی دووەم', 'شوبات', 'ئازار', 'نیسان', 'ئایار', 'حوزەیران',
            'تەممووز', 'ئاب', 'ئەیلوول', 'تشرینی یەکەم', 'تشرینی دووەم', 'کانوونی یەکەم'];
        $text = $months[(int) $date->format('n') - 1].'ی '.$digits($date->format('Y'));

        return $monthOnly ? $text : $digits($date->format('j')).'ی '.$text;
    }
}

if (! function_exists('bi')) {
    /**
     * The current language's version of a value that comes from the database
     * rather than SiteText — English, Kurdish and (where the record has one)
     * Arabic. A blank translation falls back to the English, as the language
     * toggle does: an Arabic page shows English where no Arabic was written,
     * never the Kurdish.
     */
    function bi(?string $en, ?string $ku, ?string $ar = null): string
    {
        return match (site_lang()) {
            'ku' => filled($ku) ? (string) $ku : (string) $en,
            'ar' => filled($ar) ? (string) $ar : (string) $en,
            default => (string) $en,
        };
    }
}

if (! function_exists('bi_record')) {
    /**
     * A record's text in a given language (not necessarily the page's): the
     * Kurdish or the Arabic where it was written, the English otherwise.
     */
    function bi_record(string $lang, ?string $en, ?string $ku, ?string $ar = null): ?string
    {
        return match ($lang) {
            'ku' => filled($ku) ? $ku : $en,
            'ar' => filled($ar) ? $ar : $en,
            default => $en,
        };
    }
}

if (! function_exists('rtl_slot')) {
    /**
     * What the browser should hold in a record's "_ku" slot on this page.
     *
     * The scripts read a page's second language from its *_ku fields, its
     * data-ku attributes and its .ku strings, whenever the page is right to
     * left. On an Arabic page, that slot carries the Arabic (or, where none
     * was written, the English) — so every script works in Arabic unchanged.
     */
    function rtl_slot(?string $en, ?string $ku, ?string $ar = null): ?string
    {
        return site_lang() === 'ar' ? (filled($ar) ? $ar : $en) : $ku;
    }
}

if (! function_exists('slot')) {
    /**
     * The text to show from an English / second-slot pair (see rtl_slot):
     * the English on an English page, the second slot — Kurdish or Arabic —
     * on a right-to-left one, falling back to the English when it is blank.
     */
    function slot(?string $en, ?string $second): string
    {
        return site_lang() !== 'en' && filled($second) ? (string) $second : (string) $en;
    }
}

if (! function_exists('bival')) {
    /**
     * The value of a bilingual string in the page's language — its on-screen
     * text before the language toggle runs. Echo raw with {!! !!} — values may
     * carry intended <em>/<br>. Resolves via SiteText (settings → default).
     */
    function bival(string $key): string
    {
        return match (site_lang()) {
            'ku' => SiteText::ku($key),
            'ar' => SiteText::ar($key),
            default => SiteText::en($key),
        };
    }
}

if (! function_exists('bitext')) {
    /**
     * The data-en / data-ku attribute pair the language toggle reads, both
     * HTML-escaped so the values are safe inside the attribute (the browser
     * decodes entities back, so intended <em>/<br> still render on swap).
     * Echo with {!! !!}.
     *
     * dir="auto" comes along with them. The page direction is set once, on
     * <html>, but these elements change language underneath it, and some of
     * them hold Kurdish even while the page is in English — the studio's
     * Kurdish signature on the English hero, for one. Left to inherit, Kurdish
     * sitting in an English page is laid out left-to-right, and a string that
     * mixes the two has its halves reordered to match the page rather than the
     * author. "auto" asks the browser to take the direction from the text
     * itself, so each string reads the way it was written whichever language
     * is showing. It is a no-op for plain English.
     */
    function bitext(string $key): string
    {
        // on an Arabic page the "ku" half carries the Arabic (see rtl_slot)
        $second = site_lang() === 'ar' ? SiteText::ar($key) : SiteText::ku($key);

        return 'dir="auto" data-en="'.e(SiteText::en($key)).'" data-ku="'.e($second).'"';
    }
}

if (! function_exists('google_profile')) {
    /**
     * The studio's Google Business Profile, as set in the admin (Manage →
     * Site content → Google): its link, the "write a review" link, and the
     * rating and number of reviews copied from it. Null until the link is
     * set; rating and count are null until they are.
     *
     * @return array{url: string, review_url: ?string, rating: ?float, count: ?int}|null
     */
    function google_profile(): ?array
    {
        $url = trim((string) setting('google_profile_url'));
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $review = trim((string) setting('google_review_url'));
        $rating = (float) str_replace(',', '.', (string) setting('google_rating'));
        $count = (int) setting('google_review_count');

        return [
            'url' => $url,
            'review_url' => preg_match('#^https?://#i', $review) ? $review : null,
            'rating' => $rating >= 1 && $rating <= 5 ? round($rating, 1) : null,
            'count' => $count > 0 ? $count : null,
        ];
    }
}

if (! function_exists('socials')) {
    /**
     * Footer social links as an ordered list of ['label','url'] rows.
     * Reads the JSON 'footer_socials' setting; if unset, falls back to the
     * four legacy per-network URL settings so older installs keep working.
     *
     * @return array<int, array{label:string, url:string}>
     */
    function socials(): array
    {
        $raw = SiteSetting::get('footer_socials');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(function ($row) {
                    $label = trim((string) ($row['label'] ?? ''));
                    $url = trim((string) ($row['url'] ?? ''));

                    return $label === '' ? null : ['label' => $label, 'url' => $url];
                }, $decoded)));
            }
        }

        $legacy = [
            'Instagram' => 'footer_instagram_url',
            'LinkedIn'  => 'footer_linkedin_url',
            'Behance'   => 'footer_behance_url',
            'Archello'  => 'footer_archello_url',
        ];
        $out = [];
        foreach ($legacy as $label => $key) {
            $out[] = ['label' => $label, 'url' => (string) SiteSetting::get($key, '#')];
        }

        return $out;
    }
}

if (! function_exists('linked_socials')) {
    /**
     * The social links that lead somewhere: a network left without an
     * address (empty, or the "#" placeholder) is not shown at all — a link
     * that goes nowhere makes a studio look unfinished.
     *
     * @return array<int, array{label:string, url:string}>
     */
    function linked_socials(): array
    {
        return array_values(array_filter(socials(), fn ($s) => ! in_array(trim($s['url']), ['', '#'], true)));
    }
}

if (! function_exists('footer_copy')) {
    /**
     * The footer's copyright line as written in Site Content, with its year
     * kept current: "© 2022" is shown as "© 2022–2026" — the year the
     * studio began, and that it is still at work.
     */
    function footer_copy(): string
    {
        $now = (int) now()->year;

        return preg_replace_callback('/©\s*(\d{4})(?!\s*[–-]\s*\d{4})/u',
            fn ($m) => (int) $m[1] < $now ? '© '.$m[1].'–'.$now : $m[0],
            (string) setting('footer_copy'));
    }
}
