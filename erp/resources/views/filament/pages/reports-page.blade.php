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

    {{-- ───────────────────────────────────────────── comparing the strategies
         Three questions the row-by-row reports above cannot answer, over the
         same window as whatever is selected there. --}}
    @php $comparisons = $this->comparisons(); @endphp

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Which way of setting a price actually earns more --}}
        <x-erp.card
            flush
            title="How you priced it"
            hint="Margin by pricing method. Compared on margin and not on total, because a method used more often would always win on volume."
        >
            @forelse ($comparisons['pricing'] as $method)
                <div class="flex items-center gap-4 border-t px-5 py-3" style="border-color: var(--erp-border)">
                    <span class="w-32 shrink-0 truncate">
                        <span class="block truncate text-sm font-medium" style="color: var(--erp-text-primary)">
                            {{ $method['label'] }}
                        </span>
                        <span class="block" style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                            {{ $method['lines'] }} {{ \Illuminate\Support\Str::plural('line', $method['lines']) }}
                        </span>
                    </span>

                    <span class="h-2 flex-1 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                        <span class="block h-full rounded-full"
                              style="width: {{ max(2, round(max(0, $method['margin_percent']) / $comparisons['pricing_widest'] * 100)) }}%;
                                     background: {{ $method['margin_percent'] < 0 ? 'var(--erp-series-8)' : 'var(--erp-series-1)' }}"></span>
                    </span>

                    <span class="w-16 shrink-0 erp-numeric text-end text-sm font-medium"
                          style="color: var(--erp-text-primary)">
                        {{ number_format($method['margin_percent'], 1) }}%
                    </span>

                    <span class="w-24 shrink-0 erp-numeric text-end"
                          style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                        ${{ number_format($method['profit'], 0) }}
                    </span>
                </div>
            @empty
                <x-erp.empty title="No lines priced in this window">
                    Set a wider date range above, or price some deal lines.
                </x-erp.empty>
            @endforelse
        </x-erp.card>

        {{-- Sea against air, in units that can be compared --}}
        <x-erp.card
            flush
            title="What shipping really cost"
            hint="Sea is billed for space and air for weight, so both are shown for both — that is what makes the choice comparable."
        >
            @forelse ($comparisons['shipping'] as $mode)
                <div class="border-t px-5 py-3" style="border-color: var(--erp-border)">
                    <div class="flex items-baseline justify-between gap-4">
                        <span class="text-sm font-medium" style="color: var(--erp-text-primary)">
                            {{ $mode['label'] }}
                        </span>
                        <span class="erp-numeric text-sm font-medium" style="color: var(--erp-text-primary)">
                            ${{ number_format($mode['freight'], 2) }}
                        </span>
                    </div>

                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1"
                         style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                        <span>{{ $mode['shipments'] }} {{ \Illuminate\Support\Str::plural('shipment', $mode['shipments']) }}</span>
                        <span class="erp-numeric">
                            {{ $mode['per_kg'] !== null ? '$' . number_format($mode['per_kg'], 2) . ' / kg' : '— / kg' }}
                        </span>
                        <span class="erp-numeric">
                            {{ $mode['per_cbm'] !== null ? '$' . number_format($mode['per_cbm'], 2) . ' / m³' : '— / m³' }}
                        </span>
                    </div>
                </div>
            @empty
                <x-erp.empty title="No freight billed in this window">
                    A consignment appears here once its freight amount has been typed from the forwarder's bill.
                </x-erp.empty>
            @endforelse
        </x-erp.card>
    </div>

    {{-- The thin ones, which a monthly total hides --}}
    <x-erp.card
        flush
        title="Deals earning least"
        hint="Ranked by margin, not by profit — a small order at 4% is the same mistake a large one only makes more expensive."
    >
        @forelse ($comparisons['thin'] as $deal)
            <a href="{{ \App\Filament\Resources\Deals\DealResource::getUrl('edit', ['record' => $deal['id']]) }}"
               class="erp-transition flex items-center gap-4 border-t px-5 py-3 hover:bg-[var(--erp-bg-hover)]"
               style="border-color: var(--erp-border)">
                <span class="w-32 shrink-0 truncate">
                    <span class="block truncate text-sm font-medium" style="color: var(--erp-text-primary)">
                        {{ $deal['deal'] }}
                    </span>
                    <span class="block truncate" style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                        {{ $deal['customer'] }}
                    </span>
                </span>

                <span class="h-2 flex-1 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    <span class="block h-full rounded-full"
                          style="width: {{ max(2, round(max(0, $deal['margin_percent']) / $comparisons['thin_widest'] * 100)) }}%;
                                 background: {{ $deal['margin_percent'] < 10 ? 'var(--erp-serious)' : 'var(--erp-series-1)' }}"></span>
                </span>

                <span @class(['w-16 shrink-0 erp-numeric text-end text-sm font-medium', 'erp-critical' => $deal['margin_percent'] < 0])
                      @style(['color: var(--erp-text-primary)' => $deal['margin_percent'] >= 0])>
                    {{ number_format($deal['margin_percent'], 1) }}%
                </span>

                <span class="w-28 shrink-0 erp-numeric text-end"
                      style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                    ${{ number_format($deal['profit'], 2) }}
                </span>
            </a>
        @empty
            <x-erp.empty title="No deals in this window">
                Widen the date range above to see more.
            </x-erp.empty>
        @endforelse
    </x-erp.card>
</x-filament-panels::page>
