@php
    /**
     * @var array|null $inputs   the visitor's answers, from the address
     * @var array|null $estimate
     * @var array<string, array> $levels
     * @var int $maxFloors
     * @var float $coverage
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = bival('est_title').' — '.$brand;
    $description = \Illuminate\Support\Str::limit(bival('est_intro'), 160);
    $ogImage = asset('og-image.jpg').'?v='.filemtime(public_path('og-image.jpg'));
    $query = request()->getQueryString();
    $fill = fn (string $key, array $with) => str_replace(array_keys($with), array_values($with), bival($key));

    // what the form shows: the visitor's answers, or a sensible start
    $in = $inputs ?? ['plot' => 0, 'floors' => min(2, $maxFloors), 'built' => null, 'basement' => false,
        'bedrooms' => 3, 'bathrooms' => 2, 'finish' => 'premium', 'pool' => false];
    $autoBuilt = $in['plot'] ? \App\Support\CostEstimate::autoBuilt($in['plot'], $in['floors']) : null;
    $pct = rtrim(rtrim(number_format($coverage, 1), '0'), '.');

    // the words estimate.js writes itself, in the page's language
    $jsText = collect(['form_sending', 'form_failed', 'form_need_contact', 'form_check', 'est_sent'])
        ->mapWithKeys(fn ($k) => [$k => bival($k)]);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.seo-alternates', ['path' => '/estimate'])
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aram Mizuri Architecture">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ site_url('/estimate') }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('engage.css') }}?v={{ filemtime(public_path('engage.css')) }}">
    <link rel="stylesheet" href="{{ asset('estimate.css') }}?v={{ filemtime(public_path('estimate.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--estimate">

    @include('partials.page-top', ['otherUrl' => site_url('/estimate', $other).($query ? '?'.$query : ''), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home')])

    <main class="es">
        <header class="es-head">
            <p class="pp-label">{{ bival('est_label') }}</p>
            <h1 class="es-title">{{ bival('est_title') }}</h1>
            <p class="es-intro">{{ bival('est_intro') }}</p>
        </header>

        <div class="es-body">
            {{-- A plain GET form: without JavaScript its button works it out;
                 with it, estimate.js asks for the result as each answer
                 changes, and keeps the address in step. --}}
            <form class="es-form" id="esForm" method="get" action="{{ site_url('/estimate') }}" data-calc="{{ site_url('/estimate/calc') }}">
                <div class="es-field">
                    <label class="es-label" for="es-plot">{{ bival('est_plot') }}</label>
                    <div class="es-unit">
                        <input id="es-plot" name="plot" type="number" inputmode="numeric" min="20" max="100000" step="1"
                               value="{{ $in['plot'] ?: '' }}" placeholder="600" dir="ltr">
                        <span>m²</span>
                    </div>
                </div>

                <fieldset class="es-field">
                    <legend class="es-label">{{ bival('est_floors') }}</legend>
                    <div class="es-seg">
                        @for ($f = 1; $f <= $maxFloors; $f++)
                            <label><input type="radio" name="floors" value="{{ $f }}" @checked($in['floors'] === $f)><span>{{ $f }}</span></label>
                        @endfor
                    </div>
                </fieldset>

                <div class="es-field">
                    <p class="es-label">{{ bival('est_built') }}</p>
                    <p class="es-built"><bdi><b id="esBuiltAuto">{{ $autoBuilt ? number_format($autoBuilt) : '—' }}</b> m²</bdi>
                        <span>{{ $fill('est_built_auto', ['{coverage}' => $pct]) }}</span></p>
                    <label class="es-check">
                        <input type="checkbox" id="esBuiltOwn" @checked($in['built'] !== null)>
                        <span>{{ bival('est_built_own') }}</span>
                    </label>
                    <div class="es-unit" id="esBuiltWrap" @if ($in['built'] === null) hidden @endif>
                        <input id="es-built" name="built" type="number" inputmode="numeric" min="20" max="200000" step="1"
                               value="{{ $in['built'] }}" dir="ltr" aria-label="{{ bival('est_built') }}">
                        <span>m²</span>
                    </div>
                </div>

                <div class="es-pair">
                    @foreach (['bedrooms' => 'est_bedrooms', 'bathrooms' => 'est_bathrooms'] as $name => $label)
                        <div class="es-field">
                            <label class="es-label" for="es-{{ $name }}">{{ bival($label) }}</label>
                            <div class="es-step" dir="ltr">
                                <button type="button" data-step="-1" aria-label="−">−</button>
                                <input id="es-{{ $name }}" name="{{ $name }}" type="number" inputmode="numeric" min="0" max="20" value="{{ $in[$name] }}">
                                <button type="button" data-step="1" aria-label="+">+</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <fieldset class="es-field">
                    <legend class="es-label">{{ bival('est_finish') }}</legend>
                    <div class="es-finishes">
                        @foreach ($levels as $key => $level)
                            <label class="es-finish">
                                <input type="radio" name="finish" value="{{ $key }}" @checked($in['finish'] === $key)>
                                <span class="es-finish__name">{{ $isKu ? $level['ku'] : $level['en'] }}</span>
                                <span class="es-finish__rate">{{ \App\Support\CostEstimate::rangeIn(bival('est_per_m2'), \App\Support\CostEstimate::usd($level['low']), \App\Support\CostEstimate::usd($level['high'])) }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="es-toggles">
                    <label class="es-switch"><input type="checkbox" name="basement" value="1" @checked($in['basement'])><span>{{ bival('est_basement') }}</span></label>
                    <label class="es-switch"><input type="checkbox" name="pool" value="1" @checked($in['pool'])><span>{{ bival('est_pool') }}</span></label>
                </div>

                <button class="pp-btn es-go" type="submit"><span>{{ bival('est_go') }}</span></button>
            </form>

            <aside class="es-result" aria-live="polite">
                <p class="pp-label">{{ bival('est_result') }}</p>
                <div id="esResult">@include('partials.estimate-result', ['estimate' => $estimate])</div>

                {{-- "Send this to the studio": a short form right here, sent with
                     the answers — the server works the estimate out again and
                     files it with the enquiry, its plot, budget band and type. --}}
                <div class="es-send" id="esSend" @unless ($estimate) hidden @endunless>
                    <button class="pp-btn es-send__open" type="button" id="esSendOpen">
                        <span>{{ bival('est_send') }}</span>
                        <span class="pp-arrow" aria-hidden="true">→</span>
                    </button>
                    <form class="es-send__form" id="esSendForm" hidden novalidate>
                        <p class="es-send__title">{{ bival('est_send_title') }}</p>
                        <input name="name" type="text" required autocomplete="name" placeholder="{{ bival('form_name') }}" aria-label="{{ bival('form_name') }}">
                        <input name="phone" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" placeholder="{{ bival('form_phone') }}" aria-label="{{ bival('form_phone') }}">
                        <input name="email" type="email" autocomplete="email" dir="ltr" placeholder="{{ bival('form_email') }}" aria-label="{{ bival('form_email') }}">
                        <p class="es-send__hint">{{ bival('form_reach') }}</p>
                        <textarea name="message" rows="3" dir="auto" placeholder="{{ bival('form_message') }}" aria-label="{{ bival('form_message') }}"></textarea>
                        <div class="form-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                        <button class="pp-btn" type="submit"><span>{{ bival('est_send_btn') }}</span></button>
                        <p class="es-send__status" id="esSendStatus" role="status" hidden></p>
                    </form>
                </div>
            </aside>
        </div>
    </main>

    @include('partials.page-foot')

    @include('partials.whatsapp-fab', ['text' => ['en' => \App\Support\SiteText::en('wa_text'), 'ku' => \App\Support\SiteText::ku('wa_text')], 'hideOver' => '.es-send', 'waProject' => ''])

    <script type="application/json" id="esText">{!! json_encode($jsText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    @include('partials.saved-script')
    <script src="{{ asset('estimate.js') }}?v={{ filemtime(public_path('estimate.js')) }}" defer></script>
</body>
</html>
