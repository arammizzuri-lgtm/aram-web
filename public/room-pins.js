/* =========================================================
   ARAM MIZURI ARCHITECTURE — room-pins.js
   A drawing in a client's room (room.blade.php), with the comments pinned
   to it, numbered: an image, or a PDF drawn page by page with pdf.js. The
   client taps a spot and writes; a number opens its thread, the studio's
   answers in it. The studio's preview of the room shows it read-only.
   ========================================================= */
(() => {
    const dlg = document.getElementById('rmPins');
    if (!dlg || typeof dlg.showModal !== 'function') return;

    const q = (s, el = dlg) => el.querySelector(s);
    const view = q('.rm-pins__view'), sheet = q('.rm-pins__sheet'), loading = q('.rm-pins__loading');
    const thread = q('.rm-pins__thread'), hint = q('.rm-pins__hint'), form = q('.rm-pins__form'), err = q('.rm-pins__error');
    const pager = q('.rm-pins__pages'), pageNo = q('.rm-pins__page');
    const readonly = 'readonly' in dlg.dataset, opening = loading.textContent;
    const ZOOMS = [1, 1.5, 2, 3, 4];
    const cards = new Map([...document.querySelectorAll('[data-drawing]')].map((c) => [c, JSON.parse(c.dataset.drawing)]));
    let cur = null;      // the drawing open: { card, d, pdf, pg, pages, page, zoom, aspect, media, sel }
    let pdfjs = null, task = null;

    const el = (tag, cls, text) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    };

    // a thread's messages, drawn as under the progress updates
    const msgs = (list) => list.map((m) => {
        const box = el('div', `rm-msg ${m.studio ? 'rm-msg--studio' : 'rm-msg--client'}`), body = el('p', 'rm-msg__body', m.body);
        body.dir = 'auto';
        box.append(el('span', 'rm-msg__who', m.who), body);
        return box;
    });

    // under the drawing in the room: each pin's number, and its thread
    function renderCard(card) {
        const { pins } = cards.get(card), count = q('[data-count]', card);
        q('.rm-marks', card).replaceChildren(...pins.map((p) => {
            const li = el('li', 'rm-mark'), n = el('button', 'rm-dot', p.n), box = el('div', 'rm-thread');
            n.type = 'button';
            n.dataset.mark = p.n;
            box.append(...msgs(p.msgs));
            li.append(n, box);
            return li;
        }));
        if (count) { count.textContent = pins.length; count.hidden = !pins.length; }
    }

    async function open(card, n) {
        const d = cards.get(card), me = { card, d, page: 1, pages: 1, zoom: 0, aspect: 1, sel: null };
        cur = me;
        q('.rm-pins__title').textContent = d.title;
        sheet.replaceChildren();
        loading.textContent = opening;
        loading.hidden = false;
        pager.hidden = true;
        select(null);
        if (!dlg.open) dlg.showModal();
        try {
            if (d.pdf) {
                pdfjs ||= await import(dlg.dataset.pdfjs);
                pdfjs.GlobalWorkerOptions.workerSrc = dlg.dataset.worker;
                me.pdf = await pdfjs.getDocument({ url: d.file }).promise;
                me.pages = me.pdf.numPages;
                me.media = el('canvas');
            } else {
                const img = new Image();
                img.alt = '';
                img.src = d.file;
                await img.decode();
                me.media = img;
                me.aspect = img.naturalWidth / img.naturalHeight;
            }
        } catch {
            // it cannot be drawn here: the file itself, as before
            const a = el('a', 'pp-act', dlg.dataset.openLabel);
            a.href = d.file;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            if (cur === me) loading.replaceChildren(a);
            return;
        }
        if (cur !== me) return;
        const p = n && d.pins.find((x) => x.n === n);
        await show(p ? p.page : 1);
        if (p && cur === me) select(p);
    }

    async function show(page) {
        const me = cur;
        me.page = page;
        if (me.pdf) {
            const pg = await me.pdf.getPage(page);
            if (cur !== me) return;
            const vp = pg.getViewport({ scale: 1 });
            me.pg = pg;
            me.aspect = vp.width / vp.height;
        }
        pager.hidden = me.pages < 2;
        pageNo.textContent = `${page} / ${me.pages}`;
        sheet.replaceChildren(me.media);
        loading.hidden = true;
        layout();
    }

    // fit the view, times the zoom; a PDF page is drawn again at the new size
    function layout() {
        if (!cur || !cur.media) return;
        const fit = Math.max(120, Math.min(view.clientWidth - 24, (view.clientHeight - 24) * cur.aspect));
        const w = Math.round(fit * ZOOMS[cur.zoom]);
        sheet.style.width = `${w}px`;
        if (cur.pdf && cur.pg) draw(w);
        drawPins();
    }

    function draw(w) {
        if (task) task.cancel();
        const pg = cur.pg, canvas = cur.media, base = pg.getViewport({ scale: 1 });
        let scale = (w * Math.min(window.devicePixelRatio || 1, 2)) / base.width;
        // phones refuse a canvas over about 16 million pixels
        scale = Math.min(scale, Math.sqrt(16e6 / (base.width * base.height)));
        const vp = pg.getViewport({ scale });
        canvas.width = Math.floor(vp.width);
        canvas.height = Math.floor(vp.height);
        task = pg.render({ canvasContext: canvas.getContext('2d'), viewport: vp });
        task.promise.catch(() => {});   // a render cancelled by the next one
    }

    function dot(label, at, on) {
        const b = el('button', `rm-dot${on ? ' is-on' : ''}`, label);
        b.type = 'button';
        b.style.left = `${at.x * 100}%`;
        b.style.top = `${at.y * 100}%`;
        if (at.n) b.dataset.pin = at.n;
        return b;
    }

    function drawPins() {
        sheet.querySelectorAll('.rm-dot').forEach((b) => b.remove());
        const sel = cur.sel;
        cur.d.pins.filter((p) => p.page === cur.page).forEach((p) => sheet.append(dot(p.n, p, sel && sel.n === p.n)));
        if (sel && !sel.n && sel.page === cur.page) sheet.append(dot('+', sel, true));
    }

    // a pin's thread in the side panel — or a new spot, waiting for its words
    function select(p) {
        if (!cur) return;
        cur.sel = p;
        thread.replaceChildren(...(p && p.msgs ? msgs(p.msgs) : []));
        hint.hidden = !!p;
        if (form) {
            form.hidden = !p;
            err.hidden = true;
            if (p && !p.n) form.elements.body.focus({ preventScroll: true });
        }
        if (cur.media) drawPins();
    }

    sheet.addEventListener('click', (e) => {
        const b = e.target.closest('[data-pin]');
        if (b) return select(cur.d.pins.find((p) => p.n === +b.dataset.pin));
        if (readonly || !cur.media || e.target.closest('.rm-dot')) return;
        const r = sheet.getBoundingClientRect(), clamp = (v) => Math.min(1, Math.max(0, v));
        select({ page: cur.page, x: clamp((e.clientX - r.left) / r.width), y: clamp((e.clientY - r.top) / r.height) });
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const me = cur, s = me && me.sel, send = q('button[type=submit]', form);
        if (!s) return;
        const body = new FormData(form);
        body.set('page', s.page);
        body.set('x', s.x.toFixed(5));
        body.set('y', s.y.toFixed(5));
        send.disabled = true;
        err.hidden = true;
        try {
            const res = await fetch(me.d.post, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(String(res.status));
            const out = await res.json();
            me.d.pins = out.pins;
            renderCard(me.card);
            form.reset();
            if (cur === me) select(out.pins.find((p) => p.n === out.n) || null);
        } catch {
            err.textContent = dlg.dataset.error;
            err.hidden = false;
        }
        send.disabled = false;
    });

    dlg.addEventListener('click', (e) => {
        const go = e.target.closest('[data-go]'), zoom = e.target.closest('[data-zoom]');
        if (e.target.closest('[data-close]')) dlg.close();
        else if (go && cur && cur.media) {
            const page = cur.page + +go.dataset.go;
            if (page >= 1 && page <= cur.pages) { if (cur.sel && !cur.sel.n) select(null); show(page); }
        } else if (zoom && cur && cur.media) {
            // zoom about the middle of what is in view
            const cx = (view.scrollLeft + view.clientWidth / 2) / view.scrollWidth, cy = (view.scrollTop + view.clientHeight / 2) / view.scrollHeight;
            cur.zoom = Math.min(ZOOMS.length - 1, Math.max(0, cur.zoom + +zoom.dataset.zoom));
            layout();
            view.scrollLeft = cx * view.scrollWidth - view.clientWidth / 2;
            view.scrollTop = cy * view.scrollHeight - view.clientHeight / 2;
        }
    });

    dlg.addEventListener('close', () => {
        if (task) task.cancel();
        cur = null;
        sheet.replaceChildren();
    });

    let frame = 0;
    window.addEventListener('resize', () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(layout); });

    document.addEventListener('click', (e) => {
        const card = e.target.closest('[data-drawing]');
        if (!card || dlg.contains(e.target)) return;
        const mark = e.target.closest('[data-mark]');
        if (e.target.closest('[data-pins-open]')) open(card);
        else if (mark) open(card, +mark.dataset.mark);
    });

    cards.forEach((d, card) => renderCard(card));

    // from the admin: #pins-12 opens drawing 12, #pins-12-3 its pin 3
    const m = location.hash.match(/^#pins-(\d+)(?:-(\d+))?$/);
    const target = m && [...cards].find(([, d]) => d.id === +m[1]);
    if (target) {
        target[0].scrollIntoView();
        open(target[0], +(m[2] || 0));
    }
})();
