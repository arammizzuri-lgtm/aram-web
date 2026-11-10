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
            <p class="lm-statement__kicker" dir="auto">{{ $kicker }}</p>
        @endif
        <p class="lm-statement__big" dir="auto">{{ Landmark::digits($big) }}</p>
        @if (filled($text))
            <p class="lm-statement__text" dir="auto">{{ $text }}</p>
        @endif
    </section>
@endif
