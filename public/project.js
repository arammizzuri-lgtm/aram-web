/* =========================================================
   ARAM MIZURI ARCHITECTURE — project.js
   The project page (/projects/{slug}): its location map and the full-screen
   photo viewer. (Its share buttons are engage.js's, its heart saved.js's.)
   Everything else on the page is plain server-rendered HTML that works
   without this file.
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
            icon: L.divIcon({ className: '', html: '<span class="pp-pin"></span>', iconSize: [22, 22], iconAnchor: [11, 11] }),
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

        const all = images;
        document.querySelectorAll('[data-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // a button inside a set of its own (a progress update's photos)
                // steps through that set; any other, through the project's
                const holder = btn.closest('[data-images]:not(dialog)');
                try { images = holder ? JSON.parse(holder.dataset.images) : all; } catch (e) { images = all; }
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

    /* ---- Render vs. built -----------------------------------
       Press anywhere on the picture and drag: the dividing line follows the
       pointer. The hidden range input carries the value for the keyboard
       (and for screen readers), and both stay in step. */
    document.querySelectorAll('.pp-compare__frame').forEach(function (frame) {
        const range = frame.querySelector('.pp-compare__range');
        if (!range) return;
        const set = function () { frame.style.setProperty('--pos', range.value + '%'); };
        const follow = function (e) {
            const box = frame.getBoundingClientRect();
            range.value = Math.max(0, Math.min(100, (e.clientX - box.left) / box.width * 100));
            set();
        };
        let dragging = false;
        frame.addEventListener('pointerdown', function (e) {
            dragging = true;
            frame.setPointerCapture(e.pointerId);
            follow(e);
        });
        frame.addEventListener('pointermove', function (e) { if (dragging) follow(e); });
        ['pointerup', 'pointercancel'].forEach(function (ev) {
            frame.addEventListener(ev, function () { dragging = false; });
        });
        range.addEventListener('input', set);
        set();
    });

    /* ---- Film & 360° tours -------------------------------
       A poster until pressed. Only then is the player itself loaded — so a
       visitor who never presses play downloads nothing from YouTube or
       Matterport, and the page stays light. */
    document.querySelectorAll('.pp-embed__frame[data-embed]').forEach(function (frame) {
        const btn = frame.querySelector('.pp-embed__play');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const iframe = document.createElement('iframe');
            iframe.src = frame.dataset.embed;
            iframe.title = frame.dataset.title || '';
            iframe.allow = 'autoplay; fullscreen; picture-in-picture; xr-spatial-tracking; gyroscope; accelerometer';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            frame.innerHTML = '';
            frame.appendChild(iframe);
            frame.classList.add('is-playing');
            iframe.focus();
        });
    });
})();
