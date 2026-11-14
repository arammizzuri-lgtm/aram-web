<?php

namespace App\Http\Controllers;

use App\Mail\VisitRequested;
use App\Models\VisitRequest;
use App\Support\StudioMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

use function Illuminate\Support\defer;

/**
 * /visit — ask for a studio visit, a site visit or a phone call, on the day
 * and at the time that suits. No slots: it waits in the admin for the studio
 * to confirm it or suggest another, on WhatsApp. The studio is e-mailed.
 */
class VisitController extends Controller
{
    public function show(): Response
    {
        return response()->view('visit');
    }

    public function store(Request $request): RedirectResponse
    {
        $back = redirect()->to(site_url('/visit'));
        // the off-screen field only bots fill in: answer as if it worked, keep nothing
        if (filled($request->input('website'))) {
            return $back->with('visit_sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[\d\s()\-.]{6,}$/'],
            'kind' => ['required', Rule::in(array_keys(VisitRequest::KINDS))],
            'about' => ['required', 'string', 'max:2000'],
            'day' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Baghdad')->toDateString()],
            'time' => ['required', 'date_format:H:i'],
        ]);

        $visit = VisitRequest::create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'kind' => $data['kind'],
            'about' => trim($data['about']),
            'wanted_on' => $data['day'],
            'wanted_time' => $data['time'],
            'lang' => site_lang(),
            'ip_address' => $request->ip(),
        ]);
        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send(new VisitRequested($visit)));
        }

        return $back->with('visit_sent', true);
    }
}
