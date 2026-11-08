@php
    /** @var \App\Models\ProjectUpdate $post */
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectUpdate> $build */
    $lang = site_lang();
    $project = $post->project;
    $projectName = $project->tr('name');
    $headline = $post->title($lang) ?: $projectName;
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = $headline.' — '.$projectName.' — '.$brand;
    $note = (string) $post->note($lang);
    $description = \Illuminate\Support\Str::limit($note !== '' ? preg_replace('/\s+/u', ' ', $note) : $projectName, 160);
    $photos = $post->imageUrls('lg');
    $thumbs = $post->imageUrls('thumb');
    $path = '/journal/'.$post->slug;
    $step = $build->search(fn ($p) => $p->id === $post->id);
    $schema = ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $headline,
        'datePublished' => $post->date->toDateString(), 'dateModified' => $post->updated_at?->toAtomString(),
        'url' => site_url($path), 'inLanguage' => $lang, 'image' => $photos ?: null, 'description' => $description,
        'author' => \App\Support\Architect::reference(), 'publisher' => ['@id' => url('/').'#studio'],
        'about' => ['@type' => 'CreativeWork', 'name' => $project->name, 'url' => $project->hasPage() ? $project->pageUrl('en') : null],
        'isPartOf' => ['@type' => 'Blog', 'name' => 'Build journal', 'url' => site_url('/journal')]];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => $path, 'css' => 'journal.css', 'type' => 'article', 'image' => $photos[0] ?? null])
</head>
<body class="pp pp--journal">
    @include('partials.page-top', ['otherUrl' => null])

    <main class="jr-post">
        <nav class="jr-crumbs" aria-label="Breadcrumb">
            <a href="{{ site_url('/journal') }}">{{ bival('journal_label') }}</a>
            <span aria-hidden="true">/</span>
            <span><bdi>{{ $projectName }}</bdi></span>
        </nav>

        <header class="jr-post__head">
            <p class="jr-post__meta">
                <time datetime="{{ $post->date->toDateString() }}">{{ site_date($post->date, $lang) }}</time>
                @if ($build->count() > 1)
                    <span>· {{ $step + 1 }} / {{ $build->count() }}</span>
                @endif
            </p>
            <h1 class="jr-post__title" dir="auto">{{ $headline }}</h1>
            <p class="jr-post__project"><bdi>{{ $projectName }}</bdi>@if ($project->tr('location')) · <bdi>{{ $project->tr('location') }}</bdi>@endif</p>
        </header>

        @if ($note !== '')
            <div class="jr-post__text" dir="auto">
                @foreach (preg_split('/\R{2,}/u', trim($note)) as $para)
                    <p>{!! nl2br(e($para)) !!}</p>
                @endforeach
            </div>
        @endif

        @if ($photos)
            {{-- the site photographs; each opens at full size --}}
            <div class="jr-photos jr-photos--{{ min(count($photos), 3) }}">
                @foreach ($photos as $i => $src)
                    <a class="jr-photo" href="{{ $src }}" target="_blank" rel="noopener">
                        <img src="{{ $thumbs[$i] ?? $src }}" alt="{{ $headline }} — {{ $i + 1 }}" loading="{{ $i < 2 ? 'eager' : 'lazy' }}" decoding="async">
                    </a>
                @endforeach
            </div>
        @endif

        <div class="jr-post__actions">
            @if ($project->hasPage())
                <a class="pp-btn" href="{{ $project->pageUrl($lang) }}">{{ bival('journal_project') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
            @endif
            @include('partials.share-link', ['shareClass' => 'pp-btn pp-btn--line', 'shareAt' => 'journal', 'shareProject' => $project->slug,
                'shareUrl' => site_url($path), 'shareTitle' => $headline])
        </div>

        {{-- the whole build, step by step, this entry marked --}}
        @if ($build->count() > 1)
            <section class="jr-build">
                <h2 class="pp-label">{{ bival('journal_more') }}</h2>
                <ol class="jr-build__list">
                    @foreach ($build as $b)
                        <li class="jr-build__step{{ $b->id === $post->id ? ' is-here' : '' }}">
                            <a href="{{ $b->journalUrl() }}" @if ($b->id === $post->id) aria-current="page" @endif>
                                <time datetime="{{ $b->date->toDateString() }}">{{ site_date($b->date, $lang) }}</time>
                                <span dir="auto">{{ $b->title($lang) ?: $projectName }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <p class="jr-post__back"><a href="{{ site_url('/journal') }}"><span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span> {{ bival('journal_all') }}</a></p>
    </main>

    @include('partials.page-foot')
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
</body>
</html>
