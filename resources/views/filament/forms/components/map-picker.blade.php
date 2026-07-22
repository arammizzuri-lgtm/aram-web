@php
    use Illuminate\Support\Str;

    // Sibling lat/lng live in the same container as this field
    // (e.g. "data.map_picker" -> "data.lat" / "data.lng").
    $prefix   = Str::contains($getStatePath(), '.') ? Str::beforeLast($getStatePath(), '.') : '';
    $dot      = $prefix === '' ? '' : $prefix . '.';
    $config   = [
        'latPath' => $dot . 'lat',
        'lngPath' => $dot . 'lng',
        'center'  => $field->getCenter(),
        'zoom'    => $field->getZoom(),
        'tiles'   => $field->getTiles(),
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="amp-wrap"
        wire:ignore
        x-data="mapPicker(@js($config))"
        x-init="init()"
    >
        <div class="amp-search">
            <input
                type="text"
                placeholder="Search a place — e.g. “Erbil Citadel” — then fine-tune the pin"
                x-on:keydown.enter.prevent="search($event.target.value)"
                x-ref="q"
            >
            <button type="button" x-on:click="search($refs.q.value)" x-bind:disabled="searching">
                <span x-show="!searching">Search</span>
                <span x-show="searching">…</span>
            </button>
        </div>

        <div class="amp-map" x-ref="map"></div>

        <div class="amp-foot">
            <span class="amp-coords" x-show="lat !== null && lng !== null">
                Pin: <b x-text="lat"></b>, <b x-text="lng"></b>
            </span>
            <span class="amp-coords" x-show="lat === null || lng === null">
                Click the map to drop a pin — drag it to fine-tune.
            </span>
            <button type="button" class="amp-clear" x-show="lat !== null" x-on:click="clear()">
                Remove pin
            </button>
        </div>
    </div>
</x-dynamic-component>
