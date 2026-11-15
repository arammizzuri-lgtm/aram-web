    <!-- ========== PROCESS ========== -->
    <section class="process" id="process">
        <div class="process__bg" aria-hidden="true">
            <span class="process__aurora process__aurora--a"></span>
            <span class="process__aurora process__aurora--b"></span>
            <span class="process__grid"></span>
        </div>

        <div class="process__inner">

            <!-- Sticky narrative rail -->
            <aside class="process__aside">
                @unless (isset($sheet))
                <p class="section-label" {!! bitext('process_label') !!}>{{ bival('process_label') }}</p>
                <h2 class="process__title" {!! bitext('process_title') !!}>{{ bival('process_title') }}</h2>
                @include('home.sheet-ref', ['key' => 'process'])
                @endunless

                <div class="process__meter">
                    <div class="process__count">
                        <span class="process__count-now" id="procNow">01</span>
                        <span class="process__count-total">/ 04</span>
                    </div>
                    <div class="process__bar"><span class="process__bar-fill" id="procBar"></span></div>
                </div>

                {{-- Liquid-glass design showcase — cycles through every
                     project cover every 3s (initProcessShowcase in script.js).
                     Desktop only: hidden ≤1100px where the aside stacks. --}}
                <div class="process__showcase" id="processShowcase" role="button" tabindex="0">
                    <p class="pshow__label" {!! bitext('process_selected') !!}>{{ bival('process_selected') }}</p>
                    <div class="pshow__frame">
                        <img class="pshow__img" alt="" draggable="false">
                        <img class="pshow__img" alt="" draggable="false">
                    </div>
                    <div class="pshow__foot">
                        <span class="pshow__num" id="pshowNum"></span>
                        <span class="pshow__name" id="pshowName"></span>
                        <span class="pshow__go" aria-hidden="true">→</span>
                    </div>
                </div>
            </aside>

            <!-- Scroll-told timeline -->
            <ol class="process__list" id="procList">
                <span class="process__rail" aria-hidden="true"><span class="process__rail-fill" id="procRailFill"></span></span>

                <li class="process__item" data-step="0">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="01" data-ku="٠١">{{ bi('01', '٠١', '٠١') }}</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase1') !!}>{{ bival('process_phase1') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step1_title') !!}>{{ bival('process_step1_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step1_desc') !!}>{{ bival('process_step1_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="1">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="02" data-ku="٠٢">{{ bi('02', '٠٢', '٠٢') }}</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase2') !!}>{{ bival('process_phase2') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step2_title') !!}>{{ bival('process_step2_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step2_desc') !!}>{{ bival('process_step2_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="2">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="03" data-ku="٠٣">{{ bi('03', '٠٣', '٠٣') }}</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase3') !!}>{{ bival('process_phase3') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step3_title') !!}>{{ bival('process_step3_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step3_desc') !!}>{{ bival('process_step3_desc') }}</p>
                        </div>
                    </article>
                </li>

                <li class="process__item" data-step="3">
                    <div class="process__node"><span class="process__orb"></span></div>
                    <article class="process__card">
                        <span class="process__ghost" aria-hidden="true" dir="auto" data-en="04" data-ku="٠٤">{{ bi('04', '٠٤', '٠٤') }}</span>
                        <div class="process__card-body">
                            <span class="process__kicker" {!! bitext('process_phase4') !!}>{{ bival('process_phase4') }}</span>
                            <h3 class="process__card-title" {!! bitext('process_step4_title') !!}>{{ bival('process_step4_title') }}</h3>
                            <p class="process__card-desc" {!! bitext('process_step4_desc') !!}>{{ bival('process_step4_desc') }}</p>
                        </div>
                    </article>
                </li>
            </ol>
        </div>
    </section>
