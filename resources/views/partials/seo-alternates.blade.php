{{-- This page's address, and its translations. The canonical is the page in
     the language being shown; the alternates tell search engines the
     English, Kurdish and Arabic versions are one page in three languages,
     not duplicates, and that English is the one to show everyone else. --}}
<link rel="canonical" href="{{ site_url($path) }}">
@foreach (array_keys(site_langs()) as $code)
<link rel="alternate" hreflang="{{ $code }}" href="{{ site_url($path, $code) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ site_url($path, 'en') }}">
