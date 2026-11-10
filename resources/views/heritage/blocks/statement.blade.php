{{-- A statement set very large: a figure or a short sentence, a small line above, a line below. --}}
@php
    use App\Models\Landmark;
    $big = Landmark::t($d, 'big');
    $kicker = Landmark::t($d, 'kicker');
    $text = Landmark::t($d, 'text');
@endphp
@if (filled($big))
    <section class="lm-sec lm-statement lm-statement--{{ ($d['align'] ?? 'start') === 'center' ? 'center' : 'start' }} reveal">
        @if (filled($kicker))
            <p class="lm-statement__kicker"{{ text_dir($kicker) }}>{{ $kicker }}</p>
        @endif
        <p class="lm-statement__big"{{ text_dir(Landmark::digits($big)) }}>{{ Landmark::digits($big) }}</p>
        @if (filled($text))
            <p class="lm-statement__text"{{ text_dir($text) }}>{{ $text }}</p>
        @endif
    </section>
@endif
