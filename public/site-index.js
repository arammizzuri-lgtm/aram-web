/* =========================================================
   ARAM MIZURI ARCHITECTURE — site-index.js
   The menu on every page (partials/site-index, site-index.css).
   · The Index button opens the site as a sheet index: a modal <dialog>, so
     the keyboard stays inside it, Escape or Close shuts it, and focus goes
     back to the button. Following a sheet shuts it at once, so a section of
     the page it was opened on scrolls into view.
   · The bar steps aside while the visitor reads down the page and comes
     back on the first scroll up, near the top, or when it takes focus.
   ========================================================= */
(function () {
    'use strict';

    const root = document.documentElement;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ── The index ─────────────────────────────────────────────────── */
    const dlg = document.getElementById('siteIndex');
    const openers = document.querySelectorAll('[data-index-open]');
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
})();
