/* =========================================================
   ARAM MIZURI ARCHITECTURE — projects.js
   The projects page (/projects), a studio's set of drawings:
   · the legend and the statuses filter at once and the search narrows as
     it is typed; the address keeps step (?type=…&status=…&q=…&view=sheets),
     so any view can be sent;
   · Index or Sheets, remembered for the next visit;
   · in the Index, the project's picture comes up beside the pointer and
     follows it, leaning a little the way it is moved;
   · on a computer or tablet the Sheets are a wall across the window: the
     sheets packed into the bays of a structural grid — lettered across the
     top, numbered down the side — gliding to their new bays when the
     legend changes, the bays of the sheet pointed at lit, a disc going
     with the pointer, and the pictures drifting in their frames;
   · rows and sheets come into view as the page is scrolled.
   Without this script the legend and the views still work, as links, and
   the browser packs the wall itself.
   ========================================================= */
(function () {
    'use strict';

    const page = document.getElementById('px');
    const bar = document.getElementById('pxBar');
    if (!page || !bar) return;

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const wallWide = window.matchMedia('(min-width: 761px)');
    const rtl = document.documentElement.getAttribute('dir') === 'rtl';
    const keys = Array.from(bar.querySelectorAll('.px-key[data-type]'));
    const statusKeys = Array.from(bar.querySelectorAll('.px-key[data-status]'));
    const views = Array.from(bar.querySelectorAll('.px-view'));
    const search = document.getElementById('pxSearch');
    const index = document.getElementById('pxIndex');
    const sheets = document.getElementById('pxSheets');
    const wall = document.getElementById('pxWall');
    const wallGrid = document.getElementById('pxWallGrid');
    const tileList = document.getElementById('pxTiles');
    const tiles = Array.from(tileList.children);          // every sheet, and the blank one last
    const yours = tileList.querySelector('[data-yours]');
    const cards = tiles.filter(t => t !== yours);          // the projects' own sheets
    const years = Array.from(index.querySelectorAll('.px-year'));
    const rows = Array.from(index.querySelectorAll('.px-row'));
    const count = document.getElementById('pxCount');
    const empty = document.getElementById('pxEmpty');
    const clear = document.getElementById('pxClear');

    let type = (keys.find(k => k.classList.contains('is-on')) || { dataset: {} }).dataset.type || '';
    let status = (statusKeys.find(k => k.classList.contains('is-on')) || { dataset: {} }).dataset.status || '';
    let view = page.dataset.view === 'sheets' ? 'sheets' : 'index';
    let hidePeek = () => {};      // set below, where there is a picture to hide
    let drift = () => {};         // likewise, where the pictures drift

    page.classList.add('px-js');

    /* ---- The top bar turns to frosted glass once the page moves ---- */
    const top = document.querySelector('.pp-top');
    if (top) {
        const solid = () => top.classList.toggle('is-solid', window.scrollY > 40);
        window.addEventListener('scroll', solid, { passive: true });
        solid();
    }

    /* ---- The wall ---------------------------------------------------------
       The bays are filled one after another, row by row: each empty bay
       takes the first of the next few sheets that fits it — large ones two
       by two, wide ones two across — so a small sheet steps in where a large
       one would leave a gap, and the studio's order holds but for a step or
       two. The blank sheet takes the room left at the end. Then the grid is
       drawn behind: dashed lines along the bays' edges, a lettered bubble
       over each column and a numbered one beside each row. */
    const LETTERS = 'ABCDEFGHIJKL';
    const LOOK_AHEAD = 5;
    let packed = false;
    let bubbles = { cols: [], rows: [] };

    const columnsFor = width => (width >= 1500 ? 6 : width >= 1150 ? 5 : width >= 860 ? 4 : 3);

    function unpack() {
        tiles.forEach(t => {
            t.style.gridColumn = '';
            t.style.gridRow = '';
            const img = t.querySelector('.px-sheet__pic img');
            if (img) img.style.translate = '';
            if (t !== yours) {
                const w = +t.dataset.w || 1, h = +t.dataset.h || 1;
                t.classList.toggle('px-sheet--l', w > 1 && h > 1);
                t.classList.toggle('px-sheet--w', w > 1 && h === 1);
                t.classList.toggle('px-sheet--s', w === 1);
            }
        });
        tileList.style.removeProperty('--cols');
        tileList.style.removeProperty('--row');
        wallGrid.replaceChildren();
        bubbles = { cols: [], rows: [] };
        packed = false;
    }

    function layoutWall() {
        if (!wallWide.matches || sheets.hidden) {
            if (packed) unpack();
            return;
        }
        const width = tileList.clientWidth;
        if (!width) return;
        const gap = parseFloat(getComputedStyle(tileList).columnGap) || 16;
        const cols = columnsFor(width + gap);
        const cell = (width - (cols - 1) * gap) / cols;
        const row = Math.round(cell * 0.72);
        tileList.style.setProperty('--cols', cols);
        tileList.style.setProperty('--row', row + 'px');

        const visible = cards.filter(t => !t.hidden);
        let shapes = new Map(visible.map(t => [t, [Math.min(+t.dataset.w || 1, cols), +t.dataset.h || 1]]));
        let best = pack(visible, cols, shapes);

        // A wall that would end ragged — bays left empty that the blank sheet
        // cannot fill — has one of its last large or wide sheets drawn a size
        // smaller, if that leaves fewer empty bays: three at most, and only
        // among the last few in the studio's order, never the first ones.
        for (let round = 0; round < 3 && best.waste > 0; round++) {
            let better = null;
            visible.slice(-8).forEach(t => {
                const [w, h] = shapes.get(t);
                const smaller = w > 1 && h > 1 ? [[2, 1], [1, 1]] : w > 1 ? [[1, 1]] : [];
                smaller.forEach(shape => {
                    const trial = new Map(shapes).set(t, shape);
                    const result = pack(visible, cols, trial);
                    if (result.waste < (better ? better.result.waste : best.waste)) better = { result, trial };
                });
            });
            if (!better) break;
            best = better.result;
            shapes = better.trial;
        }

        best.places.forEach((p, t) => place(t, ...p));
        place(yours, ...best.blank);
        drawGrid(cols, best.rows, cell, row, gap);
        packed = true;
        drift();
    }

    // A sheet into its bays: its grid position, its shape (which sets its
    // title block), and the bays it stands in, under its number.
    function place(el, x, y, w, h) {
        el.style.gridColumn = (x + 1) + ' / span ' + w;
        el.style.gridRow = (y + 1) + ' / span ' + h;
        Object.assign(el.dataset, { x, y, cw: w, ch: h });
        if (el !== yours) {
            el.classList.toggle('px-sheet--l', w > 1 && h > 1);
            el.classList.toggle('px-sheet--w', w > 1 && h === 1);
            el.classList.toggle('px-sheet--s', w === 1);
        }
        const ref = el.querySelector('.px-tb__ref');
        if (ref) ref.textContent = LETTERS[x] + (y + 1) + (w > 1 || h > 1 ? '–' + LETTERS[x + w - 1] + (y + h) : '');
    }

    // Pack the sheets into a wall `cols` bays wide, each in the shape `shapes`
    // gives it, then the blank sheet into the room left. Says where each
    // goes, how many rows the wall runs to, and how many bays stay empty.
    function pack(list, cols, shapes) {
        const filled = [];                 // filled[y][x]: 0 empty, 1 a sheet, 2 a bay nothing left could fill
        const at = (x, y) => (filled[y] ? filled[y][x] : 0);
        const free = (x, y, w, h) => {
            for (let j = y; j < y + h; j++) for (let i = x; i < x + w; i++) if (at(i, j)) return false;
            return true;
        };
        const mark = (x, y, w, h, v) => {
            for (let j = y; j < y + h; j++) {
                filled[j] = filled[j] || new Array(cols).fill(0);
                for (let i = x; i < x + w; i++) filled[j][i] = v;
            }
        };

        const places = new Map();
        const queue = list.slice();
        let cx = 0, cy = 0;
        while (queue.length) {
            while (at(cx, cy)) { if (++cx === cols) { cx = 0; cy++; } }
            const fits = t => { const [w, h] = shapes.get(t); return cx + w <= cols && free(cx, cy, w, h); };
            let k = queue.slice(0, LOOK_AHEAD).findIndex(fits);
            if (k < 0) k = queue.findIndex(fits);
            if (k < 0) { mark(cx, cy, 1, 1, 2); continue; }
            const t = queue.splice(k, 1)[0];
            const [w, h] = shapes.get(t);
            mark(cx, cy, w, h, 1);
            places.set(t, [cx, cy, w, h]);
        }

        // The blank sheet: the first room left, in reading order — as wide as
        // the free bays run there, and two rows deep if the row below is free
        // too — or, on a wall with no room left, a row of its own across it.
        let blank = [0, filled.length, cols, 1];
        outer: for (let y = 0; y < filled.length; y++) {
            for (let x = 0; x < cols; x++) {
                if (filled[y][x] === 1) continue;
                let w = 0;
                while (x + w < cols && filled[y][x + w] !== 1) w++;
                const below = filled[y + 1];
                blank = [x, y, w, below && below.slice(x, x + w).every(v => v !== 1) ? 2 : 1];
                break outer;
            }
        }
        mark(...blank, 1);

        let waste = 0;
        filled.forEach(r => r.forEach(v => { if (v !== 1) waste++; }));
        return { places, blank, rows: filled.length, waste };
    }

    function drawGrid(cols, rowCount, cell, row, gap) {
        // where the bays start, from the wall's inline start (its right, in Kurdish)
        const ox = rtl ? wall.clientWidth - tileList.offsetLeft - tileList.offsetWidth : tileList.offsetLeft;
        const oy = tileList.offsetTop;
        const frag = document.createDocumentFragment();
        const add = (className, start, topPx, text) => {
            const el = document.createElement('i');
            el.className = className;
            if (start !== null) el.style.insetInlineStart = start + 'px';
            if (topPx !== null) el.style.top = topPx + 'px';
            if (text) el.textContent = text;
            frag.appendChild(el);
            return el;
        };
        for (let i = 0; i <= cols; i++) add('px-gl px-gl--v', ox + i * (cell + gap) - gap / 2, null);
        for (let j = 0; j <= rowCount; j++) add('px-gl px-gl--h', null, oy + j * (row + gap) - gap / 2);
        bubbles = { cols: [], rows: [] };
        for (let i = 0; i < cols; i++) bubbles.cols.push(add('px-bub', ox + i * (cell + gap) + cell / 2, oy / 2, LETTERS[i]));
        for (let j = 0; j < rowCount; j++) bubbles.rows.push(add('px-bub', ox / 2, oy + j * (row + gap) + row / 2, String(j + 1)));
        wallGrid.replaceChildren(frag);
    }

    // From where each sheet was to where it is now (and its new size, for the
    // blank sheet), so the wall rearranges itself rather than jumping.
    function glide(before) {
        tiles.forEach(t => {
            const a = before.get(t);
            if (t.hidden || !a || !t.animate) return;
            const b = t.getBoundingClientRect();
            const dx = a.left - b.left, dy = a.top - b.top, sx = a.width / b.width, sy = a.height / b.height;
            if (Math.abs(dx) < 1 && Math.abs(dy) < 1 && Math.abs(sx - 1) < 0.01 && Math.abs(sy - 1) < 0.01) return;
            t.animate([
                { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(' + sx + ',' + sy + ')', transformOrigin: 'top left' },
                { transform: 'none', transformOrigin: 'top left' },
            ], { duration: 700, easing: 'cubic-bezier(.16,1,.3,1)' });
        });
    }

    // the bubbles over the columns and beside the rows a sheet stands in
    function light(t, on) {
        if (!packed) return;
        const x = +t.dataset.x, y = +t.dataset.y, w = +t.dataset.cw, h = +t.dataset.ch;
        for (let i = x; i < x + w; i++) if (bubbles.cols[i]) bubbles.cols[i].classList.toggle('is-lit', on);
        for (let j = y; j < y + h; j++) if (bubbles.rows[j]) bubbles.rows[j].classList.toggle('is-lit', on);
    }

    /* ---- Filter and search ------------------------------------------- */
    // Kurdish phones type ٠–٩; the projects are searched in 0–9
    const plain = s => s.toLowerCase().replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).trim();

    function apply() {
        const words = plain(search ? search.value : '').split(/\s+/).filter(Boolean);
        const fits = el => (!type || el.dataset.categories.split(' ').includes(type))
            && (!status || el.dataset.status === status)
            && words.every(w => el.dataset.search.includes(w));

        const before = packed && !still ? new Map(tiles.filter(t => !t.hidden).map(t => [t, t.getBoundingClientRect()])) : null;
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

        layoutWall();
        if (before) glide(before);
    }

    function remember() {
        const url = new URL(location.href);
        const q = search ? search.value.trim() : '';
        const keep = (name, value) => { if (value) url.searchParams.set(name, value); else url.searchParams.delete(name); };
        keep('type', type);
        keep('status', status);
        keep('q', q);
        keep('view', view === 'sheets' ? 'sheets' : '');
        history.replaceState(null, '', url);
        // the other language keeps them too
        const other = document.querySelector('.pp-lang');
        if (other) {
            const there = new URL(other.href, location.href);
            there.search = url.search;
            other.href = there.toString();
        }
    }

    const mark = (list, attr, value) => list.forEach(k => {
        const on = k.dataset[attr] === value;
        k.classList.toggle('is-on', on);
        if (on) k.setAttribute('aria-current', 'true'); else k.removeAttribute('aria-current');
    });

    function choose(next) {
        type = next;
        mark(keys, 'type', type);
        remember();
        apply();
    }

    function chooseStatus(next) {
        status = next;
        mark(statusKeys, 'status', status);
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
        layoutWall();
    }

    // a plain click is answered here; a click asking for a new tab is left to the browser
    const plainClick = e => !(e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0);
    keys.forEach(k => k.addEventListener('click', e => { if (plainClick(e)) { e.preventDefault(); choose(k.dataset.type); } }));
    statusKeys.forEach(k => k.addEventListener('click', e => { if (plainClick(e)) { e.preventDefault(); chooseStatus(k.dataset.status); } }));
    views.forEach(v => v.addEventListener('click', e => { if (plainClick(e)) { e.preventDefault(); show(v.dataset.view); } }));
    // the address takes the search once the typing pauses (browsers limit how often it may change)
    let typing = 0;
    if (search) search.addEventListener('input', () => { apply(); clearTimeout(typing); typing = setTimeout(remember, 400); });
    if (clear) clear.addEventListener('click', e => {
        e.preventDefault();
        if (search) search.value = '';
        status = '';
        mark(statusKeys, 'status', '');
        choose('');
    });

    /* ---- Coming into view ----------------------------------------------
       Those arriving together come in one after another, a beat apart. */
    if (!still && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            const arriving = entries.filter(e => e.isIntersecting).map(e => e.target);
            arriving.forEach((el, n) => {
                el.style.setProperty('--d', Math.min(n, 8) * 0.07 + 's');
                el.classList.add('is-in');
                io.unobserve(el);
                // once in, it answers the pointer at once, without the beat
                setTimeout(() => el.style.removeProperty('--d'), 1600);
            });
        }, { rootMargin: '0px 0px -8% 0px' });
        rows.concat(tiles).forEach(el => io.observe(el));
    } else {
        rows.concat(tiles).forEach(el => el.classList.add('is-in'));
    }

    /* ---- Depth: the pictures drift in their frames as the wall scrolls ---- */
    if (!still && 'IntersectionObserver' in window) {
        const inView = new Set();
        const watch = new IntersectionObserver(entries => entries.forEach(e => {
            if (e.isIntersecting) inView.add(e.target); else inView.delete(e.target);
        }));
        tiles.forEach(t => watch.observe(t));
        let queued = false;
        drift = () => {
            queued = false;
            if (!packed) return;
            const vh = window.innerHeight;
            inView.forEach(t => {
                const img = t.querySelector('.px-sheet__pic img');
                if (!img) return;
                const r = t.getBoundingClientRect();
                const p = Math.max(-1, Math.min(1, (r.top + r.height / 2 - vh / 2) / vh));
                img.style.translate = '0 ' + (p * r.height * -0.05).toFixed(1) + 'px';
            });
        };
        window.addEventListener('scroll', () => { if (!queued) { queued = true; requestAnimationFrame(drift); } }, { passive: true });
    }

    /* ---- The disc beside the pointer on the wall, and its bays lit ---- */
    const disc = document.getElementById('pxDisc');
    if (disc && fine && !still) {
        const label = disc.querySelector('.px-disc__label');
        let x = 0, y = 0, tx = 0, ty = 0, on = false, frame = 0;
        const follow = () => {
            x += (tx - x) * 0.22;
            y += (ty - y) * 0.22;
            disc.style.transform = 'translate3d(' + x.toFixed(1) + 'px,' + y.toFixed(1) + 'px,0)';
            frame = (on || Math.abs(tx - x) + Math.abs(ty - y) > 0.5) ? requestAnimationFrame(follow) : 0;
        };
        tiles.forEach(t => {
            const link = t.querySelector('.px-sheet__link');
            link.addEventListener('mouseenter', e => {
                if (!packed) return;                     // a phone's list: no disc
                tx = e.clientX; ty = e.clientY;
                if (!on) { x = tx; y = ty; }
                on = true;
                label.textContent = link.dataset.disc || '';
                disc.classList.add('is-on');
                light(t, true);
                if (!frame) frame = requestAnimationFrame(follow);
            });
            link.addEventListener('mousemove', e => { tx = e.clientX; ty = e.clientY; });
            link.addEventListener('mouseleave', () => {
                on = false;
                disc.classList.remove('is-on');
                light(t, false);
            });
        });
    }

    /* ---- The picture beside the pointer (the Index, with a mouse) ------- */
    const peek = document.getElementById('pxPeek');
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

    /* ---- The wall keeps to the window ----------------------------------- */
    if ('ResizeObserver' in window) {
        // the years stay just under the bar, however many lines its keys take
        new ResizeObserver(() => page.style.setProperty('--bar-h', bar.offsetHeight + 'px')).observe(bar);
        let lastWidth = -1;
        new ResizeObserver(() => {
            const w = tileList.clientWidth;
            if (w !== lastWidth) { lastWidth = w; requestAnimationFrame(() => layoutWall()); }
        }).observe(tileList);
    } else {
        window.addEventListener('resize', () => layoutWall());
    }
    if (wallWide.addEventListener) wallWide.addEventListener('change', () => layoutWall());

    // On a phone the keys run on in one strip: one asked for in the address (from a
    // service on the homepage, say) is brought into it, so it shows what is chosen.
    const strip = bar.querySelector('.px-keys');
    const chosen = bar.querySelector('.px-key[data-status].is-on:not([data-status=""])')
        || bar.querySelector('.px-key[data-type].is-on:not([data-type=""])');
    if (strip && chosen && strip.scrollWidth > strip.clientWidth) {
        const a = chosen.getBoundingClientRect(), s = strip.getBoundingClientRect();
        strip.scrollBy({ left: rtl ? a.right - s.right + 24 : a.left - s.left - 24 });
    }

    // the view chosen last time (the head already showed it), unless the address names one
    if (document.documentElement.classList.contains('px-want-sheets')) show('sheets', true);
    else layoutWall();
})();
