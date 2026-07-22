@php
    use Illuminate\Support\Str;

    $prefix = Str::contains($getStatePath(), '.') ? Str::beforeLast($getStatePath(), '.') : '';
    $dot    = $prefix === '' ? '' : $prefix.'.';
    $images = $field->getImages();
    $config = [
        'coverPath' => $dot.'cover',
        'xPath'     => $dot.'cover_x',
        'yPath'     => $dot.'cover_y',
        'images'    => $images,
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (empty($images))
        <div class="cvp-empty">Upload images and save first — then choose the cover and its focal point here.</div>
    @else
        <div class="cvp-wrap" wire:ignore x-data="coverPicker(@js($config))" x-init="init()">
            <div class="cvp-col">
                <div class="cvp-label">1 · Pick the cover image</div>
                <div class="cvp-grid">
                    <template x-for="im in images" :key="im.value">
                        <button type="button" class="cvp-thumb" :class="{ 'is-cover': im.value === cover }"
                                x-on:click="pick(im.value)">
                            <img :src="im.url" alt="" loading="lazy">
                            <span class="cvp-badge" x-show="im.value === cover">Cover</span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="cvp-col">
                <div class="cvp-label">2 · Click the part to keep in view (focal point)</div>
                <div class="cvp-stage" x-ref="stage" x-on:click="setFocus($event)" title="Click to set the focal point">
                    <img :src="coverUrl()" alt="" x-bind:style="`object-position:${x}% ${y}%`">
                    <span class="cvp-dot" x-bind:style="`left:${x}%;top:${y}%`"></span>
                </div>
                <div class="cvp-readout">
                    Focal point: <b x-text="x + '%'"></b>, <b x-text="y + '%'"></b>
                    <button type="button" class="cvp-reset" x-on:click="reset()">Reset to centre</button>
                </div>
            </div>
        </div>
    @endif
</x-dynamic-component>
