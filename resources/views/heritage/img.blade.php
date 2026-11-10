{{-- One photograph of a landmark, responsive: the derivative the browser
     needs (HeritageImage: sm / md / lg by srcset), its real proportions
     reserved before it loads, lazy unless told otherwise, and — when
     $lightbox — wrapped in a link the lightbox (landmark.js) opens.
     $img: ['file', 'w', 'h', 'alt_*', 'caption_*', 'credit', 'source'].
     $size: the size the src falls back to (default lg). $sizes: the CSS
     width hint (default 100vw). $eager: load at once (the opening photograph).
     $alt: a fallback alt text. $class: on the <img>. --}}
@php
    use App\Models\Landmark;
    use App\Support\HeritageImage;
    $file = $img['file'] ?? null;
    $size = $size ?? 'lg';
    $w = (int) ($img['w'] ?? 0); $h = (int) ($img['h'] ?? 0);
    $altText = Landmark::t($img, 'alt') ?: ($alt ?? '');
    $captionText = Landmark::t($img, 'caption');
    $src = HeritageImage::url($file, $size);
    $srcset = HeritageImage::srcset($file, $w ?: null);
    $full = HeritageImage::url($file, 'lg');
@endphp
@if ($src)
    @if ($lightbox ?? false)
        <a class="lm-zoom" href="{{ $full }}" data-lightbox data-caption="{{ $captionText }}" data-credit="{{ $img['credit'] ?? '' }}" aria-label="{{ $altText }}">
    @endif
    <img class="{{ $class ?? '' }}" src="{{ $src }}"
         @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes ?? '100vw' }}" @endif
         alt="{{ $altText }}"
         @if ($w && $h) width="{{ $w }}" height="{{ $h }}" style="aspect-ratio: {{ $w }} / {{ $h }}" @endif
         @if ($eager ?? false) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif>
    @if ($lightbox ?? false)
        </a>
    @endif
@endif
