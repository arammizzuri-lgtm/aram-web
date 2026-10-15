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
     * The language this page is rendered in: 'ku' for everything under /ku
     * (set by the SiteLanguage middleware), 'en' everywhere else.
     */
    function site_lang(): string
    {
        return app()->getLocale() === 'ku' ? 'ku' : 'en';
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

        return url(($lang ?? site_lang()) === 'ku' ? rtrim('/ku'.$path, '/') : $path);
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
        if (($lang ?? site_lang()) !== 'ku') {
            return $date->format($monthOnly ? 'F Y' : 'j F Y');
        }

        $months = ['کانوونی دووەم', 'شوبات', 'ئازار', 'نیسان', 'ئایار', 'حوزەیران',
            'تەممووز', 'ئاب', 'ئەیلوول', 'تشرینی یەکەم', 'تشرینی دووەم', 'کانوونی یەکەم'];
        $digits = fn (string $s) => strtr($s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $text = $months[(int) $date->format('n') - 1].'ی '.$digits($date->format('Y'));

        return $monthOnly ? $text : $digits($date->format('j')).'ی '.$text;
    }
}

if (! function_exists('bi')) {
    /**
     * The current language's half of an English/Kurdish pair — for the values
     * that come from the database rather than SiteText. A blank Kurdish value
     * falls back to the English one, as the language toggle does.
     */
    function bi(?string $en, ?string $ku): string
    {
        return site_lang() === 'ku' && filled($ku) ? (string) $ku : (string) $en;
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
        return site_lang() === 'ku' ? SiteText::ku($key) : SiteText::en($key);
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
        return 'dir="auto" data-en="'.e(SiteText::en($key)).'" data-ku="'.e(SiteText::ku($key)).'"';
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
