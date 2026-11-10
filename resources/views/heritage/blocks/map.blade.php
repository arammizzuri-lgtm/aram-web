{{-- Where it is: a few words, the place and part of Kurdistan, directions — and the map of Greater Kurdistan with this one pin (heritage.js). --}}
@php
    use App\Models\Landmark;
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_where');
    $text = Landmark::t($d, 'text');
    $mapPins = $l->lat !== null ? [['n' => '', 'lat' => $l->lat, 'lng' => $l->lng, 'name' => $l->tr('name'), 'unesco' => $l->unesco]] : [];
@endphp
<section class="lm-sec lm-mapsec reveal">
    <div class="lm-mapsec__text">
        <h2 class="lm-h2" dir="auto">{{ $heading }}</h2>
        @if (filled($text))
            <p class="lm-mapsec__words" dir="auto">{{ $text }}</p>
        @endif
        <dl class="lm-facts">
            <div><dt>{{ bival('landmarks_place') }}</dt><dd dir="auto">{{ $l->tr('place') }}</dd></div>
            <div><dt>{{ bival('landmarks_region') }}</dt><dd>{{ Landmark::regionName($l->region) }}</dd></div>
            <div><dt>{{ bival('landmarks_period') }}</dt><dd dir="auto">{{ $l->tr('period') }}</dd></div>
            <div><dt>{{ bival('landmarks_era') }}</dt><dd>{{ Landmark::eraName($l->era) }}</dd></div>
        </dl>
        @if ($l->lat !== null)
            <a class="lm-go" href="https://www.google.com/maps/search/?api=1&query={{ $l->lat }},{{ $l->lng }}" target="_blank" rel="noopener">{{ bival('landmarks_directions') }} <span aria-hidden="true">↗</span></a>
        @endif
    </div>
    @if (($tiles ?? null) && $l->lat !== null)
        <div class="hr-map hr-map--single" id="hrMap" data-single="1" data-tiles="{{ $tiles }}"
             data-pins='@json($mapPins)'
             data-outline="{{ asset('kurdistan-data.js') }}?v={{ filemtime(public_path('kurdistan-data.js')) }}" aria-hidden="true"></div>
    @endif
</section>
