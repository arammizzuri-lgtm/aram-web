{{-- Proof that the site is the studio's, for Google Search Console and Bing
     Webmaster Tools — the codes pasted under Site Content → Search & sharing.
     Nothing is written while a code is empty. --}}
@php
    $googleCode = \App\Filament\Pages\ManageSiteContent::verificationCode(setting('google_site_verification'));
    $bingCode = \App\Filament\Pages\ManageSiteContent::verificationCode(setting('bing_site_verification'));
@endphp
@if ($googleCode !== '')
    <meta name="google-site-verification" content="{{ $googleCode }}">
@endif
@if ($bingCode !== '')
    <meta name="msvalidate.01" content="{{ $bingCode }}">
@endif
