{{-- A chapter of an essay on buildings, as the journal sets one (journal-essay.css): a heading, then its parts in order —
     paragraphs, a note on the gold rule, a quotation, the four diagrams of the courtyard house (journal/type), a list of
     terms (linked to the glossary) or of how something is built, periods with their dates, a line to the glossary.
     Style "sources": the reading list, one source a line, left to right. --}}
@php
    use App\Models\Landmark;
    $sources = ($d['style'] ?? 'text') === 'sources';
    $heading = Landmark::t($d, 'heading');
    $glossary = site_url('/journal/glossary');
    $t = fn (array $p, string $f) => Landmark::t($p, $f);
@endphp
<section class="{{ $sources ? 'ja-sources' : 'ja-text' }}">
    @if (filled($heading))
        <h2 class="ja-h">{{ $heading }}</h2>
    @endif
    @foreach ((array) ($d['parts'] ?? []) as $j => $part)
        @php $p = (array) ($part['data'] ?? []); $items = array_values((array) ($p['items'] ?? [])); @endphp
        @switch($part['type'] ?? null)
            @case('paragraphs')
                {!! Landmark::rich($t($p, 'text')) !!}
                @break
            @case('note')
                <p class="ja-note">{{ $t($p, 'text') }}</p>
                @break
            @case('caption')
                <p class="ja-cap">{{ $t($p, 'text') }}</p>
                @break
            @case('glossary')
                <p class="ja-glossary"><a href="{{ $glossary }}#{{ $p['anchor'] ?? '' }}">{{ $t($p, 'text') }}</a> <span class="pp-arrow" aria-hidden="true">→</span></p>
                @break
            @case('quote')
                <blockquote class="ja-quote">
                    <p>{{ $t($p, 'text') }}</p>
                    @if (filled($t($p, 'cite')))<cite>{{ $t($p, 'cite') }}</cite>@endif
                </blockquote>
                @break
            @case('diagrams')
                <ol class="ja-types">
                    @foreach ($items as $k => $it)
                        <li>
                            @if (! empty($it['tp']))
                                @include('journal.type', ['id' => 'ty'.$n.'-'.$j.'-'.$k, 'tp' => $it['tp']])
                            @endif
                            <strong>{{ $t($it, 'title') }}</strong>
                            <span>{{ $t($it, 'text') }}</span>
                        </li>
                    @endforeach
                </ol>
                @break
            @case('list')
                <dl class="{{ ($p['style'] ?? 'terms') === 'build' ? 'ja-build' : 'ja-terms' }}">
                    @foreach ($items as $it)
                        <div>
                            {{-- a word written only in English (a date: 1980s, 5.5.2024) is set left to right --}}
                            <dt{!! blank($it['term_ku'] ?? null) && blank($it['term_ar'] ?? null) ? ' dir="ltr"' : '' !!}>@if (filled($it['anchor'] ?? null))<a href="{{ $glossary }}#{{ $it['anchor'] }}">{{ $t($it, 'term') }}</a>@else{{ $t($it, 'term') }}@endif</dt>
                            <dd>{{ $t($it, 'text') }}</dd>
                        </div>
                    @endforeach
                </dl>
                @break
            @case('periods')
                <div class="ja-ages">
                    @foreach ($items as $it)
                        <div class="ja-age">
                            <p class="ja-age__when" dir="ltr">{{ $it['when'] ?? '' }}</p>
                            <h3>{{ $t($it, 'title') }}</h3>
                            <p>{{ $t($it, 'text') }}</p>
                        </div>
                    @endforeach
                </div>
                @break
            @case('refs')
                {{-- *title* and [link](https://…) as the other sections write them, one source a line --}}
                <ol dir="ltr">
                    @foreach ($items as $it)
                        <li>{!! preg_replace('~^<p>|</p>$~', '', Landmark::rich($it['text'] ?? '')) !!}</li>
                    @endforeach
                </ol>
                @break
        @endswitch
    @endforeach
</section>
