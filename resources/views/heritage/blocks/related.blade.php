{{-- Other landmarks to go on to — the ones named, else the same age and part of Kurdistan. --}}
@php
    use App\Models\Landmark;
    $items = $l->related(3, filled($d['slugs'] ?? null) ? (array) $d['slugs'] : null);
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_related_default');
@endphp
@if ($items->isNotEmpty())
    <section class="lm-sec lm-rel reveal">
        <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
        <div class="lm-rel__grid">
            @foreach ($items as $r)
                <a class="lm-rel__card" href="{{ $r->url() }}">
                    <span class="lm-rel__img">
                        @if ($r->image)
                            <img src="{{ $r->imageUrl('sm') }}" srcset="{{ \App\Support\HeritageImage::srcset($r->image) }}" sizes="(min-width: 900px) 30vw, 100vw" alt="" loading="lazy" decoding="async">
                        @endif
                    </span>
                    <span class="lm-rel__meta">{{ Landmark::eraName($r->era) }}@if ($r->unesco) · UNESCO @endif</span>
                    <span class="lm-rel__name"{{ text_dir($r->tr('name')) }}>{{ $r->tr('name') }}</span>
                    <span class="lm-rel__where"{{ text_dir($r->tr('place')) }}>{{ $r->tr('place') }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
