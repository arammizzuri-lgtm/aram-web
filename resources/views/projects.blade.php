@php
    /**
     * @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects
     * @var \Illuminate\Support\Collection<int, \App\Models\Category> $categories
     * @var \Illuminate\Support\Collection<string, int> $counts  projects per category key
     * @var string $type  the category asked for in the address, or ''
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('pj_title').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('pj_intro'), 160);
    $ogImage = $projects->first()?->socialImageUrl() ?? asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));
    $count = fn (int $n) => $n === 1 ? bival('pj_count_one') : str_replace('{n}', (string) $n, bival('pj_count'));
    $shown = $type === '' ? $projects->count() : $projects->filter(fn ($p) => in_array($type, $p->categoryKeys(), true))->count();

    // the projects, in order, as a list search engines can read
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

    @include('partials.page-top', ['otherUrl' => site_url('/projects', $other).($type ? '?type='.$type : ''), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home')])

    <main class="pj-page">
        <header class="pj-head">
            <p class="pp-label">{{ bival('pj_label') }}</p>
            <h1 class="pj-title">{{ bival('pj_title') }}</h1>
            <p class="pj-intro">{{ bival('pj_intro') }}</p>
        </header>

        {{-- The categories are links, so a filtered view has an address of its
             own; projects.js answers them at once, and adds the search. --}}
        <div class="pj-tools" id="pjTools" data-count="{{ bival('pj_count') }}" data-count-one="{{ bival('pj_count_one') }}">
            <nav class="pj-filter" aria-label="{{ bival('pg_title') }}">
                <a class="pj-chip{{ $type === '' ? ' is-active' : '' }}" href="{{ site_url('/projects') }}" data-type="" @if ($type === '') aria-current="true" @endif>
                    {{ bival('filter_all') }} <span class="pj-chip__n">{{ $projects->count() }}</span>
                </a>
                @foreach ($categories as $cat)
                    <a class="pj-chip{{ $type === $cat->key ? ' is-active' : '' }}" href="{{ site_url('/projects') }}?type={{ $cat->key }}" data-type="{{ $cat->key }}"
                       @if ($type === $cat->key) aria-current="true" @endif><bdi>{{ bi($cat->name, $cat->name_ku) }}</bdi> <span class="pj-chip__n">{{ $counts[$cat->key] }}</span></a>
                @endforeach
            </nav>
            <label class="pj-search">
                <span class="pj-search__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                </span>
                <input type="search" id="pjSearch" placeholder="{{ bival('pj_search') }}" aria-label="{{ bival('pj_search') }}" autocomplete="off" dir="auto">
            </label>
        </div>
        <p class="pj-count" id="pjCount" aria-live="polite">{{ $count($shown) }}</p>

        <ul class="pj-grid" id="pjGrid">
            @foreach ($projects as $p)
                @php $inType = $type === '' || in_array($type, $p->categoryKeys(), true); @endphp
                <li class="pj-card" data-categories="{{ implode(' ', $p->categoryKeys()) }}" data-search="{{ mb_strtolower($p->searchText()) }}" @unless ($inType) hidden @endunless>
                    <a class="pp-card" href="{{ $p->pageUrl($lang) }}">
                        <span class="pp-card__img">
                            <img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                            <span class="pj-status pj-status--{{ $p->statusClass() }}">{{ $p->statusLabel($lang) }}</span>
                        </span>
                        <span class="pp-card__num">{{ $p->num }}</span>
                        <span class="pp-card__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                        <span class="pp-card__meta">{!! collect([...$p->categoryLabelsIn($lang), $p->cityLabel(), $p->endYear()])->filter()->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</span>
                    </a>
                    <button class="pj-heart" type="button" data-save="{{ $p->slug }}" data-save-at="projects" aria-pressed="false" aria-label="{{ bival('save_add') }}">
                        @include('partials.icon-heart')
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="pj-empty" id="pjEmpty" @if ($shown > 0) hidden @endif>
            <p>{{ bival('pg_empty') }}</p>
            <a class="pp-act" href="{{ site_url('/projects') }}" data-type="" id="pjClear">{{ bival('pj_clear') }}</a>
        </div>

        <section class="pp-cta pj-cta">
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
