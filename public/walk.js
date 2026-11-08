/* =========================================================
   ARAM MIZURI ARCHITECTURE — walk.js
   The Erbil walk's map: the stops as numbered gold pins, joined in their
   order by a dashed route; a pin scrolls the page to its stop.
   ========================================================= */
(function () {
    'use strict';
    const el = document.getElementById('wkMap');
    if (!el || typeof L === 'undefined') return;
    const pins = JSON.parse(el.dataset.pins || '[]');
    if (!pins.length) return;

    const map = L.map(el, { zoomControl: true, scrollWheelZoom: false, attributionControl: true });
    L.tileLayer(el.dataset.tiles, { subdomains: 'abcd', maxZoom: 19,
        attribution: '&copy; OpenStreetMap &copy; CARTO' }).addTo(map);

    const points = pins.map(p => [p.lat, p.lng]);
    L.polyline(points, { color: '#F5C518', weight: 2, opacity: .8, dashArray: '6 8' }).addTo(map);

    pins.forEach(p => {
        const icon = L.divIcon({ className: '', iconSize: [30, 30], iconAnchor: [15, 15],
            html: '<span class="wk-pin">' + p.n + '</span>' });
        L.marker([p.lat, p.lng], { icon, title: p.name, alt: p.name }).addTo(map)
            .on('click', () => {
                const stop = document.getElementById('stop-' + p.n);
                if (stop) stop.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
    });

    map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 16 });
})();
