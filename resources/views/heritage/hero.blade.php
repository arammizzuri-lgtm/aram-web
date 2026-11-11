{{-- How a landmark's page opens, in the style the admin chose (Landmark::HERO_STYLES):
     cinematic — the cover fills the screen, the name over it;
     split — the name beside a tall cover; editorial — the name, then a wide cover;
     frame — the cover set in wide margins, the name above; dark — no cover up
     here, the name and the period set very large. --}}
@php
    use App\Models\Landmark;
    $style = $l->hero_style;
    $hasImg = filled($l->image) && $style !== 'dark';
    if (! $hasImg && $style !== 'dark') { $style = 'dark'; }
    $cover = ['file' => $l->image, 'alt_en' => $l->name_en, 'alt_ku' => $l->name_ku, 'alt_ar' => $l->name_ar, 'credit' => $l->image_credit, 'source' => $l->image_source];
    $intro = $l->tr('intro') ?: $l->tr('summary');
@endphp
<header class="lm-hero lm-hero--{{ $style }}">
    @if ($style === 'cinematic')
        <div class="lm-hero__bg" aria-hidden="true">
            @include('heritage.img', ['img' => $cover, 'size' => 'lg', 'sizes' => '100vw', 'eager' => true, 'alt' => ''])
        </div>
    @endif
    <div class="lm-hero__inner">
        <div class="lm-hero__text">
            <a class="lm-back" href="{{ site_url(Landmark::PATH) }}#{{ $l->slug }}"><span class="pp-arrow" aria-hidden="true">←</span> {{ bival('landmarks_back') }}</a>
            <p class="pp-label">{{ Landmark::regionName($l->region) }} · {{ Landmark::eraName($l->era) }}</p>
            <h1 class="lm-title"{{ text_dir($l->tr('name')) }}>{{ $l->tr('name') }}</h1>
            @if ($style === 'dark')
                <p class="lm-hero__big"{{ text_dir(Landmark::digits($l->tr('period'))) }}>{{ Landmark::digits($l->tr('period')) }}</p>
            @endif
            @if (filled($intro))
                <p class="lm-intro"{{ text_dir($intro) }}>{{ $intro }}</p>
            @endif
            <ul class="lm-meta">
                @if (filled($l->tr('place')))<li{{ text_dir($l->tr('place')) }}>{{ $l->tr('place') }}</li>@endif
                @if (filled($l->tr('period')) && $style !== 'dark')<li{{ text_dir($l->tr('period')) }}>{{ $l->tr('period') }}</li>@endif
                @if ($l->unesco)
                    <li class="lm-meta__unesco"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8h18zM5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>{{ bival('landmarks_unesco') }}@if ($l->unesco_year) · {{ Landmark::digits($l->unesco_year) }}@endif</li>
                @endif
            </ul>
            {{-- what it is built of: each material opens the heritage map on the landmarks built of it --}}
            @if ($l->materialKeys())
                <p class="lm-built">
                    <span class="lm-built__label">{{ bival('landmarks_built_of') }}</span>
                    @foreach ($l->materialKeys() as $mat)
                        <a class="lm-built__tag" href="{{ site_url(Landmark::PATH) }}?material={{ $mat }}#map" style="--mat: {{ Landmark::MATERIALS[$mat] }}"><i class="hr-swatch" aria-hidden="true"></i>{{ Landmark::materialName($mat) }}</a>
                    @endforeach
                </p>
            @endif
        </div>
        @if (in_array($style, ['split', 'editorial', 'frame'], true))
            <figure class="lm-hero__fig">
                @include('heritage.img', ['img' => $cover, 'size' => 'lg', 'sizes' => $style === 'split' ? '(min-width: 900px) 50vw, 100vw' : '(min-width: 1400px) 1320px, 100vw', 'eager' => true, 'lightbox' => true, 'alt' => $l->tr('name')])
                @if (filled($l->image_credit))
                    <figcaption class="lm-fig__cap">@include('heritage.credit', ['img' => $cover])</figcaption>
                @endif
            </figure>
        @endif
    </div>
    @if ($style === 'cinematic')
        @if (filled($l->image_credit))
            <p class="lm-hero__credit">@include('heritage.credit', ['img' => $cover])</p>
        @endif
        <span class="lm-hero__scroll" aria-hidden="true"><span>{{ bival('ui_scroll') }}</span><i></i></span>
    @endif
</header>
