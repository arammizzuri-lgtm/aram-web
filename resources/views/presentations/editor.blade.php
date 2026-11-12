{{--
    The presentation editor: a page of its own behind the admin login
    (PresentationController). Everything on it is drawn by the modules in
    public/js/presentations — no build step; the import map gives each module
    its file's time as a version, so a deploy is never served stale.
--}}
@php
    $v = fn (string $path) => '/'.$path.'?v='.filemtime(public_path($path));
    $imports = [
        'htm-preact' => $v('vendor/presentations/htm-preact.module.js'),
        'pdf-lib' => $v('vendor/presentations/pdf-lib.esm.min.js'),
        'fontkit' => $v('vendor/presentations/fontkit2.es.min.js'),
        'pdfjs' => $v('vendor/presentations/pdf.min.js'),
    ];
    foreach (['model', 'text', 'layout', 'view', 'uploads', 'export', 'panels', 'editor'] as $module) {
        $imports['@pm/'.$module] = $v("js/presentations/{$module}.js");
    }
    $boot['urls']['pdfWorker'] = $v('vendor/presentations/pdf.worker.min.js');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    <link rel="icon" href="/favicon.svg">
    <link rel="stylesheet" href="{{ $v('css/presentations/editor.css') }}">
    <script type="importmap">{!! json_encode(['imports' => $imports], JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body>
    <div id="app"><p class="pm-boot">Opening the editor…</p></div>
    <script id="boot" type="application/json">{!! json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="module" src="{{ $imports['@pm/editor'] }}"></script>
</body>
</html>
