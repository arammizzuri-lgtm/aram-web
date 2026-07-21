<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\SiteSetting;

/**
 * Serves the public portfolio page, now powered by the database. Builds the
 * window.__SITE__ payload (projects + editable content) consumed by script.js.
 */
class PortfolioController extends Controller
{
    public function index()
    {
        $projects = Project::publishedOrdered()->get();
        $content = SiteSetting::allKeyed();
        $stats = $this->portfolioStats($projects);

        $payload = [
            'projects' => $projects->map(fn (Project $p) => [
                'num' => $p->num,
                'name' => $p->name,
                'name_ku' => $p->name_ku,
                'category' => $p->category,
                'status' => $p->status,
                'area' => $p->area,
                'typology' => $p->typology,
                'location' => $p->location,
                'lat' => $p->lat,
                'lng' => $p->lng,
                'year' => $p->year,
                'desc' => $p->desc,
                'desc_ku' => $p->desc_ku,
                'narrative' => $p->narrative,
                'materials' => $p->materials ?? [],
                'related' => $p->related ?? [],
                'imgs' => $p->imageUrls(),
            ])->values()->all(),
            'content' => $content,
            'stats' => $stats,
        ];

        return view('portfolio', compact('projects', 'content', 'payload', 'stats'));
    }

    /**
     * Single source of truth for the headline numbers shown on the hero sun,
     * the map stat bar, and the About section — all derived from the live
     * project data so they always agree.
     *
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     * @return array<string, mixed>
     */
    private function portfolioStats($projects): array
    {
        // City = first word of the location's first segment, so "Erbil Old City"
        // and "Erbil City Centre" collapse into one city ("Erbil").
        $city = fn (Project $p) => trim(explode(' ', $p->cityLabel())[0] ?? '');

        // Country = last location segment, with the Kurdistan Region mapped to Iraq.
        $countryMap = ['Kurdistan Region' => 'Iraq'];
        $country = function (Project $p) use ($countryMap) {
            $parts = explode(',', (string) $p->location);
            $seg = trim(end($parts) ?: '');

            return $countryMap[$seg] ?? $seg;
        };

        // Total area = sum of each project's numeric area (strip "m²", commas…).
        $area = (int) $projects->sum(fn (Project $p) => (int) preg_replace('/\D/', '', (string) $p->area));

        $short = $area >= 1_000_000
            ? rtrim(rtrim(number_format($area / 1_000_000, 1), '0'), '.').'M'
            : round($area / 1000).'K';

        return [
            'projects' => $projects->count(),
            'cities' => $projects->map($city)->filter()->unique()->count(),
            'countries' => $projects->map($country)->filter()->unique()->count(),
            'area' => $area,
            'area_short' => $short,
        ];
    }
}
