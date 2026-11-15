    {{-- ========== COST ESTIMATE ==========
         One line and a button to the estimator (/estimate), just before the
         contact form: the question most people come with, answered first. --}}
    @if (\App\Support\CostEstimate::enabled())
        <section class="est-band" id="estimate">
            <div class="est-band__inner">
                <div class="est-band__text">
                    <p class="section-label" {!! bitext('est_label') !!}>{{ bival('est_label') }}</p>
                    <h2 class="est-band__title" {!! bitext('est_band_title') !!}>{{ bival('est_band_title') }}</h2>
                    <p class="est-band__sub" {!! bitext('est_band_text') !!}>{{ bival('est_band_text') }}</p>
                </div>
                <a class="est-band__btn" href="{{ site_url('/estimate') }}"
                   data-href-en="{{ site_url('/estimate', 'en') }}" data-href-ku="{{ site_url('/estimate', 'ku') }}">
                    <span {!! bitext('est_band_btn') !!}>{{ bival('est_band_btn') }}</span>
                    <span class="est-band__arrow" aria-hidden="true">→</span>
                </a>
            </div>
        </section>
    @endif
