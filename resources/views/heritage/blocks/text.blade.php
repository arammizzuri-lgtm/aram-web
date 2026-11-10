{{-- A text section: a heading and prose, in one of a few layouts. --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'standard';
    $heading = Landmark::t($d, 'heading');
    $body = Landmark::rich(Landmark::t($d, 'body'));
@endphp
@if ($body !== '' || filled($heading))
    <section class="lm-sec lm-text lm-text--{{ $layout }} reveal">
        @if (filled($heading))
            <h2 class="lm-h2" dir="auto">{{ $heading }}</h2>
        @endif
        <div class="lm-prose" dir="auto">{!! $body !!}</div>
    </section>
@endif
