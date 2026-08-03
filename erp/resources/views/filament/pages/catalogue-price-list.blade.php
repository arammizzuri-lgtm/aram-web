@php
    $section = $this->currentSection();
    $items = $this->items();
    $breaks = $this->quantityBreaks();
    $currency = $this->currency();
    $coverage = $this->coverage();
    $suppliers = $this->suppliers();
    $fields = $section?->attributes() ?? [];
    $qty = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="erp-label mb-1 block">Section</label>
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
                        <option value="">No supplier carries this section yet</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label class="erp-label mb-1 block">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Code or name…"
                       class="fi-input w-full rounded-lg border px-3 py-2 text-sm"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs"
             style="color: var(--erp-text-secondary)">
            <span><strong class="erp-numeric">{{ $items->count() }}</strong>
                {{ str($section?->item_label ?? 'item')->lower()->plural($items->count()) }}</span>
            <span><strong class="erp-numeric">{{ count($breaks) }}</strong> quantity
                {{ str('break')->plural(count($breaks)) }}</span>
            <span>Priced <strong>{{ $section?->price_unit ?? 'per unit' }}</strong> in <strong>{{ $currency }}</strong></span>
            <span @style(['color: var(--erp-warning)' => $coverage['percent'] < 100])>
                <strong class="erp-numeric">{{ $coverage['priced'] }}</strong> of
                <strong class="erp-numeric">{{ $coverage['total'] }}</strong> priced
            </span>
        </div>

        <div class="mt-4 flex flex-wrap items-end gap-3 border-t pt-4" style="border-color: var(--erp-border)">
            <div>
                <label class="erp-label mb-1 block">Add a quantity break</label>
                <input type="number" min="2" step="1" wire:model="newBreak" wire:keydown.enter="addBreak"
                       placeholder="e.g. 10000"
                       class="fi-input w-40 rounded-lg border px-3 py-2 text-sm erp-numeric"
                       style="border-color: var(--erp-border); background: var(--erp-bg-surface)" />
            </div>
            <x-filament::button wire:click="addBreak" color="gray" size="sm">Add column</x-filament::button>
            <p class="text-xs" style="color: var(--erp-text-muted)">
                Opens an empty column so you can enter a tier this supplier has not quoted before.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ $section?->name }} catalogue</x-slot>
        <x-slot name="description">
            Prices are {{ $section?->price_unit ?? 'per unit' }}, with a column per quantity break —
            the largest applicable break wins at order time. Leave a cell empty where the
            supplier does not quote that break.
        </x-slot>

        @if ($items->isEmpty())
            <p class="py-8 text-center text-sm" style="color: var(--erp-text-muted)">
                @if ($suppliers->isEmpty())
                    Nothing has been added to {{ $section?->name }} yet.
                @else
                    Nothing matches that search.
                @endif
            </p>
        @else
            {{--
                The table sizes to its content and scrolls, rather than being
                squeezed into the panel width — a fabric's composition and five
                quantity breaks do not fit side by side on a laptop, and letting
                the browser compress them turned every code into a 3-line wrap.
                The identity column is pinned so you can still tell which row you
                are typing a price into once you have scrolled right.
            --}}
            <div class="-mx-3 overflow-x-auto px-3">
                <table class="w-max min-w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            <th class="sticky start-0 z-10 px-3 py-2 text-start font-semibold"
                                style="background: var(--erp-bg-surface)">{{ $section?->item_label }}</th>
                            {{-- Columns come from the section's own field definitions. --}}
                            @foreach ($fields as $field)
                                <th class="px-3 py-2 text-start font-semibold whitespace-nowrap">
                                    {{ $field['label'] }}
                                    @isset($field['unit'])
                                        <span style="color: var(--erp-text-muted)">({{ $field['unit'] }})</span>
                                    @endisset
                                </th>
                            @endforeach
                            <th class="px-3 py-2 text-end font-semibold">MOQ</th>
                            @foreach ($breaks as $break)
                                <th class="px-3 py-2 text-end font-semibold whitespace-nowrap">
                                    {{ $break <= 1 ? 'Base' : $qty($break) . '+' }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            @php $prices = $item->prices->keyBy(fn ($p) => (string) (float) $p->min_quantity); @endphp
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                {{-- Code above name: one pinned column instead of two squeezed ones. --}}
                                <td class="sticky start-0 z-10 px-3 py-1.5 whitespace-nowrap"
                                    style="background: var(--erp-bg-surface)">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $item->name }}</span>
                                        @if ($item->name_zh)
                                            <span class="text-xs" style="color: var(--erp-text-muted)">{{ $item->name_zh }}</span>
                                        @endif
                                        @if ($item->isStocked())
                                            <x-filament::badge size="sm" color="success" class="inline-flex">stocked</x-filament::badge>
                                        @endif
                                    </div>
                                    <div class="erp-identifier text-xs" style="color: var(--erp-text-muted)">{{ $item->code }}</div>
                                </td>
                                @foreach ($fields as $field)
                                    <td class="px-3 py-1.5 whitespace-nowrap"
                                        style="color: var(--erp-text-secondary)">
                                        {{ $item->attribute($field['key']) ?? '—' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-1.5 text-end erp-numeric">
                                    {{ $item->moq ? $qty((float) $item->moq) : '—' }}
                                </td>
                                @foreach ($breaks as $break)
                                    <td class="px-3 py-1 text-end">
                                        <input
                                            type="number" step="0.01" min="0" inputmode="decimal"
                                            value="{{ optional($prices->get((string) $break))->price ? rtrim(rtrim($prices->get((string) $break)->price, '0'), '.') : '' }}"
                                            wire:change="savePrice({{ $item->id }}, '{{ $break }}', $event.target.value)"
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
