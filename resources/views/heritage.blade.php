@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Landmark> $landmarks */
    use App\Models\Landmark;
    use App\Support\HeritageImage;
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('landmarks_label').' — '.bival('landmarks_title').' | '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('landmarks_intro'), 200);
    $tiles = carto_tiles('dark_nolabels');
    $digits = fn (string|int $s) => Landmark::digits($s);
    $num = fn (int $i) => $digits(str_pad((string) $i, 2, '0', STR_PAD_LEFT));

    // only the ages and parts that have a landmark get a filter
    $eras = collect(array_keys(Landmark::ERAS))->filter(fn ($k) => $landmarks->contains('era', $k))->values();
    $regions = collect(array_keys(Landmark::REGIONS))->filter(fn ($k) => $landmarks->contains('region', $k))->values();

    $pins = $landmarks->filter(fn ($l) => $l->lat !== null)->map(fn ($l) => [
        'n' => $num($landmarks->search($l) + 1), 'lat' => $l->lat, 'lng' => $l->lng, 'url' => $l->url(), 'slug' => $l->slug,
        'name' => $l->tr('name'), 'place' => $l->tr('place'), 'period' => $l->tr('period'), 'summary' => $l->tr('summary'),
        'era' => $l->era, 'eraName' => Landmark::eraName($l->era), 'region' => $l->region, 'unesco' => $l->unesco, 'img' => $l->imageUrl('md'),
    ])->values();

    // the figures: how many, how many UNESCO, how many years since the oldest, how many parts
    $now = (int) date('Y');
    $oldest = $landmarks->whereNotNull('year_from')->min('year_from');
    $years = $oldest !== null ? $now - $oldest : null;
    $yearsLabel = $years ? $digits(number_format(floor($years / 1000) * 1000)).'+' : null;

    // the collection across time: one row per age, each landmark a mark at
    // the year it begins. The scale gives every stretch between two anchor
    // years the same width — the deep past, where a few sites lie tens of
    // thousands of years apart, and the last three thousand years, where
    // most of them crowd — so no age is crushed and the axis labels, one per
    // anchor, never run into each other. Faint lines mark the anchors.
    $start = $oldest === null ? $now : (int) ($oldest < -10000 ? floor($oldest / 5000) * 5000 : floor($oldest / 1000) * 1000);
    $anchors = array_values(array_merge([$start], array_filter([-10000, -5000, -3000, -1000, 1, 1000], fn ($y) => $y > $start && $y < $now), [$now]));
    $segs = max(1, count($anchors) - 1);
    $pos = function (?int $y) use ($anchors, $segs, $now): float {
        $y = $y === null ? $now : max($anchors[0], min($now, $y));
        for ($i = 0; $i < $segs; $i++) {
            if ($y <= $anchors[$i + 1]) {
                return round(($i + ($y - $anchors[$i]) / max(1, $anchors[$i + 1] - $anchors[$i])) / $segs * 100, 2);
            }
        }

        return 100.0;
    };
    $ticks = collect($anchors)->map(fn ($y, $k) => ['x' => $pos($y), 'label' => $k === $segs ? bival('landmarks_now') : Landmark::yearLabel($y)]);
    // marks closer than a mark's width share no lane: the next one drops a
    // lane (heritage.js redoes this at the real width, so phones get more)
    $rows = $eras->map(function ($era) use ($landmarks, $pos) {
        $ends = [];
        $items = $landmarks->filter(fn ($l) => $l->era === $era)->map(fn ($l) => [
            'i' => $landmarks->search($l), 'x' => $pos($l->year_from), 'name' => $l->tr('name'), 'slug' => $l->slug, 'region' => $l->region,
        ])->sortBy('x')->values()->map(function ($it) use (&$ends) {
            $lane = 0;
            while (isset($ends[$lane]) && $it['x'] - $ends[$lane] < 2.8) {
                $lane++;
            }
            $ends[$lane] = $it['x'];

            return $it + ['lane' => $lane];
        });
        // the bar runs from the age's first mark to its last
        return ['era' => $era, 'items' => $items, 'lanes' => max(1, count($ends)),
            'a' => $items->min('x'), 'b' => $items->max('x')];
    });

    $schema = ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => bival('landmarks_label'), 'description' => $description,
        'url' => site_url(Landmark::PATH), 'inLanguage' => $lang, 'author' => \App\Support\Architect::reference(),
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $landmarks->map(fn ($l, $i) => ['@type' => 'ListItem', 'position' => $i + 1,
            'url' => $l->url(), 'name' => $l->tr('name'), 'image' => $l->imageUrl('md') ? url($l->imageUrl('md')) : null])->all()]];
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
                <div class="hr-hero__row">
                    <a class="pp-btn" href="#map">{{ bival('landmarks_explore') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                    <p class="hr-hero__count">{{ str_replace(['{n}', '{u}'], [$digits($landmarks->count()), $digits($landmarks->where('unesco', true)->count())], bival('landmarks_count')) }}</p>
                </div>
            @endif
        </header>

        @if ($landmarks->isNotEmpty())
            <dl class="hr-stats reveal">
                <div class="hr-stat"><dd><span class="lm-stat__value" data-count>{{ $digits($landmarks->count()) }}</span></dd><dt>{{ bival('landmarks_stat_landmarks') }}</dt></div>
                @if ($yearsLabel)<div class="hr-stat"><dd><span class="lm-stat__value" data-count>{{ $yearsLabel }}</span></dd><dt>{{ bival('landmarks_stat_years') }}</dt></div>@endif
                <div class="hr-stat"><dd><span class="lm-stat__value" data-count>{{ $digits($landmarks->where('unesco', true)->count()) }}</span></dd><dt>{{ bival('landmarks_stat_unesco') }}</dt></div>
                <div class="hr-stat"><dd><span class="lm-stat__value" data-count>{{ $digits($regions->count()) }}</span></dd><dt>{{ bival('landmarks_stat_parts') }}</dt></div>
            </dl>

            <section class="hr-explore" id="map">
                {{-- filters: one age or one part of Kurdistan at a time (heritage.js) --}}
                <nav class="hr-filters" aria-label="{{ bival('landmarks_by_era') }} / {{ bival('landmarks_by_region') }}">
                    <button type="button" class="hr-chip is-on" data-filter="">{{ bival('landmarks_all') }} <span class="hr-chip__n">{{ $digits($landmarks->count()) }}</span></button>
                    <span class="hr-filters__group">
                        <span class="hr-filters__label">{{ bival('landmarks_by_era') }}</span>
                        @foreach ($eras as $k)
                            <button type="button" class="hr-chip" data-filter="era:{{ $k }}">{{ Landmark::eraName($k) }} <span class="hr-chip__n">{{ $digits($landmarks->where('era', $k)->count()) }}</span></button>
                        @endforeach
                    </span>
                    <span class="hr-filters__group">
                        <span class="hr-filters__label">{{ bival('landmarks_by_region') }}</span>
                        @foreach ($regions as $k)
                            <button type="button" class="hr-chip" data-filter="region:{{ $k }}">{{ Landmark::regionName($k) }} <span class="hr-chip__n">{{ $digits($landmarks->where('region', $k)->count()) }}</span></button>
                        @endforeach
                    </span>
                </nav>

                @if ($tiles && $pins->isNotEmpty())
                    {{-- Greater Kurdistan, with the homepage map's border (heritage.js) --}}
                    <div class="hr-map-wrap">
                        <div class="hr-map" id="hrMap" data-tiles="{{ $tiles }}" data-pins='@json($pins)'
                             data-outline="{{ asset('kurdistan-data.js') }}?v={{ filemtime(public_path('kurdistan-data.js')) }}"
                             data-read="{{ bival('landmarks_read') }}" data-unesco="{{ bival('landmarks_unesco') }}"></div>
                        <p class="hr-map__hint" aria-hidden="true">{{ bival('landmarks_map_hint') }}</p>
                    </div>
                @endif

                @if ($oldest !== null)
                    {{-- the collection across time: a row per age, a mark per landmark (heritage.js opens its card) --}}
                    <div class="hr-time reveal" aria-label="{{ bival('landmarks_timeline_title') }}">
                        <div class="hr-time__head">
                            <h2 class="hr-time__title">{{ bival('landmarks_timeline_title') }}</h2>
                            <p class="hr-time__hint">{{ bival('landmarks_timeline_hint') }}</p>
                        </div>
                        <div class="hr-time__grid" style="--segs: {{ $segs }}">
                            @foreach ($rows as $row)
                                <button type="button" class="hr-time__label" data-era="{{ $row['era'] }}">{{ Landmark::eraName($row['era']) }}</button>
                                <div class="hr-time__row" data-era="{{ $row['era'] }}" style="--lanes: {{ $row['lanes'] }}">
                                    @if ($row['b'] > $row['a'])
                                        <span class="hr-time__bar" style="--a: {{ $row['a'] }}; --b: {{ $row['b'] }}" aria-hidden="true"></span>
                                    @endif
                                    @foreach ($row['items'] as $it)
                                        <a class="hr-time__dot{{ $it['x'] > 80 ? ' is-end' : ($it['x'] < 20 ? ' is-start' : '') }}" href="#{{ $it['slug'] }}" style="--x: {{ $it['x'] }}; --lane: {{ $it['lane'] }}" data-i="{{ $it['i'] }}" data-era="{{ $row['era'] }}" data-region="{{ $it['region'] }}" data-name="{{ $it['name'] }}" aria-label="{{ $it['name'] }}">{{ $num($it['i'] + 1) }}</a>
                                    @endforeach
                                </div>
                            @endforeach
                            <span class="hr-time__corner" aria-hidden="true"></span>
                            <div class="hr-time__axis" aria-hidden="true">
                                @foreach ($ticks as $t)
                                    <span style="--x: {{ $t['x'] }}">{{ $t['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </section>

            <section class="hr-collection">
                <h2 class="hr-collection__title">{{ bival('landmarks_collection') }}</h2>
                @foreach ($eras as $ei => $era)
                    @php
                        $group = $landmarks->filter(fn ($l) => $l->era === $era);
                        $gFrom = $group->whereNotNull('year_from')->min('year_from');
                        $gTo = $group->contains(fn ($l) => $l->year_to === null) ? null : $group->max('year_to');
                        $span = $gFrom === null ? '' : Landmark::yearLabel($gFrom).' – '.($gTo === null ? bival('landmarks_now') : Landmark::yearLabel($gTo));
                    @endphp
                    <section class="hr-group" data-era="{{ $era }}">
                        <h3 class="hr-group__title">
                            <span class="hr-group__n">{{ $digits($ei + 1) }}</span>
                            <span class="hr-group__name">{{ Landmark::eraName($era) }}</span>
                            @if ($span)<span class="hr-group__years">{{ $span }}</span>@endif
                        </h3>
                        <ol class="hr-list">
                            @foreach ($group as $l)
                                @php $i = $landmarks->search($l); @endphp
                                <li class="hr-card reveal" data-era="{{ $l->era }}" data-region="{{ $l->region }}" id="{{ $l->slug }}">
                                    <a class="hr-card__link" href="{{ $l->url() }}">
                                        <span class="hr-card__img">
                                            @if ($l->image)
                                                <img src="{{ $l->imageUrl('sm') }}" srcset="{{ HeritageImage::srcset($l->image) }}" sizes="(min-width: 1000px) 30vw, (min-width: 640px) 50vw, 100vw" alt="{{ $l->tr('name') }}" loading="lazy" decoding="async">
                                            @else
                                                <span class="hr-card__ph" aria-hidden="true">{{ $num($i + 1) }}</span>
                                            @endif
                                            <span class="hr-card__n">{{ $num($i + 1) }}</span>
                                        </span>
                                        <span class="hr-card__meta">
                                            <span{{ text_dir($l->tr('place')) }}>{{ $l->tr('place') }}</span>
                                            @if ($l->unesco)<span class="hr-card__unesco">UNESCO</span>@endif
                                        </span>
                                        <span class="hr-card__name"{{ text_dir($l->tr('name')) }}>{{ $l->tr('name') }}</span>
                                        <span class="hr-card__where"{{ text_dir($l->tr('period')) }}>{{ $l->tr('period') }}</span>
                                        @if (filled($l->tr('summary')))
                                            <span class="hr-card__summary"{{ text_dir($l->tr('summary')) }}>{{ $l->tr('summary') }}</span>
                                        @endif
                                        <span class="hr-card__more">{{ bival('landmarks_read') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            </section>
        @endif

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
    <script src="{{ asset('landmark.js') }}?v={{ filemtime(public_path('landmark.js')) }}" defer></script>
</body>
</html>
