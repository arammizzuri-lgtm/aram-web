{{-- The hearts and the "Saved" list (saved.js), on every public page. The
     words the script has to write itself — a heart's label as it turns on and
     off — travel with it in both languages, since the homepage switches
     language in place. --}}
@php
    $savedText = collect(['save' => 'save_add', 'saved' => 'save_added', 'kept' => 'saved_kept'])
        ->map(fn ($key) => ['en' => \App\Support\SiteText::en($key), 'ku' => \App\Support\SiteText::ku($key)]);
@endphp
<script type="application/json" id="savedText">{!! json_encode($savedText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('saved.js') }}?v={{ filemtime(public_path('saved.js')) }}" defer></script>
