<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Models\ProjectUpdate;
use Illuminate\Http\Request;

/**
 * The studio's journal — /journal (and /ku, /ar): construction updates
 * published as posts, each at /journal/{slug} with the rest of its build
 * beside it (written once, in the project's progress in the admin), and the
 * essays, written in the admin (App\Models\Essay). Newest first.
 */
class JournalController extends Controller
{
    public function index()
    {
        return view('journal', ['entries' => self::entries()]);
    }

    /** The same entries as an Atom feed, in the page's language: /journal/feed, /ku/journal/feed, /ar/journal/feed. */
    public function feed()
    {
        return response()->view('journal-feed', ['entries' => self::entries()->take(30)])
            ->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }

    /**
     * The build entries and the essays, as one list, newest first: each ['date' => Carbon, and 'post' or 'essay'].
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private static function entries()
    {
        return ProjectUpdate::inJournal()->with('project')->orderByDesc('date')->orderByDesc('id')->get()
            ->map(fn ($p) => ['date' => $p->date, 'post' => $p])
            ->concat(Essay::published()->get()->map(fn ($e) => ['date' => $e->date, 'essay' => $e]))
            ->sortByDesc(fn ($e) => $e['date']->getTimestamp())->values();
    }

    public function show(Request $request, string $slug)
    {
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
