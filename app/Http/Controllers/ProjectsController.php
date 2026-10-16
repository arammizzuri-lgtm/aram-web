<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Every project on one page — /projects (and /ku/projects): the studio's work
 * as a set of drawings. Two ways to look at it: the Index, a register of the
 * projects year by year, and the Sheets, each project framed as a drawing
 * sheet with its title block. The categories are a legend, each with the
 * hatch it is drawn in; beside it, the statuses, each with its dot.
 *
 * Filtered by category (?type=residential), status (?status=completed) and
 * search (?q=stone) from the address, and shown in either view
 * (?view=sheets), so any of them can be linked to — from a service on the
 * homepage, a material on a project page, the page-not-found page — and all
 * work without JavaScript; projects.js answers them at once.
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

        // likewise the statuses, in the admin's order (any not listed there, after)
        $statusCounts = $projects->countBy(fn (Project $p) => $p->statusKey())->forget('');
        $order = Status::ordered()->pluck('name')->map(fn ($n) => Str::slug($n))->flip();
        $statuses = $projects->unique(fn (Project $p) => $p->statusKey())
            ->filter(fn (Project $p) => $p->statusKey() !== '')
            ->sortBy(fn (Project $p) => $order[$p->statusKey()] ?? PHP_INT_MAX)
            ->values();

        $type = (string) $request->query('type', '');
        if (! $categories->contains('key', $type)) {
            $type = '';
        }
        $status = (string) $request->query('status', '');
        if (! $statusCounts->has($status)) {
            $status = '';
        }
        $q = Str::limit(trim((string) $request->query('q', '')), 80, '');
        $view = $request->query('view') === 'sheets' ? 'sheets' : 'index';

        // the register runs from the newest year back; undated work last
        $byYear = $projects->groupBy(fn (Project $p) => preg_match('/^\d{4}$/', $p->endYear()) ? $p->endYear() : '')
            ->sortKeysUsing(fn ($a, $b) => $a === '' ? 1 : ($b === '' ? -1 : strcmp($b, $a)));
        $years = $byYear->keys()->filter()->values();

        return view('projects', compact(
            'projects', 'categories', 'counts', 'hatch', 'statuses', 'statusCounts',
            'type', 'status', 'q', 'view', 'byYear', 'years',
        ));
    }
}
