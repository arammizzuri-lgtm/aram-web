<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Every project on one page — /projects (and /ku/projects): the studio's work
 * as a set of drawings. Two ways to look at it: the Index, a register of the
 * projects year by year, and the Sheets, each project framed as a drawing
 * sheet with its title block. The categories are a legend, each with the
 * hatch it is drawn in.
 *
 * Filtered by category (?type=residential) and shown in either view
 * (?view=sheets) from the address, so either can be linked to and both work
 * without JavaScript; projects.js answers them at once, and adds the search.
 */
class ProjectsController extends Controller
{
    /** How many hatch patterns projects.css has: categories take them in turn. */
    private const HATCHES = 8;

    public function index(Request $request)
    {
        $projects = Project::publishedOrdered()->get()->filter(fn (Project $p) => $p->hasPage())->values();

        // only the categories there is something to show for, each with its count
        $counts = $projects->flatMap(fn (Project $p) => $p->categoryKeys())->countBy();
        $categories = Category::ordered()->get()->filter(fn (Category $c) => $counts->has($c->key))->values();
        $hatch = $categories->pluck('key')->flip()->map(fn ($i) => $i % self::HATCHES);

        $type = (string) $request->query('type', '');
        if (! $categories->contains('key', $type)) {
            $type = '';
        }
        $view = $request->query('view') === 'sheets' ? 'sheets' : 'index';

        // the register runs from the newest year back; undated work last
        $byYear = $projects->groupBy(fn (Project $p) => preg_match('/^\d{4}$/', $p->endYear()) ? $p->endYear() : '')
            ->sortKeysUsing(fn ($a, $b) => $a === '' ? 1 : ($b === '' ? -1 : strcmp($b, $a)));
        $years = $byYear->keys()->filter()->values();

        return view('projects', compact('projects', 'categories', 'counts', 'hatch', 'type', 'view', 'byYear', 'years'));
    }
}
