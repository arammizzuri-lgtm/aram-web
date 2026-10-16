{{-- "Built on a plot like yours" — under the estimator, the studio's
     projects on plots nearest in size to the visitor's
     (App\Support\CostEstimate::similarProjects). Drawn as the page loads and
     again from EstimateController::calc as the plot changes. Nothing without
     a plot, or with nothing near it. --}}
@if ($similar->isNotEmpty())
    @php $lang = site_lang(); @endphp
    <section class="es-like" aria-labelledby="esLikeTitle">
        <p class="pp-label">{{ bival('est_like_label') }}</p>
        <h2 class="es-like__title" id="esLikeTitle">{{ bival('est_like_title') }}</h2>
        <p class="es-like__text">{{ str_replace('{plot}', number_format($plot), bival('est_like_text')) }}</p>
        <ul class="es-like__grid">
            @foreach ($similar as $p)
                <li>
                    <a class="es-like__card" href="{{ $p->pageUrl($lang) }}">
                        <span class="es-like__pic"><img src="{{ $p->coverThumbUrl() }}" alt="" loading="lazy" decoding="async" style="object-position: {{ $p->coverPosition() }}"></span>
                        <span class="es-like__name"><bdi>{{ bi($p->name, $p->name_ku) }}</bdi></span>
                        <span class="es-like__meta">
                            <span class="es-like__plot"><bdi>{{ str_replace('{area}', number_format($p->plotArea()), bival('est_like_plot')) }}</bdi></span>
                            @if ($p->cityLabel())<span>· <bdi>{{ $p->cityLabel() }}</bdi></span>@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
