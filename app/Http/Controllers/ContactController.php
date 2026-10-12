<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Mail\NewEnquiry;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Support\Enquiry;
use App\Support\StudioMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use function Illuminate\Support\defer;

/**
 * Stores enquiries from the public form so they appear in the admin, and
 * tells the studio by e-mail. Validated server-side and rate-limited at the
 * route to deter spam.
 */
class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // A field no person ever sees (it is off-screen and skipped by the
        // keyboard). Bots fill in every field they find: answer them as if it
        // worked, and keep nothing.
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // either one is enough — many clients would rather be called
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40', 'regex:/^\+?[\d\s()\-.]{6,}$/'],
            'type' => ['nullable', Rule::in(array_keys(Enquiry::types()))],
            'city' => ['nullable', 'string', 'max:80'],
            'plot_area' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'budget' => ['nullable', Rule::in(array_keys(Enquiry::budgets()))],
            'timeline' => ['nullable', Rule::in(array_keys(Enquiry::timelines()))],
            'about' => ['nullable', 'string', 'max:191'],      // slug of the project they were viewing
            'message' => ['nullable', 'string', 'max:5000'],
            'lang' => ['nullable', Rule::in(['en', 'ku'])],
            'page' => ['nullable', 'string', 'max:255'],
        ]);

        $enquiry = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'project_type' => $data['type'] ?? null,
            'city' => $data['city'] ?? null,
            'plot_area' => $data['plot_area'] ?? null,
            'budget' => $data['budget'] ?? null,
            'timeline' => $data['timeline'] ?? null,
            'project_id' => filled($data['about'] ?? null)
                ? Project::query()->where('slug', $data['about'])->value('id')
                : null,
            'page' => $data['page'] ?? null,
            'lang' => $data['lang'] ?? null,
            // where this visitor first came from (TrackPageView)
            'source' => $request->session()->get(TrackPageView::SESSION_KEY),
            'message' => $data['message'] ?? '',
            'ip_address' => $request->ip(),
        ]);

        // After the response has gone: the visitor sees "received" at once
        // instead of waiting on the mail server.
        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send(new NewEnquiry($enquiry->load('project'))));
        }

        return response()->json(['ok' => true]);
    }
}
