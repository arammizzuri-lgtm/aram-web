<?php

namespace App\Http\Controllers;

use App\Models\Drawing;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A drawing's survey scan, for the tracer only: registered inside the panel's
 * authenticated routes (AdminPanelProvider), so a signed-in studio account is
 * the only way to it. The scans' licences allow no copy on the site.
 */
class DrawingScanController extends Controller
{
    public function __invoke(Drawing $drawing): Response
    {
        abort_unless($drawing->scanExists(), 404);
        $path = Storage::disk(Drawing::DISK)->path($drawing->scan);

        return response()->file($path, [
            'Content-Type' => File::mimeType($path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'max-age=86400',
        ])->setPrivate();
    }
}
