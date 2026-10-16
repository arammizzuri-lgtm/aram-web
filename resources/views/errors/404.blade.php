@php
    /*
     * Page not found — a mistyped address, an old link, a project shared on
     * WhatsApp and taken down since. Rather than a dead end: the projects to
     * search, a few to start from, and the way home, in the language of the
     * address (/ku/… in Kurdish, even where no route ran to say so).
     */
    if (request()->is('ku', 'ku/*')) {
        app()->setLocale('ku');
    }
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    // the studio's work in its own order — never in the way of the page itself
    $picks = rescue(fn () => \App\Models\Project::publishedOrdered()->get()->filter(fn ($p) => $p->hasPage())->take(4)->values(), collect(), false);
    $wa = whatsapp_url(bival('wa_text'));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ bival('nf_title') }} — {{ $brand }}</title>
    <meta name="robots" content="noindex">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('not-found.css') }}?v={{ filemtime(public_path('not-found.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--not-found">

    @include('partials.page-top', ['otherUrl' => site_url('/', $other), 'markHere' => false])

    <main class="nf">
        {{-- the number, drawn in outline with its dimension line, as a sheet's would be --}}
        <div class="nf-code" aria-hidden="true">
            <span class="nf-code__n">404</span>
            <span class="nf-code__dim"><i></i><b></b><i></i></span>
        </div>
        <p class="pp-label">{{ bival('nf_label') }}</p>
        <h1 class="nf-title">{{ bival('nf_title') }}</h1>
        <p class="nf-text">{{ bival('nf_text') }}</p>

        <form class="nf-find" action="{{ site_url('/projects') }}" method="get" role="search">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.5"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="search" name="q" placeholder="{{ bival('pj_search') }}" aria-label="{{ bival('pj_search') }}" autocomplete="off" dir="auto">
            <button type="submit">{{ bival('nf_search') }} <span class="pp-arrow" aria-hidden="true">→</span></button>
        </form>

        <div class="nf-ways">
            <a class="pp-btn" href="{{ site_url('/projects') }}">
                <span>{{ bival('nf_all') }}</span>
                <span class="pp-arrow" aria-hidden="true">→</span>
            </a>
            <a class="pp-act" href="{{ site_url('/') }}">{{ bival('careers_home') }}</a>
            @if ($wa)
                <a class="pp-act pp-act--wa" href="{{ $wa }}" target="_blank" rel="noopener" data-wa="not-found">
                    @include('partials.icon-whatsapp')
                    <span>{{ bival('wa_cta') }}</span>
                </a>
            @endif
        </div>

        @if ($picks->isNotEmpty())
            <section class="nf-picks" aria-labelledby="nfPicks">
                <h2 class="pp-label" id="nfPicks">{{ bival('nf_picks') }}</h2>
                <ul class="nf-picks__grid">
                    @foreach ($picks as $p)
                        <li>
                            <a class="nf-pick" href="{{ $p->pageUrl($lang) }}">
                                <span class="nf-pick__pic"><img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}"></span>
                                <span class="nf-pick__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                                <span class="nf-pick__meta"><bdi>{{ collect([$p->cityLabel(), $p->endYear()])->filter()->implode(' · ') }}</bdi></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>

    @include('partials.page-foot')

    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
