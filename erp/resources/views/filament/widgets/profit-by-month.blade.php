{{--
    How the profit was built — a waterfall, month by month, ending in the total.

    Each column stands where the last one ended. A hairline carries the running
    total across the gap to the next, so an empty month reads as a flat stretch
    and a losing one as a drop. Hover a month for what it was made of; click it
    for its deals.
--}}
@php
    $chart = $this->chart();
    $label = $this->activeLabel();
@endphp

<div>
    <x-erp.chart
        title="How the profit was built"
        :hint="$label . ', in USD. Each month steps up by what it earned and down by what it lost; the last column is the total.'"
    >
        <x-slot name="controls">
            <x-erp.range-picker :value="$this->widgetRange" />
        </x-slot>

        <x-slot name="figure">
            <div class="text-end">
                <div @class(['erp-stat-value', 'erp-critical' => $chart['total'] < 0])>{{ $chart['totalText'] }}</div>
                <div class="erp-stat-hint">
                    {{ $chart['best'] ? 'best was '.$chart['best'] : 'across the window' }}
                </div>
            </div>
        </x-slot>

        @if (! $chart['anything'])
            <x-erp.empty title="No profit recorded yet">
                Months fill in here as deals are delivered and costed.
            </x-erp.empty>
        @else
            <div class="flex gap-3 px-5 pt-8">
                {{-- The scale, one round figure per gridline. --}}
                <div class="relative w-11 shrink-0" style="height: 14rem" aria-hidden="true">
                    @foreach ($chart['ticks'] as $tick)
                        <span class="erp-axis-label absolute end-0" style="top: {{ $tick['y'] }}%; transform: translateY(-50%)">{{ $tick['label'] }}</span>
                    @endforeach
                </div>

                <div class="relative flex-1" style="height: 14rem">
                    @foreach ($chart['ticks'] as $tick)
                        <div class="erp-gridline" style="top: {{ $tick['y'] }}%" @if ($tick['zero']) data-zero @endif></div>
                    @endforeach

                    <div class="absolute inset-0 flex" style="gap: 6px">
                        @foreach ($chart['columns'] as $column)
                            <a href="{{ $column['url'] }}"
                               class="erp-mark relative h-full flex-1"
                               aria-label="{{ implode('. ', $column['tip']) }}"
                               x-on:mouseenter="show($event, @js($column['tip']))"
                               x-on:mouseleave="hide()"
                               x-on:focus="show($event, @js($column['tip']))"
                               x-on:blur="hide()">
                                @unless ($column['empty'])
                                    <div data-anchor class="erp-fill absolute"
                                         style="inset-inline: 16%; top: {{ $column['top'] }}%; height: {{ $column['height'] }}%; border-radius: 3px; --bar: {{ $column['colour'] }}"></div>

                                    @if ($chart['labelled'] || $column['kind'] === 'total')
                                        <span class="erp-axis-label absolute inset-x-0 text-center"
                                              @if ($column['kind'] === 'step') data-step-label @endif
                                              style="top: calc({{ $column['up'] ? $column['top'] : $column['top'] + $column['height'] }}% {{ $column['up'] ? '- 1.15rem' : '+ 0.25rem' }}); color: {{ $column['kind'] === 'total' ? 'var(--erp-text-primary)' : 'var(--erp-text-secondary)' }}">{{ $column['label'] }}</span>
                                    @endif
                                @endunless

                                {{-- The running total, carried across to the next column. --}}
                                @if ($column['kind'] === 'step')
                                    <div class="absolute" style="left: 84%; width: calc(32% + 6px); top: {{ $column['level'] }}%; height: 1px; background-color: var(--erp-axis)"></div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex gap-3 px-5 pt-2" aria-hidden="true">
                <div class="w-11 shrink-0"></div>
                <div class="flex flex-1" style="gap: 6px">
                    @foreach ($chart['columns'] as $column)
                        <span class="erp-axis-label flex-1 text-center"
                              @if ($column['kind'] === 'total') style="color: var(--erp-text-secondary); font-weight: 600" @endif>{{ $column['axis'] }}</span>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 pt-3 pb-4 erp-stat-hint" style="margin-top: 0">
                <span class="inline-flex items-center gap-1.5"><span class="erp-swatch" style="--bar: var(--erp-good)"></span>Earned</span>
                <span class="inline-flex items-center gap-1.5"><span class="erp-swatch" style="--bar: var(--erp-critical)"></span>Lost</span>
                <span class="inline-flex items-center gap-1.5"><span class="erp-swatch" style="--bar: var(--erp-series-1)"></span>Total</span>
                <span>· Click a month for its deals</span>
            </div>
        @endif

        <x-slot name="table">
            <x-erp.data-table
                :columns="[
                    ['label' => 'Month'],
                    ['label' => 'Deals', 'numeric' => true],
                    ['label' => 'Revenue', 'numeric' => true],
                    ['label' => 'Cost', 'numeric' => true],
                    ['label' => 'Profit', 'numeric' => true],
                    ['label' => 'Margin', 'numeric' => true],
                    ['label' => 'Running total', 'numeric' => true],
                ]"
                :rows="$chart['table']"
                :footer="$chart['footer']"
                empty="No profit recorded in this window."
            />
        </x-slot>
    </x-erp.chart>
</div>
