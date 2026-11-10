{{-- An annotated photograph: numbered points on the picture, each explained in the list beside it (landmark.js links the two). --}}
@php
    use App\Models\Landmark;
    $points = array_values(array_filter((array) ($d['points'] ?? []), fn ($p) => isset($p['x'], $p['y'])));
    $heading = Landmark::t($d, 'heading');
    $caption = Landmark::t($d, 'caption');
@endphp
@if (filled($d['file'] ?? null))
    <section class="lm-sec lm-anno reveal" data-count="{{ count($points) }}">
        @if (filled($heading))
            <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        @endif
        <div class="lm-anno__wrap">
            <div class="lm-anno__stage">
                @include('heritage.img', ['img' => $d, 'size' => 'lg', 'sizes' => '(min-width: 1100px) 60vw, 100vw', 'lightbox' => false, 'alt' => $l->tr('name')])
                @foreach ($points as $k => $p)
                    <button type="button" class="lm-anno__pt" style="left: {{ (float) $p['x'] }}%; top: {{ (float) $p['y'] }}%" data-point="{{ $k }}" aria-label="{{ Landmark::t($p, 'label') }}">
                        <span>{{ Landmark::digits($k + 1) }}</span>
                    </button>
                @endforeach
            </div>
            @if ($points)
                <ol class="lm-anno__list">
                    @foreach ($points as $k => $p)
                        <li class="lm-anno__item" data-point="{{ $k }}" tabindex="0">
                            <span class="lm-anno__n" aria-hidden="true">{{ Landmark::digits($k + 1) }}</span>
                            <span class="lm-anno__body">
                                <strong{{ text_dir(Landmark::t($p, 'label')) }}>{{ Landmark::t($p, 'label') }}</strong>
                                <span{{ text_dir(Landmark::t($p, 'text')) }}>{{ Landmark::t($p, 'text') }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
        @if (filled($caption) || filled($d['credit'] ?? null))
            <p class="lm-fig__cap">
                @if (filled($caption))<span class="lm-fig__text"{{ text_dir($caption) }}>{{ $caption }}</span>@endif
                @include('heritage.credit', ['img' => $d])
            </p>
        @endif
    </section>
@endif
