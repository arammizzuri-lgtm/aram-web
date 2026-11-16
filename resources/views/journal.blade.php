@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectUpdate> $posts */
    /** @var \Illuminate\Support\Collection<int, array> $articles  the essays (App\Support\Articles) */
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('journal_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('journal_intro'), 160);
    $cover = fn ($post) => $post->imageUrls('thumb')[0] ?? $post->project?->coverThumbUrl();
    // the build entries and the essays, as one list, newest first
    $entries = $posts->map(fn ($p) => ['date' => $p->date, 'post' => $p])
        ->concat($articles->map(fn ($a) => ['date' => \Illuminate\Support\Carbon::parse($a['date']), 'article' => $a]))
        ->sortByDesc(fn ($e) => $e['date']->getTimestamp())->values();
    $schema = ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => bival('journal_label'), 'url' => site_url('/journal'),
        'inLanguage' => $lang, 'publisher' => ['@id' => url('/').'#studio'],
        'blogPost' => $entries->take(20)->map(fn ($e) => isset($e['post'])
            ? ['@type' => 'BlogPosting', 'headline' => $e['post']->title($lang) ?: $e['post']->project->tr('name'),
                'url' => $e['post']->journalUrl(), 'datePublished' => $e['date']->toDateString()]
            : ['@type' => 'Article', 'headline' => bi(...$e['article']['title']),
                'url' => \App\Support\Articles::url($e['article']), 'datePublished' => $e['date']->toDateString()])->values()->all()];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => '/journal', 'css' => 'journal.css'])
</head>
<body class="pp pp--journal">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <header class="jr-hero">
            <p class="pp-label">{{ bival('journal_label') }}</p>
            <h1 class="jr-hero__title">{{ bival('journal_title') }}</h1>
            <p class="jr-hero__intro">{{ bival('journal_intro') }}</p>
        </header>

        @if ($entries->isEmpty())
            <p class="jr-empty">{{ bival('journal_empty') }}</p>
        @else
            {{-- newest first; the first entry set large --}}
            <ol class="jr-list">
                @foreach ($entries as $i => $entry)
                    <li class="jr-item{{ $i === 0 ? ' jr-item--lead' : '' }}">
                        @isset($entry['post'])
                            @php $post = $entry['post']; @endphp
                            <a class="jr-card" href="{{ $post->journalUrl() }}">
                                <span class="jr-card__img">
                                    @if ($src = $cover($post))
                                        <img src="{{ $src }}" alt="" loading="{{ $i < 3 ? 'eager' : 'lazy' }}" decoding="async">
                                    @endif
                                </span>
                                <span class="jr-card__meta">
                                    <time datetime="{{ $post->date->toDateString() }}">{{ site_date($post->date, $lang) }}</time>
                                    <span class="jr-card__project"><bdi>{{ $post->project->tr('name') }}</bdi></span>
                                </span>
                                <span class="jr-card__title" dir="auto">{{ $post->title($lang) ?: $post->project->tr('name') }}</span>
                                @if (filled($post->note($lang)))
                                    <span class="jr-card__note" dir="auto">{{ \Illuminate\Support\Str::limit($post->note($lang), 160) }}</span>
                                @endif
                                <span class="jr-card__read">{{ bival('journal_read') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                            </a>
                        @else
                            {{-- an essay: its cover is a sheet of its own drawings --}}
                            @php $article = $entry['article']; @endphp
                            <a class="jr-card" href="{{ \App\Support\Articles::url($article) }}">
                                <span class="jr-card__img jr-card__img--plan">
                                    @include('journal.cover', ['keys' => $article['cover']])
                                </span>
                                <span class="jr-card__meta">
                                    <time datetime="{{ $entry['date']->toDateString() }}">{{ site_date($entry['date'], $lang) }}</time>
                                    <span class="jr-card__project">{{ bi(...$article['kind']) }}</span>
                                </span>
                                <span class="jr-card__title">{{ bi(...$article['title']) }}</span>
                                <span class="jr-card__note">{{ bi(...$article['summary']) }}</span>
                                <span class="jr-card__read">{{ bival('journal_read') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                            </a>
                        @endisset
                    </li>
                @endforeach
            </ol>
        @endif
    </main>

    @include('partials.page-foot')
</body>
</html>
