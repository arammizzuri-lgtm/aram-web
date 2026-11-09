/* =========================================================
   ARAM MIZURI ARCHITECTURE — partners.js
   The partners page: the trade filters; the switch between the logo wall
   and the showrooms map (the map, and Leaflet, load only when asked for);
   each partner opening in a dialog — also from a #partner-N link, which is
   how the homepage strip and the partners' own badges arrive; the anonymous
   counting of what visitors do with a partner; and the "Become a partner"
   application.
   ========================================================= */
(function () {
    'use strict';

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ── Counting: one small anonymous ping, never in the way ────────── */
    function track(partner, type) {
        if (!partner || !type) return;
        try {
            const body = new FormData();
            body.append('partner', partner);
            body.append('type', type);
            if (!(navigator.sendBeacon && navigator.sendBeacon('/tp', body))) {
                fetch('/tp', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
            }
        } catch (e) { /* counting must never get in the way */ }
    }

    /* ── Filters: one trade at a time ───────────────────────────────── */
    const chips = document.querySelectorAll('.pt-chip');
    const tiles = document.querySelectorAll('.pt-tile');
    chips.forEach(chip => chip.addEventListener('click', () => {
        chips.forEach(c => c.classList.toggle('is-on', c === chip));
        const trade = chip.dataset.trade;
        tiles.forEach(t => { t.hidden = !!trade && t.dataset.trade !== trade; });
    }));

    /* ── Dialogs ────────────────────────────────────────────────────── */
    let opener = null;
    function open(id, from) {
        const dlg = document.getElementById(id);
        if (!dlg || typeof dlg.showModal !== 'function' || dlg.open) return;
        opener = from || null;
        dlg.showModal();
        track(dlg.dataset.partner, 'open');
        if (location.hash !== '#' + id) history.replaceState(null, '', '#' + id);
    }
    document.querySelectorAll('[data-open]').forEach(btn =>
        btn.addEventListener('click', () => open(btn.dataset.open, btn)));

    document.querySelectorAll('.pt-dlg').forEach(dlg => {
        dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());
        // a click on the dimmed backdrop (the dialog itself, outside the panel) closes it
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
        dlg.addEventListener('close', () => {
            if (location.hash === '#' + dlg.id) history.replaceState(null, '', location.pathname + location.search);
            if (opener) opener.focus();
            opener = null;
        });
        // calls, WhatsApp, directions and website visits
        dlg.querySelectorAll('[data-track]').forEach(a =>
            a.addEventListener('click', () => track(dlg.dataset.partner, a.dataset.track)));
    });

    const fromHash = () => {
        const id = decodeURIComponent(location.hash.slice(1));
        if (/^partner-\d+$/.test(id)) open(id, document.querySelector('[data-open="' + id + '"]'));
    };
    fromHash();
    window.addEventListener('hashchange', fromHash);

    /* ── Logos / Showrooms map ──────────────────────────────────────── */
    const switches = document.querySelectorAll('.pt-switch__btn');
    const mapEl = document.getElementById('ptMap');
    let map = null;

    function loadLeaflet(done) {
        if (window.L) return done();
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = mapEl.dataset.leafletCss;
        document.head.appendChild(css);
        const js = document.createElement('script');
        js.src = mapEl.dataset.leafletJs;
        js.onload = done;
        document.head.appendChild(js);
    }

    function buildMap() {
        const pins = JSON.parse(mapEl.dataset.pins || '[]');
        map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });
        L.tileLayer(mapEl.dataset.tiles, {
            subdomains: 'abcd', maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        }).addTo(map);

        const points = pins.map(p => {
            const icon = L.divIcon({ className: '', iconSize: [18, 18], iconAnchor: [9, 9], html: '<span class="pt-pin"></span>' });
            const card =
                '<div class="pt-mcard">' +
                    (p.logo ? '<span class="pt-mcard__logo"><img src="' + esc(p.logo) + '" alt=""></span>' : '') +
                    '<p class="pt-mcard__trade">' + esc(p.trade) + '</p>' +
                    '<p class="pt-mcard__name">' + esc(p.name) + '</p>' +
                    (p.address ? '<p class="pt-mcard__addr" dir="auto">' + esc(p.address) + '</p>' : '') +
                    '<div class="pt-mcard__actions">' +
                        '<a class="pt-mcard__btn" href="' + esc(p.dir) + '" target="_blank" rel="noopener" data-dir="' + esc(p.id) + '">' + esc(mapEl.dataset.directions) + ' ↗</a>' +
                        '<button type="button" class="pt-mcard__btn pt-mcard__btn--gold" data-view-partner="' + esc(p.id) + '">' + esc(mapEl.dataset.view) + '</button>' +
                    '</div>' +
                '</div>';
            L.marker([p.lat, p.lng], { icon, title: p.name, alt: p.name }).addTo(map)
                .bindPopup(card, { className: 'pt-popup', maxWidth: 280, minWidth: 220, closeButton: true });
            return [p.lat, p.lng];
        });
        if (points.length === 1) map.setView(points[0], 14);
        else map.fitBounds(L.latLngBounds(points), { padding: [50, 50], maxZoom: 14 });

        // the buttons inside a map card
        mapEl.addEventListener('click', e => {
            const view = e.target.closest('[data-view-partner]');
            if (view) open(view.dataset.viewPartner, view);
            const dir = e.target.closest('[data-dir]');
            if (dir) {
                const dlg = document.getElementById(dir.dataset.dir);
                if (dlg) track(dlg.dataset.partner, 'map');
            }
        });
    }

    switches.forEach(btn => btn.addEventListener('click', () => {
        const view = btn.dataset.view;
        switches.forEach(b => { const on = b === btn; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
        document.querySelectorAll('[data-view-pane]').forEach(p => { p.hidden = p.dataset.viewPane !== view; });
        if (view === 'map' && mapEl) {
            if (map) { map.invalidateSize(); return; }
            loadLeaflet(buildMap);
        }
    }));

    /* ── Become a partner ───────────────────────────────────────────── */
    const form = document.getElementById('ptApply');
    if (!form) return;
    const status = document.getElementById('paStatus');
    const logo = document.getElementById('paLogo');
    const logoName = document.getElementById('paLogoName');
    const say = (text, ok) => { status.hidden = false; status.textContent = text; status.classList.toggle('is-ok', !!ok); status.classList.toggle('is-err', ok === false); };

    logo.addEventListener('change', () => {
        const f = logo.files[0];
        logoName.textContent = f ? f.name : logoName.dataset.empty;
        if (f && f.size > +form.dataset.maxBytes) { say(form.dataset.msgBig, false); logo.value = ''; logoName.textContent = logoName.dataset.empty; }
    });

    form.addEventListener('submit', e => {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); say(form.dataset.msgCheck, false); return; }
        const btn = form.querySelector('button[type=submit]');
        btn.disabled = true;
        say(form.dataset.msgSending);
        fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(() => {
                form.reset();
                logoName.textContent = logoName.dataset.empty;
                form.querySelectorAll('.pt-field, .pt-form__foot button').forEach(el => { el.hidden = true; });
                say(form.dataset.msgOk, true);
            })
            .catch(r => {
                btn.disabled = false;
                say(r && r.status === 422 ? form.dataset.msgCheck : form.dataset.msgFailed, false);
            });
    });
})();
