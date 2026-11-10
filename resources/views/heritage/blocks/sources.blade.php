{{-- Sources and further reading, small and at the end. --}}
@php
    use App\Models\Landmark;
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['title'] ?? null)));
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_sources_default');
@endphp
@if ($items)
    <section class="lm-sec lm-src reveal">
        <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        <ol class="lm-src__list">
            @foreach ($items as $it)
                <li>
                    @if (preg_match('#^https?://#i', (string) ($it['url'] ?? '')))
                        <a href="{{ $it['url'] }}" target="_blank" rel="noopener">{{ $it['title'] }}</a>
                    @else
                        <span>{{ $it['title'] }}</span>
                    @endif
                    @if (filled($it['note'] ?? null))<span class="lm-src__note">{{ $it['note'] }}</span>@endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
