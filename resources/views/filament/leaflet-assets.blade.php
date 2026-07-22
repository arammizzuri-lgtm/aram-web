{{-- Leaflet assets + the map-picker Alpine component for the admin panel.
     Loaded once into every panel page <head> so the project form's map
     picker (and any future map field) can initialise. Mirrors the public
     site: Leaflet 1.9.4 + CartoDB dark tiles. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .amp-wrap { --amp-gold: #F5C518; }
    .amp-search { display: flex; gap: 0.5rem; margin-bottom: 0.6rem; }
    .amp-search input {
        flex: 1; background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.12); border-radius: 0.5rem;
        color: #e6e6e6; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none;
    }
    .amp-search input:focus { border-color: var(--amp-gold); }
    .amp-search button {
        background: rgba(245,197,24,0.14); color: var(--amp-gold);
        border: 1px solid rgba(245,197,24,0.35); border-radius: 0.5rem;
        padding: 0 0.9rem; font-size: 0.8rem; font-weight: 600; cursor: pointer;
        white-space: nowrap;
    }
    .amp-search button:hover { background: var(--amp-gold); color: #111; }
    .amp-map {
        height: 380px; width: 100%; border-radius: 0.75rem;
        border: 1px solid rgba(255,255,255,0.12); overflow: hidden; z-index: 0;
        background: #1a1a1c;
    }
    .amp-foot {
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.75rem; margin-top: 0.55rem; font-size: 0.8rem; color: #9aa0a6;
    }
    .amp-coords { font-variant-numeric: tabular-nums; }
    .amp-coords b { color: #e6e6e6; font-weight: 600; }
    .amp-clear {
        background: none; border: 1px solid rgba(255,255,255,0.15);
        color: #c8c8c8; border-radius: 0.5rem; padding: 0.3rem 0.7rem;
        font-size: 0.75rem; cursor: pointer;
    }
    .amp-clear:hover { border-color: #e06666; color: #e06666; }
    .amp-pin svg { filter: drop-shadow(0 2px 3px rgba(0,0,0,0.5)); }
    .leaflet-container { background: #1a1a1c; }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('mapPicker', (config) => ({
            map: null,
            marker: null,
            lat: null,
            lng: null,
            searching: false,

            init() {
                this.lat = this._num(this.$wire.get(config.latPath));
                this.lng = this._num(this.$wire.get(config.lngPath));
                this.$nextTick(() => this.build());
            },

            build() {
                const has = this.lat !== null && this.lng !== null;
                const map = L.map(this.$refs.map, {
                    zoomControl: true,
                    attributionControl: false,
                    scrollWheelZoom: true,
                }).setView(has ? [this.lat, this.lng] : config.center, has ? 15 : config.zoom);

                L.tileLayer(config.tiles, {
                    subdomains: 'abcd', maxZoom: 19, detectRetina: true,
                }).addTo(map);

                this.map = map;
                if (has) this._drawMarker(this.lat, this.lng);

                map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng, false));

                // Leaflet measures the container at init; sections/tabs may be
                // hidden or resize, so re-measure once it is actually laid out.
                setTimeout(() => map.invalidateSize(), 250);
                if (window.ResizeObserver) {
                    new ResizeObserver(() => map.invalidateSize()).observe(this.$refs.map);
                }
            },

            place(lat, lng, fly) {
                lat = this._round(lat);
                lng = this._round(lng);
                this._drawMarker(lat, lng);
                if (fly) this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15));
                else this.map.panTo([lat, lng]);
                this._commit(lat, lng);
            },

            _drawMarker(lat, lng) {
                if (!this.marker) {
                    this.marker = L.marker([lat, lng], {
                        draggable: true,
                        icon: L.divIcon({
                            className: 'amp-pin',
                            html: '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 23s7-7.6 7-13A7 7 0 1 0 5 10c0 5.4 7 13 7 13z" fill="#F5C518" stroke="#111" stroke-width="1.2"/><circle cx="12" cy="10" r="2.7" fill="#111"/></svg>',
                            iconSize: [30, 30], iconAnchor: [15, 29],
                        }),
                    }).addTo(this.map);
                    this.marker.on('dragend', () => {
                        const p = this.marker.getLatLng();
                        this._commit(this._round(p.lat), this._round(p.lng));
                    });
                } else {
                    this.marker.setLatLng([lat, lng]);
                }
            },

            _commit(lat, lng) {
                this.lat = lat;
                this.lng = lng;
                this.$wire.set(config.latPath, lat, false);
                this.$wire.set(config.lngPath, lng, false);
            },

            clear() {
                this.lat = this.lng = null;
                if (this.marker) { this.map.removeLayer(this.marker); this.marker = null; }
                this.$wire.set(config.latPath, null, false);
                this.$wire.set(config.lngPath, null, false);
            },

            async search(q) {
                q = (q || '').trim();
                if (!q || this.searching) return;
                this.searching = true;
                try {
                    const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
                        + encodeURIComponent(q);
                    const res = await fetch(url, { headers: { 'Accept-Language': 'en' } });
                    const hits = await res.json();
                    if (hits && hits[0]) {
                        this.place(parseFloat(hits[0].lat), parseFloat(hits[0].lon), true);
                    }
                } catch (e) { /* offline / rate-limited — silently ignore */ }
                this.searching = false;
            },

            _round(v) { return Math.round(parseFloat(v) * 1e6) / 1e6; },
            _num(v) {
                if (v === null || v === undefined || v === '') return null;
                const n = parseFloat(v);
                return Number.isFinite(n) ? n : null;
            },
        }));
    });
</script>
