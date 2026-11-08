<?php

namespace App\Support;

use App\Models\Project;

/**
 * Aram Mizuri, the person — as opposed to the studio that carries his name.
 *
 * Search engines and AI assistants decide who someone is from one page they
 * trust, tied to everything else by a single identifier. This is that
 * identifier (self::id()), the page (/aram-mizuri) and the Person they read
 * there, built from the "Architect page" tab of Site Content so every place
 * that names him — his page, the studio, each project, llms.txt — says the
 * same thing. Only what is written there is published: an empty list is left
 * out, never filled in.
 */
class Architect
{
    public const PATH = '/aram-mizuri';

    /** The one identifier everything about him points at, the same in both languages. */
    public static function id(): string
    {
        return url(self::PATH).'#person';
    }

    /** The items of a one-per-line field, in English or the page's language. */
    public static function lines(string $key, ?string $lang = null): array
    {
        $text = match ($lang ?? site_lang()) {
            'ku' => SiteText::ku($key) ?: SiteText::en($key),
            'ar' => SiteText::ar($key),
            default => SiteText::en($key),
        };

        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text))));
    }

    /**
     * A one-per-line field whose lines are fields themselves, split on "|":
     * the career ("when | role | where | what happened") and the key figures
     * ("number | what it counts"). Missing fields come back as ''.
     *
     * @return array<int, array<int, string>>
     */
    public static function rows(string $key, int $fields, ?string $lang = null): array
    {
        return array_map(
            fn ($line) => array_pad(array_map('trim', explode('|', $line, $fields)), $fields, ''),
            self::lines($key, $lang),
        );
    }

    /**
     * His biography as paragraphs: his own, once written; until then, the
     * two paragraphs of the homepage's About section.
     *
     * @return array<int, string>
     */
    public static function bio(?string $lang = null): array
    {
        $lang ??= site_lang();
        $pick = fn (string $key) => match ($lang) {
            'ku' => SiteText::ku($key) ?: SiteText::en($key),
            'ar' => SiteText::ar($key),
            default => SiteText::en($key),
        };

        $own = trim((string) $pick('arch_bio'));
        if ($own !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $own))));
        }

        return array_values(array_filter([trim($pick('about_bio_1')), trim($pick('about_bio_2'))]));
    }

    /** His portrait, as set for the About section — null until there is one. */
    public static function portrait(): ?string
    {
        $src = Project::resolveImage(setting('about_portrait_img'));

        return filled($src) ? url($src) : null;
    }

    /**
     * Lighter copies of the portrait, for srcset: WebP files saved beside
     * the original under the same name — "{name}-800.webp" and "{name}.webp"
     * (made from public/images/aram-portrait-sq.jpg). A portrait without
     * them is simply served as it is.
     *
     * @return string a srcset value, or '' when there are none
     */
    public static function portraitSrcset(): string
    {
        $src = Project::resolveImage(setting('about_portrait_img'));
        $path = ltrim((string) parse_url((string) $src, PHP_URL_PATH), '/');
        if ($path === '' || ! preg_match('/\.(jpe?g|png)$/i', $path) || ! is_file(public_path($path))) {
            return '';
        }

        $base = preg_replace('/\.(jpe?g|png)$/i', '', $path);
        $set = [];
        foreach ([$base.'-800.webp', $base.'.webp'] as $file) {
            if (is_file(public_path($file)) && ($size = @getimagesize(public_path($file)))) {
                $set[] = asset($file).' '.$size[0].'w';
            }
        }

        return implode(', ', $set);
    }

    /** Web addresses that are his: his own profiles, then the studio's. */
    public static function profiles(): array
    {
        $own = array_filter(self::lines('arch_profiles', 'en'), fn ($u) => preg_match('#^https?://\S+$#i', $u));
        $studio = collect(linked_socials())->pluck('url')->map(fn ($u) => trim((string) $u))
            ->filter(fn ($u) => preg_match('#^https?://#i', $u))->all();

        return array_values(array_unique([...$own, ...$studio]));
    }

    /** A line of "what — where, when": the organisation, for alumniOf. */
    private static function organisation(string $line): string
    {
        $parts = preg_split('/\s+[—–-]\s+/u', $line);
        $org = trim((string) end($parts));

        return trim(preg_replace('/,?\s*\d{4}(\s*[–-]\s*\d{4})?\s*$/u', '', $org)) ?: $line;
    }

    /**
     * The short reference the studio and the projects carry: enough to name
     * him, and the identifier that joins it to the full Person on his page.
     */
    public static function reference(): array
    {
        return ['@type' => 'Person', '@id' => self::id(), 'name' => SiteText::en('arch_name'), 'url' => site_url(self::PATH, 'en')];
    }

    /**
     * The full Person, as his page publishes it (always in English, the
     * language search engines index him under; his Kurdish name rides along).
     */
    public static function person(): array
    {
        $name = SiteText::en('arch_name');
        $parts = preg_split('/\s+/u', trim($name));
        $education = self::lines('arch_education', 'en');
        // a line naming a qualification ("Degree — where, when") is a credential too
        $degrees = array_values(array_filter($education, fn ($l) => preg_match('/\s[—–-]\s/u', $l)));
        $studio = ['@type' => 'ProfessionalService', '@id' => url('/').'#studio', 'name' => 'Aram Mizuri Architecture', 'url' => site_url('/', 'en')];
        $firms = array_map(fn ($f) => ['@type' => 'Organization', 'name' => $f], self::lines('arch_firms', 'en'));

        return array_filter([
            '@type' => 'Person',
            '@id' => self::id(),
            'name' => $name,
            'givenName' => count($parts) > 1 ? $parts[0] : null,
            'familyName' => count($parts) > 1 ? end($parts) : null,
            'alternateName' => array_values(array_unique(array_filter([SiteText::ku('arch_name'), ...self::lines('arch_other_names', 'en')]))) ?: null,
            'jobTitle' => 'Architect',
            'description' => trim(strip_tags(SiteText::en('arch_seo_description'))) ?: null,
            'url' => site_url(self::PATH, 'en'),
            'image' => self::portrait(),
            'worksFor' => $firms ? [$studio, ...$firms] : $studio,
            'workLocation' => ['@type' => 'Place', 'name' => 'Erbil, Kurdistan Region, Iraq', 'address' => [
                '@type' => 'PostalAddress', 'addressLocality' => 'Erbil', 'addressRegion' => 'Kurdistan Region', 'addressCountry' => 'IQ',
            ]],
            'knowsAbout' => self::lines('arch_expertise', 'en') ?: null,
            'alumniOf' => $education ? array_map(fn ($l) => ['@type' => 'EducationalOrganization', 'name' => self::organisation($l)], $education) : null,
            'hasCredential' => $degrees ? array_map(fn ($l) => ['@type' => 'EducationalOccupationalCredential', 'name' => $l, 'credentialCategory' => 'degree'], $degrees) : null,
            'award' => self::lines('arch_awards', 'en') ?: null,
            'sameAs' => self::profiles() ?: null,
        ]);
    }

    /**
     * The projects his page leads with: the large ones first, then the rest
     * in the studio's own order — every one with a page to link to.
     */
    public static function selectedProjects(int $limit = 6)
    {
        return Project::publishedOrdered()->get()->filter->hasPage()
            ->sortBy(fn (Project $p, int $i) => [$p->size === 'large' ? 0 : 1, $i])
            ->take($limit)->values();
    }
}
