{{-- A timeline of events: down the page, sideways (scrolls), or compact. --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'vertical';
    $events = array_values(array_filter((array) ($d['events'] ?? []), fn ($e) => filled($e['title_en'] ?? null) || filled($e['date_en'] ?? null)));
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_timeline_default');
@endphp
@if ($events)
    <section class="lm-sec lm-tl lm-tl--{{ $layout }} reveal" data-count="{{ count($events) }}">
        <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        <ol class="lm-tl__list">
            @foreach ($events as $ev)
                @php $text = Landmark::t($ev, 'text'); @endphp
                <li class="lm-tl__ev">
                    <span class="lm-tl__dot" aria-hidden="true"></span>
                    <span class="lm-tl__date"{{ text_dir(Landmark::digits(Landmark::t($ev, 'date'))) }}>{{ Landmark::digits(Landmark::t($ev, 'date')) }}</span>
                    <h3 class="lm-tl__title"{{ text_dir(Landmark::t($ev, 'title')) }}>{{ Landmark::t($ev, 'title') }}</h3>
                    @if (filled($text) && $layout !== 'compact')
                        <p class="lm-tl__text"{{ text_dir($text) }}>{{ $text }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
