{{-- The bar across the top of the standalone pages (a project, careers, the
     saved list): the studio's name home, the visitor's saved projects once
     there are any (saved.js), a way back, and this page in the other language.
     $otherUrl: this page in the other language. $backHref / $backLabel:
     where "back" goes (default: every project, /projects). $withSaved:
     false on a page that is not for browsing (a client's room). --}}
@php $isKuTop = site_lang() === 'ku'; $otherLang = $isKuTop ? 'en' : 'ku'; @endphp
<header class="pp-top">
    <a class="pp-brand" href="{{ site_url('/') }}">
        <span class="pp-brand__name">{{ bival('brand_name') }}</span>
        <span class="pp-brand__sub">{{ bival('brand_sub') }}</span>
    </a>
    <nav class="pp-top__nav">
        @if ($withSaved ?? true)
            <a class="pp-top__link pp-saved" href="{{ site_url('/saved') }}" data-saved-link hidden aria-label="{{ bival('saved_nav') }}">
                @include('partials.icon-heart')
                <span class="pp-saved__n" data-saved-count></span>
            </a>
        @endif
        <a class="pp-top__link" href="{{ $backHref ?? site_url('/projects') }}">
            <span class="pp-arrow pp-arrow--back" aria-hidden="true">←</span>
            <span>{{ $backLabel ?? bival('pp_all_projects') }}</span>
        </a>
        <a class="pp-lang" href="{{ $otherUrl }}" hreflang="{{ $otherLang }}" lang="{{ $otherLang }}" dir="auto">{{ $isKuTop ? 'English' : 'کوردی' }}</a>
    </nav>
</header>
