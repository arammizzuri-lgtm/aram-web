@php
    $shipment = $this->shipment();
    $run = $this->getCurrentRun();
    $money = fn ($value, int $decimals = 2) => '$' . number_format((float) $value, $decimals);
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Costs, with the basis each one was spread by --}}
        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Shipment costs</x-slot>
            <x-slot name="description">
                The basis decides who pays what. Freight follows the space consumed,
                insurance follows value, duty follows each item's own HS code.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            <th class="py-2 text-start font-semibold">Cost</th>
                            <th class="py-2 text-start font-semibold">Basis</th>
                            <th class="py-2 text-end font-semibold">Amount</th>
                            <th class="py-2 text-center font-semibold">State</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shipment->costs as $cost)
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                <td class="py-2">
                                    <div class="font-medium">{{ $cost->type->name }}</div>
                                    @if ($cost->description)
                                        <div class="text-xs" style="color: var(--erp-text-muted)">{{ $cost->description }}</div>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $cost->allocation_basis->getLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 text-end erp-numeric">{{ $money($cost->base_amount) }}</td>
                                <td class="py-2 text-center">
                                    <x-filament::badge :color="$cost->is_estimated ? 'warning' : 'success'" size="sm">
                                        {{ $cost->is_estimated ? 'Estimated' : 'Actual' }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center" style="color: var(--erp-text-muted)">
                                No costs recorded yet.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- The headline: goods + costs = landed --}}
        <x-filament::section>
            <x-slot name="heading">Container total</x-slot>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt style="color: var(--erp-text-secondary)">Goods value</dt>
                    <dd class="erp-numeric font-medium">{{ $money($shipment->total_goods_base) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt style="color: var(--erp-text-secondary)">Shipping costs</dt>
                    <dd class="erp-numeric font-medium">+ {{ $money($shipment->total_costs_base) }}</dd>
                </div>
                <div class="flex justify-between border-t pt-3" style="border-color: var(--erp-border)">
                    <dt class="font-semibold">Landed total</dt>
                    <dd class="erp-numeric font-semibold">
                        {{ $money((float) $shipment->total_goods_base + (float) $shipment->total_costs_base) }}
                    </dd>
                </div>
            </dl>

            <div class="mt-5">
                <div class="mb-1 flex items-center justify-between text-xs">
                    <span style="color: var(--erp-text-muted)">Cost uplift over goods</span>
                    <span class="erp-numeric font-semibold">+{{ $shipment->costUpliftPercent() }}%</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    <div class="h-full rounded-full"
                         style="width: {{ min(100, $shipment->costUpliftPercent()) }}%; background: var(--color-primary-600)"></div>
                </div>
            </div>

            <dl class="mt-5 space-y-2 text-xs" style="color: var(--erp-text-secondary)">
                <div class="flex justify-between">
                    <dt>Total volume</dt>
                    <dd class="erp-numeric">{{ number_format((float) $shipment->total_volume_cbm, 2) }} m³</dd>
                </div>
                <div class="flex justify-between">
                    <dt>Total weight</dt>
                    <dd class="erp-numeric">{{ number_format((float) $shipment->total_weight_kg, 0) }} kg</dd>
                </div>
                @if ($run)
                    <div class="flex justify-between">
                        <dt>Costing run</dt>
                        <dd>v{{ $run->version }} · {{ $shipment->landed_cost_status->getLabel() }}</dd>
                    </div>
                @endif
            </dl>
        </x-filament::section>
    </div>

    {{-- Per-item result: the number the business runs on --}}
    <x-filament::section>
        <x-slot name="heading">Landed cost per item</x-slot>
        <x-slot name="description">
            Every figure traces back to a charge above. Compare the unit cost against the
            supplier price to see what shipping really added.
        </x-slot>

        @if (! $run)
            <p class="py-6 text-center text-sm" style="color: var(--erp-text-muted)">
                Not costed yet — press <strong>Recalculate</strong> to run the allocation.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            <th class="py-2 text-start font-semibold">Product</th>
                            <th class="py-2 text-end font-semibold">Qty</th>
                            <th class="py-2 text-end font-semibold">Supplier price</th>
                            @foreach ($run->lines->first()?->unitCostBreakdown() ?? [] as $label => $ignored)
                                <th class="py-2 text-end font-semibold">{{ $label }}</th>
                            @endforeach
                            <th class="py-2 text-end font-semibold">Landed / unit</th>
                            <th class="py-2 text-end font-semibold">Uplift</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($run->lines as $line)
                            @php
                                $breakdown = $line->unitCostBreakdown();
                                $supplierUnit = (float) $line->quantity > 0
                                    ? (float) $line->goods_value_base / (float) $line->quantity
                                    : 0;
                            @endphp
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                <td class="py-2.5">
                                    <div class="font-medium">{{ $line->product->name }}</div>
                                    <div class="erp-identifier">{{ $line->product->sku }}</div>
                                </td>
                                <td class="py-2.5 text-end erp-numeric">{{ number_format((float) $line->quantity, 0) }}</td>
                                <td class="py-2.5 text-end erp-numeric">{{ $money($supplierUnit) }}</td>
                                @foreach ($breakdown as $amount)
                                    <td class="py-2.5 text-end erp-numeric" style="color: var(--erp-text-secondary)">
                                        {{ $money($amount) }}
                                    </td>
                                @endforeach
                                <td class="py-2.5 text-end erp-numeric font-semibold">
                                    {{ $money($line->landed_unit_cost) }}
                                </td>
                                <td class="py-2.5 text-end whitespace-nowrap">
                                    <x-filament::badge
                                        :color="(float) $line->cost_uplift_percent > 50 ? 'danger' : ((float) $line->cost_uplift_percent > 30 ? 'warning' : 'success')"
                                        size="sm">
                                        +{{ number_format((float) $line->cost_uplift_percent, 1) }}%
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-xs" style="color: var(--erp-text-muted)">
                Bulky, low-value goods absorb far more freight than their value suggests —
                which is exactly what a flat percentage markup would hide.
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
