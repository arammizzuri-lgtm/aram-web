@php
    $definitions = $this->definitions();
    $result = $this->result();
    $rows = $result['rows'];
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                       style="color: var(--erp-text-muted)">Report</label>
                <select wire:model.live="report" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @foreach ($definitions as $key => $definition)
                        <option value="{{ $key }}">{{ $definition['label'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" style="color: var(--erp-text-muted)">
                    {{ $definitions[$this->report]['description'] }}
                </p>
            </div>

            {{-- Balance reports are point-in-time, so a date range would be a lie. --}}
            @if ($this->isDated())
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                           style="color: var(--erp-text-muted)">From</label>
                    <input type="date" wire:model.live="from"
                           class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                           style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                           style="color: var(--erp-text-muted)">To</label>
                    <input type="date" wire:model.live="to"
                           class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                           style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
                </div>
            @else
                <div class="xl:col-span-2 flex items-end">
                    <p class="text-xs" style="color: var(--erp-text-muted)">
                        This report shows the position as it stands now, so it takes no date range.
                    </p>
                </div>
            @endif
        </div>

        @if (filled($result['totals']))
            <div class="mt-5 flex flex-wrap gap-x-8 gap-y-3 border-t pt-4" style="border-color: var(--erp-border)">
                @foreach ($result['totals'] as $label => $value)
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide"
                             style="color: var(--erp-text-muted)">{{ $label }}</div>
                        <div class="mt-0.5 text-lg font-semibold erp-numeric">
                            @if (is_numeric($value))
                                {{ (float) $value < 0 ? "\u{2212}" : '' }}${{ number_format(abs((float) $value), 2) }}
                            @else
                                {{ $value }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ $definitions[$this->report]['label'] }}</x-slot>
        <x-slot name="description">{{ number_format($rows->count()) }} rows</x-slot>

        @if ($rows->isEmpty())
            <p class="py-8 text-center text-sm" style="color: var(--erp-text-muted)">
                Nothing to report for this period.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            @foreach ($result['headings'] as $index => $heading)
                                {{-- Padding is not cosmetic here: a right-aligned figure
                                     butted against the next column reads as one value. --}}
                                <th @class([
                                    'px-3 py-2 font-semibold whitespace-nowrap',
                                    'text-end' => $index > 1,
                                    'text-start' => $index <= 1,
                                ])>{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows->take(300) as $row)
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                @foreach ($row as $index => $cell)
                                    <td @class([
                                        'px-3 py-2 whitespace-nowrap',
                                        'text-end erp-numeric' => $index > 1 && is_numeric($cell),
                                        'text-start' => $index <= 1 || ! is_numeric($cell),
                                    ])>
                                        @if (is_numeric($cell) && $index > 1)
                                            {{ (float) $cell < 0 ? "\u{2212}" : '' }}{{ number_format(abs((float) $cell), 2) }}
                                        @else
                                            {{ str($cell)->replace('_', ' ')->ucfirst() }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rows->count() > 300)
                <p class="mt-3 text-xs" style="color: var(--erp-text-muted)">
                    Showing the first 300 rows. Export the CSV for all {{ number_format($rows->count()) }}.
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
