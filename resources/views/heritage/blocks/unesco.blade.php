{{-- The UNESCO panel: the property's name, the year and criteria of its inscription, why, and the listing. --}}
@php
    use App\Models\Landmark;
    $text = Landmark::rich(Landmark::t($d, 'text'));
@endphp
@if ($l->unesco)
    <section class="lm-sec lm-unesco reveal">
        <div class="lm-unesco__badge">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8h18zM5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/></svg>
            <span>{{ bival('landmarks_unesco') }}</span>
        </div>
        <div class="lm-unesco__body">
            <h2 class="lm-h2" dir="auto">{{ $l->unesco_name ?: $l->tr('name') }}</h2>
            @if ($l->unesco_year || $l->unesco_criteria)
                <p class="lm-unesco__meta">
                    @if ($l->unesco_year){{ str_replace('{y}', Landmark::digits($l->unesco_year), bival('landmarks_unesco_inscribed')) }}@endif
                    @if ($l->unesco_year && $l->unesco_criteria) · @endif
                    @if ($l->unesco_criteria){{ str_replace('{c}', $l->unesco_criteria, bival('landmarks_unesco_criteria')) }}@endif
                </p>
            @endif
            @if ($text !== '')
                <div class="lm-prose" dir="auto">{!! $text !!}</div>
            @endif
            @if ($l->unesco_ref)
                <a class="lm-go" href="https://whc.unesco.org/en/list/{{ $l->unesco_ref }}/" target="_blank" rel="noopener">{{ bival('landmarks_unesco_link') }} <span aria-hidden="true">↗</span></a>
            @endif
        </div>
    </section>
@endif
