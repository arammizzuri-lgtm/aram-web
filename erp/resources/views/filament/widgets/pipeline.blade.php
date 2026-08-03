{{--
    The pipeline, read left to right as the business runs.

    Deliberately not a chart. Seven numbers do not need an axis, and each one
    here is a door — the count is the thing you read and the click is the thing
    you do, which a chart would make harder rather than easier.
--}}
@php
    $stages  = $this->stages();
    $busiest = $this->busiest();
    $total   = $this->total();
@endphp

<div>
    <x-erp.card
        title="What is on"
        :hint="$total === 1 ? 'One deal open. Click a stage to see it.' : $total . ' deals open. Click a stage to see them.'"
        flush
    >
        @if ($total === 0)
            <x-erp.empty title="Nothing open">
                Every deal is closed or cancelled. New ones appear here as soon as they are created.
            </x-erp.empty>
        @else
            <div class="grid gap-px" style="background: var(--erp-border); grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr))">
                @foreach ($stages as $stage)
                    @php
                        /* The bar is the count against the busiest stage, so the
                           shape of the queue is legible without reading a single
                           number. An empty stage still gets its column: the gap
                           in the run is itself information. */
                        $share = $stage['count'] > 0 ? max(6, round($stage['count'] / $busiest * 100)) : 0;
                        $isEmpty = $stage['count'] === 0;
                    @endphp

                    <a href="{{ $stage['url'] }}"
                       class="erp-transition block p-4 hover:bg-[var(--erp-bg-hover)]"
                       style="background: var(--erp-bg-surface)">
                        <div class="erp-label truncate">{{ $stage['label'] }}</div>

                        <div class="mt-1.5 erp-numeric text-start"
                             style="font-size: var(--text-figure);
                                    line-height: var(--text-figure--line-height);
                                    font-weight: 600;
                                    color: {{ $isEmpty ? 'var(--erp-text-muted)' : 'var(--erp-text-primary)' }}">
                            {{ $stage['count'] }}
                        </div>

                        <div class="mt-2 h-1 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                            @if ($share > 0)
                                <div class="h-full rounded-full"
                                     style="width: {{ $share }}%; background: var(--erp-series-1)"></div>
                            @endif
                        </div>

                        <div class="mt-2 erp-numeric text-start"
                             style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                            {{ $isEmpty ? '—' : $stage['value']->display() }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-erp.card>
</div>
