{{-- The Mudhafaria Minaret: how a brick tower stands. An elevation with the
     inside shown, 10 px to the metre (36 m today): (1) the octagonal base of
     solid brick, about a third of the height, (2) a balcony on corbels, then
     the round shaft, tapering — its top broken, a second balcony fallen,
     (3) the stairs inside the wall: from the east door up to the balcony,
     and on inside the shaft, (4) the ornament: bands of hazarbaf brick on
     the shaft, two tiers of arched niches on the base — and the weave of
     the brickwork, enlarged, beside it. --}}
@php
    // the shaft tapers from 56 wide at the balcony (y = 280) to 44 near the top (y = 72)
    $half = fn ($y) => 28 - 6 * (280 - $y) / 208;
    // a stair turning inside the wall, seen from the side: half a turn every 12 px, between ±$reach($y)
    $stair = function (float $from, float $to, callable $reach, int $side = 1): string {
        $d = sprintf('M%s,%s', round(400 + $side * $reach($from), 1), $from);
        for ($y = $from; $y - 12 >= $to; $y -= 12, $side = -$side) {
            $a = round(400 + $side * $reach($y), 1);
            $b = round(400 - $side * $reach($y - 12), 1);
            $d .= sprintf(' C%s,%s %s,%s %s,%s', $a, $y - 6, $b, $y - 6, $b, $y - 12);
        }

        return $d;
    };
    $bands = [[262, 236], [224, 198], [186, 160], [148, 122], [110, 90]];
@endphp
<svg class="tq__svg" viewBox="0 40 800 400" role="img">
    <defs>
        <clipPath id="tqm-lens"><circle cx="620" cy="196" r="84"/></clipPath>
    </defs>
    {{-- the plain, and a person for scale --}}
    <path class="tq-base" d="M120,420 H700"/>
    @for ($x = 134; $x < 700; $x += 28)
        <path class="tq-base" d="M{{ $x }},428 l-8,11" opacity=".55"/>
    @endfor
    @include('heritage.techniques._figure', ['x' => 322, 'y' => 420, 'h' => 17, 'class' => 'tq-base'])

    {{-- 1. the base: an octagon of solid brick --}}
    <g data-step="1">
        <defs>
            <pattern id="tqm-brick" width="12" height="6" patternUnits="userSpaceOnUse">
                <path d="M0,.5 H12 M0,3.5 H12 M3,0 V3 M9,3 V6" fill="none" stroke="currentColor" stroke-width=".6" opacity=".55"/>
            </pattern>
        </defs>
        <rect class="tq-p" fill="url(#tqm-brick)" x="362" y="300" width="76" height="112"/>
        <path class="tq-l" d="M362,412 V300 H438 V412"/>
        <path class="tq-t" d="M381,300 V412 M419,300 V412"/>
        <rect class="tq-l tq-k" x="356" y="412" width="88" height="8"/>
    </g>

    {{-- 2. a balcony on corbels, and the round shaft, tapering; a second balcony once stood near the top --}}
    <g data-step="2">
        <path class="tq-l tq-k" d="M372,280 L{{ 400 - $half(72) }},72 l5,-9 l7,7 l8,-11 l9,10 l7,-6 l6,9 L428,280 Z"/>
        <path class="tq-l tq-k" d="M362,300 L353,290 V280 H447 V290 L438,300 Z"/>
        <path class="tq-t" d="M353,290 H447"/>
        <path class="tq-t tq-dash" d="M366,120 V108 H434 V120 M361,120 H439"/>
    </g>

    {{-- 3. the stairs inside the wall: the east door, up to the balcony, and on inside the shaft --}}
    <g data-step="3">
        <path class="tq-l tq-k" d="M425,412 V396 Q430,388 435,396 V412"/>
        <path class="tq-t" d="{{ $stair(404, 296, fn ($y) => 30) }}"/>
        <path class="tq-t" d="{{ $stair(276, 84, fn ($y) => $half($y) - 6, -1) }}"/>
        @foreach ([246, 186, 126] as $i => $y)
            <rect class="tq-t" x="{{ $i % 2 ? 400 - $half($y) + 3 : 400 + $half($y) - 6 }}" y="{{ $y }}" width="3" height="9"/>
        @endforeach
    </g>

    {{-- 4. brick as ornament --}}
    <g data-step="4">
        <defs>
            <pattern id="tqm-weave" width="8" height="8" patternUnits="userSpaceOnUse">
                <path d="M0,8 L8,0 M-2,2 L2,-2 M6,10 L10,6 M0,0 L8,8 M-2,6 L2,10 M6,-2 L10,2" fill="none" stroke="currentColor" stroke-width=".7" opacity=".9"/>
            </pattern>
            <pattern id="tqm-weave-big" width="34" height="34" patternUnits="userSpaceOnUse">
                <path d="M4,13 L13,4 M21,30 L30,21 M21,4 L30,13 M4,21 L13,30" fill="none" stroke="currentColor" stroke-width="5"/>
                <path d="M17,2 V10 M17,24 V32 M2,17 H10 M24,17 H32" fill="none" stroke="currentColor" stroke-width="1" opacity=".6"/>
            </pattern>
        </defs>
        {{-- bands of hazarbaf, plain courses between them --}}
        @foreach ($bands as [$y0, $y1])
            @php $p = sprintf('M%s,%s H%s L%s,%s H%s Z', 400 - $half($y0), $y0, 400 + $half($y0), 400 + $half($y1), $y1, 400 - $half($y1)); @endphp
            <path class="tq-k" d="{{ $p }}"/>
            <path class="tq-p" fill="url(#tqm-weave)" d="{{ $p }}"/>
            <path class="tq-t" d="{{ $p }}"/>
        @endforeach
        {{-- the base: a lower tier of tall niches, an upper tier of small ones --}}
        @foreach ([[386, 11], [403, 11], [366.5, 10], [423.5, 10]] as $i => [$x, $w])
            <path class="tq-t tq-k" d="M{{ $x }},{{ $i === 3 ? 388 : 402 }} V342 Q{{ $x + $w / 2 }},332 {{ $x + $w }},342 V{{ $i === 3 ? 388 : 402 }} Z"/>
        @endforeach
        @foreach ([385, 396, 407, 366, 425] as $x)
            <path class="tq-t tq-k" d="M{{ $x }},326 V313 Q{{ $x + 4 }},306 {{ $x + 8 }},313 V326 Z"/>
        @endforeach
        {{-- the balcony's parapet: a lattice of cut brick --}}
        <path class="tq-t" d="M353,280 @for ($x = 353; $x < 446; $x += 9.4) l4.7,10 l4.7,-10 @endfor"/>
        {{-- the weave, enlarged --}}
        <circle class="tq-t" cx="414" cy="196" r="11"/>
        <path class="tq-t tq-dash" d="M425,196 H536"/>
        <circle class="tq-k" cx="620" cy="196" r="84"/>
        <rect class="tq-p" fill="url(#tqm-weave-big)" x="530" y="106" width="180" height="180" clip-path="url(#tqm-lens)"/>
        <circle class="tq-l" cx="620" cy="196" r="84"/>
    </g>
</svg>
