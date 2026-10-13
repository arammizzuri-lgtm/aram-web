{{-- The head of a client room's pages (the PIN, the room). $roomTitle: the
     tab's title. Nothing here invites a search engine or a link preview in:
     the room is one client's. --}}
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $roomTitle }}</title>
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
@php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
<link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
<noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
<link rel="stylesheet" href="{{ asset('room.css') }}?v={{ filemtime(public_path('room.css')) }}">
@include('partials.kurdish-font')
