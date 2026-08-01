@php
    $alerts = $this->getAlerts();
    $tones = [
        'danger' => ['border' => 'var(--erp-status-critical)', 'icon' => 'heroicon-m-exclamation-circle'],
        'warning' => ['border' => 'var(--erp-status-warning)', 'icon' => 'heroicon-m-exclamation-triangle'],
    ];
@endphp

{{--
    The root element is unconditional: Livewire needs one persistent root node to
    diff against, and wrapping it in the @if made the component vanish from the
    DOM whenever there was nothing to report, which broke hydration.

    Nothing wrong still means nothing visible, so this band always carries meaning.
--}}
<div class="space-y-2">
    @if (filled($alerts))
        @foreach ($alerts as $alert)
            @php $tone = $tones[$alert['tone']] ?? $tones['warning']; @endphp

            <a href="{{ $alert['url'] }}"
               class="flex items-start gap-3 rounded-xl border p-4 transition hover:opacity-90"
               style="border-color: var(--erp-border); border-inline-start: 3px solid {{ $tone['border'] }}; background: var(--erp-bg-surface)">
                <x-filament::icon :icon="$tone['icon']" class="mt-0.5 h-5 w-5 shrink-0" style="color: {{ $tone['border'] }}" />

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold">{{ $alert['label'] }}</div>
                    <div class="mt-0.5 text-xs" style="color: var(--erp-text-secondary)">{{ $alert['detail'] }}</div>
                </div>

                <x-filament::icon icon="heroicon-m-chevron-right" class="mt-0.5 h-4 w-4 shrink-0"
                                  style="color: var(--erp-text-muted)" />
            </a>
        @endforeach
    @endif
</div>
