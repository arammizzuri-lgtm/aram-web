{{-- A drawing on its own plate, drawn as the journal's plates are (journal/drawing): one of the essays' own (with any
     correction that is live) or a plan traced in the admin. App\Models\Drawing::resolve reads the section's pick. --}}
@php
    $dwg = \App\Models\Drawing::resolve($d['drawing'] ?? null);
    $caption = \App\Models\Landmark::t($d, 'caption');
@endphp
@if ($dwg)
    <div class="lm-sec ja-plate ja-dblock reveal">
        <figure class="ja-plate__fig">
            @include('journal.drawing', ['dwg' => $dwg, 'id' => 'dw-s'.$n, 'name' => filled($caption) ? $caption : $l->tr('title')])
            @if (filled($caption))<figcaption{{ text_dir($caption) }}>{{ $caption }}</figcaption>@endif
        </figure>
    </div>
@endif
