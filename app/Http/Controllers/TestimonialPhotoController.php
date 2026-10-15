<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A client's photo, for the admin's browser to draw their portrait from
 * (portrait-drawer.js): registered inside the panel's authenticated routes
 * (AdminPanelProvider). The photo is never published; only the drawing is.
 */
class TestimonialPhotoController extends Controller
{
    public function __invoke(Testimonial $testimonial): Response
    {
        $disk = Storage::disk(Testimonial::PHOTO_DISK);
        abort_unless($testimonial->portrait && $disk->exists($testimonial->portrait), 404);

        return $disk->response($testimonial->portrait, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
