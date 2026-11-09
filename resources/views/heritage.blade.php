@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Landmark> $landmarks */
    use App\Models\Landmark;
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('landmarks_label').' — '.bival('landmarks_title').' | '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('landmarks_intro'), 200);
    $tiles = carto_tiles('dark_nolabels');
    $digits = fn (string $s) => $lang === 'en' ? $s : strtr($s, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    $num = fn (int $i) => $digits(str_pad((string) $i, 2, '0', STR_PAD_LEFT));

    // only the ages and parts that have a landmark get a filter
    $eras = collect(array_keys(Landmark::ERAS))->filter(fn ($k) => $landmarks->contains('era', $k));
    $regions = collect(array_keys(Landmark::REGIONS))->filter(fn ($k) => $landmarks->contains('region', $k));

    $pins = $landmarks->filter(fn ($l) => $l->lat !== null)->map(fn ($l) => [
        'n' => $num($landmarks->search($l) + 1), 'lat' => $l->lat, 'lng' => $l->lng, 'url' => $l->url(),
        'name' => $l->tr('name'), 'period' => $l->tr('period'), 'summary' => $l->tr('summary'),
        'era' => $l->era, 'eraName' => Landmark::eraName($l->era), 'region' => $l->region, 'unesco' => $l->unesco, 'img' => $l->imageUrl(),
    ])->values();

    $schema = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => bival('landmarks_label'), 'description' => $description,
        'url' => site_url(Landmark::PATH), 'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(),
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $landmarks->map(fn ($l, $i) => ['@type' => 'ListItem', 'position' => $i + 1,
            'url' => $l->url(), 'name' => $l->tr('name')])->all()]];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => Landmark::PATH, 'css' => 'heritage.css'])
    @if ($tiles && $pins->isNotEmpty())
        <link rel="stylesheet" href="{{ asset('vendor/leaflet.css') }}?v={{ filemtime(public_path('vendor/leaflet.css')) }}">
    @endif
</head>
<body class="pp pp--heritage">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <header class="hr-hero">
            <p class="pp-label">{{ bival('landmarks_label') }}</p>
            <h1 class="hr-hero__title">{{ bival('landmarks_title') }}</h1>
            <p class="hr-hero__intro">{{ bival('landmarks_intro') }}</p>
            @if ($landmarks->isNotEmpty())
                <p class="hr-hero__count">{{ str_replace(['{n}', '{u}'], [$digits((string) $landmarks->count()), $digits((string) $landmarks->where('unesco', true)->count())], bival('landmarks_count')) }}</p>
            @endif
        </header>

        @if ($landmarks->isNotEmpty())
            {{-- filters: one age or one part of Kurdistan at a time (heritage.js) --}}
            <nav class="hr-filters" aria-label="{{ bival('landmarks_by_era') }} / {{ bival('landmarks_by_region') }}">
                <button type="button" class="hr-chip is-on" data-filter="">{{ bival('landmarks_all') }}</button>
                <span class="hr-filters__group">
                    <span class="hr-filters__label">{{ bival('landmarks_by_era') }}</span>
                    @foreach ($eras as $k)
                        <button type="button" class="hr-chip" data-filter="era:{{ $k }}">{{ Landmark::eraName($k) }}</button>
                    @endforeach
                </span>
                <span class="hr-filters__group">
                    <span class="hr-filters__label">{{ bival('landmarks_by_region') }}</span>
                    @foreach ($regions as $k)
                        <button type="button" class="hr-chip" data-filter="region:{{ $k }}">{{ Landmark::regionName($k) }}</button>
                    @endforeach
                </span>
            </nav>
        @endif

        @if ($tiles && $pins->isNotEmpty())
            {{-- Greater Kurdistan, with the homepage map's border (heritage.js) --}}
            <div class="hr-map-wrap">
                <div class="hr-map" id="hrMap" data-tiles="{{ $tiles }}" data-pins='@json($pins)'
                     data-outline="{{ asset('kurdistan-data.js') }}?v={{ filemtime(public_path('kurdistan-data.js')) }}"
                     data-read="{{ bival('landmarks_read') }}" data-unesco="{{ bival('landmarks_unesco') }}"></div>
                <p class="hr-map__hint" aria-hidden="true">{{ bival('landmarks_map_hint') }}</p>
            </div>
        @endif

        <ol class="hr-list">
            @foreach ($landmarks as $i => $l)
                <li class="hr-card" data-era="{{ $l->era }}" data-region="{{ $l->region }}" id="{{ $l->slug }}">
                    <a class="hr-card__link" href="{{ $l->url() }}">
                        <span class="hr-card__img">
                            @if ($l->imageUrl())
                                <img src="{{ $l->imageUrl() }}" alt="{{ $l->tr('name') }}" loading="lazy" decoding="async">
                            @else
                                <span class="hr-card__ph" aria-hidden="true">{{ $num($i + 1) }}</span>
                            @endif
                        </span>
                        <span class="hr-card__meta">
                            <span>{{ $num($i + 1) }}</span>
                            <span>{{ Landmark::eraName($l->era) }}</span>
                            @if ($l->unesco)<span class="hr-card__unesco">UNESCO</span>@endif
                        </span>
                        <span class="hr-card__name" dir="auto">{{ $l->tr('name') }}</span>
                        <span class="hr-card__where" dir="auto">{{ $l->tr('place') }} · {{ $l->tr('period') }}</span>
                        @if (filled($l->tr('summary')))
                            <span class="hr-card__summary" dir="auto">{{ $l->tr('summary') }}</span>
                        @endif
                        <span class="hr-card__more">{{ bival('landmarks_read') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                    </a>
                </li>
            @endforeach
        </ol>

        <section class="hr-end">
            <p class="hr-end__by">{{ bival('landmarks_by') }}</p>
            <h2 class="hr-end__title">{{ bival('landmarks_cta') }}</h2>
            <div class="hr-end__actions">
                <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('landmarks_cta_btn') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                <a class="pp-btn pp-btn--line" href="{{ site_url('/projects') }}">{{ bival('nf_all') }}</a>
            </div>
        </section>
    </main>

    @include('partials.page-foot')
    @if ($tiles && $pins->isNotEmpty())
        <script src="{{ asset('vendor/leaflet.js') }}?v={{ filemtime(public_path('vendor/leaflet.js')) }}" defer></script>
    @endif
    <script src="{{ asset('heritage.js') }}?v={{ filemtime(public_path('heritage.js')) }}" defer></script>
</body>
</html>
