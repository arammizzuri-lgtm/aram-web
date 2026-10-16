@php
    /**
     * A holiday card (CardController): the greeting, one of the studio's
     * photographs and the visitor's own name, drawn in the browser
     * (cards.js, greeting.js) and sent on WhatsApp — or saved.
     *
     * @var \App\Models\Greeting $greeting
     * @var array<int, array{url: string, pos: string, name_en: string, name_ku: string}> $photos
     * @var bool $preview  shown to the studio ahead of its dates
     */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $other = $isKu ? 'en' : 'ku';
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $title = $greeting->title($lang).' — '.$brand;
    $host = preg_replace('/^www\./', '', (string) parse_url(url('/'), PHP_URL_HOST));

    // everything the card is drawn from, in both languages: the visitor may
    // send a Kurdish card from the English page, and the other way round
    $card = [
        'lang' => $lang,
        'title' => ['en' => $greeting->title_en, 'ku' => $greeting->title('ku')],
        'text' => ['en' => $greeting->text('en'), 'ku' => $greeting->text('ku')],
        'from' => ['en' => \App\Support\SiteText::en('card_from'), 'ku' => \App\Support\SiteText::ku('card_from')],
        'brand' => ['en' => mb_strtoupper(\App\Support\SiteText::en('brand_name')).' · '.mb_strtoupper(\App\Support\SiteText::en('card_brand_sub')),
            'ku' => \App\Support\SiteText::ku('brand_name').' · '.\App\Support\SiteText::ku('card_brand_sub')],
        'host' => $host,
        'photos' => $photos,
        'file' => \Illuminate\Support\Str::slug($greeting->name ?: 'card').'-card.jpg',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ bival('card_lead') }}">
    <meta name="robots" content="noindex">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ bival('card_lead') }}">
    @if ($photos)<meta property="og:image" content="{{ $photos[0]['url'] }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @php $fontsHref = 'https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&display=swap'; @endphp
    <link href="{{ $fontsHref }}" rel="stylesheet" media="print" onload="this.media='all';this.onload=null">
    <noscript><link href="{{ $fontsHref }}" rel="stylesheet"></noscript>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="{{ asset('project.css') }}?v={{ filemtime(public_path('project.css')) }}">
    <link rel="stylesheet" href="{{ asset('cards.css') }}?v={{ filemtime(public_path('cards.css')) }}">
    @include('partials.kurdish-font')
</head>
<body class="pp pp--card">

    @include('partials.page-top', ['otherUrl' => site_url('/card', $other).($preview ? '?greeting='.$greeting->id : '')])

    <main class="gc" id="gc" data-card='@json($card)'>
        @if ($preview)
            <p class="gc-preview">{{ bival('card_preview') }}</p>
        @endif

        <div class="gc-layout">
            {{-- the card itself, drawn here as the answers change --}}
            <figure class="gc-stage">
                <canvas class="gc-canvas" id="gcCanvas" width="1080" height="1350" role="img" aria-label="{{ $greeting->title($lang) }}"></canvas>
                <i class="px-crop px-crop--tl" aria-hidden="true"></i><i class="px-crop px-crop--tr" aria-hidden="true"></i>
                <i class="px-crop px-crop--bl" aria-hidden="true"></i><i class="px-crop px-crop--br" aria-hidden="true"></i>
            </figure>

            <div class="gc-panel">
                <p class="pp-label">{{ bival('card_label') }}</p>
                <h1 class="gc-title">{{ $greeting->title($lang) }}</h1>
                <p class="gc-lead">{{ bival('card_lead') }}</p>

                <form class="gc-form" id="gcForm" onsubmit="return false">
                    <label class="gc-field">
                        <span class="gc-field__label">{{ bival('card_name') }}</span>
                        <input type="text" id="gcName" maxlength="40" autocomplete="name" dir="auto" placeholder="{{ bival('card_name_ph') }}">
                    </label>

                    <fieldset class="gc-field">
                        <legend class="gc-field__label">{{ bival('card_lang') }}</legend>
                        <div class="gc-seg">
                            <label><input type="radio" name="gcLang" value="ku" @checked($isKu)><span lang="ckb">کوردی</span></label>
                            <label><input type="radio" name="gcLang" value="en" @checked(! $isKu)><span lang="en">English</span></label>
                        </div>
                    </fieldset>

                    @if (count($photos) > 1)
                        <fieldset class="gc-field">
                            <legend class="gc-field__label">{{ bival('card_photo') }}</legend>
                            <div class="gc-photos">
                                @foreach ($photos as $i => $photo)
                                    <label class="gc-photo">
                                        <input type="radio" name="gcPhoto" value="{{ $i }}" @checked($i === 0)>
                                        <img src="{{ $photo['url'] }}" alt="{{ $isKu ? $photo['name_ku'] : $photo['name_en'] }}" loading="lazy" decoding="async" style="object-position: {{ $photo['pos'] }}">
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="gc-actions">
                        <button class="pp-btn gc-send" type="button" id="gcSend" disabled>
                            @include('partials.icon-whatsapp')
                            <span>{{ bival('card_send') }}</span>
                        </button>
                        <button class="pp-act gc-save" type="button" id="gcSave" disabled>{{ bival('card_save') }}</button>
                    </div>
                    <p class="gc-private">{{ bival('card_private') }}</p>
                </form>
                <noscript><p class="gc-private">{{ bival('card_noscript') }}</p></noscript>
            </div>
        </div>
    </main>

    @include('partials.page-foot')

    <script src="{{ asset('engage.js') }}?v={{ filemtime(public_path('engage.js')) }}" defer></script>
    <script src="{{ asset('cards.js') }}?v={{ filemtime(public_path('cards.js')) }}" defer></script>
    <script src="{{ asset('greeting.js') }}?v={{ filemtime(public_path('greeting.js')) }}" defer></script>
    @include('partials.saved-script')
</body>
</html>
