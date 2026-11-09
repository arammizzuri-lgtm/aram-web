@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Partner> $partners */
    /** @var \Illuminate\Support\Collection<int, string> $trades */
    use App\Models\Partner;
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('partners_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('partners_intro'), 160);
    $together = fn (Partner $p) => $p->projects->filter(fn ($pr) => $pr->hasPage())->values();
    // the showrooms map: only partners with a pin, and only when there is one
    $tiles = carto_tiles('dark_all');
    $mapPins = $tiles ? $partners->filter(fn ($p) => $p->lat !== null)->map(fn ($p) => [
        'id' => $p->anchor(), 'lat' => $p->lat, 'lng' => $p->lng, 'name' => $p->name,
        'trade' => Partner::categoryName($p->category), 'address' => $p->address(), 'logo' => $p->logoUrl(),
        'dir' => 'https://www.google.com/maps/dir/?api=1&destination='.$p->lat.','.$p->lng,
    ])->values() : collect();
    $schema = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => bival('partners_label'), 'url' => site_url('/partners'),
        'itemListElement' => $partners->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1,
            'item' => array_filter([
                '@type' => 'Organization', 'name' => $p->name, 'url' => $p->websiteUrl(), 'description' => $p->description('en'),
                'logo' => $p->logoUrl() ? url($p->logoUrl()) : null, 'telephone' => $p->phone,
                'address' => filled($p->address_en) ? ['@type' => 'PostalAddress', 'streetAddress' => $p->address_en] : null,
            ])])->all()];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => '/partners', 'css' => 'partners.css'])
</head>
<body class="pp pp--partners">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <header class="pt-hero">
            <p class="pp-label">{{ bival('partners_label') }}</p>
            <h1 class="pt-hero__title">{{ bival('partners_title') }}</h1>
            <p class="pt-hero__intro">{{ bival('partners_intro') }}</p>
        </header>

        @if ($partners->isEmpty())
            <p class="pt-empty">{{ bival('partners_empty') }}</p>
        @else
            @if ($mapPins->isNotEmpty())
                {{-- Logos or the showrooms on a map, in the same place (partners.js) --}}
                <div class="pt-switch" role="tablist">
                    <button type="button" class="pt-switch__btn is-on" role="tab" aria-selected="true" data-view="wall">{{ bival('partners_tab_logos') }}</button>
                    <button type="button" class="pt-switch__btn" role="tab" aria-selected="false" data-view="map">{{ bival('partners_tab_map') }}</button>
                </div>
            @endif

            <div class="pt-view" data-view-pane="wall">
            @if ($trades->count() > 1)
                {{-- one trade at a time (partners.js) --}}
                <nav class="pt-filters">
                    <button type="button" class="pt-chip is-on" data-trade="">{{ bival('landmarks_all') }}</button>
                    @foreach ($trades as $key)
                        <button type="button" class="pt-chip" data-trade="{{ $key }}">{{ Partner::categoryName($key) }}</button>
                    @endforeach
                </nav>
            @endif

            {{-- The logo wall: white one-colour logos, gold on hover; each opens
                 its partner in a dialog (partners.js), or by its #partner-N link. --}}
            <ul class="pt-wall">
                @foreach ($partners as $p)
                    <li class="pt-tile" data-trade="{{ $p->category }}">
                        <button type="button" class="pt-tile__btn" data-open="{{ $p->anchor() }}" aria-haspopup="dialog">
                            @if ($p->monoUrl())
                                <span class="pt-tile__logo" style="--logo: url('{{ $p->monoUrl() }}')" role="img" aria-label="{{ $p->name }}"></span>
                            @else
                                <span class="pt-tile__word" dir="auto">{{ $p->name }}</span>
                            @endif
                            <span class="pt-tile__name"><bdi>{{ $p->name }}</bdi></span>
                            <span class="pt-tile__trade">{{ Partner::categoryName($p->category) }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            </div>

            @if ($mapPins->isNotEmpty())
                <div class="pt-view pt-view--map" data-view-pane="map" hidden>
                    <div class="pt-map" id="ptMap" data-tiles="{{ $tiles }}" data-pins='@json($mapPins)'
                         data-leaflet-css="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}"
                         data-leaflet-js="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}"
                         data-directions="{{ bival('partners_directions') }}" data-view="{{ bival('partners_view') }}"></div>
                </div>
            @endif

            {{-- one dialog per partner, opened from its tile or its #partner-N link --}}
            <div class="pt-details">
                @foreach ($partners as $p)
                    @php $projects = $together($p); @endphp
                    <dialog class="pt-dlg" id="{{ $p->anchor() }}" data-partner="{{ $p->id }}" aria-labelledby="{{ $p->anchor() }}-name">
                        <div class="pt-dlg__panel">
                            <button type="button" class="pt-dlg__close" data-close aria-label="{{ bival('partners_close') }}">×</button>
                            <div class="pt-dlg__head">
                                @if ($p->logoUrl())
                                    <span class="pt-dlg__logo"><img src="{{ $p->logoUrl() }}" alt="" loading="lazy" decoding="async"></span>
                                @endif
                                <div>
                                    <p class="pt-dlg__trade">{{ Partner::categoryName($p->category) }}</p>
                                    <h2 class="pt-dlg__name" id="{{ $p->anchor() }}-name"><bdi>{{ $p->name }}</bdi></h2>
                                </div>
                            </div>
                            @if (filled($p->description()))
                                <p class="pt-dlg__desc" dir="auto">{{ $p->description() }}</p>
                            @endif

                            {{-- their words about working with the studio — only when written --}}
                            @if (filled($p->quote()))
                                <figure class="pt-quote">
                                    <blockquote dir="auto">{{ $p->quote() }}</blockquote>
                                    @if (filled($p->quote_by))
                                        <figcaption><bdi>{{ $p->quote_by }}</bdi>@if (filled($p->quoteRole()))<span dir="auto">{{ $p->quoteRole() }}</span>@endif</figcaption>
                                    @endif
                                </figure>
                            @endif

                            @if ($projects->isNotEmpty())
                                <h3 class="pt-dlg__h">{{ bival('partners_together') }}</h3>
                                <ul class="pt-dlg__projects">
                                    @foreach ($projects as $pr)
                                        <li>
                                            <a href="{{ $pr->pageUrl($lang) }}">
                                                <span class="pt-dlg__thumb">@if ($pr->coverThumbUrl())<img src="{{ $pr->coverThumbUrl() }}" alt="" loading="lazy" decoding="async">@endif</span>
                                                <span class="pt-dlg__pname" dir="auto">{{ $pr->tr('name') }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($p->telUrl() || $p->whatsappUrl() || $p->websiteUrl() || filled($p->address()) || $p->mapUrl())
                                <div class="pt-dlg__contact">
                                    @if (filled($p->address()))
                                        <p class="pt-dlg__addr" dir="auto">{{ $p->address() }}</p>
                                    @endif
                                    <div class="pt-dlg__actions">
                                        @if ($p->telUrl())
                                            <a class="pt-act" href="{{ $p->telUrl() }}" data-track="call"><span>{{ bival('partners_call') }}</span> <bdi dir="ltr">{{ $p->phone }}</bdi></a>
                                        @endif
                                        @if ($p->whatsappUrl())
                                            <a class="pt-act" href="{{ $p->whatsappUrl() }}" target="_blank" rel="noopener" data-track="whatsapp">{{ bival('partners_whatsapp') }}</a>
                                        @endif
                                        @if ($p->mapUrl())
                                            <a class="pt-act" href="{{ $p->mapUrl() }}" target="_blank" rel="noopener" data-track="map">{{ bival('partners_map') }} <span aria-hidden="true">↗</span></a>
                                        @endif
                                        @if ($p->websiteUrl())
                                            <a class="pt-act pt-act--gold" href="{{ $p->websiteUrl() }}" target="_blank" rel="noopener" data-track="website">{{ bival('partners_visit') }} <span aria-hidden="true">↗</span></a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </dialog>
                @endforeach
            </div>
        @endif

        {{-- Become a partner: the form opens under the question; an application
             arrives in the admin as a hidden partner, and the studio is emailed. --}}
        <section class="pt-join" id="become-a-partner">
            <h2 class="pt-join__title">{{ bival('partners_join') }}</h2>
            <details class="pt-apply">
                <summary class="pp-btn pp-btn--line">{{ bival('partners_join_btn') }} <span class="pp-arrow" aria-hidden="true">→</span></summary>
                <p class="pt-apply__intro">{{ bival('partners_apply_intro') }}</p>
                <form class="pt-form" id="ptApply" action="{{ url('/partners/apply') }}" method="post" enctype="multipart/form-data" novalidate
                      data-msg-sending="{{ bival('form_sending') }}" data-msg-check="{{ bival('form_check') }}"
                      data-msg-failed="{{ bival('form_failed') }}" data-msg-ok="{{ bival('partners_f_ok') }}"
                      data-msg-big="{{ bival('partners_f_big') }}" data-max-bytes="{{ \App\Http\Controllers\PartnersController::MAX_LOGO_KB * 1024 }}">
                    @csrf
                    <input type="hidden" name="lang" value="{{ $lang }}">
                    <div class="pt-field">
                        <label for="paCompany">{{ bival('partners_f_company') }}</label>
                        <input type="text" id="paCompany" name="company" required maxlength="120" autocomplete="organization">
                    </div>
                    <div class="pt-field">
                        <label for="paTrade">{{ bival('partners_f_trade') }}</label>
                        <select id="paTrade" name="trade" required>
                            @foreach (array_keys(Partner::CATEGORIES) as $key)
                                <option value="{{ $key }}">{{ Partner::categoryName($key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pt-field">
                        <label for="paPerson">{{ bival('partners_f_person') }}</label>
                        <input type="text" id="paPerson" name="contact_person" required maxlength="120" autocomplete="name">
                    </div>
                    <div class="pt-field">
                        <label for="paEmail">{{ bival('partners_f_email') }}</label>
                        <input type="email" id="paEmail" name="email" required maxlength="190" autocomplete="email" dir="ltr">
                    </div>
                    <div class="pt-field">
                        <label for="paPhone">{{ bival('partners_f_phone') }}</label>
                        <input type="tel" id="paPhone" name="phone" maxlength="40" autocomplete="tel" inputmode="tel" dir="ltr">
                        <label class="pt-check"><input type="checkbox" name="whatsapp" value="1"> <span>{{ bival('partners_f_whatsapp') }}</span></label>
                    </div>
                    <div class="pt-field">
                        <label for="paSite">{{ bival('partners_f_site') }}</label>
                        <input type="text" id="paSite" name="site" maxlength="255" placeholder="www." dir="ltr" autocomplete="url">
                    </div>
                    <div class="pt-field pt-field--full">
                        <label for="paLine">{{ bival('partners_f_line') }}</label>
                        <input type="text" id="paLine" name="description" maxlength="200">
                    </div>
                    <div class="pt-field pt-field--full">
                        <span class="pt-field__label">{{ bival('partners_f_logo') }}</span>
                        <label class="pt-file" for="paLogo">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/></svg>
                            <span class="pt-file__name" id="paLogoName" data-empty="{{ bival('partners_f_logo_choose') }}">{{ bival('partners_f_logo_choose') }}</span>
                        </label>
                        <input class="pt-file__input" type="file" id="paLogo" name="logo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="pt-field pt-field--full">
                        <label for="paMessage">{{ bival('partners_f_message') }}</label>
                        <textarea id="paMessage" name="message" rows="4" maxlength="2000"></textarea>
                    </div>
                    {{-- spam trap: off-screen, out of the tab order --}}
                    <div class="pt-hp" aria-hidden="true"><label for="paNick">Nickname</label><input type="text" id="paNick" name="nickname" tabindex="-1" autocomplete="off"></div>
                    <div class="pt-field--full pt-form__foot">
                        <button type="submit" class="pp-btn"><span class="pt-form__send">{{ bival('partners_f_send') }}</span> <span class="pp-arrow" aria-hidden="true">→</span></button>
                        <p class="pt-form__status" id="paStatus" role="status" aria-live="polite" hidden></p>
                    </div>
                </form>
            </details>
        </section>
    </main>

    @include('partials.page-foot')
    <script src="{{ asset('partners.js') }}?v={{ filemtime(public_path('partners.js')) }}" defer></script>
</body>
</html>
