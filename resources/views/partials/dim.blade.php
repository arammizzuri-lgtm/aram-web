{{-- A dimension line along a project's picture, drawn from its middle out to
     the two ticks when the card is pointed at (engage.css): the project's
     area, or its years when no area is given. $project. --}}
@php $dimText = trim((string) ($project->area ?: $project->year)); @endphp
@if ($dimText !== '')
    <span class="dim" dir="ltr" aria-hidden="true"><i></i><b>{{ $dimText }}</b><i></i></span>
@endif
