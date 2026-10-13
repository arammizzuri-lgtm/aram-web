{{-- "Share" on a phone (its own share sheet), "Copy link" on a computer,
     where there is none — engage.js decides, and counts it. $shareUrl and
     $shareTitle: what is shared. $shareAt: where the button sits.
     $shareProject: the project's slug, if it is one. $shareClass: its look. --}}
<button class="{{ $shareClass }}" type="button" data-share="link" data-share-at="{{ $shareAt }}"
        data-share-project="{{ $shareProject ?? '' }}" data-share-url="{{ $shareUrl }}" data-share-title="{{ $shareTitle }}"
        data-copy-en="{{ \App\Support\SiteText::en('share_copy') }}" data-copy-ku="{{ \App\Support\SiteText::ku('share_copy') }}"
        data-copied-en="{{ \App\Support\SiteText::en('pp_copied') }}" data-copied-ku="{{ \App\Support\SiteText::ku('pp_copied') }}"
        @isset($shareId) id="{{ $shareId }}" @endisset>
    @include('partials.icon-share')
    <span data-share-label {!! bitext('pp_share') !!}>{{ bival('pp_share') }}</span>
</button>
