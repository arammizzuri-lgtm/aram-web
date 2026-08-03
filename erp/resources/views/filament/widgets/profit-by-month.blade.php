@php
    $chart = $this->chart();
    $signed = fn ($money) => ($money->isNegative() ? '−' : '') . '$' . number_format(abs($money->toFloat()), 2);
@endphp

<div>
    <x-erp.card title="Profit by month" hint="Twelve months, in USD, after everything each one cost.">
        {{-- The two things anybody takes from a twelve-month profit chart, said
             in words rather than left to be worked out off an axis. --}}
        <x-slot name="head">
            <div class="text-end">
                <div class="erp-numeric erp-stat-value" style="font-size: var(--text-figure)">
                    {{ $signed($chart['total']) }}
                </div>
                <div class="erp-stat-hint">
                    @if ($chart['best'] && $chart['best']['profit'] > 0)
                        best was {{ $chart['best']['full'] }}
                    @else
                        across the year
                    @endif
                </div>
            </div>
        </x-slot>

        @if (! $chart['anything'])
            <x-erp.empty title="No profit recorded yet">
                Months fill in here as deals are delivered and costed.
            </x-erp.empty>
        @else
            <div class="flex items-stretch gap-1" style="height: 9rem">
                @foreach ($chart['columns'] as $column)
                    {{-- Each column is its own strip, so the months are evenly
                         spaced whatever the card width and the label sits under
                         its own bar without any axis arithmetic. --}}
                    <div class="group relative flex-1">
                        <div class="relative h-full">
                            {{-- Zero, drawn once per column so it runs the full
                                 width without a separate absolutely-placed rule
                                 that could fall out of step with the bars. --}}
                            <div class="absolute inset-x-0"
                                 style="top: {{ $chart['zero'] }}%; height: 1px; background: var(--erp-axis)"></div>

                            <div class="absolute inset-x-0 rounded-[2px] erp-transition"
                                 style="top: {{ $column['top'] }}%;
                                        height: {{ $column['height'] }}%;
                                        background: {{ $column['empty']
                                            ? 'var(--erp-border-strong)'
                                            : ($column['positive'] ? 'var(--erp-series-1)' : 'var(--erp-series-8)') }};
                                        opacity: {{ $column['empty'] ? '0.5' : '1' }}">
                                <title>{{ $column['full'] }}</title>
                            </div>
                        </div>

                        {{-- The figure on hover. Twelve labels at once would be
                             noise; one, when asked for, is an answer. --}}
                        <div class="pointer-events-none absolute inset-x-0 -top-1 z-10 hidden justify-center group-hover:flex">
                            <span class="erp-numeric whitespace-nowrap rounded-md px-1.5 py-0.5"
                                  style="font-size: 10px;
                                         background: var(--erp-bg-sunken);
                                         color: var(--erp-text-primary);
                                         border: 1px solid var(--erp-border)">
                                {{ $column['empty'] ? '—' : ($column['positive'] ? '' : '−') . '$' . number_format(abs($column['profit']), 0) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex gap-1">
                @foreach ($chart['columns'] as $column)
                    <div class="flex-1 text-center" style="font-size: 10px; color: var(--erp-axis-text)">
                        {{ $column['label'] }}
                    </div>
                @endforeach
            </div>
        @endif
    </x-erp.card>
</div>
