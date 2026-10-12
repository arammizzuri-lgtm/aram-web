<?php

namespace App\Http\Controllers;

use App\Models\Project;

/**
 * A project's own page — /projects/{slug}, and /ku/projects/{slug} in Kurdish.
 *
 * On the homepage a project opens in the overlay, and the address bar follows
 * it there; this is what that address shows when it is opened directly: from
 * a shared link, a search result, or a reload. It carries everything the
 * overlay does, server-rendered, so the link preview and Google both see the
 * project itself rather than the homepage.
 */
class ProjectPageController extends Controller
{
    public function show(string $slug)
    {
        $projects = Project::publishedOrdered()->get()->filter->hasPage()->values();
        $index = $projects->search(fn (Project $p) => $p->slug === $slug);
        abort_if($index === false, 404);

        $project = $projects[$index];
        $count = $projects->count();

        // Neighbours in grid order, wrapping round, as the overlay's arrows do.
        $prev = $count > 1 ? $projects[($index - 1 + $count) % $count] : null;
        $next = $count > 1 ? $projects[($index + 1) % $count] : null;

        // "More projects": the ones that share a category first, then the
        // rest of the grid, never the neighbours already linked above.
        $others = $projects->reject(fn (Project $p) => in_array($p->id, [$project->id, $prev?->id, $next?->id], true));
        $keys = $project->categoryKeys();
        $more = $others
            ->sortByDesc(fn (Project $p) => count(array_intersect($keys, $p->categoryKeys())))
            ->take(3)->values();

        // what clients said about this one, in the language of the page
        $quotes = $project->testimonials()->publishedOrdered()->inLanguage(site_lang())->with('client')->get();

        return view('project', [
            'project' => $project,
            'quotes' => $quotes,
            'prev' => $prev,
            'next' => $next,
            'more' => $more,
            'position' => $index + 1,
            'count' => $count,
        ]);
    }
}
