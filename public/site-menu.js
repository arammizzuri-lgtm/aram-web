/* =========================================================
   ARAM MIZURI ARCHITECTURE — site-menu.js
   The Menu button on the standalone pages' top bar (partials/page-top),
   on a tablet or phone: it unfolds the site as a list of numbered sheets
   under the bar, and folds it away again — with the button, Escape, a tap
   outside it, or a link followed.
   ========================================================= */
(function () {
    'use strict';

    const btn = document.querySelector('[data-menu-open]');
    const sheet = document.getElementById('ppSheet');
    if (!btn || !sheet) return;

    const top = document.querySelector('.pp-top');
    const isOpen = () => btn.getAttribute('aria-expanded') === 'true';

    function open() {
        sheet.hidden = false;
        // one frame shown before it unfolds, so the unfolding is seen
        requestAnimationFrame(() => requestAnimationFrame(() => sheet.classList.add('is-open')));
        btn.setAttribute('aria-expanded', 'true');
        document.documentElement.classList.add('pp-menu-open');
        if (top) top.classList.add('is-solid');
        const first = sheet.querySelector('a');
        if (first) first.focus({ preventScroll: true });
    }

    function close(returnFocus) {
        if (!isOpen()) return;
        sheet.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.documentElement.classList.remove('pp-menu-open');
        if (top && window.scrollY <= 40) top.classList.remove('is-solid');
        const done = () => { if (!isOpen()) sheet.hidden = true; };
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) done();
        else setTimeout(done, 380);
        if (returnFocus) btn.focus({ preventScroll: true });
    }

    btn.addEventListener('click', () => (isOpen() ? close(true) : open()));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(true); });
    document.addEventListener('click', e => {
        if (isOpen() && !sheet.contains(e.target) && !btn.contains(e.target)) close(false);
    });
    // a link followed folds it away
    sheet.addEventListener('click', e => { if (e.target.closest('a')) close(false); });
    // turned to a computer's width, the menu runs across the bar instead
    const wide = window.matchMedia('(min-width: 1101px)');
    const fit = () => { if (wide.matches) close(false); };
    if (wide.addEventListener) wide.addEventListener('change', fit);
    // back to this page from the next one, the menu is not left open
    window.addEventListener('pageshow', () => close(false));
})();
