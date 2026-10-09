@php $ranking = $this->ranking(); @endphp

<div>
    <x-erp.chart
        title="Profit by category"
        :hint="$this->activeLabel() . ', in USD. Each line of business side by side — what it brought in, what it cost, and its share of the profit.'"
    >
        <x-slot name="controls">
            <x-erp.range-picker :value="$this->widgetRange" />
        </x-slot>

        <x-erp.ranked-list
            :rows="$ranking['rows']"
            :strip="$ranking['strip']"
            :legend="[['Profit', 'var(--erp-good)'], ['Loss', 'var(--erp-critical)'], ['Revenue', 'var(--erp-muted-fill)'], ['Largest share', 'var(--erp-series-1)']]"
            empty-title="Nothing sold in this window"
        >
            Categories appear here once deal lines are picked from a price list and costed.
        </x-erp.ranked-list>

        @if ($ranking['approximate'])
            <p class="erp-stat-hint border-t px-5 py-3" style="border-color: var(--erp-border); margin-top: 0">
                Approximate where a deal carries a lump commission or discount — that belongs to the deal, not to any one line.
            </p>
        @endif

        <x-slot name="table">
            <x-erp.data-table
                :columns="$ranking['table']['columns']"
                :rows="$ranking['table']['rows']"
                :footer="$ranking['table']['footer']"
                empty="Nothing sold in this window."
            />
        </x-slot>
    </x-erp.chart>
</div>
