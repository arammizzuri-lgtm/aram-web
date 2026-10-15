{{-- The studio as Google reads it: what it is, where it is, how to reach it.
     This is what fills a business panel in search and places the office on
     Google Maps. Everything comes from the same settings the page shows, so
     an edit in the admin reaches here too. The coordinates are the office pin
     on the contact map (OFFICE in script.js). --}}
@php
    $phone = fn (string $key, string $default) => preg_replace('/\s+/', ' ', str_replace('(0)', '', setting($key, $default)));
    $studio = [
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        '@id' => url('/').'#studio',
        'name' => 'Aram Mizuri Architecture',
        'alternateName' => 'تەڵارسازیی ئارام مزووری',
        'url' => site_url('/'),
        'logo' => asset('apple-touch-icon.png'),
        'image' => $image,
        'description' => $description,
        'email' => setting('contact_email_new', 'Info@arammizuri.com'),
        'telephone' => $phone('contact_phone', '+964 (0) 782 445 4414'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => \App\Support\SiteText::en('contact_map_addr'),
            'addressLocality' => 'Erbil',
            'addressRegion' => 'Kurdistan Region',
            'addressCountry' => 'IQ',
        ],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 36.23992, 'longitude' => 44.04997],
        'hasMap' => 'https://www.google.com/maps/search/?api=1&query=36.23992,44.04997',
        'areaServed' => 'Kurdistan Region of Iraq',
        'founder' => ['@type' => 'Person', 'name' => 'Aram Mizuri', 'jobTitle' => 'Principal Architect'],
        'sameAs' => collect(socials())->pluck('url')
            ->filter(fn ($u) => preg_match('#^https?://#i', (string) $u))->values()->all(),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($studio, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
