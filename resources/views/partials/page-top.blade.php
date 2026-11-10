{{-- The bar across the top of the standalone pages (a project, every project,
     careers, the estimator, the saved list, the heritage pages…): the
     studio's name home, the visitor's saved projects once there are any
     (saved.js), Contact, the three languages and the Index button, which
     opens the whole site as a sheet index (partials/site-index) — the same
     as the homepage's. The bar steps out of the way while the visitor reads
     down the page and comes back when they scroll up (site-index.js).
     $otherUrl: this page in the other language (older pages still pass it;
     the languages come from site_lang_urls now). $langUrls: where the
     language links lead instead of this page in each language ([code =>
     url]) — a page not found sends them home, not to the same missing page
     again. $markHere: false where the index should not say which page this
     is (a page not found). $withMenu: false on a page that is not for
     browsing (a client's room), which keeps a single way back instead:
     $backHref / $backLabel (default: home). $withSaved: false there too. --}}
@php
    $topMenu = ($withMenu ?? true) ? \App\Support\SiteMenu::items($markHere ?? true) : [];
@endphp
<link rel="stylesheet" href="{{ asset('site-index.css') }}?v={{ filemtime(public_path('site-index.css')) }}">
<header class="pp-top" data-tuck>
    <a class="pp-brand" href="{{ site_url('/') }}">
        <span class="pp-brand__name">{{ bival('brand_name') }}</span>
        <span class="pp-brand__sub">{{ bival('brand_sub') }}</span>
    </a>
    <nav class="pp-top__nav" aria-label="{{ bival('nav_menu') }}">
        @if ($withSaved ?? true)
            <a class="pp-top__link pp-saved" href="{{ site_url('/saved') }}" data-saved-link hidden aria-label="{{ bival('saved_nav') }}">
                @include('partials.icon-heart')
                <span class="pp-saved__n" data-saved-count></span>
            </a>
        @endif
        @unless ($topMenu)
            <a class="pp-top__link" href="{{ $backHref ?? site_url('/') }}">
                <span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span>
                <span>{{ $backLabel ?? bival('careers_home') }}</span>
            </a>
        @endunless
        @if ($topMenu)
            <a class="bar-contact" href="{{ site_url('/') }}#contact">{{ bival('nav_contact') }}</a>
        @endif
        @include('partials.lang-switch', ['urls' => $langUrls ?? null, 'class' => 'lsw--bar'])
        @if ($topMenu)
            @include('partials.index-button')
        @endif
    </nav>
</header>
@if ($topMenu)
    @include('partials.site-index', ['sheets' => $topMenu, 'markHere' => $markHere ?? true, 'langUrls' => $langUrls ?? null])
@endif
<script src="{{ asset('site-index.js') }}?v={{ filemtime(public_path('site-index.js')) }}" defer></script>
