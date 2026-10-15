<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Every project on one page — /projects (and /ku/projects): reached from the
 * "Projects" heading on the homepage, where the same work sits in a single
 * sliding row. Filtered by category (?type=residential, so a filtered view
 * can be linked to) and searched in the browser (projects.js); without
 * JavaScript the category links still work, here on the server.
 */
class ProjectsController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::publishedOrdered()->get()->filter(fn (Project $p) => $p->hasPage())->values();

        // only the categories there is something to show for, each with its count
        $counts = $projects->flatMap(fn (Project $p) => $p->categoryKeys())->countBy();
        $categories = Category::ordered()->get()->filter(fn (Category $c) => $counts->has($c->key))->values();

        $type = (string) $request->query('type', '');
        if (! $categories->contains('key', $type)) {
            $type = '';
        }

        return view('projects', compact('projects', 'categories', 'counts', 'type'));
    }
}
