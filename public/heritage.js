/* =========================================================
   ARAM MIZURI ARCHITECTURE — heritage.js
   Kurdistan's architectural heritage (/kurdistan-heritage and each
   landmark's page): the map of Greater Kurdistan with its landmarks, and
   the filters by age and by part of Kurdistan.

   The border is the homepage map's own: the same data (kurdistan-data.js)
   and the same outline layer — the block between the two "copied" marks
   below is copied from script.js unchanged. If the border drawing changes
   there, copy it here again.
   ========================================================= */
(function () {
    'use strict';

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ── Filters: one age or one part of Kurdistan at a time ─────────── */
    const chips = document.querySelectorAll('.hr-chip');
    const cards = document.querySelectorAll('.hr-card');
    const groups = document.querySelectorAll('.hr-group');
    const dots = document.querySelectorAll('.hr-time__dot');
    const rows = document.querySelectorAll('.hr-time__row, .hr-time__label');
    let onFilter = null;     // set by the map, to dim its pins too
    // a landmark has one age and one part, but may be built of several
    // materials (data-material="basalt limestone"): any of them matches
    const has = (held, key, value) => key === 'material'
        ? (Array.isArray(held) ? held : String(held || '').split(' ')).includes(value)
        : held === value;
    function filter(key, value) {
        cards.forEach(card => { card.hidden = !!value && !has(card.dataset[key], key, value); });
        // an age with nothing left to show folds away
        groups.forEach(g => { g.hidden = !g.querySelector('.hr-card:not([hidden])'); });
        dots.forEach(d => d.classList.toggle('is-dim', !!value && !has(d.dataset[key], key, value)));
        document.querySelectorAll('.hr-mat__item').forEach(m => m.classList.toggle('is-on', key === 'material' && m.dataset.material === value));
        rows.forEach(r => r.classList.toggle('is-dim', key === 'era' && !!value && r.dataset.era !== value));
        rows.forEach(r => r.classList.toggle('is-on', key === 'era' && !!value && r.dataset.era === value));
        if (onFilter) onFilter(key, value);
    }
    chips.forEach(chip => chip.addEventListener('click', () => {
        chips.forEach(c => c.classList.toggle('is-on', c === chip));
        const [key, value] = (chip.dataset.filter || ':').split(':');
        filter(key, value);
    }));
    // an age's name on the timeline is the same filter — a second tap clears it
    document.querySelectorAll('.hr-time__label').forEach(label => label.addEventListener('click', () => {
        const on = label.classList.contains('is-on');
        const chip = document.querySelector('.hr-chip[data-filter="' + (on ? '' : 'era:' + label.dataset.era) + '"]');
        if (chip) chip.click();
    }));

    // "Show on the map" under a material (Built from the land), and a link
    // from a landmark's page (…?material=basalt#map): the same filter as the
    // material's chip, with the map brought into view
    function showMaterial(key, scroll) {
        const chip = document.querySelector('.hr-chip[data-filter="material:' + key + '"]');
        if (!chip) return;
        chip.click();
        const target = document.getElementById('map');
        if (scroll && target) target.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }
    document.querySelectorAll('[data-material-show]').forEach(b => b.addEventListener('click', () => showMaterial(b.dataset.materialShow, true)));
    const asked = new URLSearchParams(location.search).get('material');
    // once the map below has been set up, so its pins are filtered too
    if (asked && /^[a-z]+$/.test(asked)) setTimeout(() => showMaterial(asked, false), 0);

    /* ── The marks across time: lanes at the real width ──────────────── */
    // The page sets the lanes for a desktop-wide track; here they are redone
    // in pixels, so on a phone marks that would touch drop to another lane
    // instead of covering each other.
    const MARK = 24;   // a mark's width and the gap after it, in px (heritage.css --lane-h)
    const PAD = 12;    // heritage.css --pad
    function laneTime() {
        document.querySelectorAll('.hr-time__row').forEach(row => {
            const track = row.clientWidth - 2 * PAD;
            if (track <= 0) return;
            const marks = [...row.querySelectorAll('.hr-time__dot')]
                .map(d => ({ d, px: parseFloat(d.style.getPropertyValue('--x')) / 100 * track }))
                .sort((a, b) => a.px - b.px);
            const ends = [];
            marks.forEach(m => {
                let lane = 0;
                while (ends[lane] !== undefined && m.px - ends[lane] < MARK) lane++;
                ends[lane] = m.px;
                m.d.style.setProperty('--lane', lane);
            });
            row.style.setProperty('--lanes', Math.max(1, ends.length));
        });
    }
    if (document.querySelector('.hr-time__row')) {
        laneTime();
        let frame = 0;
        window.addEventListener('resize', () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(laneTime); });
    }

    const el = document.getElementById('hrMap');
    if (!el || typeof L === 'undefined') return;

    /* ── copied from script.js: the Kurdistan outline layer ─────────── */
    const GOLD = '#F5C518';
    const DEG  = Math.PI / 180;

    // Drop points closer together than this (Manhattan, CSS px) while building
    // the path. Measured at the zoomed-out view: no effect on fidelity, and it
    // cuts the 44k-point ring to a few thousand segments.
    const DECIMATE = 1;

    // Web-Mercator world-pixel coordinates at a given zoom (Leaflet's EPSG3857).
    // The canvas origin is derived with these same two functions, so any constant
    // offset cancels out and only the scale has to agree with Leaflet.
    function mercX(lng, world) { return world * (0.5 + lng / 360); }
    function mercY(lat, world) {
        const s = Math.max(-0.9999, Math.min(0.9999, Math.sin(lat * DEG)));
        return world * (0.5 - Math.log((1 + s) / (1 - s)) / (4 * Math.PI));
    }

    /* ── Seam bridging ──────────────────────────────────────────────────
       The source outline carries a lot of degenerate boundary: long, hair-thin
       excursions that run out and straight back on themselves. Some point
       inward — gaps where the polygons this region was stitched together from
       failed to meet, one of them 34km long and 450m wide — and some are
       outward spurs, down to a dead-straight line of no width at all. They are
       nothing as shapes but very loud as lines, which is exactly why the
       original SVG filter ran a closing pass over the whole map to bridge them.
       Drawing the outline honestly brought them all back into view, so bridge
       them here instead: once, in the geometry, at load.

       A seam is long and hair-thin — at least 2km of path, mean width (twice
       its area over its length) under 800m, and at least fifteen times longer
       than it is wide. A real bay or headland is nowhere near that slender and
       is kept untouched. On this dataset it drops about a tenth of the
       perimeter while moving the enclosed area by 0.02%, which is the
       signature of boundary that encloses nothing. */
    const SEAM_MAX_WIDTH  = 800;     // m — anything fatter is a real feature
    const SEAM_MIN_LENGTH = 2000;    // m — anything shorter is ordinary wiggle
    const SEAM_MIN_ASPECT = 15;      // length : width
    const SEAM_MAX_MOUTH  = 2500;    // m — how far apart the two ends may sit
    const SEAM_MAX_SPAN   = 25000;   // vertices

    function cleanRing(coords) {
        const n = coords.length;
        if (n < 500) return coords;          // the small islands have no seams

        // Local metric coordinates, plus prefix sums for the shoelace area and
        // the path length, so testing one candidate is O(1) instead of O(span).
        const X = new Float64Array(n), Y = new Float64Array(n);
        for (let i = 0; i < n; i++) {
            const lat = coords[i][1];
            X[i] = coords[i][0] * 111320 * Math.cos(lat * DEG);
            Y[i] = lat * 110540;
        }
        const csum = new Float64Array(n), lsum = new Float64Array(n);
        for (let i = 1; i < n; i++) {
            csum[i] = csum[i - 1] + (X[i - 1] * Y[i] - X[i] * Y[i - 1]);
            lsum[i] = lsum[i - 1] + Math.hypot(X[i] - X[i - 1], Y[i] - Y[i - 1]);
        }

        // Bucket the vertices so each one only looks at its own neighbourhood.
        const grid = new Map();
        const key = (x, y) => Math.floor(x / SEAM_MAX_MOUTH) + ',' + Math.floor(y / SEAM_MAX_MOUTH);
        for (let i = 0; i < n; i++) {
            const k = key(X[i], Y[i]);
            const cell = grid.get(k);
            if (cell) cell.push(i); else grid.set(k, [i]);
        }

        // For each vertex, the nearest vertex that is far away along the path:
        // the far side of a seam, if there is one. Sampled every third vertex —
        // a seam mouth spans far more than three, so this still finds every one
        // of them, and it keeps the whole pass down to a few tens of
        // milliseconds on a phone. It runs once, when the map scrolls in.
        const cands = [];
        for (let i = 0; i < n; i += 3) {
            const gx = Math.floor(X[i] / SEAM_MAX_MOUTH), gy = Math.floor(Y[i] / SEAM_MAX_MOUTH);
            let best = -1, bestD = Infinity, looked = 0;
            for (let dx = -1; dx <= 1 && looked < 600; dx++)
            for (let dy = -1; dy <= 1 && looked < 600; dy++) {
                const cell = grid.get((gx + dx) + ',' + (gy + dy));
                if (!cell) continue;
                // The marsh channels pack thousands of vertices into one cell;
                // cap the scan so load time stays linear in the worst case.
                for (let t = 0; t < cell.length && looked < 600; t++) {
                    looked++;
                    const j = cell[t], span = j - i;
                    if (span < 40 || span > SEAM_MAX_SPAN) continue;
                    const d = Math.hypot(X[j] - X[i], Y[j] - Y[i]);
                    if (d <= SEAM_MAX_MOUTH && d < bestD) { bestD = d; best = j; }
                }
            }
            if (best >= 0) cands.push(i * 100000 + (best - i));
        }
        // Longest first, so a seam is taken out in one piece rather than in bits.
        cands.sort((a, b) => (b % 100000) - (a % 100000));

        const drop = new Uint8Array(n);
        for (let c = 0; c < cands.length; c++) {
            const i = Math.floor(cands[c] / 100000), j = i + (cands[c] % 100000);
            if (drop[i] || drop[j]) continue;
            const length = lsum[j] - lsum[i];
            if (length < SEAM_MIN_LENGTH) continue;
            const area  = Math.abs(csum[j] - csum[i] + (X[j] * Y[i] - X[i] * Y[j])) / 2;
            const width = 2 * area / length;
            if (width > SEAM_MAX_WIDTH) continue;                  // a real feature
            if (length / Math.max(width, 1) < SEAM_MIN_ASPECT) continue;
            for (let k = i + 1; k <= j; k++) drop[k] = 1;
        }

        const out = [];
        for (let i = 0; i < n; i++) if (!drop[i]) out.push(coords[i]);
        if (out.length < 4) return coords;
        const first = out[0], last = out[out.length - 1];
        if (first[0] !== last[0] || first[1] !== last[1]) out.push(first);
        return out;
    }

    // Flatten the GeoJSON into typed arrays carrying their own lat/lng bbox, so a
    // redraw is a tight numeric loop and an off-screen ring costs one comparison.
    function buildRings(geojson) {
        const rings = [];
        const addRing = raw => {
            const coords = cleanRing(raw);
            const n = coords.length;
            if (n < 4) return;
            const pts = new Float64Array(n * 2);
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            for (let i = 0; i < n; i++) {
                const lng = coords[i][0], lat = coords[i][1];
                pts[i * 2] = lng; pts[i * 2 + 1] = lat;
                if (lng < minX) minX = lng;
                if (lng > maxX) maxX = lng;
                if (lat < minY) minY = lat;
                if (lat > maxY) maxY = lat;
            }
            rings.push({ pts, n, minX, minY, maxX, maxY });
        };
        const geom  = geojson.geometry || geojson;
        const polys = geom.type === 'MultiPolygon' ? geom.coordinates : [geom.coordinates];
        polys.forEach(poly => poly.forEach(addRing));
        return rings;
    }

    const KurdistanOutline = L.Layer.extend({
        // Slack around the viewport, so a two-finger pan doesn't run off the
        // painted area before the map settles and we redraw.
        options: { padding: 0.15 },

        initialize(rings) {
            this._rings  = rings;
            this._bounds = rings.reduce(
                (b, r) => b.extend([[r.minY, r.minX], [r.maxY, r.maxX]]),
                L.latLngBounds([[rings[0].minY, rings[0].minX], [rings[0].maxY, rings[0].maxX]])
            );
        },

        getBounds() { return this._bounds; },

        getEvents() {
            const events = {
                viewreset: this._schedule,
                moveend:   this._schedule,
                zoomend:   this._schedule,
                resize:    this._schedule,
                zoom:      this._onZoom,      // fires per frame of a pinch
            };
            if (this._zoomAnimated) events.zoomanim = this._onAnimZoom;
            return events;
        },

        onAdd(map) {
            const canvas = this._canvas = L.DomUtil.create('canvas', 'kurd-outline');
            if (this._zoomAnimated) L.DomUtil.addClass(canvas, 'leaflet-zoom-animated');
            this._ctx = canvas.getContext('2d');
            map.getPanes().overlayPane.appendChild(canvas);
            this._reset();
        },

        onRemove() {
            if (this._frame) { L.Util.cancelAnimFrame(this._frame); this._frame = null; }
            L.DomUtil.remove(this._canvas);
        },

        // moveend and zoomend both fire at the end of a zoom — coalesce them.
        _schedule() {
            if (this._frame) return;
            this._frame = L.Util.requestAnimFrame(function () {
                this._frame = null;
                this._reset();
            }, this);
        },

        _reset() {
            const map = this._map;
            if (!map) return;
            const size = map.getSize();
            const padX = Math.round(size.x * this.options.padding);
            const padY = Math.round(size.y * this.options.padding);
            const w = size.x + padX * 2, h = size.y + padY * 2;
            // Cap the backing store at 2× — beyond that the extra pixels cost
            // real milliseconds and buy nothing on a 2px line.
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const canvas = this._canvas;

            // Anchor the canvas, and remember that corner in lat/lng so the zoom
            // transform can re-derive where it belongs at any other scale.
            this._zoom0 = map.getZoom();
            this._nw0   = map.containerPointToLatLng([-padX, -padY]);
            L.DomUtil.setPosition(canvas, map.containerPointToLayerPoint([-padX, -padY]));

            const pxW = Math.round(w * dpr), pxH = Math.round(h * dpr);
            if (canvas.width  !== pxW) canvas.width  = pxW;
            if (canvas.height !== pxH) canvas.height = pxH;
            canvas.style.width  = w + 'px';
            canvas.style.height = h + 'px';

            this._draw(dpr);
        },

        _onZoom()      { this._transform(this._map.getCenter(), this._map.getZoom()); },
        _onAnimZoom(e) { this._transform(e.center, e.zoom); },

        // Scale the already-painted canvas along with the map rather than
        // repainting it — this is what makes a pinch pure GPU compositing.
        _transform(center, zoom) {
            const map = this._map;
            if (!map || !map._latLngToNewLayerPoint) return;
            const scale  = map.getZoomScale(zoom, this._zoom0);
            const offset = map._latLngToNewLayerPoint(this._nw0, zoom, center);
            L.DomUtil.setTransform(this._canvas, offset, scale);
        },

        _draw(dpr) {
            const map = this._map, ctx = this._ctx, canvas = this._canvas;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);   // draw in CSS pixels

            // Take the origin from Leaflet's own pixel origin plus wherever it
            // just placed this canvas, rather than re-deriving it from the map
            // centre. Leaflet rounds that origin; deriving it independently
            // leaves the paint up to half a pixel out of step with the tiles,
            // which a mid-pinch scale then multiplies into visible drift.
            const world  = 256 * Math.pow(2, this._zoom0);
            const origin = map.getPixelOrigin();
            const pos    = L.DomUtil.getPosition(canvas) || L.point(0, 0);
            const originX = origin.x + pos.x;
            const originY = origin.y + pos.y;

            const view  = map.getBounds().pad(this.options.padding + 0.05);
            const west  = view.getWest(),  east  = view.getEast();
            const south = view.getSouth(), north = view.getNorth();
            const degToPx = world / 360;

            let drawn = 0;
            ctx.beginPath();
            for (let r = 0; r < this._rings.length; r++) {
                const ring = this._rings[r];
                if (ring.maxX < west  || ring.minX > east ||
                    ring.maxY < south || ring.minY > north) continue;
                // Islands and enclaves smaller than a pixel: nothing to see.
                if ((ring.maxX - ring.minX) * degToPx < 1.5 &&
                    (ring.maxY - ring.minY) * degToPx < 1.5) continue;

                const pts = ring.pts, n = ring.n;
                let lastX = 0, lastY = 0, started = false;
                for (let i = 0; i < n; i++) {
                    const x = mercX(pts[i * 2],     world) - originX;
                    const y = mercY(pts[i * 2 + 1], world) - originY;
                    if (!started) {
                        ctx.moveTo(x, y);
                        lastX = x; lastY = y; started = true;
                        continue;
                    }
                    // Sub-pixel detail is invisible but not free — drop it.
                    if (i !== n - 1 && Math.abs(x - lastX) + Math.abs(y - lastY) < DECIMATE) continue;
                    ctx.lineTo(x, y);
                    lastX = x; lastY = y;
                }
                if (started) { ctx.closePath(); drawn++; }
            }
            if (!drawn) return;

            // Wash, then the border stroked exactly where the data puts it.
            ctx.fillStyle = 'rgba(245,197,24,.06)';
            ctx.fill('evenodd');

            ctx.strokeStyle = GOLD;
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.lineCap  = 'round';
            ctx.stroke();
        },
    });

    /* ── end of the copy from script.js ─────────────────────────────── */

    const single = !!el.dataset.single;
    const pins = JSON.parse(el.dataset.pins || '[]');
    if (!pins.length) return;

    const map = L.map(el, {
        zoomControl: true, scrollWheelZoom: false, attributionControl: true,
        // on the landmark's page the map is a picture of where it is; a finger
        // scrolling the page should scroll the page
        dragging: !(single && L.Browser.mobile), tap: false, minZoom: 4, maxZoom: 16,
    });
    L.tileLayer(el.dataset.tiles, {
        subdomains: 'abcd', maxZoom: 19, opacity: 0.92,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
    }).addTo(map);

    const markers = pins.map((p, i) => {
        const icon = L.divIcon({
            className: '', iconSize: [30, 30], iconAnchor: [15, 15],
            html: '<span class="hm-pin' + (p.unesco ? ' hm-pin--unesco' : '') + (single ? ' hm-pin--single' : '') + '"><span>' + esc(p.n) + '</span></span>',
        });
        const m = L.marker([p.lat, p.lng], { icon, keyboard: !single, alt: p.name }).addTo(map);
        if (!single) {
            m.on('click', () => show(i));
            m.bindTooltip(esc(p.name), { direction: 'top', offset: [0, -10], className: 'hr-tip' });
        }
        return m;
    });

    // zoomed out, the pins are small dots (close landmarks would overlap as
    // numbered discs); zoomed in, they carry their numbers
    const far = () => el.classList.toggle('is-far', !single && map.getZoom() < 7);
    map.on('zoomend', far);

    const all = L.latLngBounds(pins.map(p => [p.lat, p.lng]));
    if (single) map.setView([pins[0].lat, pins[0].lng], 6);
    else map.fitBounds(all, { padding: [40, 40] });
    far();

    // the border: the homepage's data file, fetched once the map is on screen
    let outline = null;
    function drawBorder() {
        if (typeof KURDISTAN_GEOJSON === 'undefined') return;
        const rings = buildRings(KURDISTAN_GEOJSON);
        if (!rings.length) return;
        outline = new KurdistanOutline(rings).addTo(map);
        if (!single) map.fitBounds(outline.getBounds(), { padding: [24, 24] });
    }
    const io = new IntersectionObserver(entries => {
        if (!entries[0].isIntersecting) return;
        io.disconnect();
        if (typeof KURDISTAN_GEOJSON !== 'undefined') return drawBorder();
        const s = document.createElement('script');
        s.src = el.dataset.outline;
        s.onload = drawBorder;
        document.head.appendChild(s);
    }, { rootMargin: '200px' });
    io.observe(el);

    if (single) return;

    /* ── The card a pin opens ───────────────────────────────────────── */
    const pop = document.createElement('div');
    pop.className = 'hr-pop';
    pop.hidden = true;
    el.parentNode.appendChild(pop);
    let openIdx = null;

    function show(i) {
        const p = pins[i];
        openIdx = i;
        pop.innerHTML =
            '<button type="button" class="hr-pop__close" aria-label="×">×</button>' +
            (p.img ? '<img class="hr-pop__img" src="' + esc(p.img) + '" alt="">' : '') +
            '<div class="hr-pop__body">' +
            '<p class="hr-pop__meta">' + esc(p.n) + ' · ' + esc(p.eraName) + (p.place ? ' <span dir="auto">· ' + esc(p.place) + '</span>' : '') + '</p>' +
            '<h3 class="hr-pop__name" dir="auto">' + esc(p.name) + '</h3>' +
            '<p class="hr-pop__period" dir="auto">' + esc(p.period) + '</p>' +
            (p.unesco ? '<p class="hr-pop__unesco">' + esc(el.dataset.unesco) + '</p>' : '') +
            (p.builtOf ? '<p class="hr-pop__built">' + esc(el.dataset.built) + ': ' + esc(p.builtOf) + '</p>' : '') +
            (p.summary ? '<p class="hr-pop__text" dir="auto">' + esc(p.summary) + '</p>' : '') +
            '<a class="hr-pop__more" href="' + esc(p.url) + '">' + esc(el.dataset.read) + ' <span class="pp-arrow" aria-hidden="true">→</span></a>' +
            '</div>';
        pop.hidden = false;
        pop.querySelector('.hr-pop__close').addEventListener('click', hide);
        markers.forEach((m, j) => { const e = m.getElement(); if (e) e.classList.toggle('is-open', j === i); });
        map.panTo([p.lat, p.lng], { animate: true });
    }

    // a mark on the timeline opens its landmark on the map
    dots.forEach(d => d.addEventListener('click', e => {
        const i = pins.findIndex(p => p.slug === (d.getAttribute('href') || '').slice(1));
        if (i < 0) return;
        e.preventDefault();
        show(i);
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }));
    function hide() {
        pop.hidden = true;
        openIdx = null;
        markers.forEach(m => { const e = m.getElement(); if (e) e.classList.remove('is-open'); });
    }
    map.on('click', hide);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && openIdx !== null) hide(); });

    // a filter dims the other pins and frames the ones left; a material
    // chosen also gives its pins the material's colour
    const colours = JSON.parse(el.dataset.colours || '{}');
    onFilter = (key, value) => {
        const shown = [];
        const colour = key === 'material' && value ? colours[value] : null;
        pins.forEach((p, i) => {
            const on = !value || has(p[key], key, value);
            const e = markers[i].getElement();
            if (e) {
                e.classList.toggle('is-dim', !on);
                e.classList.toggle('is-mat', on && !!colour);
                if (on && colour) e.style.setProperty('--mat', colour); else e.style.removeProperty('--mat');
            }
            if (on) shown.push([p.lat, p.lng]);
        });
        if (openIdx !== null && value && !has(pins[openIdx][key], key, value)) hide();
        if (!value) map.flyToBounds(outline ? outline.getBounds() : all, { padding: [24, 24], duration: .8 });
        else if (shown.length) map.flyToBounds(L.latLngBounds(shown), { padding: [60, 60], maxZoom: 8, duration: .8 });
    };
})();
