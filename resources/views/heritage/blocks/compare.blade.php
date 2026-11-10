{{-- Before and after: two photographs of the same view, a handle to drag between them (landmark.js). --}}
@php
    use App\Models\Landmark;
    $before = (array) ($d['before'] ?? []);
    $after = (array) ($d['after'] ?? []);
    $heading = Landmark::t($d, 'heading');
    $caption = Landmark::t($d, 'caption');
    $lb = Landmark::t($d, 'label_before') ?: bival('landmarks_before');
    $la = Landmark::t($d, 'label_after') ?: bival('landmarks_after');
    $both = filled($before['file'] ?? null) && filled($after['file'] ?? null);
    $one = $both ? null : (filled($before['file'] ?? null) ? $before : (filled($after['file'] ?? null) ? $after : null));
@endphp
@if ($both)
    <section class="lm-sec lm-cmp reveal">
        @if (filled($heading))
            <h2 class="lm-h2" dir="auto">{{ $heading }}</h2>
        @endif
        <div class="lm-cmp__stage" style="--pos: 50%" @if (($after['w'] ?? 0) && ($after['h'] ?? 0)) data-ar="{{ $after['w'] }} / {{ $after['h'] }}" @endif>
            <div class="lm-cmp__after">
                @include('heritage.img', ['img' => $after, 'size' => 'lg', 'sizes' => '(min-width: 1400px) 1320px, 100vw', 'lightbox' => false, 'alt' => $la])
            </div>
            <div class="lm-cmp__before" aria-hidden="true">
                @include('heritage.img', ['img' => $before, 'size' => 'lg', 'sizes' => '(min-width: 1400px) 1320px, 100vw', 'lightbox' => false, 'alt' => $lb])
            </div>
            <span class="lm-cmp__label lm-cmp__label--before" dir="auto">{{ $lb }}</span>
            <span class="lm-cmp__label lm-cmp__label--after" dir="auto">{{ $la }}</span>
            <span class="lm-cmp__handle" aria-hidden="true"></span>
            <input type="range" class="lm-cmp__range" min="0" max="100" value="50" aria-label="{{ bival('landmarks_compare_hint') }}">
        </div>
        <p class="lm-fig__cap">
            @if (filled($caption))<span class="lm-fig__text" dir="auto">{{ $caption }}</span>@endif
            <span class="lm-credit">{{ bival('landmarks_before') }}: {{ $before['credit'] ?? '' }} · {{ bival('landmarks_after') }}: {{ $after['credit'] ?? '' }}</span>
        </p>
    </section>
@elseif ($one)
    @include('heritage.blocks.image', ['d' => $one + ['layout' => 'wide', 'caption_en' => $caption]])
@endif
