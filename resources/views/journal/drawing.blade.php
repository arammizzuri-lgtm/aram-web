{{-- One plan, section or elevation of a journal essay's plates, drawn the way the sheet's
     plan is (partials/sheet-plan): walls in double line, doors swinging, windows, stairs with their
     arrows, rooms named with their areas, the axes and their dimensions, a north point and a scale.
     $dwg comes from an essay's drawings file or the admin's tracer (Drawing::resolve; all in cm); $id keeps its defs apart; $name is for readers who cannot see it; $rel (0–1) is its width against
     the widest drawing on its plate, so the floors of one house share a scale.
     Lines draw themselves as the drawing comes into view, and the court is lit by the sun over Erbil
     now (journal-drawing.js). --}}
@php
    $d = $dwg['d'];
    [$vx, $vy, $vw, $vh] = $dwg['vb'];
    $fs = $dwg['fs'];
    $words = [
        'room' => bi('ROOM', 'ژوور', 'غرفة'), 'tarma' => bi('TARMA', 'تارمە', 'طارمة'), 'court' => bi('COURT', 'حەوشە', 'الفناء'),
        'bath' => bi('BATH', 'گەرماو', 'حمّام'), 'wc' => bi('WC', 'ئاودەست', 'مرحاض'), 'iwan' => bi('IWAN', 'ئەیوان', 'إيوان'),
        'kitchen' => bi('KITCHEN', 'چێشتخانە', 'مطبخ'), 'store' => bi('STORE', 'کۆگا', 'مخزن'), 'serdab' => bi('SERDAB', 'سەرداب', 'سرداب'),
        'dalan' => bi('DALAN', 'دالان', 'دهليز'), 'hall' => bi('HALL', 'هۆڵ', 'قاعة'), 'arcade' => bi('ARCADE', 'ڕیزە کەوانە', 'رواق'),
        'qaa' => bi('QA‘A', 'قاعە', 'القاعة'), 'diwan' => bi('DIWAN', 'دیوەخان', 'ديوان'), 'stable' => bi('STABLE', 'تەویلە', 'إسطبل'),
        'stair' => bi('STAIR', 'پەیژە', 'درج'), 'alley' => bi('KOLAN · ALLEY', 'کۆڵان', 'الزقاق'), 'void' => bi('OPEN TO BELOW', 'بۆشایی بۆ خوارەوە', 'فراغ إلى الأسفل'),
        'up' => bi('UP', 'سەر', 'صعود'), 'dn' => bi('DN', 'خوار', 'نزول'), 'roof' => bi('ROOF', 'سەربان', 'سطح'), 'gallery' => bi('GALLERY', 'باڵەخانە', 'رواق علوي'),
        'yard' => bi('YARD', 'حەوشە', 'الحوش'), 'basin' => bi('BASIN', 'حەوز', 'حوض'), 'entry' => bi('ENTRY', 'دەروازە', 'المدخل'),
        'shop' => bi('SHOP', 'دوکان', 'دكان'), 'passage' => bi('PASSAGE', 'ڕاڕەو', 'الممر'), 'lane' => bi('BAZAAR LANE', 'کۆڵانی بازاڕ', 'زقاق السوق'),
        'cellar' => bi('CELLAR', 'ژێرزەمین', 'قبو'),
        // a site plan's places, named as OpenStreetMap names them
        'citadel' => bi('CITADEL', 'قەڵا', 'القلعة'), 'qaysari' => bi('QAYSARI BAZAAR', 'بازاڕی قەیسەری', 'سوق القيصرية'),
        'park' => bi('SHAR PARK', 'باغی شار', 'حديقة شار'), 'gate' => bi('GRAND GATE', 'دەروازەی گەورە', 'البوابة الكبرى'),
    ];
    $lit = $dwg['court'] && $dwg['north'] !== null;   // a court whose north we know gets the sun; one without, plain paving
    $say = fn (string $v) => str_starts_with($v, '@') ? ($words[substr($v, 1)] ?? substr($v, 1)) : $v;
@endphp
<svg class="ja-dwg" viewBox="{{ $vx }} {{ $vy }} {{ $vw }} {{ $vh }}" style="--u:{{ round($fs / 8.5, 2) }}px;font-size:{{ $fs }}px;width:{{ round(($rel ?? 1) * 100, 1) }}%" role="img" aria-label="{{ $name }}"
    @if ($lit) data-north="{{ $dwg['north'] }}" data-wallh="{{ $dwg['wallH'] }}" data-np="{{ implode(',', $dwg['np']) }}" data-nr="{{ $dwg['nr'] }}" @endif>
    @if ($dwg['court'])
        <defs>
            <pattern id="{{ $id }}-h" width="{{ round($fs * .55, 1) }}" height="{{ round($fs * .55, 1) }}" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="d-hatch" d="M0 0V{{ round($fs * .55, 1) }}"/></pattern>
            <clipPath id="{{ $id }}-c"><path d="{{ $dwg['court'] }}"/></clipPath>
            @if ($lit)<mask id="{{ $id }}-m"><path d="{{ $dwg['court'] }}" fill="#fff"/><path class="d-sunlit" d="{{ $dwg['court'] }}" fill="#000"/></mask>@endif
        </defs>
    @endif
    <path class="d-axis" d="{{ $d['axis'] }}"/>
    @if ($dwg['court'])
        <g class="d-sun{{ $lit ? '' : ' is-still' }}">
            <path class="d-shade" d="{{ $dwg['court'] }}" fill="url(#{{ $id }}-h)" @if ($lit) mask="url(#{{ $id }}-m)" @endif/>
            @if ($lit)<g clip-path="url(#{{ $id }}-c)"><path class="d-light d-sunlit" d="{{ $dwg['court'] }}"/></g>@endif
        </g>
    @endif
    @if ($d['ctx'])<path class="d-ctx dl" pathLength="1" d="{{ $d['ctx'] }}"/>@endif
    <path class="d-poche" fill-rule="evenodd" d="{{ $d['wall'] }}"/>
    <path class="d-wall dl" pathLength="1" d="{{ $d['wall'] }}"/>
    @if ($d['cut'] ?? '')<path class="d-cut" d="{{ $d['cut'] }}"/>@endif{{-- openings added in the admin's tracer, cut through the wall --}}
    @if ($d['over'])<path class="d-over" d="{{ $d['over'] }}"/>@endif
    @foreach (['fix', 'stair', 'open', 'leaf', 'glass', 'arrow', 'dim', 'bubble', 'north', 'scale'] as $g)
        @if ($d[$g] ?? '')<path class="d-{{ $g }} dl" pathLength="1" d="{{ $d[$g] }}"/>@endif
    @endforeach
    @if ($lit)
        <g class="d-sunmark"><path class="d-sunray" d="M0 0"/><circle class="d-sundot" r="{{ round($fs * .4, 1) }}"/></g>
    @endif
    <g class="d-text">
        @foreach ($dwg['text'] as $tx)
            <text x="{{ $tx[0] }}" y="{{ $tx[1] }}" class="{{ $tx[2] }}" text-anchor="{{ $tx[4] ?? 'middle' }}" @isset($tx[5]) transform="rotate({{ $tx[5] }} {{ $tx[0] }} {{ $tx[1] }})" @endisset>{{ $say($tx[3]) }}</text>
        @endforeach
        @if ($lit)<text x="{{ $dwg['np'][0] - $dwg['nr'] * 2.2 }}" y="{{ $dwg['title'][1] }}" class="d-sunnote" text-anchor="end"></text>@endif
    </g>
</svg>
