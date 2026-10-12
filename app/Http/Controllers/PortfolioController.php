<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Status;
use App\Models\Testimonial;
use App\Support\SiteText;

/**
 * Serves the public portfolio page, now powered by the database. Builds the
 * window.__SITE__ payload (projects + editable content) consumed by script.js.
 */
class PortfolioController extends Controller
{
    public function index()
    {
        $projects = Project::publishedOrdered()->withCount('updates')->get();
        $clients = Client::publishedOrdered()->get();
        $quotes = Testimonial::publishedOrdered()->with(['client', 'project'])->get();
        $categories = Category::ordered()->get();
        // never allKeyed(): this goes to the browser in window.__SITE__
        $content = SiteSetting::publicKeyed();
        $stats = $this->portfolioStats($projects);

        $payload = [
            'projects' => $projects->map(fn (Project $p) => [
                'num' => $p->num,
                // the overlay puts /projects/{slug} in the address bar
                'slug' => $p->hasPage() ? $p->slug : null,
                'name' => $p->name,
                'name_ku' => $p->name_ku,
                'category' => $p->category,          // primary — kept for the map card
                'categories' => $p->categoryKeys(),  // every category, primary first
                'category_labels' => $p->categoryLabels(),
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
                // Three index-aligned lists of the same photos: `imgs` is the
                // untouched original (zoom, full-resolution view, download),
                // `imgs_lg` the display-sized hero and `imgs_sm` the strip
                // thumbnail. Sending originals for previews is what used to make
                // opening a project download tens of megabytes.
                'imgs' => $p->map_only ? [] : $p->orderedImageUrls(),
                'imgs_lg' => $p->map_only ? [] : $p->orderedVariantUrls('lg'),
                'imgs_sm' => $p->map_only ? [] : $p->orderedVariantUrls('sm'),
                'map_only' => (bool) $p->map_only,
                // what the project page has beyond photos — the overlay links to each
                'extras' => [
                    'film' => $p->mediaItems() !== [],
                    'compare' => filled($p->compare_before) && filled($p->compare_after),
                    'progress' => $p->updates_count > 0,
                ],
            ])->values()->all(),
            'content' => $content,
            'stats' => $stats,
            // name => tone, so the map overlay can colour any status' dot.
            'statusTones' => Status::ordered()->pluck('tone', 'name')->all(),
            // Waiting/progress copy in both languages. Everything else on the
            // page carries its own data-en/data-ku pair, but these strings have
            // no markup to live in until something is actually slow.
            'ui' => SiteText::jsStrings(),
            // The page title in both languages, for the language toggle and
            // for the overlay, which titles the tab after the open project.
            'titles' => ['en' => SiteText::en('seo_title'), 'ku' => SiteText::ku('seo_title')],
            // The overlay's WhatsApp button opens a chat that names the open
            // project. Null while no number is set, which hides the button.
            'whatsapp' => ($wa = phone_digits(setting('contact_whatsapp'))) ? [
                'number' => $wa,
                'project' => ['en' => SiteText::en('wa_text_project'), 'ku' => SiteText::ku('wa_text_project')],
            ] : null,
            // CARTO basemap key, appended to every tile URL (see cartoTiles in
            // script.js). It is a public, domain-restricted key by design.
            'cartoKey' => config('services.carto.basemaps_key'),
        ];

        return view('portfolio', compact('projects', 'clients', 'quotes', 'categories', 'content', 'payload', 'stats'));
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
