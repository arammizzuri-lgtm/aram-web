/* =========================================================
   ARAM MIZURI ARCHITECTURE — estimate-house.js
   The cost estimator's drawing (/estimate): the visitor's house drawn as
   an architect's axonometric beside the figures — the plot inside its
   boundary line, the house on it, a basement dug below the ground line,
   a pool and trees in the garden — from the same answers the figures
   come from. As an answer changes the drawing grows into its new shape:
   walls rise floor by floor, windows come as each floor is finished.
   estimate.js passes each new set of numbers ("estimate:house").
   ========================================================= */
(function () {
    'use strict';

    const fig = document.getElementById('esHouse');
    if (!fig) return;
    const svg = fig.querySelector('svg');
    const text = (function () {
        try { return JSON.parse(document.getElementById('esText').textContent) || {}; } catch (e) { return {}; }
    })();
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rtl = document.documentElement.getAttribute('dir') === 'rtl';

    const VW = 560, VH = 360;
    const COS = Math.cos(Math.PI / 6), SIN = 0.5;
    const STOREY = 3.2, PARAPET = 0.8, DIG = 3.2;          // metres
    const FIELDS = ['pw', 'pd', 'hw', 'hd', 'floors', 'basement', 'pool'];

    const fmt = n => Math.round(n).toLocaleString('en');
    const say = (key, n) => (text[key] || '').replace('{n}', fmt(n));
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // From the estimate's numbers to the drawing's, in metres. A plot is
    // drawn half as deep again as it is wide, a house a little deeper than
    // wide, and never more of the plot than leaves a garden in front.
    function shape(h) {
        const plot = Math.max(60, h.plot || 0);
        const pw = Math.sqrt(plot / 1.5), pd = pw * 1.5;
        const fp = Math.max(25, Math.min(h.footprint || 0, plot * 0.9));
        const hw = Math.min(Math.sqrt(fp / 1.15), pw - 1.2);
        const hd = Math.min(fp / hw, pd * 0.7);
        return { pw, pd, hw, hd, floors: Math.max(1, h.floors || 1), basement: h.basement ? 1 : 0, pool: h.pool ? 1 : 0 };
    }
    // the plot alone, before there is anything to build on it
    const emptyShape = { pw: 16, pd: 24, hw: 9, hd: 11, floors: 0, basement: 0, pool: 0 };

    /* ---- The drawing, from a shape (and the words to put on it) ------- */
    function draw(g, h) {
        const top = g.floors * STOREY;
        const crown = top + PARAPET * Math.min(1, g.floors);
        const back = Math.min(2, g.pd * 0.06);
        const u0 = (g.pw - g.hw) / 2, u1 = u0 + g.hw;
        const v0 = back, v1 = back + g.hd;
        const dig = DIG * g.basement;

        // the pool in the garden in front, as long as the garden allows
        let pool = null;
        if (g.pool > 0.01) {
            const garden = g.pd - v1;
            const lw = Math.min(8, g.pw * 0.5) * g.pool;
            const lv = Math.max(1, Math.min(4, garden - 1.6)) * g.pool;
            const pu = Math.max(0.6, u0 + 0.3);
            pool = { u0: pu, u1: pu + lw, v0: v1 + 0.8, v1: v1 + 0.8 + lv };
        }

        // where it all lands on the page: fitted, with room for the words
        const P = (u, v, z) => [(u - v) * COS, (u + v) * SIN - z];
        const pts = [P(0, 0, 0), P(g.pw, 0, 0), P(g.pw, g.pd, 0), P(0, g.pd, 0),
            P(u0, v0, crown), P(u1, v0, crown), P(u1, v1, crown), P(u0, v1, crown),
            P(u0, v1, -dig), P(u1, v1, -dig), P(u1, v0, -dig)];
        const xs = pts.map(p => p[0]), ys = pts.map(p => p[1]);
        const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
        const L = 18, R = 128, T = 14, B = 38;
        const k = Math.min((VW - L - R) / (maxX - minX || 1), (VH - T - B) / (maxY - minY || 1));
        const ox = L + ((VW - L - R) - (maxX - minX) * k) / 2 - minX * k;
        const oy = T + ((VH - T - B) - (maxY - minY) * k) / 2 - minY * k;
        const at = (u, v, z) => { const p = P(u, v, z); return [ox + p[0] * k, oy + p[1] * k]; };
        const pt = p => p[0].toFixed(1) + ',' + p[1].toFixed(1);
        const poly = (cls, ...corners) => '<polygon class="' + cls + '" points="' + corners.map(c => pt(at(...c))).join(' ') + '"/>';
        const line = (cls, a, b) => { const p = at(...a), q = at(...b); return '<line class="' + cls + '" x1="' + p[0].toFixed(1) + '" y1="' + p[1].toFixed(1) + '" x2="' + q[0].toFixed(1) + '" y2="' + q[1].toFixed(1) + '"/>'; };
        const label = (cls, p, words, anchor, dy) => '<text class="' + cls + '" x="' + p[0].toFixed(1) + '" y="' + (p[1] + (dy || 0)).toFixed(1) + '" text-anchor="' + anchor + '" direction="' + (rtl ? 'rtl' : 'ltr') + '">' + esc(words) + '</text>';
        const outward = rtl ? 'end' : 'start';            // a label reading away from the drawing, to its right
        const inward = rtl ? 'start' : 'end';             // …and one reading away to its left

        let out = '';

        // 1. the plot and its boundary
        out += poly('h-plot', [0, 0, 0], [g.pw, 0, 0], [g.pw, g.pd, 0], [0, g.pd, 0]);

        // 2. dug below the ground line: the basement, cut open and hatched
        if (dig > 0.05) {
            out += poly('h-dig', [u0, v1, 0], [u1, v1, 0], [u1, v1, -dig], [u0, v1, -dig]);
            out += poly('h-dig h-dig--side', [u1, v1, 0], [u1, v0, 0], [u1, v0, -dig], [u1, v1, -dig]);
            out += line('h-ground', [u0 - 0.8, v1, 0], [u1, v1, 0]) + line('h-ground', [u1, v1, 0], [u1, v0 - 0.8, 0]);
        }

        // 3. the garden: trees, drawn as a plan draws them, and the pool
        const trees = [[0.12, 0.88], [0.86, 0.9], [0.9, 0.12]];
        trees.forEach(([fu, fv], i) => {
            const c = at(g.pw * fu, g.pd * fv, 0);
            const r = Math.max(5, Math.min(14, g.pw * 0.06 * k));
            const covered = fv * g.pd > v0 - 1 && fv * g.pd < v1 + 1 && fu * g.pw > u0 - 1 && fu * g.pw < u1 + 1;
            if (covered || (pool && fv * g.pd > pool.v0 - 1 && fu * g.pw < pool.u1 + 1.5 && fv * g.pd < pool.v1 + 1.5)) return;
            out += '<ellipse class="h-tree" cx="' + c[0].toFixed(1) + '" cy="' + c[1].toFixed(1) + '" rx="' + (r * 1.22).toFixed(1) + '" ry="' + (r * 0.71).toFixed(1) + '"/>';
            out += '<path class="h-tree h-tree--x" d="M' + (c[0] - r * 0.6).toFixed(1) + ' ' + c[1].toFixed(1) + 'h' + (r * 1.2).toFixed(1) + 'M' + c[0].toFixed(1) + ' ' + (c[1] - r * 0.35).toFixed(1) + 'v' + (r * 0.7).toFixed(1) + '"/>';
        });
        if (pool) {
            const inset = 0.35 * g.pool;
            out += poly('h-pool', [pool.u0, pool.v0, 0], [pool.u1, pool.v0, 0], [pool.u1, pool.v1, 0], [pool.u0, pool.v1, 0]);
            out += poly('h-water', [pool.u0 + inset, pool.v0 + inset, -0.3], [pool.u1 - inset, pool.v0 + inset, -0.3], [pool.u1 - inset, pool.v1 - inset, -0.3], [pool.u0 + inset, pool.v1 - inset, -0.3]);
            for (let i = 1; i <= 2; i++) {
                const v = pool.v0 + (pool.v1 - pool.v0) * i / 3;
                out += line('h-ripple', [pool.u0 + (pool.u1 - pool.u0) * 0.25, v, -0.3], [pool.u0 + (pool.u1 - pool.u0) * 0.6, v, -0.3]);
            }
        }

        // 4. the house: its two faces to us, then its roof, floor lines,
        //    windows and door, and its edges drawn over all
        if (top > 0.02) {
            out += poly('h-face h-face--side', [u1, v1, 0], [u1, v0, 0], [u1, v0, crown], [u1, v1, crown]);
            out += poly('h-face h-face--front', [u0, v1, 0], [u1, v1, 0], [u1, v1, crown], [u0, v1, crown]);
            out += poly('h-roof', [u0, v0, crown], [u1, v0, crown], [u1, v1, crown], [u0, v1, crown]);

            const full = Math.floor(g.floors + 0.001);
            for (let s = 1; s <= full; s++) {
                const z = Math.min(s * STOREY, top);
                out += line('h-floor', [u0, v1, z], [u1, v1, z]) + line('h-floor', [u1, v1, z], [u1, v0, z]);
            }
            const bays = Math.max(1, Math.floor(g.hw / 3.4)), sideBays = Math.max(1, Math.floor(g.hd / 4.4));
            for (let s = 0; s < full; s++) {
                const z = s * STOREY;
                for (let b = 0; b < bays; b++) {
                    const mid = u0 + g.hw * (b + 0.5) / bays;
                    const door = s === 0 && b === Math.floor((bays - 1) / 2);
                    const w = door ? Math.min(1.1, g.hw / bays * 0.45) : Math.min(1.6, g.hw / bays * 0.55);
                    const z0 = z + (door ? 0 : 0.9), z1 = z + (door ? 2.3 : 2.3);
                    out += poly(door ? 'h-door' : 'h-win', [mid - w / 2, v1, z0], [mid + w / 2, v1, z0], [mid + w / 2, v1, z1], [mid - w / 2, v1, z1]);
                }
                for (let b = 0; b < sideBays; b++) {
                    const mid = v0 + g.hd * (b + 0.5) / sideBays, w = Math.min(0.9, g.hd / sideBays * 0.3);
                    out += poly('h-win h-win--side', [u1, mid + w / 2, z + 0.8], [u1, mid - w / 2, z + 0.8], [u1, mid - w / 2, z + 2.4], [u1, mid + w / 2, z + 2.4]);
                }
            }
            out += line('h-edge', [u0, v1, 0], [u0, v1, crown]) + line('h-edge', [u1, v1, 0], [u1, v1, crown]) + line('h-edge', [u1, v0, 0], [u1, v0, crown]);
            out += line('h-edge', [u0, v1, 0], [u1, v1, 0]) + line('h-edge', [u1, v1, 0], [u1, v0, 0]);
            out += line('h-edge h-edge--crown', [u0, v1, crown], [u1, v1, crown]) + line('h-edge h-edge--crown', [u1, v1, crown], [u1, v0, crown]);
        }

        // 5. the words: the height, dimensioned beside the house; the plot,
        //    along its front; the basement and the pool where they are
        if (h) {
            if (top > 0.02) {
                const dimU = u1 + Math.max(1.4, g.pw * 0.06);
                const a = at(dimU, v0, 0), b = at(dimU, v0, top);
                out += '<line class="h-dim" x1="' + a[0].toFixed(1) + '" y1="' + a[1].toFixed(1) + '" x2="' + b[0].toFixed(1) + '" y2="' + b[1].toFixed(1) + '"/>';
                [a, b].forEach(p => { out += '<line class="h-dim h-dim--tick" x1="' + (p[0] - 5).toFixed(1) + '" y1="' + (p[1] + 5).toFixed(1) + '" x2="' + (p[0] + 5).toFixed(1) + '" y2="' + (p[1] - 5).toFixed(1) + '"/>'; });
                const mid = [a[0] + 10, (a[1] + b[1]) / 2];
                out += label('h-label h-label--big', mid, h.floors === 1 ? text.est_house_floor || '' : say('est_house_floors', h.floors), outward, -2);
                out += label('h-label', mid, say('est_house_built', h.built), outward, 15);
            }
            if (h.plot_known) {
                // along the plot's front edge, under its middle
                const a = at(0, g.pd, 0), b = at(g.pw, g.pd, 0);
                out += label('h-label h-label--plot', [(a[0] + b[0]) / 2 + 6, (a[1] + b[1]) / 2], say('est_house_plot', h.plot), outward, 20);
            }
            if (dig > 0.5) {
                // in the hatched cut itself, under the middle of the house's front
                out += label('h-label h-label--dug', at(u0 + g.hw / 2, v1, -dig / 2), text.est_basement || '', 'middle', 4);
            }
            if (pool && g.pool > 0.5) {
                const p = at(pool.u0, (pool.v0 + pool.v1) / 2, 0);
                out += label('h-label h-label--pool', [p[0] - 8, p[1]], text.est_line_pool || '', inward, 4);
            }
        } else {
            const c = at(g.pw / 2, g.pd / 2, 0);
            out += label('h-label h-label--hint', c, text.est_house_empty || '', 'middle', 4);
        }

        svg.innerHTML = '<defs><pattern id="esHatch" width="7" height="7" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">'
            + '<line x1="0" y1="0" x2="0" y2="7" class="h-hatch"/></pattern></defs>' + out;
    }

    /* ---- Growing from one shape to the next --------------------------- */
    let shown = null, house = null, frame = 0;

    function go(next) {
        house = next;
        const to = next ? shape(next) : emptyShape;
        // a first house rises from the ground of the plot it stands on
        const from = shown || Object.assign({}, to, { floors: 0, basement: 0, pool: 0 });
        cancelAnimationFrame(frame);
        if (still) { shown = to; draw(to, next); return; }
        const t0 = performance.now(), length = 750;
        const step = now => {
            const t = Math.min(1, (now - t0) / length);
            const e = 1 - Math.pow(1 - t, 3);
            const g = {};
            FIELDS.forEach(f => { g[f] = from[f] + (to[f] - from[f]) * e; });
            shown = g;
            draw(g, next);
            if (t < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
    }

    document.addEventListener('estimate:house', e => go(e.detail || null));

    let first = null;
    try { first = JSON.parse(fig.dataset.house || 'null'); } catch (e) { /* drawn empty */ }
    go(first);
})();
