@php
    $rows = $this->rows();
    $signed = fn ($money) => ($money->isNegative() ? '−' : '') . '$' . number_format(abs($money->toFloat()), 2);
@endphp

<div>
    <x-erp.card
        flush
        title="Profit by supplier"
        hint="Last 90 days, in USD. Goods margin less what it cost to pay them. Freight is not apportioned here — a consignment belongs to deals, not suppliers."
    >
        @forelse ($rows as $index => $row)
            <div class="flex items-center gap-4 border-t px-5 py-3" style="border-color: var(--erp-border)">
                <span class="erp-numeric w-4 shrink-0 text-center"
                      style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                    {{ $index + 1 }}
                </span>

                <span class="w-40 shrink-0 truncate">
                    <span class="block truncate text-sm font-medium" style="color: var(--erp-text-primary)">
                        {{ $row['supplier'] }}
                    </span>
                    {{-- The cost of paying them, said only when there is one.
                         A supplier who is cheap on paper and expensive to send
                         money to is the thing this row exists to expose. --}}
                    @if ($row['transfer']->isPositive())
                        <span class="erp-numeric block truncate"
                              style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                            {{ $signed($row['transfer']) }} to pay
                        </span>
                    @endif
                </span>

                <span class="h-2 flex-1 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    <span class="block h-full rounded-full"
                          style="width: {{ $row['share'] }}%;
                                 background: {{ $row['negative'] ? 'var(--erp-series-8)' : 'var(--erp-series-3)' }}"></span>
                </span>

                <span class="w-16 shrink-0 erp-numeric text-end"
                      style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                    {{ number_format($row['margin_percent'], 1) }}%
                </span>

                <span @class(['erp-numeric w-28 shrink-0 text-end text-sm font-medium', 'erp-critical' => $row['negative']])
                      @style(['color: var(--erp-text-primary)' => ! $row['negative']])>
                    {{ $signed($row['money']) }}
                </span>
            </div>
        @empty
            <x-erp.empty title="Nothing bought in the last 90 days">
                Suppliers appear here once a deal line names them and the goods have been costed.
            </x-erp.empty>
        @endforelse
    </x-erp.card>
</div>
