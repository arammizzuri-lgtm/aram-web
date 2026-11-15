    <!-- ========== CONTACT ========== -->
    <section class="contact" id="contact">
        <div class="contact__bg" aria-hidden="true">
            <span class="contact__aurora"></span>
            <span class="contact__grid"></span>
        </div>

        <div class="contact__inner">

            <div class="contact__info">
                @unless (isset($sheet))
                <p class="section-label" {!! bitext('contact_label') !!}>{{ bival('contact_label') }}</p>
                <h2 class="contact__title" {!! bitext('contact_title') !!}>{!! bival('contact_title') !!}</h2>
                @include('home.sheet-ref', ['key' => 'contact'])
                @endunless

                <div class="contact__methods">
                    {{-- WhatsApp first — it is how most clients here would rather
                         start. Hidden until a number is set in Site Content. --}}
                    @if ($waUrl = whatsapp_url(bival('wa_text')))
                        <div>
                            <a class="cmethod cmethod--link cmethod--wa" href="{{ $waUrl }}" target="_blank" rel="noopener" data-wa="contact"
                               data-href-en="{{ whatsapp_url(\App\Support\SiteText::en('wa_text')) }}"
                               data-href-ku="{{ whatsapp_url(\App\Support\SiteText::second('wa_text')) }}">
                                <span class="cmethod__icon">@include('partials.icon-whatsapp')</span>
                                <span class="cmethod__text">
                                    <span class="cmethod__label" {!! bitext('wa_label') !!}>{{ bival('wa_label') }}</span>
                                    <span class="cmethod__value"><bdi dir="ltr">{{ setting('contact_whatsapp') }}</bdi></span>
                                </span>
                                <span class="cmethod__hint"><span {!! bitext('wa_cta') !!}>{{ bival('wa_cta') }}</span> ↗&#xFE0E;</span>
                            </a>
                        </div>
                    @endif
                    {{-- one email, two lines — the address itself lives on the map card below --}}
                    <div class="cmethod cmethod--copy" data-copy="{{ setting('contact_email_new', 'Info@arammizuri.com') }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_email_new', 'Info@arammizuri.com') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_email_lbl') !!}>{{ bival('contact_email_lbl') }}</span>
                            <span class="cmethod__value"><bdi dir="ltr">{{ setting('contact_email_new', 'Info@arammizuri.com') }}</bdi></span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </div>
                    <div class="cmethod cmethod--copy" data-copy="{{ str_replace('(0) ', '', setting('contact_phone', '+964 (0) 782 445 4414')) }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_phone', '+964 (0) 782 445 4414') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_phone_lbl') !!}>{{ bival('contact_phone_lbl') }}</span>
                            <span class="cmethod__value"><bdi dir="ltr">{{ setting('contact_phone', '+964 (0) 782 445 4414') }}</bdi></span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </div>
                    <div class="cmethod cmethod--copy" data-copy="{{ str_replace('(0) ', '', setting('contact_phone2', '+964 (0) 750 408 6367')) }}" tabindex="0" role="button" aria-label="Copy {{ setting('contact_phone2', '+964 (0) 750 408 6367') }}">
                        <span class="cmethod__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                        </span>
                        <div class="cmethod__text">
                            <span class="cmethod__label" {!! bitext('contact_phone2_lbl') !!}>{{ bival('contact_phone2_lbl') }}</span>
                            <span class="cmethod__value"><bdi dir="ltr">{{ setting('contact_phone2', '+964 (0) 750 408 6367') }}</bdi></span>
                        </div>
                        <span class="cmethod__hint" {!! bitext('contact_copy') !!}>{{ bival('contact_copy') }}</span>
                    </div>
                </div>

                {{-- Office locator — dark map, pulsing gold pin, and the
                     visiting policy. Initialised in script.js (initOfficeMap). --}}
                <div class="contact__map">
                    <div class="contact__map-canvas" id="officeMapEl" role="link" tabindex="0" aria-label="Open office location in Google Maps — Italian Village 2, Erbil"@if ($gmaps = google_profile()) data-maps="{{ $gmaps['url'] }}"@endif></div>
                    <span class="contact__map-open" aria-hidden="true">
                        <span {!! bitext('contact_open_maps') !!}>{{ bival('contact_open_maps') }}</span> ↗&#xFE0E;
                    </span>
                    <div class="contact__map-foot">
                        <p class="contact__map-addr" {!! bitext('contact_map_addr') !!}>{{ bival('contact_map_addr') }}</p>
                        <p class="contact__map-note">
                            <span class="contact__map-dot" aria-hidden="true"></span>
                            <span {!! bitext('contact_visits') !!}>{{ bival('contact_visits') }}</span>
                            <a class="contact__map-book" href="{{ site_url('/visit') }}" data-href-en="{{ site_url('/visit', 'en') }}" data-href-ku="{{ site_url('/visit', 'ku') }}"><span {!! bitext('visit_link') !!}>{{ bival('visit_link') }}</span> <span aria-hidden="true">→</span></a>
                        </p>
                    </div>
                </div>
            </div>

            {{-- The enquiry form. Name and one way to reply (e-mail or phone) are
                 all it asks for; the project details fold away so a quick
                 question stays quick. "About" is set when the visitor comes
                 from a project — its page (?project=slug) or the overlay —
                 so the studio knows what they were looking at. --}}
            @php
                $choices = fn (array $options) => collect($options)->map(fn ($o, $key) =>
                    '<option value="'.e($key).'" data-en="'.e($o['en']).'" data-ku="'.e(rtl_slot($o['en'], $o['ku'], $o['ar'] ?? null)).'">'.e(bi($o['en'], $o['ku'], $o['ar'] ?? null)).'</option>'
                )->implode('');
                $choose = '<option value="" '.bitext('form_choose').'>'.e(bival('form_choose')).'</option>';
            @endphp
            <form class="contact__form" id="contactForm" novalidate>
                <div class="form-about" id="formAbout" hidden>
                    <span class="form-about__label" {!! bitext('form_about') !!}>{{ bival('form_about') }}</span>
                    <span class="form-about__name" id="formAboutName" dir="auto"></span>
                    <button type="button" class="form-about__clear" id="formAboutClear" aria-label="Remove">✕</button>
                </div>
                <input type="hidden" name="about" id="cf-about">
                {{-- Their saved projects go with the enquiry (saved.js) — shown, so
                     nothing is sent they cannot see, and ✕ to leave them off. --}}
                <div class="form-about form-saved" id="formSaved" hidden>
                    <span class="form-about__label">@include('partials.icon-heart')<span {!! bitext('form_saved') !!}>{{ bival('form_saved') }}</span></span>
                    <span class="form-about__name" id="formSavedNames" dir="auto"></span>
                    <button type="button" class="form-about__clear" id="formSavedClear" aria-label="Remove">✕</button>
                </div>

                <div class="form-grid">
                    <div class="form-field form-field--full">
                        <input type="text" id="cf-name" name="name" placeholder=" " required autocomplete="name">
                        <label for="cf-name" {!! bitext('form_name') !!}>{{ bival('form_name') }}</label>
                    </div>
                    <div class="form-field">
                        <input type="tel" id="cf-phone" name="phone" placeholder=" " autocomplete="tel" inputmode="tel" dir="ltr">
                        <label for="cf-phone" {!! bitext('form_phone') !!}>{{ bival('form_phone') }}</label>
                    </div>
                    <div class="form-field">
                        <input type="email" id="cf-email" name="email" placeholder=" " autocomplete="email" dir="ltr">
                        <label for="cf-email" {!! bitext('form_email') !!}>{{ bival('form_email') }}</label>
                    </div>
                    <p class="form-note form-field--full" id="cfReach" {!! bitext('form_reach') !!}>{{ bival('form_reach') }}</p>
                    <div class="form-field form-field--select form-field--full">
                        <select id="cf-type" name="type">{!! $choose !!}{!! $choices(\App\Support\Enquiry::types()) !!}</select>
                        <label for="cf-type" {!! bitext('form_project') !!}>{{ bival('form_project') }}</label>
                    </div>
                </div>

                <details class="form-more" id="formMore">
                    <summary class="form-more__toggle"><span {!! bitext('form_more') !!}>{{ bival('form_more') }}</span></summary>
                    <div class="form-grid">
                        <div class="form-field">
                            <input type="text" id="cf-city" name="city" placeholder=" " autocomplete="address-level2">
                            <label for="cf-city" {!! bitext('form_city') !!}>{{ bival('form_city') }}</label>
                        </div>
                        <div class="form-field">
                            <input type="number" id="cf-plot" name="plot_area" placeholder=" " min="1" step="1" inputmode="numeric" dir="ltr">
                            <label for="cf-plot" {!! bitext('form_plot') !!}>{{ bival('form_plot') }}</label>
                        </div>
                        <div class="form-field form-field--select">
                            <select id="cf-budget" name="budget">{!! $choose !!}{!! $choices(\App\Support\Enquiry::budgets()) !!}</select>
                            <label for="cf-budget" {!! bitext('form_budget') !!}>{{ bival('form_budget') }}</label>
                        </div>
                        <div class="form-field form-field--select">
                            <select id="cf-timeline" name="timeline">{!! $choose !!}{!! $choices(\App\Support\Enquiry::timelines()) !!}</select>
                            <label for="cf-timeline" {!! bitext('form_timeline') !!}>{{ bival('form_timeline') }}</label>
                        </div>
                    </div>
                </details>

                <div class="form-field form-field--grow">
                    <textarea id="cf-message" name="message" placeholder=" " rows="4"></textarea>
                    <label for="cf-message" {!! bitext('form_message') !!}>{{ bival('form_message') }}</label>
                </div>
                {{-- A sketch to go with it, drawn here (sketch.js) — or on the
                     projects page's blank sheet, arriving as ?sketch=… --}}
                <input type="hidden" name="sketch" id="cf-sketch">
                <div class="form-sketch" id="formSketch" hidden>
                    <img alt="" width="120" height="90">
                    <span class="form-about__label" {!! bitext('sk_attached') !!}>{{ bival('sk_attached') }}</span>
                    <button type="button" class="form-about__clear" id="formSketchClear" aria-label="Remove">✕</button>
                </div>
                <button type="button" class="form-sketch-open" data-sketch-open="attach">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20l4.2-1 10-10a2.1 2.1 0 0 0-3-3l-10 10z"/><path d="M13.5 7.5l3 3"/></svg>
                    <span {!! bitext('sk_open') !!}>{{ bival('sk_open') }}</span>
                </button>
                {{-- Spam trap: off-screen and out of the tab order, so only bots fill it. --}}
                <div class="form-hp" aria-hidden="true">
                    <label for="cf-website">Website</label>
                    <input type="text" id="cf-website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <button type="submit" class="form-submit">
                    <span class="form-submit__label" {!! bitext('form_send') !!}>{{ bival('form_send') }}</span>
                    <span class="form-submit__arrow">→</span>
                </button>
                <p class="form-success" id="formSuccess" hidden {!! bitext('form_success') !!}>
                    {{ bival('form_success') }}
                </p>
            </form>
        </div>
    </section>
