{{-- The site's menu as the index sheet of a set of drawings: every page as
     a numbered sheet (A-00 Home … A-10 Contact), and H-01, Kurdistan's
     architectural heritage, with a landmark that changes every day. A modal
     <dialog> (site-index.js): it keeps the keyboard inside while open, closes
     with Escape or Close, and gives focus back to the Index button. One
     sheet for every page — the homepage's bar and every other page's
     (partials/page-top) open the same.
     $sheets: SiteMenu::items(). $markHere: whether to mark the page the
     visitor is on. $langUrls: where the language links lead (default: this
     page in each language). --}}
@php
    $heritage = \App\Support\SiteMenu::heritage($markHere ?? true);
    $sxWa = whatsapp_url(bival('wa_text'));
    $sxBrand = trim(explode('|', bival('seo_title'))[0]);
    $sxDigits = fn ($n) => \App\Models\Landmark::digits($n);
@endphp
<dialog class="sx" id="siteIndex" aria-labelledby="sxTitle">
    <div class="sx__sheet">
        <header class="sx__head">
            <p class="sx__title" id="sxTitle">{{ bival('nav_index_title') }}</p>
            <span class="sx__brand">{{ $sxBrand }}</span>
            <button class="sx__close" type="button" data-index-close>
                <span>{{ bival('nav_close') }}</span>
                <svg viewBox="0 0 14 14" aria-hidden="true"><path d="M2 2l10 10M12 2 2 12"/></svg>
            </button>
        </header>

        <div class="sx__body">
            <nav class="sx__nav" aria-label="{{ bival('nav_menu') }}">
                <ol class="sx__list">
                    @foreach ($sheets as $i => $s)
                        <li class="sx__item" style="--i: {{ $i }}">
                            <a class="sx__link" href="{{ $s['href'] }}" data-sheet="{{ $s['key'] }}"@if ($s['here']) aria-current="page"@endif>
                                <span class="sx__code" dir="ltr">{{ $s['code'] }}</span>
                                <span class="sx__label">{{ $s['label'] }}</span>
                                @if ($s['here'])<span class="sx__here">{{ bival('nav_here') }}</span>@endif
                                <span class="sx__arrow" aria-hidden="true">→</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            @if ($heritage)
                {{-- H-01: the heritage collection, a sheet of its own --}}
                <section class="sx__h" style="--i: {{ count($sheets) }}" aria-labelledby="sxHeritage">
                    <a class="sx__h-head" href="{{ $heritage['href'] }}"@if ($heritage['here']) aria-current="page"@endif>
                        <span class="sx__code" dir="ltr">H-01</span>
                        <span class="sx__h-label" id="sxHeritage">{{ bival('landmarks_nav') }}</span>
                        <span class="sx__h-title">{{ bival('landmarks_title') }} <span class="sx__arrow" aria-hidden="true">→</span></span>
                        @if ($heritage['here'])<span class="sx__here">{{ bival('nav_here') }}</span>@endif
                    </a>
                    @if ($heritage['today'])
                        <a class="sx__today" href="{{ $heritage['today']['href'] }}">
                            @if ($heritage['today']['img'])
                                <img class="sx__today-img" src="{{ $heritage['today']['img'] }}" alt="" loading="lazy" decoding="async">
                            @endif
                            <span class="sx__today-text">
                                <span class="sx__today-tag">{{ bival('nav_today') }}</span>
                                <span class="sx__today-name">{{ $heritage['today']['name'] }}</span>
                                @if (filled($heritage['today']['place']))<span class="sx__today-place">{{ $heritage['today']['place'] }}</span>@endif
                            </span>
                        </a>
                    @endif
                    <dl class="sx__figures">
                        <div><dd>{{ $sxDigits($heritage['count']) }}</dd><dt>{{ bival('landmarks_stat_landmarks') }}</dt></div>
                        @if ($heritage['years'])<div><dd>{{ $heritage['years'] }}</dd><dt>{{ bival('landmarks_stat_years') }}</dt></div>@endif
                        @if ($heritage['unesco'])<div><dd>{{ $sxDigits($heritage['unesco']) }}</dd><dt>{{ bival('landmarks_stat_unesco') }}</dt></div>@endif
                    </dl>
                </section>
            @endif
        </div>

        {{-- the title block, along the foot of the sheet --}}
        <footer class="sx__block">
            <div class="sx__cell">
                <span class="sx__k">{{ bival('nav_studio') }}</span>
                <span class="sx__v">{{ $sxBrand }}</span>
            </div>
            <div class="sx__cell">
                <span class="sx__k">{{ bival('landmarks_place') }}</span>
                <span class="sx__v">{{ bival('nav_place') }}</span>
            </div>
            <div class="sx__cell">
                <span class="sx__k">{{ bival('nav_language') }}</span>
                @include('partials.lang-switch', ['urls' => $langUrls ?? null, 'class' => 'lsw--sheet'])
            </div>
            @if ($sxWa)
                <div class="sx__cell sx__cell--wa">
                    <a class="sx__wa" href="{{ $sxWa }}" target="_blank" rel="noopener" data-wa="menu">
                        @include('partials.icon-whatsapp')
                        <span>{{ bival('wa_cta') }}</span>
                    </a>
                </div>
            @endif
        </footer>
    </div>
</dialog>
