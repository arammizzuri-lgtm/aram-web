<?php

namespace App\Http\Controllers;

use App\Support\SavedProjects;
use Illuminate\Http\Request;

/**
 * A list of saved projects — /saved?p=slug,slug (and /ku/saved).
 *
 * The list itself lives in the visitor's browser (saved.js), so this page is
 * told what to show by its address. That makes it two pages in one: the
 * visitor's own shortlist, reached from "Saved" in the top bar, and the same
 * list as it arrives on someone else's phone when it is shared on WhatsApp.
 * The page tells the two apart in the browser (see the view).
 */
class SavedController extends Controller
{
    public function index(Request $request)
    {
        $requested = SavedProjects::slugs($request->query('p'));

        return view('saved', [
            'projects' => SavedProjects::projects($requested),
            'requested' => $requested,
            'asked' => $request->has('p'),
        ]);
    }
}
