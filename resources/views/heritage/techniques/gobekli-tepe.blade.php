{{-- Göbekli Tepe: how a ring of T-pillars was raised. A view into an
     enclosure, 40 px to the metre: (1) a pillar cut from the bedrock of the
     plateau, (2) the two central pillars, over five metres tall, stood
     facing each other, (3) the ring — smaller pillars held in a wall of
     fieldstone with a bench at its foot, (4) the carving: arms, hands, belt
     and loincloth on the central pillars, animals on the others. The ring
     comes first here so the central pillars are drawn over it. --}}
@php
    // a T-pillar seen from its broad side: foot centre ($x, $y), height, shaft and head widths, head height
    $T = fn ($x, $y, $H, $sw, $hw, $hh) => sprintf('M%s,%s V%s H%s V%s H%s V%s H%s V%s Z',
        $x - $sw / 2, $y, $y - $H + $hh, $x - $hw / 2, $y - $H, $x + $hw / 2, $y - $H + $hh, $x + $sw / 2, $y);
@endphp
<svg class="tq__svg" viewBox="0 0 800 460" role="img">
    {{-- the plateau: bedrock, hatched below its surface --}}
    <path class="tq-base" d="M20,380 H780"/>
    @for ($x = 236; $x < 780; $x += 26)
        <path class="tq-base" d="M{{ $x }},388 l-8,11" opacity=".55"/>
    @endfor

    {{-- 3. the ring: a wall of fieldstone, a bench at its foot, smaller pillars standing in it --}}
    <g data-step="3">
        <path class="tq-l" d="M300,380 V300 H720 V380"/>
        @for ($row = 0; $row < 4; $row++)
            @for ($x = 300 + ($row % 2) * 16; $x < 700; $x += 32)
                <rect class="tq-t" x="{{ $x + 3 }}" y="{{ 303 + $row * 15 }}" width="{{ min(27, 716 - $x) }}" height="11" rx="5" opacity=".7"/>
            @endfor
        @endfor
        <rect class="tq-l tq-k" x="300" y="364" width="420" height="16"/>
        @foreach ([350, 670] as $x)
            <path class="tq-l tq-k" d="{{ $T($x, 380, 140, 24, 56, 28) }}"/>
        @endforeach
    </g>

    {{-- 2. the two central pillars, facing each other, and the floor they stand on --}}
    <g data-step="2">
        <path class="tq-l" d="M322,380 H698" stroke-width="3"/>
        <path class="tq-l tq-k" d="{{ $T(450, 380, 220, 34, 84, 40) }}"/>
        <path class="tq-l tq-k" d="{{ $T(570, 380, 220, 34, 84, 40) }}"/>
        @include('heritage.techniques._figure', ['x' => 510, 'y' => 380, 'h' => 68])
    </g>

    {{-- 1. the quarry: a pillar cut free of the bedrock, and the way to the hilltop --}}
    <g data-step="1">
        <path class="tq-f" d="M40,388 H76 V398 H204 V422 H76 V432 H40 Z"/>
        <path class="tq-l" d="M40,388 H76 V398 H204 V422 H76 V432 H40 Z"/>
        <path class="tq-t tq-dash" d="M32,382 H84 V391 H212 V429 H84 V438 H32 Z"/>
        @include('heritage.techniques._figure', ['x' => 128, 'y' => 380, 'h' => 68])
        <path class="tq-t" d="M139,345 l16,10 M151,349 l6,9"/>
        <path class="tq-t tq-dash" d="M222,370 H282"/>
        <path class="tq-t" d="M282,370 l-9,-5 M282,370 l-9,5"/>
    </g>

    {{-- 4. the carving --}}
    <g data-step="4">
        {{-- arms bent at the elbow, hands meeting over a belt, a fox-pelt loincloth --}}
        <path class="tq-t" d="M438,216 V266 L460,284 M460,284 h6 M460,288 h6 M460,292 h6 M433,300 H467 M433,308 H467 M444,308 L450,334 L456,308"/>
        <path class="tq-t" d="M582,216 V266 L560,284 M560,284 h-6 M560,288 h-6 M560,292 h-6 M553,300 H587 M553,308 H587 M564,308 L570,334 L576,308"/>
        {{-- a fox on a central pillar --}}
        <path class="tq-t" transform="translate(432,170) scale(1.25)" d="M0,7 L6,0 L8,4 Q15,2 22,5 L30,1 L25,9 L22,14 H20.5 L20,9 H11 L10,14 H8.5 L8,9 L5,9 Z"/>
        {{-- a bull's head and a snake on one pillar of the ring, a crane on another --}}
        <path class="tq-t" d="M340,251 q10,-13 20,0 M345,254 h10 l-2,10 h-6 z"/>
        <path class="tq-t" d="M350,280 q-5,7 0,14 q5,7 0,14 q-5,7 0,14 q5,7 0,14"/>
        <path class="tq-t" d="M666,336 V356 M674,336 V356 M662,330 a8,5 0 1 0 16,0 a8,5 0 1 0 -16,0 M676,327 q7,-10 2,-19 l7,2"/>
    </g>
</svg>
