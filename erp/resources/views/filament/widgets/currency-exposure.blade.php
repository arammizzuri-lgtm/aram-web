{{--
    The trade by currency: one bar for what was sold, one for what was bought,
    each divided by the money it changed hands in, with the amounts in their own
    currency written underneath.
--}}
@php $exposure = $this->exposure(); @endphp

<div>
    <x-erp.chart
        title="Currency exposure"
        :hint="$this->activeLabel() . '. What changed hands in each currency — its own amount, and in dollars at the rate stamped on the deal. Goods only.'"
    >
        <x-slot name="controls">
            <x-erp.range-picker :value="$this->widgetRange" />
        </x-slot>

        @if (! $exposure['anything'])
            <x-erp.empty title="Nothing sold or bought in this window">
                Currencies appear here once deals have lines priced on them.
            </x-erp.empty>
        @else
            <div class="flex flex-col gap-5 px-5 pt-4 pb-3">
                @foreach ($exposure['sides'] as $side)
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="erp-label">{{ $side['heading'] }}</span>
                            <span class="erp-numeric text-sm font-semibold" style="color: var(--erp-text-primary)">{{ $side['total'] }}</span>
                        </div>

                        @if ($side['segments'] === [])
                            <p class="erp-stat-hint">Nothing in this window.</p>
                        @else
                            <div class="mt-2 flex h-7 w-full overflow-hidden rounded-lg" style="gap: 2px">
                                @foreach ($side['segments'] as $segment)
                                    <span class="erp-mark erp-fill flex h-full items-center justify-center overflow-hidden"
                                          tabindex="0"
                                          data-anchor
                                          style="flex: 0 0 {{ $segment['width'] }}%; --bar: {{ $segment['colour'] }}"
                                          aria-label="{{ implode('. ', $segment['tip']) }}"
                                          x-on:mouseenter="show($event, @js($segment['tip']))"
                                          x-on:mouseleave="hide()"
                                          x-on:focus="show($event, @js($segment['tip']))"
                                          x-on:blur="hide()">
                                        @if ($segment['label'])
                                            {{-- Dark ink on the fill: it clears 4.9:1 on all three, where white would not. --}}
                                            <span class="whitespace-nowrap px-2 text-xs font-semibold" style="color: var(--erp-bg-page)">{{ $segment['label'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>

                            <p class="erp-stat-hint erp-numeric mt-1.5" style="text-align: start">{{ $side['amounts'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t px-5 py-3 erp-stat-hint"
                 style="border-color: var(--erp-border); margin-top: 0">
                @foreach ($exposure['legend'] as [$name, $colour])
                    <span class="inline-flex items-center gap-1.5"><span class="erp-swatch" style="--bar: {{ $colour }}"></span>{{ $name }}</span>
                @endforeach
            </div>
        @endif

        <x-slot name="table">
            <x-erp.data-table
                :columns="[
                    ['label' => 'Side'],
                    ['label' => 'Currency'],
                    ['label' => 'Amount', 'numeric' => true],
                    ['label' => 'In USD', 'numeric' => true],
                    ['label' => 'Share', 'numeric' => true],
                ]"
                :rows="$exposure['table']"
                empty="Nothing sold or bought in this window."
            />
        </x-slot>
    </x-erp.chart>
</div>
