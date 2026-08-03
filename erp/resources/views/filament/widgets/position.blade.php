{{--
    Two groups, because they answer different questions.

    The top row happened over a window. The bottom row is true right now and
    belongs to no window at all — which is why they cannot share a heading, and
    why the tiles this replaces were quietly lying under "Last 30 days".
--}}
@php $position = $this->position(); @endphp

<div>
    <x-erp.card flush>
        <div class="erp-card-head">
            <h2 class="erp-card-title">Last {{ $position['window'] }} days</h2>
            <p class="erp-card-hint">What the business did across the window.</p>
        </div>

        <div class="erp-figures">
            @foreach ($position['flows'] as $figure)
                <x-erp.figure
                    :label="$figure['label']"
                    :value="$figure['value']"
                    :hint="$figure['hint'] ?? null"
                    :lead="$figure['lead'] ?? false"
                    :tone="$figure['tone'] ?? null"
                />
            @endforeach
        </div>

        <div class="erp-card-head" style="border-top: 1px solid var(--erp-border)">
            <h2 class="erp-card-title">Right now</h2>
            <p class="erp-card-hint">Balances, true as of this moment — not a thirty-day figure.</p>
        </div>

        <div class="erp-figures">
            @foreach ($position['balances'] as $figure)
                <x-erp.figure
                    :label="$figure['label']"
                    :value="$figure['value']"
                    :hint="$figure['hint'] ?? null"
                    :tone="$figure['tone'] ?? null"
                />
            @endforeach
        </div>
    </x-erp.card>
</div>
