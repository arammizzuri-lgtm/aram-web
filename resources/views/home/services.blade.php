    <!-- ========== SERVICES ========== -->
    <section class="services" id="services">
        <div class="services__inner">
            <div class="services__head">
                <p class="section-label section-label--center" {!! bitext('services_label') !!}>{{ bival('services_label') }}</p>
                <h2 class="services__title" {!! bitext('services_title') !!}>{{ bival('services_title') }}</h2>
                @include('home.sheet-ref', ['key' => 'services'])
            </div>
            <div class="services__grid">
                @foreach (\App\Support\Services::all() as $i => $s)
                    @php $go = \App\Support\Services::link($s, $projects); @endphp
                    {{-- each card opens its work on /projects, or the contact page (App\Support\Services) --}}
                    <a class="service{{ $i >= 4 ? ' service--third' : '' }}" href="{{ $go['href'] }}"
                       @isset($go['href_en']) data-href-en="{{ $go['href_en'] }}" data-href-ku="{{ $go['href_ku'] }}" @endisset>
                        <div class="service__top">
                            <svg class="service__icon" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $s['icon'] !!}</svg>
                            <span class="service__num">0{{ $i + 1 }}</span>
                        </div>
                        <h3 class="service__name" dir="auto" data-en="{{ $s['en'] }}" data-ku="{{ $s['ku'] }}">{{ slot($s["en"], $s["ku"]) }}</h3>
                        <p class="service__desc" dir="auto" data-en="{{ $s['den'] }}" data-ku="{{ $s['dku'] }}">{{ slot($s["den"], $s["dku"]) }}</p>
                        <span class="service__go">
                            <span data-en="{{ $go['en'] }}" data-ku="{{ $go['ku'] }}">{{ slot($go["en"], $go["ku"]) }}</span>
                            <span class="service__go-arrow" aria-hidden="true">{{ $go['arrow'] }}&#xFE0E;</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
