@php
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Models\Partner>> $groups */
    use App\Models\Partner;
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('partners_label').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('partners_intro'), 160);
    $schema = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => bival('partners_label'), 'url' => site_url('/partners'),
        'itemListElement' => $groups->flatten(1)->values()->map(fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1,
            'item' => array_filter(['@type' => 'Organization', 'name' => $p->name, 'url' => $p->websiteUrl(), 'description' => $p->description('en')])])->all()];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.page-head', ['path' => '/partners', 'css' => 'partners.css'])
</head>
<body class="pp pp--partners">
    @include('partials.page-top', ['otherUrl' => null])

    <main>
        <header class="pt-hero">
            <p class="pp-label">{{ bival('partners_label') }}</p>
            <h1 class="pt-hero__title">{{ bival('partners_title') }}</h1>
            <p class="pt-hero__intro">{{ bival('partners_intro') }}</p>
        </header>

        @forelse ($groups as $key => $partners)
            <section class="pt-group">
                <h2 class="pt-group__title">{{ Partner::categoryName($key) }}</h2>
                <ul class="pt-grid">
                    @foreach ($partners as $p)
                        <li class="pt-card">
                            <span class="pt-card__logo">
                                @if ($p->logoUrl())
                                    <img src="{{ $p->logoUrl() }}" alt="{{ $p->name }}" loading="lazy" decoding="async">
                                @else
                                    <span class="pt-card__initial" aria-hidden="true">{{ mb_substr($p->name, 0, 1) }}</span>
                                @endif
                            </span>
                            <span class="pt-card__name"><bdi>{{ $p->name }}</bdi></span>
                            @if (filled($p->description()))
                                <span class="pt-card__desc" dir="auto">{{ $p->description() }}</span>
                            @endif
                            @if ($p->websiteUrl())
                                <a class="pt-card__link" href="{{ $p->websiteUrl() }}" target="_blank" rel="noopener">{{ bival('partners_visit') }} <span aria-hidden="true">↗</span></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="pt-empty">{{ bival('partners_empty') }}</p>
        @endforelse

        <section class="pt-join">
            <h2 class="pt-join__title">{{ bival('partners_join') }}</h2>
            <a class="pp-btn pp-btn--line" href="{{ site_url('/') }}#contact">{{ bival('partners_join_btn') }} <span class="pp-arrow" aria-hidden="true">→</span></a>
        </section>
    </main>

    @include('partials.page-foot')
</body>
</html>
