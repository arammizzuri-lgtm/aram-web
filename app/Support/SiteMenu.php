<?php

namespace App\Support;

use App\Models\Landmark;
use App\Models\Partner;

/**
 * The site's menu: an index of sheets, as in a set of drawings
 * (partials/site-index). Every page's bar carries only the studio's name,
 * the Index button, the languages and Contact; the Index opens the whole
 * site — A-00 Home to A-10 Contact — and, as a sheet of its own, H-01
 * Kurdistan's architectural heritage with a landmark that changes daily.
 */
class SiteMenu
{
    /**
     * The sheets, numbered in order (A-00, A-01, …): a sheet that is switched
     * off (the estimator, partners not shown yet) takes no number.
     *
     * @param  bool  $markHere  whether to mark the page the visitor is on (not on a page that was not found)
     * @return array<int, array{code: string, key: string, label: string, href: string, here: bool}>
     */
    public static function items(bool $markHere = true): array
    {
        $route = $markHere ? preg_replace('/^(ku|ar)\./', '', request()->route()?->getName() ?? '') : '';
        $here = fn (string ...$names) => in_array($route, $names, true);
        $home = fn (string $section) => site_url('/').'#'.$section;

        $sheets = array_values(array_filter([
            ['home', bival('careers_home'), site_url('/'), $here('home')],
            ['projects', bival('nav_projects'), site_url('/projects'), $here('projects', 'project')],
            ['map', bival('nav_map'), $home('map'), false],
            // About is the architect's own page, not the homepage's section
            ['about', bival('nav_about'), site_url(Architect::PATH), $here('architect')],
            ['services', bival('nav_services'), $home('services'), false],
            ['process', bival('nav_process'), $home('process'), false],
            CostEstimate::enabled() ? ['estimate', bival('est_nav'), site_url('/estimate'), $here('estimate')] : null,
            ['journal', bival('journal_nav'), site_url('/journal'), $here('journal', 'journal.post')],
            Partner::anyPublished() ? ['partners', bival('partners_nav'), site_url('/partners'), $here('partners')] : null,
            ['careers', bival('careers_link'), site_url('/careers'), $here('careers')],
            ['contact', bival('nav_contact'), $home('contact'), false],
        ]));

        return array_map(fn ($s, $i) => [
            'code' => 'A-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'key' => $s[0], 'label' => $s[1], 'href' => $s[2], 'here' => $s[3],
        ], $sheets, array_keys($sheets));
    }

    /**
     * Sheet H-01, Kurdistan's architectural heritage: its figures and the
     * landmark of the day — one of those with a photograph, a different one
     * each day of the year. Null when no landmark is published (or the
     * database cannot be read: the menu never fails a page).
     *
     * @return array{href: string, here: bool, count: int, unesco: int, years: ?string,
     *               today: ?array{name: string, place: ?string, href: string, img: ?string}}|null
     */
    public static function heritage(bool $markHere = true): ?array
    {
        $landmarks = rescue(fn () => Landmark::publishedOrdered()
            ->get(['id', 'slug', 'sort_order', 'name_en', 'name_ku', 'name_ar', 'place_en', 'place_ku', 'place_ar', 'image', 'unesco', 'year_from']),
            collect(), false);
        if ($landmarks->isEmpty()) {
            return null;
        }
        $route = $markHere ? preg_replace('/^(ku|ar)\./', '', request()->route()?->getName() ?? '') : '';

        // years of building, as the collection page counts them: from the
        // oldest landmark built, not a cave lived in before anyone built
        $oldestBuilt = $landmarks->whereNotNull('year_from')->where('year_from', '>=', -12000)->min('year_from');
        $years = $oldestBuilt !== null ? Landmark::digits(number_format(floor(((int) date('Y') - $oldestBuilt) / 1000) * 1000)).'+' : null;

        $pictured = $landmarks->filter(fn ($l) => filled($l->image))->values();
        $today = $pictured->isNotEmpty() ? $pictured[(int) date('z') % $pictured->count()] : null;

        return [
            'href' => site_url(Landmark::PATH),
            'here' => in_array($route, ['heritage', 'landmark'], true),
            'count' => $landmarks->count(),
            'unesco' => $landmarks->where('unesco', true)->count(),
            'years' => $years,
            'today' => $today ? [
                'name' => (string) $today->tr('name'),
                'place' => $today->tr('place'),
                'href' => $today->url(),
                'img' => $today->imageUrl('md'),
            ] : null,
        ];
    }
}
