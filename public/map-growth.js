/* =========================================================
   ARAM MIZURI ARCHITECTURE — map-growth.js
   "Watch the studio grow": the homepage map (script.js, initProjectMap)
   emptied, then its projects played back onto it year by year — the map
   moving to where each year's work stands, every pin landing in turn, a
   year counter and a dimension line of the years keeping time. Anything the
   visitor does to the map stops it and puts every pin back.
   ========================================================= */
(function () {
    'use strict';

    const api = window.siteProjectMap;
    const ui = document.getElementById('mapGrow');
    if (!api || !ui || !api.pins.length) return;

    const map = api.map;
    const play = document.getElementById('mapGrowPlay');
    const label = play.querySelector('.map-grow__label');
    const clock = document.getElementById('mapGrowClock');
    const yearEl = document.getElementById('mapGrowYear');
    const countEl = document.getElementById('mapGrowCount');
    const beyondEl = document.getElementById('mapGrowBeyond');
    const ticks = Array.from(document.querySelectorAll('#mapGrowLine [data-year]'));
    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';
    const say = name => ui.dataset[name + (isKu() ? 'Ku' : 'En')] || ui.dataset[name + 'En'] || '';
    const sleep = ms => new Promise(r => setTimeout(r, still ? Math.min(ms, 120) : ms));

    // A project's year is the last one it names ("2021 – 2024" ended in 2024);
    // one without a year comes in last.
    const endYear = p => { const m = String(p.year || '').match(/\d{4}/g); return m ? +m[m.length - 1] : null; };
    const pins = api.pins.map(pin => ({ ...pin, year: endYear(pin.proj), el: () => document.getElementById('mapPin' + pin.idx) }))
        .sort((a, b) => (a.year || 9999) - (b.year || 9999) || (parseInt(a.proj.num, 10) || 0) - (parseInt(b.proj.num, 10) || 0));
    const lastYear = Math.max(...pins.map(p => p.year || 0));

    // Far from the rest (Madrid, from Erbil): counted and named as it lands,
    // but not flown to — the map stays on the region the studio works in.
    const lat = pins.map(p => p.marker.getLatLng().lat).sort((a, b) => a - b);
    const lng = pins.map(p => p.marker.getLatLng().lng).sort((a, b) => a - b);
    const middle = L.latLng(lat[lat.length >> 1], lng[lng.length >> 1]);
    pins.forEach(p => { p.far = map.distance(middle, p.marker.getLatLng()) > 900000; });
    const near = pins.filter(p => !p.far);

    let run = 0;            // which playing this is; a newer one (or a stop) ends the old
    let shown = 0;
    let state = 'idle';     // idle | playing | done

    function setLabel() {
        label.textContent = say(state === 'playing' ? 'stop' : state === 'done' ? 'again' : 'play');
        play.setAttribute('aria-pressed', state === 'playing' ? 'true' : 'false');
        ui.classList.toggle('is-playing', state === 'playing');
    }
    function setCount(n) {
        shown = n;
        countEl.textContent = n === 1 ? say('one') : say('count').replace('{n}', n);
    }
    function setYear(y) {
        yearEl.textContent = y;
        ticks.forEach(t => {
            t.classList.toggle('is-past', +t.dataset.year < y);
            t.classList.toggle('is-now', +t.dataset.year === y);
        });
    }

    // the map moving to a set of pins, and settled
    function frame(list) {
        return new Promise(resolve => {
            if (!list.length) return resolve();
            const bounds = L.latLngBounds(list.map(p => p.marker.getLatLng()));
            // clear of the title (top corner) and the year counter (foot), on whichever side they sit
            const size = map.getSize();
            const side = Math.min(380, size.x * 0.3);
            const opts = {
                paddingTopLeft: L.point(isKu() ? 90 : side, 170),
                paddingBottomRight: L.point(isKu() ? side : 90, 150),
                maxZoom: 12,
            };
            const done = () => { clearTimeout(guard); resolve(); };
            const guard = setTimeout(done, 2600);
            map.once('moveend', done);
            if (still) map.fitBounds(bounds, Object.assign(opts, { animate: false }));
            else map.flyToBounds(bounds, Object.assign(opts, { duration: 1.1 }));
        });
    }

    function land(pin) {
        const el = pin.el();
        if (!el) return;
        el.classList.remove('is-unbuilt');
        el.classList.add('is-landing');
        setTimeout(() => el.classList.remove('is-landing'), 900);
    }

    function restore() {
        pins.forEach(p => { const el = p.el(); if (el) el.classList.remove('is-unbuilt', 'is-landing'); });
        ui.classList.remove('is-playing');
    }

    async function start() {
        const mine = ++run;
        const live = () => mine === run;
        state = 'playing';
        setLabel();
        api.hideCard();
        beyondEl.hidden = true;
        pins.forEach(p => { const el = p.el(); if (el) el.classList.add('is-unbuilt'); });
        clock.hidden = false;
        setCount(0);
        setYear(pins[0].year || lastYear);

        // year by year: the map to that year's work, then each project landing
        const years = [];
        pins.forEach(p => {
            const y = p.year || lastYear;
            if (!years.length || years[years.length - 1].year !== y) years.push({ year: y, pins: [] });
            years[years.length - 1].pins.push(p);
        });
        const each = Math.max(160, Math.min(420, 9000 / pins.length));

        for (const group of years) {
            if (!live()) return;
            setYear(group.year);
            await frame(group.pins.filter(p => !p.far));
            for (const pin of group.pins) {
                if (!live()) return;
                land(pin);
                setCount(shown + 1);
                if (pin.far) {
                    beyondEl.textContent = say('beyond').replace('{place}', pin.proj.location || pin.proj.city || pin.proj.name || '');
                    beyondEl.hidden = false;
                }
                await sleep(each);
            }
            await sleep(650);
        }
        if (!live()) return;
        await frame(near);
        if (!live()) return;
        state = 'done';
        setLabel();
    }

    function stop() {
        if (state !== 'playing') return;
        run++;
        state = 'idle';
        restore();
        clock.hidden = true;
        setLabel();
    }

    play.addEventListener('click', e => {
        e.stopPropagation();
        if (state === 'playing') stop(); else start();
    });
    // the visitor taking the map — or leaving it — stops the playing
    ['pointerdown', 'wheel', 'touchstart'].forEach(evt => api.el.addEventListener(evt, stop, { passive: true }));
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(entries => { if (!entries[0].isIntersecting) stop(); }).observe(ui.closest('section') || api.el);
    }
    document.addEventListener('langchange', () => {
        setLabel();
        if (!clock.hidden) {
            setCount(shown);
            if (!beyondEl.hidden) {
                const far = pins.find(p => p.far);
                if (far) beyondEl.textContent = say('beyond').replace('{place}', far.proj.location || far.proj.name || '');
            }
        }
    });

    setLabel();
    ui.hidden = false;
})();
