<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Status;

/**
 * Serves the public portfolio page, now powered by the database. Builds the
 * window.__SITE__ payload (projects + editable content) consumed by script.js.
 */
class PortfolioController extends Controller
{
    public function index()
    {
        $projects = Project::publishedOrdered()->get();
        $clients = Client::publishedOrdered()->get();
        $categories = Category::ordered()->get();
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
                'neighbourhood' => $p->neighbourhood,
                'city' => $p->city,
                'country' => $p->country,
                'lat' => $p->lat,
                'lng' => $p->lng,
                'year' => $p->year,
                'desc' => $p->desc,
                'desc_ku' => $p->desc_ku,
                'narrative' => $p->narrative,
                'materials' => $p->materials ?? [],
                'related' => $p->related ?? [],
                'imgs' => $p->map_only ? [] : $p->orderedImageUrls(),
                'map_only' => (bool) $p->map_only,
            ])->values()->all(),
            'content' => $content,
            'stats' => $stats,
            // name => tone, so the map overlay can colour any status' dot.
            'statusTones' => Status::ordered()->pluck('tone', 'name')->all(),
        ];

        return view('portfolio', compact('projects', 'clients', 'categories', 'content', 'payload', 'stats'));
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
        // City = the structured city field (falls back to the location string).
        $city = fn (Project $p) => $p->cityLabel();

        // Country = the structured country field, with the Kurdistan Region
        // counted under Iraq so the tally stays a count of sovereign states.
        $countryMap = ['Kurdistan Region' => 'Iraq'];
        $country = function (Project $p) use ($countryMap) {
            $c = $p->country ?: trim((string) (array_reverse(explode(',', (string) $p->location))[0] ?? ''));

            return $countryMap[$c] ?? $c;
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
