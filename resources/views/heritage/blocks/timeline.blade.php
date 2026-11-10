{{-- A timeline of events: down the page, sideways (scrolls), or compact. --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'vertical';
    $events = array_values(array_filter((array) ($d['events'] ?? []), fn ($e) => filled($e['title_en'] ?? null) || filled($e['date_en'] ?? null)));
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_timeline_default');
@endphp
@if ($events)
    <section class="lm-sec lm-tl lm-tl--{{ $layout }} reveal" data-count="{{ count($events) }}">
        <h2 class="lm-h2" dir="auto">{{ $heading }}</h2>
        <ol class="lm-tl__list">
            @foreach ($events as $ev)
                @php $text = Landmark::t($ev, 'text'); @endphp
                <li class="lm-tl__ev">
                    <span class="lm-tl__dot" aria-hidden="true"></span>
                    <span class="lm-tl__date" dir="auto">{{ Landmark::digits(Landmark::t($ev, 'date')) }}</span>
                    <h3 class="lm-tl__title" dir="auto">{{ Landmark::t($ev, 'title') }}</h3>
                    @if (filled($text) && $layout !== 'compact')
                        <p class="lm-tl__text" dir="auto">{{ $text }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
