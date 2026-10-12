{{-- This page's address, and its translation. The canonical is the page in
     the language being shown; the alternates tell search engines the English
     and Kurdish versions are one page in two languages, not duplicates, and
     that English is the one to show everyone else. --}}
<link rel="canonical" href="{{ site_url($path) }}">
<link rel="alternate" hreflang="en" href="{{ site_url($path, 'en') }}">
<link rel="alternate" hreflang="ku" href="{{ site_url($path, 'ku') }}">
<link rel="alternate" hreflang="x-default" href="{{ site_url($path, 'en') }}">
