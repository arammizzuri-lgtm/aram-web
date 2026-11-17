{{-- An essay's figures, across its head under the line (journal/essay puts the first one there): Houses 7, Plans 12 … --}}
@php
    $items = array_filter((array) ($d['items'] ?? []), fn ($it) => filled($it['value'] ?? null));
@endphp
@if ($items)
    <dl class="ja-count">
        @foreach ($items as $it)
            {{-- a bare number reads the same either way; anything more (130+, c. 1750–1930) is set left to right --}}
            <div><dt>{{ \App\Models\Landmark::t($it, 'label') }}</dt><dd{!! ctype_digit((string) $it['value']) ? '' : ' dir="ltr"' !!}>{{ $it['value'] }}</dd></div>
        @endforeach
    </dl>
@endif
