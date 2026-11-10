{{-- The bar across the top of the standalone pages (a project, every project,
     careers, the estimator, the saved list): the studio's name home, the
     same menu as the homepage's — its sections, every project and the
     estimator — the visitor's saved projects once there are any (saved.js),
     and this page in the other language. On a computer the menu runs across
     the bar; on a tablet or phone it folds into a Menu button (site-menu.js).
     $otherUrl: this page in the other language. $langUrls: where the language
     links lead instead of this page in each language ([code => url]) — a page
     not found sends them home, not to the same missing page again.
     $markHere: false where the
     menu should not say which page this is (a page not found). $withMenu: false on a page
     that is not for browsing (a client's room), which keeps a single way
     back instead: $backHref / $backLabel (default: home). $withSaved: false
     there too. --}}
@php
    // this page in the two other languages (site_lang_urls); $otherUrl, which
    // pages still pass, was the one other language when there were two
    $langLinks = collect($langUrls ?? site_lang_urls())->except(site_lang());
    $topMenu = ($withMenu ?? true) ? \App\Support\SiteMenu::items($markHere ?? true) : [];
    $topWa = $topMenu ? whatsapp_url(bival('wa_text')) : null;
@endphp
<header class="pp-top">
    <a class="pp-brand" href="{{ site_url('/') }}">
        <span class="pp-brand__name">{{ bival('brand_name') }}</span>
        <span class="pp-brand__sub">{{ bival('brand_sub') }}</span>
    </a>
    <nav class="pp-top__nav" aria-label="{{ bival('nav_menu') }}">
        @if ($topMenu)
            <span class="pp-menu">
                @foreach ($topMenu as $item)
                    @continue($item['only_in_sheet'])
                    <a class="pp-menu__link" href="{{ $item['href'] }}"@if ($item['here']) aria-current="page"@endif>{{ $item['label'] }}</a>
                @endforeach
            </span>
        @endif
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
        @foreach ($langLinks as $code => $href)
            <a class="pp-lang" href="{{ $href }}" hreflang="{{ $code }}" lang="{{ $code === 'ku' ? 'ckb' : $code }}" dir="auto">{{ site_langs()[$code] }}</a>
        @endforeach
        @if ($topMenu)
            <button class="pp-top__link pp-menu-btn" type="button" aria-expanded="false" aria-controls="ppSheet" aria-label="{{ bival('nav_menu') }}" data-menu-open>
                <span class="pp-menu-btn__icon" aria-hidden="true"><i></i><i></i></span>
                <span>{{ bival('nav_menu') }}</span>
            </button>
        @endif
    </nav>
</header>
@if ($topMenu)
    {{-- The menu, on a tablet or phone: the site as a list of numbered sheets --}}
    <div class="pp-sheet" id="ppSheet" hidden>
        <nav class="pp-sheet__inner" aria-label="{{ bival('nav_menu') }}">
            <ol class="pp-sheet__list">
                @foreach ($topMenu as $i => $item)
                    <li>
                        <a class="pp-sheet__link" href="{{ $item['href'] }}"@if ($item['here']) aria-current="page"@endif>
                            <span class="pp-sheet__num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="pp-sheet__label">{{ $item['label'] }}</span>
                            <span class="pp-arrow" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            @if ($topWa)
                <a class="pp-btn pp-btn--wa pp-sheet__wa" href="{{ $topWa }}" target="_blank" rel="noopener" data-wa="menu">
                    @include('partials.icon-whatsapp')
                    <span>{{ bival('wa_cta') }}</span>
                </a>
            @endif
        </nav>
    </div>
    <script src="{{ asset('site-menu.js') }}?v={{ filemtime(public_path('site-menu.js')) }}" defer></script>
@endif
