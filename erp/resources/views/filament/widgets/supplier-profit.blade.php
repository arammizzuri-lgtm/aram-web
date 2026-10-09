@php $ranking = $this->ranking(); @endphp

<div>
    <x-erp.chart
        title="Profit by supplier"
        :hint="$this->activeLabel() . ', in USD. Goods margin less what it cost to pay them. Freight is not apportioned here — a consignment belongs to deals, not suppliers.'"
    >
        <x-slot name="controls">
            <x-erp.range-picker :value="$this->widgetRange" />
        </x-slot>

        <x-erp.ranked-list
            :rows="$ranking['rows']"
            :legend="[['Profit', 'var(--erp-good)'], ['Lost on transfers', 'var(--erp-serious)'], ['Loss', 'var(--erp-critical)']]"
            empty-title="Nothing bought in this window"
        >
            Suppliers appear here once a deal line names them and the goods have been costed.
        </x-erp.ranked-list>

        <x-slot name="table">
            <x-erp.data-table
                :columns="$ranking['table']['columns']"
                :rows="$ranking['table']['rows']"
                :footer="$ranking['table']['footer']"
                empty="Nothing bought in this window."
            />
        </x-slot>
    </x-erp.chart>
</div>
