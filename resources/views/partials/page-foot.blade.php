{{-- The footer of the standalone pages (a project, careers). --}}
<footer class="pp-foot">
    <div class="pp-foot__brand">
        <a href="{{ site_url('/') }}">{{ bival('footer_logo') }}</a>
        <span>{{ bival('footer_tagline') }}</span>
        @if (filled(bival('arch_membership')))
            <span class="pp-foot__member"><svg class="pp-foot__shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.4-7 10-4-1.6-7-5.5-7-10V6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.6 12.2l2.3 2.3 4.6-4.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ bival('arch_membership') }}</span>
        @endif
    </div>
    @php
        $footEmail = setting('contact_email_new', 'Info@arammizuri.com');
        $footPhone = setting('contact_phone', '+964 (0) 782 445 4414');
    @endphp
    <div class="pp-foot__links">
        <a href="mailto:{{ $footEmail }}">{{ $footEmail }}</a>
        <a href="tel:{{ preg_replace('/[^\d+]/', '', str_replace('(0)', '', $footPhone)) }}" dir="ltr">{{ $footPhone }}</a>
        <a href="{{ site_url(\App\Support\Architect::PATH) }}">{{ bival('arch_name') }}</a>
        <a href="{{ site_url('/journal') }}">{{ bival('journal_nav') }}</a>
        <a href="{{ site_url('/kurdistan-heritage') }}">{{ bival('landmarks_nav') }}</a>
        @if (\App\Models\Partner::anyPublished())
            <a href="{{ site_url('/partners') }}">{{ bival('partners_nav') }}</a>
        @endif
        <a href="{{ site_url('/careers') }}">{{ bival('careers_link') }}</a>
        @if (\App\Support\CostEstimate::enabled())
            <a href="{{ site_url('/estimate') }}">{{ bival('est_nav') }}</a>
        @endif
        @foreach (linked_socials() as $s)
            @if (preg_match('#^https?://#i', trim($s['url'])))
                <a href="{{ trim($s['url']) }}" target="_blank" rel="noopener">{{ $s['label'] }}</a>
            @endif
        @endforeach
    </div>
</footer>
