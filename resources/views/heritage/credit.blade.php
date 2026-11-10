{{-- A photograph's credit line, linked to its source when there is one.
     $img: ['credit', 'source']. --}}
@php $credit = trim((string) ($img['credit'] ?? '')); $source = trim((string) ($img['source'] ?? '')); @endphp
@if ($credit !== '')
    <span class="lm-credit">{{ bival('landmarks_photo') }}:
        @if (preg_match('#^https?://#i', $source))
            <a href="{{ $source }}" target="_blank" rel="noopener">{{ $credit }}</a>
        @else
            {{ $credit }}
        @endif
    </span>
@endif
