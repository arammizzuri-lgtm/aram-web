@php
    /** @var \App\Models\ReviewRequest $review */
    $lang = site_lang();
    $project = $review->project ? bi($review->project->name, $review->project->name_ku, $review->project->name_ar) : null;
    $intro = $project ? str_replace('{project}', $project, bival('review_intro')) : bival('review_intro_plain');
    $state = $review->isUsed() ? 'used' : ($review->isExpired() ? 'expired' : 'open');
    $ver = fn (string $file) => asset($file).'?v='.filemtime(public_path($file));
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl() ? 'rtl' : 'ltr' }}">
<head>
    {{-- a client's private page: never indexed, never previewed with their name --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ bival('review_title') }} — Aram Mizuri Architecture</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500&family=Vazirmatn:wght@200;300;400;500;600&family=Playfair+Display:ital@1&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="{{ $ver('project.css') }}">
    <link rel="stylesheet" href="{{ $ver('review.css') }}">
    @include('partials.kurdish-font')
</head>
<body class="pp rv-body">
    <main class="rv">
        <a class="rv-brand" href="{{ site_url('/') }}">
            <img src="/favicon.svg" width="34" height="34" alt="">
            <span>ARAM MIZURI<small>ARCHITECTURE</small></span>
        </a>

        @if ($state === 'open')
            <header class="rv-head">
                {{-- the name isolated, so a Latin name sits right in a Kurdish or Arabic greeting --}}
                <p class="rv-greet">{!! str_replace('{name}', '<bdi>'.e($review->name).'</bdi>', e(bival('review_greeting'))) !!}</p>
                <h1 class="rv-title">{{ bival('review_title') }}</h1>
                <p class="rv-intro" dir="auto">{{ $intro }}</p>
            </header>

            <form class="rv-form" id="rvForm" action="{{ url('/review/'.$review->token) }}" method="post" enctype="multipart/form-data" novalidate
                  data-msg-sending="{{ bival('form_sending') }}" data-msg-check="{{ bival('form_check') }}" data-msg-failed="{{ bival('form_failed') }}"
                  data-msg-big="{{ bival('review_big') }}" data-msg-mic="{{ bival('review_mic') }}" data-msg-closed="{{ bival('review_used') }}"
                  data-rec="{{ bival('review_rec') }}" data-stop="{{ bival('review_stop') }}" data-again="{{ bival('review_again') }}"
                  data-max-voice="{{ \App\Http\Controllers\ReviewController::MAX_VOICE_KB * 1024 }}">
                @csrf

                <div class="rv-field">
                    <label for="rvQuote">{{ bival('review_words') }}</label>
                    <textarea id="rvQuote" name="quote" rows="6" required minlength="10" maxlength="1500" dir="auto" placeholder="{{ bival('review_words_hint') }}"></textarea>
                </div>
                <div class="rv-row">
                    <div class="rv-field">
                        <label for="rvName">{{ bival('review_name') }}</label>
                        <input type="text" id="rvName" name="name" required maxlength="120" value="{{ $review->name }}" dir="auto" autocomplete="name">
                    </div>
                    <div class="rv-field">
                        <label for="rvRole">{{ bival('review_role') }}</label>
                        <input type="text" id="rvRole" name="role" maxlength="160" dir="auto" value="{{ $review->client?->name }}">
                    </div>
                </div>

                {{-- their voice: recorded here (review.js), or a voice note they choose --}}
                <section class="rv-box" aria-labelledby="rvVoiceH">
                    <h2 class="rv-box__title" id="rvVoiceH">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                        {{ bival('review_voice_title') }}
                    </h2>
                    <p class="rv-box__hint">{{ bival('review_voice_hint') }}</p>
                    <div class="rv-rec" id="rvRec">
                        <button type="button" class="rv-rec__btn" id="rvRecBtn"><span class="rv-rec__dot" aria-hidden="true"></span><span class="rv-rec__label">{{ bival('review_rec') }}</span></button>
                        <span class="rv-rec__time" id="rvRecTime" dir="ltr" hidden>0:00 / 1:30</span>
                        <div class="rv-rec__wave" id="rvWave" aria-hidden="true" hidden></div>
                    </div>
                    <div class="rv-take" id="rvTake" hidden>
                        <audio id="rvAudio" controls preload="metadata"></audio>
                        <button type="button" class="rv-link" id="rvTakeRemove">{{ bival('review_remove') }}</button>
                    </div>
                    <label class="rv-upload">
                        <input type="file" id="rvVoiceFile" accept="audio/*,.m4a,.opus,.ogg,.mp3,.wav,.aac">
                        <span>{{ bival('review_upload') }}</span>
                    </label>
                </section>

                {{-- a photo for the pencil portrait: shrunk in the browser before it is sent --}}
                <section class="rv-box" aria-labelledby="rvPhotoH">
                    <h2 class="rv-box__title" id="rvPhotoH">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
                        {{ bival('review_photo_title') }}
                    </h2>
                    <p class="rv-box__hint">{{ bival('review_photo_hint') }}</p>
                    <div class="rv-photo">
                        <span class="rv-photo__preview" id="rvPhotoPreview" hidden></span>
                        <label class="rv-photo__pick">
                            <input type="file" id="rvPhoto" accept="image/jpeg,image/png,image/webp">
                            <span id="rvPhotoLabel" data-empty="{{ bival('review_photo_choose') }}">{{ bival('review_photo_choose') }}</span>
                        </label>
                        <button type="button" class="rv-link" id="rvPhotoRemove" hidden>{{ bival('review_remove') }}</button>
                    </div>
                </section>

                <label class="rv-consent">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>{{ bival('review_consent') }}</span>
                </label>

                <div class="rv-foot">
                    <button type="submit" class="pp-btn rv-send">{{ bival('review_send') }} <span class="pp-arrow" aria-hidden="true">→</span></button>
                    <p class="rv-status" id="rvStatus" role="status" aria-live="polite" hidden></p>
                </div>
            </form>

            <div class="rv-done" id="rvDone" hidden>
                <img src="/favicon.svg" width="64" height="64" alt="">
                <h2>{{ bival('review_ok_title') }}</h2>
                <p>{{ bival('review_ok') }}</p>
                {{-- the same words on Google, where the next family looking for an architect will see them --}}
                @if ($googleReview = google_profile()['review_url'] ?? null)
                    <p class="rv-google">{{ bival('review_google') }}</p>
                    <a class="pp-btn rv-google__btn" href="{{ $googleReview }}" target="_blank" rel="noopener">{{ bival('review_google_btn') }} <span aria-hidden="true">↗</span></a>
                @endif
            </div>
        @else
            <div class="rv-done">
                <img src="/favicon.svg" width="64" height="64" alt="">
                <p>{{ $state === 'used' ? bival('review_used') : bival('review_expired') }}</p>
            </div>
        @endif
    </main>
    @if ($state === 'open')
        <script src="{{ $ver('review.js') }}" defer></script>
    @endif
</body>
</html>
