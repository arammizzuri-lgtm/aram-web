@php
    $rows = $this->rows();
    $signed = fn ($money) => ($money->isNegative() ? '−' : '') . '$' . number_format(abs($money->toFloat()), 2);
@endphp

<div>
    <x-erp.card
        flush
        title="Profit by customer"
        hint="Last 90 days, in USD. Ranked by what they earned, not what they spent."
    >
        @forelse ($rows as $index => $row)
            @php $tag = $row['url'] ? 'a' : 'div'; @endphp

            <{{ $tag }}
                @if ($row['url']) href="{{ $row['url'] }}" @endif
                class="erp-transition flex items-center gap-4 border-t px-5 py-3 {{ $row['url'] ? 'hover:bg-[var(--erp-bg-hover)]' : '' }}"
                style="border-color: var(--erp-border)"
            >
                {{-- The rank, because a list that is ordered should say so. --}}
                <span class="erp-numeric w-4 shrink-0 text-center"
                      style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                    {{ $index + 1 }}
                </span>

                <span class="w-40 shrink-0 truncate text-sm font-medium"
                      style="color: var(--erp-text-primary)">
                    {{ $row['customer'] }}
                </span>

                {{-- The bar carries the comparison and nothing else — no axis,
                     no gridlines, no legend. The figure beside it is exact, so
                     nothing has to be read off a scale by eye. --}}
                <span class="h-2 flex-1 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    <span class="block h-full rounded-full"
                          style="width: {{ $row['share'] }}%;
                                 background: {{ $row['negative'] ? 'var(--erp-series-8)' : 'var(--erp-series-1)' }}"></span>
                </span>

                <span @class(['erp-numeric w-28 shrink-0 text-end text-sm font-medium', 'erp-critical' => $row['negative']])
                      @style(['color: var(--erp-text-primary)' => ! $row['negative']])>
                    {{ $signed($row['profit']) }}
                </span>
            </{{ $tag }}>
        @empty
            <x-erp.empty title="Nothing earned in the last 90 days">
                Customers appear here once their deals have been delivered and costed.
            </x-erp.empty>
        @endforelse
    </x-erp.card>
</div>
