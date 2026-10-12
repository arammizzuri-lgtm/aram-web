<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Mail\NewApplication;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Support\StudioMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Illuminate\Support\defer;

/**
 * /careers (and /ku/careers): the positions the studio is hiring for, and
 * the form to apply — to one of them, or with an open application.
 */
class CareersController extends Controller
{
    /** Largest CV accepted, in kilobytes (8 MB). */
    public const MAX_CV_KB = 8192;

    public function index()
    {
        return view('careers', [
            'openings' => JobOpening::listed()->get(),
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        // the same spam trap as the enquiry form
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'position' => ['nullable', Rule::exists('job_openings', 'id')->where('is_open', true)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[\d\s()\-.]{6,}$/'],
            'portfolio' => ['nullable', 'url', 'max:500'],
            'message' => ['nullable', 'string', 'max:5000'],
            // checked by content, not just the name: a renamed .exe is refused,
            // and the file must open the way every PDF does, with "%PDF-"
            'cv' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.self::MAX_CV_KB,
                function (string $attribute, $file, \Closure $fail) {
                    $head = $file->isValid() ? (string) @file_get_contents($file->getRealPath(), false, null, 0, 5) : '';
                    if ($head !== '%PDF-') {
                        $fail('The CV must be a PDF file.');
                    }
                }],
            'lang' => ['nullable', Rule::in(['en', 'ku'])],
        ]);

        $file = $request->file('cv');
        // a name of our own on disk; the applicant's file name kept for downloads
        $path = $file->storeAs('cvs', Str::uuid().'.pdf', JobApplication::DISK);

        $application = JobApplication::create([
            'job_opening_id' => $data['position'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'portfolio_url' => $data['portfolio'] ?? null,
            'message' => $data['message'] ?? null,
            'cv_path' => $path,
            'cv_name' => Str::limit(preg_replace('/[^\pL\pN ._()-]+/u', '_', $file->getClientOriginalName()), 180, ''),
            'cv_size' => $file->getSize(),
            'lang' => $data['lang'] ?? null,
            'source' => $request->session()->get(TrackPageView::SESSION_KEY),
            'ip_address' => $request->ip(),
        ]);

        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send(new NewApplication($application->load('opening'))));
        }

        return response()->json(['ok' => true]);
    }
}
