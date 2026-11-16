<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Models\ProjectUpdate;
use App\Support\Articles;
use Illuminate\Http\Request;

/**
 * The studio's journal — /journal (and /ku, /ar): construction updates
 * published as posts, each at /journal/{slug} with the rest of its build
 * beside it (written once, in the project's progress in the admin), and the
 * essays: those in App\Support\Articles, each a view of its own, and those
 * written in the admin (App\Models\Essay). Newest first.
 */
class JournalController extends Controller
{
    public function index()
    {
        return view('journal', [
            'posts' => ProjectUpdate::inJournal()->with('project')->orderByDesc('date')->orderByDesc('id')->get(),
            'articles' => collect(Articles::all()),
            'essays' => Essay::published()->get(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        if ($article = Articles::find($slug)) {
            return view($article['view'], compact('article'));
        }

        // an essay from the admin; a draft only through its signed preview link, never indexed
        if ($essay = Essay::where('slug', $slug)->first()) {
            if ($essay->is_published) {
                return view('journal.essay', ['essay' => $essay, 'preview' => false]);
            }
            abort_unless($request->hasValidSignature(), 404);

            return response()->view('journal.essay', ['essay' => $essay, 'preview' => true])->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $post = ProjectUpdate::inJournal()->with('project')->where('slug', $slug)->firstOrFail();

        // the same build's other entries, in the order they happened
        $build = ProjectUpdate::inJournal()->where('project_id', $post->project_id)
            ->orderBy('date')->orderBy('id')->get();

        return view('journal-post', compact('post', 'build'));
    }
}
