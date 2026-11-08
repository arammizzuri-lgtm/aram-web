@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectUpdate> $posts */
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('journal_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('journal_intro'), 160);
    $cover = fn ($post) => $post->imageUrls('thumb')[0] ?? $post->project?->coverThumbUrl();
    $schema = ['@context' => 'https://schema.org', '@type' => 'Blog', 'name' => bival('journal_label'), 'url' => site_url('/journal'),
        'inLanguage' => $lang, 'publisher' => ['@id' => url('/').'#studio'],
        'blogPost' => $posts->take(20)->map(fn ($p) => ['@type' => 'BlogPosting', 'headline' => $p->title($lang) ?: $p->project->tr('name'),
            'url' => $p->journalUrl(), 'datePublished' => $p->date->toDateString()])->values()->all()];
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

        @if ($posts->isEmpty())
            <p class="jr-empty">{{ bival('journal_empty') }}</p>
        @else
            {{-- newest first; the first entry set large --}}
            <ol class="jr-list">
                @foreach ($posts as $i => $post)
                    <li class="jr-item{{ $i === 0 ? ' jr-item--lead' : '' }}">
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
                    </li>
                @endforeach
            </ol>
        @endif
    </main>

    @include('partials.page-foot')
</body>
</html>
