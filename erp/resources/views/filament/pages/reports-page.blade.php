@php
    $definition = $this->definition();
    $rows = $this->rows();
    $numeric = $this->numericColumns();

    // Money columns get a fixed-point figure; counts and dates are left alone.
    $cell = function ($value, int $index) use ($numeric) {
        if (! in_array($index, $numeric, true) || ! is_numeric($value)) {
            return $value;
        }

        $formatted = number_format((float) $value, 2);

        // A true minus outside the number, so columns line up.
        return (float) $value < 0
            ? '−'.number_format(abs((float) $value), 2)
            : $formatted;
    };
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="erp-label mb-1 block">Report</label>
                <select wire:model.live="report"
                        class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @foreach ($this->available() as $key => $option)
                        <option value="{{ $key }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="erp-label mb-1 block">From</label>
                <input type="date" wire:model.live="from"
                       class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>

            <div>
                <label class="erp-label mb-1 block">To</label>
                <input type="date" wire:model.live="to"
                       class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <span class="text-xs" style="color: var(--erp-text-secondary)">
                <strong class="erp-numeric">{{ $rows->count() }}</strong>
                {{ str('row')->plural($rows->count()) }}
            </span>

            <x-filament::button wire:click="export" color="gray" size="sm" icon="heroicon-o-arrow-down-tray">
                Export CSV
            </x-filament::button>
        </div>
    </x-filament::section>

    <x-filament::section>
        @if ($rows->isEmpty())
            <p class="py-10 text-center text-sm" style="color: var(--erp-text-muted)">
                Nothing in this range.
            </p>
        @else
            {{-- Sizes to its content and scrolls, rather than squeezing columns
                 until the figures wrap. --}}
            <div class="-mx-3 overflow-x-auto px-3">
                <table class="w-max min-w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            @foreach ($definition['columns'] as $index => $column)
                                <th @class(['px-3 py-2 font-semibold whitespace-nowrap',
                                            'text-end' => in_array($index, $numeric, true),
                                            'text-start' => ! in_array($index, $numeric, true)])>
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                @foreach ($row as $index => $value)
                                    <td @class(['px-3 py-1.5 whitespace-nowrap',
                                                'text-end erp-numeric' => in_array($index, $numeric, true),
                                                // A loss reads as a loss, in the
                                                // same red the charts use.
                                                'font-medium' => in_array($index, $numeric, true)])
                                        @style(['color: var(--erp-diverging-neg)' =>
                                            in_array($index, $numeric, true) && is_numeric($value) && (float) $value < 0])>
                                        {{ $cell($value, $index) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
