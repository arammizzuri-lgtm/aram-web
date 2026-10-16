@php
    /**
     * A site board (BoardController::poster): the poster for the fence of a
     * building going up, in English and Kurdish together — the people who
     * pass it read either — sized to an A-series sheet and laid out in
     * units of its own width, so it is the same at A3 as at A0.
     *
     * @var \App\Models\Project $project
     * @var string $size  a3 | a2 | a1 | a0
     * @var array{0: int, 1: int} $mm
     * @var string $theme  dark | light
     */

    $building = $project->statusClass() === 'build';
    $phone = setting('contact_whatsapp') ?: setting('contact_phone', '+964 (0) 782 445 4414');
    $nameKu = $project->name_ku ?: null;
    $link = fn (array $q) => route('filament.studio-aks-panel.project-board', $project).'?'.http_build_query(array_merge(['size' => $size, 'theme' => $theme], $q));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Site board — {{ $project->name }} ({{ strtoupper($size) }})</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&display=swap" rel="stylesheet">
    <style>
        @font-face { font-family: 'Speda'; src: url('/fonts/speda.ttf') format('truetype'); font-weight: 400 700; font-display: block; }
        @page { size: {{ $mm[0] }}mm {{ $mm[1] }}mm; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --gold: #F5C518; }
        html, body { background: #2a2a2a; }
        body { font-family: 'Inter', 'Speda', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        [dir="rtl"], .ku { font-family: 'Speda', 'Inter', sans-serif; }

        /* the toolbar, on screen only */
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px; padding: 12px 20px;
            background: #111; color: #ddd; font-size: 13px; border-bottom: 1px solid #333; }
        .bar b { color: #fff; font-weight: 500; }
        .bar a, .bar button { color: #ddd; text-decoration: none; padding: 6px 12px; border-radius: 100px; border: 1px solid #444; background: none; font: inherit; cursor: pointer; }
        .bar a.on { border-color: var(--gold); color: var(--gold); }
        .bar button { background: var(--gold); color: #111; border-color: var(--gold); font-weight: 500; }
        .bar span { color: #888; }
        .stage { padding: 24px; display: grid; place-items: center; }

        /* the board: a sheet of the chosen size, everything measured in its width */
        .board {
            --w: {{ $mm[0] }}; --h: {{ $mm[1] }};
            width: min(94vw, calc((100vh - 110px) * var(--w) / var(--h))); aspect-ratio: var(--w) / var(--h);
            container-type: inline-size; position: relative; overflow: hidden;
            display: flex; flex-direction: column;
            padding: 5.5cqw 6cqw 4.5cqw;
            background: #0b0b0b; color: #f2efe8; box-shadow: 0 20px 60px rgba(0,0,0,.5);
        }
        .board--light { background: #fbfaf6; color: #141414; }
        .b-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 4cqw; }
        .b-brand b { display: block; font-size: 3.6cqw; font-weight: 500; letter-spacing: .32em; }
        .b-brand span { display: block; margin-top: .9cqw; font-size: 1.5cqw; letter-spacing: .3em; text-transform: uppercase; color: var(--gold); }
        .board--light .b-brand span { color: #9a7a00; }
        .b-brand--ku { text-align: right; }
        .b-brand--ku b { letter-spacing: 0; font-size: 4cqw; font-weight: 400; }
        .b-brand--ku span { letter-spacing: 0; text-transform: none; font-size: 2cqw; }

        .b-photo { position: relative; margin: 4.5cqw 1.5cqw 0; flex: 1 1 auto; min-height: 0; }
        .b-photo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: {{ $project->coverPosition() }}; }
        .b-crop { position: absolute; width: 3.4cqw; height: 3.4cqw; border: 0 solid var(--gold); }
        .b-crop--tl { top: -1.5cqw; left: -1.5cqw; border-top-width: .25cqw; border-left-width: .25cqw; }
        .b-crop--tr { top: -1.5cqw; right: -1.5cqw; border-top-width: .25cqw; border-right-width: .25cqw; }
        .b-crop--bl { bottom: -1.5cqw; left: -1.5cqw; border-bottom-width: .25cqw; border-left-width: .25cqw; }
        .b-crop--br { bottom: -1.5cqw; right: -1.5cqw; border-bottom-width: .25cqw; border-right-width: .25cqw; }

        .b-head { display: flex; justify-content: space-between; align-items: baseline; gap: 3cqw; margin-top: 4.2cqw; color: var(--gold); }
        .board--light .b-head { color: #9a7a00; }
        .b-head span { font-size: 5.4cqw; font-weight: 200; line-height: 1; }
        .b-head span[dir="rtl"] { font-weight: 400; font-size: 5.8cqw; }

        /* the title block, as every drawing sheet carries one */
        .b-block { display: grid; grid-template-columns: 17cqw minmax(0, 1fr) 30cqw; margin-top: 3.2cqw; border: .18cqw solid currentColor; }
        .b-cell { padding: 1.6cqw 2cqw; border-left: .18cqw solid currentColor; min-width: 0; }
        .b-cell:first-child { border-left: 0; }
        .b-cell small { display: block; font-size: 1.15cqw; letter-spacing: .26em; text-transform: uppercase; opacity: .55; }
        .b-num b { display: block; margin-top: .6cqw; font-size: 7.2cqw; font-weight: 200; color: var(--gold); line-height: 1; }
        .board--light .b-num b { color: #9a7a00; }
        .b-name b { display: block; margin-top: .9cqw; font-size: 3.4cqw; font-weight: 300; line-height: 1.15; overflow-wrap: anywhere; }
        .b-name b[dir="rtl"] { font-weight: 400; font-size: 3.5cqw; margin-top: 1cqw; }
        .b-status b { display: flex; align-items: center; gap: 1.2cqw; margin-top: 1cqw; font-size: 2.2cqw; font-weight: 400; }
        .b-status b[dir="rtl"] { margin-top: .9cqw; }
        .b-status i { width: 1.4cqw; height: 1.4cqw; border-radius: 50%; background: {{ ['done' => '#4ecb71', 'build' => '#F5C518'][$project->statusClass()] ?? '#999' }}; flex: none; }

        /* the QR code, on white whatever the board, and what it is for */
        .b-scan { display: grid; grid-template-columns: 27cqw minmax(0, 1fr); gap: 4cqw; align-items: center; margin-top: 3.6cqw; }
        .b-qr { background: #fff; padding: 1.2cqw; border-radius: 1cqw; line-height: 0; }
        .b-qr svg { width: 100%; height: auto; display: block; }
        .b-qr .qr-data-dark { fill: #000; }
        .b-scan__en { font-size: 3.6cqw; font-weight: 300; line-height: 1.15; }
        .b-scan__ku { margin-top: 1.4cqw; font-size: 3.6cqw; line-height: 1.3; }
        .b-scan__url { margin-top: 2.4cqw; font-size: 2.6cqw; font-weight: 500; color: var(--gold); letter-spacing: .02em; }
        .board--light .b-scan__url { color: #9a7a00; }
        .b-scan__tel { margin-top: 1cqw; font-size: 2.3cqw; font-weight: 300; opacity: .8; direction: ltr; }

        .b-foot { display: flex; justify-content: space-between; gap: 3cqw; margin-top: 3.4cqw; padding-top: 1.8cqw; border-top: .18cqw solid rgba(245,197,24,.5);
            font-size: 1.7cqw; letter-spacing: .12em; text-transform: uppercase; opacity: .85; }
        .b-foot span[dir="rtl"] { letter-spacing: 0; text-transform: none; font-size: 2cqw; }

        @media print {
            html, body { background: none; }
            .bar { display: none; }
            .stage { padding: 0; display: block; }
            .board { width: {{ $mm[0] }}mm; height: {{ $mm[1] }}mm; aspect-ratio: auto; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <b>{{ $project->name }}</b>
        <span>Size</span>
        @foreach (array_keys(\App\Http\Controllers\BoardController::SIZES) as $s)
            <a href="{{ $link(['size' => $s]) }}" @class(['on' => $s === $size])>{{ strtoupper($s) }}</a>
        @endforeach
        <span>Board</span>
        <a href="{{ $link(['theme' => 'dark']) }}" @class(['on' => $theme === 'dark'])>Dark</a>
        <a href="{{ $link(['theme' => 'light']) }}" @class(['on' => $theme === 'light'])>Light</a>
        <button type="button" onclick="window.print()">Print / save as PDF</button>
        <span>In the print window, set margins to “None” — or send the PDF to the printer.</span>
    </div>

    <div class="stage">
        <article class="board board--{{ $theme }}">
            <header class="b-top">
                <div class="b-brand"><b>{{ mb_strtoupper(\App\Support\SiteText::en('brand_name')) }}</b><span>{{ \App\Support\SiteText::en('brand_sub') }}</span></div>
                <div class="b-brand b-brand--ku" dir="rtl"><b>{{ \App\Support\SiteText::ku('brand_name') }}</b><span>{{ \App\Support\SiteText::ku('brand_sub') }}</span></div>
            </header>

            <figure class="b-photo">
                @if ($photo)<img src="{{ $photo }}" alt="">@endif
                <i class="b-crop b-crop--tl"></i><i class="b-crop b-crop--tr"></i><i class="b-crop b-crop--bl"></i><i class="b-crop b-crop--br"></i>
            </figure>

            @if ($building)
                <p class="b-head"><span>{{ \App\Support\SiteText::en('board_building') }}</span><span dir="rtl">{{ \App\Support\SiteText::ku('board_building') }}</span></p>
            @endif

            <div class="b-block">
                <div class="b-cell b-num"><small>{{ \App\Support\SiteText::en('pj_sheet') }}</small><b>{{ $project->num }}</b></div>
                <div class="b-cell b-name">
                    <small>{{ \App\Support\SiteText::en('pj_project') }} · {{ \App\Support\SiteText::ku('pj_project') }}</small>
                    <b>{{ $project->name }}</b>
                    @if ($nameKu)<b dir="rtl">{{ $nameKu }}</b>@endif
                </div>
                <div class="b-cell b-status">
                    <small>{{ \App\Support\SiteText::en('pj_status') }} · {{ \App\Support\SiteText::ku('pj_status') }}</small>
                    <b><i></i>{{ $project->statusLabel('en') }}</b>
                    <b dir="rtl"><i></i>{{ $project->statusLabel('ku') }}</b>
                </div>
            </div>

            <div class="b-scan">
                <div class="b-qr">{!! $qr !!}</div>
                <div>
                    <p class="b-scan__en">{{ \App\Support\SiteText::en('board_scan') }}</p>
                    <p class="b-scan__ku" dir="rtl">{{ \App\Support\SiteText::ku('board_scan') }}</p>
                    <p class="b-scan__url">{{ $short }}</p>
                    @if ($phone)<p class="b-scan__tel">{{ $phone }}</p>@endif
                </div>
            </div>

            <footer class="b-foot">
                <span>{{ \App\Support\SiteText::en('board_by') }}</span>
                <span dir="rtl">{{ \App\Support\SiteText::ku('board_by') }}</span>
            </footer>
        </article>
    </div>
</body>
</html>
