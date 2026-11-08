@use('App\Support\Architect')
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects */
    $lang = site_lang();
    $isKu = site_rtl($lang);   // right to left: Kurdish or Arabic
    $other = $isKu ? 'en' : 'ku';
    $path = Architect::PATH;

    $title = strip_tags(bival('arch_seo_title'));
    $description = \Illuminate\Support\Str::limit(trim(strip_tags(bival('arch_seo_description'))), 160);
    $portrait = Architect::portrait();
    $ogImage = $portrait ?: asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));

    $bio = Architect::bio($lang);
    $figures = array_filter(Architect::rows('arch_figures', 2, $lang), fn ($f) => $f[0] !== '');
    $timeline = array_filter(Architect::rows('arch_timeline', 4, $lang), fn ($s) => $s[0] !== '' || $s[1] !== '');
    $n = 1;   // the sections are numbered as they appear, like the sheets of a set
    $lists = array_filter([
        'expertise' => [bival('arch_expertise_title'), Architect::lines('arch_expertise', $lang)],
        'education' => [bival('arch_education_title'), Architect::lines('arch_education', $lang)],
        'awards' => [bival('arch_awards_title'), Architect::lines('arch_awards', $lang)],
        'press' => [bival('arch_press_title'), Architect::lines('arch_press', $lang)],
    ], fn ($l) => $l[1]);

    // A line that ends in a web address links to it ("Interview — ArchDaily https://…").
    $item = function (string $line) {
        if (preg_match('#^(.*?)\s*(https?://\S+)$#u', $line, $m)) {
            return '<a href="'.e($m[2]).'" target="_blank" rel="noopener">'.e($m[1] !== '' ? $m[1] : $m[2]).'</a>';
        }

        return e($line);
    };

    $person = Architect::person();
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'ProfilePage',
                '@id' => site_url($path).'#page',
                'url' => site_url($path),
                'name' => $title,
                'inLanguage' => $lang,
                'mainEntity' => ['@id' => Architect::id()],
                'hasPart' => $projects->map(fn ($p) => [
                    '@type' => 'CreativeWork', '@id' => site_url('/projects/'.$p->slug, 'en').'#project',
                    'name' => $p->name, 'url' => $p->pageUrl($lang), 'author' => ['@id' => Architect::id()],
                ])->all(),
            ],
            $person,
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Aram Mizuri Architecture', 'item' => site_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => strip_tags(bival('arch_name')), 'item' => site_url($path)],
                ],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @include('partials.seo-alternates', ['path' => $path])
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ site_url($path) }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="profile:first_name" content="{{ $person['givenName'] ?? '' }}">
    <meta property="profile:last_name" content="{{ $person['familyName'] ?? '' }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('architect.css') }}?v={{ filemtime(public_path('architect.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--architect">

    @include('partials.page-top', ['otherUrl' => site_url($path, $other)])

    <main>
        {{-- Who he is. The portrait as large as the screen allows and never
             cropped: its own black ground runs into the page's, so it stands
             free of any frame. Beside it, the name in two lines and the role
             in one quiet line, as the big practices set a founder. --}}
        <section class="ar-hero{{ $portrait ? '' : ' ar-hero--text' }}">
            @if ($portrait)
                <figure class="ar-portrait">
                    @php $portraitSet = Architect::portraitSrcset(); @endphp
                    <img src="{{ $portrait }}" @if ($portraitSet) srcset="{{ $portraitSet }}" sizes="(max-width: 900px) 100vw, 58vw" @endif alt="{{ strip_tags(bival('arch_name')) }} — {{ strip_tags(bival('arch_label')) }}" fetchpriority="high">
                    <figcaption>{{ bival('about_portrait_caption') }}</figcaption>
                </figure>
            @endif
            <div class="ar-hero__text">
                <nav class="ar-crumbs" aria-label="Breadcrumb">
                    <a href="{{ site_url('/') }}">{{ bival('careers_home') }}</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page">{{ bival('nav_about') }}</span>
                </nav>
                <p class="pp-label">{{ bival('arch_label') }}</p>
                <h1 class="ar-hero__name">
                    @foreach (preg_split('/\s+/u', trim(strip_tags(bival('arch_name')))) as $word)
                        <span>{{ $word }}</span>
                    @endforeach
                </h1>
                <p class="ar-hero__role">{{ bival('arch_role') }}</p>
                @if (filled(bival('arch_based')))
                    <p class="ar-hero__based"><span class="ar-dot" aria-hidden="true"></span>{{ bival('arch_based') }}</p>
                @endif
                <div class="ar-hero__actions">
                    <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('arch_cta') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                    <a class="ar-hero__more" href="#profile">{{ bival('arch_story_title') }} <span aria-hidden="true">↓</span></a>
                </div>
            </div>
        </section>

        {{-- The figures, as the title block along the foot of a sheet --}}
        @if ($figures)
            <dl class="ar-strip">
                @foreach ($figures as [$number, $what])
                    <div class="ar-strip__cell">
                        <dt>{{ $number }}</dt>
                        <dd dir="auto">{{ $what }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        {{-- 01 — his story, in his words --}}
        <section class="ar-sec" id="profile">
            <header class="ar-sec__head">
                <span class="ar-sec__num">{{ str_pad((string) $n++, 2, '0', STR_PAD_LEFT) }}</span>
                <h2 class="ar-sec__title">{{ bival('arch_story_title') }}</h2>
            </header>
            <div class="ar-sec__body">
                @foreach ($bio as $i => $paragraph)
                    <p class="ar-bio{{ $i === 0 ? ' ar-bio--lede' : '' }}" dir="auto">{{ $paragraph }}</p>
                @endforeach
            </div>
        </section>

        {{-- his own words, given the whole width --}}
        @if (filled(bival('about_quote')))
            <figure class="ar-quote">
                <blockquote><p dir="auto">{{ bival('about_quote') }}</p></blockquote>
                <figcaption dir="auto">{{ bival('about_quote_cite') }}</figcaption>
            </figure>
        @endif

        {{-- 02 — the career as an index, the latest first --}}
        @if ($timeline)
            <section class="ar-sec">
                <header class="ar-sec__head">
                    <span class="ar-sec__num">{{ str_pad((string) $n++, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="ar-sec__title">{{ bival('arch_timeline_title') }}</h2>
                </header>
                <ol class="ar-sec__body ar-index" reversed>
                    @foreach (array_reverse($timeline) as $i => [$when, $role, $where, $what])
                        <li class="ar-row{{ $i === 0 ? ' is-latest' : '' }}">
                            <span class="ar-row__when" dir="auto">{{ $when }}</span>
                            <div class="ar-row__main">
                                <h3 class="ar-row__role" dir="auto">{{ $role }}</h3>
                                @if ($where !== '')<p class="ar-row__where" dir="auto">{{ $where }}</p>@endif
                            </div>
                            @if ($what !== '')<p class="ar-row__what" dir="auto">{{ $what }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- 03 — the record: what he works on, his education, and — once
             written in Site Content — awards, publications and talks --}}
        @if ($lists)
            <section class="ar-sec">
                <header class="ar-sec__head">
                    <span class="ar-sec__num">{{ str_pad((string) $n++, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="ar-sec__title">{{ bival('arch_record_title') }}</h2>
                </header>
                <div class="ar-sec__body ar-record">
                    @foreach ($lists as $key => [$heading, $items])
                        <div class="ar-record__col ar-record__col--{{ $key }}">
                            <h3 class="pp-label">{{ $heading }}</h3>
                            <ul class="ar-list">
                                @foreach ($items as $line)
                                    <li dir="auto">{!! $item($line) !!}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($projects->isNotEmpty())
            <section class="ar-sec ar-sec--wide">
                <header class="ar-sec__head">
                    <span class="ar-sec__num">{{ str_pad((string) $n++, 2, '0', STR_PAD_LEFT) }}</span>
                    <h2 class="ar-sec__title">{{ bival('arch_projects_title') }}</h2>
                    <a class="ar-projects__all" href="{{ site_url('/projects') }}">{{ bival('arch_all_projects') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                </header>
                <div class="pp-more__grid ar-sec__body">
                    @foreach ($projects as $p)
                        <a class="pp-card" href="{{ $p->pageUrl($lang) }}">
                            <span class="pp-card__img">
                                <img src="{{ $p->coverThumbUrl() }}" alt="{{ $p->name }} — {{ strip_tags(bival('arch_credit')) }}: {{ strip_tags(bival('arch_name')) }}" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                            </span>
                            <span class="pp-card__num">{{ $p->num }}</span>
                            <span class="pp-card__name"><bdi>{{ bi($p->name, $p->name_ku, $p->name_ar) }}</bdi></span>
                            <span class="pp-card__meta">{!! collect([...$p->categoryLabelsIn($lang), $p->cityLabel(), $p->endYear()])->filter()->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ar-cta">
            <p class="ar-cta__name">{{ bival('arch_name') }}</p>
            <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('arch_cta') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
        </section>
    </main>

    @include('partials.page-foot')
</body>
</html>
