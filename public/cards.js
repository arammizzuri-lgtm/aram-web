/* =========================================================
   ARAM MIZURI ARCHITECTURE — cards.js
   Pictures made in the browser, to be sent on:
     · a project as a phone-tall card for WhatsApp Status and stories —
       its photograph framed as a drawing sheet, with its title block
       (the "Picture for Status" button on a project's page)
     · the holiday cards — the greeting, a photograph and the visitor's
       own name (/card, greeting.js)
   Drawn on a canvas, so the Kurdish is set by the browser, which joins
   its letters as they should be; the photographs are the studio's own,
   from its own address. Sent through the phone's share sheet — WhatsApp,
   its Status — or, where there is none, saved as a picture.
   window.siteCards: { story(data, canvas?), greeting(data, choice, canvas?), send(canvas, options) }
   ========================================================= */
(function () {
    'use strict';

    const GOLD = '#F5C518';
    const TONES = { done: '#4ecb71', build: GOLD, concept: 'rgba(255,255,255,.55)' };

    /* ---- Faces and photographs, fetched once ---------------------------- */
    const faces = {};
    function face(family, url) {
        if (!faces[family]) {
            faces[family] = 'FontFace' in window
                ? new FontFace(family, 'url(' + url + ')').load().then(f => { document.fonts.add(f); return true; }, () => false)
                : Promise.resolve(false);
        }
        return faces[family];
    }
    function fontsReady() {
        const waits = [face('CardSpeda', '/fonts/speda.ttf'), face('CardSirwan', '/fonts/sirwan.ttf')];
        if (document.fonts && document.fonts.load) {
            ['200', '300', '400', '500'].forEach(w => waits.push(document.fonts.load(w + ' 40px Inter').catch(() => null)));
        }
        return Promise.all(waits);
    }

    const pictures = {};
    function picture(url) {
        if (!url) return Promise.resolve(null);
        if (!pictures[url]) {
            pictures[url] = new Promise(resolve => {
                const img = new Image();
                // asked for openly, so a photograph from anywhere else fails
                // here — the card is then drawn without it — rather than
                // taking the whole picture down with it
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = () => resolve(null);
                img.src = url;
            });
        }
        return pictures[url];
    }

    /* ---- Drawing helpers ------------------------------------------------ */
    const KU = '"CardSpeda", "Vazirmatn", sans-serif', EN = '"Inter", "Helvetica Neue", Arial, sans-serif';
    function setFont(ctx, lang, size, weight, family) {
        ctx.font = (weight || 400) + ' ' + size + 'px ' + (family || (lang === 'ku' ? KU : EN));
        if ('direction' in ctx) ctx.direction = lang === 'ku' ? 'rtl' : 'ltr';
    }
    // Latin capitals, spaced out as the site sets them; Kurdish is never
    // spaced (pulling its letters apart would break their joins)
    function spaced(ctx, str, x, y, spacing, align) {
        ctx.textAlign = align;
        if ('letterSpacing' in ctx) {
            ctx.letterSpacing = spacing + 'px';
            ctx.fillText(str, align === 'right' ? x + spacing : x, y);
            ctx.letterSpacing = '0px';
            return;
        }
        const chars = Array.from(str);
        const width = chars.reduce((w, ch) => w + ctx.measureText(ch).width + spacing, -spacing);
        let at = align === 'right' ? x - width : align === 'center' ? x - width / 2 : x;
        ctx.textAlign = 'left';
        chars.forEach(ch => { ctx.fillText(ch, at, y); at += ctx.measureText(ch).width + spacing; });
    }
    function wrap(ctx, str, width, most) {
        const lines = [];
        let line = '';
        String(str || '').split(/\s+/).filter(Boolean).forEach(word => {
            const next = line ? line + ' ' + word : word;
            if (line && ctx.measureText(next).width > width) { lines.push(line); line = word; } else line = next;
        });
        if (line) lines.push(line);
        if (most && lines.length > most) {
            lines.length = most;
            lines[most - 1] = lines[most - 1].replace(/\s*\S+$/, '') + '…';
        }
        return lines;
    }
    // the largest size up to `size` at which the words fit in `width`
    function fitFont(ctx, lang, str, width, size, weight, family) {
        for (let s = size; s > 20; s -= 2) {
            setFont(ctx, lang, s, weight, family);
            if (ctx.measureText(str).width <= width) return s;
        }
        return 20;
    }
    function cover(ctx, img, x, y, w, h, pos) {
        const p = String(pos || '50% 50%').split(/\s+/).map(v => parseFloat(v) / 100);
        const fx = Number.isFinite(p[0]) ? p[0] : 0.5, fy = Number.isFinite(p[1]) ? p[1] : 0.5;
        const s = Math.max(w / img.naturalWidth, h / img.naturalHeight);
        const sw = w / s, sh = h / s;
        ctx.drawImage(img, (img.naturalWidth - sw) * fx, (img.naturalHeight - sh) * fy, sw, sh, x, y, w, h);
    }
    // where there is no photograph: the frame hatched, as a drawing marks an opening
    function hatch(ctx, x, y, w, h) {
        ctx.save();
        ctx.beginPath(); ctx.rect(x, y, w, h); ctx.clip();
        ctx.fillStyle = '#141414'; ctx.fillRect(x, y, w, h);
        ctx.strokeStyle = 'rgba(245,197,24,.18)'; ctx.lineWidth = 2;
        for (let i = -h; i < w; i += 28) { ctx.beginPath(); ctx.moveTo(x + i, y + h); ctx.lineTo(x + i + h, y); ctx.stroke(); }
        ctx.restore();
    }
    // crop marks, outside the corners of a frame
    function crops(ctx, x, y, w, h, len, gap) {
        ctx.save();
        ctx.strokeStyle = 'rgba(245,197,24,.85)'; ctx.lineWidth = 2;
        [[x, y, -1, -1], [x + w, y, 1, -1], [x, y + h, -1, 1], [x + w, y + h, 1, 1]].forEach(([cx, cy, dx, dy]) => {
            ctx.beginPath();
            ctx.moveTo(cx + dx * gap, cy); ctx.lineTo(cx + dx * (gap + len), cy);
            ctx.moveTo(cx, cy + dy * gap); ctx.lineTo(cx, cy + dy * (gap + len));
            ctx.stroke();
        });
        ctx.restore();
    }
    // the Kurdish sun: a disc and its twenty-one rays
    function sun(ctx, cx, cy, r, color) {
        ctx.save();
        ctx.fillStyle = color;
        ctx.beginPath(); ctx.arc(cx, cy, r * 0.42, 0, Math.PI * 2); ctx.fill();
        for (let i = 0; i < 21; i++) {
            const a = (i / 21) * Math.PI * 2 - Math.PI / 2, half = Math.PI / 21 * 0.42;
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(a - half) * r * 0.5, cy + Math.sin(a - half) * r * 0.5);
            ctx.lineTo(cx + Math.cos(a) * r, cy + Math.sin(a) * r);
            ctx.lineTo(cx + Math.cos(a + half) * r * 0.5, cy + Math.sin(a + half) * r * 0.5);
            ctx.closePath(); ctx.fill();
        }
        ctx.restore();
    }
    function canvasOf(c, w, h) {
        const canvas = c || document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        return canvas;
    }

    /* ---- A project, for WhatsApp Status --------------------------------
       1080 × 1920: the studio's name across the top, the photograph framed
       with crop marks, and under it the title block every drawing carries
       — sheet number, project, place, year, status — then the address. */
    async function story(d, target) {
        await fontsReady();
        const img = await picture(d.img);
        const W = 1080, H = 1920, M = 72, ku = d.lang === 'ku';
        const canvas = canvasOf(target, W, H), ctx = canvas.getContext('2d');
        const start = ku ? 'right' : 'left', end = ku ? 'left' : 'right';
        const sx = x => (ku ? W - x : x);                          // a distance from the reading edge

        ctx.fillStyle = '#090909'; ctx.fillRect(0, 0, W, H);

        // the studio's name, and the sheet's number in the far corner
        ctx.fillStyle = '#fff';
        if (ku) { setFont(ctx, 'ku', 40, 500); ctx.textAlign = start; ctx.fillText(d.brand, sx(M), 130); }
        else { setFont(ctx, 'en', 32, 500); spaced(ctx, d.brand, sx(M), 128, 11, start); }
        ctx.fillStyle = GOLD;
        if (ku) { setFont(ctx, 'ku', 24, 400); ctx.textAlign = start; ctx.fillText(d.sub, sx(M), 172); }
        else { setFont(ctx, 'en', 18, 400); spaced(ctx, d.sub.toUpperCase(), sx(M), 168, 6, start); }
        setFont(ctx, 'en', 30, 300); ctx.textAlign = end;
        if ('direction' in ctx) ctx.direction = 'ltr';
        ctx.fillText('Nº ' + (d.num || ''), ku ? M : W - M, 130);

        // the photograph, framed
        const F = { x: M, y: 230, w: W - 2 * M, h: 1090 };
        if (img) cover(ctx, img, F.x, F.y, F.w, F.h, d.pos); else hatch(ctx, F.x, F.y, F.w, F.h);
        crops(ctx, F.x, F.y, F.w, F.h, 34, 12);

        // the title block
        const T = { x: M, y: 1380, w: W - 2 * M, h1: 210, h2: 160 };
        ctx.strokeStyle = 'rgba(255,255,255,.3)'; ctx.lineWidth = 2;
        ctx.strokeRect(T.x, T.y, T.w, T.h1 + T.h2);
        ctx.beginPath(); ctx.moveTo(T.x, T.y + T.h1); ctx.lineTo(T.x + T.w, T.y + T.h1); ctx.stroke();
        const numW = 210;
        const numEdge = ku ? T.x + T.w - numW : T.x + numW;
        ctx.beginPath(); ctx.moveTo(numEdge, T.y); ctx.lineTo(numEdge, T.y + T.h1); ctx.stroke();
        const third = T.w / 3;
        [1, 2].forEach(i => { ctx.beginPath(); ctx.moveTo(T.x + third * i, T.y + T.h1); ctx.lineTo(T.x + third * i, T.y + T.h1 + T.h2); ctx.stroke(); });

        const small = (words, x, y) => {
            ctx.fillStyle = 'rgba(255,255,255,.45)';
            if (ku) { setFont(ctx, 'ku', 24, 400); ctx.textAlign = start; ctx.fillText(words, x, y); }
            else { setFont(ctx, 'en', 17, 400); spaced(ctx, String(words).toUpperCase(), x, y, 5, start); }
        };
        const pad = 28;
        // sheet number
        const numX = ku ? T.x + T.w - pad : T.x + pad;
        small(d.labels.sheet, numX, T.y + 52);
        ctx.fillStyle = GOLD; setFont(ctx, 'en', 104, 200); ctx.textAlign = start;
        if ('direction' in ctx) ctx.direction = 'ltr';
        ctx.textAlign = ku ? 'right' : 'left';
        ctx.fillText(d.num || '—', numX, T.y + 170);
        // project
        const nameX = ku ? numEdge - pad : numEdge + pad;
        small(d.labels.project, nameX, T.y + 52);
        ctx.fillStyle = '#fff';
        const nameW = T.w - numW - 2 * pad;
        setFont(ctx, d.lang, ku ? 54 : 52, ku ? 400 : 300);
        ctx.textAlign = start;
        let lines = wrap(ctx, d.name, nameW, 2);
        if (lines.length > 1) { setFont(ctx, d.lang, ku ? 44 : 42, ku ? 400 : 300); lines = wrap(ctx, d.name, nameW, 2); }
        lines.forEach((l, i) => ctx.fillText(l, nameX, T.y + (lines.length > 1 ? 118 : 140) + i * 54));
        // place, year, status
        const cells = [[d.labels.place, d.place], [d.labels.year, d.year], [d.labels.status, d.status]];
        cells.forEach(([lab, val], i) => {
            const cx = ku ? T.x + T.w - third * i - pad : T.x + third * i + pad;
            small(lab, cx, T.y + T.h1 + 50);
            ctx.fillStyle = '#fff';
            const isStatus = i === 2;
            const vx = isStatus ? (ku ? cx - 26 : cx + 26) : cx;
            fitFont(ctx, i === 1 ? 'en' : d.lang, val || '—', third - 2 * pad - (isStatus ? 26 : 0), 34, 300);
            if (i === 1 && 'direction' in ctx) ctx.direction = 'ltr';
            ctx.textAlign = start;
            ctx.fillText(val || '—', vx, T.y + T.h1 + 112);
            if (isStatus) {
                ctx.fillStyle = TONES[d.tone] || '#fff';
                ctx.beginPath(); ctx.arc(ku ? cx - 7 : cx + 7, T.y + T.h1 + 101, 7, 0, Math.PI * 2); ctx.fill();
            }
        });

        // the address, and the sun
        ctx.fillStyle = GOLD; setFont(ctx, 'en', 30, 400);
        if ('direction' in ctx) ctx.direction = 'ltr';
        ctx.textAlign = ku ? 'right' : 'left';
        ctx.fillText(d.host, ku ? W - M : M, 1846);
        sun(ctx, ku ? M + 30 : W - M - 30, 1834, 30, GOLD);
        return canvas;
    }

    /* ---- A holiday card -------------------------------------------------
       1080 × 1350: the photograph above, fading into the card; the sun
       rising at its edge; the greeting, the line under it, the visitor's
       name; the studio's name and address along the foot. */
    async function greeting(d, choice, target) {
        await fontsReady();
        const photo = d.photos[choice.photo] || d.photos[0] || {};
        const img = await picture(photo.url);
        const W = 1080, H = 1350, M = 72, lang = choice.lang === 'ku' ? 'ku' : 'en', ku = lang === 'ku';
        const canvas = canvasOf(target, W, H), ctx = canvas.getContext('2d');

        ctx.fillStyle = '#0b0b0b'; ctx.fillRect(0, 0, W, H);
        const PH = 800;
        if (img) cover(ctx, img, 0, 0, W, PH, photo.pos); else hatch(ctx, 0, 0, W, PH);
        const fade = ctx.createLinearGradient(0, PH - 330, 0, PH);
        fade.addColorStop(0, 'rgba(11,11,11,0)'); fade.addColorStop(1, '#0b0b0b');
        ctx.fillStyle = fade; ctx.fillRect(0, PH - 330, W, 331);
        sun(ctx, W / 2, PH - 6, 52, GOLD);

        // the greeting: Sirwan's calligraphy for Kurdish, Inter for English
        ctx.fillStyle = GOLD; ctx.textAlign = 'center';
        const title = d.title[lang] || d.title.en;
        const tSize = ku ? fitFont(ctx, 'ku', title, W - 2 * M, 112, 400, '"CardSirwan", ' + KU)
            : fitFont(ctx, 'en', title, W - 2 * M, 96, 200);
        let y = PH + 112 + tSize * 0.35;
        ctx.fillText(title, W / 2, y);

        // the line under it
        const line = d.text[lang] || '';
        if (line) {
            ctx.fillStyle = 'rgba(255,255,255,.74)';
            setFont(ctx, lang, ku ? 36 : 33, 300); ctx.textAlign = 'center';
            const lines = wrap(ctx, line, W - 2 * M - 80, 2);
            y += 58;
            lines.forEach(l => { y += ku ? 54 : 50; ctx.fillText(l, W / 2, y); });
        }

        // from them
        const name = (choice.name || '').trim();
        if (name) {
            ctx.fillStyle = '#fff';
            const from = (d.from[lang] || '— {name}').replace('{name}', name);
            fitFont(ctx, lang, from, W - 2 * M, 44, 400);
            ctx.textAlign = 'center';
            ctx.fillText(from, W / 2, Math.min(H - 150, y + 92));
        }

        // the studio, along the foot
        ctx.strokeStyle = 'rgba(245,197,24,.35)'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(M, H - 96); ctx.lineTo(W - M, H - 96); ctx.stroke();
        ctx.fillStyle = 'rgba(255,255,255,.6)';
        const brand = (d.brand[lang] || d.brand.en) + ' · ' + d.host;
        if (ku) { setFont(ctx, 'ku', 26, 400); ctx.textAlign = 'center'; ctx.fillText(brand, W / 2, H - 48); }
        else { setFont(ctx, 'en', 19, 400); spaced(ctx, brand, W / 2, H - 50, 5, 'center'); }
        return canvas;
    }

    /* ---- Sending it on ---------------------------------------------------
       Through the phone's share sheet (WhatsApp, its Status…), or saved
       where there is none — or when "save" was asked for. Counted for the
       dashboard either way. */
    function canShareFiles() {
        try {
            return !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'x.jpg', { type: 'image/jpeg' })] }));
        } catch (e) { return false; }
    }
    async function send(canvas, o) {
        const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.92));
        if (!blob) return 'failed';
        const track = () => { if (window.siteTrack) window.siteTrack('share', { placement: o.placement, project: o.project }); };
        if (o.share !== false && canShareFiles()) {
            try {
                const file = new File([blob], o.file, { type: 'image/jpeg' });
                await navigator.share(Object.assign({ files: [file] }, o.text ? { text: o.text } : {}));
                track();
                return 'shared';
            } catch (e) {
                if (e && e.name === 'AbortError') return 'cancelled';
                // otherwise: saved, below
            }
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = o.file;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 8000);
        track();
        return 'saved';
    }

    window.siteCards = { story, greeting, send, canShareFiles };

    /* ---- "Picture for Status", on a project's page -----------------------
       The card drawn, shown, then shared or saved from there: a share
       sheet has to be opened by a tap of its own. */
    const btn = document.querySelector('[data-story]');
    if (!btn) return;
    let data = null;
    try { data = JSON.parse(btn.dataset.story); } catch (e) { return; }
    btn.hidden = false;

    let dialog = null, canvas = null;
    function build() {
        dialog = document.createElement('dialog');
        dialog.className = 'sc';
        dialog.setAttribute('aria-label', btn.dataset.title || '');
        dialog.innerHTML =
            '<div class="sc__frame"><canvas class="sc__canvas" width="1080" height="1920"></canvas><span class="sc__wait" aria-hidden="true"></span></div>' +
            '<div class="sc__side">' +
            '<p class="sc__hint"></p>' +
            '<div class="sc__actions">' +
            (canShareFiles() ? '<button type="button" class="pp-btn sc__share" data-act="share"><span></span></button>' : '') +
            '<button type="button" class="pp-act sc__save" data-act="save"></button>' +
            '</div></div>' +
            '<button type="button" class="sc__close" data-act="close">✕</button>';
        document.body.appendChild(dialog);
        canvas = dialog.querySelector('canvas');
        dialog.querySelector('.sc__hint').textContent = btn.dataset.hint || '';
        const share = dialog.querySelector('.sc__share span');
        if (share) share.textContent = btn.dataset.share || '';
        dialog.querySelector('.sc__save').textContent = btn.dataset.save || '';
        dialog.querySelector('.sc__close').setAttribute('aria-label', btn.dataset.close || 'Close');
        dialog.addEventListener('click', e => {
            const act = e.target.closest('[data-act]');
            if (e.target === dialog || (act && act.dataset.act === 'close')) { dialog.close(); return; }
            if (!act || dialog.classList.contains('is-drawing')) return;
            send(canvas, { file: data.file, placement: 'story', project: data.slug, text: data.url, share: act.dataset.act === 'share' });
        });
    }

    btn.addEventListener('click', () => {
        if (!dialog) build();
        dialog.classList.add('is-drawing');
        if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', '');
        story(data, canvas).then(() => dialog.classList.remove('is-drawing'));
    });
})();
