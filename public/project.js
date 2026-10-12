/* =========================================================
   ARAM MIZURI ARCHITECTURE — project.js
   The project page (/projects/{slug}): its location map, the full-screen
   photo viewer, and the share button. Everything else on the page is plain
   server-rendered HTML that works without this file.
   ========================================================= */
(function () {
    'use strict';

    /* ---- Top bar ------------------------------------------
       Clear over the photo, frosted glass once it would sit over text. */
    (function initTopBar() {
        const bar = document.querySelector('.pp-top');
        if (!bar) return;
        const hero = document.querySelector('.pp-hero');
        let ticking = false;
        function update() {
            ticking = false;
            const edge = hero ? hero.offsetHeight - bar.offsetHeight : 80;
            bar.classList.toggle('is-solid', window.scrollY > edge);
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        update();
    })();

    /* ---- Location map ------------------------------------
       The same dark CARTO tiles and pulsing gold pin as the homepage maps.
       It is context, not a tool: no scroll-zoom to hijack the page, and the
       "Open in Google Maps" chip is the way to go further. */
    (function initMap() {
        const el = document.getElementById('ppMap');
        if (!el || typeof L === 'undefined') return;
        const at = [parseFloat(el.dataset.lat), parseFloat(el.dataset.lng)];
        if (!isFinite(at[0]) || !isFinite(at[1])) return;

        const map = L.map(el, {
            zoomControl: false, attributionControl: true,
            scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false,
        }).setView(at, 14);
        map.attributionControl.setPrefix(false);
        if (el.dataset.tiles) {
            L.tileLayer(el.dataset.tiles, {
                subdomains: 'abcd', maxZoom: 18,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
            }).addTo(map);
        }
        L.marker(at, {
            interactive: false,
            icon: L.divIcon({ className: '', html: '<span class="pp-pin"></span>', iconSize: [18, 18], iconAnchor: [9, 9] }),
        }).addTo(map);

        // Leaflet measures its box once; measure again when it scrolls into view.
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries, io) {
                if (!entries[0].isIntersecting) return;
                map.invalidateSize();
                io.disconnect();
            }).observe(el);
        }
    })();

    /* ---- Photo viewer ------------------------------------
       Every photo at display size (the large derivative), one at a time.
       Arrow keys and swipes step through them; Escape closes — the dialog
       element gives us that, and the focus handling, for free. */
    (function initLightbox() {
        const box = document.getElementById('ppLightbox');
        if (!box || typeof box.showModal !== 'function') return;
        let images = [];
        try { images = JSON.parse(box.dataset.images || '[]'); } catch (e) { images = []; }
        if (!images.length) return;

        const img   = box.querySelector('.pp-lightbox__img');
        const count = box.querySelector('.pp-lightbox__count');
        let cur = 0;

        function show(i) {
            cur = (i + images.length) % images.length;
            img.classList.add('is-loading');
            img.onload = function () { img.classList.remove('is-loading'); };
            img.src = images[cur];
            count.textContent = (cur + 1) + ' / ' + images.length;
            // warm the neighbour the visitor is most likely to step to
            const next = new Image();
            next.src = images[(cur + 1) % images.length];
        }

        document.querySelectorAll('[data-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                show(parseInt(btn.dataset.open, 10) || 0);
                box.showModal();
            });
        });

        const rtl = document.documentElement.getAttribute('dir') === 'rtl';
        box.querySelector('.pp-lightbox__close').addEventListener('click', function () { box.close(); });
        box.querySelector('.pp-lightbox__prev').addEventListener('click', function () { show(cur - 1); });
        box.querySelector('.pp-lightbox__next').addEventListener('click', function () { show(cur + 1); });
        // a click on the dark surround, not the photo or a button, closes
        box.addEventListener('click', function (e) { if (e.target === box) box.close(); });
        box.addEventListener('keydown', function (e) {
            // "forward" is to the left when reading right-to-left
            if (e.key === 'ArrowRight') show(cur + (rtl ? -1 : 1));
            if (e.key === 'ArrowLeft')  show(cur + (rtl ? 1 : -1));
        });

        let startX = null;
        box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        box.addEventListener('touchend', function (e) {
            if (startX === null) return;
            const dx = e.changedTouches[0].clientX - startX;
            startX = null;
            if (Math.abs(dx) > 45) show(cur + ((dx < 0) !== rtl ? 1 : -1));
        });
    })();

    /* ---- Share ------------------------------------------
       The phone's own share sheet where there is one (WhatsApp, Instagram,
       Messages…); elsewhere, the link goes to the clipboard. */
    (function initShare() {
        const btn = document.getElementById('ppShare');
        if (!btn) return;
        const label = btn.querySelector('.pp-share__label');
        const idle = label.textContent;

        function confirm() {
            label.textContent = btn.dataset.copied;
            btn.classList.add('is-done');
            setTimeout(function () { label.textContent = idle; btn.classList.remove('is-done'); }, 2200);
        }

        btn.addEventListener('click', function () {
            const data = { title: btn.dataset.title, url: btn.dataset.url };
            if (navigator.share) {
                navigator.share(data).catch(function () { /* dismissed — nothing to do */ });
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(data.url).then(confirm, function () { window.prompt('', data.url); });
            } else {
                window.prompt('', data.url);
            }
        });
    })();
})();
