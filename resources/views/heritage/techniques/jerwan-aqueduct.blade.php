{{-- Jerwan: how a canal was carried across a valley. An elevation along the
     valley (not to scale along its length): (1) limestone dressed into
     blocks, many inscribed, (2) a solid causeway of coursed masonry laid
     across the valley, (3) pointed openings corbelled through it for the
     stream, (4) the channel on top, sealed, and the water running to
     Nineveh. The causeway comes before the openings, which are cut out of it. --}}
@php
    // one corbelled opening: the courses stepped in from each side until they meet
    $opening = fn ($cx) => sprintf('M%1$s,372 V342 H%2$s V336 H%3$s V330 H%4$s V336 H%5$s V342 H%6$s V372 Z',
        $cx - 9, $cx - 6, $cx - 3, $cx + 3, $cx + 6, $cx + 9);
@endphp
<svg class="tq__svg" viewBox="0 130 800 290" role="img">
    {{-- the valley, and the stream in its bed --}}
    <path class="tq-base" d="M20,290 H150 Q230,290 300,372 H500 Q570,290 650,290 H780"/>
    @foreach ([34, 62, 90, 118, 676, 704, 732, 760] as $x)
        <path class="tq-base" d="M{{ $x }},298 l-8,11" opacity=".55"/>
    @endforeach
    @for ($x = 318; $x < 480; $x += 28)
        <path class="tq-base" d="M{{ $x }},382 l-8,11" opacity=".55"/>
    @endfor
    <path class="tq-w" d="M312,378 q7,-4 14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0" opacity=".8"/>

    {{-- 2. the causeway: a solid mass of coursed masonry --}}
    <g data-step="2">
        <defs>
            <pattern id="tqj-ashlar" width="24" height="16" patternUnits="userSpaceOnUse">
                <path d="M0,.5 H24 M0,8.5 H24 M6,0 V8 M18,8 V16" fill="none" stroke="currentColor" stroke-width=".8" opacity=".6"/>
            </pattern>
        </defs>
        <path class="tq-p" fill="url(#tqj-ashlar)" d="M150,290 H650 Q570,290 500,372 H300 Q230,290 150,290 Z"/>
        <path class="tq-l" d="M150,290 H650 Q570,290 500,372 H300 Q230,290 150,290 Z"/>
        {{-- a person on top, for the height of it: nine metres at the stream --}}
        @include('heritage.techniques._figure', ['x' => 262, 'y' => 290, 'h' => 15])
    </g>

    {{-- 3. openings for the stream, corbelled to a point --}}
    <g data-step="3">
        @foreach ([344, 372, 400, 428, 456] as $cx)
            <path class="tq-l tq-k" d="{{ $opening($cx) }}"/>
        @endforeach
        <path class="tq-w" d="M312,378 q7,-4 14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0 t14,0"/>
    </g>

    {{-- 1. the blocks: dressed limestone, the king's name on many --}}
    <g data-step="1">
        @for ($row = 0; $row < 3; $row++)
            @for ($col = 0; $col < 4 - ($row === 0 ? 1 : 0); $col++)
                <rect class="tq-l" x="{{ 44 + $col * 16 + ($row % 2 ? 0 : 8) }}" y="{{ 234 + $row * 15 }}" width="15" height="14"/>
            @endfor
        @endfor
        {{-- one block, larger: cuneiform on its face --}}
        <rect class="tq-f" x="36" y="146" width="72" height="72"/>
        <rect class="tq-l" x="36" y="146" width="72" height="72"/>
        @foreach ([166, 183, 200] as $y)
            @foreach ([46, 66, 86] as $i => $x)
                <path class="tq-t" d="M{{ $x }},{{ $y }} h12 M{{ $x }},{{ $y - 3 }} v6 l4,-3 z{{ $i === 1 ? ' M'.($x + 6).','.($y - 6).' v5 l-2,-4 h4 z' : '' }}"/>
            @endforeach
        @endforeach
        <path class="tq-t tq-dash" d="M72,221 V232"/>
    </g>

    {{-- 4. the channel on top: sealed, and the water running --}}
    <g data-step="4">
        <path class="tq-l" d="M150,290 H650" stroke-width="3.2"/>
        <path class="tq-t" d="M150,290 V280 H650 V290"/>
        <path class="tq-t tq-dash" d="M24,280 H150 M650,280 H776"/>
        <path class="tq-w tq-flow" d="M24,285.5 H770" stroke-width="2.2"/>
        <path class="tq-w" d="M776,285.5 l-10,-5 M776,285.5 l-10,5" stroke-width="2"/>
    </g>
</svg>
