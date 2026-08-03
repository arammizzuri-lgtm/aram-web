{{--
    Nothing here yet — said properly.

    A blank panel reads as something that failed to load. A sentence naming
    what would appear here, and what to do to make it appear, reads as a system
    that knows where it is. Every list in the ERP should have one.

    <x-erp.empty title="Nothing billed yet">
        Use "Invoice goods" on the deal once the prices are settled.
    </x-erp.empty>
--}}
@props(['title' => null])

<div {{ $attributes->class('erp-empty') }}>
    @if ($title)
        <p class="erp-empty-title">{{ $title }}</p>
    @endif

    @if (trim($slot) !== '')
        <p @class(['mt-1' => $title])>{{ $slot }}</p>
    @endif
</div>
