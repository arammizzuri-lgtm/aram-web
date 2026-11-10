{{-- The cost estimate on one sheet of paper (EstimateController::sheet): a
     title block — the studio, the date, who it was prepared for — the
     visitor's answers, the figures as the estimator gives them
     (partials/estimate-result), the small print and the studio's details.
     Light, for paper; laid out for A4. The browser saves it as a PDF, which
     sets Kurdish and Arabic properly. With ?print=1 the print dialog opens
     once the fonts are in. Not for search engines. --}}
@php
    $lang = site_lang();
    $rtl = site_rtl();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $num = fn ($n) => number_format((int) $n);
    $finish = $estimate['finish'][$lang] ?? $estimate['finish']['en'];
    $answers = array_filter([
        bival('est_plot') => $inputs['plot'] ? $num($inputs['plot']).' m²' : null,
        bival('est_floors') => (string) $inputs['floors'],
        bival('est_built') => $num($estimate['built']).' m²',
        bival('est_basement') => $inputs['basement'] ? $num($estimate['basement_area']).' m²' : '—',
        bival('est_bedrooms') => (string) $inputs['bedrooms'],
        bival('est_bathrooms') => (string) $inputs['bathrooms'],
        bival('est_finish') => $finish,
        bival('est_pool') => $inputs['pool'] ? '✓' : '—',
    ], fn ($v) => $v !== null);
    $phones = array_values(array_filter([setting('contact_phone', '+964 (0) 782 445 4414'), setting('contact_phone2', '+964 (0) 750 408 6367')]));
    $email = setting('contact_email_new', 'Info@arammizuri.com');
    $site = preg_replace('#^https?://(www\.)?#', '', rtrim(url('/'), '/'));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ bival('est_label') }}{{ $for !== '' ? ' — '.$for : '' }} — {{ $brand }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @include('partials.kurdish-font')
    <style>
        @font-face { font-family: 'Speda'; src: url('/fonts/speda.ttf') format('truetype'); font-weight: 400 700; font-display: swap; }
        :root { --gold: #b8860b; --ink: #141414; --muted: #5f5f5f; --line: #dedad2; --font: 'Inter', 'Speda', sans-serif; }
        [dir="rtl"] { --font: 'Speda', 'XoshnusKurdish', 'Inter', sans-serif; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { background: #e9e6e0; }
        body { font-family: var(--font); color: var(--ink); -webkit-font-smoothing: antialiased; }
        bdi { unicode-bidi: isolate; }

        /* on screen: the sheet on a desk, a bar to save it */
        .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px 16px; padding: 14px 16px; background: #141414; color: #fff; }
        .bar__save { display: inline-flex; align-items: center; gap: 8px; height: 42px; padding: 0 22px; border: 0; border-radius: 100px; background: #F5C518; color: #0a0a0a; font: 500 15px/1 var(--font); cursor: pointer; }
        .bar__back { color: rgba(255,255,255,.8); font-size: 14px; text-decoration: none; }
        .bar__back:hover { color: #F5C518; }
        .bar__hint { width: 100%; text-align: center; font-size: 12.5px; color: rgba(255,255,255,.6); }
        .paper { width: min(210mm, 100% - 24px); min-height: 297mm; margin: 24px auto 40px; padding: 18mm 16mm 14mm; background: #fff; box-shadow: 0 10px 40px rgba(0,0,0,.18); display: flex; flex-direction: column; }

        /* the title block */
        .tb { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 18px; padding-bottom: 14px; border-bottom: 2px solid var(--ink); }
        .tb__brand { font-size: 13px; font-weight: 600; letter-spacing: .26em; text-transform: uppercase; }
        [dir="rtl"] .tb__brand { letter-spacing: 0; font-size: 16px; }
        .tb__title { margin-top: 10px; font-size: 30px; font-weight: 300; line-height: 1.2; }
        [dir="rtl"] .tb__title { font-weight: 400; font-size: 28px; line-height: 1.5; }
        .tb__for { margin-top: 6px; font-size: 15px; color: var(--gold); }
        .tb__meta { display: grid; align-content: start; gap: 4px; text-align: end; font-size: 12px; color: var(--muted); }
        .tb__meta b { display: block; font-weight: 500; font-size: 13px; color: var(--ink); }
        [dir="rtl"] .tb__meta { font-size: 13.5px; }

        h2 { margin: 26px 0 10px; font-size: 11px; font-weight: 600; letter-spacing: .2em; text-transform: uppercase; color: var(--gold); }
        [dir="rtl"] h2 { letter-spacing: 0; font-size: 14px; }

        .answers { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid var(--line); }
        .answers div { padding: 10px 10px 10px 0; border-bottom: 1px solid var(--line); }
        [dir="rtl"] .answers div { padding: 10px 0 10px 10px; }
        .answers dt { font-size: 11px; color: var(--muted); }
        [dir="rtl"] .answers dt { font-size: 13px; }
        .answers dd { margin-top: 3px; font-size: 15px; font-weight: 500; }

        /* the figures, as the estimator draws them */
        .result .es-figure { padding: 14px 0; border-bottom: 1px solid var(--line); }
        .result .es-figure__label { font-size: 12px; color: var(--muted); }
        [dir="rtl"] .result .es-figure__label { font-size: 14px; }
        .result .es-figure__usd { margin-top: 4px; font-size: 30px; font-weight: 300; }
        .result .es-figure__iqd { margin-top: 2px; font-size: 13px; color: var(--muted); }
        .result .es-figure--fee .es-figure__usd { font-size: 22px; }
        .result .es-figure__note { margin-top: 4px; font-size: 12px; color: var(--muted); }
        .result .es-lines { list-style: none; padding: 6px 0; border-bottom: 1px solid var(--line); }
        .result .es-lines li { display: flex; justify-content: space-between; gap: 16px; padding: 5px 0; font-size: 13.5px; }
        [dir="rtl"] .result .es-lines li { font-size: 15px; }
        .result .es-note { margin-top: 14px; font-size: 12px; line-height: 1.6; color: var(--muted); }
        [dir="rtl"] .result .es-note { font-size: 13.5px; line-height: 1.8; }

        .foot { margin-top: auto; padding-top: 22px; }
        .contact { display: flex; flex-wrap: wrap; gap: 6px 26px; padding-top: 12px; border-top: 2px solid var(--ink); font-size: 13px; }
        .contact span { color: var(--muted); }

        @page { size: A4; margin: 0; }
        @media print {
            html { background: #fff; }
            .bar { display: none; }
            .paper { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; }
        }
        @media (max-width: 640px) {
            .paper { padding: 22px 18px; min-height: 0; }
            .tb { grid-template-columns: minmax(0, 1fr); }
            .tb__meta { text-align: start; }
            .answers { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <div class="bar">
        <button class="bar__save" type="button" onclick="window.print()">{{ bival('est_sheet_save') }} <span aria-hidden="true">↓</span></button>
        <a class="bar__back" href="{{ $back }}">{{ bival('est_sheet_back') }}</a>
        <p class="bar__hint">{{ bival('est_sheet_hint') }}</p>
    </div>

    <main class="paper">
        <header class="tb">
            <div>
                <p class="tb__brand">{{ $brand }}</p>
                <h1 class="tb__title">{{ bival('est_title') }}</h1>
                @if ($for !== '')<p class="tb__for">{{ str_replace('{name}', $for, bival('est_sheet_for')) }}</p>@endif
            </div>
            <p class="tb__meta">
                <span>{{ bival('est_sheet_date') }}<b>{{ site_date(now()) }}</b></span>
            </p>
        </header>

        <h2>{{ bival('est_sheet_answers') }}</h2>
        <dl class="answers">
            @foreach ($answers as $label => $value)
                <div><dt>{{ $label }}</dt><dd><bdi>{{ $value }}</bdi></dd></div>
            @endforeach
        </dl>

        <h2>{{ bival('est_result') }}</h2>
        <div class="result">@include('partials.estimate-result', ['estimate' => $estimate])</div>

        <footer class="foot">
            <h2>{{ bival('est_sheet_talk') }}</h2>
            <p class="contact">
                @foreach ($phones as $phone)<bdi dir="ltr">{{ $phone }}</bdi>@endforeach
                <bdi dir="ltr">{{ $email }}</bdi>
                <bdi dir="ltr">{{ $site }}</bdi>
            </p>
        </footer>
    </main>

    @if ($print)
        {{-- the print dialog, once the fonts are in, so the PDF has them --}}
        <script>
            window.addEventListener('load', function () {
                (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 300); });
            });
        </script>
    @endif
</body>
</html>
