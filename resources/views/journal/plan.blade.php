{{-- One redrawn plan of the journal's Citadel houses: the traced walls (resources/journal/citadel-plans.php)
     and, over them, what the page adds — numbered tags for its legend, columns, an arrow at the entrance,
     a basin, a scale bar where the survey gives a measure.
     $plan [w, h, d]; $tags [[x, y, label]], $cols [[x, y, r]], $doors [[x, y, degrees]], $basin [x, y, rx, ry]:
     x and y as fractions of the plan; $scale [units per metre, metres]; $name for readers who cannot see it. --}}
@php
    $w = $plan['w']; $h = $plan['h']; $pad = 46;
    $bar = isset($scale) ? $scale[0] * $scale[1] : 0;
@endphp
<svg class="ja-plan" viewBox="{{ -$pad }} {{ -$pad }} {{ $w + 2 * $pad }} {{ $h + 2 * $pad + ($bar ? 54 : 0) }}" role="img" aria-label="{{ $name }}">
    <path class="ja-plan__wall" fill-rule="evenodd" d="{{ $plan['d'] }}"/>
    @isset($basin)
        <ellipse class="ja-plan__line" cx="{{ $basin[0] * $w }}" cy="{{ $basin[1] * $h }}" rx="{{ $basin[2] * $w }}" ry="{{ $basin[3] * $w }}"/>
        <ellipse class="ja-plan__line" cx="{{ $basin[0] * $w }}" cy="{{ $basin[1] * $h }}" rx="{{ $basin[2] * $w * .55 }}" ry="{{ $basin[3] * $w * .55 }}"/>
    @endisset
    @foreach ($cols ?? [] as [$x, $y, $r])
        <circle class="ja-plan__col" cx="{{ $x * $w }}" cy="{{ $y * $h }}" r="{{ $r }}"/>
    @endforeach
    @foreach ($doors ?? [] as [$x, $y, $deg])
        <path class="ja-plan__door" transform="translate({{ $x * $w }} {{ $y * $h }}) rotate({{ $deg }})" d="M0 0l-13 26h26z"/>
    @endforeach
    @foreach ($tags ?? [] as [$x, $y, $label])
        <g class="ja-plan__tag" transform="translate({{ $x * $w }} {{ $y * $h }})">
            <circle r="{{ strlen($label) > 2 ? 30 : 21 }}"/>
            <text dy=".36em" text-anchor="middle" @if (strlen($label) > 2) class="is-long" @endif>{{ $label }}</text>
        </g>
    @endforeach
    @if ($bar)
        {{-- the scale: a bar in metres, ticked as on a drawing --}}
        <g class="ja-plan__scale" transform="translate(0 {{ $h + $pad + 14 }})" direction="ltr">
            <path d="M0 0h{{ $bar }}M0 -8v16M{{ $bar }} -8v16M{{ $bar / 2 }} -5v10"/>
            <text x="0" y="30">0</text>
            <text x="{{ $bar }}" y="30" text-anchor="end">{{ $scale[1] }} m</text>
        </g>
    @endif
</svg>
