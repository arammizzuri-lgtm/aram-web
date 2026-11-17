{{-- An essay's plates (journal-essay.css): one sheet a building, its code (CH-01 …), name and type across the top, its plans
     side by side at one scale, its sections and elevations below them, columns as wide as the drawings, then the text,
     the legend to the numbers on the drawings, and the facts. "Print the plates" sets them one to an A3 sheet
     (journal-drawing.js), each with a title strip along its foot. The drawings are picked as the "Drawing" section
     picks them (App\Models\Drawing::resolve): the essays' own, corrections in, or a plan traced in the admin. --}}
@php
    use App\Models\Drawing;
    use App\Models\Landmark;
    $plates = array_values((array) ($d['plates'] ?? []));
    $code = trim((string) ($d['code'] ?? '')) ?: 'PL';
    $heading = Landmark::t($d, 'heading');
    $key = Landmark::t($d, 'key');
    $headline = $l->tr('title');
    $drawn = fn ($rows) => array_values(array_filter(array_map(fn ($r) => ($dwg = Drawing::resolve($r['drawing'] ?? null)) ? [
        'dwg' => $dwg, 'cap' => (string) Landmark::t($r, 'caption'), 'wide' => ! empty($r['wide']),
        'id' => 'dw-'.preg_replace('/[^a-z0-9-]/', '', str_replace(['code:', 'db:'], ['', 'db'], $r['drawing'])),
    ] : null, (array) $rows)));
@endphp
<section class="ja-plates">
    @if (filled($heading))
        <h2 class="ja-h">{{ $heading }}</h2>
    @endif
    @if (filled($key))
        <p class="ja-plates__key">{{ $key }}</p>
    @endif
    <button type="button" class="pp-btn pp-btn--line ja-print" data-print-plates>{{ bi('Print the plates (A3, or save as PDF)', 'چاپی تابلۆکان (A3، یان پاشەکەوت وەک PDF)', 'اطبع اللوحات (A3، أو احفظها PDF)') }}</button>

    @foreach ($plates as $i => $plate)
        @php
            $name = (string) Landmark::t($plate, 'name');
            [$plans, $secs] = [$drawn($plate['plans'] ?? []), $drawn($plate['secs'] ?? [])];
        @endphp
        <article class="ja-plate" id="plate-{{ $i + 1 }}">
            <header class="ja-plate__head">
                <span class="ja-plate__code" dir="ltr">{{ $code }}-{{ sprintf('%02d', $i + 1) }}</span>
                <h3 class="ja-plate__name">{{ $name }}</h3>
                <p class="ja-plate__type">{{ Landmark::t($plate, 'type') }}</p>
            </header>
            @if ($plans)
                <div class="ja-plate__sheet ja-plate__sheet--{{ count($plans) }}">
                    @php $widest = max(array_map(fn ($p) => $p['dwg']['vb'][2], $plans)); @endphp
                    @foreach ($plans as $p)
                        <figure class="ja-plate__fig{{ $p['wide'] ? ' ja-plate__fig--wide' : '' }}">
                            @include('journal.drawing', ['dwg' => $p['dwg'], 'id' => $p['id'], 'name' => $name.' — '.$p['cap'], 'rel' => $p['dwg']['vb'][2] / $widest])
                            <figcaption>{{ $p['cap'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
            @if ($secs)
                {{-- sections and elevations from the same survey; columns as wide as the drawings, so all are at one scale --}}
                <div class="ja-plate__sheet ja-plate__sheet--sec" style="--cols: {{ implode(' ', array_map(fn ($p) => round($p['dwg']['vb'][2]).'fr', $secs)) }}">
                    @foreach ($secs as $p)
                        <figure class="ja-plate__fig">
                            @include('journal.drawing', ['dwg' => $p['dwg'], 'id' => $p['id'], 'name' => $name.' — '.$p['cap']])
                            <figcaption>{{ $p['cap'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
            <div class="ja-plate__body">
                <div class="ja-plate__text">
                    {!! Landmark::rich(Landmark::t($plate, 'text')) !!}
                    @if (! empty($plate['legend']))
                        <ol class="ja-legend">
                            @foreach ($plate['legend'] as $row)
                                <li><span class="ja-legend__n" dir="ltr">{{ $row['n'] ?? '' }}</span><span>{{ Landmark::t($row, 'label') }}</span></li>
                            @endforeach
                        </ol>
                    @endif
                </div>
                @if (! empty($plate['facts']))
                    <dl class="ja-facts">
                        @foreach ($plate['facts'] as $f)
                            @php $v = (string) Landmark::t($f, 'value'); @endphp
                            <div><dt>{{ Landmark::t($f, 'label') }}</dt>@if (! empty($f['ltr']))<dd dir="ltr">{{ $v }}</dd>@else<dd><bdi>{{ $v }}</bdi></dd>@endif</div>
                        @endforeach
                    </dl>
                @endif
            </div>
            <p class="ja-plate__foot"><span>Aram Mizuri Architecture — {{ $headline }}</span><span dir="ltr">{{ $l->url() }} · {{ $i + 1 }} / {{ count($plates) }}</span></p>
        </article>
    @endforeach
</section>
