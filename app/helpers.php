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
