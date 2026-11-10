{{-- One photograph: full bleed, wide, at reading width, or offset to one side with its caption beside it. --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'wide';
    $caption = Landmark::t($d, 'caption');
    $sizes = match ($layout) { 'full' => '100vw', 'wide' => '(min-width: 1400px) 1320px, 100vw', 'column' => '(min-width: 900px) 760px, 100vw', default => '(min-width: 900px) 60vw, 100vw' };
@endphp
@if (filled($d['file'] ?? null))
    <figure class="lm-sec lm-fig lm-fig--{{ $layout }} reveal">
        <div class="lm-fig__media">
            @include('heritage.img', ['img' => $d, 'size' => 'lg', 'sizes' => $sizes, 'lightbox' => true, 'alt' => $l->tr('name')])
        </div>
        @if (filled($caption) || filled($d['credit'] ?? null))
            <figcaption class="lm-fig__cap">
                @if (filled($caption))<span class="lm-fig__text"{{ text_dir($caption) }}>{{ $caption }}</span>@endif
                @include('heritage.credit', ['img' => $d])
            </figcaption>
        @endif
    </figure>
@endif
