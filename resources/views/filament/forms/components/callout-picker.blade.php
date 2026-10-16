@php
    $images = $field->getImages();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (empty($images))
        <div class="clp-empty">Upload photographs and save first — then place notes on them here.</div>
    @else
        <div class="clp" wire:ignore
             x-data="calloutPicker({ state: $wire.$entangle(@js($getStatePath())), images: @js($images) })">
            <div class="clp-strip">
                <template x-for="im in images" :key="im.value">
                    <button type="button" class="clp-thumb" :class="{ 'is-on': im.value === current }" x-on:click="current = im.value">
                        <img :src="im.thumb" alt="" loading="lazy">
                        <span class="clp-count" x-show="countOn(im.value)" x-text="countOn(im.value)"></span>
                    </button>
                </template>
            </div>

            <div class="clp-stage" x-ref="stage" x-on:click="add($event)" title="Click the spot the note is about">
                <img :src="large()" alt="" draggable="false">
                <template x-for="(n, k) in notesOn()" :key="n.key">
                    <span class="clp-dot" :class="{ 'is-on': n.key === selected }"
                          :style="`left:${n.x}%;top:${n.y}%`"
                          x-on:pointerdown.stop.prevent="grab($event, n)" x-on:click.stop
                          x-text="k + 1"></span>
                </template>
            </div>
            <p class="clp-help">Click the photograph where a note belongs, then write it below. Drag a numbered point to move it.</p>

            <ol class="clp-list">
                <template x-for="(n, k) in notesOn()" :key="n.key">
                    <li class="clp-note" :class="{ 'is-on': n.key === selected }" x-on:focusin="selected = n.key">
                        <span class="clp-num" x-text="k + 1"></span>
                        <input type="text" maxlength="90" placeholder="Note (English) — e.g. Hand-cut Amadiyah limestone" x-model="n.en" x-on:input.debounce.300ms="save()">
                        <input type="text" maxlength="90" dir="rtl" lang="ckb" placeholder="تێبینی (کوردی)" x-model="n.ku" x-on:input.debounce.300ms="save()">
                        <button type="button" class="clp-remove" x-on:click="remove(n)" title="Remove this note">✕</button>
                    </li>
                </template>
            </ol>
        </div>
    @endif
</x-dynamic-component>
