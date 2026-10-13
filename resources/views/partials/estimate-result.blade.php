{{-- The estimator's result (App\Support\CostEstimate::calculate) — on the
     page as it loads, and again from EstimateController::calc as each answer
     changes, so there is one way it is drawn. Amounts sit in <bdi>, or a
     Kurdish line reads "$180,000" as "180,000$". --}}
@php
    $lang = site_lang();
    $fill = fn (string $key, array $with) => str_replace(array_keys($with), array_values($with), bival($key));
@endphp
@if (! $estimate)
    <p class="es-empty">{{ bival('est_empty') }}</p>
@else
    @php
        $finish = $estimate['finish'][$lang] ?? $estimate['finish']['en'];
        $labels = [
            'building' => $fill('est_line_building', ['{area}' => number_format($estimate['built']), '{finish}' => $finish]),
            'basement' => $fill('est_line_basement', ['{area}' => number_format($estimate['basement_area'])]),
            'bathrooms' => $fill('est_line_bathrooms', ['{n}' => $estimate['inputs']['bathrooms']]),
            'pool' => bival('est_line_pool'),
        ];
        $pct = fn (float $n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
        [$feeLow, $feeHigh] = $estimate['fee_rates'];
        $feeNote = $estimate['fee_basis'] === 'percent'
            ? \App\Support\CostEstimate::rangeIn(bival('est_fee_percent'), $pct($feeLow), $pct($feeHigh))
            : \App\Support\CostEstimate::rangeIn(bival('est_fee_m2'), \App\Support\CostEstimate::usd($feeLow), \App\Support\CostEstimate::usd($feeHigh));
    @endphp
    <div class="es-figure">
        <p class="es-figure__label">{{ bival('est_building') }}</p>
        <p class="es-figure__usd"><bdi>{{ \App\Support\CostEstimate::usdRange($estimate['construction']) }}</bdi></p>
        @if ($iqd = \App\Support\CostEstimate::iqdRange($estimate['construction'], $estimate['iqd_rate'], $lang))
            <p class="es-figure__iqd"><bdi>{{ $iqd }}</bdi></p>
        @endif
    </div>
    <ul class="es-lines">
        @foreach ($estimate['lines'] as $key => $line)
            <li><span>{{ $labels[$key] }}</span><bdi>{{ \App\Support\CostEstimate::usdRange($line) }}</bdi></li>
        @endforeach
    </ul>
    <div class="es-figure es-figure--fee">
        <p class="es-figure__label">{{ bival('est_fee') }}</p>
        <p class="es-figure__usd"><bdi>{{ \App\Support\CostEstimate::usdRange($estimate['fee']) }}</bdi></p>
        @if ($iqd = \App\Support\CostEstimate::iqdRange($estimate['fee'], $estimate['iqd_rate'], $lang))
            <p class="es-figure__iqd"><bdi>{{ $iqd }}</bdi></p>
        @endif
        <p class="es-figure__note">{{ $feeNote }}</p>
    </div>
    <p class="es-note">{{ bival('est_note') }}</p>
@endif
