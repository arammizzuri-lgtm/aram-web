{{-- A client's pencil portrait and voice note, with a quote ($cvQuote).
     The portrait is a disc of paper that draws itself while they speak
     (voices.js), inside a ring that carries their company's name and, on a
     seal, its logo, and turns while the voice plays. The player stays hidden
     until voices.js knows this browser can play the file — the quote stands
     on its own without it. Labels carry both languages for the homepage's
     language toggle. --}}
@php
    $cvSketch = $cvQuote->sketchUrl();
    $cvVoice = $cvQuote->voiceUrl();
    $cvClient = $cvQuote->client;
    $cvNameEn = $cvQuote->personName('en');
    $cvNameKu = $cvQuote->personName('ku');
    $cvRingId = 'cvRing'.$cvQuote->id;
    if ($cvClient) {
        // The company's name around the ring, repeated to go most of the way
        // round; the gap left at the top right is where the seal sits.
        $cvCompany = mb_strtoupper(\Illuminate\Support\Str::limit($cvClient->name, 40, ''));
        $cvRepeat = max(1, intdiv(80, mb_strlen($cvCompany) + 3));
        $cvRingText = implode(' · ', array_fill(0, $cvRepeat, $cvCompany));
    }
@endphp
@if ($cvSketch || $cvVoice)
    <div class="cv" data-cv @if ($cvSketch) data-has-portrait @endif>
        @if ($cvSketch)
            <div class="cv__portrait">
                <div class="cv__tilt">
                    <div class="cv__paper">
                        <img class="cv__sketch" src="{{ $cvSketch }}" width="560" height="560" loading="lazy" decoding="async"
                             alt="{{ bi('Pencil portrait of '.$cvNameEn, 'وێنەی قەڵەمڕەساسی '.$cvNameKu) }}"
                             data-alt-en="Pencil portrait of {{ $cvNameEn }}" data-alt-ku="وێنەی قەڵەمڕەساسی {{ $cvNameKu }}">
                        <canvas class="cv__draw" width="560" height="560" aria-hidden="true"></canvas>
                    </div>
                    <div class="cv__orbit" aria-hidden="true">
                        <svg class="cv__ring" viewBox="0 0 240 240">
                            <circle class="cv__hair" cx="120" cy="120" r="117"/>
                            @if ($cvClient)
                                <path id="{{ $cvRingId }}" d="M16,120 a104,104 0 1,1 208,0 a104,104 0 1,1 -208,0" fill="none"/>
                                {{-- starts just past the seal and runs clockwise to just before it --}}
                                <text class="cv__company" transform="rotate(149 120 120)" direction="ltr">
                                    <textPath href="#{{ $cvRingId }}" textLength="603" lengthAdjust="spacing">{{ $cvRingText }}</textPath>
                                </text>
                            @else
                                <circle class="cv__ticks" cx="120" cy="120" r="107"/>
                            @endif
                        </svg>
                        @if ($cvClient)
                            <span class="cv__seal">
                                @if ($cvClient->monoUrl())
                                    <img src="{{ $cvClient->monoUrl() }}" alt="" loading="lazy" decoding="async">
                                @elseif ($cvClient->mark)
                                    <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">{!! $cvClient->mark !!}</svg>
                                @else
                                    <span class="cv__initial">{{ mb_substr($cvClient->name, 0, 1) }}</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        @if ($cvVoice)
            <div class="cv__player" hidden data-src="{{ $cvVoice }}" data-type="{{ $cvQuote->voiceType() }}" data-seed="{{ $cvQuote->id }}">
                <button type="button" class="cv__play"
                        aria-label="{{ bi('Listen to '.$cvNameEn, 'گوێ لە '.$cvNameKu.' بگرە') }}"
                        data-play-en="Listen to {{ $cvNameEn }}" data-play-ku="گوێ لە {{ $cvNameKu }} بگرە"
                        data-pause-en="Pause" data-pause-ku="وەستاندن">
                    <svg class="cv__icon cv__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                    <svg class="cv__icon cv__icon--pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></svg>
                </button>
                <div class="cv__wave" role="slider" tabindex="0" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
                     aria-label="{{ bi('Voice note', 'پەیامی دەنگی') }}" data-label-en="Voice note" data-label-ku="پەیامی دەنگی"></div>
                <span class="cv__time" aria-hidden="true"></span>
            </div>
        @endif
    </div>
@endif
