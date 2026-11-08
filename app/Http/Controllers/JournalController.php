<?php

namespace App\Http\Controllers;

use App\Models\ProjectUpdate;

/**
 * The studio's build journal — /journal (and /ku, /ar): construction updates
 * published as posts, newest first, each at /journal/{slug} with the rest of
 * its build beside it. Written once, in the project's progress (admin).
 */
class JournalController extends Controller
{
    public function index()
    {
        return view('journal', [
            'posts' => ProjectUpdate::inJournal()->with('project')->orderByDesc('date')->orderByDesc('id')->get(),
        ]);
    }

    public function show(string $slug)
    {
        $post = ProjectUpdate::inJournal()->with('project')->where('slug', $slug)->firstOrFail();

        // the same build's other entries, in the order they happened
        $build = ProjectUpdate::inJournal()->where('project_id', $post->project_id)
            ->orderBy('date')->orderBy('id')->get();

        return view('journal-post', compact('post', 'build'));
    }
}
