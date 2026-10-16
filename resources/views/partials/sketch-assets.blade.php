{{-- "Draw it for us" (sketch.js): the drawing paper's words in both languages
     — the homepage switches language in place — and where a sketch is kept,
     seen and sent. Opened by any element with data-sketch-open. --}}
@php
    $skKeys = [
        'title' => 'sk_title', 'hint' => 'sk_hint', 'start' => 'sk_start', 'close' => 'sk_close',
        'pen' => 'sk_pen', 'line' => 'sk_line', 'undo' => 'sk_undo', 'clear' => 'sk_clear',
        'send' => 'sk_send', 'wa' => 'sk_wa', 'save' => 'card_save', 'attach' => 'sk_attach',
        'sending' => 'sk_sending', 'failed' => 'sk_failed', 'empty' => 'sk_empty', 'waText' => 'sk_wa_text',
    ];
    $skConfig = [
        'en' => array_map(fn ($k) => \App\Support\SiteText::en($k), $skKeys),
        'ku' => array_map(fn ($k) => \App\Support\SiteText::ku($k), $skKeys),
        'host' => preg_replace('/^www\./', '', (string) parse_url(url('/'), PHP_URL_HOST)),
        'store' => route('sketch.store'),
        'show' => url('/sketches/TOKEN'),
        'home' => site_url('/'),
        'wa' => whatsapp_url(),
    ];
@endphp
<script type="application/json" id="skText">{!! json_encode($skConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<link rel="stylesheet" href="{{ asset('sketch.css') }}?v={{ filemtime(public_path('sketch.css')) }}">
<script src="{{ asset('sketch.js') }}?v={{ filemtime(public_path('sketch.js')) }}" defer></script>
