{{--
    Project Grid editor.

    The geometry below is a deliberate copy of `.pg__grid` / `.pgc--*` in
    public/style.css: three rows, dense column flow, tiles spanning two columns
    (wide) or two columns by two rows (large). Keep the two in step — if the
    public grid's row count or spans change, change them here too, otherwise
    this preview quietly stops telling the truth.

    Everything is client-side until "Save layout" is pressed, so dragging feels
    instant and a mis-drag costs nothing. The wrapper is wire:ignore'd to keep
    Livewire from patching the DOM out from under Alpine mid-drag.
--}}
<x-filament-panels::page>

    <style>
        .amg__bar {
            display: flex; flex-wrap: wrap; gap: .75rem;
            align-items: center; justify-content: space-between;
            margin-bottom: .875rem;
        }
        .amg__hint { font-size: .8125rem; color: rgba(255,255,255,.55); line-height: 1.5; }
        .amg__hint b { color: rgba(255,255,255,.8); font-weight: 600; }
        .amg__actions { display: flex; align-items: center; gap: .5rem; }
        .amg__dirty {
            font-size: .75rem; color: #F5C518;
            padding: .25rem .625rem; border-radius: 100px;
            background: rgba(245,197,24,.12); border: 1px solid rgba(245,197,24,.3);
            white-space: nowrap;
        }
        .amg__btn {
            font-size: .8125rem; font-weight: 500;
            padding: .4rem .85rem; border-radius: .5rem;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.06); color: rgba(255,255,255,.9);
            cursor: pointer; transition: background .15s, opacity .15s;
        }
        .amg__btn:hover:not(:disabled) { background: rgba(255,255,255,.12); }
        .amg__btn:disabled { opacity: .4; cursor: default; }
        .amg__btn--primary {
            background: #F5C518; border-color: #F5C518; color: #000; font-weight: 600;
        }
        .amg__btn--primary:hover:not(:disabled) { background: #ffd633; }

        /* ---- the grid: mirrors .pg__grid on the public site ---- */
        .amg__scroll {
            border: 1px solid rgba(255,255,255,.1);
            border-radius: .75rem;
            background:
                linear-gradient(rgba(255,255,255,.02), rgba(255,255,255,.02)),
                repeating-linear-gradient(90deg, transparent 0 39px, rgba(255,255,255,.03) 39px 40px);
            overflow: hidden;
        }
        .amg__grid {
            display: grid;
            grid-auto-flow: column dense;          /* same flow as the public row */
            grid-template-rows: repeat(3, 1fr);    /* same three rows */
            grid-auto-columns: 148px;
            gap: 10px;
            height: 432px;
            overflow-x: auto;
            overflow-y: hidden;
            padding: 14px;
            scrollbar-width: thin;
        }
        .amg__tile--wide  { grid-column: span 2; }
        .amg__tile--large { grid-column: span 2; grid-row: span 2; }

        .amg__tile {
            position: relative; overflow: hidden;
            border-radius: .5rem; background: #111;
            cursor: grab; user-select: none;
            border: 1px solid rgba(255,255,255,.08);
            transition: outline-color .15s, opacity .15s;
            outline: 2px solid transparent; outline-offset: -2px;
        }
        .amg__tile:hover { outline-color: rgba(245,197,24,.55); }
        .amg__tile.is-dragging { opacity: .35; cursor: grabbing; }
        .amg__img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .amg__scrim {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,.15) 45%, transparent 70%);
        }
        .amg__pos {
            position: absolute; top: 6px; left: 6px; z-index: 3;
            min-width: 20px; height: 20px; padding: 0 5px;
            display: grid; place-items: center;
            font-size: 10px; font-weight: 700; border-radius: 5px;
            background: rgba(0,0,0,.7); color: #F5C518;
            border: 1px solid rgba(245,197,24,.35);
        }
        .amg__body { position: absolute; left: 8px; right: 8px; bottom: 30px; z-index: 3; pointer-events: none; }
        .amg__name {
            font-size: 11.5px; font-weight: 500; color: #fff; line-height: 1.25;
            overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        }
        .amg__meta {
            font-size: 8px; letter-spacing: .1em; text-transform: uppercase;
            color: rgba(255,255,255,.5); margin-top: 2px;
            overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
        }
        .amg__tools {
            position: absolute; left: 6px; right: 6px; bottom: 6px; z-index: 4;
            display: flex; gap: 3px; align-items: center;
        }
        .amg__sz, .amg__mv {
            font-size: 8.5px; font-weight: 700; line-height: 1;
            padding: 3.5px 5px; border-radius: 4px; cursor: pointer;
            background: rgba(0,0,0,.62); color: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.14);
            transition: background .15s, color .15s;
        }
        .amg__sz:hover, .amg__mv:hover { background: rgba(0,0,0,.85); color: #fff; }
        .amg__sz.is-on { background: #F5C518; border-color: #F5C518; color: #000; }
        .amg__mv { margin-left: auto; }

        /* ---- projects the public grid leaves out ---- */
        .amg__off { margin-top: 1.25rem; }
        .amg__off-title { font-size: .8125rem; font-weight: 600; color: rgba(255,255,255,.8); margin-bottom: .5rem; }
        .amg__off-list { display: flex; flex-wrap: wrap; gap: .5rem; }
        .amg__off-item {
            display: flex; align-items: center; gap: .5rem;
            padding: .3rem .6rem .3rem .3rem; border-radius: .5rem;
            background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09);
            font-size: .75rem; color: rgba(255,255,255,.75); text-decoration: none;
        }
        .amg__off-item:hover { background: rgba(255,255,255,.09); }
        .amg__off-thumb { width: 26px; height: 26px; border-radius: .3rem; object-fit: cover; background: #111; }
        .amg__off-why {
            font-size: .625rem; letter-spacing: .06em; text-transform: uppercase;
            color: rgba(255,255,255,.45);
        }
        .amg__empty { padding: 2rem; text-align: center; color: rgba(255,255,255,.4); font-size: .875rem; }
    </style>

    <div wire:ignore x-data="projectGrid(@js($tiles))">

        <div class="amg__bar">
            <p class="amg__hint">
                <b>Drag any tile</b> to move it along the row — tile <b>1</b> is the first
                thing visitors see. The <b>1×1 / 2×1 / 2×2</b> buttons set how much of the
                grid a project takes up, and <b>⇤ ⇥</b> send it to the very start or end.
            </p>
            <div class="amg__actions">
                <span class="amg__dirty" x-show="dirty" x-cloak>Unsaved changes</span>
                <button type="button" class="amg__btn" @click="reset()" :disabled="!dirty">Reset</button>
                <button type="button" class="amg__btn amg__btn--primary"
                        @click="save()" :disabled="!dirty || saving">
                    <span x-text="saving ? 'Saving…' : 'Save layout'"></span>
                </button>
            </div>
        </div>

        <div class="amg__scroll">
            <div class="amg__grid" @dragover.prevent>
                <template x-for="(tile, index) in tiles" :key="tile.id">
                    <article class="amg__tile"
                             :class="[
                                 'amg__tile--' + tile.size,
                                 dragIndex === index ? 'is-dragging' : '',
                             ]"
                             draggable="true"
                             @dragstart="start(index, $event)"
                             @dragover.prevent="over(index)"
                             @dragend="end()"
                             @drop.prevent="end()">

                        <img class="amg__img" :src="tile.cover" :style="'object-position:' + tile.position" alt="" draggable="false">
                        <div class="amg__scrim"></div>

                        <div class="amg__pos" x-text="index + 1"></div>

                        <div class="amg__body">
                            <div class="amg__name" x-text="tile.name"></div>
                            <div class="amg__meta" x-text="tile.meta"></div>
                        </div>

                        <div class="amg__tools">
                            <template x-for="opt in sizes" :key="opt.value">
                                <button type="button" class="amg__sz"
                                        :class="tile.size === opt.value ? 'is-on' : ''"
                                        :title="opt.title"
                                        @click.stop="setSize(tile, opt.value)"
                                        x-text="opt.label"></button>
                            </template>
                            <button type="button" class="amg__mv" title="Send to the start"
                                    @click.stop="move(index, 0)">⇤</button>
                            <button type="button" class="amg__mv" title="Send to the end"
                                    @click.stop="move(index, tiles.length - 1)">⇥</button>
                        </div>
                    </article>
                </template>

                <template x-if="tiles.length === 0">
                    <p class="amg__empty">No projects are on the grid yet.</p>
                </template>
            </div>
        </div>

        @if ($excluded)
            <div class="amg__off">
                <p class="amg__off-title">Not on the grid ({{ count($excluded) }})</p>
                <div class="amg__off-list">
                    @foreach ($excluded as $item)
                        <a class="amg__off-item" href="{{ $item['editUrl'] }}">
                            <img class="amg__off-thumb" src="{{ $item['cover'] }}" alt="">
                            <span>{{ $item['name'] }}</span>
                            <span class="amg__off-why">{{ $item['reason'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('projectGrid', (initial) => ({
                tiles: initial,
                saved: JSON.parse(JSON.stringify(initial)),
                dragIndex: null,
                dirty: false,
                saving: false,

                sizes: [
                    { value: 'default', label: '1×1', title: 'Default — one cell' },
                    { value: 'wide',    label: '2×1', title: 'Wide — two columns' },
                    { value: 'large',   label: '2×2', title: 'Large — two columns by two rows' },
                ],

                start(index, event) {
                    this.dragIndex = index;
                    event.dataTransfer.effectAllowed = 'move';
                    // Firefox refuses to start a drag without payload.
                    event.dataTransfer.setData('text/plain', String(index));
                },

                /* Live reorder as the pointer crosses a neighbour, so the row
                   reflows exactly as the public grid will. */
                over(index) {
                    if (this.dragIndex === null || this.dragIndex === index) return;
                    const [moved] = this.tiles.splice(this.dragIndex, 1);
                    this.tiles.splice(index, 0, moved);
                    this.dragIndex = index;
                    this.dirty = true;
                },

                end() {
                    this.dragIndex = null;
                },

                move(from, to) {
                    if (from === to) return;
                    const [moved] = this.tiles.splice(from, 1);
                    this.tiles.splice(to, 0, moved);
                    this.dirty = true;
                },

                setSize(tile, size) {
                    if (tile.size === size) return;
                    tile.size = size;
                    this.dirty = true;
                },

                reset() {
                    this.tiles = JSON.parse(JSON.stringify(this.saved));
                    this.dirty = false;
                },

                async save() {
                    this.saving = true;
                    try {
                        await this.$wire.saveLayout(
                            this.tiles.map(t => ({ id: t.id, size: t.size }))
                        );
                        this.saved = JSON.parse(JSON.stringify(this.tiles));
                        this.dirty = false;
                    } finally {
                        this.saving = false;
                    }
                },
            }));
        });
    </script>
</x-filament-panels::page>
