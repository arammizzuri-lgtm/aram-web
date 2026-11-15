    <!-- ========== PROJECTS MAP ========== -->
    <section class="proj-map" id="map">

        <!-- Edge-darkening veil -->
        <div class="proj-map__veil"></div>

        <!-- Leaflet map canvas -->
        <div id="projectMapEl"></div>

        <!-- Section label (top-left overlay) — on the map's own page, the title block says it -->
        @unless (isset($sheet))
        <div class="proj-map__label">
            <p class="section-label" {!! bitext('map_label') !!}>{{ bival('map_label') }}</p>
            <h2 class="proj-map__title" {!! bitext('map_title') !!}>{!! bival('map_title') !!}</h2>
            @include('home.sheet-ref', ['key' => 'map'])
        </div>
        @endunless

        {{-- "Watch the studio grow": the projects played onto the map year by
             year (map-growth.js), a year counter and a dimension line of the
             years keeping time. Hidden until the script is there to play it. --}}
        @php
            $growYears = $projects->map(fn ($p) => $p->endYear())->filter(fn ($y) => preg_match('/^\d{4}$/', $y))->unique()->sort()->values();
        @endphp
        @if ($growYears->count() > 1)
            <div class="map-grow" id="mapGrow" hidden
                 data-play-en="{{ \App\Support\SiteText::en('map_grow') }}" data-play-ku="{{ \App\Support\SiteText::second('map_grow') }}"
                 data-stop-en="{{ \App\Support\SiteText::en('map_grow_stop') }}" data-stop-ku="{{ \App\Support\SiteText::second('map_grow_stop') }}"
                 data-again-en="{{ \App\Support\SiteText::en('map_grow_again') }}" data-again-ku="{{ \App\Support\SiteText::second('map_grow_again') }}"
                 data-count-en="{{ \App\Support\SiteText::en('pj_count') }}" data-count-ku="{{ \App\Support\SiteText::second('pj_count') }}"
                 data-one-en="{{ \App\Support\SiteText::en('pj_count_one') }}" data-one-ku="{{ \App\Support\SiteText::second('pj_count_one') }}"
                 data-beyond-en="{{ \App\Support\SiteText::en('map_grow_beyond') }}" data-beyond-ku="{{ \App\Support\SiteText::second('map_grow_beyond') }}">
                <div class="map-grow__clock" id="mapGrowClock" hidden>
                    <span class="map-grow__year" id="mapGrowYear" aria-hidden="true"></span>
                    <span class="map-grow__count" id="mapGrowCount" aria-live="polite"></span>
                    <span class="map-grow__beyond" id="mapGrowBeyond" hidden></span>
                </div>
                <ol class="map-grow__line" id="mapGrowLine" aria-hidden="true">
                    @foreach ($growYears as $y)
                        <li data-year="{{ $y }}"><span>{{ $y }}</span></li>
                    @endforeach
                </ol>
                <button type="button" class="map-grow__play" id="mapGrowPlay" aria-pressed="false">
                    <svg class="map-grow__icon map-grow__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                    <svg class="map-grow__icon map-grow__icon--stop" viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
                    <span class="map-grow__label">{{ bival('map_grow') }}</span>
                </button>
            </div>
        @endif

        <!-- Floating project card — liquid glass; JS positions it near the pin.
             Two modes: a photo card, or (for map-only projects) a spec card
             with no image, toggled via .map-card--specs. -->
        <div class="map-card" id="mapCard" role="dialog" aria-label="Project preview">
            <span class="map-card__glow" aria-hidden="true"></span>
            <span class="map-card__sheen" aria-hidden="true"></span>
            <button class="map-card__close" id="mapCardClose" aria-label="Close">✕</button>

            {{-- .img-ph rather than the shimmer host class: this frame's own
                 ::after is already the bottom gradient that keeps the number
                 legible over the photo. --}}
            <div class="map-card__img-wrap">
                <span class="img-ph is-loading" id="mapCardPh" aria-hidden="true"></span>
                <img class="map-card__img" id="mapCardImg" src="" alt="">
                <span class="map-card__num" id="mapCardNum"></span>
            </div>

            <div class="map-card__body">
                <span class="map-card__badge" id="mapCardBadge" hidden></span>
                <h3 class="map-card__name" id="mapCardName"></h3>
                <p class="map-card__meta" id="mapCardMeta"></p>
                <p class="map-card__excerpt" id="mapCardExcerpt"></p>

                {{-- spec grid shown only for map-only (image-less) projects --}}
                <dl class="map-card__specs" id="mapCardSpecs">
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Type" data-ku="{{ rtl_slot('Type', 'جۆر', 'النوع') }}">{{ bi('Type', 'جۆر', 'النوع') }}</dt>
                        <dd id="mcSpecType"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Year" data-ku="{{ rtl_slot('Year', 'ساڵ', 'السنة') }}">{{ bi('Year', 'ساڵ', 'السنة') }}</dt>
                        <dd id="mcSpecYear"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Plot Area" data-ku="{{ rtl_slot('Plot Area', 'ڕووبەری زەوی', 'مساحة القطعة') }}">{{ bi('Plot Area', 'ڕووبەری زەوی', 'مساحة القطعة') }}</dt>
                        <dd id="mcSpecArea"></dd>
                    </div>
                    <div class="map-card__spec">
                        <dt dir="auto" data-en="Location" data-ku="{{ rtl_slot('Location', 'شوێن', 'الموقع') }}">{{ bi('Location', 'شوێن', 'الموقع') }}</dt>
                        <dd id="mcSpecLoc"></dd>
                    </div>
                </dl>

                <button class="map-card__cta" id="mapCardCta">
                    <span {!! bitext('map_view_project') !!}>{{ bival('map_view_project') }}</span>
                    <svg width="14" height="10" viewBox="0 0 14 10" fill="none"><path d="M1 5h12M8 1l5 4-5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
        </div>

        <!-- Zoom controls -->
        <div class="proj-map__zoom">
            <button class="map-zoom-btn" id="mapZoomIn"  aria-label="Zoom in">+</button>
            <button class="map-zoom-btn" id="mapZoomOut" aria-label="Zoom out">−</button>
        </div>

        <!-- Bottom stat bar -->
        <div class="proj-map__stats">
            <div class="map-stat"><span class="map-stat__val">{{ $stats['projects'] }}</span><span class="map-stat__lbl" {!! bitext('stat_projects') !!}>{{ bival('stat_projects') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['cities'] }}</span><span class="map-stat__lbl" {!! bitext('stat_cities') !!}>{{ bival('stat_cities') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['area_short'] }}</span><span class="map-stat__lbl" {!! bitext('stat_area') !!}>{{ bival('stat_area') }}</span></div>
            <div class="map-stat__div"></div>
            <div class="map-stat"><span class="map-stat__val">{{ $stats['countries'] }}</span><span class="map-stat__lbl" {!! bitext('stat_countries') !!}>{{ bival('stat_countries') }}</span></div>
        </div>

    </section>
