{{--
    The root <div> is unconditional on purpose.

    Livewire needs a stable root element to hydrate against. Wrapping the whole
    widget in @if means it vanishes from the DOM the moment there is nothing to
    show, and the next update fails with "snapshot missing on component".
--}}
<div>
    @php $items = $this->items(); @endphp

    @if ($items->isNotEmpty())
        <div class="space-y-2">
            @foreach ($items as $item)
                @php
                    $tone = match ($item['tone']) {
                        'danger' => ['border' => 'var(--erp-critical)', 'icon' => 'heroicon-o-exclamation-triangle'],
                        'warning' => ['border' => 'var(--erp-warning)', 'icon' => 'heroicon-o-exclamation-circle'],
                        // --erp-accent does not exist; this is the validated
                        // blue that clears contrast on both surfaces.
                        default => ['border' => 'var(--erp-diverging-pos)', 'icon' => 'heroicon-o-information-circle'],
                    };
                @endphp

                <a href="{{ $item['url'] }}"
                   class="flex items-start gap-3 rounded-xl border p-4 transition hover:opacity-80"
                   style="border-color: var(--erp-border);
                          border-inline-start: 3px solid {{ $tone['border'] }};
                          background: var(--erp-bg-surface)">

                    <x-filament::icon :icon="$tone['icon']" class="mt-0.5 h-5 w-5 shrink-0"
                                      style="color: {{ $tone['border'] }}" />

                    <div class="min-w-0">
                        <div class="font-medium">{{ $item['title'] }}</div>
                        <div class="text-sm" style="color: var(--erp-text-secondary)">{{ $item['body'] }}</div>
                    </div>

                    <x-filament::icon icon="heroicon-o-chevron-right"
                                      class="ms-auto mt-0.5 h-4 w-4 shrink-0"
                                      style="color: var(--erp-text-muted)" />
                </a>
            @endforeach
        </div>
    @endif
</div>
