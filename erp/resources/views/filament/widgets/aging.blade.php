{{--
    How old the money is, both ways.

    Two groups that read the same way — oldest on the right, because that is the
    column that becomes a loss. Balances, true right now: nothing here moves with
    the time filter above it.
--}}
@php $aging = $this->aging(); @endphp

<div>
    <x-erp.card flush>
        <div class="erp-card-head">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="erp-card-title">Owed to you</h2>
                    <p class="erp-card-hint">By how long it has been owed, oldest on the right.</p>
                </div>
                <div class="shrink-0 text-end">
                    <div class="erp-label">Total</div>
                    <div class="erp-numeric">{{ $aging['receivablesTotal'] }}</div>
                </div>
            </div>
        </div>

        <div class="erp-figures">
            @foreach ($aging['receivables'] as $bucket)
                <x-erp.figure
                    :label="$bucket['label']"
                    :value="$bucket['value']"
                    :tone="$bucket['tone'] ?? null"
                />
            @endforeach
        </div>

        @if ($aging['payables'] !== null)
            <div class="erp-card-head" style="border-top: 1px solid var(--erp-border)">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="erp-card-title">You owe suppliers</h2>
                        <p class="erp-card-hint">By how long it has been owed, from the order date.</p>
                    </div>
                    <div class="shrink-0 text-end">
                        <div class="erp-label">Total</div>
                        <div class="erp-numeric">{{ $aging['payablesTotal'] }}</div>
                    </div>
                </div>
            </div>

            <div class="erp-figures">
                @foreach ($aging['payables'] as $bucket)
                    <x-erp.figure
                        :label="$bucket['label']"
                        :value="$bucket['value']"
                        :tone="$bucket['tone'] ?? null"
                    />
                @endforeach
            </div>
        @endif
    </x-erp.card>
</div>
