@php
    $sizes = $this->sizes();
    $crystals = $this->crystals();
    $currency = $this->currency();
    $coverage = $this->coverage();
    $suppliers = $this->suppliers();
@endphp

<x-filament-panels::page>
    {{-- Supplier first: everything below belongs to whoever is selected here. --}}
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                       style="color: var(--erp-text-muted)">Supplier</label>
                <select wire:model.live="supplierId" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @forelse ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @empty
                        <option value="">No supplier has a crystal catalogue yet</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                       style="color: var(--erp-text-muted)">Finish</label>
                <select wire:model.live="finish" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @foreach ($this->finishOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                       style="color: var(--erp-text-muted)">Order by</label>
                <div class="flex gap-2">
                    <select wire:model.live="sort" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                            style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                        @foreach ($this->sortOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    {{-- Sorting the view is temporary; this makes it the saved order. --}}
                    <x-filament::button wire:click="applySortPermanently" color="gray" size="sm"
                                        :disabled="$this->sort === 'catalogue'"
                                        title="Save this order as the catalogue order">
                        Save order
                    </x-filament::button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide"
                       style="color: var(--erp-text-muted)">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Code or colour name — P01, Siam, AB…"
                       class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs"
                 style="color: var(--erp-text-secondary)">
                <span><strong class="erp-numeric">{{ $crystals->count() }}</strong> colours shown</span>
                <span>
                    <strong class="erp-numeric">{{ $sizes->count() }}</strong> sizes
                    <span style="color: var(--erp-text-muted)">({{ $this->quotedSizeCount() }} quoted)</span>
                </span>
                <span>Prices in <strong>{{ $currency }}</strong></span>
                <span>
                    Coverage
                    <strong class="erp-numeric">{{ $coverage['percent'] }}%</strong>
                    ({{ number_format($coverage['priced']) }} of {{ number_format($coverage['total']) }} cells)
                </span>
            </div>

            {{-- Kept up here rather than under the grid: with 90 rows on screen,
                 a footer button means scrolling the whole catalogue to save. --}}
            @if ($crystals->isNotEmpty())
                <x-filament::button wire:click="savePrices" wire:loading.attr="disabled" size="sm">
                    <span wire:loading.remove wire:target="savePrices">Save prices</span>
                    <span wire:loading wire:target="savePrices">Saving…</span>
                </x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Catalogue</x-slot>
        <x-slot name="description">
            Every cell is one supplier, one colour, one size. Leave a cell empty where the
            supplier does not offer that size — an empty cell is not a price of zero.
        </x-slot>

        @if ($crystals->isEmpty())
            <p class="py-8 text-center text-sm" style="color: var(--erp-text-muted)">
                @if ($suppliers->isEmpty())
                    No supplier has a crystal catalogue yet.
                @else
                    Nothing matches that search.
                @endif
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            <th class="sticky start-0 z-10 py-2 text-start font-semibold"
                                style="background: var(--erp-bg-surface)">Code</th>
                            <th class="py-2 text-start font-semibold">Colour</th>
                            @foreach ($sizes as $size)
                                <th class="py-2 text-end font-semibold whitespace-nowrap">{{ $size->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($crystals as $crystal)
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                <td class="sticky start-0 z-10 py-1.5 erp-identifier"
                                    style="background: var(--erp-bg-surface)">{{ $crystal->crystal_code }}</td>
                                <td class="py-1.5">
                                    <span class="font-medium">{{ $crystal->crystal_name }}</span>
                                    @if ($crystal->finish !== 'plain')
                                        <x-filament::badge size="sm" color="gray" class="ms-2 inline-flex">
                                            {{ $crystal->finish === 'ab' ? 'AB' : 'effect' }}
                                        </x-filament::badge>
                                    @endif
                                </td>
                                @foreach ($sizes as $size)
                                    <td class="py-1 text-end">
                                        <input
                                            type="number" step="0.01" min="0" inputmode="decimal"
                                            wire:model="prices.{{ $crystal->id }}-{{ $size->id }}"
                                            placeholder="—"
                                            class="w-24 rounded-md border px-2 py-1 text-end text-sm erp-numeric"
                                            style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
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
