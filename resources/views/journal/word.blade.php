{{-- A glossary scene (resources/journal/glossary.php) with the parts $keys lit: the part outlined in gold, its area
     washed, and its Kurdish name on a leader above the drawing. One key for a word's card on the glossary; every key,
     one after another, for the pinned card on /journal ($steps). $sid keeps the hatch pattern's id apart.
     Base kinds: w line, c thin line, g glass, a axis, p area, pe area even-odd, h earth hatch, cut an opening. --}}
@php
    $steps ??= false;
    $parts = $sc['parts'];
    $faint = function (array $p) {
        $o = isset($p['fill']) && empty($p['quiet']) ? '<path class="s-p"'.(! empty($p['eo']) ? ' fill-rule="evenodd"' : '').' d="'.($p['fill'] === true ? $p['d'] : $p['fill']).'"/>' : '';
        return $o.(($p['as'] ?? '') === 'a' ? '<path class="s-a" d="'.$p['d'].'"/>' : '<path class="s-w dl" pathLength="1" d="'.$p['d'].'"/>');
    };
@endphp
<svg class="{{ $cls }}" viewBox="0 -26 240 186" aria-hidden="true">
    <defs><pattern id="{{ $sid }}-h" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="s-hatch" d="M0 0V5"/></pattern></defs>
    @foreach ($sc['base'] as $b)
        @switch ($b[0])
            @case('parts') {!! implode('', array_map($faint, $parts)) !!} @break
            @case('t') <text class="s-t" x="{{ $b[1] }}" y="{{ $b[2] }}">{{ $b[3] }}</text> @break
            @case('h') <path class="s-shade" fill="url(#{{ $sid }}-h)" d="{{ $b[1] }}"/> @break
            @case('cut') <path class="s-cut" d="{{ $b[1] }}"/> @break
            @case('p') @case('pe') <path class="s-p"{!! $b[0] === 'pe' ? ' fill-rule="evenodd"' : '' !!} d="{{ $b[1] }}"/><path class="s-w dl" pathLength="1" d="{{ $b[1] }}"/> @break
            @default <path class="s-{{ $b[0] }} dl" pathLength="1" d="{{ $b[1] }}"/>
        @endswitch
    @endforeach
    @foreach ($keys as $i => $k)
        @php
            $p = $parts[$k];
            [$ax, $ay] = $p['at'];
            // the name sits above the drawing, over its part where it fits; a long name moves in from the edge
            $half = mb_strlen($p['w'][1]) * 3.4 + 3;
            $tx = round(min(max($ax, $half), 240 - $half), 1);
        @endphp
        @if ($steps)<g class="jp-step" style="--i:{{ $i }}">@endif
        @isset($p['fill'])<path class="jg-hlf"{!! ! empty($p['eo']) ? ' fill-rule="evenodd"' : '' !!} d="{{ $p['fill'] === true ? $p['d'] : $p['fill'] }}"/>@endisset
        <path class="jg-hl dl" pathLength="1" d="{{ $p['d'] }}"/>
        <g class="jg-lbl"><path d="M{{ $tx }} -6L{{ $ax }} {{ $ay }}"/><circle cx="{{ $ax }}" cy="{{ $ay }}" r="1.8"/><text x="{{ $tx }}" y="-10" lang="ckb">{{ $p['w'][1] }}</text></g>
        @if ($steps)</g>@endif
    @endforeach
</svg>
