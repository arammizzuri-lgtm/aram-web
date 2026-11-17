@php
    /** @var \Illuminate\Support\Collection<int, array> $entries  the build entries ('post'), and the essays written in the admin ('essay'), newest first */
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
            default => ['@type' => 'Article', 'headline' => $e['essay']->tr('title'),
                'url' => $e['essay']->url(), 'datePublished' => $e['date']->toDateString()],
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

    <main class="jr-main">
        <header class="jr-hero">
            <p class="pp-label">{{ bival('journal_label') }}</p>
            <h1 class="jr-hero__title">{{ bival('journal_title') }}</h1>
            <p class="jr-hero__intro">{{ bival('journal_intro') }}</p>
        </header>

        {{-- pinned: the glossary, its house in section with each part named in Kurdish in turn (journal/word) --}}
        @php $section = (require resource_path('journal/glossary.php'))['section']; @endphp
        <aside class="jr-pin" aria-label="{{ bi('Pinned', 'هەڵواسراو', 'مثبّت') }}">
            <a href="{{ site_url('/journal/glossary') }}">
                @include('journal.word', ['sc' => $section, 'keys' => array_keys($section['parts']), 'cls' => 'jg-ill', 'sid' => 'pin', 'steps' => true])
                <span>
                    <span class="jr-pin__tag"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v5M9 3h6M10 3v6l-3 4v4h10v-4l-3-4V3"/></svg>{{ bi('Pinned · Glossary', 'هەڵواسراو · فەرهەنگ', 'مثبّت · مسرد') }}</span>
                    <span class="jr-pin__title" role="heading" aria-level="2">{{ bi('A dictionary of Kurdish architecture', 'فەرهەنگی تەلارسازی کوردی', 'معجم العمارة الكوردية') }}</span>
                    <span class="jr-pin__text">{{ bi('Foundation, column, beam, arch, dome: the words of a building in Kurdish, each lit on a drawing so the name stays with the thing. Then test yourself.',
                        'بناغە، کۆڵەکە، تیر، کەوانە، گومبەز: وشەکانی بینا بە کوردی، هەر یەکەیان لەسەر نەخشەیەک ڕووناک کراوەتەوە تا ناوەکە لەگەڵ شتەکە لە بیر بمێنێت. پاشان خۆت تاقی بکەرەوە.',
                        'الأساس، العمود، الجسر، القوس، القبّة: مفردات المبنى بالكوردية، كلٌّ منها مضاءة على رسم كي يبقى الاسم مقروناً بالشيء. ثم اختبر نفسك.') }}</span>
                    <span class="jr-pin__go">{{ bi('Learn the words', 'وشەکان فێر بە', 'تعلّم الكلمات') }} <span class="pp-arrow" aria-hidden="true">→</span></span>
                </span>
            </a>
        </aside>

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
                            {{-- an essay: its cover photograph, or a sheet of its own drawings (journal/cover) --}}
                            @php
                                $essay = $entry['essay'];
                                $src = $essay->imageUrl('md');
                                [$plan, $sec] = $src ? [null, null] : [\App\Models\Drawing::resolve($essay->extras['cover_plan'] ?? null), \App\Models\Drawing::resolve($essay->extras['cover_section'] ?? null)];
                            @endphp
                            <a class="jr-card" href="{{ $essay->url() }}">
                                @if ($plan && $sec)
                                    <span class="jr-card__img jr-card__img--plan">
                                        @include('journal.cover', ['essay' => $essay, 'plan' => $plan, 'sec' => $sec])
                                    </span>
                                @else
                                    <span class="jr-card__img">
                                        @if ($src)
                                            <img src="{{ $src }}" alt="" loading="{{ $i < 3 ? 'eager' : 'lazy' }}" decoding="async">
                                        @endif
                                    </span>
                                @endif
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
                        @endisset
                    </li>
                @endforeach
            </ol>
        @endif
    </main>

    @include('partials.page-foot')
</body>
</html>
