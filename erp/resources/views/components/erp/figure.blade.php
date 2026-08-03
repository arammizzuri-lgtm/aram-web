{{--
    One figure, with its label above and its meaning below.

    A number on its own is not information. Two things make it one: what it
    counts, and which way it points — so the label and the hint are part of the
    component rather than something each screen remembers to add.

    <x-erp.figure label="Account balance" :value="$balance" hint="they owe you" lead />

    `lead` marks the single figure a screen is *about*. Only one per screen: if
    everything is emphasised then nothing is, and a wall of display-size numbers
    is the most common way a dashboard ends up unreadable.

    `tone` colours the figure, and should be used sparingly — for the one that
    asks to be acted on, not for every number that happens to be a total.
--}}
@props([
    'label',
    'value',
    'hint' => null,
    'lead' => false,
    'tone' => null,
])

<div {{ $attributes }}>
    <div class="erp-label">{{ $label }}</div>

    <div @class([
            'erp-stat-value mt-2 erp-numeric text-start',
            'erp-good' => $tone === 'good',
            'erp-warning' => $tone === 'warning',
            'erp-serious' => $tone === 'serious',
            'erp-critical' => $tone === 'critical',
        ])
        @if ($lead) data-lead @endif
    >{{ $value }}</div>

    @if ($hint)
        <div class="erp-stat-hint">{{ $hint }}</div>
    @endif
</div>
