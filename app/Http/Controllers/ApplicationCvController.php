<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * An applicant's CV, for the admin only: registered inside the panel's
 * authenticated routes (AdminPanelProvider), so a signed-in studio account
 * is the only way to it. Opens in the browser; ?download saves it instead.
 */
class ApplicationCvController extends Controller
{
    public function __invoke(Request $request, JobApplication $application): Response
    {
        abort_unless($application->cvExists(), 404);

        $disposition = $request->has('download') ? 'attachment' : 'inline';

        return Storage::disk(JobApplication::DISK)->response($application->cv_path, $application->cv_name ?: 'cv.pdf', [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }
}
