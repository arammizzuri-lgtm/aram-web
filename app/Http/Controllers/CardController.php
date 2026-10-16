<?php

namespace App\Http\Controllers;

use App\Models\Greeting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The holiday card — /card, and /ku/card: between an occasion's dates
 * (Manage → Holiday cards), a greeting card made with one of the studio's
 * projects and the visitor's own name, drawn in the browser (greeting.js)
 * and sent on WhatsApp. The name never leaves the visitor's phone.
 *
 * The studio can see any card ahead of its dates (?greeting=id), signed in.
 */
class CardController extends Controller
{
    public function show(Request $request): View
    {
        $greeting = $request->filled('greeting') && auth()->check()
            ? Greeting::find($request->integer('greeting'))
            : null;
        $greeting ??= Greeting::current();
        abort_unless($greeting, 404);

        return view('card', [
            'greeting' => $greeting,
            'photos' => $greeting->photos(),
            'preview' => ! $greeting->isOn(),
        ]);
    }
}
