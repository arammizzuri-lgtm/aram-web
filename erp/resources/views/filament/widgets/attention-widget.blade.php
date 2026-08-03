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

                {{-- The same card as everywhere else, with a colour bound down
                     one edge. Hover dims the whole row rather than tinting it:
                     these rows carry status colour already, and a hover tint
                     underneath it reads as a second status. --}}
                <a href="{{ $item['url'] }}"
                   class="erp-card erp-transition flex items-start gap-3 p-4 hover:bg-[var(--erp-bg-hover)]"
                   style="border-inline-start: 3px solid {{ $tone['border'] }}">

                    <x-filament::icon :icon="$tone['icon']" class="mt-0.5 h-5 w-5 shrink-0"
                                      style="color: {{ $tone['border'] }}" />

                    <div class="min-w-0">
                        <div class="font-medium" style="color: var(--erp-text-primary)">{{ $item['title'] }}</div>
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
