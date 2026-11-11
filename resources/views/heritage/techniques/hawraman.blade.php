{{-- Hawraman: how a village climbs a slope. A section up the hillside, about
     24 px to the metre: (1) a house cut back into a sunny slope, its rear
     wall the mountain, (2) front and side walls of local stone, (3) a flat
     roof — timber beams, brush, packed earth, with its stone roller, (4) the
     next houses, each standing on the roof of the one below, which is its
     yard. Every tier is 120 across and 80 up, the slope of the hill. --}}
@php
    // tier $k: its floor runs from ($x, $y) to ($x + 120, $y); its roof is the next floor, 80 above
    $tier = fn ($k) => ['x' => 200 + 120 * $k, 'y' => 380 - 80 * $k];
@endphp
<svg class="tq__svg" viewBox="0 40 800 420" role="img">
    {{-- the slope, and the sun it faces --}}
    <path class="tq-base" d="M80,460 L680,60"/>
    @for ($i = 0; $i < 10; $i++)
        <path class="tq-base" d="M{{ 104 + $i * 60 }},{{ 452 - $i * 40 }} l12,3" opacity=".55"/>
    @endfor
    <circle class="tq-base" cx="118" cy="112" r="20"/>
    @foreach ([0, 45, 90, 135, 180, 225, 270, 315] as $a)
        <path class="tq-base" d="M118,112 m{{ round(28 * cos(deg2rad($a)), 1) }},{{ round(28 * sin(deg2rad($a)), 1) }} l{{ round(10 * cos(deg2rad($a)), 1) }},{{ round(10 * sin(deg2rad($a)), 1) }}"/>
    @endforeach
    <path class="tq-base tq-dash" d="M146,132 L204,300" opacity=".6"/>

    @foreach ([0, 1, 2, 3] as $k)
        @php ['x' => $x, 'y' => $y] = $tier($k); @endphp

        {{-- the cut: a level floor, and the mountain as the rear wall --}}
        <g data-step="{{ $k === 0 ? 1 : 4 }}">
            <path class="tq-f" d="M{{ $x }},{{ $y }} H{{ $x + 120 }} V{{ $y - 80 }} Z"/>
            <path class="tq-l" d="M{{ $x }},{{ $y }} H{{ $x + 120 }} V{{ $y - 80 }}"/>
            @for ($i = 0; $i < 4; $i++)
                <path class="tq-t" d="M{{ $x + 120 }},{{ $y - 12 - $i * 17 }} l9,-6"/>
            @endfor
        </g>

        {{-- the walls: local stone, laid dry or in mud, thick --}}
        <g data-step="{{ $k === 0 ? 2 : 4 }}">
            <rect class="tq-l tq-k" x="{{ $x }}" y="{{ $y - 68 }}" width="15" height="68"/>
            @for ($i = 1; $i < 6; $i++)
                <path class="tq-t" d="M{{ $x }},{{ $y - $i * 11 }} h15 M{{ $x + ($i % 2 ? 5 : 10) }},{{ $y - $i * 11 }} v11"/>
            @endfor
            {{-- a window in the wall, and the room behind it --}}
            <rect class="tq-t tq-k" x="{{ $x + 3 }}" y="{{ $y - 48 }}" width="9" height="14"/>
        </g>

        {{-- the roof: beams, brush, packed earth — and the terrace of the house behind --}}
        <g data-step="{{ $k === 0 ? 3 : 4 }}">
            <rect class="tq-l tq-k" x="{{ $x - 9 }}" y="{{ $y - 72 }}" width="129" height="4"/>
            <path class="tq-t" d="M{{ $x - 9 }},{{ $y - 74 }} q4,-3 8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0 t8,0"/>
            <rect class="tq-f" x="{{ $x - 9 }}" y="{{ $y - 80 }}" width="129" height="5"/>
            <path class="tq-l" d="M{{ $x - 9 }},{{ $y - 72 }} V{{ $y - 80 }} H{{ $x + 120 }}"/>
            @if ($k === 0)
                {{-- the stone roller that lives on every roof --}}
                <rect class="tq-l tq-k" x="{{ $x + 66 }}" y="{{ $y - 90 }}" width="20" height="10" rx="5"/>
                <path class="tq-t" d="M{{ $x + 86 }},{{ $y - 85 }} l14,-9"/>
            @endif
        </g>
    @endforeach

    {{-- 4. life on the roofs: a neighbour's yard, fruit drying, and stairs for lanes --}}
    <g data-step="4">
        @include('heritage.techniques._figure', ['x' => 232, 'y' => 300, 'h' => 40])
        @include('heritage.techniques._figure', ['x' => 402, 'y' => 220, 'h' => 40])
        @foreach ([[352, 220], [362, 220], [372, 220], [357, 216], [367, 216]] as [$fx, $fy])
            <circle class="tq-t" cx="{{ $fx }}" cy="{{ $fy - 2.5 }}" r="2.5"/>
        @endforeach
        @foreach ([0, 1, 2] as $k)
            @php ['x' => $x, 'y' => $y] = $tier($k + 1); @endphp
            <path class="tq-t tq-dash" d="M{{ $x - 34 }},{{ $y }} @for ($s = 0; $s < 8; $s++) v-10 h3 @endfor"/>
        @endforeach
    </g>
</svg>
