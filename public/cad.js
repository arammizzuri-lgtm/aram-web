/* =========================================================
   ARAM MIZURI ARCHITECTURE — cad.js
   What a hand used to a CAD mouse tries, on every page with the index
   (computers only; styles in site-index.css):
   · Dragged across an empty part of the page, a selection box: left to
     right a window (solid, blue), right to left a crossing (dashed,
     green). Let go, the pictures and titles it caught are picked out until
     the next click or Escape — a window takes what is wholly inside, a
     crossing whatever it touches. Text is never in its way: a drag that
     starts on text, or that starts to select some, is the browser's.
   · Shift and drag measures, as on a sheet at 1:100: DIST = 4.35 m, and
     the angle as CAD gives it, counter-clockwise from east.
   ========================================================= */
(function () {
    'use strict';
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    const root = document.documentElement;
    // what has a drag of its own, or wants the click
    const OWN = 'a, button, input, textarea, select, label, summary, dialog, iframe, [contenteditable]:not([contenteditable="false"]), [role="slider"], '
        + '.leaflet-container, .hero, #pgGrid, #statbarTrack, .clients__track-wrap, #projOverlay, .pp-compare__frame, .cv__wave, .cmd';
    // pictures drag themselves out of the page: a box does not start on one (a measure may)
    const MEDIA = 'img, video, audio, canvas, [draggable="true"]';
    // what a box can pick: the sheet's objects, not every box of the layout
    const PICK = 'img, video, svg.sheet-plan, h1, h2, h3, figure';
    const M_PER_PX = 0.0254 / 96 * 100;   // a CSS pixel is 1/96 in; at 1:100
    const SP = '\u2002\u2002';             // SVG keeps no run of plain spaces
    const NS = 'http://www.w3.org/2000/svg';

    let layer, box, line, ticks, bg, label;
    const make = () => {
        layer = document.createElementNS(NS, 'svg');
        layer.setAttribute('class', 'cad-layer');
        layer.setAttribute('aria-hidden', 'true');
        layer.innerHTML = '<rect class="cad-box"/><g class="cad-dim"><line class="cad-dim__l"/><path class="cad-dim__t"/>'
            + '<rect class="cad-dim__bg" rx="2"/><text class="cad-dim__x" text-anchor="middle" dominant-baseline="central"></text></g>';
        document.body.appendChild(layer);
        [box, line, ticks, bg, label] = ['.cad-box', '.cad-dim__l', '.cad-dim__t', '.cad-dim__bg', '.cad-dim__x'].map(s => layer.querySelector(s));
    };
    const attrs = (el, a) => Object.keys(a).forEach(k => el.setAttribute(k, a[k]));

    // the point is on a line of text (a drag from it selects that text)
    const onText = (x, y) => {
        let node = null;
        if (document.caretPositionFromPoint) { const c = document.caretPositionFromPoint(x, y); node = c && c.offsetNode; }
        else if (document.caretRangeFromPoint) { const c = document.caretRangeFromPoint(x, y); node = c && c.startContainer; }
        if (!node || node.nodeType !== 3 || !node.data.trim()) return false;
        const r = document.createRange();
        r.selectNodeContents(node);
        return [...r.getClientRects()].some(b => x >= b.left - 2 && x <= b.right + 2 && y >= b.top - 2 && y <= b.bottom + 2);
    };

    let mode = null, x0 = 0, y0 = 0, picked = [];
    const unpick = () => { picked.forEach(el => el.classList.remove('cad-picked')); picked = []; };
    const clearDim = () => { if (layer) layer.classList.remove('is-dist'); };

    document.addEventListener('pointerdown', e => {
        unpick();
        clearDim();
        if (e.pointerType !== 'mouse' || e.button !== 0 || e.ctrlKey || e.metaKey || e.altKey) return;
        const t = e.target.closest ? e.target : e.target.parentElement;
        if (!t || t.closest(OWN)) return;
        if (e.shiftKey) {
            mode = 'dist';
            e.preventDefault();   // nor does Shift stretch a selection of text
            getSelection().removeAllRanges();
        } else if (t.closest(MEDIA) || onText(e.clientX, e.clientY)) {
            return;
        } else {
            mode = 'maybe';
        }
        x0 = e.clientX; y0 = e.clientY;
    });

    window.addEventListener('pointermove', e => {
        if (!mode) return;
        const x = e.clientX, y = e.clientY;
        if (mode === 'maybe') {
            if (Math.hypot(x - x0, y - y0) < 6) return;
            const sel = getSelection();
            if (sel && !sel.isCollapsed) { mode = null; return; }   // the browser is selecting text: leave it be
            mode = 'box';
            sel && sel.removeAllRanges();
            root.classList.add('is-cad');   // and nothing is selected under the box
        }
        if (!layer) make();
        if (mode === 'box') {
            attrs(box, { x: Math.min(x, x0), y: Math.min(y, y0), width: Math.abs(x - x0), height: Math.abs(y - y0) });
            box.classList.toggle('is-crossing', x < x0);
            layer.classList.add('is-box');
        } else {
            const dx = x - x0, dy = y - y0, len = Math.hypot(dx, dy);
            if (len < 1) return;
            const ux = dx / len, uy = dy / len, tx = (ux - uy) * 4, ty = (uy + ux) * 4;   // ticks at 45° to the line
            attrs(line, { x1: x0, y1: y0, x2: x, y2: y });
            ticks.setAttribute('d', `M${x0 - tx} ${y0 - ty}L${x0 + tx} ${y0 + ty}M${x - tx} ${y - ty}L${x + tx} ${y + ty}`);
            const deg = Math.round((Math.atan2(-dy, dx) * 180 / Math.PI + 360) % 360);
            label.textContent = 'DIST = ' + (len * M_PER_PX).toFixed(2) + ' m' + SP + '∠ ' + deg + '°';
            layer.classList.add('is-dist');
            // the figure over the line's middle, on its upper side, its whole box clear of the line
            const up = ux > 0 ? 1 : -1, nx = uy * up, ny = -ux * up;
            const size = label.getBBox(), off = 12 + Math.abs(nx) * size.width / 2 + Math.abs(ny) * size.height / 2;
            attrs(label, { x: (x0 + x) / 2 + nx * off, y: (y0 + y) / 2 + ny * off });
            const b = label.getBBox();
            attrs(bg, { x: b.x - 6, y: b.y - 3, width: b.width + 12, height: b.height + 6 });
        }
    });

    const end = e => {
        if (mode === 'box') {
            const l = Math.min(e.clientX, x0), r = Math.max(e.clientX, x0), t = Math.min(e.clientY, y0), btm = Math.max(e.clientY, y0);
            const isWindow = e.clientX >= x0;
            const caught = [...document.querySelectorAll(PICK)].filter(el => {
                const b = el.getBoundingClientRect();
                if (!b.width || !b.height || (el.checkVisibility && !el.checkVisibility({ opacityProperty: true, visibilityProperty: true }))) return false;
                return isWindow ? b.left >= l && b.right <= r && b.top >= t && b.bottom <= btm
                               : b.left < r && b.right > l && b.top < btm && b.bottom > t;
            });
            // a figure caught whole is one object, not also its picture
            picked = caught.filter(el => !caught.some(p => p !== el && p.contains(el)));
            picked.forEach(el => el.classList.add('cad-picked'));
            layer.classList.remove('is-box');
        }
        root.classList.remove('is-cad');
        mode = null;
    };
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', () => { if (layer) layer.classList.remove('is-box'); root.classList.remove('is-cad'); mode = null; });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { unpick(); clearDim(); } });
    // the measure is drawn on the screen, not the page: a scroll takes it away
    window.addEventListener('scroll', clearDim, { passive: true });
})();
