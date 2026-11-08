{{-- The blueprint view's switch (blueprint.js): a drafting-grid icon that
     turns the site into a blueprint and back. $class: its look where it sits.
     blueprint.js is loaded with the first switch on a page — early, without
     defer, so a visitor who keeps the view on sees no flash of the photos. --}}
@once
    <script src="{{ asset('blueprint.js') }}?v={{ filemtime(public_path('blueprint.js')) }}"
            data-css="{{ asset('blueprint.css') }}?v={{ filemtime(public_path('blueprint.css')) }}"></script>
@endonce
<button type="button" class="{{ $class ?? 'pp-top__link' }} bp-switch" data-blueprint aria-pressed="false"
        data-on="{{ bival('blueprint_on') }}" data-off="{{ bival('blueprint_off') }}"
        aria-label="{{ bival('blueprint_on') }}" title="{{ bival('blueprint_on') }}">
    <svg class="bp-switch__icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
        <rect x="3.5" y="3.5" width="17" height="17" rx="1.5"/>
        <path d="M3.5 9.2h17M3.5 14.8h17M9.2 3.5v17M14.8 3.5v17" opacity=".55"/>
        <path d="M6.5 17.5l4-6 3 3.5 3.5-5" stroke-width="1.7"/>
    </svg>
    @if (! empty($withLabel))
        <span data-blueprint-label>{{ bival('blueprint_on') }}</span>
    @endif
</button>
