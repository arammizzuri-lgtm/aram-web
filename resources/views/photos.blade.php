@php
    /**
     * Room by room (PhotosController): the same room from every project.
     *
     * @var \Illuminate\Support\Collection<int, array{room: string, img: string, project: \App\Models\Project}> $photos  those shown
     * @var int $total  every photograph with its room
     * @var \Illuminate\Support\Collection<string, int> $counts
     * @var \Illuminate\Support\Collection<int, string> $rooms
     * @var string $room  the room asked for, or ''
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $here = $room !== '' ? \App\Support\Rooms::label($room, $lang) : null;
    $title = ($here ?: bival('ph_title')).' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('ph_intro'), 160);
    $count = fn (int $n) => str_replace('{n}', (string) $n, bival('ph_count'));
    $with = fn (string $r, ?string $l = null) => site_url('/photos', $l).($r !== '' ? '?room='.$r : '');
    $viewer = $photos->map(fn ($ph) => [
        'src' => $ph['project']->variantUrl($ph['img'], 'lg'),
        'name' => bi($ph['project']->name, $ph['project']->name_ku),
        'room' => \App\Support\Rooms::label($ph['room'], $lang),
        'url' => $ph['project']->pageUrl($lang),
    ])->values();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @include('partials.seo-alternates', ['path' => '/photos'])
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($photos->isNotEmpty())<meta property="og:image" content="{{ $viewer[0]['src'] }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('photos.css') }}?v={{ filemtime(public_path('photos.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--photos">

    @include('partials.page-top', ['otherUrl' => $with($room, $other)])

    <main class="ph">
        <header class="ph-head">
            <p class="pp-label">{{ bival('ph_label') }}</p>
            <h1 class="ph-title">{{ bival('ph_title') }}</h1>
            <p class="ph-intro">{{ bival('ph_intro') }}</p>
        </header>

        @if ($total === 0)
            <div class="ph-empty">
                <p>{{ bival('ph_empty') }}</p>
                <a class="pp-btn" href="{{ site_url('/projects') }}"><span>{{ bival('nf_all') }}</span><span class="pp-arrow" aria-hidden="true">→</span></a>
            </div>
        @else
            {{-- the rooms, each with how many: a link each, so it works without JavaScript --}}
            <nav class="ph-rooms" aria-label="{{ bival('ph_label') }}">
                <a class="ph-room{{ $room === '' ? ' is-on' : '' }}" href="{{ $with('') }}" @if ($room === '') aria-current="true" @endif>{{ bival('ph_all') }}<sup>{{ $total }}</sup></a>
                @foreach ($rooms as $r)
                    <a class="ph-room{{ $room === $r ? ' is-on' : '' }}" href="{{ $with($r) }}" @if ($room === $r) aria-current="true" @endif>{{ \App\Support\Rooms::label($r, $lang) }}<sup>{{ $counts[$r] }}</sup></a>
                @endforeach
            </nav>
            <p class="ph-count">{{ $count($photos->count()) }}@if ($here) · <bdi>{{ $here }}</bdi>@endif</p>

            <ul class="ph-wall" id="phWall" data-photos='@json($viewer)'>
                @foreach ($photos as $i => $ph)
                    @php $p = $ph['project']; @endphp
                    <li class="ph-shot">
                        <button class="ph-shot__open" type="button" data-at="{{ $i }}" aria-label="{{ bi($p->name, $p->name_ku) }} — {{ \App\Support\Rooms::label($ph['room'], $lang) }}">
                            <img src="{{ $p->variantUrl($ph['img'], 'thumb') }}" alt="" loading="lazy" decoding="async">
                        </button>
                        <p class="ph-shot__cap">
                            <a href="{{ $p->pageUrl($lang) }}"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></a>
                            @if ($room === '')<span class="ph-shot__room">{{ \App\Support\Rooms::label($ph['room'], $lang) }}</span>@endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        <section class="pp-cta">
            <p class="pp-label">{{ bival('contact_label') }}</p>
            <h2 class="pp-cta__title">{{ bival('pp_cta_title') }}</h2>
            <p class="pp-cta__text">{{ bival('pp_cta_text') }}</p>
            <div class="pp-cta__actions">
                <a class="pp-btn" href="{{ site_url('/') }}#contact"><span>{{ bival('pp_cta_button') }}</span><span class="pp-arrow" aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    @include('partials.page-foot')

    {{-- a photograph large, with the project it is from (photos.js) --}}
    <dialog class="ph-viewer" id="phViewer" aria-label="{{ bival('ph_title') }}">
        <img class="ph-viewer__img" alt="">
        <p class="ph-viewer__cap"><span class="ph-viewer__room"></span> <a class="ph-viewer__link" href="#"><bdi class="ph-viewer__name"></bdi> <span>· {{ bival('ph_open') }} ↗&#xFE0E;</span></a></p>
        <button class="pp-lightbox__btn ph-viewer__close" type="button" aria-label="Close">✕</button>
        <button class="pp-lightbox__btn ph-viewer__prev" type="button" aria-label="Previous">←</button>
        <button class="pp-lightbox__btn ph-viewer__next" type="button" aria-label="Next">→</button>
    </dialog>

    <script src="{{ asset('photos.js') }}?v={{ filemtime(public_path('photos.js')) }}" defer></script>
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
