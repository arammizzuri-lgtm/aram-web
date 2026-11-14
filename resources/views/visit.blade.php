{{-- /visit — ask for a studio visit, a site visit or a phone call on the day
     and time that suits (VisitController). No slots to pick from: the studio
     confirms it or suggests another on WhatsApp (Clients → Visits). Built on
     the room's PIN card (room.css). --}}
@php
    $lang = site_lang();
    $brand = trim(explode('|', bival('seo_title'))[0]);
    $today = now('Asia/Baghdad')->toDateString();
    $bad = fn (string $field) => $errors->has($field) ? 'aria-invalid=true' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ site_rtl($lang) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.room-head', ['roomTitle' => bival('visit_label').' — '.$brand])
</head>
<body class="pp pp--room">

    @include('partials.page-top')

    <main class="rm-gate">
        <div class="rm-gate__card">
            <p class="pp-label">{{ bival('visit_label') }}</p>
            <h1 class="rm-gate__title">{{ bival('visit_title') }}</h1>

            @if (session('visit_sent'))
                <p class="rm-gate__text" role="status">{{ bival('visit_sent') }}</p>
                <a class="pp-btn rm-visit__home" href="{{ site_url('/') }}">
                    <span>{{ bival('careers_home') }}</span>
                    <span class="pp-arrow" aria-hidden="true">→</span>
                </a>
            @else
                <p class="rm-gate__text">{{ bival('visit_text') }}</p>
                <form class="rm-pin rm-visit" method="post" action="{{ site_url('/visit') }}">
                    @csrf
                    <div class="form-hp" aria-hidden="true">
                        <label for="vs-website">Website</label>
                        <input type="text" id="vs-website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="rm-visit__f">
                        <label class="rm-pin__label" for="vs-kind">{{ bival('visit_kind') }}</label>
                        <select id="vs-kind" name="kind" required {{ $bad('kind') }}>
                            @foreach (array_keys(\App\Models\VisitRequest::KINDS) as $kind)
                                <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ bival('visit_kind_'.$kind) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rm-visit__when">
                        <div class="rm-visit__f">
                            <label class="rm-pin__label" for="vs-day">{{ bival('visit_day') }}</label>
                            <input id="vs-day" name="day" type="date" min="{{ $today }}" value="{{ old('day') }}" required dir="ltr" {{ $bad('day') }}>
                        </div>
                        <div class="rm-visit__f">
                            <label class="rm-pin__label" for="vs-time">{{ bival('visit_time') }}</label>
                            <input id="vs-time" name="time" type="time" step="900" value="{{ old('time') }}" required dir="ltr" {{ $bad('time') }}>
                        </div>
                    </div>
                    <div class="rm-visit__f">
                        <label class="rm-pin__label" for="vs-name">{{ bival('form_name') }}</label>
                        <input id="vs-name" name="name" type="text" maxlength="120" autocomplete="name" value="{{ old('name') }}" required {{ $bad('name') }}>
                    </div>
                    <div class="rm-visit__f">
                        <label class="rm-pin__label" for="vs-phone">{{ bival('form_phone') }}</label>
                        <input id="vs-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="{{ old('phone') }}" required dir="ltr" {{ $bad('phone') }}>
                    </div>
                    <div class="rm-visit__f">
                        <label class="rm-pin__label" for="vs-about">{{ bival('visit_about') }}</label>
                        <textarea id="vs-about" name="about" rows="3" maxlength="2000" placeholder="{{ bival('visit_about_ph') }}" required {{ $bad('about') }}>{{ old('about') }}</textarea>
                    </div>
                    @if ($errors->any())
                        <p class="rm-pin__error" role="alert">{{ bival('visit_error') }}</p>
                    @endif
                    <button class="pp-btn" type="submit">
                        <span>{{ bival('visit_send') }}</span>
                        <span class="pp-arrow" aria-hidden="true">→</span>
                    </button>
                </form>
            @endif
        </div>
    </main>

    @include('partials.page-foot')
</body>
</html>
