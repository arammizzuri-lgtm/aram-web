<?php

namespace App\Http\Controllers;

use App\Models\Landmark;
use Illuminate\Http\Request;

/**
 * Kurdistan's architectural heritage — /kurdistan-heritage (and /ku, /ar):
 * the landmarks of Greater Kurdistan on one map, drawn with the same border
 * as the homepage map, and an editorial page for each landmark.
 */
class HeritageController extends Controller
{
    public function index()
    {
        return view('heritage', ['landmarks' => Landmark::publishedOrdered()->get()->values()]);
    }

    /**
     * A landmark's page. An unpublished landmark is shown only through the
     * admin's signed preview link (Landmark::previewUrl), never indexed.
     */
    public function show(Request $request, string $slug)
    {
        $all = Landmark::publishedOrdered()->get()->values();
        $i = $all->search(fn ($l) => $l->slug === $slug);

        if ($i === false) {
            $draft = Landmark::where('slug', $slug)->first();
            abort_unless($draft && $request->hasValidSignature(), 404);

            return response()->view('landmark', ['landmark' => $draft, 'prev' => null, 'next' => null, 'preview' => true])
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return view('landmark', [
            'landmark' => $all[$i],
            'prev' => $all[$i - 1] ?? null,
            'next' => $all[$i + 1] ?? null,
            'preview' => false,
        ]);
    }
}
