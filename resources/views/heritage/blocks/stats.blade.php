{{-- Figures: a row of large numbers, a gold band, or a list with their notes. The numbers count up as they scroll in (landmark.js). --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'row';
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['value'] ?? null)));
    $heading = Landmark::t($d, 'heading');
@endphp
@if ($items)
    <section class="lm-sec lm-stats lm-stats--{{ $layout }} reveal" data-count="{{ count($items) }}">
        @if (filled($heading))
            <h2 class="lm-h2" dir="auto">{{ $heading }}</h2>
        @endif
        <dl class="lm-stats__list">
            @foreach ($items as $it)
                @php $unit = Landmark::t($it, 'unit'); $label = Landmark::t($it, 'label'); $note = Landmark::t($it, 'note'); @endphp
                <div class="lm-stat">
                    <dt class="lm-stat__label" dir="auto">{{ $label }}</dt>
                    <dd>
                        <span class="lm-stat__value" data-count>{{ Landmark::digits($it['value']) }}</span>
                        @if (filled($unit))<span class="lm-stat__unit" dir="auto">{{ $unit }}</span>@endif
                        @if (filled($note))<small class="lm-stat__note" dir="auto">{{ $note }}</small>@endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif
