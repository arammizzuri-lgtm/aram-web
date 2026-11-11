{{-- How it was built: a drawing that builds up in steps, each step with a
     title and a line of text. The drawing is one of the studio's own
     (resources/views/heritage/techniques/<drawing>.blade.php, listed in
     Landmark::TECHNIQUES): its parts carry data-step="n" and appear with the
     step of that number. The words — heading, introduction, each step — are
     the admin's, in three languages. landmark.js steps through it once as it
     scrolls into view and lets the visitor pick a step; without JavaScript
     the whole drawing shows, with every step's text beside it. --}}
@php
    use App\Models\Landmark;
    $drawing = $d['drawing'] ?? null;
    $steps = array_values(array_filter((array) ($d['steps'] ?? []), fn ($s) => filled($s['title_en'] ?? null) || filled($s['text_en'] ?? null)));
    $heading = Landmark::t($d, 'heading') ?: bival('landmarks_tq_label');
    $text = Landmark::t($d, 'text');
    $known = is_string($drawing) && array_key_exists($drawing, Landmark::TECHNIQUES) && view()->exists('heritage.techniques.'.$drawing);
@endphp
@if ($known && $steps)
    <section class="lm-sec lm-tq reveal" data-steps="{{ count($steps) }}">
        <header class="lm-sec__head">
            <p class="pp-label">{{ bival('landmarks_tq_label') }}</p>
            <h2 class="lm-h2"{{ text_dir($heading) }}>{{ $heading }}</h2>
            @if (filled($text))<p class="lm-sec__lead"{{ text_dir($text) }}>{{ $text }}</p>@endif
        </header>
        <div class="tq" data-tq>
            <figure class="tq__fig" aria-hidden="true">
                @include('heritage.techniques.'.$drawing)
            </figure>
            <div class="tq__side">
                <ol class="tq__steps">
                    @foreach ($steps as $k => $s)
                        @php $title = Landmark::t($s, 'title'); $body = Landmark::t($s, 'text'); @endphp
                        <li class="tq__step" data-tq-step="{{ $k + 1 }}">
                            <button type="button" class="tq__pick" aria-label="{{ $title }}">
                                <span class="tq__n">{{ Landmark::digits(str_pad((string) ($k + 1), 2, '0', STR_PAD_LEFT)) }}</span>
                                <span class="tq__title"{{ text_dir($title) }}>{{ $title }}</span>
                            </button>
                            @if (filled($body))<p class="tq__text"{{ text_dir($body) }}>{{ $body }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <button type="button" class="tq__replay" data-tq-replay hidden>
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 8a5.5 5.5 0 1 0 1.8-4.1M2.5 2.5v3h3"/></svg>
                    <span>{{ bival('landmarks_tq_replay') }}</span>
                </button>
            </div>
        </div>
    </section>
@endif
