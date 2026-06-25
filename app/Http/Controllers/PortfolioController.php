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

        $payload = [
            'projects' => $projects->map(fn (Project $p) => [
                'num' => $p->num,
                'name' => $p->name,
                'category' => $p->category,
                'status' => $p->status,
                'area' => $p->area,
                'typology' => $p->typology,
                'location' => $p->location,
                'year' => $p->year,
                'desc' => $p->desc,
                'narrative' => $p->narrative,
                'materials' => $p->materials ?? [],
                'related' => $p->related ?? [],
                'imgs' => $p->imageUrls(),
            ])->values()->all(),
            'content' => $content,
        ];

        return view('portfolio', compact('projects', 'content', 'payload'));
    }
}
