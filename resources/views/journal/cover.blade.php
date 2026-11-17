{{-- An essay's card cover on /journal: one drawing sheet with a plan and a section from the essay itself
     (its drawings file, framed by each drawing's 'bb', the box round what is built),
     a 5 m bar to the section's scale and a title block. Gold line on the dark panel, as the essay draws.
     $essay: an App\Models\Essay whose extras name the plan and the section (cover_plan, cover_section, as Drawing::resolve
     takes them) and the title block's words (kicker, line1, line2, note in three languages, and code); $plan, $sec resolved. --}}
@php
    $rtl = site_rtl();
    // the section across the top right, 770 wide; the plan fills the left within 620 x 860
    $ss = 770 / $sec['bb'][2];
    $sh = round($sec['bb'][3] * $ss);
    $ps = min(620 / $plan['bb'][2], 860 / $plan['bb'][3]);
    [$pw, $ph] = [round($plan['bb'][2] * $ps), round($plan['bb'][3] * $ps)];
    [$px, $py] = [70 + (620 - $pw) / 2, 70 + (860 - $ph) / 2];
    $bar = round(500 * $ss);   // 5 m at the section's scale (drawings are in cm)
    // the title block's lines start at its right edge in Kurdish and Arabic
    $tx = $rtl ? 1530 : 760;
@endphp
<svg class="jr-cover" viewBox="0 0 1600 1000" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
    <defs>
        <pattern id="jr-cover-grid" width="40" height="40" patternUnits="userSpaceOnUse"><path class="c-grid" d="M40 0H0V40"/></pattern>
        <pattern id="jr-cover-hatch" width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="c-hatch" d="M0 0V14"/></pattern>
    </defs>
    <rect width="1600" height="1000" fill="url(#jr-cover-grid)"/>
    <rect class="c-frame" x="28" y="28" width="1544" height="944"/>
    <path class="c-frame" d="M730 28V972M730 560H1572"/>

    @foreach ([[$plan, $px, $py, $pw, $ph], [$sec, 760, 110, 770, $sh]] as [$dw, $x, $y, $w, $h])
        @php [$bx, $by, $bw, $bh] = $dw['bb']; @endphp
        <svg x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $h }}" viewBox="{{ $bx }} {{ $by }} {{ $bw }} {{ $bh }}" overflow="visible">
            @if ($dw['court'])<path class="c-court" d="{{ $dw['court'] }}"/>@endif
            @if ($dw['d']['ctx'])<path class="c-ctx" d="{{ $dw['d']['ctx'] }}"/>@endif
            <path class="c-poche" fill-rule="evenodd" d="{{ $dw['d']['wall'] }}"/>
            <path class="c-wall" d="{{ $dw['d']['wall'] }}"/>
            @foreach (['fix', 'stair', 'open', 'leaf', 'glass'] as $g)
                @if ($dw['d'][$g])<path class="c-{{ $g }}" d="{{ $dw['d'][$g] }}"/>@endif
            @endforeach
        </svg>
    @endforeach

    {{-- the section's scale --}}
    <path class="c-bar" d="M760 {{ 110 + $sh + 46 }}h{{ $bar }}@for ($i = 0; $i <= 5; $i++)M{{ 760 + round($bar * $i / 5, 1) }} {{ 110 + $sh + ($i % 5 ? 40 : 36) }}v{{ $i % 5 ? 12 : 20 }}@endfor"/>
    <text class="c-small" x="760" y="{{ 110 + $sh + 82 }}" direction="ltr">0</text>
    <text class="c-small" x="{{ 760 + $bar }}" y="{{ 110 + $sh + 82 }}" text-anchor="middle" direction="ltr">5 m</text>

    {{-- the title block --}}
    <g @if ($rtl) direction="rtl" @endif>
        <text class="c-kicker{{ $rtl ? ' is-rtl' : '' }}" x="{{ $tx }}" y="640">{{ $essay->extra('kicker') }}</text>
        <text class="c-title" x="{{ $tx }}" y="722">{{ $essay->extra('line1') }}</text>
        <text class="c-title" x="{{ $tx }}" y="786">{{ $essay->extra('line2') }}</text>
        <text class="c-note{{ $rtl ? ' is-rtl' : '' }}" x="{{ $tx }}" y="870">{{ $essay->extra('note') }}</text>
    </g>
    <text class="c-code" x="{{ $rtl ? 760 : 1530 }}" y="930" text-anchor="{{ $rtl ? 'start' : 'end' }}" direction="ltr">{{ $essay->extras['code'] ?? '' }}</text>
</svg>
