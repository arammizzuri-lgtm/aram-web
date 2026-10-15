/* =========================================================
   ARAM MIZURI ARCHITECTURE — projects.js
   The projects page (/projects): the category links filter at once, without
   a new page, and keep the address in step (?type=…) so a filtered view can
   be shared; the search narrows by name, place, type and year as it is
   typed. Without this script the category links still work, on the server.
   ========================================================= */
(function () {
    'use strict';

    const tools = document.getElementById('pjTools');
    const grid = document.getElementById('pjGrid');
    if (!tools || !grid) return;

    const chips = Array.from(tools.querySelectorAll('.pj-chip'));
    const cards = Array.from(grid.querySelectorAll('.pj-card'));
    const search = document.getElementById('pjSearch');
    const count = document.getElementById('pjCount');
    const empty = document.getElementById('pjEmpty');
    const clear = document.getElementById('pjClear');
    let type = (chips.find(c => c.classList.contains('is-active')) || {}).dataset?.type || '';

    // Kurdish phones type ٠–٩; the cards are searched in 0–9
    const plain = s => s.toLowerCase().replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).trim();

    function apply() {
        const words = plain(search ? search.value : '').split(/\s+/).filter(Boolean);
        let shown = 0;
        cards.forEach(card => {
            const inType = !type || card.dataset.categories.split(' ').includes(type);
            const found = words.every(w => card.dataset.search.includes(w));
            card.hidden = !(inType && found);
            if (!card.hidden) shown++;
        });
        count.textContent = shown === 1 ? tools.dataset.countOne : tools.dataset.count.replace('{n}', shown);
        empty.hidden = shown > 0;
    }

    function choose(next) {
        type = next;
        chips.forEach(c => {
            const on = c.dataset.type === type;
            c.classList.toggle('is-active', on);
            if (on) c.setAttribute('aria-current', 'true'); else c.removeAttribute('aria-current');
        });
        const url = new URL(location.href);
        if (type) url.searchParams.set('type', type); else url.searchParams.delete('type');
        history.replaceState(null, '', url);
        apply();
    }

    chips.forEach(c => c.addEventListener('click', e => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;   // a new tab, as asked
        e.preventDefault();
        choose(c.dataset.type);
    }));
    if (clear) {
        clear.addEventListener('click', e => {
            e.preventDefault();
            if (search) search.value = '';
            choose('');
        });
    }
    if (search) search.addEventListener('input', apply);
})();
