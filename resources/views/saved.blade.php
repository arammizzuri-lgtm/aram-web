@php
    /**
     * @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects
     * @var list<string> $requested  the slugs the address asked for
     * @var bool $asked  whether the address carried a list at all
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $slugs = $projects->pluck('slug')->all();
    $query = $slugs ? '?p='.implode('.', $slugs) : '';
    $listUrl = site_url('/saved').$query;

    $names = $projects->map(fn ($p) => bi($p->name, $p->name_ku));
    $title = bival('saved_label').' — '.$brand;
    // A shared list's link preview names what is in it, over the first one's cover.
    $ogTitle = $names->isNotEmpty() ? \Illuminate\Support\Str::limit($names->take(3)->implode(' · '), 90).' — '.$brand : $title;
    $ogDescription = \Illuminate\Support\Str::limit(bival('saved_intro_shared').' '.$names->implode(' · '), 160);
    $ogImage = $projects->first()?->socialImageUrl() ?? asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));

    $waText = str_replace('{url}', $listUrl, bival('saved_wa_text'));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    {{-- One visitor's list: nothing for a search engine to keep. --}}
    <meta name="robots" content="noindex, follow">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:url" content="{{ $listUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Whose list is this? The address is the same for the visitor's own
         shortlist and for the copy a relative opens from WhatsApp, and only
         this browser knows which it is. Decided before the page paints, so
         the right heading shows from the start (html.sv-own). An address with
         no list at all shows the visitor's own, if they have one. --}}
    <script>
        (function () {
            try {
                var mine = JSON.parse(localStorage.getItem('am_saved') || '[]');
                var asked = @json($requested);
                if (!Array.isArray(mine)) return;
                if (!{{ $asked ? 'true' : 'false' }} && mine.length) {
                    location.replace(location.pathname + '?p=' + mine.join('.'));
                    return;
                }
                var own = asked.length > 0 && asked.length === mine.length
                    && asked.every(function (s) { return mine.indexOf(s) !== -1; });
                if (own) document.documentElement.classList.add('sv-own');
            } catch (e) { /* storage blocked: shown as a shared list, which still works */ }
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
    <link rel="stylesheet" href="{{ asset('saved.css') }}?v={{ filemtime(public_path('saved.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--saved">

    @include('partials.page-top', ['otherUrl' => site_url('/saved', $other).$query, 'backHref' => site_url('/').'#projects'])

    <main class="sv-page">
        <header class="sv-head">
            <p class="pp-label">{{ bival('saved_label') }}</p>
            <h1 class="sv-title sv-title--own">{{ bival('saved_title') }}</h1>
            <h1 class="sv-title sv-title--shared">{{ bival('saved_title_shared') }}</h1>
            @if ($projects->isNotEmpty())
                <p class="sv-intro sv-intro--own">{{ bival('saved_intro') }}</p>
                <p class="sv-intro sv-intro--shared">{{ bival('saved_intro_shared') }}</p>
            @endif
        </header>

        @if ($projects->isEmpty())
            <div class="sv-empty">
                <p>{{ bival('saved_empty') }}</p>
                <a class="pp-btn" href="{{ site_url('/') }}#projects">
                    <span>{{ bival('saved_browse') }}</span>
                    <span class="pp-arrow" aria-hidden="true">→</span>
                </a>
            </div>
        @else
            <ul class="sv-grid">
                @foreach ($projects as $p)
                    <li class="sv-card" data-slug="{{ $p->slug }}">
                        <a class="pp-card" href="{{ $p->pageUrl($lang) }}">
                            <span class="pp-card__img">
                                <img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}">
                            </span>
                            <span class="pp-card__num">{{ $p->num }}</span>
                            <span class="pp-card__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                            <span class="pp-card__meta">{!! collect([...$p->categoryLabelsIn($lang), $p->cityLabel(), $p->endYear()])->filter()->map(fn ($part) => '<bdi>'.e($part).'</bdi>')->implode(' · ') !!}</span>
                        </a>
                        <button class="sv-heart" type="button" data-save="{{ $p->slug }}" data-save-at="saved" aria-pressed="false" aria-label="{{ bival('save_add') }}">
                            @include('partials.icon-heart')
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- Send the list on: to the studio (the homepage form, with the list
                 attached), to family on WhatsApp, or anywhere else. On the
                 visitor's own list these follow the hearts (saved.js). --}}
            <div class="sv-actions">
                <a class="pp-btn" href="{{ site_url('/') }}?saved={{ implode('.', $slugs) }}#contact" data-saved-ask data-base="{{ site_url('/') }}">
                    <span>{{ bival('saved_ask') }}</span>
                    <span class="pp-arrow" aria-hidden="true">→</span>
                </a>
                <a class="pp-btn pp-btn--wa" href="https://wa.me/?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener"
                   data-saved-wa data-template="{{ bival('saved_wa_text') }}" data-share="whatsapp" data-share-at="saved">
                    @include('partials.icon-whatsapp')
                    <span>{{ bival('share_wa') }}</span>
                </a>
                @include('partials.share-link', ['shareClass' => 'pp-act', 'shareAt' => 'saved', 'shareProject' => '',
                    'shareUrl' => $listUrl, 'shareTitle' => $title])
                <button class="pp-act sv-keep" type="button" data-saved-keep>
                    @include('partials.icon-heart')
                    <span>{{ bival('saved_keep') }}</span>
                </button>
            </div>
        @endif
    </main>

    @include('partials.page-foot')

    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
