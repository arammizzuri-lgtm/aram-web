<?php

namespace App\Http\Controllers;

use App\Models\Landmark;

/**
 * Kurdistan's architectural heritage — /kurdistan-heritage (and /ku, /ar):
 * the landmarks of Greater Kurdistan on one map, drawn with the same border
 * as the homepage map, and a page for each landmark.
 */
class HeritageController extends Controller
{
    public function index()
    {
        return view('heritage', ['landmarks' => Landmark::publishedOrdered()->get()->values()]);
    }

    public function show(string $slug)
    {
        $all = Landmark::publishedOrdered()->get()->values();
        $i = $all->search(fn ($l) => $l->slug === $slug);
        abort_if($i === false, 404);

        return view('landmark', [
            'landmark' => $all[$i],
            'prev' => $all[$i - 1] ?? null,
            'next' => $all[$i + 1] ?? null,
        ]);
    }
}
