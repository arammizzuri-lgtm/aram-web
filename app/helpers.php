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

if (! function_exists('bival')) {
    /**
     * The English value of a bilingual string (its initial on-screen text
     * before the language toggle runs). Echo raw with {!! !!} — values may
     * carry intended <em>/<br>. Resolves via SiteText (settings → default).
     */
    function bival(string $key): string
    {
        return SiteText::en($key);
    }
}

if (! function_exists('bitext')) {
    /**
     * The data-en / data-ku attribute pair the language toggle reads, both
     * HTML-escaped so the values are safe inside the attribute (the browser
     * decodes entities back, so intended <em>/<br> still render on swap).
     * Echo with {!! !!}.
     */
    function bitext(string $key): string
    {
        return 'data-en="'.e(SiteText::en($key)).'" data-ku="'.e(SiteText::ku($key)).'"';
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
