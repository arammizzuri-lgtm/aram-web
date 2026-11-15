    <!-- ========== PROJECT OVERLAY ========== -->
    <div class="proj-overlay" id="projOverlay" aria-hidden="true" role="dialog" aria-label="Project details">

      <!-- Location map — a separate window beside the main detail window -->
      <aside class="od-sidemap" id="overlayMap" aria-hidden="true">
          <div class="od-sidemap__canvas" id="overlayMapCanvas"></div>
          <div class="od-sidemap__bar">
              <span class="od-sidemap__dot"></span>
              <span class="od-sidemap__city" id="overlayMapCity"></span>
          </div>
      </aside>

      <div class="od-modal">

        <!-- Fixed top bar -->
        <div class="proj-overlay__topbar">
            <button class="proj-overlay__back" id="projOverlayClose" aria-label="Back to projects" {!! bitext('overlay_back') !!}>{{ bival('overlay_back') }}</button>
            <div class="proj-overlay__proj-nav">
                <button class="proj-overlay__proj-btn" id="overlayProjPrev" aria-label="Previous project">←</button>
                <span class="proj-overlay__counter" id="overlayCounter">1 / 10</span>
                <button class="proj-overlay__proj-btn" id="overlayProjNext" aria-label="Next project">→</button>
            </div>
        </div>

        <!-- Full-bleed body: image fills all, panel floats on top -->
        <div class="proj-overlay__body" id="overlayBody">

            <!-- Image — full image floats over a blurred ambient copy of itself -->
            <div class="od-bg">
                <div class="od-bg__ambient od-ambient"></div>
                <img class="od-bg__img" id="overlayHeroImg" src="" alt="">
                <div class="od-bg__veil"></div>

                <!-- Full-resolution view + download controls -->
                <div class="od-fullres">
                    <a class="od-fullres__view" id="overlayFullRes" href="#" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 3H5a2 2 0 0 0-2 2v4M15 3h4a2 2 0 0 1 2 2v4M21 15v4a2 2 0 0 1-2 2h-4M3 15v4a2 2 0 0 0 2 2h4"/>
                        </svg>
                        <span {!! bitext('overlay_fullres') !!}>{{ bival('overlay_fullres') }}</span>
                    </a>
                    <a class="od-fullres__dl" id="overlayDownload" href="#" download title="Download image" aria-label="Download image">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- HUD bracket decorations (image side) -->
            <div class="od-hud" aria-hidden="true">
                <div class="od-hud__c od-hud__c--tl"></div>
                <div class="od-hud__c od-hud__c--bl"></div>
            </div>

            <!-- Image nav -->
            <button class="od-img-btn od-img-btn--prev" id="overlayImgPrev" aria-label="Previous image">←</button>
            <button class="od-img-btn od-img-btn--next" id="overlayImgNext" aria-label="Next image">→</button>

            <!-- Thumbstrip -->
            <div class="od-thumbs" id="overlayThumbs"></div>

            <!-- Floating frosted-glass data panel (ambient-tinted) -->
            <div class="od-panel">
                <div class="od-panel__ambient od-ambient"></div>
                <div class="od-panel__scrim"></div>
                <div class="od-panel__scroll">

                    <div class="od-status-row">
                        <span class="od-dot" id="overlayDot"></span>
                        <span class="od-status-text" id="overlayStatusBadge"></span>
                    </div>

                    <div class="od-num" id="overlayNum"></div>
                    <h2 class="od-name" id="overlayName"></h2>

                    <div class="od-rule"></div>

                    <dl class="od-specs">
                        <div class="od-spec">
                            <dt {!! bitext('overlay_location') !!}>{{ bival('overlay_location') }}</dt>
                            <dd id="overlayLocation"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_year') !!}>{{ bival('overlay_year') }}</dt>
                            <dd id="overlayYear"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_typology') !!}>{{ bival('overlay_typology') }}</dt>
                            <dd id="overlayTypology"></dd>
                        </div>
                        <div class="od-spec">
                            <dt {!! bitext('overlay_plot') !!}>{{ bival('overlay_plot') }}</dt>
                            <dd id="overlayArea"></dd>
                        </div>
                    </dl>

                    <div class="od-tags" id="overlayTags"></div>

                    <p class="od-desc" id="overlayDesc"></p>

                    {{-- What the project's own page has beyond these photos: links
                         straight to it, shown only for the parts this project has
                         (set in populateOverlay from its "extras"). --}}
                    <div class="od-extras" id="overlayExtras" hidden>
                        <a class="od-extra" data-extra="film" data-anchor="film" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                            <span {!! bitext('pp_film') !!}>{{ bival('pp_film') }}</span>
                        </a>
                        <a class="od-extra" data-extra="compare" data-anchor="render-vs-built" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M12 3v18"/></svg>
                            <span {!! bitext('pp_compare') !!}>{{ bival('pp_compare') }}</span>
                        </a>
                        <a class="od-extra" data-extra="progress" data-anchor="progress" href="#" hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16M6 20V10l6-5 6 5v10"/><path d="M10 20v-5h4v5"/></svg>
                            <span {!! bitext('pp_progress') !!}>{{ bival('pp_progress') }}</span>
                        </a>
                    </div>

                    {{-- The two ways to act on what was just seen. Both carry the
                         open project: the form gets it as "About", the WhatsApp
                         message names it (set in populateOverlay). --}}
                    <div class="od-cta">
                        <a class="od-cta__btn od-cta__btn--primary" id="overlayEnquire" href="{{ site_url('/contact') }}">
                            <span {!! bitext('pp_cta_button') !!}>{{ bival('pp_cta_button') }}</span>
                            <span class="od-cta__arrow" aria-hidden="true">→</span>
                        </a>
                        @if (whatsapp_url())
                            <a class="od-cta__btn od-cta__btn--wa" id="overlayWa" href="{{ whatsapp_url() }}" target="_blank" rel="noopener" data-wa="overlay">
                                @include('partials.icon-whatsapp')
                                <span {!! bitext('wa_label') !!}>{{ bival('wa_label') }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- Keep this one (saved.js), or pass it on (engage.js). Pointed
                         at the open project in populateOverlay; hidden for one
                         without a page of its own to link to. --}}
                    <div class="od-share" id="overlayShareRow" hidden>
                        <button class="od-share__btn od-share__btn--save" id="overlaySave" type="button" data-save="" data-save-at="overlay" aria-pressed="false">
                            @include('partials.icon-heart')
                            <span data-save-label>{{ bival('save_add') }}</span>
                        </button>
                        <a class="od-share__btn od-share__btn--wa" id="overlayShareWa" href="#" target="_blank" rel="noopener" data-share="whatsapp" data-share-at="overlay">
                            @include('partials.icon-whatsapp')
                            <span {!! bitext('share_wa') !!}>{{ bival('share_wa') }}</span>
                        </a>
                        @include('partials.share-link', ['shareClass' => 'od-share__btn', 'shareAt' => 'overlay', 'shareProject' => '',
                            'shareUrl' => site_url('/'), 'shareTitle' => bival('seo_title'), 'shareId' => 'overlayShare'])
                    </div>

                </div>
            </div>

        </div>
      </div><!-- /.od-modal -->

    </div>

    {{-- ========== FULL-SIZE IMAGE PROGRESS ==========
         Shown while "View Full Resolution" or the download is fetching the
         untouched original — a multi-megabyte file that is then decoded and
         watermarked. Before this existed the full-res link opened a blank
         tab and the download button appeared to do nothing at all, which is
         the exact wait most likely to be read as a broken site. Percentages
         come from Content-Length; see prepareFullImage in script.js. --}}
    <div class="dlp" id="dlProgress" role="dialog" aria-modal="true" aria-live="polite" aria-label="Preparing full-size image">
        <div class="dlp__panel">
            <div class="dlp__sun" id="dlpSun" aria-hidden="true"></div>
            <p class="dlp__title" id="dlpTitle"></p>
            <p class="dlp__note" id="dlpNote"></p>
            <div class="dlp__bar" id="dlpBar"><div class="dlp__fill" id="dlpFill"></div></div>
            <div class="dlp__stats">
                <span class="dlp__bytes" id="dlpBytes"></span>
                <span class="dlp__pct" id="dlpPct"></span>
            </div>
            <div class="dlp__actions">
                <button class="dlp__btn dlp__btn--gold" id="dlpRetry" type="button" hidden {!! bitext('dl_retry') !!}>{{ bival('dl_retry') }}</button>
                <button class="dlp__btn" id="dlpCancel" type="button" {!! bitext('dl_cancel') !!}>{{ bival('dl_cancel') }}</button>
            </div>
        </div>
    </div>
