{{-- The footer of the standalone pages (a project, careers). --}}
<footer class="pp-foot">
    <div class="pp-foot__brand">
        <a href="{{ site_url('/') }}">{{ bival('footer_logo') }}</a>
        <span>{{ bival('footer_tagline') }}</span>
    </div>
    @php
        $footEmail = setting('contact_email_new', 'Info@arammizzuri.com');
        $footPhone = setting('contact_phone', '+964 (0) 782 445 4414');
    @endphp
    <div class="pp-foot__links">
        <a href="mailto:{{ $footEmail }}">{{ $footEmail }}</a>
        <a href="tel:{{ preg_replace('/[^\d+]/', '', str_replace('(0)', '', $footPhone)) }}" dir="ltr">{{ $footPhone }}</a>
        <a href="{{ site_url('/careers') }}">{{ bival('careers_link') }}</a>
        @foreach (socials() as $s)
            @if (preg_match('#^https?://#i', trim($s['url'])))
                <a href="{{ trim($s['url']) }}" target="_blank" rel="noopener">{{ $s['label'] }}</a>
            @endif
        @endforeach
    </div>
</footer>
