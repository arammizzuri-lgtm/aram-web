{{-- Erbil Citadel: how a town became a hill. A section through the mound (a
     tell): (1) a village of mudbrick houses on the plain, (2) the houses
     fall, are levelled and built on — a low rise, (3) thousands of years of
     that: layer on layer to 25–32 metres, slopes of about 45°, (4) what
     stands on top today — a ring of houses whose backs are the wall, with
     ramps up to the gates. The first houses stay in the drawing, buried. --}}
@php
    // the mound's sides are at 45°: at height $y the layer runs from $left($y) to $right($y)
    $left = fn ($y) => 140 + (400 - $y);
    $right = fn ($y) => 660 - (400 - $y);
@endphp
<svg class="tq__svg" viewBox="0 200 800 240" role="img">
    {{-- the plain --}}
    <path class="tq-base" d="M20,400 H780"/>
    @for ($x = 34; $x < 780; $x += 28)
        <path class="tq-base" d="M{{ $x }},408 l-8,11" opacity=".55"/>
    @endfor

    {{-- 3. layer on layer: the mound, and the towns inside it --}}
    <g data-step="3">
        <path class="tq-f" d="M140,400 L270,270 H530 L660,400 Z"/>
        <path class="tq-l" d="M140,400 L270,270 H530 L660,400"/>
        @foreach ([372, 346, 320, 295] as $i => $y)
            @php $a = $left($y) + 8; $w = $right($y) - 8 - $a; @endphp
            <path class="tq-t tq-dash" d="M{{ $a }},{{ $y }} q{{ $w / 4 }},-6 {{ $w / 2 }},0 t{{ $w / 2 }},0"/>
            @foreach ([0.2, 0.42, 0.63, 0.82] as $j => $f)
                @if (($i + $j) % 2 === 0)
                    <rect class="tq-t" x="{{ round($a + $w * $f) }}" y="{{ $y - 10 }}" width="13" height="7" opacity=".7"/>
                @endif
            @endforeach
        @endforeach
    </g>

    {{-- 2. mudbrick back to earth, and built on again: the first rise --}}
    <g data-step="2">
        <path class="tq-f" d="M250,400 Q400,350 550,400 Z"/>
        <path class="tq-l" d="M250,400 Q400,350 550,400"/>
        <path class="tq-t tq-dash" d="M296,392 Q400,366 504,392"/>
        @foreach ([[350, 379], [378, 376], [406, 376], [434, 379]] as [$x, $y])
            <rect class="tq-l" x="{{ $x }}" y="{{ $y - 13 }}" width="19" height="13"/>
        @endforeach
    </g>

    {{-- 1. a village on the plain --}}
    <g data-step="1">
        @foreach ([[334, 16], [362, 20], [390, 15], [418, 19], [446, 16]] as [$x, $h])
            <rect class="tq-l" x="{{ $x }}" y="{{ 400 - $h }}" width="22" height="{{ $h }}"/>
            <rect class="tq-t" x="{{ $x + 8 }}" y="{{ 400 - 8 }}" width="5" height="8"/>
        @endforeach
    </g>

    {{-- 4. the houses are the wall: a ring of them on the rim, ramps to the gates --}}
    <g data-step="4">
        @foreach ([30, 26, 34, 28, 32, 26, 30, 34, 27, 31, 28, 33] as $i => $h)
            @php $x = 272 + $i * 21.4; @endphp
            <rect class="tq-l tq-k" x="{{ $x }}" y="{{ 270 - $h }}" width="21.4" height="{{ $h }}"/>
            <rect class="tq-t" x="{{ $x + 8 }}" y="{{ 270 - $h + 6 }}" width="5" height="5"/>
        @endforeach
        {{-- the ramps up the slopes, and a gate at the head of each --}}
        <path class="tq-f" d="M112,400 L266,276 L270,270 L140,400 Z"/>
        <path class="tq-l" d="M112,400 L266,276"/>
        <path class="tq-f" d="M688,400 L534,276 L530,270 L660,400 Z"/>
        <path class="tq-l" d="M688,400 L534,276"/>
        <path class="tq-l tq-k" d="M276,270 V256 Q282.5,246 289,256 V270"/>
        <path class="tq-l tq-k" d="M511,270 V256 Q517.5,246 524,256 V270"/>
        @include('heritage.techniques._figure', ['x' => 196, 'y' => 334, 'h' => 12])
    </g>
</svg>
