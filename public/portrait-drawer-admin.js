/* =========================================================
   ARAM MIZURI ARCHITECTURE — portrait-drawer-admin.js
   "The drawing" on a client quote's edit page in the admin
   (views/filament/portrait-drawer): draws the client's portrait from their
   saved photo in this browser (portrait-drawer.js), shows it drawing
   itself line after line (pencil.js), and keeps it — the strokes go to
   the page's saveDrawing(), which writes the website's drawing.

   A photo with no drawing yet is drawn as soon as the page opens; "Draw
   again" draws it with fewer or more lines, with or without shading and
   guide lines. Mounted by Alpine's x-init, so it also starts on a field
   that appears after a save.
   ========================================================= */

function loadPencil(src) {
    if (window.pencil) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}

export async function mount(el) {
    if (el.dataset.mounted) return;
    el.dataset.mounted = '1';

    const paper = el.querySelector('.pd__paper');
    const status = el.querySelector('.pd__status');
    const detail = el.querySelector('.pd__detail');
    const shade = el.querySelector('.pd__shade');
    const guides = el.querySelector('.pd__guides');
    const again = el.querySelector('.pd__again');
    detail.value = el.dataset.detail || 'balanced';

    const say = (text, bad) => { status.textContent = text; status.classList.toggle('pd__status--bad', !!bad); };
    let frame = 0;

    // set an SVG drawing on the paper and draw it, line after line, in $ms
    async function show(svgText, ms) {
        await loadPencil(el.dataset.pencil);
        const doc = new DOMParser().parseFromString(svgText, 'image/svg+xml');
        const svg = document.importNode(doc.documentElement, true);
        paper.replaceChildren(svg);
        const pen = window.pencil(svg);
        cancelAnimationFrame(frame);
        const t0 = performance.now();
        const step = now => {
            const t = Math.min(1, (now - t0) / ms);
            pen.render(t);
            if (t < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
    }

    async function draw() {
        again.disabled = true;
        say('Finding the face… The first time, the face finder takes a few seconds to load (about 20 MB).');
        try {
            const [{ drawPortrait, toSvg }] = await Promise.all([import(el.dataset.drawer), loadPencil(el.dataset.pencil)]);
            const img = new Image();
            img.src = el.dataset.photo;
            await img.decode();
            const drawing = await drawPortrait(img, { detail: detail.value, shading: shade.checked, guides: guides.checked });
            say('Drawing… and saving.');
            show(toSvg(drawing), 6000);
            const root = el.closest('[wire\\:id]');
            await window.Livewire.find(root.getAttribute('wire:id')).call('saveDrawing', drawing, detail.value);
            say('Drawn and saved: ' + drawing.strokes.length + ' strokes. On the website it draws itself like this — in step with the voice, if there is one.');
        } catch (e) {
            say((e && e.message) || 'The portrait could not be drawn.', true);
        } finally {
            again.disabled = false;
        }
    }

    again.addEventListener('click', draw);

    if (el.dataset.drawing) {
        try {
            const r = await fetch(el.dataset.drawing, { credentials: 'same-origin' });
            if (!r.ok) throw new Error();
            await show(await r.text(), 4000);
            say('“Draw again” draws it with fewer or more lines, or without shading or guide lines.');
        } catch (e) {
            draw();
        }
    } else {
        draw();
    }
}
