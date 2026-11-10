{{-- Facts worth knowing: cards, a numbered list, one fact set large, or "meanwhile in the world". --}}
@php
    use App\Models\Landmark;
    $layout = $d['layout'] ?? 'cards';
    $items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => filled($i['text_en'] ?? null)));
    $heading = Landmark::t($d, 'heading') ?: bival($layout === 'world' ? 'landmarks_meanwhile' : 'landmarks_facts_default');
@endphp
@if ($items)
    <section class="lm-sec lm-facts lm-facts--{{ $layout }} reveal" data-count="{{ count($items) }}">
        <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        <ol class="lm-facts__list">
            @foreach ($items as $k => $it)
                @php $label = Landmark::t($it, 'label'); @endphp
                <li class="lm-fact">
                    <span class="lm-fact__n" aria-hidden="true">{{ Landmark::digits(str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                    @if (filled($label))<span class="lm-fact__label"{{ text_dir($label) }}>{{ $label }}</span>@endif
                    <p class="lm-fact__text"{{ text_dir(Landmark::t($it, 'text')) }}>{{ Landmark::t($it, 'text') }}</p>
                </li>
            @endforeach
        </ol>
    </section>
@endif
