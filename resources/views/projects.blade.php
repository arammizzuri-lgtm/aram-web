@php
    /**
     * The studio's work as a set of drawings (ProjectsController).
     *
     * @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects  in the studio's order
     * @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories
     * @var \Illuminate\Support\Collection<string, int> $counts  projects per category key
     * @var \Illuminate\Support\Collection<string, int> $hatch  the hatch each category is drawn in
     * @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> $byYear  newest first; '' undated
     * @var \Illuminate\Support\Collection<int, string> $years
     * @var string $type  the category asked for in the address, or ''
     * @var string $view  'index' or 'sheets'
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('pj_title').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('pj_intro'), 160);
    $ogImage = $projects->first()?->socialImageUrl() ?? asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));
    $count = fn (int $n) => $n === 1 ? bival('pj_count_one') : str_replace('{n}', (string) $n, bival('pj_count'));
    $inType = fn ($p) => $type === '' || in_array($type, $p->categoryKeys(), true);
    $shown = $projects->filter($inType)->count();
    $span = $years->isEmpty() ? '' : ($years->last() === $years->first() ? $years->first() : $years->last().' – '.$years->first());
    $query = fn (array $q) => ($q = array_filter($q)) ? '?'.http_build_query($q) : '';
    $catName = $categories->mapWithKeys(fn ($c) => [$c->key => bi($c->name, $c->name_ku)]);
    $kinds = fn ($p) => collect($p->categoryKeys())->filter(fn ($k) => $catName->has($k));

    $itemList = [
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'name' => $title, 'url' => site_url('/projects'), 'inLanguage' => $lang,
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $projects->values()->map(fn ($p, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'url' => $p->pageUrl($lang), 'name' => bi($p->name, $p->name_ku),
        ])->all()],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @include('partials.seo-alternates', ['path' => '/projects'])
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ site_url('/projects') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">{!! json_encode($itemList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    {{-- The view this visitor chose last time, settled before the page paints
         so the other one never flashes up. An address that names a view wins. --}}
    <script>
        (function () {
            try {
                if (!new URLSearchParams(location.search).get('view') && localStorage.getItem('am_projects_view') === 'sheets') {
                    document.documentElement.classList.add('px-want-sheets');
                }
            } catch (e) { /* storage blocked: the default view */ }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('engage.css') }}?v={{ filemtime(public_path('engage.css')) }}">
    <link rel="stylesheet" href="{{ asset('projects.css') }}?v={{ filemtime(public_path('projects.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--projects">

    @include('partials.page-top', ['otherUrl' => site_url('/projects', $other).$query(['type' => $type, 'view' => $view === 'sheets' ? 'sheets' : '']), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home')])

    <main class="px" id="px" data-view="{{ $view }}">

        {{-- ---- The title, set out with construction lines and a dimension ---- --}}
        <header class="px-head">
            <p class="pp-label">{{ bival('pj_label') }}</p>
            <div class="px-title-wrap">
                <h1 class="px-title">{{ bival('pj_title') }}</h1>
                <span class="px-guide px-guide--cap" aria-hidden="true"></span>
                <span class="px-guide px-guide--base" aria-hidden="true"></span>
                <span class="px-guide px-guide--side" aria-hidden="true"></span>
                <span class="px-guide__dot" aria-hidden="true"></span>
            </div>
            <p class="px-intro">{{ bival('pj_intro') }}</p>
            <div class="px-dim">
                <span class="px-dim__tick" aria-hidden="true"></span>
                <span class="px-dim__line" aria-hidden="true"></span>
                <span class="px-dim__label"><span id="pxCount" aria-live="polite">{{ $count($shown) }}</span>@if ($span) <span class="px-dim__span">· <bdi>{{ $span }}</bdi></span>@endif</span>
                <span class="px-dim__line" aria-hidden="true"></span>
                <span class="px-dim__tick" aria-hidden="true"></span>
            </div>
        </header>

        {{-- ---- The legend (categories, each in its hatch), search, and views ---- --}}
        <div class="px-bar" id="pxBar" data-count="{{ bival('pj_count') }}" data-count-one="{{ bival('pj_count_one') }}">
            <div class="px-bar__inner">
                <nav class="px-legend" aria-label="{{ bival('pj_legend') }}">
                    <span class="px-legend__title" aria-hidden="true">{{ bival('pj_legend') }}</span>
                    <a class="px-key{{ $type === '' ? ' is-on' : '' }}" href="{{ site_url('/projects').$query(['view' => $view === 'sheets' ? 'sheets' : '']) }}" data-type="" @if ($type === '') aria-current="true" @endif>
                        <span class="px-swatch px-swatch--all" aria-hidden="true"></span>{{ bival('filter_all') }}<sup>{{ $projects->count() }}</sup>
                    </a>
                    @foreach ($categories as $cat)
                        <a class="px-key{{ $type === $cat->key ? ' is-on' : '' }}" href="{{ site_url('/projects').$query(['type' => $cat->key, 'view' => $view === 'sheets' ? 'sheets' : '']) }}" data-type="{{ $cat->key }}" @if ($type === $cat->key) aria-current="true" @endif>
                            <span class="px-swatch hatch-{{ $hatch[$cat->key] }}" aria-hidden="true"></span><bdi>{{ $catName[$cat->key] }}</bdi><sup>{{ $counts[$cat->key] }}</sup>
                        </a>
                    @endforeach
                </nav>
                <div class="px-bar__end">
                    <label class="px-find">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.5"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <input type="search" id="pxSearch" placeholder="{{ bival('pj_search') }}" aria-label="{{ bival('pj_search') }}" autocomplete="off" dir="auto">
                    </label>
                    <div class="px-views" role="group" aria-label="{{ bival('pj_view') }}">
                        <a class="px-view{{ $view === 'index' ? ' is-on' : '' }}" href="{{ site_url('/projects').$query(['type' => $type]) }}" data-view="index" aria-pressed="{{ $view === 'index' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="1.4"/></svg>
                            <span>{{ bival('pj_index') }}</span>
                        </a>
                        <a class="px-view{{ $view === 'sheets' ? ' is-on' : '' }}" href="{{ site_url('/projects').$query(['type' => $type, 'view' => 'sheets']) }}" data-view="sheets" aria-pressed="{{ $view === 'sheets' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="11" stroke="currentColor" stroke-width="1.4"/><path d="M3.5 19.5h17M13 15.5v4" stroke="currentColor" stroke-width="1.4"/></svg>
                            <span>{{ bival('pj_sheets') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ---- Index: the register, year by year. On a computer the project's
             picture comes up beside the pointer (projects.js); on a phone each
             row carries a small one. ---- --}}
        <section class="px-index" id="pxIndex" aria-label="{{ bival('pj_index') }}" @if ($view !== 'index') hidden @endif>
            <div class="px-cols" aria-hidden="true">
                <span>{{ bival('pj_sheet') }}</span><span>{{ bival('pj_project') }}</span><span>{{ bival('pj_type') }}</span>
                <span>{{ bival('pj_place') }}</span><span>{{ bival('pj_status') }}</span>
            </div>
            @foreach ($byYear as $year => $group)
                <div class="px-year" data-year="{{ $year }}" @unless ($group->contains($inType)) hidden @endunless>
                    <h2 class="px-year__label">
                        <span class="px-year__n"><bdi>{{ $year !== '' ? $year : bival('pj_undated') }}</bdi></span>
                        <span class="px-year__count">{{ $group->filter($inType)->count() }}</span>
                    </h2>
                    <ol class="px-rows">
                        @foreach ($group as $p)
                            <li class="px-row" data-categories="{{ implode(' ', $p->categoryKeys()) }}" data-search="{{ mb_strtolower($p->searchText()) }}"
                                data-img="{{ $p->coverThumbUrl() }}" data-pos="{{ $p->coverPosition() }}" data-num="{{ $p->num }}" @unless ($inType($p)) hidden @endunless>
                                <a class="px-row__link" href="{{ $p->pageUrl($lang) }}">
                                    <span class="px-row__num">{{ $p->num }}</span>
                                    <span class="px-row__thumb" aria-hidden="true"><img src="{{ $p->variantUrl($p->coverImage(), 'sm') }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}"></span>
                                    <span class="px-row__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                                    <span class="px-row__type">
                                        <span class="px-row__hatches" aria-hidden="true">@foreach ($kinds($p) as $k)<span class="px-swatch hatch-{{ $hatch[$k] }}"></span>@endforeach</span>
                                        <span class="px-row__typetext">{{ $kinds($p)->map(fn ($k) => $catName[$k])->implode(' · ') }}</span>
                                    </span>
                                    <span class="px-row__place"><bdi>{{ $p->cityLabel() }}</bdi></span>
                                    <span class="px-row__status px-st--{{ $p->statusClass() }}">{{ $p->statusLabel($lang) }}</span>
                                    <span class="px-row__arrow" aria-hidden="true">↗&#xFE0E;</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </section>
        <div class="px-peek" id="pxPeek" aria-hidden="true">
            <img alt="">
            <span class="px-peek__num"></span>
        </div>

        {{-- ---- Sheets: each project framed as a drawing sheet, with the title
             block every drawing carries in its corner. On a computer or
             tablet they are pinned up as a wall across the whole window, on a
             structural grid — its bays lettered across the top and numbered
             down the side, as on a plan (projects.js packs the wall and draws
             the grid). Large projects take two bays by two, wide ones two
             across. On a phone, one sheet under another. ---- --}}
        @php
            $shape = fn ($p) => ['large' => [2, 2, 'l'], 'wide' => [2, 1, 'w']][$p->size] ?? [1, 1, 's'];
            $nextSheet = str_pad((string) ((int) $projects->max(fn ($p) => (int) $p->num) + 1), 2, '0', STR_PAD_LEFT);
        @endphp
        <section class="px-sheets" id="pxSheets" aria-label="{{ bival('pj_sheets') }}" @if ($view !== 'sheets') hidden @endif>
          <div class="px-wall" id="pxWall">
            <div class="px-wall__grid" id="pxWallGrid" aria-hidden="true"></div>
            <ol class="px-sheets__grid" id="pxTiles">
                @foreach ($projects as $p)
                    @php [$w, $h, $s] = $shape($p); @endphp
                    <li class="px-sheet px-sheet--{{ $s }}" data-w="{{ $w }}" data-h="{{ $h }}" data-categories="{{ implode(' ', $p->categoryKeys()) }}" data-search="{{ mb_strtolower($p->searchText()) }}" @unless ($inType($p)) hidden @endunless>
                        <a class="px-sheet__link" href="{{ $p->pageUrl($lang) }}" data-disc="{{ bival('pj_disc_view') }}">
                            <span class="px-sheet__frame">
                                <span class="px-sheet__pic"><img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}"></span>
                                {{-- on the wall's smaller sheets the status rides on the picture --}}
                                <span class="px-sheet__status px-st--{{ $p->statusClass() }}" aria-hidden="true">{{ $p->statusLabel($lang) }}</span>
                            </span>
                            {{-- the title block: the sheet's number tall at the side, the
                                 project across the top, its particulars beneath; on the
                                 wall, the bays it stands in under its number --}}
                            <span class="px-tb">
                                <span class="px-tb__cell px-tb__cell--num"><small>{{ bival('pj_sheet') }}</small><b>{{ $p->num }}</b><small class="px-tb__ref"></small></span>
                                <span class="px-tb__cell px-tb__cell--name">
                                    <span class="px-tb__head"><small>{{ bival('pj_project') }}</small><span class="px-tb__hatches" aria-hidden="true">@foreach ($kinds($p) as $k)<span class="px-swatch hatch-{{ $hatch[$k] }}"></span>@endforeach</span></span>
                                    <b><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></b>
                                </span>
                                <span class="px-tb__cell"><small>{{ bival('pj_place') }}</small><b><bdi>{{ $p->cityLabel() ?: '—' }}</bdi></b></span>
                                <span class="px-tb__cell"><small>{{ bival('pj_year') }}</small><b><bdi>{{ $p->endYear() ?: '—' }}</bdi></b></span>
                                <span class="px-tb__cell px-tb__cell--status"><small>{{ bival('pj_status') }}</small><b class="px-st--{{ $p->statusClass() }}">{{ $p->statusLabel($lang) }}</b></span>
                            </span>
                            {{-- crop marks, at the corners of the whole sheet --}}
                            <i class="px-crop px-crop--tl" aria-hidden="true"></i><i class="px-crop px-crop--tr" aria-hidden="true"></i>
                            <i class="px-crop px-crop--bl" aria-hidden="true"></i><i class="px-crop px-crop--br" aria-hidden="true"></i>
                        </a>
                        <button class="px-heart" type="button" data-save="{{ $p->slug }}" data-save-at="projects" aria-pressed="false" aria-label="{{ bival('save_add') }}">
                            @include('partials.icon-heart')
                        </button>
                    </li>
                @endforeach
                {{-- the last bay: a blank sheet, numbered next, for the visitor's own project --}}
                <li class="px-sheet px-sheet--yours" data-w="1" data-h="1" data-yours>
                    <a class="px-sheet__link" href="{{ site_url('/') }}#contact" data-disc="{{ bival('pj_disc_start') }}">
                        <span class="px-yours">
                            <span class="px-yours__num"><small>{{ bival('pj_sheet') }}</small><b>{{ $nextSheet }}</b></span>
                            <span class="px-yours__title">{{ bival('pj_yours_title') }}</span>
                            <span class="px-yours__text">{{ bival('pj_yours_text') }}</span>
                            <span class="px-yours__cta">{{ bival('pp_cta_button') }} <span class="px-yours__arrow" aria-hidden="true">↗&#xFE0E;</span></span>
                        </span>
                        <i class="px-crop px-crop--tl" aria-hidden="true"></i><i class="px-crop px-crop--tr" aria-hidden="true"></i>
                        <i class="px-crop px-crop--bl" aria-hidden="true"></i><i class="px-crop px-crop--br" aria-hidden="true"></i>
                    </a>
                </li>
            </ol>
          </div>
        </section>
        {{-- the disc that goes with the pointer over the wall: "View", or "Start" on the blank sheet --}}
        <div class="px-disc" id="pxDisc" aria-hidden="true"><span class="px-disc__label"></span><span class="px-disc__arrow">↗&#xFE0E;</span></div>

        <div class="px-empty" id="pxEmpty" @if ($shown > 0) hidden @endif>
            <p>{{ bival('pg_empty') }}</p>
            <a class="pp-act" href="{{ site_url('/projects') }}" id="pxClear">{{ bival('pj_clear') }}</a>
        </div>

        <section class="pp-cta px-cta">
            <p class="pp-label">{{ bival('contact_label') }}</p>
            <h2 class="pp-cta__title">{{ bival('pp_cta_title') }}</h2>
            <p class="pp-cta__text">{{ bival('pp_cta_text') }}</p>
            <div class="pp-cta__actions">
                <a class="pp-btn" href="{{ site_url('/') }}#contact">
                    <span>{{ bival('pp_cta_button') }}</span>
                    <span class="pp-arrow" aria-hidden="true">→</span>
                </a>
                @if ($wa = whatsapp_url(bival('wa_text')))
                    <a class="pp-btn pp-btn--wa" href="{{ $wa }}" target="_blank" rel="noopener" data-wa="projects">
                        @include('partials.icon-whatsapp')
                        <span>{{ bival('wa_cta') }}</span>
                    </a>
                @endif
            </div>
        </section>
    </main>

    @include('partials.page-foot')

    <script src="{{ asset('projects.js') }}?v={{ filemtime(public_path('projects.js')) }}" defer></script>
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
