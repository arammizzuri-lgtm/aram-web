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
            <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        @endif
        <div class="lm-prose"{{ text_dir(strip_tags((string) $body)) }}>{!! $body !!}</div>
    </section>
@endif
