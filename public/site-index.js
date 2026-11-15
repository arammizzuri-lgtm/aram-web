/* =========================================================
   ARAM MIZURI ARCHITECTURE — site-index.js
   The menu on every page (partials/site-index, site-index.css).
   · The Index button opens the site as a sheet index: a modal <dialog>, so
     the keyboard stays inside it, Escape or Close shuts it, and focus goes
     back to the button. Following a sheet shuts it at once, so a section of
     the page it was opened on scrolls into view.
   · The bar steps aside while the visitor reads down the page and comes
     back on the first scroll up, near the top, or when it takes focus.
   · Keys, as in CAD: ← → the sheet before and after, I the index, and /
     a command line that goes to any sheet by name or number.
   ========================================================= */
(function () {
    'use strict';

    const root = document.documentElement;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── The index ─────────────────────────────────────────────────── */
    const dlg = document.getElementById('siteIndex');
    const openers = document.querySelectorAll('[data-index-open]');
    let openIndex = null;
    if (dlg && openers.length) {
        const modal = typeof dlg.showModal === 'function';
        const expanded = v => openers.forEach(b => b.setAttribute('aria-expanded', v ? 'true' : 'false'));

        const open = () => {
            if (dlg.open) return;
            dlg.classList.remove('is-closing');
            if (modal) dlg.showModal(); else dlg.setAttribute('open', '');
            root.classList.add('has-index');
            expanded(true);
            // the page the visitor is on, or the first sheet
            const start = dlg.querySelector('.sx__link[aria-current]') || dlg.querySelector('.sx__link');
            if (start) start.focus({ preventScroll: true });
        };
        // the page is given back at once (not when the close event comes),
        // so a link to a section of it can scroll there
        const shut = () => {
            dlg.classList.remove('is-closing');
            root.classList.remove('has-index');
            expanded(false);
            if (!dlg.open) return;
            if (modal) dlg.close(); else dlg.removeAttribute('open');
        };
        const close = now => {
            if (!dlg.open || dlg.classList.contains('is-closing')) return;
            if (now || reduce) { shut(); return; }
            dlg.classList.add('is-closing');
            setTimeout(shut, 190);
        };

        openIndex = open;
        openers.forEach(b => b.addEventListener('click', open));
        dlg.querySelectorAll('[data-index-close]').forEach(b => b.addEventListener('click', () => close(false)));
        // Escape: the same fade as Close
        dlg.addEventListener('cancel', e => { e.preventDefault(); close(false); });
        if (!modal) document.addEventListener('keydown', e => { if (e.key === 'Escape') close(false); });
        dlg.addEventListener('close', () => { root.classList.remove('has-index'); expanded(false); });
        // a sheet followed
        dlg.addEventListener('click', e => {
            const a = e.target.closest('a[href]');
            if (a && a.target !== '_blank') close(true);
        });
        // back on this page from the next one, the index is not left open
        window.addEventListener('pageshow', () => close(true));
    }

    /* ── The bar steps aside while reading ─────────────────────────── */
    const bar = document.querySelector('[data-tuck]');
    if (bar) {
        const EDGE = 140;   // px from the top within which the bar always shows
        const STEP = 8;     // px of scroll that counts as a direction
        let lastY = window.scrollY, ticking = false;
        const update = () => {
            ticking = false;
            const y = window.scrollY, dy = y - lastY;
            if (y <= EDGE || root.classList.contains('has-index') || bar.querySelector(':focus-visible')) {
                bar.classList.remove('is-tucked');
                lastY = y;
                return;
            }
            if (Math.abs(dy) < STEP) return;
            bar.classList.toggle('is-tucked', dy > 0);
            lastY = y;
        };
        window.addEventListener('scroll', () => {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        // a keyboard reaching the bar finds it there
        bar.addEventListener('focusin', () => bar.classList.remove('is-tucked'));
    }

    /* ── The command line: / opens it, as in CAD ───────────────────────
       "Command:" at the foot of the screen; the sheets of the index are
       its commands (PROJECTS, MAP, CONTACT… or a sheet's number, or its
       name in the page's language), listed as they are typed. Enter goes,
       Tab completes, ↑ ↓ choose, Escape closes. In English on every
       page, as CAD itself is. */
    const cmd = (() => {
        let box, input, list, note, all = [], shown = [], pick = 0;
        const commands = () => {
            const out = [];
            if (!dlg) return out;
            const text = (el, sel) => ((el.querySelector(sel) || {}).textContent || '').trim();
            dlg.querySelectorAll('.sx__link[data-sheet]').forEach(a => out.push({
                name: a.dataset.sheet.toUpperCase(), code: text(a, '.sx__code'), label: text(a, '.sx__label'), href: a.href,
            }));
            const h = dlg.querySelector('.sx__h-head');
            if (h) out.push({ name: 'HERITAGE', code: text(h, '.sx__code'), label: text(h, '.sx__h-label'), href: h.href });
            if (openIndex) out.push({ name: 'INDEX', code: '', label: text(dlg, '.sx__title'), run: () => { close(); openIndex(); } });
            return out;
        };
        const flat = s => s.toLowerCase().replace(/[\s_\-.]/g, '');
        const match = q => {
            q = flat(q);   // as typed, or as CAD's _PROJECTS
            if (!q) return all;
            return all.filter(c => flat(c.name).startsWith(q) || (c.code && flat(c.code).startsWith(q)) || c.label.toLowerCase().includes(q));
        };
        const render = () => {
            list.innerHTML = '';
            shown.forEach((c, i) => {
                const li = document.createElement('li');
                li.id = 'cmdOpt' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', i === pick ? 'true' : 'false');
                li.innerHTML = '<b></b><span class="cmd__code"></span><span class="cmd__label" dir="auto"></span>';
                li.children[0].textContent = c.name;
                li.children[1].textContent = c.code;
                li.children[2].textContent = c.label;
                li.addEventListener('mousedown', e => { e.preventDefault(); go(c); });
                list.appendChild(li);
            });
            list.hidden = !shown.length;
            input.setAttribute('aria-activedescendant', shown.length ? 'cmdOpt' + pick : '');
        };
        const go = c => {
            note.textContent = 'Command: ' + c.name;
            if (c.run) c.run(); else location.href = c.href;
        };
        const build = () => {
            box = document.createElement('div');
            box.className = 'cmd';
            box.dir = 'ltr';
            box.innerHTML = '<ul class="cmd__list" id="cmdList" role="listbox" hidden></ul>'
                + '<p class="cmd__note" aria-live="polite"></p>'
                + '<label class="cmd__line"><span class="cmd__prompt">Command:</span>'
                + '<input class="cmd__in" type="text" role="combobox" aria-controls="cmdList" aria-expanded="true" aria-autocomplete="list" autocomplete="off" autocapitalize="characters" spellcheck="false"></label>';
            document.body.appendChild(box);
            [list, note, input] = [box.querySelector('.cmd__list'), box.querySelector('.cmd__note'), box.querySelector('.cmd__in')];
            input.addEventListener('input', () => { shown = match(input.value); pick = 0; note.textContent = ''; render(); });
            input.addEventListener('keydown', e => {
                if (e.key === 'Escape') { e.preventDefault(); close(); }
                else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (shown.length) { pick = (pick + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % shown.length; render(); }
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    if (shown[pick]) { input.value = shown[pick].name; shown = [shown[pick]]; pick = 0; render(); }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (shown[pick]) go(shown[pick]);
                    else {
                        note.textContent = 'Unknown command "' + input.value.trim().toUpperCase() + '".';
                        input.value = ''; shown = all; pick = 0; render();
                    }
                }
            });
            input.addEventListener('blur', () => setTimeout(() => { if (document.activeElement !== input) close(); }, 0));
        };
        const open = () => {
            if (!box) build();
            all = commands(); shown = all; pick = 0;
            input.value = ''; note.textContent = '';
            render();
            box.classList.add('is-open');
            input.focus();
        };
        const close = () => { if (box) { box.classList.remove('is-open'); input.blur(); } };
        return { open };
    })();

    /* ── The keyboard: ← → to the sheet before and after, I for the index ──
       Only when nothing else wants the keys: not while typing, nor in a map
       or a slider, nor with a viewer, the index or a project open. Reading
       right to left, ← is the way on. */
    const latin = k => /^[\x20-\x7e]$/.test(k);   // other scripts: go by the key's place
    const wayTo = on => {
        const a = document.querySelector(on ? 'a[rel~="next"]' : 'a[rel~="prev"]');
        if (a || !dlg) return a;
        // a sheet with no way on of its own (home, projects, journal…): its place in the index
        const links = [...dlg.querySelectorAll('.sx__link')];
        const i = links.findIndex(l => l.hasAttribute('aria-current') && l.pathname === location.pathname);
        if (i < 0) return null;
        return on ? links[(i + 1) % links.length] : links[i - 1] || null;
    };
    document.addEventListener('keydown', e => {
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.target.closest && e.target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), .leaflet-container, [role="slider"]')) return;
        if (root.classList.contains('has-index') || document.querySelector('dialog[open], #projOverlay.open')) return;
        if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && !e.shiftKey) {
            const a = wayTo((e.key === 'ArrowRight') !== (root.dir === 'rtl'));
            if (a) { e.preventDefault(); location.href = a.href; }
        } else if (openIndex && (e.key === 'i' || e.key === 'I' || (e.code === 'KeyI' && !latin(e.key)))) {
            e.preventDefault();
            openIndex();
        } else if (dlg && (e.key === '/' || (e.code === 'Slash' && !latin(e.key)))) {
            e.preventDefault();
            cmd.open();
        }
    });
})();
