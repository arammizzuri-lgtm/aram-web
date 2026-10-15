/* =========================================================
   ARAM MIZURI ARCHITECTURE — projects.js
   The projects page (/projects), a studio's set of drawings:
   · the legend filters at once and the search narrows as it is typed;
     the address keeps step (?type=…&view=sheets), so any view can be sent;
   · Index or Sheets, remembered for the next visit;
   · in the Index, the project's picture comes up beside the pointer and
     follows it, leaning a little the way it is moved;
   · rows and sheets come into view as the page is scrolled.
   Without this script the legend and the views still work, as links.
   ========================================================= */
(function () {
    'use strict';

    const page = document.getElementById('px');
    const bar = document.getElementById('pxBar');
    if (!page || !bar) return;

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rtl = document.documentElement.getAttribute('dir') === 'rtl';
    const keys = Array.from(bar.querySelectorAll('.px-key'));
    const views = Array.from(bar.querySelectorAll('.px-view'));
    const search = document.getElementById('pxSearch');
    const index = document.getElementById('pxIndex');
    const sheets = document.getElementById('pxSheets');
    const years = Array.from(index.querySelectorAll('.px-year'));
    const rows = Array.from(index.querySelectorAll('.px-row'));
    const cards = Array.from(sheets.querySelectorAll('.px-sheet'));
    const count = document.getElementById('pxCount');
    const empty = document.getElementById('pxEmpty');
    const clear = document.getElementById('pxClear');

    let type = (keys.find(k => k.classList.contains('is-on')) || { dataset: {} }).dataset.type || '';
    let view = page.dataset.view === 'sheets' ? 'sheets' : 'index';
    let hidePeek = () => {};      // set below, where there is a picture to hide

    page.classList.add('px-js');

    /* ---- The top bar turns to frosted glass once the page moves ---- */
    const top = document.querySelector('.pp-top');
    if (top) {
        const solid = () => top.classList.toggle('is-solid', window.scrollY > 40);
        window.addEventListener('scroll', solid, { passive: true });
        solid();
    }

    /* ---- Filter and search ------------------------------------------- */
    // Kurdish phones type ٠–٩; the projects are searched in 0–9
    const plain = s => s.toLowerCase().replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).trim();

    function apply() {
        const words = plain(search ? search.value : '').split(/\s+/).filter(Boolean);
        const fits = el => (!type || el.dataset.categories.split(' ').includes(type))
            && words.every(w => el.dataset.search.includes(w));

        let shown = 0;
        cards.forEach(card => { card.hidden = !fits(card); if (!card.hidden) shown++; });
        rows.forEach(row => { row.hidden = !fits(row); });
        years.forEach(year => {
            const n = year.querySelectorAll('.px-row:not([hidden])').length;
            year.hidden = n === 0;
            const c = year.querySelector('.px-year__count');
            if (c) c.textContent = n;
        });

        count.textContent = shown === 1 ? bar.dataset.countOne : bar.dataset.count.replace('{n}', shown);
        empty.hidden = shown > 0;
    }

    function remember() {
        const url = new URL(location.href);
        if (type) url.searchParams.set('type', type); else url.searchParams.delete('type');
        if (view === 'sheets') url.searchParams.set('view', 'sheets'); else url.searchParams.delete('view');
        history.replaceState(null, '', url);
    }

    function choose(next) {
        type = next;
        keys.forEach(k => {
            const on = k.dataset.type === type;
            k.classList.toggle('is-on', on);
            if (on) k.setAttribute('aria-current', 'true'); else k.removeAttribute('aria-current');
        });
        remember();
        apply();
    }

    function show(next, keepAddress) {
        view = next;
        document.documentElement.classList.remove('px-want-sheets');
        page.dataset.view = view;
        index.hidden = view !== 'index';
        sheets.hidden = view !== 'sheets';
        views.forEach(v => {
            const on = v.dataset.view === view;
            v.classList.toggle('is-on', on);
            v.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        try { localStorage.setItem('am_projects_view', view); } catch (e) { /* not kept: fine */ }
        if (!keepAddress) remember();
        hidePeek();
    }

    // a plain click is answered here; a click asking for a new tab is left to the browser
    const plainClick = e => !(e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0);
    keys.forEach(k => k.addEventListener('click', e => { if (plainClick(e)) { e.preventDefault(); choose(k.dataset.type); } }));
    views.forEach(v => v.addEventListener('click', e => { if (plainClick(e)) { e.preventDefault(); show(v.dataset.view); } }));
    if (search) search.addEventListener('input', apply);
    if (clear) clear.addEventListener('click', e => { e.preventDefault(); if (search) search.value = ''; choose(''); });

    // the view chosen last time (the head already showed it), unless the address names one
    if (document.documentElement.classList.contains('px-want-sheets')) show('sheets', true);

    /* ---- Coming into view ----------------------------------------------
       Those arriving together come in one after another, a beat apart. */
    if (!still && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            const arriving = entries.filter(e => e.isIntersecting).map(e => e.target);
            arriving.forEach((el, n) => {
                el.style.setProperty('--d', Math.min(n, 8) * 0.07 + 's');
                el.classList.add('is-in');
                io.unobserve(el);
            });
        }, { rootMargin: '0px 0px -8% 0px' });
        rows.concat(cards).forEach(el => io.observe(el));
    } else {
        rows.concat(cards).forEach(el => el.classList.add('is-in'));
    }

    /* ---- The picture beside the pointer (the Index, with a mouse) ------- */
    const peek = document.getElementById('pxPeek');
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    if (peek && fine && !still) {
        const img = peek.querySelector('img');
        const num = peek.querySelector('.px-peek__num');
        let x = 0, y = 0, tx = 0, ty = 0, lean = 0, frame = 0, on = false, current = null;

        const place = () => {
            const vx = tx - x;
            x += vx * 0.16;
            y += (ty - y) * 0.16;
            lean += (Math.max(-7, Math.min(7, vx * 0.08)) - lean) * 0.12;
            peek.style.transform = 'translate3d(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px,0) rotate(' + lean.toFixed(2) + 'deg)';
            frame = (on || Math.abs(vx) > 0.5) ? requestAnimationFrame(place) : 0;
        };
        const target = e => {
            const w = peek.offsetWidth, h = peek.offsetHeight, gap = 34;
            // beside the pointer, on the side with room; kept inside the window
            let px = rtl ? e.clientX - w - gap : e.clientX + gap;
            if (px + w > window.innerWidth - 12) px = e.clientX - w - gap;
            if (px < 12) px = e.clientX + gap;
            tx = px;
            ty = Math.max(12, Math.min(window.innerHeight - h - 12, e.clientY - h / 2));
        };

        // Pictures are fetched as they are wanted: the one pointed at, and the
        // rows either side of it, so moving down the list finds them ready.
        const fetched = new Map();
        const fetchPicture = row => {
            if (!row || !row.dataset.img) return Promise.resolve(false);
            if (!fetched.has(row)) {
                const pic = new Image();
                pic.src = row.dataset.img;
                fetched.set(row, (pic.decode ? pic.decode() : Promise.resolve()).then(() => true, () => false));
            }
            return fetched.get(row);
        };

        rows.forEach((row, i) => {
            const link = row.querySelector('.px-row__link');
            link.addEventListener('mouseenter', e => {
                target(e);
                current = row;
                fetchPicture(rows[i - 1]);
                fetchPicture(rows[i + 1]);
                fetchPicture(row).then(ok => {
                    if (current !== row || !ok) return;   // moved on, or no picture to show
                    img.style.objectPosition = row.dataset.pos || '50% 50%';
                    num.textContent = 'Nº ' + (row.dataset.num || '');
                    img.src = row.dataset.img;
                    if (!on) { x = tx; y = ty; }          // appears where the pointer is, then follows
                    on = true;
                    peek.classList.add('is-on');
                    if (!frame) frame = requestAnimationFrame(place);
                });
            });
            link.addEventListener('mousemove', target);
        });
        hidePeek = () => { on = false; current = null; peek.classList.remove('is-on'); };
        index.addEventListener('mouseleave', hidePeek);
        window.addEventListener('scroll', () => { if (on && !index.matches(':hover')) hidePeek(); }, { passive: true });
    }
})();
