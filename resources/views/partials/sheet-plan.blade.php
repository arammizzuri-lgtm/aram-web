{{-- Behind a sheet's title: a courtyard house in plan, drawing itself line by
     line as the page opens (sheet.css) — the grid, the walls, the portico
     round the court, the doors and windows, then the dimensions and notes.
     Every drawn line has pathLength="1", so one dash draws any of them. --}}
<svg class="sheet-plan" viewBox="0 0 640 470" dir="ltr" aria-hidden="true" focusable="false">
    {{-- the structural grid: axes 1–4 and A–C with their bubbles --}}
    <g class="p-axis">
        <path d="M80 26V444M260 26V444M420 26V444M560 26V444M26 70H614M26 230H614M26 390H614"/>
    </g>
    <g class="p-bubble" style="--d:.15s">
        <path pathLength="1" d="M70 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M250 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M410 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M550 14a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 70a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 230a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
        <path pathLength="1" d="M4 390a10 10 0 1 0 20 0a10 10 0 1 0-20 0"/>
    </g>
    {{-- the outer walls, inner and outer face, open for the windows and the door --}}
    <g class="p-wall" style="--d:.35s">
        <path pathLength="1" d="M340 390H560V260M560 200V70H520M460 70H200M140 70H80V200M80 260V390H300"/>
        <path pathLength="1" d="M340 382H552V260M552 200V78H520M460 78H200M140 78H88V200M88 260V382H300"/>
    </g>
    {{-- the rooms round the court, and the court's portico on its columns --}}
    <g class="p-part" style="--d:.9s">
        <path pathLength="1" d="M260 78V160M420 78V160M260 300V382M420 300V382"/>
        <path pathLength="1" d="M88 230H190M230 230H260M420 230H500M540 230H552"/>
        <path pathLength="1" d="M260 160H300M380 160H420M260 300H300M380 300H420"/>
    </g>
    <g class="p-col" style="--d:1.2s">
        <path pathLength="1" d="M268 168h8v8h-8z"/><path pathLength="1" d="M336 168h8v8h-8z"/><path pathLength="1" d="M404 168h8v8h-8z"/>
        <path pathLength="1" d="M268 226h8v8h-8z"/><path pathLength="1" d="M404 226h8v8h-8z"/>
        <path pathLength="1" d="M268 284h8v8h-8z"/><path pathLength="1" d="M404 284h8v8h-8z"/>
        <path pathLength="1" d="M276 176H404V288H276Z"/>
        <path pathLength="1" d="M326 232a14 14 0 1 0 28 0a14 14 0 1 0-28 0"/>
        <path pathLength="1" d="M334 232a6 6 0 1 0 12 0a6 6 0 1 0-12 0"/>
    </g>
    {{-- doors swinging open, windows' glass --}}
    <g class="p-open" style="--d:1.5s">
        <path pathLength="1" d="M300 382V342A40 40 0 0 1 340 382"/>
        <path pathLength="1" d="M190 230V190A40 40 0 0 1 230 230"/>
        <path pathLength="1" d="M500 230V190A40 40 0 0 1 540 230"/>
        <path pathLength="1" d="M140 70V78M200 70V78M140 74H200M460 70V78M520 70V78M460 74H520"/>
        <path pathLength="1" d="M80 200H88M80 260H88M84 200V260M552 200H560M552 260H560M556 200V260M300 382V390M340 382V390"/>
    </g>
    {{-- dimensions along the top and the side, a north point --}}
    <g class="p-dim" style="--d:1.9s">
        <path pathLength="1" d="M80 46H560M76 50L84 42M256 50L264 42M416 50L424 42M556 50L564 42"/>
        <path pathLength="1" d="M50 70V390M46 74L54 66M46 234L54 226M46 394L54 386"/>
        <path pathLength="1" d="M596 432a14 14 0 1 0 28 0a14 14 0 1 0-28 0"/>
        <path pathLength="1" d="M610 446V418L604 430M610 418L616 430"/>
    </g>
    <g class="p-note">
        <text x="170" y="41" text-anchor="middle">6.75</text>
        <text x="340" y="41" text-anchor="middle">6.00</text>
        <text x="490" y="41" text-anchor="middle">5.25</text>
        <text x="44" y="150" text-anchor="middle" transform="rotate(-90 44 150)">6.00</text>
        <text x="44" y="310" text-anchor="middle" transform="rotate(-90 44 310)">6.00</text>
        <text x="80" y="18" text-anchor="middle" class="p-tag">1</text>
        <text x="260" y="18" text-anchor="middle" class="p-tag">2</text>
        <text x="420" y="18" text-anchor="middle" class="p-tag">3</text>
        <text x="560" y="18" text-anchor="middle" class="p-tag">4</text>
        <text x="14" y="74" text-anchor="middle" class="p-tag">A</text>
        <text x="14" y="234" text-anchor="middle" class="p-tag">B</text>
        <text x="14" y="394" text-anchor="middle" class="p-tag">C</text>
        <text x="174" y="158" text-anchor="middle">01 · 36.8 m²</text>
        <text x="174" y="310" text-anchor="middle">02 · 36.8 m²</text>
        <text x="340" y="124" text-anchor="middle">03 · 18.5 m²</text>
        <text x="486" y="158" text-anchor="middle">04 · 28.2 m²</text>
        <text x="486" y="310" text-anchor="middle">05 · 28.2 m²</text>
        <text x="340" y="268" text-anchor="middle">COURT</text>
        <text x="610" y="412" text-anchor="middle" class="p-tag">N</text>
        <text x="80" y="462" class="p-title">GROUND FLOOR PLAN   ·   1:100</text>
    </g>
</svg>
