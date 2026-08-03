{{--
    A card.

    Nine views were writing `rounded-xl border` and inlining the border and
    background colours by hand — the same rule Filament's own sections use,
    restated nine times and free to drift nine ways. This is that card with a
    name, so a page written in plain Blade is made of the same material as a
    page built out of Filament sections.

    <x-erp.card title="Statement" hint="Newest first.">
        …
    </x-erp.card>

    `flush` for content that draws its own edges — a table, a list of rows —
    where padding would leave a margin the rules do not reach across.
--}}
@props([
    'title' => null,
    'hint' => null,
    'flush' => false,
])

<section {{ $attributes->class('erp-card') }}>
    @if ($title || $hint || isset($head))
        <div class="erp-card-head">
            <div class="flex items-start justify-between gap-4">
                <div>
                    @if ($title)
                        <h2 class="erp-card-title">{{ $title }}</h2>
                    @endif

                    @if ($hint)
                        <p class="erp-card-hint">{{ $hint }}</p>
                    @endif
                </div>

                @isset($head)
                    <div class="shrink-0">{{ $head }}</div>
                @endisset
            </div>
        </div>
    @endif

    @if ($flush)
        {{ $slot }}
    @else
        <div class="erp-card-body">{{ $slot }}</div>
    @endif
</section>
