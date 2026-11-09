{{-- The head shared by the standalone pages added since the journal (the
     journal, the partners and the heritage map): title, description, the three language
     versions, the link preview, fonts, icons and project.css — plus the
     page's own stylesheet ($css) and structured data ($schema).
     $title, $description, $path, $image (optional), $css (optional),
     $schema (optional array), $type (og:type, default "website"). --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
@include('partials.seo-alternates', ['path' => $path])
<meta property="og:type" content="{{ $type ?? 'website' }}">
<meta property="og:site_name" content="Aram Mizuri Architecture">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ site_url($path) }}">
<meta property="og:image" content="{{ $image ?? asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg')) }}">
<meta name="twitter:card" content="summary_large_image">
@if (! empty($schema))
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
<link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
<noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
@isset($css)
    <link rel="stylesheet" href="{{ asset($css) }}?v={{ filemtime(public_path($css)) }}">
@endisset
@include('partials.kurdish-font')
