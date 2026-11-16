<?php

namespace App\Http\Controllers;

use App\Models\ProjectUpdate;
use App\Support\Articles;

/**
 * The studio's journal — /journal (and /ku, /ar): construction updates
 * published as posts, each at /journal/{slug} with the rest of its build
 * beside it (written once, in the project's progress in the admin), and the
 * essays in App\Support\Articles, each a view of its own. Newest first.
 */
class JournalController extends Controller
{
    public function index()
    {
        return view('journal', [
            'posts' => ProjectUpdate::inJournal()->with('project')->orderByDesc('date')->orderByDesc('id')->get(),
            'articles' => collect(Articles::all()),
        ]);
    }

    public function show(string $slug)
    {
        if ($article = Articles::find($slug)) {
            return view($article['view'], compact('article'));
        }

        $post = ProjectUpdate::inJournal()->with('project')->where('slug', $slug)->firstOrFail();

        // the same build's other entries, in the order they happened
        $build = ProjectUpdate::inJournal()->where('project_id', $post->project_id)
            ->orderBy('date')->orderBy('id')->get();

        return view('journal-post', compact('post', 'build'));
    }
}
