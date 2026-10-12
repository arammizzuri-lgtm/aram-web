{{-- The floating WhatsApp button, on the homepage and every project page.
     $text: the first message, ['en' => …, 'ku' => …]. $hideOver: the sections
     it steps aside for (engage.js) — where it would cover controls, or where
     the page already offers WhatsApp itself. Absent with no number set. --}}
@php $fab = whatsapp_url($text[site_lang()] ?? $text['en']); @endphp
@if ($fab)
    <a class="wa-fab" id="waFab" href="{{ $fab }}" target="_blank" rel="noopener"
       data-href-en="{{ whatsapp_url($text['en']) }}" data-href-ku="{{ whatsapp_url($text['ku']) }}"
       data-hide-over="{{ $hideOver }}"
       aria-label="{{ bival('wa_cta') }}">
        @include('partials.icon-whatsapp')
        <span class="wa-fab__label" {!! bitext('wa_cta') !!}>{{ bival('wa_cta') }}</span>
    </a>
@endif
