{{-- A gallery of photographs: collage, grid, pairs, a sideways strip or a stack. Every photograph opens in the lightbox. --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'collage';
    $images = array_values(array_filter((array) ($d['images'] ?? []), fn ($i) => filled($i['file'] ?? null)));
    $heading = Landmark::t($d, 'heading');
    $text = Landmark::t($d, 'text');
    $sizes = match ($layout) { 'stack' => '(min-width: 1400px) 1320px, 100vw', 'strip' => '(min-width: 900px) 40vw, 80vw', 'pairs' => '(min-width: 900px) 50vw, 100vw', default => '(min-width: 900px) 33vw, 100vw' };
@endphp
@if ($images)
    <section class="lm-sec lm-gal lm-gal--{{ $layout }} reveal" data-count="{{ count($images) }}">
        @if (filled($heading) || filled($text))
            <header class="lm-sec__head">
                <h2 class="lm-h2" dir="auto">{{ $heading ?: bival('landmarks_gallery_default') }}</h2>
                @if (filled($text))<p class="lm-sec__lead" dir="auto">{{ $text }}</p>@endif
            </header>
        @endif
        <div class="lm-gal__grid">
            @foreach ($images as $k => $img)
                @php $cap = Landmark::t($img, 'caption'); $w = (int) ($img['w'] ?? 0); $h = (int) ($img['h'] ?? 0); @endphp
                <figure class="lm-gal__item{{ $w && $h && $h > $w ? ' is-tall' : '' }}" @if ($w && $h) style="--ar: {{ $w }} / {{ $h }}" @endif>
                    @include('heritage.img', ['img' => $img, 'size' => $layout === 'stack' || ($layout === 'collage' && $k === 0) ? 'lg' : 'md', 'sizes' => $layout === 'collage' && $k === 0 ? '(min-width: 900px) 66vw, 100vw' : $sizes, 'lightbox' => true, 'alt' => $l->tr('name')])
                    @if (filled($cap) || filled($img['credit'] ?? null))
                        <figcaption class="lm-gal__cap">
                            @if (filled($cap))<span dir="auto">{{ $cap }}</span>@endif
                            @include('heritage.credit', ['img' => $img])
                        </figcaption>
                    @endif
                </figure>
            @endforeach
        </div>
    </section>
@endif
