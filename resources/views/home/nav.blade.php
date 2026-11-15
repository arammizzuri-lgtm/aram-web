    <!-- Navigation: the studio's name, and a short bar — the Index (the
         whole site as a sheet index, partials/site-index), Contact, the
         visitor's saved projects once there are any, and the three
         languages. The same Index opens from every other page's bar. -->
    <nav class="nav" id="nav" data-tuck aria-label="{{ bival('nav_menu') }}">
        <a href="{{ isset($sheet) ? site_url('/') : '#home' }}" class="nav__logo">
            <span class="logo-name" {!! bitext('brand_name') !!}>{{ bival('brand_name') }}</span>
            <span class="logo-sub" {!! bitext('brand_sub') !!}>{{ bival('brand_sub') }}</span>
        </a>

        <div class="nav__bar">
            @include('partials.index-button', ['class' => 'nav__index'])
            <a href="{{ site_url('/contact') }}" class="nav__link nav__contact" {!! bitext('nav_contact') !!}>{{ bival('nav_contact') }}</a>
            {{-- The visitor's hearted projects (saved.js), once there are any --}}
            <a href="{{ site_url('/saved') }}" class="nav__link nav__saved" data-saved-link hidden>
                @include('partials.icon-heart')
                <span {!! bitext('saved_nav') !!}>{{ bival('saved_nav') }}</span>
                <span class="nav__saved-n" data-saved-count></span>
            </a>
            <span class="nav__sep"></span>
            {{-- The three languages, this one marked: a link to each version (/, /ku, /ar) --}}
            @include('partials.lang-switch', ['class' => 'lsw--bar'])
        </div>
    </nav>
