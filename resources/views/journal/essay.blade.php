{{-- An essay written in the admin (App\Models\Essay): headed as the journal's essays are, its cover photograph,
     then its sections, drawn by the heritage pages' own blocks (heritage/blocks, landmark.css, landmark.js:
     the sections drifting in, the figures counting, the lightbox). A draft shows only through the signed preview. --}}
@php
    /** @var \App\Models\Essay $essay */
    $l = $essay;   // the heritage blocks call their page $l
    $lang = site_lang();
    $preview = $preview ?? false;
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $headline = $essay->tr('title');
    $title = $headline.' — '.$brand;
    $description = \Illuminate\Support\Str::limit($essay->tr('summary') ?: $headline, 160);
    $path = '/journal/'.$essay->slug;
    $kind = bi('Essay', 'وتار', 'مقال');
    $cover = $essay->imageUrl('lg') ? url($essay->imageUrl('lg')) : null;
    $schema = array_filter(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $headline,
        'datePublished' => $essay->date->toDateString(), 'dateModified' => $essay->updated_at?->toAtomString(), 'url' => site_url($path),
        'inLanguage' => $lang, 'description' => $description, 'image' => $cover,
        'author' => \App\Support\Architect::reference(), 'publisher' => ['@id' => url('/').'#studio'],
        'isPartOf' => ['@type' => 'Blog', 'name' => 'Journal', 'url' => site_url('/journal')]]);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => $path, 'css' => 'journal.css', 'type' => 'article', 'image' => $cover])
    <link rel="stylesheet" href="{{ asset('landmark.css') }}?v={{ filemtime(public_path('landmark.css')) }}">
    <link rel="stylesheet" href="{{ asset('journal-essay.css') }}?v={{ filemtime(public_path('journal-essay.css')) }}">
    @if ($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
</head>
<body class="pp pp--journal">
    @include('partials.page-top', ['otherUrl' => null])

    <main class="ja">
        @if ($preview)
            <p class="lm-preview">{{ bi('Preview — this essay is not published yet', 'پێشبینین — ئەم وتارە هێشتا بڵاونەکراوەتەوە', 'معاينة — هذا المقال لم يُنشر بعد') }}</p>
        @endif
        <nav class="jr-crumbs" aria-label="Breadcrumb">
            <a href="{{ site_url('/journal') }}">{{ bival('journal_label') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $kind }}</span>
        </nav>

        <header class="ja-head">
            <p class="jr-post__meta">
                <span>{{ $kind }}</span>
                <span>· <time datetime="{{ $essay->date->toDateString() }}">{{ site_date($essay->date, $lang) }}</time></span>
            </p>
            <h1 class="jr-post__title"{{ text_dir($headline) }}>{{ $headline }}</h1>
            @if (filled($essay->tr('summary')))
                <p class="ja-head__lead"{{ text_dir($essay->tr('summary')) }}>{{ $essay->tr('summary') }}</p>
            @endif
        </header>

        @if ($essay->image)
            <figure class="je-cover">
                @include('heritage.img', ['img' => $essay->images()[0], 'size' => 'lg', 'sizes' => '(min-width: 1240px) 1180px, 100vw', 'eager' => true, 'lightbox' => true, 'alt' => $headline])
                @if (filled($essay->image_credit))
                    <figcaption class="lm-fig__cap">@include('heritage.credit', ['img' => $essay->images()[0]])</figcaption>
                @endif
            </figure>
        @endif

        <div class="lm-body">
            @foreach ($essay->sections() as $i => ['type' => $type, 'data' => $data])
                @include('heritage.blocks.'.$type, ['d' => $data, 'n' => $i])
            @endforeach
        </div>

        <div class="jr-post__actions">
            @include('partials.share-link', ['shareClass' => 'pp-btn pp-btn--line', 'shareAt' => 'journal', 'shareUrl' => site_url($path), 'shareTitle' => $headline])
        </div>
        <p class="jr-post__back"><a href="{{ site_url('/journal') }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('journal_all') }}</a></p>
    </main>

    @include('partials.page-foot')

    {{-- the lightbox every photograph opens in (landmark.js) --}}
    <dialog class="lm-lb" id="lmLightbox" aria-label="{{ $headline }}">
        <button type="button" class="lm-lb__close" data-lb-close aria-label="{{ bival('landmarks_lb_close') }}">×</button>
        <button type="button" class="lm-lb__arrow lm-lb__arrow--prev" data-lb-prev aria-label="{{ bival('landmarks_lb_prev') }}"><span class="pp-arrow">←</span></button>
        <figure class="lm-lb__fig">
            <img class="lm-lb__img" src="" alt="">
            <figcaption class="lm-lb__cap"><span class="lm-lb__text" dir="auto"></span><span class="lm-lb__credit"></span><span class="lm-lb__n"></span></figcaption>
        </figure>
        <button type="button" class="lm-lb__arrow lm-lb__arrow--next" data-lb-next aria-label="{{ bival('landmarks_lb_next') }}"><span class="pp-arrow">→</span></button>
    </dialog>

    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    <script src="{{ asset('landmark.js') }}?v={{ filemtime(public_path('landmark.js')) }}" defer></script>
</body>
</html>
