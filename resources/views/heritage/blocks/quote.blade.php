{{-- A documented quotation — an inscription, a traveller, an archaeologist — and who said it. --}}
@php
    use App\Models\Landmark;
    $text = Landmark::t($d, 'text');
    $source = Landmark::t($d, 'source');
@endphp
@if (filled($text))
    <figure class="lm-sec lm-quote reveal">
        <blockquote class="lm-quote__text" dir="auto"><p>{{ $text }}</p></blockquote>
        @if (filled($source))
            <figcaption class="lm-quote__src" dir="auto">{{ $source }}</figcaption>
        @endif
    </figure>
@endif
