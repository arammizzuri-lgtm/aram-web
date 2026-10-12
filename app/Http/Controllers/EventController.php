<?php

namespace App\Http\Controllers;

use App\Http\Middleware\TrackPageView;
use App\Models\PageView;
use App\Models\Project;
use App\Models\SiteEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Receives the small anonymous pings the pages send with navigator.sendBeacon
 * — a project opened in the overlay, a WhatsApp button pressed — so the
 * dashboard can count what page views alone cannot see.
 *
 * Always answers 204 and never fails loudly: nothing a visitor does may be
 * held up by the counting of it.
 */
class EventController extends Controller
{
    public function store(Request $request): Response
    {
        if (PageView::isBot($request)) {
            return response()->noContent();
        }

        $data = validator($request->all(), [
            'type' => ['required', Rule::in([SiteEvent::PROJECT_OPEN, SiteEvent::WHATSAPP_CLICK])],
            'project' => ['nullable', 'string', 'max:191'],
            'placement' => ['nullable', Rule::in(SiteEvent::PLACEMENTS)],
        ]);
        if ($data->fails()) {
            return response()->noContent();
        }
        $data = $data->validated();

        SiteEvent::create([
            'type' => $data['type'],
            'project_id' => filled($data['project'] ?? null)
                ? Project::query()->where('slug', $data['project'])->value('id')
                : null,
            'placement' => $data['placement'] ?? null,
            'source' => $request->hasSession() ? $request->session()->get(TrackPageView::SESSION_KEY, 'direct') : 'direct',
            'visitor_hash' => PageView::visitorHash($request),
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
