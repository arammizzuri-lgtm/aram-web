{{-- Taq-e Bostan: how a hall was carved, not built. The cliff seen from the
     pool: (1) the site — a spring, its pool, and the stretch of cliff
     chosen, (2) the iwan hollowed out of the limestone: a vaulted room
     under a true semicircular arch, (3) the face dressed smooth and
     crowned — winged figures in the spandrels, a crescent between stepped
     battlements, trees of life beside the opening, (4) the walls carved:
     the investiture above, the armoured knight below, the royal hunt on
     the side walls, (5) the arch doubled in the pool. --}}
<svg class="tq__svg" viewBox="0 44 800 416" role="img">
    {{-- the mountain, the ground at its foot, the pool --}}
    <path class="tq-base" d="M20,176 L112,126 L204,146 L300,86 L424,102 L544,64 L664,108 L780,92"/>
    <path class="tq-base" d="M150,170 l-12,84 M230,160 l9,58 M642,136 l10,104 M592,96 l-6,46 M700,150 l-8,70 M96,200 l6,60" opacity=".6"/>
    <path class="tq-base" d="M20,380 H780"/>
    <path class="tq-base" d="M150,396 H650 L690,448 H110 Z"/>

    {{-- 1. a cliff and a spring: the water, and the stretch of rock chosen --}}
    <g data-step="1">
        <path class="tq-wf" d="M150,396 H650 L690,448 H110 Z"/>
        <path class="tq-w tq-flow" d="M604,356 q12,12 4,26 q-6,10 4,16"/>
        <path class="tq-w" d="M186,410 h56 M470,412 h72 M300,426 h92 M520,434 h84 M214,438 h52" opacity=".8"/>
        <path class="tq-t tq-dash" d="M250,380 V118 H550 V380"/>
    </g>

    {{-- 2. hollowed to a vault: the opening, and the room behind it --}}
    <g data-step="2">
        <path class="tq-l tq-k" d="M300,380 V250 A100,100 0 0 1 500,250 V380 Z"/>
        <path class="tq-t" d="M324,372 V262 A76,76 0 0 1 476,262 V372 Z"/>
        <path class="tq-t" d="M300,250 L324,262 M500,250 L476,262 M400,150 V186 M300,380 L324,372 M500,380 L476,372"/>
    </g>

    {{-- 3. the face dressed and crowned --}}
    <g data-step="3">
        <path class="tq-l" d="M250,380 V118 H550 V380"/>
        {{-- stepped battlements --}}
        @for ($i = 0; $i < 7; $i++)
            <path class="tq-t" d="M{{ 262 + $i * 41 }},118 v-7 h6 v-6 h6 v-6 h6 v6 h6 v6 h6 v7"/>
        @endfor
        {{-- the crescent on its ribbons, at the crown of the arch --}}
        <path class="tq-t" d="M390,131 A10,10 0 0 0 410,131 A14,14 0 0 1 390,131 Z M386,138 q-10,6 -18,0 M414,138 q10,6 18,0"/>
        {{-- a winged figure in each spandrel, holding out a diadem --}}
        @foreach ([0, 1] as $m)
            <g @if ($m) transform="translate(800,0) scale(-1,1)" @endif>
                <path class="tq-t" d="M270,224 q26,-40 74,-52"/>
                <circle class="tq-t" cx="350" cy="168" r="5"/>
                <path class="tq-t" d="M355,170 l12,-5"/>
                <circle class="tq-t" cx="371" cy="163" r="4"/>
                <path class="tq-t" d="M298,196 q-6,-30 -30,-42 M306,190 q0,-28 -18,-42 M314,184 q6,-22 -4,-38"/>
            </g>
        @endforeach
        {{-- a tree of life on either side of the opening --}}
        @foreach ([274, 526] as $x)
            <path class="tq-t" d="M{{ $x }},374 V268 q-9,-9 0,-18 q9,9 0,18 @foreach ([356, 336, 316, 296] as $y) M{{ $x }},{{ $y }} q-13,-2 -13,-13 M{{ $x }},{{ $y }} q13,-2 13,-13 @endforeach"/>
        @endforeach
    </g>

    {{-- 4. the walls carved --}}
    <g data-step="4">
        <path class="tq-l" d="M324,300 H476"/>
        {{-- above: the king between two gods --}}
        @foreach ([366, 400, 434] as $x)
            <circle class="tq-t" cx="{{ $x }}" cy="{{ $x === 400 ? 238 : 242 }}" r="6"/>
            <path class="tq-t" d="M{{ $x - 10 }},298 L{{ $x - 6 }},{{ $x === 400 ? 246 : 250 }} H{{ $x + 6 }} L{{ $x + 10 }},298 Z"/>
        @endforeach
        <path class="tq-t" d="M394,231 h12 M397,231 v-5 M403,231 v-5 M400,231 v-7"/>
        <circle class="tq-t" cx="383" cy="262" r="4"/>
        <path class="tq-t" d="M372,258 l8,3 M394,258 l-7,3 M422,260 l-6,8 l-4,-2"/>
        {{-- below: the knight in mail on his horse, lance in hand --}}
        <path class="tq-t" d="M376,338 q26,-11 56,0 q5,9 -1,14 h-54 q-6,-5 -1,-14 z M381,352 l-3,18 M392,352 l1,18 M420,352 l-1,18 M430,352 l3,18 M432,340 q11,3 9,20"/>
        <path class="tq-t" d="M378,340 q-9,-13 -5,-25 l-13,7 l2,7 l9,-3 M373,315 l3,-6"/>
        <path class="tq-t" d="M404,333 V314"/>
        <circle class="tq-t" cx="404" cy="308" r="6"/>
        <circle class="tq-t" cx="394" cy="324" r="7"/>
        <path class="tq-t" d="M352,332 L452,308"/>
        {{-- the side walls, covered with the hunt --}}
        @foreach ([0, 1] as $m)
            <g @if ($m) transform="translate(800,0) scale(-1,1)" @endif>
                @for ($y = 272; $y < 368; $y += 13)
                    <path class="tq-t" d="M304,{{ $y }} l5,-3 l5,3 M311,{{ $y + 6 }} l8,-2" opacity=".8"/>
                @endfor
            </g>
        @endforeach
    </g>

    {{-- 5. doubled in the pool --}}
    <g data-step="5">
        <path class="tq-w tq-dash" d="M300,398 V412 Q300,442 400,442 Q500,442 500,412 V398"/>
        <path class="tq-w tq-dash" d="M330,398 V410 Q330,430 400,430 Q470,430 470,410 V398" opacity=".7"/>
        <path class="tq-w" d="M250,398 V440 M550,398 V440" opacity=".45"/>
    </g>
</svg>
