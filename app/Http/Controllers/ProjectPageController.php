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

        // Its materials, each a way into the others built with it: a search
        // on /projects — only where that finds more than this project alone.
        $texts = $projects->map(fn (Project $p) => $p->searchText());
        $materials = collect((array) $project->materials)->filter(fn ($m) => filled($m))->map(function (string $m) use ($texts) {
            $words = Project::searchWords($m);
            $found = $texts->filter(fn (string $t) => collect($words)->every(fn ($w) => str_contains($t, $w)))->count();

            return ['name' => $m, 'href' => $found > 1 ? site_url('/projects').'?'.http_build_query(['q' => $m]) : null, 'count' => $found];
        })->values();

        // Each photograph's room, as a way to every other photograph of that
        // room (/photos) — where there is more than this one to see.
        $lang = site_lang();
        $roomCounts = $projects->flatMap(fn (Project $p) => array_values(array_filter(array_map(fn ($img) => $p->roomOf($img), $p->orderedImages()))))->countBy();
        $rooms = array_map(function ($img) use ($project, $roomCounts, $lang) {
            $room = $project->roomOf($img);

            return $room && ($roomCounts[$room] ?? 0) > 1 ? [
                'label' => str_replace(['{room}', '{n}'], [\App\Support\Rooms::label($room, $lang), $roomCounts[$room]], bival('pp_room_more')),
                'url' => site_url('/photos').'?room='.$room,
            ] : null;
        }, $project->orderedImages());

        // what clients said about this one, in the language of the page
        $quotes = $project->testimonials()->publishedOrdered()->inLanguage(site_lang())->with('client')->get();

        return view('project', [
            'project' => $project,
            'quotes' => $quotes,
            'updates' => $project->publicUpdates()->get(),
            'media' => $project->mediaItems(),
            'compare' => $project->compareUrls(),
            'materials' => $materials,
            'notes' => $project->notesByImage($lang),
            'rooms' => $rooms,
            'voice' => $project->voice($lang),
            'prev' => $prev,
            'next' => $next,
            'more' => $more,
            'position' => $index + 1,
            'count' => $count,
        ]);
    }
}
