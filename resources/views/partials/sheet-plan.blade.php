{{-- Behind a sheet's title: a house of the Erbil Citadel in plan, drawing
     itself line by line as the page opens (sheet.css). Off the alley (kolan)
     a bent way in through the dalan, so the court is never seen from the
     street; round the court the tarma on its timber columns, the iwan under
     its arch, the rooms with their wall niches, the kitchen with its tandoor,
     the serdab half a storey down for the summer; in the court the pool, a
     tree, the well and the stair up. 1 unit = 4 cm; axes 1–4 and A–D.
     Every drawn line has pathLength="1", so one dash draws any of them.
     sheet-plan.js lights the court by the sun over Erbil now, and draws CAD's
     crosshair under the mouse (p-sun, p-sunmark, p-cross). --}}
<svg class="sheet-plan" viewBox="0 0 640 470" dir="ltr" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="planHatch" width="5" height="5" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path class="p-hatch" d="M0 0V5"/></pattern>
        <clipPath id="planCourt"><rect x="238" y="188" width="198" height="178"/></clipPath>
        {{-- the court's shadow: all of it, less the patch the sun reaches --}}
        <mask id="planShade"><rect x="238" y="188" width="198" height="178" fill="#fff"/><rect class="p-sunlit" x="238" y="188" width="198" height="178" fill="#000"/></mask>
        <filter id="planGlow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="2.2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
    </defs>
    {{-- the structural grid: axes 1–4 and A–D with their bubbles --}}
    <g class="p-axis">
        <path d="M80 26V444M200 26V444M440 26V444M560 26V444M26 70H614M26 150H614M26 300H614M26 380H614"/>
    </g>
    <g class="p-bubble" style="--d:.15s">
        <path pathLength="1" d="M70 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M190 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M430 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M550 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 70a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 150a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 300a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 380a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
    </g>
    {{-- sun and shade on the court floor: hatched where the walls' shadow falls --}}
    <g class="p-sun">
        <rect class="p-shade" x="238" y="188" width="198" height="178" fill="url(#planHatch)" mask="url(#planShade)"/>
        <g clip-path="url(#planCourt)"><rect class="p-light p-sunlit" x="238" y="188" width="198" height="178"/></g>
    </g>
    {{-- the alley outside, the house across it --}}
    <g class="p-ctx" style="--d:.3s">
        <path pathLength="1" d="M56 420H580"/>
        <path pathLength="1" d="M108 380V386H144V380"/>
    </g>
    <rect class="p-poche" x="56" y="420" width="524" height="7" fill="url(#planHatch)"/>
    {{-- the outer walls, 56 cm of brick, inner and outer face; the street door; niches in their thickness --}}
    <g class="p-wall" style="--d:.35s">
        <path pathLength="1" d="M140 380H560V70H80V380H112"/>
        <path pathLength="1" d="M140 366H196M204 366H436M444 366H546V284M546 276V204M546 196V84H444M436 84H368M360 84H332V78H308V84H280M272 84H204M196 84H94V120H88V132H94V188M94 196V210H88V222H94V240H88V252H94V270H88V282H94V296M94 304V366H112"/>
        <path pathLength="1" d="M112 366V380M140 366V380"/>
    </g>
    {{-- the inner walls: the wings' fronts on the court, the rooms between them --}}
    <g class="p-part" style="--d:.9s">
        <path pathLength="1" d="M196 84V158M196 180V188M196 196V204M196 220V230M196 262V272M196 288V296M196 304V318M196 346V366"/>
        <path pathLength="1" d="M204 84V146M204 154V158M204 180V204M204 220V230M204 262V272M204 288V318M204 346V366"/>
        <path pathLength="1" d="M196 158H204M196 180H204M196 204H204M196 220H204M196 230H204M196 262H204M196 272H204M196 288H204M196 318H204M196 346H204"/>
        <path pathLength="1" d="M204 146H210M232 146H240M264 146H272M204 154H210M232 154H240M264 154H280M272 84V146M280 84V154"/>
        <path pathLength="1" d="M436 146H430M408 146H400M376 146H368M436 154H430M408 154H400M376 154H360M368 84V146M360 84V154"/>
        <path pathLength="1" d="M210 146V154M232 146V154M240 146V154M264 146V154M376 146V154M400 146V154M408 146V154M430 146V154"/>
        <path pathLength="1" d="M436 84V146M436 154V158M436 182V226M436 250V300M436 324V366"/>
        <path pathLength="1" d="M444 84V158M444 182V196M444 204V226M444 250V276M444 284V300M444 324V366"/>
        <path pathLength="1" d="M436 158H444M436 182H444M436 226H444M436 250H444M436 300H444M436 324H444"/>
        <path pathLength="1" d="M94 188H196M94 196H196M94 296H196M94 304H196M444 196H546M444 204H546M444 276H546M444 284H546"/>
    </g>
    {{-- the tarma: its timber columns on stone bases, its edge, the steps down from the iwan --}}
    <g class="p-col" style="--d:1.2s">
        <path pathLength="1" d="M233.5 183.5h9v9h-9z"/><path pathLength="1" d="M235.5 188a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M275.5 183.5h9v9h-9z"/><path pathLength="1" d="M277.5 188a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M355.5 183.5h9v9h-9z"/><path pathLength="1" d="M357.5 188a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M397.5 183.5h9v9h-9z"/><path pathLength="1" d="M399.5 188a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M233.5 220.5h9v9h-9z"/><path pathLength="1" d="M235.5 225a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M233.5 257.5h9v9h-9z"/><path pathLength="1" d="M235.5 262a2.5 2.5 0 1 0 5 0a2.5 2.5 0 1 0-5 0"/>
        <path pathLength="1" d="M204 300H238V266.5M238 257.5V229.5M238 220.5V192.5M242.5 188H275.5M284.5 188H355.5M364.5 188H397.5M406.5 188H436"/>
        <path pathLength="1" d="M290 188V200H350V188M290 194H350"/>
    </g>
    {{-- in the court and the rooms: the pool and its jet, the tree, the well, the stairs; the tandoor, a kilim, the dalan's bench --}}
    <g class="p-court" style="--d:1.35s">
        <path pathLength="1" d="M315 260H359L365 266V288L359 294H315L309 288V266Z"/>
        <path pathLength="1" d="M316.5 263H357.5L362 267.5V286.5L357.5 291H316.5L312 286.5V267.5Z"/>
        <path pathLength="1" d="M334 277a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/>
        <path pathLength="1" d="M244 330a26 26 0 1 0 52 0a26 26 0 1 0-52 0"/>
        <path pathLength="1" d="M262 322h16v16h-16z"/><path pathLength="1" d="M267 330a3 3 0 1 0 6 0a3 3 0 1 0-6 0"/>
        <path pathLength="1" d="M405 214a7 7 0 1 0 14 0a7 7 0 1 0-14 0"/><path pathLength="1" d="M408 214a4 4 0 1 0 8 0a4 4 0 1 0-8 0"/>
        <path pathLength="1" d="M300 366V340H420V366M310 340V366M320 340V366M330 340V366M340 340V366M350 340V366M360 340V366M370 340V366M380 340V366M390 340V366M400 340V366M410 340V366"/>
        <path pathLength="1" d="M470 366V336H546M480 336V366M490 336V366M500 336V366M510 336V366M520 336V366M530 336V366M540 336V366"/>
        <path pathLength="1" d="M506 106a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/><path pathLength="1" d="M510 106a6 6 0 1 0 12 0a6 6 0 1 0-12 0"/>
        <path pathLength="1" d="M546 128H530V188H546"/>
        <path pathLength="1" d="M118 228h56v56h-56z"/><path pathLength="1" d="M122 232h48v48h-48z"/>
        <path pathLength="1" d="M94 314H106V358H94"/>
    </g>
    {{-- overhead, dashed: the tarma's eaves, the iwan's arch --}}
    <g class="p-over">
        <path d="M246 300V196H436M280 150H360"/>
    </g>
    {{-- doors swinging open; the windows' glass, which glows at night --}}
    <g class="p-open" style="--d:1.5s">
        <path pathLength="1" d="M112 366V338A28 28 0 0 1 140 366"/>
        <path pathLength="1" d="M204 346H232A28 28 0 0 0 204 318"/>
        <path pathLength="1" d="M196 180H174A22 22 0 0 1 196 158"/>
        <path pathLength="1" d="M196 230H180A16 16 0 0 0 196 246M196 262H180A16 16 0 0 1 196 246"/>
        <path pathLength="1" d="M210 146V124A22 22 0 0 1 232 146"/>
        <path pathLength="1" d="M430 146V124A22 22 0 0 0 408 146"/>
        <path pathLength="1" d="M444 158H468A24 24 0 0 1 444 182"/>
        <path pathLength="1" d="M444 226H468A24 24 0 0 1 444 250"/>
        <path pathLength="1" d="M444 300H468A24 24 0 0 1 444 324"/>
        <path pathLength="1" class="p-glass" d="M200 204V220M200 272V288M240 150H264M376 150H400"/>
    </g>
    {{-- dimensions, the stairs' arrows, levels, a north point --}}
    <g class="p-dim" style="--d:1.9s">
        <path pathLength="1" d="M80 46H560M76 50L84 42M196 50L204 42M436 50L444 42M556 50L564 42"/>
        <path pathLength="1" d="M50 70V380M46 74L54 66M46 154L54 146M46 304L54 296M46 384L54 376"/>
        <path pathLength="1" d="M304 353H414M408 349L414 353L408 357"/>
        <path pathLength="1" d="M474 351H538M532 347L538 351L532 355"/>
        <path pathLength="1" d="M366 310h8l-4 6z"/><path pathLength="1" d="M243 165h8l-4 6z"/><path pathLength="1" d="M486 318h8l-4 6z"/>
        <path pathLength="1" d="M596 432a14 14 0 1 0 28 0a14 14 0 1 0-28 0"/>
        <path pathLength="1" d="M610 446V418L604 430M610 418L616 430"/>
    </g>
    {{-- where the sun stands now, round the north point --}}
    <g class="p-sunmark">
        <path class="p-sunray" d="M610 432L610 408"/>
        <circle class="p-sundot" cx="610" cy="408" r="3.5"/>
    </g>
    <g class="p-note">
        <text x="140" y="41" text-anchor="middle">4.80</text>
        <text x="320" y="41" text-anchor="middle">9.60</text>
        <text x="500" y="41" text-anchor="middle">4.80</text>
        <text x="44" y="110" text-anchor="middle" transform="rotate(-90 44 110)">3.20</text>
        <text x="44" y="225" text-anchor="middle" transform="rotate(-90 44 225)">6.00</text>
        <text x="44" y="340" text-anchor="middle" transform="rotate(-90 44 340)">3.20</text>
        <text x="80" y="18" text-anchor="middle" class="p-tag">1</text>
        <text x="200" y="18" text-anchor="middle" class="p-tag">2</text>
        <text x="440" y="18" text-anchor="middle" class="p-tag">3</text>
        <text x="560" y="18" text-anchor="middle" class="p-tag">4</text>
        <text x="14" y="74" text-anchor="middle" class="p-tag">A</text>
        <text x="14" y="154" text-anchor="middle" class="p-tag">B</text>
        <text x="14" y="304" text-anchor="middle" class="p-tag">C</text>
        <text x="14" y="384" text-anchor="middle" class="p-tag">D</text>
        <text x="156" y="322" text-anchor="middle" class="p-room">DALAN</text><text x="156" y="334" text-anchor="middle">01 · 10.1 m²</text>
        <text x="146" y="210" text-anchor="middle" class="p-room">GUEST HALL</text><text x="146" y="221" text-anchor="middle">02 · 16.3 m²</text>
        <text x="145" y="128" text-anchor="middle" class="p-room">ROOM</text><text x="145" y="140" text-anchor="middle">03 · 17.0 m²</text>
        <text x="238" y="104" text-anchor="middle" class="p-room">ROOM</text><text x="238" y="116" text-anchor="middle">04 · 6.7 m²</text>
        <text x="320" y="108" text-anchor="middle" class="p-room">IWAN</text><text x="320" y="120" text-anchor="middle">05 · 9.0 m²</text>
        <text x="402" y="104" text-anchor="middle" class="p-room">ROOM</text><text x="402" y="116" text-anchor="middle">06 · 6.7 m²</text>
        <text x="488" y="140" text-anchor="middle" class="p-room">KITCHEN</text><text x="488" y="152" text-anchor="middle">07 · 18.3 m²</text>
        <text x="495" y="236" text-anchor="middle" class="p-room">STORE</text><text x="495" y="248" text-anchor="middle">08 · 11.8 m²</text>
        <text x="500" y="298" text-anchor="middle" class="p-room">SERDAB</text><text x="500" y="310" text-anchor="middle">09 · 13.4 m²</text>
        <text x="320" y="174" text-anchor="middle" class="p-room">TARMA</text>
        <text x="337" y="316" text-anchor="middle" class="p-room">COURT</text><text x="337" y="327" text-anchor="middle">10 · 56.4 m²</text>
        <text x="377" y="316">±0.00</text><text x="254" y="171">+0.45</text><text x="497" y="324">−1.80</text>
        <text x="428" y="356" text-anchor="middle">UP</text><text x="456" y="354">DN</text>
        <text x="330" y="406" text-anchor="middle" class="p-room">KOLAN · ALLEY</text>
        <text x="610" y="412" text-anchor="middle" class="p-tag">N</text>
        <text x="580" y="446" text-anchor="end" class="p-sunnote"></text>
        <text x="80" y="462" class="p-title">CITADEL HOUSE, HAWLÊR   ·   GROUND FLOOR   ·   1:100</text>
    </g>
    {{-- CAD's crosshair, its pick box, the snap mark and the point's coordinates in metres --}}
    <g class="p-cross">
        <path class="p-cross__h" d="M26 0H614"/>
        <path class="p-cross__v" d="M0 26V444"/>
        <rect class="p-cross__box" x="-4" y="-4" width="8" height="8"/>
        <path class="p-cross__snap" d="M-5-5L5 5M5-5L-5 5"/>
        <text class="p-cross__xy" x="0" y="0"></text>
    </g>
</svg>
