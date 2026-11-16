{{-- One of the essay's four diagrams of the courtyard house, drawn the way its plans are (journal/drawing):
     walls in gold double line with the rooms and the court cut out of them, doors swinging, windows, the
     tarma's columns, and the court paved, and hatched where it is in shade. They are diagrams, not surveys, so no
     north: the shade swings across the court as a day's would (CSS), and the lines draw themselves in
     as it comes into view (journal-drawing.js, through .ja-dwg).
     $id keeps its defs apart; $tp: holes (rooms and court, [x, y, w, h] or [[x, y], ...]), court (the open,
     sunlit part), doors [x, y, w, 'h'|'v', into -1|1], wins [x, y, w, 'h'|'v'], cols [[x, y]], over (dashed
     roof edges), words [[x, y, word]]. All in a 200 x 150 frame, walls 6 thick. --}}
@php
    $T = 6;
    $box = fn ($b) => is_array($b[0]) ? 'M'.implode('L', array_map(fn ($p) => $p[0].' '.$p[1], $b)).'Z' : "M{$b[0]} {$b[1]}h{$b[2]}v{$b[3]}h-{$b[2]}z";
    $wall = 'M16 14h168v122H16z'.implode('', array_map($box, $tp['holes']));
    $court = $box($tp['court']);
    $cut = $jamb = $leaf = $arc = $glass = '';
    foreach ($tp['doors'] as [$x, $y, $w, $o, $in]) {
        if ($o === 'h') {
            $cut .= 'M'.$x.' '.($y - .6).'h'.$w.'v'.($T + 1.2).'h-'.$w.'z';
            $jamb .= "M{$x} {$y}v{$T}M".($x + $w)." {$y}v{$T}";
            $hy = $in < 0 ? $y : $y + $T;
            $leaf .= "M{$x} {$hy}V".($hy + $in * $w);
            $arc .= 'M'.$x.' '.($hy + $in * $w)."A{$w} {$w} 0 0 ".($in < 0 ? 1 : 0).' '.($x + $w).' '.$hy;
        } else {
            $cut .= 'M'.($x - .6).' '.$y.'h'.($T + 1.2).'v'.$w.'h-'.($T + 1.2).'z';
            $jamb .= "M{$x} {$y}h{$T}M{$x} ".($y + $w)."h{$T}";
            $hx = $in < 0 ? $x : $x + $T;
            $leaf .= "M{$hx} {$y}H".($hx + $in * $w);
            $arc .= 'M'.($hx + $in * $w).' '.$y."A{$w} {$w} 0 0 ".($in < 0 ? 0 : 1).' '.$hx.' '.($y + $w);
        }
    }
    foreach ($tp['wins'] ?? [] as [$x, $y, $w, $o]) {
        if ($o === 'h') {
            $cut .= 'M'.$x.' '.($y - .6).'h'.$w.'v'.($T + 1.2).'h-'.$w.'z';
            $jamb .= "M{$x} {$y}v{$T}M".($x + $w)." {$y}v{$T}";
            $glass .= 'M'.$x.' '.($y + 2.2).'h'.$w.'M'.$x.' '.($y + 3.8).'h'.$w;
        } else {
            $cut .= 'M'.($x - .6).' '.$y.'h'.($T + 1.2).'v'.$w.'h-'.($T + 1.2).'z';
            $jamb .= "M{$x} {$y}h{$T}M{$x} ".($y + $w)."h{$T}";
            $glass .= 'M'.($x + 2.2).' '.$y.'v'.$w.'M'.($x + 3.8).' '.$y.'v'.$w;
        }
    }
    $cols = implode('', array_map(fn ($c) => 'M'.($c[0] - 2.6).' '.$c[1].'a2.6 2.6 0 1 0 5.2 0a2.6 2.6 0 1 0 -5.2 0', $tp['cols'] ?? []));
    $words = ['court' => bi('COURT', 'حەوشە', 'الفناء'), 'tarma' => bi('TARMA', 'تارمە', 'طارمة'), 'iwan' => bi('IWAN', 'ئەیوان', 'إيوان'),
        'room' => bi('ROOM', 'ژوور', 'غرفة'), 'arcade' => bi('ARCADE', 'ڕیزە کەوانە', 'رواق')];
@endphp
<svg class="ja-dwg ja-type" viewBox="0 0 200 150" style="--u:1px;font-size:10px" aria-hidden="true">
    <defs>
        <pattern id="{{ $id }}-h" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="d-hatch" d="M0 0V5"/></pattern>
        <pattern id="{{ $id }}-p" width="10" height="10" patternUnits="userSpaceOnUse"><path class="t-pave" d="M10 0H0V10"/></pattern>
        <clipPath id="{{ $id }}-c"><path d="{{ $court }}"/></clipPath>
        <mask id="{{ $id }}-m"><path d="{{ $court }}" fill="#fff"/><path class="t-lit" d="{{ $court }}" fill="#000"/></mask>
    </defs>
    {{-- the court: open to the sky, paved in stone --}}
    <path class="t-court" d="{{ $court }}" fill="url(#{{ $id }}-p)"/>
    <g class="t-sun">
        <path class="d-shade" d="{{ $court }}" fill="url(#{{ $id }}-h)" mask="url(#{{ $id }}-m)"/>
        <g clip-path="url(#{{ $id }}-c)"><path class="d-light t-lit" d="{{ $court }}"/></g>
    </g>
    <path class="d-poche" fill-rule="evenodd" d="{{ $wall }}"/>
    <path class="d-wall dl" pathLength="1" d="{{ $wall }}"/>
    <path class="t-cut" d="{{ $cut }}"/>
    <path class="d-wall dl" pathLength="1" d="{{ $jamb }}"/>
    @if ($glass)<path class="d-glass dl" pathLength="1" d="{{ $glass }}"/>@endif
    <path class="d-open dl" pathLength="1" d="{{ $arc }}"/>
    <path class="d-leaf dl" pathLength="1" d="{{ $leaf }}"/>
    @if (! empty($tp['over']))<path class="d-over" d="{{ $tp['over'] }}"/>@endif
    @if ($cols)<path class="d-poche t-col" d="{{ $cols }}"/><path class="d-wall dl" pathLength="1" d="{{ $cols }}"/>@endif
    {{-- the way in, under the street door --}}
    <path class="d-arrow dl" pathLength="1" d="M100 148V140M96.5 143.5L100 140L103.5 143.5"/>
    <g class="d-text">
        @foreach ($tp['words'] as [$x, $y, $w])
            <text x="{{ $x }}" y="{{ $y }}" class="p-room" text-anchor="middle">{{ $words[$w] }}</text>
        @endforeach
    </g>
</svg>
