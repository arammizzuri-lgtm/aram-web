{{--
    A ranking: who or what earned the most, with what it was made of.

    Each row is the name and its figure, a bar, and one line of detail. The bar
    is drawn in layers, back to front, each a share of the widest figure in the
    list — so a row can show profit inside revenue, or a margin with the slice
    the exchange house took marked at its end, with one component.

    The optional strip on top is the part-to-whole: how the total divides, with
    the one share that matters picked out and the rest in neutral, because the
    story is usually "one customer is most of it" and not eight colours.

    Rows and strip segments carry a hover card (`tip`) and, where there is one,
    a link to the record behind them. Shaped by the widget:

        row:     name, url?, valueText, negative, detail, track[[width, colour]], tip[]
        strip:   segments[[width, colour, tip[]]], caption
        legend:  [[label, colour]]
--}}
@props([
    'rows',
    'strip' => null,
    'legend' => [],
    'emptyTitle' => 'Nothing in this window',
])

@if (count($rows) === 0)
    <x-erp.empty :title="$emptyTitle">{{ $slot }}</x-erp.empty>
@else
    @if ($strip)
        <div class="px-5 pt-4 pb-3">
            <div class="flex h-2.5 w-full overflow-hidden rounded-full" style="gap: 2px" role="img" aria-label="{{ $strip['caption'] }}">
                @foreach ($strip['segments'] as $segment)
                    <span class="erp-mark erp-fill block h-full"
                          tabindex="0"
                          style="flex: 0 0 {{ $segment['width'] }}%; --bar: {{ $segment['colour'] }}"
                          aria-label="{{ implode('. ', $segment['tip']) }}"
                          x-on:mouseenter="show($event, @js($segment['tip']))"
                          x-on:mouseleave="hide()"
                          x-on:focus="show($event, @js($segment['tip']))"
                          x-on:blur="hide()"></span>
                @endforeach
            </div>
            <p class="erp-stat-hint mt-2">{{ $strip['caption'] }}</p>
        </div>
    @endif

    <ol>
        @foreach ($rows as $index => $row)
            @php $tag = ($row['url'] ?? null) ? 'a' : 'div'; @endphp

            <li class="border-t" style="border-color: var(--erp-border)">
                <{{ $tag }}
                    @if ($row['url'] ?? null) href="{{ $row['url'] }}" @else tabindex="0" @endif
                    class="erp-mark erp-transition block px-5 py-3 hover:bg-[var(--erp-bg-hover)]"
                    aria-label="{{ implode('. ', $row['tip']) }}"
                    x-on:mouseenter="show($event, @js($row['tip']))"
                    x-on:mouseleave="hide()"
                    x-on:focus="show($event, @js($row['tip']))"
                    x-on:blur="hide()"
                >
                    <div class="flex items-baseline gap-3">
                        {{-- The rank, because a list that is ordered should say so. --}}
                        <span class="erp-numeric w-4 shrink-0 text-center"
                              style="font-size: var(--text-hint); color: var(--erp-text-muted)">{{ $index + 1 }}</span>

                        {{-- The whole name, wrapping if it must: a supplier's Chinese
                             name cut off after four characters is not a name. --}}
                        <span class="min-w-0 flex-1 text-sm font-medium" style="color: var(--erp-text-primary)">{{ $row['name'] }}</span>

                        <span @class(['erp-numeric shrink-0 text-sm font-semibold', 'erp-critical' => $row['negative']])
                              @style(['color: var(--erp-text-primary)' => ! $row['negative']])>{{ $row['valueText'] }}</span>
                    </div>

                    <div class="mt-2 ps-7">
                        <div class="erp-track" data-anchor>
                            @foreach ($row['track'] as $layer)
                                <span class="erp-fill" style="width: {{ $layer['width'] }}%; --bar: {{ $layer['colour'] }}"></span>
                            @endforeach
                        </div>
                        <p class="erp-stat-hint mt-1.5">{{ $row['detail'] }}</p>
                    </div>
                </{{ $tag }}>
            </li>
        @endforeach
    </ol>

    @if ($legend)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t px-5 py-3 erp-stat-hint"
             style="border-color: var(--erp-border); margin-top: 0">
            @foreach ($legend as [$name, $colour])
                <span class="inline-flex items-center gap-1.5"><span class="erp-swatch" style="--bar: {{ $colour }}"></span>{{ $name }}</span>
            @endforeach
        </div>
    @endif
@endif
