{{-- Styles + Alpine component for the project cover / focal-point picker. --}}
<style>
    .cvp-empty { color: #9aa0a6; font-size: 0.85rem; padding: 0.4rem 0; }
    .cvp-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; align-items: start; }
    @media (max-width: 900px) { .cvp-wrap { grid-template-columns: 1fr; } }
    .cvp-label { font-size: 0.78rem; color: #c8c8c8; margin-bottom: 0.55rem; font-weight: 600; }
    .cvp-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .cvp-thumb {
        position: relative; width: 84px; height: 64px; padding: 0; cursor: pointer;
        border: 2px solid rgba(255,255,255,0.12); border-radius: 0.5rem; overflow: hidden;
        background: #141416;
    }
    .cvp-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cvp-thumb:hover { border-color: rgba(245,197,24,0.5); }
    .cvp-thumb.is-cover { border-color: #F5C518; box-shadow: 0 0 0 2px rgba(245,197,24,0.25); }
    .cvp-badge {
        position: absolute; left: 4px; bottom: 4px; background: #F5C518; color: #111;
        font-size: 0.6rem; font-weight: 700; padding: 1px 5px; border-radius: 4px; letter-spacing: .02em;
    }
    .cvp-stage {
        position: relative; width: 100%; max-width: 340px; aspect-ratio: 4 / 3;
        border-radius: 0.65rem; overflow: hidden; cursor: crosshair;
        border: 1px solid rgba(255,255,255,0.12); background: #141416;
    }
    .cvp-stage img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cvp-dot {
        position: absolute; width: 18px; height: 18px; transform: translate(-50%, -50%);
        border: 2px solid #fff; border-radius: 50%; box-shadow: 0 0 0 2px rgba(0,0,0,0.5), 0 0 8px rgba(0,0,0,.6);
        background: rgba(245,197,24,0.85); pointer-events: none;
    }
    .cvp-readout { margin-top: 0.5rem; font-size: 0.78rem; color: #9aa0a6; font-variant-numeric: tabular-nums; }
    .cvp-readout b { color: #e6e6e6; }
    .cvp-reset {
        margin-left: 0.6rem; background: none; border: 1px solid rgba(255,255,255,0.15);
        color: #c8c8c8; border-radius: 0.4rem; padding: 0.2rem 0.55rem; font-size: 0.72rem; cursor: pointer;
    }
    .cvp-reset:hover { border-color: #F5C518; color: #F5C518; }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('coverPicker', (config) => ({
            images: config.images || [],
            cover: null,
            x: 50,
            y: 50,

            init() {
                this.cover = this.$wire.get(config.coverPath);
                this.x = this._num(this.$wire.get(config.xPath), 50);
                this.y = this._num(this.$wire.get(config.yPath), 50);
                if (! this.cover && this.images.length) {
                    this.cover = this.images[0].value;   // display default; stays null until picked
                }
            },

            coverUrl() {
                const im = this.images.find(i => i.value === this.cover) || this.images[0];
                return im ? im.url : '';
            },

            pick(value) {
                this.cover = value;
                this.$wire.set(config.coverPath, value, false);
            },

            setFocus(e) {
                const r = this.$refs.stage.getBoundingClientRect();
                this.x = Math.round(Math.min(100, Math.max(0, ((e.clientX - r.left) / r.width) * 100)));
                this.y = Math.round(Math.min(100, Math.max(0, ((e.clientY - r.top) / r.height) * 100)));
                this.$wire.set(config.xPath, this.x, false);
                this.$wire.set(config.yPath, this.y, false);
            },

            reset() {
                this.x = 50; this.y = 50;
                this.$wire.set(config.xPath, 50, false);
                this.$wire.set(config.yPath, 50, false);
            },

            _num(v, d) { const n = parseInt(v, 10); return Number.isFinite(n) ? n : d; },
        }));
    });
</script>
