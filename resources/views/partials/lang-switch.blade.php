{{-- The three languages side by side, the page's own marked — EN · کوردی ·
     العربية — so a visitor sees which one they are reading as well as where
     the others are (the old bar showed only the other two). Styled in
     site-index.css. $urls: [code => url], default this page in each language
     (site_lang_urls); a page not found passes the homes instead. --}}
@php
    $urls = $urls ?? site_lang_urls();
    $short = ['en' => 'EN', 'ku' => 'کوردی', 'ar' => 'العربية'];
@endphp
<span class="lsw{{ isset($class) ? ' '.$class : '' }}" role="group" aria-label="{{ bival('nav_language') }}">
    @foreach (array_keys(site_langs()) as $code)
        @if ($code === site_lang())
            <span class="lsw__item is-on" aria-current="true" lang="{{ $code === 'ku' ? 'ckb' : $code }}"@if ($code === 'en') aria-label="English"@endif>{{ $short[$code] }}</span>
        @elseif (isset($urls[$code]))
            <a class="lsw__item" href="{{ $urls[$code] }}" hreflang="{{ $code }}" lang="{{ $code === 'ku' ? 'ckb' : $code }}"@if ($code === 'en') aria-label="English"@endif>{{ $short[$code] }}</a>
        @endif
    @endforeach
</span>
