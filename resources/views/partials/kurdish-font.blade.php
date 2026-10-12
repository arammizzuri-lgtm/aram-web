{{-- The studio's licensed Xoshnûs Kurdish face, if its file has been put in
     public/fonts (see the README there). Declared only when the file exists:
     a declaration pointing at a missing file costs every Kurdish page two
     404s. Until then Speda carries the Kurdish text. --}}
@php
    $kurdishFont = collect(['kurdish.woff2' => 'woff2', 'kurdish.ttf' => 'truetype'])
        ->filter(fn ($format, $file) => is_file(public_path('fonts/'.$file)));
@endphp
@if ($kurdishFont->isNotEmpty())
    <style>
        @font-face {
            font-family: 'XoshnusKurdish';
            src: {!! $kurdishFont->map(fn ($format, $file) => "url('/fonts/{$file}?v=".filemtime(public_path('fonts/'.$file))."') format('{$format}')")->implode(', ') !!};
            font-weight: 100 900;
            font-display: swap;
        }
    </style>
@endif
