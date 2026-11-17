/* =========================================================
   The drawing tracer (Portfolio → Drawings, App\Filament\Forms\Components\DrawingTracer):
   the survey's scan under an SVG in cm, and on it the walls as closed outlines, doors, windows
   and openings along a wall, fixture/stair/overhead/context lines, room names and north.
   Its state is App\Models\Drawing's `data` (see there), kept in step with the form by Alpine.
   For a correction the original's walls come in as outlines to move, and its other lines lie
   faint underneath to trace against.
   ========================================================= */
document.addEventListener('alpine:init', () => {
    const clone = (o) => JSON.parse(JSON.stringify(o));
    const r1 = (v) => Math.round(v * 10) / 10;
    const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const path = (pts, close) => pts.length ? 'M' + pts.map((p) => p[0] + ' ' + p[1]).join('L') + (close ? 'Z' : '') : '';
    // complex numbers [re, im], for the scan's placing: cm = m·px + c
    const mul = (a, b) => [a[0] * b[0] - a[1] * b[1], a[0] * b[1] + a[1] * b[0]];
    const div = (a, b) => { const q = b[0] * b[0] + b[1] * b[1]; return [(a[0] * b[0] + a[1] * b[1]) / q, (a[1] * b[0] - a[0] * b[1]) / q]; };
    const sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
    const add = (a, b) => [a[0] + b[0], a[1] + b[1]];

    Alpine.data('drawingTracer', (cfg) => ({
        state: cfg.state,
        scanUrl: cfg.scan,
        base: cfg.base,
        lines: cfg.lines,
        tool: 'select',
        lineG: 'fix',
        openT: 45,
        opac: 0.6,
        showScan: true,
        d: null,
        hist: [],
        draft: [],
        sel: null,
        hint: '',
        img: null,

        init() {
            const s = this.state && typeof this.state === 'object' ? clone(this.state) : {};
            this.d = {
                walls: s.walls || [], openings: s.openings || [], lines: s.lines || [], rooms: s.rooms || [],
                north: s.north ?? null, scan: s.scan || { m: [1, 0], c: [0, 0] },
            };
            if (this.base && !this.d.walls.length) {
                this.d.walls = clone(this.base.walls);   // a correction starts from the original's walls
                this.state = clone(this.d);
            }
            this.svg = this.$refs.svg;
            if (this.scanUrl) {
                const im = new Image();
                im.onload = () => { this.img = { w: im.naturalWidth, h: im.naturalHeight }; this.fit(); };
                im.src = this.scanUrl;
            }
            this.fit();
            this.keys = (e) => this.key(e);
            window.addEventListener('keydown', this.keys);
            this.$watch('tool', () => { this.draft = []; this.setHint(); this.draw(); });
            this.$watch('opac', () => this.draw());
            this.$watch('showScan', () => this.draw());
            this.setHint();
        },
        destroy() { window.removeEventListener('keydown', this.keys); },

        /* ── keeping and undoing ── */
        save() { this.hist.push(JSON.stringify(this.d)); if (this.hist.length > 120) this.hist.shift(); },
        commit() { this.state = clone(this.d); this.draw(); },
        undo() { if (!this.hist.length) return; this.d = JSON.parse(this.hist.pop()); this.sel = null; this.commit(); },

        /* ── the view ── */
        fit() {
            const pts = [];
            this.d.walls.forEach((w) => pts.push(...w));
            this.d.lines.forEach((l) => pts.push(...l.p));
            this.d.openings.forEach((o) => pts.push(o.a, o.b));
            this.d.rooms.forEach((r) => pts.push([r.x, r.y]));
            if (this.base) { const [x, y, w, h] = this.base.vb; pts.push([x, y], [x + w, y + h]); }
            if (this.img && (!pts.length || this.showScan)) {
                [[0, 0], [this.img.w, 0], [0, this.img.h], [this.img.w, this.img.h]].forEach((p) => pts.push(add(mul(this.d.scan.m, p), this.d.scan.c)));
            }
            if (!pts.length) pts.push([0, 0], [1000, 800]);
            const xs = pts.map((p) => p[0]), ys = pts.map((p) => p[1]);
            const [x0, y0, x1, y1] = [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)];
            const m = Math.max(x1 - x0, y1 - y0) * 0.04 + 10;
            this.view = [x0 - m, y0 - m, x1 - x0 + 2 * m, y1 - y0 + 2 * m];
            this.draw();
        },
        at(e) {
            const p = this.svg.createSVGPoint();
            p.x = e.clientX; p.y = e.clientY;
            const q = p.matrixTransform(this.svg.getScreenCTM().inverse());
            return [q.x, q.y];
        },
        px() { const m = this.svg.getScreenCTM(); return m ? 1 / m.a : 1; },   // one screen pixel in cm
        wheel(e) {
            const p = this.at(e), k = e.deltaY > 0 ? 1.2 : 1 / 1.2;
            const [x, y, w, h] = this.view;
            this.view = [p[0] - (p[0] - x) * k, p[1] - (p[1] - y) * k, w * k, h * k];
            this.draw();
        },

        /* ── where a click lands: on a corner near it, square to the last point with Shift ── */
        snap(p, e, from) {
            const r = this.px() * 9;
            let best = null, bd = r;
            const near = (q) => { const dd = Math.hypot(q[0] - p[0], q[1] - p[1]); if (dd < bd) { bd = dd; best = q; } };
            this.d.walls.forEach((w) => w.forEach(near));
            this.d.lines.forEach((l) => l.p.forEach(near));
            if (best) return [best[0], best[1]];
            if (e.shiftKey && from) {
                return Math.abs(p[0] - from[0]) > Math.abs(p[1] - from[1]) ? [r1(p[0]), from[1]] : [from[0], r1(p[1])];
            }
            return [r1(p[0]), r1(p[1])];
        },

        /* ── the pointer ── */
        down(e) {
            if (e.button === 1 || (e.button === 0 && e.altKey && this.tool !== 'select')) return this.startPan(e);
            if (e.button !== 0) return;
            const p = this.at(e), t = e.target.dataset || {};
            const last = this.draft[this.draft.length - 1];

            switch (this.tool) {
                case 'select': {
                    if (t.h) {   // a point of an outline, a line, an opening or a room, to drag
                        const [kind, i, v] = t.h.split(':');
                        this.sel = { kind, i: +i, v: v === undefined ? null : (isNaN(+v) ? v : +v) };
                        if (e.altKey && (kind === 'wall' || kind === 'line')) return this.delPoint();
                        this.save();
                        this.drag = { moved: false };
                        this.svg.setPointerCapture(e.pointerId);
                    } else if (t.s) {   // an outline, a line or an opening itself
                        const [kind, i] = t.s.split(':');
                        this.sel = { kind, i: +i, v: null };
                    } else {
                        this.sel = null;
                        return this.startPan(e);
                    }
                    return this.draw();
                }
                case 'wall':
                case 'line': {
                    const q = this.snap(p, e, last);
                    if (this.tool === 'wall' && this.draft.length > 2 && Math.hypot(q[0] - this.draft[0][0], q[1] - this.draft[0][1]) < this.px() * 9) {
                        return this.finish();
                    }
                    this.draft.push(q);
                    return this.draw();
                }
                case 'door':
                case 'window':
                case 'gap': {
                    this.draft.push(this.snap(p, e, last));
                    if (this.draft.length === 2) {
                        this.save();
                        this.d.openings.push({ k: this.tool, a: this.draft[0], b: this.draft[1], t: +this.openT || 45, flip: false });
                        this.sel = { kind: 'open', i: this.d.openings.length - 1, v: null };
                        this.draft = [];
                        return this.commit();
                    }
                    return this.draw();
                }
                case 'room': {
                    this.save();
                    this.d.rooms.push({ x: r1(p[0]), y: r1(p[1]), en: '@room', ku: '', ar: '' });
                    this.sel = { kind: 'room', i: this.d.rooms.length - 1, v: null };
                    this.tool = 'select';
                    return this.commit();
                }
                case 'scale': {
                    this.draft.push(this.snap(p, e, last));
                    if (this.draft.length < 2) return this.draw();
                    const [a, b] = this.draft, now = Math.hypot(b[0] - a[0], b[1] - a[1]);
                    const m = parseFloat(String(window.prompt('How far apart are these two points, in metres?', r1(now / 100)) || '').replace(',', '.'));
                    this.draft = [];
                    if (!(m > 0) || now < 1) return this.draw();
                    // the scan and everything traced on it grow or shrink together, about the first point
                    const k = (m * 100) / now, f = (q) => [r1(a[0] + (q[0] - a[0]) * k), r1(a[1] + (q[1] - a[1]) * k)];
                    this.save();
                    this.mapAll(f, k);
                    this.d.scan = { m: [this.d.scan.m[0] * k, this.d.scan.m[1] * k], c: add(a, mul([k, 0], sub(this.d.scan.c, a))) };
                    this.tool = 'select';
                    this.commit();
                    return this.fit();
                }
                case 'align': {
                    // a point on the scan, the same point on the drawing; twice
                    this.draft.push([r1(p[0]), r1(p[1])]);
                    if (this.draft.length < 4) { this.setHint(); return this.draw(); }
                    const [s1, d1, s2, d2] = this.draft;
                    const toPx = (q) => div(sub(q, this.d.scan.c), this.d.scan.m);
                    const a = toPx(s1), b = toPx(s2);
                    this.draft = [];
                    if (Math.hypot(b[0] - a[0], b[1] - a[1]) < 1) return this.draw();
                    this.save();
                    const m = div(sub(d2, d1), sub(b, a));
                    this.d.scan = { m, c: sub(d1, mul(m, a)) };
                    this.tool = 'select';
                    return this.commit();
                }
            }
        },
        move(e) {
            if (this.pan) {
                const k = this.px();
                this.view = [this.pan.x - (e.clientX - this.pan.cx) * k, this.pan.y - (e.clientY - this.pan.cy) * k, this.view[2], this.view[3]];
                return this.draw();
            }
            this.cursor = this.at(e);
            if (this.drag && this.sel) {
                const s = this.sel, last = s.kind === 'wall' ? this.d.walls[s.i][(s.v + this.d.walls[s.i].length - 1) % this.d.walls[s.i].length] : null;
                const q = e.shiftKey && last ? this.snap(this.cursor, e, last) : [r1(this.cursor[0]), r1(this.cursor[1])];
                if (s.kind === 'wall') this.d.walls[s.i][s.v] = q;
                else if (s.kind === 'line') this.d.lines[s.i].p[s.v] = q;
                else if (s.kind === 'room') { this.d.rooms[s.i].x = q[0]; this.d.rooms[s.i].y = q[1]; }
                else if (s.kind === 'open') this.d.openings[s.i][s.v] = q;
                this.drag.moved = true;
                return this.draw();
            }
            if (this.draft.length) this.draw();
        },
        up() {
            if (this.pan) { this.pan = null; return; }
            if (this.drag) {
                if (this.drag.moved) this.commit(); else this.hist.pop();
                this.drag = null;
            }
        },
        startPan(e) {
            this.pan = { cx: e.clientX, cy: e.clientY, x: this.view[0], y: this.view[1] };
            this.svg.setPointerCapture(e.pointerId);
        },
        dbl(e) {
            // a new point on an outline's or a line's side, where it was double-clicked
            const t = e.target.dataset || {};
            if (this.tool !== 'select' || !t.s) return;
            const [kind, i] = t.s.split(':');
            if (kind !== 'wall' && kind !== 'line') return;
            const pts = kind === 'wall' ? this.d.walls[+i] : this.d.lines[+i].p, p = this.at(e);
            let best = 0, bd = Infinity;
            const n = kind === 'wall' ? pts.length : pts.length - 1;
            for (let j = 0; j < n; j++) {
                const a = pts[j], b = pts[(j + 1) % pts.length], ab = sub(b, a), L = ab[0] * ab[0] + ab[1] * ab[1] || 1;
                const u = Math.max(0, Math.min(1, ((p[0] - a[0]) * ab[0] + (p[1] - a[1]) * ab[1]) / L));
                const dd = Math.hypot(a[0] + ab[0] * u - p[0], a[1] + ab[1] * u - p[1]);
                if (dd < bd) { bd = dd; best = j; }
            }
            this.save();
            pts.splice(best + 1, 0, [r1(p[0]), r1(p[1])]);
            this.sel = { kind, i: +i, v: best + 1 };
            this.commit();
        },
        finish() {
            if (this.tool === 'wall' && this.draft.length > 2) {
                this.save();
                this.d.walls.push(this.draft);
                this.sel = { kind: 'wall', i: this.d.walls.length - 1, v: null };
            } else if (this.tool === 'line' && this.draft.length > 1) {
                this.save();
                this.d.lines.push({ g: this.lineG, p: this.draft });
                this.sel = { kind: 'line', i: this.d.lines.length - 1, v: null };
            }
            this.draft = [];
            this.commit();
        },
        key(e) {
            if (!this.$root.isConnected || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable) return;
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); return this.undo(); }
            if (e.key === 'Escape') { this.draft = []; this.sel = null; return this.draw(); }
            if (e.key === 'Enter' && this.draft.length) { e.preventDefault(); return this.finish(); }
            if ((e.key === 'Delete' || e.key === 'Backspace') && this.sel) {
                e.preventDefault();
                return this.sel.v !== null && typeof this.sel.v === 'number' ? this.delPoint() : this.delSel();
            }
        },
        delPoint() {
            const s = this.sel, pts = s.kind === 'wall' ? this.d.walls[s.i] : s.kind === 'line' ? this.d.lines[s.i].p : null;
            if (!pts) return;
            this.save();
            if (pts.length > (s.kind === 'wall' ? 3 : 2)) pts.splice(s.v, 1);
            else (s.kind === 'wall' ? this.d.walls : this.d.lines).splice(s.i, 1);
            this.sel = null;
            this.commit();
        },
        delSel() {
            const lists = { wall: this.d.walls, line: this.d.lines, open: this.d.openings, room: this.d.rooms };
            if (!this.sel || !lists[this.sel.kind]) return;
            this.save();
            lists[this.sel.kind].splice(this.sel.i, 1);
            this.sel = null;
            this.commit();
        },
        reset() {
            if (!this.base || !window.confirm('Put the original walls back in place of the traced ones?')) return;
            this.save();
            this.d.walls = clone(this.base.walls);
            this.commit();
        },
        mapAll(f, k) {
            this.d.walls = this.d.walls.map((w) => w.map(f));
            this.d.lines.forEach((l) => { l.p = l.p.map(f); });
            this.d.openings.forEach((o) => { o.a = f(o.a); o.b = f(o.b); o.t = r1(o.t * k); });
            this.d.rooms.forEach((r) => { [r.x, r.y] = f([r.x, r.y]); });
        },
        // an edit in the side panel (a room's names, an opening's kind)
        edited() { this.commit(); },
        get picked() {
            const s = this.sel;
            if (!s) return null;
            return s.kind === 'room' ? this.d.rooms[s.i] : s.kind === 'open' ? this.d.openings[s.i] : s.kind === 'line' ? this.d.lines[s.i] : s.kind === 'wall' ? this.d.walls[s.i] : null;
        },
        setNorth(v) { this.save(); this.d.north = v === '' || v === null ? null : ((+v % 360) + 360) % 360; this.commit(); },
        setHint() {
            this.hint = {
                select: 'Drag a point to move it · double-click a side to add a point · Alt-click or Delete removes one · drag the empty paper to move about, the wheel to zoom.',
                wall: 'Click round the wall’s outline, corner by corner; click the first point (or Enter) to close it. Shift keeps it square; corners snap. Draw an inner outline round a court or room the same way.',
                line: 'Click the points of the line; Enter to end it. Shift keeps it square.',
                door: 'Click the hinge side, then the other side, on the middle of the wall. Flip it in the panel to swing the other way.',
                window: 'Click one side of the window, then the other, on the middle of the wall.',
                gap: 'Click one side of the opening, then the other, on the middle of the wall.',
                room: 'Click where the room’s name goes; write it in the panel.',
                scale: 'Click two points whose distance you know (a dimension on the scan); then type it in metres.',
                align: ['Click a point on the scan…', '…then where that point is on the drawing.', 'Now a second point on the scan, far from the first…', '…and where it is on the drawing.'][this.draft.length] || '',
            }[this.tool];
        },

        /* ── drawn ── */
        draw() {
            if (!this.view || !this.svg) return;
            this.svg.setAttribute('viewBox', this.view.join(' '));
            const k = this.px(), d = this.d, s = this.sel, hr = 4.5 * k;
            let o = '';
            if (this.img && this.showScan) {
                const [a, b] = d.scan.m, [e, f] = d.scan.c;
                o += `<image href="${esc(this.scanUrl)}" width="${this.img.w}" height="${this.img.h}" opacity="${this.opac}" transform="matrix(${a} ${b} ${-b} ${a} ${e} ${f})" preserveAspectRatio="none" style="pointer-events:none"/>`;
            }
            if (this.base) {
                o += `<path class="dt-ref" d="${this.base.ref}" style="stroke-width:${k}"/>`;
                o += `<path class="dt-orig" d="${this.base.walls.map((w) => path(w, true)).join('')}" style="stroke-width:${k}"/>`;
            }
            o += `<path class="dt-poche" fill-rule="evenodd" d="${d.walls.map((w) => path(w, true)).join('')}"/>`;
            d.walls.forEach((w, i) => {
                o += `<path class="dt-wall${s && s.kind === 'wall' && s.i === i ? ' is-sel' : ''}" d="${path(w, true)}" style="stroke-width:${1.6 * k}"/>`;
                o += `<path class="dt-hit" data-s="wall:${i}" d="${path(w, true)}" style="stroke-width:${10 * k}"/>`;
            });
            d.lines.forEach((l, i) => {
                o += `<path class="dt-line dt-${l.g}${s && s.kind === 'line' && s.i === i ? ' is-sel' : ''}" d="${path(l.p)}" style="stroke-width:${1.2 * k}"/>`;
                o += `<path class="dt-hit" data-s="line:${i}" d="${path(l.p)}" style="stroke-width:${10 * k}"/>`;
            });
            d.openings.forEach((p, i) => {
                const len = Math.hypot(p.b[0] - p.a[0], p.b[1] - p.a[1]) || 1, n = [-(p.b[1] - p.a[1]) / len, (p.b[0] - p.a[0]) / len], h = p.t / 2;
                const q = (pt, t) => [pt[0] + n[0] * t, pt[1] + n[1] * t];
                o += `<path class="dt-open dt-${p.k}${s && s.kind === 'open' && s.i === i ? ' is-sel' : ''}" data-s="open:${i}" d="${path([q(p.a, -h), q(p.b, -h), q(p.b, h), q(p.a, h)], true)}" style="stroke-width:${1.4 * k}"/>`;
                if (p.k === 'door') {
                    const sg = p.flip ? -1 : 1, hinge = q(p.a, sg * h), end = q(p.a, sg * (h + len));
                    o += `<path class="dt-leaf" d="${path([hinge, end])}" style="stroke-width:${1.4 * k}"/>`;
                }
            });
            d.rooms.forEach((r, i) => {
                o += `<text class="dt-room${s && s.kind === 'room' && s.i === i ? ' is-sel' : ''}" x="${r.x}" y="${r.y}" style="font-size:${13 * k}px">${esc(r.en || '…')}</text>`;
            });
            // handles: every point of what is picked, the corners of every outline while picking
            const dot = (pt, id, on) => `<circle class="dt-h${on ? ' is-on' : ''}" data-h="${id}" cx="${pt[0]}" cy="${pt[1]}" r="${on ? hr * 1.3 : hr}" style="stroke-width:${k}"/>`;
            if (this.tool === 'select') {
                d.walls.forEach((w, i) => w.forEach((pt, v) => { o += dot(pt, `wall:${i}:${v}`, s && s.kind === 'wall' && s.i === i && s.v === v); }));
                d.lines.forEach((l, i) => l.p.forEach((pt, v) => { o += dot(pt, `line:${i}:${v}`, s && s.kind === 'line' && s.i === i && s.v === v); }));
                d.openings.forEach((p, i) => { o += dot(p.a, `open:${i}:a`) + dot(p.b, `open:${i}:b`); });
                d.rooms.forEach((r, i) => { o += dot([r.x, r.y], `room:${i}`, s && s.kind === 'room' && s.i === i); });
            }
            if (this.draft.length) {
                const pts = this.cursor && !['align'].includes(this.tool) ? [...this.draft, this.cursor] : this.draft;
                o += `<path class="dt-draft" d="${path(pts)}" style="stroke-width:${1.4 * k}"/>`;
                this.draft.forEach((pt, j) => { o += `<circle class="dt-dp" cx="${pt[0]}" cy="${pt[1]}" r="${hr}"/>`; if (this.tool === 'align') o += `<text class="dt-room" x="${pt[0] + hr * 2}" y="${pt[1] - hr * 2}" style="font-size:${12 * k}px">${['scan 1', 'drawing 1', 'scan 2', 'drawing 2'][j]}</text>`; });
            }
            this.svg.innerHTML = o;
        },
    }));
});
