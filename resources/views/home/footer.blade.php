    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="footer__inner">
            <div class="footer__col footer__col--brand">
                <div class="footer__logo" {!! bitext('footer_logo') !!}>{{ bival('footer_logo') }}</div>
                <div class="footer__tagline" {!! bitext('footer_tagline') !!}>{{ bival('footer_tagline') }}</div>
                @if (filled(bival('arch_membership')))
                    <div class="footer__member"><svg class="footer__shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.4-7 10-4-1.6-7-5.5-7-10V6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.6 12.2l2.3 2.3 4.6-4.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span {!! bitext('arch_membership') !!}>{{ bival('arch_membership') }}</span></div>
                @endif
                {{-- always Kurdish, in both versions of the site, so it is always RTL --}}
                <div class="footer__kurdish" dir="rtl">{{ setting('footer_kurdish') }}</div>
            </div>

            <div class="footer__col footer__col--sun">
                <div class="footer__sun-wrap" id="footerSun"></div>
            </div>

            <div class="footer__col footer__col--links">
                <nav class="footer__social">
                    <a href="{{ site_url('/journal') }}" class="footer__link" {!! bitext('journal_nav') !!}>{{ bival('journal_nav') }}</a>
                    <a href="{{ site_url('/kurdistan-heritage') }}" class="footer__link" {!! bitext('landmarks_nav') !!}>{{ bival('landmarks_nav') }}</a>
                    @if (\App\Models\Partner::anyPublished())
                        <a href="{{ site_url('/partners') }}" class="footer__link" {!! bitext('partners_nav') !!}>{{ bival('partners_nav') }}</a>
                    @endif
                    <a href="{{ site_url('/careers') }}" class="footer__link"
                       data-href-en="{{ site_url('/careers', 'en') }}" data-href-ku="{{ site_url('/careers', 'ku') }}"
                       {!! bitext('careers_link') !!}>{{ bival('careers_link') }}</a>
                    @if (\App\Support\CostEstimate::enabled())
                        <a href="{{ site_url('/estimate') }}" class="footer__link"
                           data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}"
                           {!! bitext('est_nav') !!}>{{ bival('est_nav') }}</a>
                    @endif
                    @foreach (linked_socials() as $s)
                        @php $u = trim($s['url']); $ext = $u !== '' && $u !== '#'; @endphp
                        <a href="{{ $u !== '' ? $u : '#' }}" class="footer__link"@if($ext) target="_blank" rel="noopener"@endif>{{ $s['label'] }}</a>
                    @endforeach
                </nav>
                @php $copy = footer_copy(); if (site_lang() === 'ar' && preg_match('/©\s*[\d–-]+/u', $copy, $years)) { $copy = $years[0]." آرام مزوري للعمارة.
جميع الحقوق محفوظة."; } elseif (site_lang() === 'ku' && preg_match('/©\s*[\d–-]+/u', $copy, $years)) { $copy = $years[0]." تەلارسازیی ئارام مزووری.
هەموو مافەکان پارێزراون."; } @endphp
                <p class="footer__copy">{!! nl2br(e($copy)) !!}</p>
            </div>
        </div>
    </footer>
