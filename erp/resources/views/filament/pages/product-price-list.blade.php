@php
    $section = $this->currentSection();
    $rows = $this->rows();
    $currency = $this->currency();
    $coverage = $this->coverage();
    $suppliers = $this->suppliers();
    $itemLabel = $section?->item_label ?? 'Product';
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="erp-label mb-1 block">Price list</label>
                <select wire:model.live="section" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @foreach ($this->sections() as $option)
                        <option value="{{ $option->code }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="erp-label mb-1 block">Supplier</label>
                <select wire:model.live="supplierId" class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                        style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
                    @forelse ($suppliers as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @empty
                        <option value="">No supplier has anything in this list yet</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label class="erp-label mb-1 block">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="{{ $itemLabel }} name…"
                       class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs"
             style="color: var(--erp-text-secondary)">
            <span>Prices in <strong>{{ $currency }}</strong></span>
            <span @style(['color: var(--erp-warning)' => $coverage['percent'] < 100])>
                <strong class="erp-numeric">{{ $coverage['priced'] }}</strong> of
                <strong class="erp-numeric">{{ $coverage['total'] }}</strong> sizes priced
            </span>
            @if (! $this->search)
                <button type="button" wire:click="expandAll" class="underline">Open all</button>
                <button type="button" wire:click="collapseAll" class="underline">Close all</button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        @if ($rows->isEmpty())
            <div class="py-10 text-center text-sm" style="color: var(--erp-text-secondary)">
                @if ($this->search)
                    Nothing here matches “{{ $this->search }}”.
                @else
                    Nothing in this list yet. Add products on the Products screen and
                    they will appear here, waiting for their prices.
                @endif
            </div>
        @else
            <div class="divide-y" style="border-color: var(--erp-border)">
                @foreach ($rows as $row)
                    @php
                        $product = $row['product'];
                        $sizes = $product->sizes;
                        $hasSizes = $sizes->isNotEmpty();
                        $open = $this->isExpanded($product->id);
                        $pricedCount = $sizes->filter->isPriced()->count();
                    @endphp

                    <div style="border-color: var(--erp-border)">
                        <div class="flex items-center gap-3 py-2"
                             style="padding-left: {{ $this->search ? 0 : $row['depth'] * 1.5 }}rem">

                            @if ($hasSizes)
                                <button type="button" wire:click="toggle({{ $product->id }})"
                                        class="flex items-center gap-2 text-left">
                                    <span class="inline-block w-3 text-xs"
                                          style="color: var(--erp-text-secondary)">{{ $open ? '▾' : '▸' }}</span>
                                    <span class="text-sm font-medium">{{ $product->name }}</span>
                                </button>
                            @else
                                {{-- A shelf: it holds other things and is never bought itself. --}}
                                <span class="inline-block w-3"></span>
                                <span class="text-sm" style="color: var(--erp-text-secondary)">
                                    {{ $product->name }}
                                </span>
                            @endif

                            @if ($hasSizes)
                                <span class="erp-numeric text-xs"
                                      @style([
                                          'color: var(--erp-text-secondary)' => $pricedCount === $sizes->count(),
                                          'color: var(--erp-warning)' => $pricedCount !== $sizes->count(),
                                      ])>
                                    {{ $pricedCount }}/{{ $sizes->count() }} priced
                                </span>
                            @endif

                            @if ($row['trail'])
                                <span class="text-xs" style="color: var(--erp-text-secondary)">
                                    {{ $row['trail'] }}
                                </span>
                            @endif
                        </div>

                        @if ($hasSizes && $open)
                            <div class="pb-3"
                                 style="padding-left: {{ ($this->search ? 0 : $row['depth'] * 1.5) + 1.5 }}rem">
                                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($sizes as $size)
                                        <label class="flex items-center gap-2">
                                            <span class="w-28 shrink-0 text-xs" style="color: var(--erp-text-secondary)">
                                                {{ $size->label }}
                                            </span>
                                            <input type="number" step="0.0001" min="0"
                                                   wire:model="prices.{{ $size->id }}"
                                                   placeholder="—"
                                                   class="fi-input erp-numeric w-full rounded-lg border px-2 py-1 text-sm"
                                                   style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between border-t pt-4"
                 style="border-color: var(--erp-border)">
                <p class="text-xs" style="color: var(--erp-text-secondary)">
                    An empty box means nobody has quoted that size yet — which is not the
                    same as quoting it at nothing.
                </p>

                <x-filament::button wire:click="savePrices" wire:loading.attr="disabled">
                    Save prices
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
