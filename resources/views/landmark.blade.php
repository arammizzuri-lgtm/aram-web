@php
    /** @var \App\Models\Landmark $landmark */
    /** @var ?\App\Models\Landmark $prev */
    /** @var ?\App\Models\Landmark $next */
    use App\Models\Landmark;
    use App\Support\HeritageImage;
    $l = $landmark;
    $lang = site_lang();
    $preview = $preview ?? false;
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = $l->tr('name').' — '.$l->tr('place').' · '.bival('landmarks_label').' | '.$brand;
    $description = \Illuminate\Support\Str::limit($l->tr('intro') ?: ($l->tr('summary') ?: ($l->paragraphs()[0] ?? '')), 200);
    $tiles = carto_tiles('dark_nolabels');
    $cover = $l->imageUrl('lg') ? url($l->imageUrl('lg')) : null;

    // the page's sections — and the map, the UNESCO panel and related
    // landmarks even when the page was composed without them
    $sections = $l->sections();
    if ($l->unesco && ! $l->hasSection('unesco')) {
        $sections[] = ['type' => 'unesco', 'data' => []];
    }
    if (! $l->hasSection('map')) {
        $sections[] = ['type' => 'map', 'data' => []];
    }
    if (! $l->hasSection('related')) {
        $sections[] = ['type' => 'related', 'data' => []];
    }
    $hasMap = $tiles && $l->lat !== null;

    $imageObjects = collect($l->images())->map(fn ($img) => array_filter([
        '@type' => 'ImageObject', 'contentUrl' => url(HeritageImage::url($img['file'], 'lg')),
        'caption' => Landmark::t($img, 'caption') ?: Landmark::t($img, 'alt'), 'creditText' => $img['credit'] ?? null,
    ]))->values()->all();
    $schema = ['@context' => 'https://schema.org', '@graph' => [
        array_filter(['@type' => 'LandmarksOrHistoricalBuildings', '@id' => $l->url().'#place', 'name' => $l->tr('name'), 'description' => $description,
            'url' => $l->url(), 'image' => $cover,
            'address' => array_filter(['@type' => 'PostalAddress', 'addressLocality' => $l->tr('place'), 'addressRegion' => Landmark::regionName($l->region)]),
            'geo' => $l->lat !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $l->lat, 'longitude' => $l->lng] : null,
            'hasMap' => $l->lat !== null ? 'https://www.google.com/maps/search/?api=1&query='.$l->lat.','.$l->lng : null,
            'sameAs' => $l->unesco && $l->unesco_ref ? 'https://whc.unesco.org/en/list/'.$l->unesco_ref.'/' : null]),
        array_filter(['@type' => 'Article', 'headline' => $l->tr('name'), 'description' => $description, 'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(),
            'about' => ['@id' => $l->url().'#place'], 'url' => $l->url(), 'mainEntityOfPage' => $l->url(),
            'image' => $imageObjects ?: null, 'datePublished' => $l->created_at?->toAtomString(), 'dateModified' => $l->updated_at?->toAtomString()]),
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
    @include('partials.page-head', ['path' => Landmark::PATH.'/'.$l->slug, 'css' => 'heritage.css', 'type' => 'article', 'image' => $cover])
    <link rel="stylesheet" href="{{ asset('landmark.css') }}?v={{ filemtime(public_path('landmark.css')) }}">
    @if ($hasMap)
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
    @if ($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
</head>
<body class="pp pp--heritage pp--landmark lm--{{ $l->hero_style }} lm--{{ $l->era }}">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        @if ($preview)
            <p class="lm-preview">{{ bival('landmarks_preview_note') }}</p>
        @endif

        <article class="lm">
            @include('heritage.hero')

            <div class="lm-body">
                @foreach ($sections as $i => ['type' => $type, 'data' => $data])
                    @include('heritage.blocks.'.$type, ['d' => $data, 'n' => $i])
                @endforeach
            </div>

            <p class="lm-by">{{ bival('landmarks_by') }}</p>

            @if ($prev || $next)
                <nav class="lm-nav" aria-label="{{ bival('landmarks_prev') }} / {{ bival('landmarks_next') }}">
                    @if ($prev)
                        <a class="lm-nav__link" href="{{ $prev->url() }}">
                            <span class="lm-nav__img">@if ($prev->image)<img src="{{ $prev->imageUrl('sm') }}" alt="" loading="lazy" decoding="async">@endif</span>
                            <span class="lm-nav__dir"><span class="pp-arrow" aria-hidden="true">←</span> {{ bival('landmarks_prev') }}</span>
                            <span class="lm-nav__name"{{ text_dir($prev->tr('name')) }}>{{ $prev->tr('name') }}</span>
                        </a>
                    @else
                        <span></span>
                    @endif
                    @if ($next)
                        <a class="lm-nav__link lm-nav__link--next" href="{{ $next->url() }}">
                            <span class="lm-nav__img">@if ($next->image)<img src="{{ $next->imageUrl('sm') }}" alt="" loading="lazy" decoding="async">@endif</span>
                            <span class="lm-nav__dir">{{ bival('landmarks_next') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                            <span class="lm-nav__name"{{ text_dir($next->tr('name')) }}>{{ $next->tr('name') }}</span>
                        </a>
                    @endif
                </nav>
            @endif
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

    {{-- the lightbox every photograph opens in (landmark.js) --}}
    <dialog class="lm-lb" id="lmLightbox" aria-label="{{ $l->tr('name') }}">
        <button type="button" class="lm-lb__close" data-lb-close aria-label="{{ bival('landmarks_lb_close') }}">×</button>
        <button type="button" class="lm-lb__arrow lm-lb__arrow--prev" data-lb-prev aria-label="{{ bival('landmarks_lb_prev') }}"><span class="pp-arrow">←</span></button>
        <figure class="lm-lb__fig">
            <img class="lm-lb__img" src="" alt="">
            <figcaption class="lm-lb__cap"><span class="lm-lb__text" dir="auto"></span><span class="lm-lb__credit"></span><span class="lm-lb__n"></span></figcaption>
        </figure>
        <button type="button" class="lm-lb__arrow lm-lb__arrow--next" data-lb-next aria-label="{{ bival('landmarks_lb_next') }}"><span class="pp-arrow">→</span></button>
    </dialog>

    @if ($hasMap)
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}" defer></script>
        <script src="{{ asset('heritage.js') }}?v={{ filemtime(public_path('heritage.js')) }}" defer></script>
    @endif
    <script src="{{ asset('landmark.js') }}?v={{ filemtime(public_path('landmark.js')) }}" defer></script>
</body>
</html>
