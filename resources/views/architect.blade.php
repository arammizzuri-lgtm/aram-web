@use('App\Support\Architect')
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $path = Architect::PATH;

    $title = strip_tags(bival('arch_seo_title'));
    $description = \Illuminate\Support\Str::limit(trim(strip_tags(bival('arch_seo_description'))), 160);
    $portrait = Architect::portrait();
    $ogImage = $portrait ?: asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));

    $bio = Architect::bio($lang);
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
        {{-- Who he is: name, role and portrait, then his story in his words --}}
        <section class="ar-hero">
            <div class="ar-hero__text">
                <p class="pp-label">{{ bival('arch_label') }}</p>
                <h1 class="ar-hero__name">{{ bival('arch_name') }}</h1>
                <p class="ar-hero__role">{{ bival('arch_role') }}</p>
                @foreach ($bio as $paragraph)
                    <p class="ar-hero__bio" dir="auto">{{ $paragraph }}</p>
                @endforeach
                @if (filled(bival('about_quote')))
                    <blockquote class="ar-quote">
                        <p dir="auto">{{ bival('about_quote') }}</p>
                        <cite dir="auto">{{ bival('about_quote_cite') }}</cite>
                    </blockquote>
                @endif
            </div>
            @if ($portrait)
                <figure class="ar-hero__portrait">
                    <img src="{{ $portrait }}" alt="{{ strip_tags(bival('arch_name')) }} — {{ strip_tags(bival('arch_label')) }}" width="720" height="900" fetchpriority="high">
                    <figcaption>{{ bival('about_portrait_caption') }}</figcaption>
                </figure>
            @endif
        </section>

        {{-- The record: what he works on, and — once written in Site Content —
             his education, awards, publications and talks. --}}
        @if ($lists)
            <section class="ar-record">
                @foreach ($lists as $key => [$heading, $items])
                    <div class="ar-record__col ar-record__col--{{ $key }}">
                        <h2 class="pp-label">{{ $heading }}</h2>
                        <ul class="ar-list">
                            @foreach ($items as $line)
                                <li dir="auto">{!! $item($line) !!}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($projects->isNotEmpty())
            <section class="pp-more ar-projects">
                <div class="ar-projects__head">
                    <h2 class="pp-label">{{ bival('arch_projects_title') }}</h2>
                    <a class="ar-projects__all" href="{{ site_url('/projects') }}">{{ bival('arch_all_projects') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
                </div>
                <div class="pp-more__grid">
                    @foreach ($projects as $p)
                        <a class="pp-card" href="{{ $p->pageUrl($lang) }}">
                            <span class="pp-card__img">
                                <img src="{{ $p->coverThumbUrl() }}" alt="{{ $p->name }} — {{ strip_tags(bival('arch_credit')) }}: {{ strip_tags(bival('arch_name')) }}" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                            </span>
                            <span class="pp-card__num">{{ $p->num }}</span>
                            <span class="pp-card__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                            <span class="pp-card__meta">{!! collect([...$p->categoryLabelsIn($lang), $p->cityLabel(), $p->endYear()])->filter()->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ar-cta">
            <a class="pp-btn" href="{{ site_url('/') }}#contact">{{ bival('arch_cta') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
        </section>
    </main>

    @include('partials.page-foot')
</body>
</html>
