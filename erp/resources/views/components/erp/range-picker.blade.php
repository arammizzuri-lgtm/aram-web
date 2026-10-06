{{--
    A widget's own time-range control.

    Binds to the widgetRange / widgetRangeStart / widgetRangeEnd properties the
    HasWidgetRange trait supplies, so dropping this into any widget that uses the
    trait is all it takes. "Dashboard default" is the empty value — the widget
    then follows the filter at the top of the page.

    Styled from the design tokens rather than utility classes so it needs no
    asset rebuild to look like the rest of the panel.

    $value is passed in so the custom date fields appear only when they apply.
--}}
@props(['value' => null])

@php
    $control = 'font-size: var(--text-hint); color: var(--erp-text-secondary);'
        .' background: var(--erp-bg-surface); border: 1px solid var(--erp-border);'
        .' border-radius: var(--radius-md); padding: 0.25rem 0.5rem; line-height: 1.25rem;';
@endphp

<div {{ $attributes }} style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; justify-content: flex-end;">
    <select wire:model.live="widgetRange" style="{{ $control }} max-width: 11rem;" aria-label="Time range for this widget">
        <option value="">Dashboard default</option>
        @foreach (\App\Support\DashboardRange::options() as $group => $opts)
            <optgroup label="{{ $group }}">
                @foreach ($opts as $val => $lab)
                    <option value="{{ $val }}">{{ $lab }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>

    @if ($value === \App\Support\DashboardRange::CUSTOM)
        <input type="date" wire:model.live="widgetRangeStart" style="{{ $control }}" aria-label="From">
        <input type="date" wire:model.live="widgetRangeEnd" style="{{ $control }}" aria-label="To">
    @endif
</div>
