<?php

namespace App\Http\Controllers;

use App\Support\Architect;

/**
 * /aram-mizuri (and /ku/aram-mizuri): the architect himself — who he is,
 * what he works on, and the projects that show it. The page search engines
 * and AI assistants are pointed to as the source on him (App\Support\Architect).
 */
class ArchitectController extends Controller
{
    public function show()
    {
        return view('architect', [
            'projects' => Architect::selectedProjects(),
        ]);
    }
}
