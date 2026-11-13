{{-- One option of a finish choice in a client room (room.blade.php): its
     picture, what it adds to the price, its supplier — and a radio to pick
     it while the choice is open. --}}
@php
    /** @var \App\Models\RoomChoiceOption $o */
    $img = $o->imageExists() ? $base.'/choices/options/'.$o->id : null;
    $tag = $radio ? 'label' : 'div';
@endphp
<{{ $tag }} class="rm-option">
    @if ($img)
        <img class="rm-option__img" src="{{ $img }}" alt="" loading="lazy" decoding="async">
        <a class="rm-option__zoom" href="{{ $img }}" target="_blank" rel="noopener noreferrer" aria-label="{{ bival('room_open') }}: {{ $o->name }}">⤢</a>
    @endif
    <span class="rm-option__name" dir="auto">
        @if ($radio)<input type="radio" name="option" value="{{ $o->id }}" required>@endif
        {{ $o->name }}
    </span>
    <span class="rm-option__cost">
        @if ($o->extra_cost > 0)<bdi dir="ltr">+{{ $room->money($o->extra_cost, $lang) }}</bdi>@else{{ bival('room_choice_included') }}@endif
    </span>
    @if (filled($o->details))
        <span class="rm-option__details" dir="auto">{{ $o->details }}</span>
    @endif
    @if ($o->partner)
        <span class="rm-option__supplier">{{ bival('room_choice_supplier') }}: <bdi>{{ $o->partner->name }}</bdi>
            @if ($tel = $o->partner->telUrl()) · <a href="{{ $tel }}" dir="ltr">{{ $o->partner->phone }}</a>@endif
        </span>
    @endif
</{{ $tag }}>
