<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php $scan = $field->getScanUrl(); @endphp
    <div class="dt" wire:ignore
         x-data="drawingTracer({ state: $wire.$entangle(@js($getStatePath())), scan: @js($scan), base: @js($field->getBase()), lines: @js(\App\Models\Drawing::LINES) })">
        <div class="dt-bar">
            <div class="dt-tools">
                @foreach (['select' => 'Select', 'wall' => 'Wall', 'line' => 'Line', 'door' => 'Door', 'window' => 'Window', 'gap' => 'Opening', 'room' => 'Room name', 'scale' => 'Set the scale', 'align' => 'Line up the scan'] as $t => $label)
                    <button type="button" class="dt-btn" :class="{ 'is-on': tool === '{{ $t }}' }" x-on:click="tool = '{{ $t }}'">{{ $label }}</button>
                @endforeach
            </div>
            <div class="dt-tools">
                <label class="dt-opt" x-show="tool === 'line'">Kind
                    <select x-model="lineG">
                        <template x-for="(label, g) in lines" :key="g"><option :value="g" x-text="label"></option></template>
                    </select>
                </label>
                <label class="dt-opt" x-show="['door', 'window', 'gap'].includes(tool)">Wall thickness, cm <input type="number" min="4" step="1" x-model.number="openT"></label>
                <label class="dt-opt" x-show="scanUrl"><input type="checkbox" x-model="showScan"> Scan</label>
                <label class="dt-opt" x-show="scanUrl" title="How strongly the scan shows"><input type="range" min="0.1" max="1" step="0.05" x-model.number="opac"></label>
                <button type="button" class="dt-btn" x-on:click="fit()">Fit</button>
                <button type="button" class="dt-btn" x-on:click="undo()" :disabled="!hist.length">Undo</button>
                <button type="button" class="dt-btn" x-show="base" x-on:click="reset()">Original walls</button>
            </div>
        </div>
        <p class="dt-hint" x-text="hint"></p>
        <div class="dt-main">
            <svg x-ref="svg" class="dt-svg" :class="'is-' + tool" xmlns="http://www.w3.org/2000/svg"
                 x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="up()"
                 x-on:wheel.prevent="wheel($event)" x-on:dblclick="dbl($event)" x-on:contextmenu.prevent></svg>
            <aside class="dt-side">
                @unless ($scan)
                    <p class="dt-note">No scan yet: upload one above and save, to trace over it.</p>
                @endunless
                <p x-show="base" class="dt-key"><span class="dt-sw dt-sw--orig"></span> the original’s walls · <span class="dt-sw dt-sw--ref"></span> its doors, windows and fixtures</p>
                <label class="dt-field">North, degrees clockwise from up
                    <input type="number" step="1" :value="d.north" x-on:change="setNorth($event.target.value)" placeholder="none">
                </label>
                <template x-if="sel && sel.kind === 'room' && picked">
                    <div class="dt-panel">
                        <strong>Room name</strong>
                        <label class="dt-field">English <input type="text" x-model="picked.en" x-on:input="edited()"></label>
                        <label class="dt-field">Kurdish <input type="text" dir="rtl" lang="ckb" x-model="picked.ku" x-on:input="edited()"></label>
                        <label class="dt-field">Arabic <input type="text" dir="rtl" lang="ar" x-model="picked.ar" x-on:input="edited()"></label>
                        <small>A word starting with @ is the drawings’ own, already in all three languages: @room, @court, @tarma, @iwan, @kitchen, @store, @bath, @wc, @stair, @dalan, @hall, @diwan, @serdab, @cellar.</small>
                        <button type="button" class="dt-btn dt-del" x-on:click="delSel()">Delete</button>
                    </div>
                </template>
                <template x-if="sel && sel.kind === 'open' && picked">
                    <div class="dt-panel">
                        <strong>Opening</strong>
                        <label class="dt-field">Kind
                            <select x-model="picked.k" x-on:change="edited()"><option value="door">Door</option><option value="window">Window</option><option value="gap">Opening, no door</option></select>
                        </label>
                        <label class="dt-field">Wall thickness, cm <input type="number" min="4" step="1" x-model.number="picked.t" x-on:input="edited()"></label>
                        <label class="dt-opt" x-show="picked.k === 'door'"><input type="checkbox" x-model="picked.flip" x-on:change="edited()"> Swing the other way</label>
                        <button type="button" class="dt-btn dt-del" x-on:click="delSel()">Delete</button>
                    </div>
                </template>
                <template x-if="sel && sel.kind === 'line' && picked">
                    <div class="dt-panel">
                        <strong>Line</strong>
                        <label class="dt-field">Kind
                            <select x-model="picked.g" x-on:change="edited()">
                                <template x-for="(label, g) in lines" :key="g"><option :value="g" x-text="label" :selected="g === picked.g"></option></template>
                            </select>
                        </label>
                        <button type="button" class="dt-btn dt-del" x-on:click="delSel()">Delete</button>
                    </div>
                </template>
                <template x-if="sel && sel.kind === 'wall' && picked">
                    <div class="dt-panel">
                        <strong>Wall outline</strong>
                        <small x-text="picked.length + ' points'"></small>
                        <button type="button" class="dt-btn" x-show="typeof sel.v === 'number'" x-on:click="delPoint()">Delete the point</button>
                        <button type="button" class="dt-btn dt-del" x-on:click="delSel()">Delete the outline</button>
                    </div>
                </template>
                <p class="dt-keys">Esc stops · Enter ends a line · Ctrl+Z undoes · Delete removes what is picked · the middle button, or Alt and drag, moves about while drawing</p>
            </aside>
        </div>
    </div>
</x-dynamic-component>
