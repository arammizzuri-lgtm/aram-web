{{-- A glossary term's drawing when it is not a plan (plans are journal/type): a section or an elevation in the
     same 200 x 150 frame and the same lines, ours, drawn to explain, not surveys. $key picks it; $id keeps its defs apart. --}}
@php
    $w = fn (string $en, string $ku, string $ar) => bi($en, $ku, $ar);
    $f = fn ($n) => round($n, 1);
@endphp
<svg class="ja-dwg ja-type" viewBox="0 0 200 150" style="--u:1px;font-size:10px" aria-hidden="true">
    <defs>
        <pattern id="{{ $id }}-h" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="d-hatch" d="M0 0V5"/></pattern>
    </defs>
    @switch ($key)
        @case('sirdab')
            {{-- a room above, the sirdab below, the earth around it; a grille just above the ground lets air in --}}
            <path d="M8 88H44V128H156V88H192V142H8Z" fill="url(#{{ $id }}-h)" class="d-shade"/>
            @php $wall = 'M44 30H156V128H44Z M50 36H150V74H50Z M50 80H150V122H50Z'; @endphp
            <path class="d-poche" fill-rule="evenodd" d="{{ $wall }}"/>
            <path class="d-wall dl" pathLength="1" d="{{ $wall }}"/>
            <path class="t-cut" d="M43.4 81h7.2v6h-7.2zM54 73.4h16v7.2h-16z"/>
            <path class="d-glass dl" pathLength="1" d="M46.2 81v6M47.8 81v6"/>
            <path class="d-stair dl" pathLength="1" d="M54 74v8h6v8h6v8h6v8h6v8h6v8h6"/>
            <path class="d-wall dl" pathLength="1" d="M8 88H44M156 88H192"/>
            <g class="d-text">
                <text x="100" y="58" class="p-room" text-anchor="middle">{{ $w('ROOM', 'ژوور', 'غرفة') }}</text>
                <text x="122" y="104" class="p-room" text-anchor="middle">{{ $w('SIRDAB', 'سەرداب', 'السرداب') }}</text>
            </g>
            @break

        @case('sarban')
            {{-- the roof in section: poplar poles, reeds, packed earth, and the stone roller on top --}}
            @php
                $poles = '';
                for ($x = 42; $x <= 159; $x += 13) { $poles .= 'M'.($x - 4.5).' 80a4.5 4.5 0 1 0 9 0a4.5 4.5 0 1 0 -9 0'; }
                $reeds = 'M34 70H166M34 75H166';
                for ($x = 36; $x < 166; $x += 3) { $reeds .= 'M'.$x.' 70.6l1.4 3.8'; }
            @endphp
            <path d="M34 48H166V70H34Z" fill="url(#{{ $id }}-h)" class="d-shade"/>
            <path class="d-poche" d="M20 28h14v116H20zM166 28h14v116h-14z{{ $poles }}"/>
            <path class="d-wall dl" pathLength="1" d="M20 28h14v116H20zM166 28h14v116h-14zM34 48H166"/>
            <path class="d-wall dl" pathLength="1" d="{{ $poles }}"/>
            <path class="d-fix dl" pathLength="1" d="{{ $reeds }}"/>
            <path class="d-fix dl" pathLength="1" d="M103 41a7 7 0 1 0 14 0a7 7 0 1 0 -14 0M110 41L136 25M140 96V75M68 96V85"/>
            <g class="d-text">
                <text x="70" y="62" class="p-room" text-anchor="middle">{{ $w('EARTH', 'گڵ', 'طين') }}</text>
                <text x="140" y="106" class="p-note" text-anchor="middle">{{ $w('REEDS', 'قامیش', 'قصب') }}</text>
                <text x="68" y="106" class="p-note" text-anchor="middle">{{ $w('POPLAR POLES', 'داری سپیندار', 'جذوع الحور') }}</text>
            </g>
            @break

        @case('taq')
            {{-- an arch: the ring of bricks set on their ends, every joint pointing at the centre --}}
            @php
                $ring = 'M36 136V88H46A54 54 0 0 1 154 88H164V136H140V88A40 40 0 0 0 60 88V136Z';
                $joints = '';
                for ($k = 1; $k < 12; $k++) {
                    $a = deg2rad($k * 15);
                    $joints .= 'M'.$f(100 - 40 * cos($a)).' '.$f(88 - 40 * sin($a)).'L'.$f(100 - 54 * cos($a)).' '.$f(88 - 54 * sin($a));
                }
            @endphp
            <path class="d-ctx dl" pathLength="1" d="M36 88V22M164 88V22M36 22H164"/>
            <path class="d-axis" d="M24 88H176"/>
            <path class="d-poche" d="{{ $ring }}"/>
            <path class="d-wall dl" pathLength="1" d="{{ $ring }}"/>
            <path class="d-fix dl" pathLength="1" d="{{ $joints }}"/>
            <path class="d-wall dl" pathLength="1" d="M14 136H186"/>
            <g class="d-text"><text x="100" y="118" class="p-room" text-anchor="middle">{{ $w('TAQ', 'تاق', 'الطاق') }}</text></g>
            @break

        @case('khisht')
            {{-- a wall of fired brick in courses, and a band laid in a pattern, as on the minaret --}}
            @php
                $courses = '';
                for ($k = 0; $k < 16; $k++) {
                    $y = 18 + 7 * $k;
                    if ($k >= 6 && $k <= 9) { continue; }
                    $courses .= 'M20 '.($y + 7).'H180';
                    for ($x = 20 + ($k % 2) * 11 + 22; $x < 180; $x += 22) { $courses .= 'M'.$x.' '.$y.'v7'; }
                }
                $band = '';
                for ($x = 20; $x < 180; $x += 20) { $band .= 'M'.$x.' 74l10 -12l10 12l-10 12z'; }
            @endphp
            <path class="d-poche" d="M20 60H180V88H20Z"/>
            <path class="d-fix dl" pathLength="1" d="{{ $courses }}"/>
            <path class="d-wall dl" pathLength="1" d="M20 18H180V130H20ZM20 60H180M20 88H180{{ $band }}"/>
            @break

        @case('marble')
            {{-- a doorcase of Mosul alabaster: rosettes on the lintel, small diamonds down the jambs, two leaves --}}
            @php
                $frame = 'M62 30H138V136H124V50H76V136H62Z';
                $carve = 'M65.5 33.5H134.5V136M65.5 136V33.5';
                foreach ([72, 86, 100, 114, 128] as $x) { $carve .= 'M'.($x - 4).' 40a4 4 0 1 0 8 0a4 4 0 1 0 -8 0M'.$x.' 36v8M'.($x - 4).' 40h8'; }
                for ($y = 58; $y < 130; $y += 12) { $carve .= "M69 {$y}l3 4l-3 4l-3 -4zM131 {$y}l3 4l-3 4l-3 -4z"; }
            @endphp
            <path class="d-ctx dl" pathLength="1" d="M24 18V136M176 18V136"/>
            <path class="d-poche" fill-rule="evenodd" d="{{ $frame }}"/>
            <path class="d-wall dl" pathLength="1" d="{{ $frame }}"/>
            <path class="d-fix dl" pathLength="1" d="{{ $carve }}"/>
            <path class="d-leaf dl" pathLength="1" d="M100 50V136"/>
            <path class="d-fix dl" pathLength="1" d="M81 57h14v34h-14zM81 97h14v33h-14zM105 57h14v34h-14zM105 97h14v33h-14z"/>
            <path class="d-wall dl" pathLength="1" d="M14 136H186"/>
            @break

        @case('qaysari')
            {{-- across a Qaysari passage: shops below, rooms above off a timber gallery, the street under a brick vault --}}
            @php $shell = 'M20 58h60v6H26v34h48v6H26v28h-6zM180 58h-60v6h54v34h-48v6h48v28h6zM74 64h6v40h-6zM120 64h6v40h-6zM74 64A26 26 0 0 1 126 64H120A20 20 0 0 0 80 64Z'; @endphp
            <path d="M14 132H186V142H14Z" fill="url(#{{ $id }}-h)" class="d-shade"/>
            <path class="d-poche" d="{{ $shell }}"/>
            <path class="d-wall dl" pathLength="1" d="{{ $shell }}"/>
            <path class="d-fix dl" pathLength="1" d="M80 99H91V89M80 89H91M84 99V89M87.5 99V89M120 99H109V89M120 89H109M116 99V89M112.5 99V89"/>
            <path class="d-wall dl" pathLength="1" d="M14 132H186"/>
            <g class="d-text">
                <text x="50" y="121" class="p-room" text-anchor="middle">{{ $w('SHOP', 'دوکان', 'دكان') }}</text>
                <text x="150" y="121" class="p-room" text-anchor="middle">{{ $w('SHOP', 'دوکان', 'دكان') }}</text>
                <text x="50" y="84" class="p-room" text-anchor="middle">{{ $w('ROOM', 'ژوور', 'غرفة') }}</text>
                <text x="150" y="84" class="p-room" text-anchor="middle">{{ $w('ROOM', 'ژوور', 'غرفة') }}</text>
                <text x="100" y="121" class="p-note" text-anchor="middle">{{ $w('PASSAGE', 'ڕاڕەو', 'ممر') }}</text>
            </g>
            @break

        @case('kahrez')
            {{-- a kahrez in section: the mother well down to the water under the hills, the tunnel running out at a gentler
                 slope than the ground until it surfaces among the fields, and the shafts it was dug from, each with its spoil --}}
            @php
                $ground = fn ($x) => 24 + ($x - 8) * .4565;
                $tunnel = fn ($x) => 80 + ($x - 22) * .03;
                $out = 138.4;   // where the tunnel meets the ground
                $shafts = $spoil = $crops = '';
                foreach ([38, 54, 70, 86, 102, 118] as $x) {
                    $shafts .= 'M'.($x - 1.6).' '.$f($ground($x - 1.6)).'V'.$f($tunnel($x)).'M'.($x + 1.6).' '.$f($ground($x + 1.6)).'V'.$f($tunnel($x));
                    $spoil .= 'M'.($x - 7).' '.$f($ground($x - 7)).'Q'.($x - 4).' '.$f($ground($x - 4) - 5).' '.($x - 1.6).' '.$f($ground($x - 1.6) - 2);
                }
                foreach ([148, 158, 168, 178] as $x) { $crops .= 'M'.$x.' '.$f($ground($x)).'l3 -7'; }
            @endphp
            <path d="M8 24L192 108V142H8Z" fill="url(#{{ $id }}-h)" class="d-shade"/>
            <path class="t-cut" d="M18 {{ $f($ground(18)) }}H22V80L{{ $out }} {{ $f($tunnel($out)) }}V{{ $f($tunnel($out) + 4) }}L18 84Z"/>
            <path class="d-wall dl" pathLength="1" d="M8 24L192 108"/>
            <path class="d-wall dl" pathLength="1" d="M18 {{ $f($ground(18)) }}V84L{{ $out }} {{ $f($tunnel($out) + 4) }}M22 {{ $f($ground(22)) }}V80L{{ $out }} {{ $f($tunnel($out)) }}"/>
            <path class="d-fix dl" pathLength="1" d="{{ $shafts }}{{ $spoil }}{{ $crops }}"/>
            <path class="d-arrow dl" pathLength="1" d="M56 83L74 83.5M70.5 80.8L74 83.5L70.3 86.2"/>
            <g class="d-text">
                <text x="14" y="16" class="p-note">{{ $w('MOTHER WELL', 'بیری دایک', 'البئر الأم') }}</text>
                <text x="96" y="110" class="p-room" text-anchor="middle">{{ $w('KAHREZ', 'کارێز', 'الكهريز') }}</text>
                <text x="166" y="78" class="p-note" text-anchor="middle">{{ $w('FIELDS', 'کێڵگە', 'الحقول') }}</text>
            </g>
            @break
    @endswitch
</svg>
