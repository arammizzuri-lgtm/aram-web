@php
    /** @var \Illuminate\Support\Collection<int, array> $entries  the build entries ('post'), the essays in code ('article', App\Support\Articles) and those written in the admin ('essay'), newest first */
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('journal_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('journal_intro'), 160);
    $cover = fn ($post) => $post->imageUrls('thumb')[0] ?? $post->project?->coverThumbUrl();
    $schema = ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => bival('journal_label'), 'url' => site_url('/journal'),
        'inLanguage' => $lang, 'publisher' => ['@id' => url('/').'#studio'],
        'blogPost' => $entries->take(20)->map(fn ($e) => match (true) {
            isset($e['post']) => ['@type' => 'BlogPosting', 'headline' => $e['post']->title($lang) ?: $e['post']->project->tr('name'),
                'url' => $e['post']->journalUrl(), 'datePublished' => $e['date']->toDateString()],
            isset($e['essay']) => ['@type' => 'Article', 'headline' => $e['essay']->tr('title'),
                'url' => $e['essay']->url(), 'datePublished' => $e['date']->toDateString()],
            default => ['@type' => 'Article', 'headline' => bi(...$e['article']['title']),
                'url' => \App\Support\Articles::url($e['article']), 'datePublished' => $e['date']->toDateString()],
        })->values()->all()];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => '/journal', 'css' => 'journal.css'])
    <link rel="alternate" type="application/atom+xml" title="{{ $title }}" href="{{ site_url('/journal/feed') }}">
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
                        @elseif (isset($entry['essay']))
                            {{-- an essay written in the admin: its cover photograph --}}
                            @php $essay = $entry['essay']; @endphp
                            <a class="jr-card" href="{{ $essay->url() }}">
                                <span class="jr-card__img">
                                    @if ($src = $essay->imageUrl('md'))
                                        <img src="{{ $src }}" alt="" loading="{{ $i < 3 ? 'eager' : 'lazy' }}" decoding="async">
                                    @endif
                                </span>
                                <span class="jr-card__meta">
                                    <time datetime="{{ $essay->date->toDateString() }}">{{ site_date($essay->date, $lang) }}</time>
                                    <span class="jr-card__project">{{ bi('Essay', 'وتار', 'مقال') }}</span>
                                </span>
                                <span class="jr-card__title"{{ text_dir($essay->tr('title')) }}>{{ $essay->tr('title') }}</span>
                                @if (filled($essay->tr('summary')))
                                    <span class="jr-card__note"{{ text_dir($essay->tr('summary')) }}>{{ \Illuminate\Support\Str::limit($essay->tr('summary'), 160) }}</span>
                                @endif
                                <span class="jr-card__read">{{ bival('journal_read') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                            </a>
                        @else
                            {{-- an essay in code: its cover is a sheet of its own drawings --}}
                            @php $article = $entry['article']; @endphp
                            <a class="jr-card" href="{{ \App\Support\Articles::url($article) }}">
                                <span class="jr-card__img jr-card__img--plan">
                                    @include('journal.cover', ['article' => $article])
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
