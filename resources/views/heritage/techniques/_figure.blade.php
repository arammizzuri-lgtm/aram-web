{{-- A standing person, for scale: feet at ($x, $y), $h tall. --}}
@php $r = $h * 0.075; $hip = $y - $h * 0.42; @endphp
<g class="{{ $class ?? 'tq-t' }}">
    <circle cx="{{ $x }}" cy="{{ $y - $h + $r }}" r="{{ $r }}"/>
    <path d="M{{ $x }},{{ $y - $h + 2 * $r }} V{{ $hip }} M{{ $x }},{{ $hip }} L{{ $x - $h * 0.13 }},{{ $y }} M{{ $x }},{{ $hip }} L{{ $x + $h * 0.13 }},{{ $y }} M{{ $x - $h * 0.17 }},{{ $y - $h * 0.52 }} L{{ $x }},{{ $y - $h * 0.76 }} L{{ $x + $h * 0.17 }},{{ $y - $h * 0.52 }}"/>
</g>
