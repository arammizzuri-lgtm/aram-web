{{--
    A chart in its frame: the drawing, the exact figures behind it, and a way to
    read one mark without guessing it off an axis.

    Every chart on the dashboard needs the same three things, so they live here
    and each chart only draws:

    <x-erp.chart title="Profit by customer" :hint="$hint">
        <x-slot name="controls"><x-erp.range-picker :value="$this->widgetRange" /></x-slot>
        <x-slot name="figure">…the headline number, under the controls…</x-slot>
        …the marks…
        <x-slot name="table"><x-erp.data-table :columns="…" :rows="…" /></x-slot>
    </x-erp.chart>

    A mark asks for its hover card with `show($event, ['heading', 'line', …])`
    on mouseenter and focus, and `hide()` on leave and blur. The card is pinned
    to the element inside the mark marked `data-anchor` (the bar itself) when
    there is one, so it sits on the bar rather than at the top of the row.
--}}
@props(['title', 'hint' => null])

<x-erp.card
    :title="$title"
    :hint="$hint"
    flush
    x-data="{
        view: 'chart',
        tip: null,
        show(event, lines) {
            const anchor = event.currentTarget.querySelector('[data-anchor]') ?? event.currentTarget;
            const mark = anchor.getBoundingClientRect();
            const plot = this.$refs.plot.getBoundingClientRect();
            const top = mark.top - plot.top;
            const below = top < 84;

            this.tip = {
                x: Math.min(Math.max(mark.left - plot.left + mark.width / 2, 80), plot.width - 80),
                y: below ? top + mark.height : top,
                below,
                lines,
            };
        },
        hide() { this.tip = null },
    }"
>
    <x-slot name="head">
        <div class="flex flex-col items-end gap-2">
            <div class="flex flex-wrap items-center justify-end gap-2">
                {{ $controls ?? '' }}

                <div class="erp-toggle" role="group" aria-label="Show as">
                    <button type="button" x-on:click="view = 'chart'" x-bind:aria-pressed="(view === 'chart').toString()">Chart</button>
                    <button type="button" x-on:click="view = 'table'; hide()" x-bind:aria-pressed="(view === 'table').toString()">Table</button>
                </div>
            </div>

            {{ $figure ?? '' }}
        </div>
    </x-slot>

    <div x-ref="plot" class="relative" x-show="view === 'chart'">
        {{ $slot }}

        <div class="erp-tip" x-cloak x-show="tip"
             x-bind:data-below="!! (tip && tip.below)"
             x-bind:style="tip ? `left: ${tip.x}px; top: ${tip.y}px` : ''">
            <template x-for="(line, index) in (tip ? tip.lines : [])" x-bind:key="index">
                <div x-text="line"></div>
            </template>
        </div>
    </div>

    <div x-cloak x-show="view === 'table'">
        {{ $table ?? '' }}
    </div>
</x-erp.card>
