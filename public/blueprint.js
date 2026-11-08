/* =========================================================
   ARAM MIZURI ARCHITECTURE — blueprint.js
   "Blueprint view": a switch in the menu bar that turns the whole site
   into a blueprint — deep blue paper, a white drafting grid, white
   lines and type, every photograph printed as a cyanotype. Remembered per
   visitor (localStorage). The styles are blueprint.css, fetched the
   first time the view is turned on, so a visitor who never uses it never
   downloads them.
   ========================================================= */
(function () {
    'use strict';
    const KEY = 'am_blueprint';
    const root = document.documentElement;
    const css = (document.currentScript && document.currentScript.dataset.css) || '/blueprint.css';
    let loaded = false;

    function loadCss() {
        if (loaded) return;
        loaded = true;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = css;
        document.head.appendChild(link);
    }

    function set(on, remember) {
        if (on) loadCss();
        root.classList.toggle('bp', on);
        document.querySelectorAll('[data-blueprint]').forEach(btn => {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            const label = btn.querySelector('[data-blueprint-label]');
            const text = on ? btn.dataset.off : btn.dataset.on;
            if (label) label.textContent = text;
            btn.setAttribute('aria-label', text);
            btn.title = text;
        });
        if (remember) {
            try { on ? localStorage.setItem(KEY, '1') : localStorage.removeItem(KEY); } catch (e) { /* not kept */ }
        }
    }

    let saved = false;
    try { saved = localStorage.getItem(KEY) === '1'; } catch (e) { /* storage blocked */ }
    if (saved) set(true, false);

    function wire() {
        document.querySelectorAll('[data-blueprint]').forEach(btn => {
            btn.addEventListener('click', () => set(!root.classList.contains('bp'), true));
        });
        set(root.classList.contains('bp'), false);   // labels in step
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wire);
    else wire();
})();
