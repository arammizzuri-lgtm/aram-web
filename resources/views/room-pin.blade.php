@php
    /** @var \App\Models\ClientRoom $room */
    $lang = site_lang();
    $isKu = $lang === 'ku';
    $name = bi($room->project->name, $room->project->name_ku);
    $brand = trim(explode('|', bival('seo_title'))[0]);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $isKu ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.room-head', ['roomTitle' => bival('room_label').' — '.$brand])
</head>
<body class="pp pp--room">

    @include('partials.page-top', ['otherUrl' => site_url('/room/'.$room->token, $isKu ? 'en' : 'ku'), 'backHref' => site_url('/'), 'backLabel' => bival('careers_home'), 'withSaved' => false])

    <main class="rm-gate">
        <div class="rm-gate__card">
            <p class="pp-label">{{ bival('room_label') }}</p>
            <h1 class="rm-gate__title"><bdi>{{ $name }}</bdi></h1>

            @if (! $room->is_open)
                <p class="rm-gate__text">{{ bival('room_closed') }}</p>
            @else
                <p class="rm-gate__text">{{ bival('room_pin_text') }}</p>
                {{-- No pattern="[0-9]*": a Kurdish keyboard types ٠–٩, which the
                     browser would refuse and the server accepts. --}}
                <form class="rm-pin" method="post" action="{{ site_url('/room/'.$room->token.'/pin') }}">
                    @csrf
                    <label class="rm-pin__label" for="rm-pin">{{ bival('room_pin_title') }}</label>
                    <input class="rm-pin__input" id="rm-pin" name="pin" type="text" inputmode="numeric" maxlength="4"
                           autocomplete="one-time-code" required autofocus dir="ltr">
                    @if ($error)
                        <p class="rm-pin__error" role="alert">{{ bival($error) }}</p>
                    @endif
                    <button class="pp-btn" type="submit">
                        <span>{{ bival('room_pin_open') }}</span>
                        <span class="pp-arrow" aria-hidden="true">→</span>
                    </button>
                </form>
            @endif
        </div>
    </main>

    @include('partials.page-foot')
</body>
</html>
