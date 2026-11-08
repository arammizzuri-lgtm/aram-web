<?php

namespace App\Http\Controllers;

use App\Models\WalkStop;

/**
 * The Erbil architecture walk — /erbil-walk (and /ku, /ar): the studio's
 * guide to the city's buildings, stop by stop, on a map. Not a sales page:
 * a page worth sharing, that puts "architect" and "Erbil" beside the name.
 */
class WalkController extends Controller
{
    public function index()
    {
        $stops = WalkStop::publishedOrdered()->get()->values();

        // metres from the stop before, and the whole walk
        $legs = $stops->map(fn ($s, $i) => $i === 0 ? 0 : $stops[$i - 1]->metresTo($s))->all();

        return view('erbil-walk', [
            'stops' => $stops,
            'legs' => $legs,
            'total' => array_sum($legs),
        ]);
    }
}
