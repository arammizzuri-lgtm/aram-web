{{-- Styles + Alpine component for the notes on a project's photographs
     (App\Filament\Forms\Components\CalloutPicker). --}}
<style>
    .clp-empty { color: #9aa0a6; font-size: 0.85rem; padding: 0.4rem 0; }
    .clp { display: grid; gap: 0.8rem; }
    .clp-strip { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .clp-thumb { position: relative; width: 84px; height: 64px; padding: 0; cursor: pointer; border-radius: 0.5rem; overflow: hidden;
        border: 2px solid rgba(255,255,255,0.12); background: #141416; }
    .clp-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .clp-thumb:hover { border-color: rgba(245,197,24,0.5); }
    .clp-thumb.is-on { border-color: #F5C518; box-shadow: 0 0 0 2px rgba(245,197,24,0.25); }
    .clp-count { position: absolute; right: 4px; bottom: 4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
        background: #F5C518; color: #111; font-size: 0.65rem; font-weight: 700; display: grid; place-items: center; }
    .clp-stage { position: relative; max-width: 720px; border-radius: 0.65rem; overflow: hidden; cursor: crosshair;
        border: 1px solid rgba(255,255,255,0.12); background: #141416; user-select: none; touch-action: none; }
    .clp-stage img { display: block; width: 100%; height: auto; pointer-events: none; }
    .clp-dot { position: absolute; width: 26px; height: 26px; transform: translate(-50%, -50%); border-radius: 50%; cursor: grab;
        display: grid; place-items: center; font-size: 0.72rem; font-weight: 700; color: #111; background: #F5C518;
        border: 2px solid #fff; box-shadow: 0 0 0 2px rgba(0,0,0,0.45), 0 2px 10px rgba(0,0,0,0.6); }
    .clp-dot.is-on { background: #fff; }
    .clp-help { margin: 0; font-size: 0.78rem; color: #9aa0a6; }
    .clp-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 0.5rem; max-width: 720px; }
    .clp-note { display: grid; grid-template-columns: 28px 1fr 1fr 32px; gap: 0.5rem; align-items: center;
        padding: 0.45rem; border-radius: 0.6rem; border: 1px solid rgba(255,255,255,0.08); }
    .clp-note.is-on { border-color: rgba(245,197,24,0.5); }
    .clp-num { width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 0.72rem; font-weight: 700; background: #F5C518; color: #111; }
    .clp-note input { width: 100%; min-width: 0; padding: 0.45rem 0.6rem; border-radius: 0.45rem; font-size: 0.85rem;
        background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.14); color: inherit; }
    .clp-note input:focus { outline: none; border-color: #F5C518; }
    .clp-remove { width: 30px; height: 30px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.15); background: none; color: #c8c8c8; cursor: pointer; }
    .clp-remove:hover { border-color: #f87171; color: #f87171; }
    @media (max-width: 700px) { .clp-note { grid-template-columns: 28px 1fr 32px; } .clp-note input[dir="rtl"] { grid-column: 2; } }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('calloutPicker', (config) => ({
            state: config.state,
            images: config.images || [],
            current: null,
            selected: null,
            notes: [],
            seq: 0,

            init() {
                const known = new Set(this.images.map(i => i.value));
                this.notes = (Array.isArray(this.state) ? this.state : [])
                    .filter(n => n && known.has(String(n.img)))
                    .map(n => ({ key: ++this.seq, img: String(n.img), x: +n.x || 50, y: +n.y || 50, en: n.en || '', ku: n.ku || '' }));
                this.current = (this.notes[0] || {}).img || (this.images[0] || {}).value || null;
            },

            large() {
                const im = this.images.find(i => i.value === this.current) || this.images[0];
                return im ? (im.large || im.thumb) : '';
            },
            notesOn() { return this.notes.filter(n => n.img === this.current); },
            countOn(img) { return this.notes.filter(n => n.img === img).length; },

            spot(e) {
                const r = this.$refs.stage.getBoundingClientRect();
                return {
                    x: Math.round(Math.min(100, Math.max(0, (e.clientX - r.left) / r.width * 100)) * 10) / 10,
                    y: Math.round(Math.min(100, Math.max(0, (e.clientY - r.top) / r.height * 100)) * 10) / 10,
                };
            },
            add(e) {
                const at = this.spot(e);
                const note = { key: ++this.seq, img: this.current, x: at.x, y: at.y, en: '', ku: '' };
                this.notes.push(note);
                this.selected = note.key;
                this.save();
                this.$nextTick(() => {
                    const inputs = this.$root.querySelectorAll('.clp-note.is-on input');
                    if (inputs[0]) inputs[0].focus();
                });
            },
            grab(e, note) {
                this.selected = note.key;
                const move = ev => { const at = this.spot(ev); note.x = at.x; note.y = at.y; };
                const up = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                    this.save();
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },
            remove(note) {
                this.notes = this.notes.filter(n => n !== note);
                this.save();
            },
            // the form's state: every note, empty ones too while they are being
            // written (the model keeps only those with words when it is saved)
            save() {
                this.state = this.notes.map(n => ({ img: n.img, x: n.x, y: n.y, en: n.en, ku: n.ku }));
            },
        }));
    });
</script>
