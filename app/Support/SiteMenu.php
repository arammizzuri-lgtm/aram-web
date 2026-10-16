<?php

namespace App\Support;

/**
 * The site's menu, for the bar across the top of the standalone pages
 * (partials/page-top): the homepage's own menu — every project, its
 * sections, the estimator — so a visitor reading a project can get to any
 * of it without going home first. Home and Careers join it in the folded
 * menu on a tablet or phone, where there is room for them.
 */
class SiteMenu
{
    /**
     * @param  bool  $markHere  whether to mark the page the visitor is on (not on a page that was not found)
     * @return array<int, array{label: string, href: string, here: bool, only_in_sheet: bool}>
     */
    public static function items(bool $markHere = true): array
    {
        $route = $markHere ? (request()->route()?->getName() ?? '') : '';
        $here = fn (string ...$names) => in_array(preg_replace('/^ku\./', '', $route), $names, true);
        $home = fn (string $section) => site_url('/').'#'.$section;
        $item = fn (string $label, string $href, bool $here = false, bool $onlyInSheet = false) => [
            'label' => $label, 'href' => $href, 'here' => $here, 'only_in_sheet' => $onlyInSheet,
        ];

        return array_values(array_filter([
            $item(bival('careers_home'), site_url('/'), onlyInSheet: true),
            $item(bival('nav_projects'), site_url('/projects'), $here('projects', 'project')),
            $item(bival('nav_map'), $home('map')),
            $item(bival('nav_about'), $home('about')),
            $item(bival('nav_services'), $home('services')),
            $item(bival('nav_process'), $home('process')),
            $item(bival('nav_contact'), $home('contact')),
            CostEstimate::enabled() ? $item(bival('est_nav'), site_url('/estimate'), $here('estimate')) : null,
            $item(bival('careers_link'), site_url('/careers'), $here('careers'), onlyInSheet: true),
        ]));
    }
}
