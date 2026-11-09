@php
    /** @var \App\Models\Landmark $landmark */
    /** @var ?\App\Models\Landmark $prev */
    /** @var ?\App\Models\Landmark $next */
    use App\Models\Landmark;
    $l = $landmark;
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = $l->tr('name').' — '.bival('landmarks_label').' | '.$brand;
    $description = \Illuminate\Support\Str::limit($l->tr('summary') ?: ($l->paragraphs()[0] ?? ''), 200);
    $tiles = carto_tiles('dark_nolabels');
    $image = $l->imageUrl() ? url($l->imageUrl()) : null;
    $pins = $l->lat !== null ? [['n' => '', 'lat' => $l->lat, 'lng' => $l->lng, 'name' => $l->tr('name'), 'unesco' => $l->unesco]] : [];

    $schema = ['@context' => 'https://schema.org', '@graph' => [
        array_filter(['@type' => 'LandmarksOrHistoricalBuildings', 'name' => $l->tr('name'), 'description' => $l->tr('summary'),
            'url' => $l->url(), 'image' => $image,
            'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $l->tr('place'), 'addressRegion' => Landmark::regionName($l->region)]),
            'geo' => $l->lat !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $l->lat, 'longitude' => $l->lng] : null,
            'subjectOf' => ['@type' => 'Article', 'headline' => $l->tr('name'), 'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(),
                'url' => $l->url(), 'dateModified' => $l->updated_at?->toAtomString()]]),
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => $brand, 'item' => site_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => bival('landmarks_label'), 'item' => site_url(Landmark::PATH)],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $l->tr('name'), 'item' => $l->url()],
        ]],
    ]];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => Landmark::PATH.'/'.$l->slug, 'css' => 'heritage.css', 'type' => 'article', 'image' => $image])
    @if ($tiles && $pins)
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
</head>
<body class="pp pp--heritage pp--landmark">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <article class="lm">
            <header class="lm-head">
                <a class="lm-back" href="{{ site_url(Landmark::PATH) }}#{{ $l->slug }}"><span class="pp-arrow" aria-hidden="true">←</span> {{ bival('landmarks_back') }}</a>
                <p class="pp-label">{{ Landmark::regionName($l->region) }} · {{ Landmark::eraName($l->era) }}</p>
                <h1 class="lm-title" dir="auto">{{ $l->tr('name') }}</h1>
                @if (filled($l->tr('summary')))
                    <p class="lm-summary" dir="auto">{{ $l->tr('summary') }}</p>
                @endif
                @if ($l->unesco)
                    <p class="lm-unesco"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8h18zM5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>{{ bival('landmarks_unesco') }}</p>
                @endif
            </header>

            <figure class="lm-img">
                @if ($l->imageUrl())
                    <img src="{{ $l->imageUrl() }}" alt="{{ $l->tr('name') }}" decoding="async">
                    @if ($l->image_credit)<figcaption>{{ $l->image_credit }}</figcaption>@endif
                @else
                    <span class="lm-img__soon">{{ bival('landmarks_photo_soon') }}</span>
                @endif
            </figure>

            <div class="lm-body">
                <dl class="lm-facts">
                    <div><dt>{{ bival('landmarks_period') }}</dt><dd dir="auto">{{ $l->tr('period') }}</dd></div>
                    <div><dt>{{ bival('landmarks_place') }}</dt><dd dir="auto">{{ $l->tr('place') }}</dd></div>
                    <div><dt>{{ bival('landmarks_region') }}</dt><dd>{{ Landmark::regionName($l->region) }}</dd></div>
                    <div><dt>{{ bival('landmarks_era') }}</dt><dd>{{ Landmark::eraName($l->era) }}</dd></div>
                    @if ($l->lat !== null)
                        <div><dd><a class="lm-go" href="https://www.google.com/maps/search/?api=1&query={{ $l->lat }},{{ $l->lng }}" target="_blank" rel="noopener">{{ bival('landmarks_directions') }} <span aria-hidden="true">↗</span></a></dd></div>
                    @endif
                </dl>
                <div class="lm-text" dir="auto">
                    @foreach ($l->paragraphs() as $p)
                        <p>{{ $p }}</p>
                    @endforeach
                    <p class="lm-by">{{ bival('landmarks_by') }}</p>
                </div>
            </div>

            @if ($tiles && $pins)
                {{-- where it is in Greater Kurdistan (heritage.js) --}}
                <div class="hr-map hr-map--single" id="hrMap" data-single="1" data-tiles="{{ $tiles }}" data-pins='@json($pins)'
                     data-outline="{{ asset('kurdistan-data.js') }}?v={{ filemtime(public_path('kurdistan-data.js')) }}" aria-hidden="true"></div>
            @endif

            <nav class="lm-nav">
                @if ($prev)
                    <a class="lm-nav__link" href="{{ $prev->url() }}"><span class="lm-nav__dir"><span class="pp-arrow" aria-hidden="true">←</span> {{ bival('landmarks_prev') }}</span><span class="lm-nav__name" dir="auto">{{ $prev->tr('name') }}</span></a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a class="lm-nav__link lm-nav__link--next" href="{{ $next->url() }}"><span class="lm-nav__dir">{{ bival('landmarks_next') }} <span class="pp-arrow" aria-hidden="true">→</span></span><span class="lm-nav__name" dir="auto">{{ $next->tr('name') }}</span></a>
                @endif
            </nav>
        </article>

        <section class="hr-end">
            <h2 class="hr-end__title">{{ bival('landmarks_cta') }}</h2>
            <div class="hr-end__actions">
                <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('landmarks_cta_btn') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                <a class="pp-btn pp-btn--line" href="{{ site_url(Landmark::PATH) }}">{{ bival('landmarks_back') }}</a>
            </div>
        </section>
    </main>

    @include('partials.page-foot')
    @if ($tiles && $pins)
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}" defer></script>
        <script src="{{ asset('heritage.js') }}?v={{ filemtime(public_path('heritage.js')) }}" defer></script>
    @endif
</body>
</html>
