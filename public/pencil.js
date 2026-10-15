/* =========================================================
   ARAM MIZURI ARCHITECTURE — pencil.js
   Draws a portrait (an SVG of strokes, App\Support\PortraitDrawing) line
   after line, in the order its strokes were laid down: guide lines, then
   the outline, the features, the hair, the shading. Each stroke takes as
   long as it is long — guide lines and hatching go quicker, as a hand's
   do — with a moment between strokes for the pen to lift.

   const pen = window.pencil(svgElement);   // everything hidden
   pen.render(0.5);                         // half the drawing done
   Used by voices.js on the website and by the admin's drawing preview.
   ========================================================= */
window.pencil = function (svg) {
    'use strict';

    const PACE = { g: 0.55, l: 1, h: 0.4 };
    const LIFT = 12;

    let at = 0;
    const strokes = Array.from(svg.querySelectorAll('path')).map(path => {
        const len = path.getTotalLength() || 1;
        const span = len * (PACE[path.getAttribute('data-k')] || 1);
        const stroke = { path, len, from: at, to: at + span, shown: -1 };
        at += span + LIFT;
        path.style.strokeDasharray = len + ' ' + len;
        return stroke;
    });
    const total = at || 1;

    function render(progress) {
        const t = Math.max(0, Math.min(1, progress)) * total;
        for (const s of strokes) {
            const f = t <= s.from ? 0 : t >= s.to ? 1 : (t - s.from) / (s.to - s.from);
            if (f === s.shown) continue;
            s.shown = f;
            s.path.style.strokeDashoffset = String(s.len * (1 - f));
            // not yet begun: hidden outright, or a round cap would leave a dot
            s.path.style.visibility = f > 0 ? 'visible' : 'hidden';
        }
    }

    render(0);
    return { render, strokes: strokes.length };
};
