@php
    $import = $this->getImport();
    $money = fn ($v) => $v === null ? '—' : '$' . number_format((float) $v, 2);
@endphp

<x-filament-panels::page>
    @if (! $import)
        <x-filament::section>
            <x-slot name="heading">Upload</x-slot>
            <x-slot name="description">
                Supplier spreadsheets are untrusted input. Nothing is written to the catalogue
                until you have seen every proposed change.
            </x-slot>

            {{ $this->form }}

            {{--
                `footer`, not `footerActions`.

                A Blade component silently ignores a slot it does not declare,
                and Filament's section declares `footer`. So this button — the
                only way to start an import — was dropped on the floor without a
                word, and the screen offered an upload and no way to use it.
            --}}
            <x-slot name="footer">
                <x-filament::button wire:click="analyse" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="analyse">Analyse price list</span>
                    <span wire:loading wire:target="analyse">Reading…</span>
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    @else
        {{-- What the file would do, before it does any of it. Colour lands only
             on the two that want looking at. --}}
        <x-erp.card flush>
            <div class="erp-figures">
                @foreach ([
                    ['New products', $import->rows_new, null],
                    ['Price changes', $import->rows_updated, null],
                    ['Unchanged', $import->rows_unchanged, null],
                    ['Errors', $import->rows_error, $import->rows_error > 0 ? 'critical' : null],
                    ['Needs a look', $import->suspiciousRows(), $import->suspiciousRows() > 0 ? 'warning' : null],
                ] as [$label, $value, $tone])
                    <x-erp.figure :label="$label" :value="number_format($value)" :tone="$tone" />
                @endforeach
            </div>
        </x-erp.card>

        @if ($import->avg_change_percent !== null)
            <div class="text-sm" style="color: var(--erp-text-secondary)">
                Average price movement across the changed lines:
                <strong class="erp-numeric">{{ (float) $import->avg_change_percent > 0 ? '+' : '' }}{{ $import->avg_change_percent }}%</strong>
            </div>
        @endif

        <x-filament::section>
            <x-slot name="heading">
                {{ $import->status === 'committed' ? 'Committed changes' : 'Review before committing' }}
            </x-slot>
            <x-slot name="description">
                Untick anything you do not want applied. Rows with a warning are pre-unticked —
                a move of more than 50% is usually a wrong column or a unit mix-up, not a real price.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color: var(--erp-border)">
                            <th class="w-10 py-2"></th>
                            <th class="py-2 text-start font-semibold">Supplier SKU</th>
                            <th class="py-2 text-start font-semibold">Product</th>
                            <th class="py-2 text-end font-semibold">Old</th>
                            <th class="py-2 text-end font-semibold">New</th>
                            <th class="py-2 text-end font-semibold">Change</th>
                            <th class="py-2 text-center font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($import->rows()->orderByRaw("case action when 'error' then 0 when 'create' then 1 when 'update_price' then 2 else 3 end")->limit(200)->get() as $row)
                            <tr class="border-b" style="border-color: var(--erp-border)">
                                <td class="py-2">
                                    @if (in_array($row->action, ['create', 'update_price'], true) && $import->status !== 'committed')
                                        <input type="checkbox"
                                               wire:click="toggleRow({{ $row->id }})"
                                               @checked($row->is_approved)
                                               class="rounded" />
                                    @endif
                                </td>
                                <td class="py-2 erp-identifier">{{ $row->supplier_sku ?? '—' }}</td>
                                <td class="py-2">
                                    <div>{{ $row->name ?? '—' }}</div>
                                    @if ($row->isSuspicious())
                                        <div class="text-xs" style="color: var(--erp-critical-text)">
                                            {{ $row->errors[0] ?? '' }}
                                        </div>
                                    @elseif ($row->action === 'error')
                                        <div class="text-xs" style="color: var(--erp-critical-text)">
                                            {{ $row->errors[0] ?? 'Could not read this row' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2 text-end erp-numeric">{{ $money($row->old_price) }}</td>
                                <td class="py-2 text-end erp-numeric font-medium">{{ $money($row->new_price) }}</td>
                                <td class="py-2 text-end erp-numeric">
                                    @if ($row->change_percent !== null && $row->action === 'update_price')
                                        <span style="color: {{ (float) $row->change_percent > 0 ? 'var(--erp-serious-text)' : 'var(--erp-good-text)' }}">
                                            {{ (float) $row->change_percent > 0 ? '+' : '' }}{{ $row->change_percent }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 text-center">
                                    <x-filament::badge size="sm" :color="match ($row->action) {
                                        'create' => 'info',
                                        'update_price' => 'warning',
                                        'error' => 'danger',
                                        default => 'gray',
                                    }">
                                        {{ str($row->action)->replace('_', ' ') }}
                                    </x-filament::badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center" style="color: var(--erp-text-muted)">
                                Nothing to show.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($import->rows()->count() > 200)
                <p class="mt-3 text-xs" style="color: var(--erp-text-muted)">
                    Showing the first 200 of {{ number_format($import->rows()->count()) }} rows.
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
