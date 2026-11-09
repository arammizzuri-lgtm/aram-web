<?php

namespace App\Http\Controllers;

use App\Mail\PartnerApplicationMail;
use App\Models\PageView;
use App\Models\Partner;
use App\Models\PartnerEvent;
use App\Support\StudioMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Illuminate\Support\defer;

/**
 * Partners & suppliers — /partners (and /ku, /ar): the firms and brands the
 * studio builds with, as a logo wall (or their showrooms on a map); each
 * opens to its line, its words about the studio, the projects done together
 * and how to reach it. Also: the anonymous counting of what visitors do
 * with a partner, the badge link partners put on their own websites, and
 * the "Become a partner" applications.
 */
class PartnersController extends Controller
{
    /** Largest logo accepted with an application, in kilobytes (4 MB). */
    public const MAX_LOGO_KB = 4096;

    public function index()
    {
        $partners = Partner::publishedOrdered()->with('projects')->get();

        // the trades that have a partner, in the order they are listed
        $trades = collect(array_keys(Partner::CATEGORIES))
            ->filter(fn ($key) => $partners->contains('category', $key))->values();

        return view('partners', ['partners' => $partners, 'trades' => $trades]);
    }

    /**
     * A ping from the page: a partner opened, called, WhatsApp'd, its
     * website or directions followed. Never counts robots or the studio's
     * own visits (signed in to the admin). Always answers 204.
     */
    public function track(Request $request): Response
    {
        if (PageView::isBot($request) || auth()->check()) {
            return response()->noContent();
        }
        $type = (string) $request->input('type');
        $id = (int) $request->input('partner');
        if ($type !== 'badge' && isset(PartnerEvent::TYPES[$type])
            && Partner::query()->whereKey($id)->where('is_published', true)->exists()) {
            PartnerEvent::record($id, $type, PageView::visitorHash($request));
        }

        return response()->noContent();
    }

    /** /p/{id} — where a partner's badge links: counted, then on to its panel. */
    public function badge(Request $request, int $partner): RedirectResponse
    {
        $p = Partner::query()->whereKey($partner)->where('is_published', true)->first();
        if (! $p) {
            return redirect(site_url('/partners', 'en'));
        }
        if (! PageView::isBot($request) && ! auth()->check()) {
            rescue(fn () => PartnerEvent::record($p->id, 'badge', PageView::visitorHash($request)), null, false);
        }

        return redirect(site_url('/partners', 'en').'#'.$p->anchor());
    }

    /**
     * "Become a partner": the application arrives as a hidden partner marked
     * as an application (its logo made one-colour like any other), and the
     * studio is told by email. Switching it on in the admin publishes it.
     */
    public function apply(Request $request): JsonResponse
    {
        // the same spam trap as the other forms (a field people never see)
        if (filled($request->input('nickname'))) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'company' => ['required', 'string', 'max:120'],
            'trade' => ['required', Rule::in(array_keys(Partner::CATEGORIES))],
            'contact_person' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[\d\s()\-.]{6,}$/'],
            'whatsapp' => ['nullable', 'boolean'],
            'site' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:2000'],
            // pictures only — no SVG from the public (it can carry script)
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_LOGO_KB],
            'lang' => ['nullable', Rule::in(['en', 'ku', 'ar'])],
        ]);
        $lang = $data['lang'] ?? 'en';

        $logo = $request->hasFile('logo')
            ? $request->file('logo')->storeAs('partners', 'apply-'.Str::uuid().'.'.$request->file('logo')->extension(), 'public')
            : null;

        $partner = Partner::create([
            'name' => $data['company'],
            'category' => $data['trade'],
            'description_'.$lang => $data['description'] ?? null,
            'website' => $data['site'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => (bool) ($data['whatsapp'] ?? false),
            'contact_person' => $data['contact_person'],
            'email' => $data['email'],
            'message' => $data['message'] ?? null,
            'lang' => $lang,
            'logo' => $logo,
            'is_published' => false,
            'is_application' => true,
            'applied_at' => now(),
            'sort_order' => (int) Partner::max('sort_order') + 1,
        ]);

        if (StudioMail::enabled()) {
            defer(fn () => StudioMail::send(new PartnerApplicationMail($partner)));
        }

        return response()->json(['ok' => true]);
    }
}
