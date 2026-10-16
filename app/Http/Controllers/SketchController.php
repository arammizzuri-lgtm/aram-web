<?php

namespace App\Http\Controllers;

use App\Models\Sketch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sketches visitors draw for the studio (sketch.js).
 *
 * store() takes the drawing as a PNG, checks it is one — a picture of a
 * sensible size — and keeps it only as GD draws it again, so nothing but
 * the picture itself is kept. show() gives it back to whoever has its
 * address: the studio, from the enquiry or the WhatsApp message it came in.
 */
class SketchController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:3072']]);

        $bytes = (string) file_get_contents($request->file('image')->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $fits = $info && $info[2] === IMAGETYPE_PNG
            && $info[0] >= 200 && $info[1] >= 150 && $info[0] <= 2400 && $info[1] <= 2400;
        $image = $fits ? @imagecreatefromstring($bytes) : false;
        if (! $image) {
            return response()->json(['message' => 'Not a drawing.'], 422);
        }

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        $sketch = Sketch::keep($png);
        Sketch::prune();

        return response()->json(['token' => $sketch->token, 'url' => $sketch->url()]);
    }

    public function show(string $token): Response
    {
        $sketch = Sketch::query()->where('token', $token)->firstOrFail();
        abort_unless(Storage::disk(Sketch::DISK)->exists($sketch->path), 404);

        return Storage::disk(Sketch::DISK)->response($sketch->path, 'sketch.png', [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
