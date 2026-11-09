<?php

namespace App\Http\Controllers;

use App\Mail\ReviewReceivedMail;
use App\Models\ReviewRequest;
use App\Models\Testimonial;
use App\Support\StudioMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * /review/{token} — a client's private page, from a review link: their words
 * about the studio, and — if they like — their voice, recorded right there or
 * uploaded, and a photo for the pencil portrait. What they send arrives as a
 * hidden client quote, for the studio to check and publish. Never indexed.
 */
class ReviewController extends Controller
{
    public const MAX_VOICE_KB = 10240;

    public const MAX_PHOTO_KB = 12288;

    /** The voice note's file name ending, from what the browser says it is. */
    private const AUDIO = [
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/m4a' => 'm4a', 'audio/aac' => 'm4a', 'audio/x-aac' => 'm4a', 'video/mp4' => 'm4a',
        'audio/webm' => 'webm', 'video/webm' => 'webm',
        'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/opus' => 'opus',
        'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav',
    ];

    public function show(string $token)
    {
        $review = ReviewRequest::query()->where('token', $token)->with('project')->first();
        abort_if(! $review, 404);

        return response()->view('review', ['review' => $review])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $review = ReviewRequest::query()->where('token', $token)->first();
        if (! $review || $review->isUsed() || $review->isExpired()) {
            return response()->json(['ok' => false, 'closed' => true], 410);
        }

        $data = $request->validate([
            'quote' => ['required', 'string', 'min:10', 'max:1500'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:160'],
            'voice' => ['nullable', 'file', 'max:'.self::MAX_VOICE_KB, 'mimetypes:'.implode(',', array_keys(self::AUDIO))],
            'photo' => ['nullable', 'file', 'max:'.self::MAX_PHOTO_KB, 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp'],
            'consent' => ['accepted'],
        ]);
        $lang = $review->lang;

        // a recording from the page, or a voice note they chose
        $voice = null;
        if ($request->hasFile('voice')) {
            $file = $request->file('voice');
            $ext = self::AUDIO[$file->getMimeType()] ?? null;
            if (! $ext) {
                throw ValidationException::withMessages(['voice' => 'That file is not a voice recording.']);
            }
            $voice = $file->storeAs('testimonials/voices', Str::uuid().'.'.$ext, 'public');
        }
        // the photo goes to the private disk, like one added in the admin:
        // only the pencil drawing made from it is ever shown
        $photo = $request->hasFile('photo')
            ? $request->file('photo')->storeAs('testimonials/photos', Str::uuid().'.'.$request->file('photo')->extension(), Testimonial::PHOTO_DISK)
            : null;

        $testimonial = Testimonial::create(array_filter([
            'quote_'.$lang => trim($data['quote']),
            'person_name' => trim($data['name']),
            'person_name_'.$lang => $lang !== 'en' ? trim($data['name']) : null,
            'person_role_'.$lang => filled($data['role'] ?? null) ? trim($data['role']) : null,
            'project_id' => $review->project_id,
            'client_id' => $review->client_id,
            'voice' => $voice,
            'portrait' => $photo,
            'media_consent' => true,
            'is_published' => false,
            'from_client' => true,
            'submitted_at' => now(),
            'sort_order' => (int) Testimonial::max('sort_order') + 1,
        ], fn ($v) => $v !== null));

        $review->update(['used_at' => now(), 'testimonial_id' => $testimonial->id]);

        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send(new ReviewReceivedMail($testimonial, $review)));
        }

        return response()->json(['ok' => true]);
    }
}
