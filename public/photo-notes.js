/* =========================================================
   ARAM MIZURI ARCHITECTURE — photo-notes.js
   A project's photographs, explained (the project page):
     · notes on a photograph — numbered points, the way a drawing's
       callouts are, each with a leader to its words (placed in the admin:
       Projects → a project → Notes on the photographs). In the gallery a
       point says its words when it is pointed at or tapped; in the photo
       viewer they all show, and "Notes" puts them away;
     · in the viewer, the room the photograph shows, as a way to every
       other photograph of that room (/photos).
   The viewer itself is project.js's; this follows the photograph it shows.
   ========================================================= */
(function () {
    'use strict';

    // Where a picture is actually painted inside its <img>: all of it for a
    // contained picture, the part left in view for one cropped to cover.
    function painted(img) {
        const bw = img.clientWidth, bh = img.clientHeight, nw = img.naturalWidth, nh = img.naturalHeight;
        if (!bw || !bh || !nw || !nh) return null;
        const style = getComputedStyle(img);
        if (style.objectFit !== 'cover') return { x: 0, y: 0, w: bw, h: bh };
        const s = Math.max(bw / nw, bh / nh), w = nw * s, h = nh * s;
        const pos = style.objectPosition.split(/\s+/).map(v => (v.endsWith('%') ? parseFloat(v) / 100 : 0.5));
        return { x: (bw - w) * (pos[0] ?? 0.5), y: (bh - h) * (pos[1] ?? 0.5), w, h };
    }

    function place(layer, img, notes, open) {
        layer.replaceChildren();
        const f = painted(img);
        if (!f || !notes || !notes.length) return;
        const offX = img.offsetLeft, offY = img.offsetTop;
        notes.forEach((n, i) => {
            const x = f.x + f.w * n.x / 100, y = f.y + f.h * n.y / 100;
            if (x < 6 || y < 6 || x > img.clientWidth - 6 || y > img.clientHeight - 6) return;   // cropped out of view
            const note = document.createElement('div');
            // the words go away from the nearer edges
            note.className = 'pp-note ' + (n.x < 58 ? 'to-right' : 'to-left') + ' ' + (n.y < 32 ? 'to-down' : 'to-up') + (open ? ' is-open' : '');
            note.style.left = (offX + x) + 'px';
            note.style.top = (offY + y) + 'px';
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'pp-note__dot';
            dot.textContent = String(i + 1);
            dot.setAttribute('aria-label', (i + 1) + '. ' + n.t);
            dot.setAttribute('aria-expanded', open ? 'true' : 'false');
            const lead = document.createElement('span');
            lead.className = 'pp-note__lead';
            lead.setAttribute('aria-hidden', 'true');
            const words = document.createElement('span');
            words.className = 'pp-note__text';
            words.dir = 'auto';
            words.textContent = n.t;
            note.append(dot, lead, words);
            dot.addEventListener('click', e => {
                e.stopPropagation();
                const on = !note.classList.contains('is-open');
                layer.querySelectorAll('.pp-note.is-open').forEach(o => { if (!open) { o.classList.remove('is-open'); o.firstChild.setAttribute('aria-expanded', 'false'); } });
                note.classList.toggle('is-open', on);
                dot.setAttribute('aria-expanded', on ? 'true' : 'false');
            });
            layer.appendChild(note);
        });
    }

    /* ---- In the gallery ------------------------------------------------- */
    const shots = Array.from(document.querySelectorAll('.pp-shot[data-notes]'));
    const drawShot = shot => {
        const img = shot.querySelector('img');
        let layer = shot.querySelector('.pp-notes');
        if (!layer) { layer = document.createElement('div'); layer.className = 'pp-notes'; shot.appendChild(layer); }
        let notes = [];
        try { notes = JSON.parse(shot.dataset.notes); } catch (e) { /* none */ }
        if (img.complete && img.naturalWidth) place(layer, img, notes, false);
        else img.addEventListener('load', () => place(layer, img, notes, false), { once: true });
    };
    shots.forEach(drawShot);

    /* ---- In the photo viewer -------------------------------------------- */
    const box = document.getElementById('ppLightbox');
    const viewerImg = box && box.querySelector('.pp-lightbox__img');
    const viewerLayer = box && box.querySelector('.pp-notes--viewer');
    let images = [], notesBy = [], roomsBy = [];
    try {
        images = JSON.parse(box.dataset.images || '[]');
        notesBy = JSON.parse(box.dataset.notes || '[]');
        roomsBy = JSON.parse(box.dataset.rooms || '[]');
    } catch (e) { /* the viewer as it is */ }
    const toggle = box && box.querySelector('.pp-lightbox__notes');
    const room = box && box.querySelector('.pp-lightbox__room');
    let shown = true;

    function follow() {
        if (!viewerImg) return;
        // the project's own photographs only — not a progress update's
        const i = images.indexOf(viewerImg.currentSrc || viewerImg.src) >= 0
            ? images.indexOf(viewerImg.currentSrc || viewerImg.src) : images.indexOf(viewerImg.src);
        const notes = i >= 0 ? notesBy[i] || [] : [];
        if (viewerLayer) {
            place(viewerLayer, viewerImg, notes, true);
            viewerLayer.classList.toggle('is-hidden', !shown);
        }
        if (toggle) toggle.hidden = !notes.length;
        if (room) {
            const r = i >= 0 ? roomsBy[i] : null;
            room.hidden = !r;
            if (r) { room.href = r.url; room.textContent = r.label; }
        }
    }

    if (viewerImg) {
        viewerImg.addEventListener('load', follow);
        new MutationObserver(() => { viewerLayer && viewerLayer.replaceChildren(); if (viewerImg.complete) follow(); })
            .observe(viewerImg, { attributes: true, attributeFilter: ['src'] });
    }
    if (toggle) {
        toggle.addEventListener('click', e => {
            e.stopPropagation();
            shown = !shown;
            toggle.setAttribute('aria-pressed', shown ? 'true' : 'false');
            if (viewerLayer) viewerLayer.classList.toggle('is-hidden', !shown);
        });
    }

    // the points stay on their spots as the window changes size
    let queued = false;
    window.addEventListener('resize', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            shots.forEach(drawShot);
            if (box && box.open) follow();
        });
    });
})();
