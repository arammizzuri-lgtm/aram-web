{{-- Under a homepage section's title: the way to its own page, drawn as a
     drawing's reference bubble — the arrow above the line, the sheet below. --}}
@php $ref = isset($sheet) ? null : collect($menu ?? \App\Support\SiteMenu::items())->firstWhere('key', $key); @endphp
@if ($ref)
<a class="sheet-ref" href="{{ $ref['href'] }}" aria-label="{{ $ref['label'] }} — {{ bival('sheet_full') }}">
    <span class="sheet-ref__mark" aria-hidden="true"><span class="sheet-ref__arrow">→</span><span class="sheet-ref__code" dir="ltr">{{ $ref['code'] }}</span></span>
    <span class="sheet-ref__text" aria-hidden="true">{{ bival('sheet_full') }}</span>
</a>
@endif
