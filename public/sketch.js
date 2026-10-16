/* =========================================================
   ARAM MIZURI ARCHITECTURE — sketch.js
   "Draw it for us": a sheet of drawing paper — squared, as a plan is drawn
   on — for a visitor to sketch their plot, a plan or the house they have
   in mind, with a finger or a mouse: a pencil, a straight line (it settles
   on the square and the diagonals when it is near them), undo, clear.
   Then sent to the studio:
     · from the blank sheet at the end of /projects — with a message (the
       enquiry form, the sketch attached), on WhatsApp (a link to it in the
       message), or saved as a picture;
     · from the enquiry form itself — attached to the message being written.
   The drawing is kept by the site (SketchController) at an address no one
   could guess; the visitor sees what is attached before it is sent.
   Opened by anything with data-sketch-open ("send" or "attach").
   ========================================================= */
(function () {
    'use strict';

    const openers = Array.from(document.querySelectorAll('[data-sketch-open]'));
    const textEl = document.getElementById('skText');
    if (!textEl) return;
    let cfg = {};
    try { cfg = JSON.parse(textEl.textContent) || {}; } catch (e) { return; }

    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';
    const t = key => ((cfg[isKu() ? 'ku' : 'en'] || {})[key]) || ((cfg.en || {})[key]) || '';

    // the sheet, in its own pixels: landscape, or upright on a phone held upright
    const upright = window.matchMedia('(max-width: 640px) and (orientation: portrait)').matches;
    const W = upright ? 1200 : 1600, H = upright ? 1500 : 1200;
    const PAPER = '#f4f1e8', INK = '#1d1d1b';
    let dialog, canvas, ctx, status, mode = 'send', busy = false;
    let strokes = [], current = null;

    /* ---- The sheet ------------------------------------------------------ */
    function paper() {
        ctx.fillStyle = PAPER; ctx.fillRect(0, 0, W, H);
        for (let x = 0; x <= W; x += 40) line(x, 0, x, H, x % 200 ? 'rgba(80,110,140,.14)' : 'rgba(80,110,140,.3)', 1);
        for (let y = 0; y <= H; y += 40) line(0, y, W, y, y % 200 ? 'rgba(80,110,140,.14)' : 'rgba(80,110,140,.3)', 1);
        // the studio's mark in the corner, small, as a drawing sheet carries its office's
        ctx.fillStyle = 'rgba(29,29,27,.45)';
        ctx.font = '500 20px Inter, Arial, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(cfg.host || '', W - 28, H - 26);
    }
    function line(x1, y1, x2, y2, color, width) {
        ctx.strokeStyle = color; ctx.lineWidth = width;
        ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    }
    function drawStroke(s) {
        ctx.strokeStyle = INK; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        const p = s.points;
        if (s.tool === 'line') {
            ctx.lineWidth = 3.4;
            ctx.beginPath(); ctx.moveTo(p[0][0], p[0][1]); ctx.lineTo(p[1][0], p[1][1]); ctx.stroke();
            return;
        }
        if (p.length === 1) {
            ctx.fillStyle = INK; ctx.beginPath(); ctx.arc(p[0][0], p[0][1], 1.8 + p[0][2] * 1.4, 0, Math.PI * 2); ctx.fill();
            return;
        }
        // through the midpoints, so a hand's line stays smooth; heavier as it is pressed
        for (let i = 1; i < p.length; i++) {
            const a = p[i - 1], b = p[i];
            const m0 = i > 1 ? [(p[i - 2][0] + a[0]) / 2, (p[i - 2][1] + a[1]) / 2] : a;
            const m1 = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
            ctx.lineWidth = 2.2 + (a[2] + b[2]) * 1.6;
            ctx.beginPath(); ctx.moveTo(m0[0], m0[1]); ctx.quadraticCurveTo(a[0], a[1], m1[0], m1[1]); ctx.stroke();
        }
    }
    function redraw() {
        paper();
        strokes.forEach(drawStroke);
        if (current) drawStroke(current);
        dialog.classList.toggle('is-empty', !strokes.length);
    }

    /* ---- Drawing with a finger or a mouse ------------------------------- */
    const tool = () => (dialog.querySelector('[name="skTool"]:checked') || { value: 'pen' }).value;
    function at(e) {
        const r = canvas.getBoundingClientRect();
        const pressure = e.pointerType === 'mouse' || !e.pressure ? 0.5 : e.pressure;
        return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height, pressure];
    }
    // a straight line settles on the square and the diagonals when near them
    function settle(a, b) {
        const dx = b[0] - a[0], dy = b[1] - a[1], len = Math.hypot(dx, dy);
        const angle = Math.atan2(dy, dx), step = Math.PI / 4;
        const near = Math.round(angle / step) * step;
        return Math.abs(angle - near) < 0.07 ? [a[0] + Math.cos(near) * len, a[1] + Math.sin(near) * len, b[2]] : b;
    }
    function down(e) {
        if (busy) return;
        e.preventDefault();
        canvas.setPointerCapture(e.pointerId);
        const p = at(e);
        current = { tool: tool(), points: [p] };
        if (current.tool === 'line') current.points = [p, p];
        redraw();
    }
    function move(e) {
        if (!current) return;
        e.preventDefault();
        if (current.tool === 'line') {
            current.points[1] = settle(current.points[0], at(e));
        } else {
            const events = e.getCoalescedEvents ? e.getCoalescedEvents() : [e];
            (events.length ? events : [e]).forEach(ev => current.points.push(at(ev)));
        }
        redraw();
    }
    function up() {
        if (!current) return;
        const s = current;
        current = null;
        const p = s.points;
        if (s.tool !== 'line' || Math.hypot(p[1][0] - p[0][0], p[1][1] - p[0][1]) > 6) strokes.push(s);
        redraw();
    }

    /* ---- Keeping it, and sending it on ---------------------------------- */
    function say(key, isError) {
        status.textContent = t(key);
        status.hidden = !key;
        status.classList.toggle('is-error', !!isError);
    }
    function picture() {
        return new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    }
    // the drawing, kept by the site, so it can be attached or linked to
    async function keep() {
        const blob = await picture();
        const body = new FormData();
        body.append('image', blob, 'sketch.png');
        const token = document.querySelector('meta[name="csrf-token"]');
        const r = await fetch(cfg.store, {
            method: 'POST', body, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
        });
        if (!r.ok) throw new Error('keep');
        return r.json();
    }
    async function act(what) {
        if (busy) return;
        if (!strokes.length && what !== 'save') { say('empty', true); return; }
        if (what === 'save') {
            const url = URL.createObjectURL(await picture());
            const a = document.createElement('a');
            a.href = url; a.download = 'sketch.png';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 8000);
            return;
        }
        // WhatsApp opens in a window of its own; it has to be asked for now, in the tap
        const away = what === 'wa' ? window.open('', '_blank') : null;
        busy = true;
        dialog.classList.add('is-busy');
        say('sending');
        try {
            const kept = await keep();
            if (what === 'attach') {
                attach(kept);
                dialog.close();
            } else if (what === 'message') {
                location.href = cfg.home + '?sketch=' + encodeURIComponent(kept.token) + '#contact';
            } else if (what === 'wa') {
                const link = cfg.wa + '?text=' + encodeURIComponent(t('waText').replace('{url}', kept.url));
                if (away) { away.location.href = link; dialog.close(); } else location.href = link;
            }
            say('');
        } catch (e) {
            if (away) away.close();
            say('failed', true);
        } finally {
            busy = false;
            dialog.classList.remove('is-busy');
        }
    }

    /* ---- On the enquiry form: the sketch shown, and taken off --------- */
    const chip = document.getElementById('formSketch');
    const field = document.getElementById('cf-sketch');
    function attach(kept) {
        if (!chip || !field) return;
        field.value = kept.token;
        chip.querySelector('img').src = kept.url;
        chip.hidden = false;
    }
    if (chip && field) {
        const clear = document.getElementById('formSketchClear');
        if (clear) clear.addEventListener('click', () => { field.value = ''; chip.hidden = true; });
        // arrived from the blank sheet on /projects: ?sketch=token#contact
        const params = new URLSearchParams(location.search);
        const token = params.get('sketch');
        if (token && /^[A-Za-z0-9]{40}$/.test(token)) {
            attach({ token, url: cfg.show.replace('TOKEN', token) });
            params.delete('sketch');
            const qs = params.toString();
            if (window.history && history.replaceState) history.replaceState(history.state, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
        }
    }

    /* ---- The window ------------------------------------------------------ */
    function build() {
        dialog = document.createElement('dialog');
        dialog.className = 'sk is-empty';
        dialog.innerHTML =
            '<div class="sk__head">' +
            '<p class="sk__title"></p><p class="sk__hint"></p>' +
            '<button type="button" class="sk__close" data-act="close">✕</button></div>' +
            '<div class="sk__paper"><canvas width="' + W + '" height="' + H + '"></canvas><p class="sk__start" aria-hidden="true"></p></div>' +
            '<div class="sk__bar">' +
            '<div class="sk__tools" role="radiogroup">' +
            '<label><input type="radio" name="skTool" value="pen" checked><span data-t="pen"></span></label>' +
            '<label><input type="radio" name="skTool" value="line"><span data-t="line"></span></label>' +
            '<button type="button" data-act="undo"></button><button type="button" data-act="clear"></button>' +
            '</div>' +
            '<div class="sk__actions"></div></div>' +
            '<p class="sk__status" role="status" hidden></p>';
        document.body.appendChild(dialog);
        canvas = dialog.querySelector('canvas');
        ctx = canvas.getContext('2d');
        status = dialog.querySelector('.sk__status');
        canvas.addEventListener('pointerdown', down);
        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', up);
        canvas.addEventListener('pointercancel', up);
        dialog.addEventListener('click', e => {
            const b = e.target.closest('[data-act]');
            if (e.target === dialog) { dialog.close(); return; }
            if (!b) return;
            const a = b.dataset.act;
            if (a === 'close') dialog.close();
            else if (a === 'undo') { strokes.pop(); redraw(); }
            else if (a === 'clear') { strokes = []; redraw(); }
            else act(a);
        });
    }
    function words() {
        dialog.setAttribute('aria-label', t('title'));
        dialog.querySelector('.sk__title').textContent = t('title');
        dialog.querySelector('.sk__hint').textContent = t('hint');
        dialog.querySelector('.sk__start').textContent = t('start');
        dialog.querySelector('.sk__close').setAttribute('aria-label', t('close'));
        dialog.querySelectorAll('[data-t]').forEach(el => { el.textContent = t(el.dataset.t); });
        dialog.querySelector('[data-act="undo"]').textContent = t('undo');
        dialog.querySelector('[data-act="clear"]').textContent = t('clear');
        const actions = mode === 'attach'
            ? [['attach', 'attach', 'pp-btn'], ['save', 'save', 'pp-act']]
            : [['message', 'send', 'pp-btn'], ...(cfg.wa ? [['wa', 'wa', 'pp-act pp-act--wa']] : []), ['save', 'save', 'pp-act']];
        dialog.querySelector('.sk__actions').innerHTML = actions
            .map(([act, key, cls]) => '<button type="button" class="' + cls + '" data-act="' + act + '"><span></span></button>').join('');
        dialog.querySelectorAll('.sk__actions [data-act]').forEach((b, i) => { b.querySelector('span').textContent = t(actions[i][1]); });
    }

    function open(how) {
        if (!dialog) build();
        mode = how === 'attach' ? 'attach' : 'send';
        words();
        say('');
        redraw();
        if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', '');
    }

    openers.forEach(el => el.addEventListener('click', e => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;   // a new tab: the link as it is
        e.preventDefault();
        open(el.dataset.sketchOpen);
    }));
})();
