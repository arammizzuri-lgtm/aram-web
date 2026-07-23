/* =========================================================
   ARAM MIZURI ARCHITECTURE — script.js
   ========================================================= */

'use strict';

/* ---- Kurdish Sun SVG Generator ------------------------- */
function buildKurdishSun(container, opts = {}) {
    const {
        size    = 200,
        color   = '#F5C518',
        rays    = 21,       // authentic Kurdistan flag: 21 rays
        spin    = false,
        spinSpeed = 0.015,
    } = opts;

    const NS  = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 200 200');
    svg.style.width  = size + 'px';
    svg.style.height = size + 'px';
    svg.style.display = 'block';

    const cx = 100, cy = 100;
    const rInner  = 28;
    const rTip    = 76;
    const rBase   = 38;

    // soft glow ring
    const glow = document.createElementNS(NS, 'circle');
    glow.setAttribute('cx', cx); glow.setAttribute('cy', cy);
    glow.setAttribute('r', rTip + 8);
    glow.setAttribute('fill', 'none');
    glow.setAttribute('stroke', color);
    glow.setAttribute('stroke-width', '0.8');
    glow.setAttribute('opacity', '0.25');
    svg.appendChild(glow);

    // rays (triangular — like the real flag)
    for (let i = 0; i < rays; i++) {
        const step   = (2 * Math.PI) / rays;
        const aLeft  = step * i       - Math.PI / 2;
        const aMid   = step * (i + .5) - Math.PI / 2;
        const aRight = step * (i + 1)  - Math.PI / 2;

        const x1 = cx + rBase * Math.cos(aLeft);
        const y1 = cy + rBase * Math.sin(aLeft);
        const x2 = cx + rTip  * Math.cos(aMid);
        const y2 = cy + rTip  * Math.sin(aMid);
        const x3 = cx + rBase * Math.cos(aRight);
        const y3 = cy + rBase * Math.sin(aRight);

        const poly = document.createElementNS(NS, 'polygon');
        poly.setAttribute('points', `${x1},${y1} ${x2},${y2} ${x3},${y3}`);
        poly.setAttribute('fill', color);
        svg.appendChild(poly);
    }

    // centre disc
    const disc = document.createElementNS(NS, 'circle');
    disc.setAttribute('cx', cx); disc.setAttribute('cy', cy);
    disc.setAttribute('r', rInner);
    disc.setAttribute('fill', color);
    svg.appendChild(disc);

    // inner ring (dark hole, like the real design)
    const hole = document.createElementNS(NS, 'circle');
    hole.setAttribute('cx', cx); hole.setAttribute('cy', cy);
    hole.setAttribute('r', rInner * 0.55);
    hole.setAttribute('fill', 'rgba(0,0,0,0.22)');
    svg.appendChild(hole);

    container.appendChild(svg);

    // Decorative spin — but never for visitors who ask for reduced motion
    // (this is a JS-driven inline transform, so CSS media queries can't stop it).
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (spin && !prefersReducedMotion) {
        let angle = 0;
        (function rotateSun() {
            angle += spinSpeed;
            svg.style.transform = `rotate(${angle}deg)`;
            requestAnimationFrame(rotateSun);
        })();
    }

    return svg;
}

/* ---- Loader -------------------------------------------- */
(function initLoader() {
    const loader    = document.getElementById('loader');
    const loaderSun = document.getElementById('loaderSun');
    if (!loader || !loaderSun) return;

    buildKurdishSun(loaderSun, { size: 150, color: '#F5C518', spin: true, spinSpeed: 0.03 });

    window.addEventListener('load', () => {
        setTimeout(() => {
            loader.classList.add('out');
            document.body.classList.add('is-loaded');   // triggers the hero reveal
        }, 1700);
    });
})();

/* ---- Hero (kinetic) ------------------------------------ */
(function initHero() {
    // Safety net: reveal the hero even if the loader never fires.
    setTimeout(function () { document.body.classList.add('is-loaded'); }, 2500);

    // Live Erbil clock
    var clock = document.getElementById('heroClock');
    if (clock) {
        var tick = function () {
            try {
                clock.textContent = new Date().toLocaleTimeString('en-GB',
                    { timeZone: 'Asia/Baghdad', hour: '2-digit', minute: '2-digit', second: '2-digit' });
            } catch (e) {
                clock.textContent = new Date().toLocaleTimeString();
            }
        };
        tick();
        setInterval(tick, 1000);
    }

    // Magnetic primary CTA (pointer devices only)
    var go = document.getElementById('heroGo');
    if (go && window.matchMedia && window.matchMedia('(pointer:fine)').matches) {
        go.addEventListener('pointermove', function (e) {
            var r = go.getBoundingClientRect();
            var mx = e.clientX - (r.left + r.width / 2);
            var my = e.clientY - (r.top + r.height / 2);
            go.style.transform = 'translate(' + (mx * 0.24) + 'px,' + (my * 0.4) + 'px)';
        });
        go.addEventListener('pointerleave', function () { go.style.transform = ''; });
    }
})();

/* ---- Kurdistan Sun placements -------------------------- */
(function placeSuns() {
    // Hero — the Kurdish sun as a slowly, continuously spinning brand mark.
    // (The figures are now shown in the hero's glass stat chips, so the old
    //  ray-presents-a-stat choreography is retired.)
    var heroWrap = document.getElementById('heroSun');
    if (heroWrap) {
        var heroSvg = buildKurdishSun(heroWrap, { size: 520, color: '#F5C518', spin: true, spinSpeed: 0.05 });
        heroSvg.style.width  = '100%';
        heroSvg.style.height = '100%';
    }
    // Kurdish flag band (tiny)
    const bandWrap = document.getElementById('bandSun');
    if (bandWrap) {
        buildKurdishSun(bandWrap, { size: 28, color: '#F5C518' });
    }

    // Portrait (decorative)
    const portWrap = document.getElementById('portraitSun');
    if (portWrap) {
        const s = buildKurdishSun(portWrap, { size: 260, color: '#F5C518', spin: true, spinSpeed: 0.004 });
        s.style.opacity = '0.12';
    }

    // Footer
    const footWrap = document.getElementById('footerSun');
    if (footWrap) {
        buildKurdishSun(footWrap, { size: 52, color: '#F5C518' });
    }
})();

/* ---- Custom Cursor ------------------------------------- */
(function initCursor() {
    const cursor   = document.getElementById('cursor');
    const follower = document.getElementById('cursorFollower');
    if (!cursor || !follower) return;
    if (window.matchMedia('(hover: none), (pointer: coarse)').matches) return;

    let mx = 0, my = 0, fx = 0, fy = 0;

    // Record coords only — all DOM writes happen once per frame below.
    // (Writing style on every mousemove fires layout hundreds of times a
    // second on high-Hz mice and made the cursor lag the whole page.)
    document.addEventListener('mousemove', e => {
        mx = e.clientX; my = e.clientY;
    }, { passive: true });

    (function tick() {
        fx += (mx - fx) * 0.18;
        fy += (my - fy) * 0.18;
        // translate3d = compositor-only: no reflow, no fighting the WebGL loop
        cursor.style.transform   = 'translate3d(' + mx + 'px,' + my + 'px,0) translate(-50%,-50%)';
        follower.style.transform = 'translate3d(' + fx + 'px,' + fy + 'px,0) translate(-50%,-50%)';
        requestAnimationFrame(tick);
    })();

    const hoverTargets = 'a, button, .pgc, input, textarea, select, .pgf-btn, .lang-toggle, .client-logo, .clients-modal__tile';
    document.querySelectorAll(hoverTargets).forEach(el => {
        el.addEventListener('mouseenter', () => {
            cursor.classList.add('cursor--expand');
            follower.classList.add('cursor-follower--expand');
        });
        el.addEventListener('mouseleave', () => {
            cursor.classList.remove('cursor--expand');
            follower.classList.remove('cursor-follower--expand');
        });
    });
})();

/* ---- Navigation ---------------------------------------- */
(function initNav() {
    const nav       = document.getElementById('nav');
    const hamburger = document.getElementById('hamburger');
    const menu      = document.getElementById('mobileMenu');
    if (!nav || !hamburger || !menu) return;

    // Scroll shadow
    window.addEventListener('scroll', () => {
        nav.style.boxShadow = window.scrollY > 10
            ? '0 2px 20px rgba(0,0,0,.08)'
            : 'none';
    }, { passive: true });

    // Hamburger toggle
    let open = false;
    function toggleMenu(force) {
        open = typeof force === 'boolean' ? force : !open;
        menu.classList.toggle('open', open);
        hamburger.classList.toggle('open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    hamburger.addEventListener('click', () => toggleMenu());

    menu.querySelectorAll('.mobile-menu__link').forEach(link => {
        link.addEventListener('click', () => toggleMenu(false));
    });
})();

/* ---- Hero parallax on mouse move ----------------------- */
(function initHeroParallax() {
    const wrap = document.getElementById('heroSun');
    if (!wrap) return;
    document.addEventListener('mousemove', e => {
        const hero = document.querySelector('.hero');
        if (!hero || hero.getBoundingClientRect().bottom < 0) return;
        const dx = (e.clientX / window.innerWidth  - 0.5) * 28;
        const dy = (e.clientY / window.innerHeight - 0.5) * 18;
        wrap.style.transform = `translateY(-55%) translate(${dx}px,${dy}px)`;
    }, { passive: true });
})();

/* ---- Project Filtering --------------------------------- */
(function initFilter() {
    const btns  = document.querySelectorAll('.filter-btn');
    const cards = document.querySelectorAll('.pcard');
    if (!btns.length || !cards.length) return;

    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const filter = btn.dataset.filter;

            cards.forEach((card, i) => {
                const match = filter === 'all' || card.dataset.category === filter;

                if (match) {
                    card.classList.remove('filtered-out');
                    card.style.display = '';
                    // stagger in
                    setTimeout(() => card.classList.add('filtered-in'), i * 40);
                } else {
                    card.classList.remove('filtered-in');
                    card.classList.add('filtered-out');
                    setTimeout(() => {
                        if (!card.classList.contains('filtered-in')) {
                            card.style.display = 'none';
                        }
                    }, 380);
                }
            });
        });
    });
})();

/* ---- Scroll Reveal ------------------------------------- */
(function initReveal() {
    // Mark elements for reveal
    const selectors = [
        '.about__text', '.about__side',
        '.services__head', '.service',
        '.process__head',
        '.contact__info', '.contact__form',
    ];
    selectors.forEach(sel => {
        document.querySelectorAll(sel).forEach((el, i) => {
            el.classList.add('reveal');
            if (i > 0) el.classList.add(`reveal-delay-${Math.min(i, 3)}`);
        });
    });

    const io = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('visible');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => io.observe(el));
})();

/* ---- Process timeline (scroll-told) -------------------- */
(function initProcess() {
    const section = document.getElementById('process');
    if (!section) return;
    const list  = document.getElementById('procList');
    const items = Array.from(section.querySelectorAll('.process__item'));
    if (!list || !items.length) return;

    const nowEl    = document.getElementById('procNow');
    const barEl    = document.getElementById('procBar');
    const railFill = document.getElementById('procRailFill');
    const rail     = section.querySelector('.process__rail');
    const total    = items.length;
    let activeIdx  = -1;

    const pad2 = n => (n < 10 ? '0' + n : '' + n);

    // Grow the gold rail fill down to the active node's centre.
    function railTo(idx) {
        if (!railFill || !rail || !items[idx]) return;
        const orb = items[idx].querySelector('.process__orb');
        if (!orb) return;
        const o = orb.getBoundingClientRect();
        const r = rail.getBoundingClientRect();
        railFill.style.height = Math.max(0, (o.top + o.height / 2) - r.top) + 'px';
    }

    function setActive(idx) {
        if (idx === activeIdx) return;
        activeIdx = idx;
        items.forEach((it, i) => {
            it.classList.toggle('is-active', i === idx);
            it.classList.toggle('is-done',   i <  idx);
        });
        if (nowEl) nowEl.textContent = pad2(idx + 1);
        if (barEl) barEl.style.width = (((idx + 1) / total) * 100) + '%';
        railTo(idx);
    }

    // Reveal each card as it scrolls into view.
    const revIO = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); revIO.unobserve(e.target); } });
    }, { threshold: 0.28 });
    items.forEach(it => revIO.observe(it));

    // Active step = whichever crosses the viewport's middle band.
    const actIO = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                const idx = items.indexOf(e.target);
                if (idx >= 0) setActive(idx);
            }
        });
    }, { rootMargin: '-46% 0px -46% 0px', threshold: 0 });
    items.forEach(it => actIO.observe(it));

    // Keep the rail fill aligned when layout shifts.
    window.addEventListener('resize', () => { if (activeIdx >= 0) railTo(activeIdx); }, { passive: true });

    // Pointer-tracking spotlight on each card.
    section.querySelectorAll('.process__card').forEach(card => {
        card.addEventListener('pointermove', e => {
            const r = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
            card.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
        });
    });

    setActive(0);
})();

/* ---- Hero stats -----------------------------------------
   The figures render as 3D glass cards inside the WebGL ring — see
   hero3d.js (count-up happens on the card textures themselves). The
   DOM keeps only the visually-hidden #heroStatsData source list. */

/* ---- Language Toggle ----------------------------------- */
(function initLang() {
    const btn     = document.getElementById('langToggle'); // nav pill (desktop)
    const heroBtn = document.getElementById('heroLang');   // hero pill (mobile)
    if (!btn && !heroBtn) return;
    let isKu = false;

    function toggle() {
        isKu = !isKu;
        if (btn)     btn.textContent     = isKu ? 'English' : 'کوردی';
        if (heroBtn) heroBtn.textContent = isKu ? 'EN' : 'KU';   // compact corner badge

        // direction
        document.documentElement.setAttribute('dir', isKu ? 'rtl' : 'ltr');
        document.documentElement.setAttribute('lang', isKu ? 'ku' : 'en');

        // swap text on all elements that carry data-en / data-ku
        document.querySelectorAll('[data-en][data-ku]').forEach(el => {
            const text = isKu ? el.dataset.ku : el.dataset.en;
            // headings may contain inner HTML (em tags)
            if (el.tagName === 'H2' && text.includes('<')) {
                // only <em>, <br>, <strong> are intended here — strip any other tags
                el.innerHTML = text.replace(/<(?!\/?(?:em|br|strong)\b)[^>]*>/gi, '');
            } else {
                el.textContent = text;
            }
        });

        // swap input placeholders (data-en-ph / data-ku-ph)
        document.querySelectorAll('[data-en-ph][data-ku-ph]').forEach(el => {
            el.setAttribute('placeholder', isKu ? el.dataset.kuPh : el.dataset.enPh);
        });

        // let JS-rendered UI (e.g. the open project overlay) re-render its language
        document.dispatchEvent(new Event('langchange'));
    }

    if (btn)     btn.addEventListener('click', toggle);
    if (heroBtn) heroBtn.addEventListener('click', toggle);
})();

/* ---- Process showcase — liquid-glass design reel ---------
   Cycles a cover photo from every project (3s crossfade), pauses
   off-screen, and clicks through to the project overlay. Desktop
   only — CSS hides it ≤1100px and this init bails there too. */
(function initProcessShowcase() {
    const box = document.getElementById('processShowcase');
    if (!box) return;
    if (window.matchMedia('(max-width: 1100px)').matches) return;
    const site = window.__SITE__;
    if (!site || !site.projects) return;

    // every project that has a cover image, in site order
    const slides = site.projects
        .map((p, idx) => ({ idx, num: p.num, name: p.name, nameKu: p.name_ku, img: (p.imgs || [])[0] }))
        .filter(s => s.img);
    if (!slides.length) { box.hidden = true; return; }

    const imgs   = box.querySelectorAll('.pshow__img');
    const numEl  = document.getElementById('pshowNum');
    const nameEl = document.getElementById('pshowName');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let cur = 0, active = 0, inView = true, timer = null;

    function caption(s) {
        const isKu = document.documentElement.getAttribute('dir') === 'rtl';
        numEl.textContent  = s.num;
        nameEl.textContent = (isKu && s.nameKu) ? s.nameKu : s.name;
    }
    function show(i, instant) {
        const s = slides[i];
        const next = imgs[1 - active];
        next.onload = () => {
            imgs[active].classList.remove('on');
            next.classList.add('on');
            active = 1 - active;
            caption(s);
        };
        next.src = s.img;
        if (instant && next.complete) next.onload();
    }
    show(0, true);

    if (!reduceMotion && slides.length > 1) {
        timer = setInterval(() => {
            if (!inView || document.hidden) return;
            cur = (cur + 1) % slides.length;
            show(cur);
        }, 3000);
    }
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(entries => { inView = entries[0].isIntersecting; }, { threshold: 0.15 })
            .observe(box);
    }
    document.addEventListener('langchange', () => caption(slides[cur]));

    // the card is a door to the project itself
    function openCurrent() {
        if (typeof window.openProjectOverlay === 'function') window.openProjectOverlay(slides[cur].idx);
    }
    box.addEventListener('click', openCurrent);
    box.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCurrent(); } });
})();

/* ---- Office locator map (Contact) -----------------------
   A quiet dark-tile locator with a pulsing gold pin — decorative
   context for the studio address, not a navigation tool, so all
   interaction is off. */
(function initOfficeMap() {
    const el = document.getElementById('officeMapEl');
    if (!el || typeof L === 'undefined') return;
    const OFFICE = [36.23992, 44.04997]; // Nº 592, Italian Village 2, Erbil (36°14'23.7"N 44°02'59.9"E)
    const map = L.map(el, {
        zoomControl: false, attributionControl: false,
        dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
        boxZoom: false, keyboard: false, touchZoom: false, tap: false,
    }).setView(OFFICE, 15);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd', maxZoom: 18,
    }).addTo(map);
    L.marker(OFFICE, {
        interactive: false,
        icon: L.divIcon({
            className: 'office-pin-wrap',
            html: '<span class="office-pin"></span>',
            iconSize: [16, 16], iconAnchor: [8, 8],
        }),
    }).addTo(map);
    // Leaflet measures its container at init; re-measure once the section
    // actually scrolls into view so tiles cover the full card
    if ('IntersectionObserver' in window) {
        new IntersectionObserver((entries, io) => {
            if (!entries[0].isIntersecting) return;
            map.invalidateSize();
            io.disconnect();
        }, { threshold: 0.1 }).observe(el);
    }

    // the map is a door to directions: click/Enter opens Google Maps
    const gmaps = 'https://www.google.com/maps/search/?api=1&query=' + OFFICE[0] + ',' + OFFICE[1];
    const go = () => window.open(gmaps, '_blank', 'noopener');
    el.addEventListener('click', go);
    el.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
})();

/* ---- Stats band — count up on reveal --------------------
   The practice figures that used to ride the 3D hero ring now live in
   their own band; count each up from zero the first time it scrolls in. */
(function initStatbar() {
    const track = document.getElementById('statbarTrack');
    if (!track) return;
    const nums = track.querySelectorAll('.statbar__num');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function run() {
        nums.forEach(el => {
            const target = parseInt(el.dataset.value, 10) || 0;
            const suf = el.dataset.suffix || '';
            // the glass layers (::before extrusion, ::after specular) render
            // attr(data-n), so it must always mirror the visible text
            const set = s => { el.textContent = s; el.dataset.n = s; };
            el.classList.add('on');                       // blur-in reveal
            if (reduce) { set(target.toLocaleString('en-US') + suf); return; }
            const dur = 1700, t0 = performance.now();
            (function tick(now) {
                const p = Math.min(1, (now - t0) / dur);
                const eased = 1 - Math.pow(1 - p, 4);     // fast start, soft landing
                set(Math.round(target * eased).toLocaleString('en-US') + suf);
                if (p < 1) requestAnimationFrame(tick);
            })(t0);
        });
    }

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting) { run(); io.disconnect(); }
        }, { threshold: 0.4 });
        io.observe(track);
    } else run();

    // swipe affordance: hint dies after the first real swipe, and the
    // trailing fade lifts once the row is scrolled to its end
    const hint = document.getElementById('statbarHint');
    track.addEventListener('scroll', () => {
        if (hint && Math.abs(track.scrollLeft) > 40) hint.classList.add('gone');
        const end = Math.abs(track.scrollLeft) + track.clientWidth >= track.scrollWidth - 8;
        track.classList.toggle('at-end', end);
    }, { passive: true });

    // drag-to-scroll with the mouse (touch already scrolls natively)
    let down = false, sx = 0, sl = 0;
    track.addEventListener('pointerdown', e => {
        if (e.pointerType === 'touch') return;
        if (track.scrollWidth <= track.clientWidth + 4) return;   // nothing to drag
        down = true; sx = e.clientX; sl = track.scrollLeft;
        track.classList.add('dragging');
        track.setPointerCapture(e.pointerId);
    });
    track.addEventListener('pointermove', e => {
        if (down) track.scrollLeft = sl - (e.clientX - sx);
    });
    ['pointerup', 'pointercancel'].forEach(ev =>
        track.addEventListener(ev, () => { down = false; track.classList.remove('dragging'); }));
})();

/* ---- Clients marquee — drag with inertia ----------------
   The strip drifts on its own, but a finger or mouse can grab it,
   throw it, and the momentum decays back into the idle drift —
   the same physics language as the hero ring. */
(function initClientsDrag() {
    const track = document.querySelector('.clients__track');
    const wrap  = document.querySelector('.clients__track-wrap');
    if (!track || !wrap) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    track.style.animation = 'none';               // JS owns the motion now
    const DRIFT = reduce ? 0 : -28;               // idle speed, px/s
    let x = 0, vx = DRIFT;
    let dragging = false, lastX = 0, lastT = 0, moved = 0;
    let half = 0;
    const measure = () => { half = track.scrollWidth / 2; };
    measure();
    window.addEventListener('resize', measure);

    // no pointer capture — capturing would retarget the click away from
    // the logos, and a plain click must still open the clients modal
    wrap.addEventListener('pointerdown', e => {
        dragging = true; moved = 0;
        lastX = e.clientX; lastT = performance.now();
        wrap.classList.add('dragging');
    });
    window.addEventListener('pointermove', e => {
        if (!dragging) return;
        const now = performance.now();
        const dx = e.clientX - lastX;
        moved += Math.abs(dx);
        x += dx;
        vx = dx / Math.max(8, now - lastT) * 1000;             // px/s
        lastX = e.clientX; lastT = now;
    });
    ['pointerup', 'pointercancel'].forEach(ev =>
        window.addEventListener(ev, () => { dragging = false; wrap.classList.remove('dragging'); }));
    // a real drag must not also fire a logo's click (which opens the modal)
    wrap.addEventListener('click', e => {
        if (moved > 8) { e.stopPropagation(); e.preventDefault(); }
    }, true);

    let prev = performance.now();
    (function frame(now) {
        const dt = Math.min(64, now - prev) / 1000; prev = now;
        if (!dragging) {
            vx += (DRIFT - vx) * Math.min(1, dt * 1.5);        // decay toward drift
            x += vx * dt;
        }
        if (half > 0) x = ((x % half) + half) % half - half;   // seamless wrap
        track.style.transform = 'translateX(' + x + 'px)';
        requestAnimationFrame(frame);
    })(prev);
})();

/* ---- Clients modal — liquid-glass roster ----------------
   The marquee is ambience; the modal is the record. Any logo click
   opens the full clients & partners grid. */
(function initClientsModal() {
    const modal = document.getElementById('clientsModal');
    if (!modal) return;
    const closeBtn = document.getElementById('clientsModalClose');

    function open() {
        modal.classList.add('open');
        document.documentElement.style.overflow = 'hidden'; // lock page scroll behind the dialog
        if (closeBtn) closeBtn.focus();
    }
    function close() {
        modal.classList.remove('open');
        document.documentElement.style.overflow = '';
    }

    // Delegate to the strip, not each logo: the marquee slides under the
    // cursor, so down/up can land on neighbouring logos and the browser
    // retargets the click to their common ancestor. Any true click on the
    // strip opens the roster (drags are suppressed in initClientsDrag).
    const strip = document.querySelector('.clients__track-wrap');
    if (strip) strip.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });
})();

/* ---- Contact Form -------------------------------------- */
(function initForm() {
    const form    = document.getElementById('contactForm');
    const success = document.getElementById('formSuccess');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();
        const btn      = form.querySelector('.form-submit__label');
        const original = btn.textContent;
        const token    = document.querySelector('meta[name="csrf-token"]');
        btn.textContent = 'Sending…';

        const payload = {
            name:    (form.querySelector('[name="name"]')    || {}).value || '',
            email:   (form.querySelector('[name="email"]')   || {}).value || '',
            project: (form.querySelector('[name="project"]') || {}).value || '',
            message: (form.querySelector('[name="message"]') || {}).value || '',
        };

        fetch('/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.content : '',
            },
            body: JSON.stringify(payload),
        })
        .then(res => {
            if (!res.ok) throw new Error('Request failed (' + res.status + ')');
            return res.json().catch(() => ({}));
        })
        .then(() => {
            btn.textContent = original;
            form.reset();
            if (success) {
                success.hidden = false;
                setTimeout(() => { success.hidden = true; }, 5000);
            }
        })
        .catch(() => {
            btn.textContent = original;
            if (success) {
                success.textContent = '⚠ Something went wrong — please email us directly.';
                success.hidden = false;
            }
        });
    });
})();

/* ---- Contact methods — click to copy ------------------- */
(function initContactCopy() {
    const rows = document.querySelectorAll('.cmethod--copy');
    if (!rows.length) return;

    rows.forEach(row => {
        const value = row.getAttribute('data-copy');
        const hint  = row.querySelector('.cmethod__hint');
        if (!value) return;

        function flash() {
            row.classList.add('is-copied');
            if (hint) hint.textContent = 'Copied';
            setTimeout(() => {
                row.classList.remove('is-copied');
                if (hint) hint.textContent = 'Copy';
            }, 1600);
        }
        function copy() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(flash).catch(fallbackCopy);
            } else {
                fallbackCopy();
            }
        }
        function fallbackCopy() {
            const ta = document.createElement('textarea');
            ta.value = value;
            ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            flash();
        }

        row.addEventListener('click', copy);
        row.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); copy(); }
        });
    });
})();

/* ---- Project Data -------------------------------------- */
/* Data is injected from the database via window.__SITE__ (see the Blade view).
   The hard-coded array below is kept only as an offline safety fallback. */
const PROJECT_DATA = (window.__SITE__ && Array.isArray(window.__SITE__.projects) && window.__SITE__.projects.length)
  ? window.__SITE__.projects
  : [
  {
    num: '01', name: 'Kurdish Cultural Center',
    category: 'cultural',
    status: 'Completed', area: '8,400 m²',
    typology: 'Cultural / Civic', location: 'Erbil, Kurdistan Region', year: '2022 – 2024',
    desc: 'A landmark civic building celebrating Kurdish cultural identity. The centre houses a 600-seat performance hall, permanent and temporary exhibition galleries, a research library, and a generous public plaza. Its architecture distils the geometric vocabulary of Kurdish textiles and the tiered topography of the ancient Erbil Citadel into a rigorously contemporary language.',
    narrative: `"We began not with form, but with memory — the memory of a people whose architecture was never buildings alone, but stories carved in stone and shadow. The Kurdish Cultural Center is our attempt to give that memory a contemporary home."`,
    materials: ['Amadiyah Limestone', 'Exposed Concrete', 'Perforated Brass Screen', 'Reclaimed Oak', 'Natural Sand Render'],
    related: [6, 2],
    imgs: [
      'https://images.unsplash.com/photo-1486218119243-13883505764c?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '02', name: 'Erbil Tower Residences',
    category: 'mixed-use',
    status: 'Completed', area: '22,600 m²',
    typology: 'Residential / High-Rise', location: 'Erbil, Kurdistan Region', year: '2021 – 2023',
    desc: 'A 32-storey mixed-use residential tower that redefines the Erbil skyline. The sculpted facade draws from the Khabur geometric pattern — one of the oldest ornamental traditions in the region — scaled and reinterpreted as a perforated sun-screen that regulates solar gain while giving the building a distinctive identity visible from across the city.',
    narrative: `"The challenge of the high-rise in a hot-arid climate is not merely technical — it is poetic. How do you make a tower feel like it belongs to the landscape beneath it? The answer, for us, lay in the ancient geometry of Kurdish weaving."`,
    materials: ['High-Performance Glass', 'Anodised Aluminium', 'Perforated Steel Screen', 'Granite Cladding', 'ETFE Membrane'],
    related: [7, 4],
    imgs: [
      'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1486325212027-8081e485255e?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '03', name: 'Citadel View Hotel',
    category: 'hospitality',
    status: 'Completed', area: '14,200 m²',
    typology: 'Hospitality / 5-Star', location: 'Erbil Old City, Kurdistan Region', year: '2021 – 2023',
    desc: 'A boutique luxury hotel positioned at the foot of the Erbil Citadel UNESCO World Heritage Site. The design draws the citadel\'s layered stone terraces down into a series of cascading garden courtyards, weaving exterior landscape through the guest experience at every level. Limestone sourced from the Amadiyah highlands anchors the building to its ancient context.',
    narrative: `"To build beside a 7,000-year-old citadel is to understand your own smallness — and your responsibility. Every stone we chose, every courtyard we carved, had to earn its place in that view."`,
    materials: ['Amadiyah Limestone', 'Hand-Hammered Copper', 'Reclaimed Timber Beams', 'Zellige Tile', 'Burnished Plaster'],
    related: [8, 0],
    imgs: [
      'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '04', name: 'Kurdistan Tech Campus',
    category: 'commercial',
    status: 'Completed', area: '31,000 m²',
    typology: 'Commercial / Education', location: 'Erbil, Kurdistan Region', year: '2020 – 2022',
    desc: 'A technology and innovation campus housing offices, co-working facilities, a start-up incubator, and a 400-seat auditorium. The master plan clusters six buildings around a central landscaped plaza, creating a micro-urban environment that encourages collaboration. The architecture references the modularity of circuit boards while remaining rooted in the local tradition of shaded courtyard spaces.',
    narrative: `"Kurdistan's future is being written in code as much as in concrete. This campus is our wager that those two languages need not be in conflict — that innovation flourishes most where it is rooted in place."`,
    materials: ['Unitised Glass Curtain Wall', 'Micro-Cement', 'Recycled Steel Structure', 'Photovoltaic Cladding', 'Terrazzo'],
    related: [7, 1],
    imgs: [
      'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1541123437800-1bb1317badc2?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '05', name: 'Mountain Villa — Shaqlawa',
    category: 'residential',
    status: 'Completed', area: '1,850 m²',
    typology: 'Residential / Private Villa', location: 'Shaqlawa, Kurdistan Region', year: '2020 – 2022',
    desc: 'A private hilltop residence set within the apple orchards of Shaqlawa, 1,000 metres above the Erbil plain. The villa is organised as a series of interlocking stone and concrete volumes stepping down the slope, each oriented to capture the mountain panorama. A 26-metre infinity pool edges the main terrace; cave-inspired interiors reference the rock shelters of the Bradost valley.',
    narrative: `"The mountain taught us everything about this house. Every decision — where to carve, where to cantilever, where to yield — came from listening to the topography beneath our feet."`,
    materials: ['Local Basalt Stone', 'Board-Formed Concrete', 'Weathered Steel', 'Walnut Joinery', 'Travertine'],
    related: [1, 8],
    imgs: [
      'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1480074568708-e7b720bb3f09?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '06', name: 'Erbil Urban Regeneration',
    category: 'urban',
    status: 'Concept / Planning', area: '180,000 m²',
    typology: 'Urban Design / Masterplan', location: 'Erbil City Centre, Kurdistan Region', year: '2021',
    desc: 'A strategic urban regeneration masterplan for a 180,000 m² district in central Erbil. The proposal knits together fragmented city blocks with a new pedestrian-priority boulevard, integrates stormwater infrastructure into a linear park, and introduces a mix of residential, retail, and civic uses that activate the street at all hours. The framework allows phased delivery over 15 years.',
    narrative: `"Cities are not built in decades — they are negotiated across generations. This masterplan is our contribution to that conversation: a framework generous enough to adapt, specific enough to anchor."`,
    materials: ['Local Sandstone Paving', 'Reinforced Concrete', 'Recycled Aggregate', 'Native Planting', 'Perforated Steel Canopy'],
    related: [9, 0],
    imgs: [
      'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1494522855154-9297ac14b55f?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '07', name: 'Kurdistan Museum of Art',
    category: 'cultural',
    status: 'Under Construction', area: '11,600 m²',
    typology: 'Cultural / Museum', location: 'Sulaymaniyah, Kurdistan Region', year: '2019 – 2021',
    desc: 'The first dedicated museum of contemporary art in the Kurdistan Region. The building is organised around a dramatic top-lit atrium recalling the cave art of the Zagros mountains — a primordial gallery space rising through all four floors. Flexible exhibition halls surround the atrium and can be configured for large-scale installations, touring exhibitions, and permanent collection displays.',
    narrative: `"The oldest art in this region is on the walls of caves. We took that seriously — the idea that art deserves a space that feels both ancient and charged with light. The atrium is our Zagros cave, inverted."`,
    materials: ['Poured Concrete', 'Northern Gypsum Plaster', 'Skylight Fritted Glass', 'Basalt Stone Floor', 'Micro-Pigment Render'],
    related: [0, 2],
    imgs: [
      'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1518998053901-5348d3961a04?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1604076913837-52ab5629fbe9?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '08', name: 'Erbil Business Tower',
    category: 'commercial',
    status: 'Completed', area: '44,800 m²',
    typology: 'Commercial / Office', location: 'Erbil, Kurdistan Region', year: '2018 – 2020',
    desc: 'A 48-storey Grade-A office tower that has become one of the defining landmarks of the Erbil skyline. The tapered form responds to the prevailing north-west wind, reducing structural loads while creating a dynamic silhouette. A double-skin facade incorporates photovoltaic panels integrated into the outer layer, contributing 18% of the building\'s annual energy demand.',
    narrative: `"We modelled the tower’s silhouette by studying the wind. When you let the climate shape the form, the building stops fighting its environment and starts belonging to it."`,
    materials: ['Double-Skin Glass Facade', 'Integrated PV Panel', 'Structural Steel Core', 'Aluminium Brise-Soleil', 'Polished Concrete'],
    related: [3, 1],
    imgs: [
      'https://images.unsplash.com/photo-1486325212027-8081e485255e?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1444065381814-865dc9da92d0?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '09', name: 'Duhok Mountain Resort',
    category: 'hospitality',
    status: 'Completed', area: '9,200 m²',
    typology: 'Hospitality / Resort', location: 'Duhok, Kurdistan Region', year: '2018 – 2020',
    desc: 'A mountain resort and spa occupying a dramatic ridge above the Duhok valley. The 64-key resort is structured as a village of stone lodges cascading down the slope, separated by terraced gardens and water features that collect and recycle mountain spring water. An indoor/outdoor spa and a 200-seat restaurant command panoramic views across the Tigris River basin.',
    narrative: `"We did not build on this mountain — we joined it. The lodges are arranged not as architecture imposed on landscape, but as stones that have always been there, waiting to be inhabited."`,
    materials: ['Duhok Field Stone', 'Lime-Washed Render', 'Cedar Timber Cladding', 'Copper Guttering', 'Hand-Laid Slate Roof'],
    related: [2, 4],
    imgs: [
      'https://images.unsplash.com/photo-1519681393784-d120267933ba?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1551882547-ff40c63fe2e2?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '10', name: 'Kirkuk Public Square',
    category: 'urban',
    status: 'Completed', area: '24,000 m²',
    typology: 'Urban Design / Public Space', location: 'Kirkuk, Iraq', year: '2017 – 2019',
    desc: 'A major civic square designed to serve as a shared gathering place in a city long defined by division. The design deploys a unified palette of local sandstone across interlocking terraces, fountains, and shaded pavilions. An underground car park removes 400 vehicles from the surrounding streets, dramatically improving pedestrian connectivity to the adjacent historic bazaar.',
    narrative: `"Kirkuk has always been a city of many peoples. We were asked not to design for one community, but for all of them together. The square is deliberately un-symbolic — it belongs to whoever arrives."`,
    materials: ['Kirkuk Sandstone', 'Granite Sett Paving', 'Stainless Steel Fountain', 'Shade Steel Structure', 'Drought-Tolerant Planting'],
    related: [5, 0],
    imgs: [
      'https://images.unsplash.com/photo-1505761671935-60b3a7427bad?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1516156008625-3a9d6067fab5?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '11', name: 'Diyarbakır Heritage Library',
    category: 'cultural',
    status: 'Under Construction', area: '6,800 m²',
    typology: 'Cultural / Library', location: 'Diyarbakır, Turkey', year: '2023 – 2026',
    desc: 'A public library and cultural archive set within the historic basalt-walled city of Diyarbakır. The building mediates between the ancient Armenian and Kurdish heritage of the Sur district and a contemporary reading of civic space. Perforated basalt screens cast shifting shadow patterns across the reading rooms, reconnecting the interior with the geology of the city walls that surround it.',
    narrative: `"Diyarbakır's black basalt is not a building material — it is a geological memory. We asked it to speak again, this time for knowledge rather than defence."`,
    materials: ['Diyarbakır Black Basalt', 'Poured Concrete', 'Perforated Corten Steel', 'White Oak Shelving', 'Recycled Glass Block'],
    related: [0, 18],
    imgs: [
      'https://images.unsplash.com/photo-1481026469463-66327c86e544?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1568659358410-5eba5f84ede2?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '12', name: 'Zakho River Pavilions',
    category: 'hospitality',
    status: 'Completed', area: '3,400 m²',
    typology: 'Hospitality / Leisure', location: 'Zakho, Kurdistan Region', year: '2022 – 2024',
    desc: 'A sequence of lightweight leisure pavilions extending over the Khabur River in Zakho, the ancient crossroads city of the Kurdistan Region. Each pavilion — restaurant, café, event space — sits on slender steel stilts, allowing the river to flow unimpeded beneath. The arching structural forms echo the famous Delal Bridge, whose eight centuries of presence in the river have made it the spiritual centre of the city.',
    narrative: `"The Delal Bridge has stood in this river for 800 years without apology. We asked ourselves what it would mean to add to that conversation — to place something new beside the enduring."`,
    materials: ['Weathered Steel Structure', 'Laminated Timber Deck', 'Tensile Fabric Canopy', 'Pale Zakho Stone', 'Oxidised Bronze Handrail'],
    related: [8, 2],
    imgs: [
      'https://images.unsplash.com/photo-1504711434969-e33886168f5c?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1469796466635-d906090ddd4a?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '13', name: 'Amadiyah Cliff Retreat',
    category: 'hospitality',
    status: 'Completed', area: '2,600 m²',
    typology: 'Hospitality / Boutique Hotel', location: 'Amadiyah, Kurdistan Region', year: '2021 – 2023',
    desc: 'A 22-key boutique retreat carved into the limestone plateau of Amadiyah, one of the most dramatically positioned towns in the Middle East. Guest rooms step down the cliff escarpment as a cascade of stone volumes, each opening onto a private terrace with uninterrupted views of the Barzan valley. Local limestone and hand-plastered walls keep the architecture in quiet, unhurried dialogue with the ancient rock it inhabits.',
    narrative: `"Amadiyah has been on this plateau since before written history. We spent one year studying the cliff before we placed a single line. The building grew from that patience."`,
    materials: ['Amadiyah Limestone', 'Hand-Beaten Copper Fixtures', 'Polished Gypsum Plaster', 'Mountain Cedar Joinery', 'Rammed Earth Partition'],
    related: [8, 4],
    imgs: [
      'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1507608616759-54f48f0af0ee?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1554995207-c18c203602cb?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '14', name: 'Halabja Memorial Museum',
    category: 'cultural',
    status: 'Concept / Planning', area: '5,200 m²',
    typology: 'Cultural / Memorial', location: 'Halabja, Kurdistan Region', year: '2024',
    desc: 'A memorial museum commemorating the victims of the 1988 chemical attack on Halabja. The architecture refuses spectacle: the building descends into the earth as a series of inclined planes that emerge from the landscape, drawing visitors inward through silence rather than monument. Rough concrete walls bear no ornament. The only light enters from above — a vertical shaft of sky at the deepest point of the descent.',
    narrative: `"We were not asked to make architecture. We were asked to hold a wound open long enough for the world to look at it honestly. Every decision was made in that awareness."`,
    materials: ['Board-Formed Raw Concrete', 'Corten Steel Threshold', 'Compacted Earth Floor', 'Frosted Skylight Glass', 'Basalt Memorial Slab'],
    related: [0, 10],
    imgs: [
      'https://images.unsplash.com/photo-1590725121839-892b458a74fe?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '15', name: 'Ranya Market Hall',
    category: 'commercial',
    status: 'Completed', area: '7,100 m²',
    typology: 'Commercial / Market', location: 'Ranya, Kurdistan Region', year: '2020 – 2022',
    desc: 'A contemporary market hall that reorganises the informal trading culture of Ranya, a lakeside city on the Dukan reservoir. A single long-span concrete canopy — its coffers derived from the geometry of traditional Kurdish carpet — shelters market stalls, a wholesale zone, and a rooftop café terrace with views across the lake. The open-sided structure blurs the boundary between market and city.',
    narrative: `"The bazaar is the oldest form of public space in Kurdistan. We did not try to modernise it — we tried to give it shelter, light, and air, and then step aside."`,
    materials: ['Precast Concrete Coffer', 'Polished Aggregate Floor', 'Galvanised Steel Frame', 'Translucent Polycarbonate', 'Reclaimed Brick Infill'],
    related: [3, 9],
    imgs: [
      'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1524860769819-c4d8b74e1fdb?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '16', name: 'Akre Hilltop Resort',
    category: 'hospitality',
    status: 'Under Construction', area: '12,400 m²',
    typology: 'Hospitality / Resort', location: 'Akre, Kurdistan Region', year: '2022 – 2025',
    desc: 'A luxury resort positioned on the terraced hillsides above Akre, a mediaeval Kurdish town cascading down the Bradost mountains. Each of the 48 guest suites is a private stone-clad volume opening onto its own planted terrace — a contemporary reinterpretation of the traditional terrace house. A central hammam, two restaurants, and an infinity pool anchored to the cliff edge complete the programme.',
    narrative: `"Akre has been terracing these hillsides for a thousand years. Every garden wall, every stone step, every fig tree is architecture. We tried to make something that could one day be mistaken for part of that accretion."`,
    materials: ['Akre Field Stone', 'Board-Formed Concrete', 'Hammered Copper Cladding', 'Mountain Cedar Screens', 'Lime Render'],
    related: [2, 4],
    imgs: [
      'https://images.unsplash.com/photo-1519052537078-4cd2f4f87b94?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1558369981-f9ca78462e61?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1519582707714-a98d39f31f56?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '17', name: 'Sinjar Reconstruction Framework',
    category: 'urban',
    status: 'Concept / Planning', area: '420,000 m²',
    typology: 'Urban Design / Reconstruction', location: 'Sinjar, Iraq', year: '2024',
    desc: 'An urban reconstruction framework for Sinjar, devastated by the 2014 IS occupation and largely abandoned since liberation in 2015. The plan prioritises the return of the Yazidi community through incremental reconstruction of the historic centre, new civic anchors — a community archive, a health clinic, a cultural hall — and a memorial landscape on the ruins of the main market. The framework is designed to be built by the community itself, over twenty years.',
    narrative: `"There is no architectural brief in the world harder than this: design for a community that no longer exists in its homeland, for a future they are not yet certain they can return to."`,
    materials: ['Recycled Local Stone', 'Rammed Earth', 'Reclaimed Timber', 'Compressed Soil Block', 'Tensile Shade Structure'],
    related: [9, 5],
    imgs: [
      'https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1515263487990-61b07816b324?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '18', name: 'Afrin Housing Collective',
    category: 'residential',
    status: 'Concept / Planning', area: '34,000 m²',
    typology: 'Residential / Social Housing', location: 'Afrin, Syria', year: '2024',
    desc: 'A social housing prototype developed with displaced Kurdish communities from the Afrin canton of northern Syria. The scheme proposes 240 units organised around shared courtyards, drawing on the collective living traditions of Kurdish villages in the region. Modular construction using rammed earth and recycled concrete allows communities to build incrementally over time, adapting the framework as their needs and resources evolve.',
    narrative: `"We did not design a building. We designed a kit of parts, and a set of rules, and then we handed it to the community. Architecture that is truly for people must at some point belong to them."`,
    materials: ['Rammed Earth Block', 'Recycled Concrete Aggregate', 'Timber Post-and-Beam', 'Lime Plaster', 'Hand-Formed Clay Tile'],
    related: [4, 5],
    imgs: [
      'https://images.unsplash.com/photo-1600210492493-0946911123ea?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1523217582562-09d0def993a6?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '19', name: 'Kobani Civic Center',
    category: 'cultural',
    status: 'Under Construction', area: '8,900 m²',
    typology: 'Cultural / Civic', location: 'Kobani, Syria', year: '2022 – 2025',
    desc: 'A civic centre for a city rebuilt from near-total destruction following the 2014–15 siege. The building combines a municipal library, a cultural archive of the siege and reconstruction, a 350-seat hall, and flexible community spaces. Fragments of the original city fabric — reclaimed stone, salvaged steel — are embedded into the new walls, making memory a structural as much as symbolic element of the architecture.',
    narrative: `"Kobani was destroyed and then it rebuilt itself. Our job was not to replace what was lost but to hold space for what the community is still in the process of becoming."`,
    materials: ['Reclaimed Kobani Stone', 'Salvaged Steel Frame', 'Exposed Concrete', 'Recycled Brick', 'Polished Concrete Floor'],
    related: [0, 13],
    imgs: [
      'https://images.unsplash.com/photo-1531834685032-c34bf0d84c77?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1516455207990-7a41ce80f7ee?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1529417305485-480f579e7578?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '20', name: 'Kermanshah Arts Complex',
    category: 'cultural',
    status: 'Completed', area: '15,800 m²',
    typology: 'Cultural / Arts Complex', location: 'Kermanshah, Iran', year: '2019 – 2022',
    desc: 'A major arts complex serving the Kurdish-majority province of Kermanshah. The building brings together a 900-seat concert hall, three visual arts galleries, an artists-in-residence programme, and a rooftop garden with views of the Zagros foothills. The perforated concrete facade draws on the bas-relief tradition of the UNESCO site at Bisotun — 30 km distant — translating its layered stone registers into a contemporary sun-screen legible from the city below.',
    narrative: `"Bisotun is one of the greatest works of public communication in human history — carved into a mountain so that every traveller on the ancient Silk Road would receive the message. We asked what the contemporary equivalent might look like."`,
    materials: ['Reinforced Concrete Shell', 'Perforated Concrete Screen', 'Kermanshah Travertine', 'Burnished Bronze Joinery', 'Acoustic Timber Ceiling'],
    related: [6, 0],
    imgs: [
      'https://images.unsplash.com/photo-1429041966141-44d228a42775?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?auto=format&fit=crop&w=1400&q=80',
    ]
  },
  {
    num: '21', name: 'Torre Mizuri — Madrid',
    category: 'mixed-use',
    status: 'Under Construction', area: '38,500 m²',
    typology: 'Mixed-Use / High-Rise', location: 'Madrid, Spain', year: '2023 – 2026',
    desc: 'A 28-storey mixed-use tower in the Méndez Álvaro district of Madrid, combining Grade-A offices, 64 serviced residences, a sky garden on the 14th floor, and ground-floor retail and cultural space. The building\'s ceramic brise-soleil system draws on both the Spanish azulejo tile tradition and the geometric vocabulary of Kurdish ornament — a dialogue between two Mediterranean and Middle Eastern heritages in one facade.',
    narrative: `"We were asked to build in Madrid but we could not leave Kurdistan behind. The ceramic screen is our attempt to bring those two worlds into a single surface — not as a gesture, but as an argument."`,
    materials: ['Handmade Ceramic Tile', 'High-Performance Glass', 'Structural Steel Core', 'Polished Concrete Slab', 'Green Roof System'],
    related: [1, 7],
    imgs: [
      'https://images.unsplash.com/photo-1431576901776-e539bd916ba2?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?auto=format&fit=crop&w=1400&q=80',
      'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?auto=format&fit=crop&w=1400&q=80',
    ]
  },
];

/* ---- Project coordinates ------------------------------- */
/* Coordinates now live on each project record (lat/lng, editable in the
   admin). projCoords() reads them per project and only falls back to the
   legacy index-aligned array below for any record left without coordinates. */
function projCoords(proj, i) {
    if (proj && proj.lat != null && proj.lng != null && proj.lat !== '' && proj.lng !== '') {
        const la = Number(proj.lat), ln = Number(proj.lng);
        if (!Number.isNaN(la) && !Number.isNaN(ln) && (la !== 0 || ln !== 0)) return [la, ln];
    }
    return (typeof PROJECT_COORDS !== 'undefined' && PROJECT_COORDS[i]) ? PROJECT_COORDS[i] : null;
}

/* Legacy fallback [lat, lng], index-aligned with the original project order. */
const PROJECT_COORDS = [
    [36.1912, 44.0092],  // 01 Kurdish Cultural Center — Erbil centre
    [36.2018, 44.0278],  // 02 Erbil Tower Residences
    [36.1983, 44.0088],  // 03 Citadel View Hotel — Erbil Citadel
    [36.1718, 44.0465],  // 04 Kurdistan Tech Campus — east Erbil
    [36.4073, 44.3224],  // 05 Mountain Villa — Shaqlawa
    [36.1958, 43.9988],  // 06 Erbil Urban Regeneration — west centre
    [35.5616, 45.4322],  // 07 Kurdistan Museum of Art — Sulaymaniyah
    [36.1785, 44.0152],  // 08 Erbil Business Tower
    [36.8663, 42.9816],  // 09 Duhok Mountain Resort
    [35.4676, 44.3922],  // 10 Kirkuk Public Square
    [37.9144, 40.2306],  // 11 Diyarbakır Heritage Library — Diyarbakır, Turkey
    [37.1444, 42.6849],  // 12 Zakho River Pavilions — Zakho, Kurdistan Region
    [37.0861, 43.4889],  // 13 Amadiyah Cliff Retreat — Amadiyah, Kurdistan Region
    [35.1803, 45.9864],  // 14 Halabja Memorial Museum — Halabja, Kurdistan Region
    [36.2574, 44.8789],  // 15 Ranya Market Hall — Ranya, Kurdistan Region
    [36.7439, 43.8904],  // 16 Akre Hilltop Resort — Akre, Kurdistan Region
    [36.3199, 41.8596],  // 17 Sinjar Reconstruction Framework — Sinjar, Iraq
    [36.5133, 36.8688],  // 18 Afrin Housing Collective — Afrin, Syria
    [36.8932, 38.3533],  // 19 Kobani Civic Center — Kobani, Syria
    [34.3142, 47.0650],  // 20 Kermanshah Arts Complex — Kermanshah, Iran
    [40.4168, -3.7038],  // 21 Torre Mizuri — Madrid, Spain
];

/* ---- Project Overlay (immersive scrollable detail) ------ */
(function initProjectOverlay() {
    const overlay = document.getElementById('projOverlay');
    if (!overlay) return;

    const closeBtn    = document.getElementById('projOverlayClose');
    const projPrevBtn = document.getElementById('overlayProjPrev');
    const projNextBtn = document.getElementById('overlayProjNext');
    const counterEl   = document.getElementById('overlayCounter');
    const heroImg     = document.getElementById('overlayHeroImg');
    const thumbsEl    = document.getElementById('overlayThumbs');
    const imgPrevBtn  = document.getElementById('overlayImgPrev');
    const imgNextBtn  = document.getElementById('overlayImgNext');
    const bodyEl      = document.getElementById('overlayBody');
    const topbarEl    = overlay.querySelector('.proj-overlay__topbar');
    const ambientEls  = overlay.querySelectorAll('.od-ambient');
    const fullResEl   = document.getElementById('overlayFullRes');
    const downloadEl  = document.getElementById('overlayDownload');

    // Derive a full-resolution URL for the currently shown image. Unsplash mock
    // images carry sizing params (?w=1400&q=80) — stripping them serves the
    // original native-resolution file. Real uploaded images are already full size.
    function fullResUrl(url) {
        if (!url) return url;
        if (url.indexOf('images.unsplash.com') !== -1) {
            return url.split('?')[0] + '?q=90';   // native resolution, high quality
        }
        return url;
    }
    function slugify(s) {
        return (s || 'project').toLowerCase()
            .replace(/[^\w\s-]/g, '').trim().replace(/\s+/g, '-');
    }
    // Point the "view full resolution" + download links at the current image.
    // href stays set (right-click / no-JS fallback); the click handlers below
    // intercept to serve a watermark-stamped copy.
    function updateImageLinks() {
        const proj = PROJECT_DATA[currentProjIdx];
        if (!proj) return;
        const url = fullResUrl(proj.imgs[currentImgIdx]);
        const fname = slugify(proj.name) + '-' + (currentImgIdx + 1) + '.jpg';
        if (fullResEl) { fullResEl.href = url; fullResEl.dataset.src = url; }
        if (downloadEl) {
            downloadEl.href = url;
            downloadEl.dataset.src = url;
            downloadEl.dataset.fname = fname;
            downloadEl.setAttribute('download', fname);
        }
    }

    /* ---- Copyright watermark on full-res / download ---------
       Stamps the Aram Mizuri logo across the bottom-centre of the exported
       image, with a soft black shadow so it stays legible on any background,
       so downloaded visualisations carry attribution. Falls back to a text
       credit if the logo asset is missing, and to the raw file if the canvas
       can't be exported (e.g. a cross-origin image without CORS headers taints
       the canvas). Replace /watermark-logo.png with a white, transparent-
       background PNG of the studio logo to change the mark. */
    const WM_LOGO = new Image();
    const wmLogoReady = new Promise(function (resolve) {
        WM_LOGO.onload = function () { resolve(WM_LOGO.naturalWidth > 0); };
        WM_LOGO.onerror = function () { resolve(false); };
    });
    WM_LOGO.src = '/watermark-logo.png';           // white wordmark on transparency

    // Fallback used only when the logo image can't be loaded.
    function drawTextWatermark(ctx, w, h) {
        const pad = Math.max(22, Math.round(w * 0.03));
        const fs  = Math.max(15, Math.round(w * 0.019));
        ctx.save();
        ctx.textBaseline = 'alphabetic';
        ctx.textAlign = 'center';
        if ('letterSpacing' in ctx) ctx.letterSpacing = Math.max(1, Math.round(fs * 0.06)) + 'px';
        ctx.font = '600 ' + fs + 'px "Inter", Arial, sans-serif';
        ctx.shadowColor = 'rgba(0,0,0,.6)';
        ctx.shadowBlur = fs * 0.6;
        ctx.shadowOffsetY = 1;
        ctx.fillStyle = 'rgba(255,255,255,.92)';
        ctx.fillText('Designed by Architect Aram Mizuri', w / 2, h - pad);
        ctx.restore();
    }

    function drawWatermark(ctx, w, h) {
        if (!WM_LOGO.naturalWidth) { drawTextWatermark(ctx, w, h); return; }
        const ratio = WM_LOGO.naturalHeight / WM_LOGO.naturalWidth;
        const logoW = Math.round(w * 0.24);        // ~24% of the image width
        const logoH = Math.round(logoW * ratio);
        const x = Math.round((w - logoW) / 2);     // horizontally centred
        const y = Math.round(h - logoH - h * 0.035); // a little above the bottom
        ctx.save();
        // passes 1–2: build a deep, soft black shadow cast by the logo's shape,
        // so the white mark stays legible even over bright photos. Two passes
        // deepen the shadow without brightening the white strokes.
        ctx.shadowColor = 'rgba(0,0,0,.65)';
        ctx.shadowBlur = Math.max(8, Math.round(logoW * 0.045));
        ctx.shadowOffsetY = Math.max(2, Math.round(logoH * 0.1));
        ctx.drawImage(WM_LOGO, x, y, logoW, logoH);
        ctx.drawImage(WM_LOGO, x, y, logoW, logoH);
        // pass 3: redraw crisp on top of its own shadow for a clean edge
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;
        ctx.globalAlpha = 0.97;
        ctx.drawImage(WM_LOGO, x, y, logoW, logoH);
        ctx.restore();
    }

    function watermarkedURL(url) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.crossOrigin = 'anonymous';         // request CORS so canvas isn't tainted
            img.onload = function () {
                wmLogoReady.then(function () {     // ensure the logo is ready to stamp
                    try {
                        const c = document.createElement('canvas');
                        c.width = img.naturalWidth || img.width;
                        c.height = img.naturalHeight || img.height;
                        const ctx = c.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        drawWatermark(ctx, c.width, c.height);
                        c.toBlob(function (blob) {
                            blob ? resolve(URL.createObjectURL(blob)) : reject(new Error('export failed'));
                        }, 'image/jpeg', 0.92);
                    } catch (err) { reject(err); }
                });
            };
            img.onerror = function () { reject(new Error('load failed')); };
            img.src = url;
        });
    }

    if (fullResEl) fullResEl.addEventListener('click', function (e) {
        const url = fullResEl.dataset.src;
        if (!url) return;
        e.preventDefault();
        const win = window.open('', '_blank');     // open synchronously (popup-blocker safe)
        watermarkedURL(url).then(function (obj) {
            if (win) win.location = obj; else window.open(obj, '_blank');
            setTimeout(function () { URL.revokeObjectURL(obj); }, 60000);
        }).catch(function () {
            if (win) win.location = url; else window.open(url, '_blank');
        });
    });

    if (downloadEl) downloadEl.addEventListener('click', function (e) {
        const url = downloadEl.dataset.src;
        if (!url) return;
        e.preventDefault();
        const fname = downloadEl.dataset.fname || 'project.jpg';
        const trigger = function (href, revoke) {
            const a = document.createElement('a');
            a.href = href; a.download = fname;
            document.body.appendChild(a); a.click(); a.remove();
            if (revoke) setTimeout(function () { URL.revokeObjectURL(href); }, 60000);
        };
        watermarkedURL(url).then(function (obj) { trigger(obj, true); })
                           .catch(function () { trigger(url, false); });
    });

    // Paint a blurred copy of the current image behind the image + info panel,
    // so both pick up the project's own colours (ambient "liquid glass").
    function applyAmbient(url) {
        const bg = 'url("' + url + '")';
        ambientEls.forEach(function (el) { el.style.backgroundImage = bg; });
    }

    /* ---- Location mini-map (picture-in-picture) ---------- */
    var miniMap = null, miniMarker = null;
    var miniCanvas = document.getElementById('overlayMapCanvas');
    var miniCityEl = document.getElementById('overlayMapCity');
    var miniWrap   = document.getElementById('overlayMap');

    function ensureMiniMap() {
        if (miniMap || typeof L === 'undefined' || !miniCanvas) return;
        miniMap = L.map(miniCanvas, {
            zoomControl: false, attributionControl: false,
            dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
            boxZoom: false, keyboard: false, touchZoom: false, tap: false,
            inertia: false, fadeAnimation: false,
        });
        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            subdomains: 'abcd', maxZoom: 18,
        }).addTo(miniMap);
        miniMarker = L.marker([0, 0], {
            interactive: false,
            icon: L.divIcon({
                className: 'od-sidemap__pin-wrap',
                html: '<span class="od-sidemap__pin"></span>',
                iconSize: [20, 20], iconAnchor: [10, 20],
            }),
        }).addTo(miniMap);
    }

    // Recentre the mini-map on the given project's location and drop the pin.
    function updateMiniMap(index, proj) {
        var coords = projCoords(proj, index);
        if (!miniWrap) return;
        if (!coords || typeof L === 'undefined' || !miniCanvas) {
            miniWrap.style.display = 'none';      // no location → no map
            return;
        }
        miniWrap.style.display = '';
        ensureMiniMap();
        miniMap.setView(coords, 12, { animate: false });   // city scale
        miniMarker.setLatLng(coords);
        if (miniCityEl) {
            // Show the neighbourhood on the map, falling back to the city.
            miniCityEl.textContent = proj.neighbourhood || proj.city
                || (proj.location || '').split(',')[0].trim() || proj.location || '';
        }
        // The canvas may have rendered at the wrong size while the modal was
        // scaling in; recompute tile layout once it has settled.
        setTimeout(function () { if (miniMap) miniMap.invalidateSize(false); }, 360);
    }


    const cursor   = document.getElementById('cursor');
    const follower = document.getElementById('cursorFollower');

    let currentProjIdx = 0;
    let currentImgIdx  = 0;
    let isOpen         = false;
    let allCards       = [];

    /* ---- Which projects the overlay walks through ----------
       Map-only projects (a pin + a line in the statistics, no photos) have no
       gallery card, so they must not turn up when stepping to the next / previous
       project either — the overlay would show an empty frame. Same for anything
       that ended up with no images. PROJECT_DATA keeps every project so the map
       and the statistics stay complete; these are the indices into it that the
       overlay is allowed to show. */
    function isGalleryProject(proj) {
        return !!proj && !proj.map_only && Array.isArray(proj.imgs) && proj.imgs.length > 0;
    }

    const galleryIdx = PROJECT_DATA
        .map(function (proj, i) { return isGalleryProject(proj) ? i : -1; })
        .filter(function (i) { return i !== -1; });

    /* ---- Image switcher ---------------------------------- */
    function setImg(idx) {
        const proj = PROJECT_DATA[currentProjIdx];
        if (!proj) return;
        currentImgIdx = ((idx % proj.imgs.length) + proj.imgs.length) % proj.imgs.length;
        heroImg.style.opacity = '0';
        setTimeout(() => {
            heroImg.src = proj.imgs[currentImgIdx];
            heroImg.alt = proj.name;
            heroImg.style.opacity = '1';
            applyAmbient(proj.imgs[currentImgIdx]);
        }, 200);
        thumbsEl.querySelectorAll('.od-thumb').forEach((t, i) => {
            t.classList.toggle('active', i === currentImgIdx);
        });
        updateImageLinks();
    }

    /* ---- Generate hashtags from project data ------------ */
    function buildTags(proj) {
        var tags = [];
        // Categories — a project can carry several
        var cats = (proj.categories && proj.categories.length) ? proj.categories : [proj.category];
        cats.forEach(function(c) {
            if (!c) return;
            var tag = '#' + String(c).replace(/[\s-]+/g, '');
            if (tags.indexOf(tag) === -1) tags.push(tag);
        });
        // Typology parts (e.g. "Cultural / Civic" → #cultural #civic)
        proj.typology.split('/').forEach(function(t) {
            var slug = t.trim().toLowerCase().replace(/\s+/g, '');
            if (slug && tags.indexOf('#' + slug) === -1) tags.push('#' + slug);
        });
        // City
        var city = proj.location.split(',')[0].trim().toLowerCase().replace(/\s+/g, '');
        if (city) tags.push('#' + city);
        // Status shorthand
        if      (proj.status === 'Completed')           tags.push('#built');
        else if (proj.status === 'Under Construction')  tags.push('#inprogress');
        else                                            tags.push('#concept');
        return tags.slice(0, 6);
    }

    /* ---- Populate overlay -------------------------------- */
    function populateOverlay(index) {
        var proj = PROJECT_DATA[index];
        if (!proj) return;

        var isKu = document.documentElement.getAttribute('dir') === 'rtl';
        var STATUS_KU = { 'Completed': 'تەواوبوو', 'Under Construction': 'لەژێر بنیاتنان', 'Concept / Planning': 'بیرۆکە و پلاندانان' };

        // tone comes from the DB-managed statuses (name => done|build|concept),
        // with a sensible fallback for the three original statuses.
        var tones = (window.__SITE__ && window.__SITE__.statusTones) || {};
        var tone  = tones[proj.status]
                 || (proj.status === 'Completed'          ? 'done'
                  :  proj.status === 'Under Construction' ? 'build'
                  :  'concept');
        var statusClass = tone === 'done' ? 's-done' : tone === 'build' ? 's-build' : 's-concept';

        // Status dot + badge
        var dotEl   = document.getElementById('overlayDot');
        var badgeEl = document.getElementById('overlayStatusBadge');
        dotEl.className   = 'od-dot '    + statusClass;
        badgeEl.textContent = (isKu && STATUS_KU[proj.status]) ? STATUS_KU[proj.status] : proj.status;
        badgeEl.className = 'od-status-text ' + statusClass;

        // Num + name
        document.getElementById('overlayNum').textContent      = proj.num;
        document.getElementById('overlayName').textContent     = (isKu && proj.name_ku) ? proj.name_ku : proj.name;

        // Spec fields
        document.getElementById('overlayLocation').textContent = proj.location;
        document.getElementById('overlayYear').textContent     = proj.year;
        document.getElementById('overlayTypology').textContent = proj.typology;
        document.getElementById('overlayArea').textContent     = proj.area;

        // Hashtags
        var tagsEl = document.getElementById('overlayTags');
        tagsEl.innerHTML = '';
        buildTags(proj).forEach(function(tag) {
            var span = document.createElement('span');
            span.className   = 'od-tag';
            span.textContent = tag;
            tagsEl.appendChild(span);
        });

        // Description
        document.getElementById('overlayDesc').textContent = (isKu && proj.desc_ku) ? proj.desc_ku : proj.desc;

        // Counter — position among the projects the overlay can actually show
        var pos = galleryIdx.indexOf(index);
        counterEl.textContent = (pos === -1 ? 1 : pos + 1) + ' / ' + (galleryIdx.length || 1);

        // Location mini-map
        updateMiniMap(index, proj);

        // Thumbstrip
        thumbsEl.innerHTML = '';
        proj.imgs.forEach(function(src, i) {
            var img     = document.createElement('img');
            img.src       = src;
            img.className = 'od-thumb' + (i === 0 ? ' active' : '');
            img.alt       = proj.name + ' — photo ' + (i + 1);
            img.addEventListener('click', function() { setImg(i); });
            thumbsEl.appendChild(img);
        });

        // Hero image — crossfade
        currentImgIdx = 0;
        heroImg.style.opacity = '0';
        setTimeout(function() {
            heroImg.src = proj.imgs[0];
            heroImg.alt = proj.name;
            heroImg.style.opacity = '';  // CSS transition handles this
            applyAmbient(proj.imgs[0]);
        }, 60);
        updateImageLinks();

        if (topbarEl) topbarEl.classList.remove('bar-solid');
    }

    /* ---- Stagger grid cards ------------------------------ */
    function staggerCards(selectedIdx) {
        allCards.forEach((card, i) => {
            card.classList.remove('proj-fall', 'proj-selected');
            if (parseInt(card.dataset.index, 10) === selectedIdx) {
                card.classList.add('proj-selected');
            } else {
                card.style.transitionDelay = (i * 35) + 'ms';
                card.classList.add('proj-fall');
            }
        });
    }

    function restoreCards() {
        const total = allCards.length;
        allCards.forEach((card, i) => {
            card.style.transitionDelay = ((total - 1 - i) * 25) + 'ms';
            card.classList.remove('proj-fall', 'proj-selected');
        });
        setTimeout(() => allCards.forEach(c => { c.style.transitionDelay = ''; }), total * 25 + 600);
    }

    /* ---- Open -------------------------------------------- */
    function openOverlay(index) {
        // Nothing to show for a map-only / image-less project.
        if (!isGalleryProject(PROJECT_DATA[index])) return;
        currentProjIdx = index;
        isOpen = true;
        populateOverlay(index);
        staggerCards(index);
        setTimeout(() => {
            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }, 180);
    }

    /* ---- Close ------------------------------------------- */
    function closeOverlay() {
        if (!isOpen) return;
        isOpen = false;
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        restoreCards();
    }

    /* ---- Switch project ---------------------------------- */
    function switchProject(newIndex) {
        if (!isGalleryProject(PROJECT_DATA[newIndex])) return;
        currentProjIdx = newIndex;
        // Briefly collapse open state so CSS stagger animations replay
        overlay.classList.remove('open');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                populateOverlay(newIndex);
                overlay.classList.add('open');
            });
        });
        // Cards carry their PROJECT_DATA index, which is not their position in
        // the grid once map-only projects are left out of the markup.
        allCards.forEach(card => {
            const selected = parseInt(card.dataset.index, 10) === newIndex;
            card.classList.toggle('proj-selected', selected);
            card.classList.toggle('proj-fall',    !selected);
        });
    }

    /* Next / previous, walking only the projects that have a gallery. */
    function stepProject(delta) {
        const total = galleryIdx.length;
        if (!total) return;
        let pos = galleryIdx.indexOf(currentProjIdx);
        if (pos === -1) pos = delta > 0 ? -1 : 0;   // off-list: enter from the edge
        switchProject(galleryIdx[((pos + delta) % total + total) % total]);
    }

    /* ---- Wire grid cards --------------------------------- */
    allCards = Array.from(document.querySelectorAll('.pgc'));
    allCards.forEach(card => {
        card.addEventListener('click', () => {
            const idx = parseInt(card.dataset.index, 10);
            if (isNaN(idx)) return;
            if (isOpen && currentProjIdx === idx) return;
            if (isOpen) { switchProject(idx); return; }
            openOverlay(idx);
        });
        card.addEventListener('mouseenter', () => {
            cursor   && cursor.classList.add('cursor--expand');
            follower && follower.classList.add('cursor-follower--expand');
        });
        card.addEventListener('mouseleave', () => {
            cursor   && cursor.classList.remove('cursor--expand');
            follower && follower.classList.remove('cursor-follower--expand');
        });
    });

    /* ---- Controls ---------------------------------------- */
    closeBtn && closeBtn.addEventListener('click', closeOverlay);
    // click the blurred backdrop (outside the card) to dismiss
    overlay.addEventListener('click', e => { if (e.target === overlay) closeOverlay(); });
    projPrevBtn && projPrevBtn.addEventListener('click', e => { e.stopPropagation(); stepProject(-1); });
    projNextBtn && projNextBtn.addEventListener('click', e => { e.stopPropagation(); stepProject(1); });
    imgPrevBtn  && imgPrevBtn.addEventListener('click',  e => { e.stopPropagation(); setImg(currentImgIdx - 1); });
    imgNextBtn  && imgNextBtn.addEventListener('click',  e => { e.stopPropagation(); setImg(currentImgIdx + 1); });

    document.addEventListener('keydown', e => {
        if (!isOpen) return;
        if (e.key === 'Escape')     closeOverlay();
        if (e.key === 'ArrowLeft')  setImg(currentImgIdx - 1);
        if (e.key === 'ArrowRight') setImg(currentImgIdx + 1);
        if (e.key === 'ArrowUp')    { e.preventDefault(); stepProject(-1); }
        if (e.key === 'ArrowDown')  { e.preventDefault(); stepProject(1); }
    });

    // Re-render the open project in the new language when the site is toggled.
    document.addEventListener('langchange', function () {
        if (isOpen) populateOverlay(currentProjIdx);
    });

    window.openProjectOverlay  = openOverlay;
    window.closeProjectOverlay = closeOverlay;
})();

/* ---- Projects Grid (BIG-inspired) ---------------------- */
(function initProjectGrid() {
    const grid    = document.getElementById('pgGrid');
    const emptyEl = document.getElementById('pgEmpty');
    const search  = document.getElementById('pgSearch');
    if (!grid) return;

    const cards = Array.from(grid.querySelectorAll('.pgc'));

    /* ---- Lazy load images -------------------------------- */
    const imgObserver = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const img = e.target;
            const src = img.dataset.src;
            if (src) {
                img.src = src;
                img.addEventListener('load', () => img.classList.add('img-loaded'), { once: true });
                if (img.complete && img.naturalWidth) img.classList.add('img-loaded');
            }
            imgObserver.unobserve(img);
        });
    }, { rootMargin: '200px' });

    cards.forEach(card => {
        const img = card.querySelector('.pgc__img');
        if (img) imgObserver.observe(img);
    });

    /* ---- Horizontal scroll controls ---------------------- */
    const prevBtn  = document.getElementById('pgPrev');
    const nextBtn  = document.getElementById('pgNext');
    const progress = document.getElementById('pgProgress');

    function maxScroll() { return grid.scrollWidth - grid.clientWidth; }
    function pageStep()  { return Math.max(grid.clientWidth * 0.8, 280); }

    function updateSliderUI() {
        const max = maxScroll();
        const x   = grid.scrollLeft;
        if (prevBtn) prevBtn.disabled = x <= 4;
        if (nextBtn) nextBtn.disabled = x >= max - 4;
        if (progress) progress.style.width = (max > 4 ? (x / max) * 100 : 0) + '%';
        // trailing-edge fade only while there is somewhere left to go
        // (|x| copes with RTL, where scrollLeft runs negative)
        const slider = grid.closest('.pg__slider');
        if (slider) slider.classList.toggle('at-end', Math.abs(x) >= max - 4);
    }

    /* ---- "More to explore" affordances -------------------
       1. chip under the rail — dismissed forever after the first real scroll
       2. one-time peek nudge when the grid first enters the viewport:
          the row moves, so the eye learns the axis before reading a word */
    const moreChip = document.getElementById('pgMore');
    let nudging = false, explored = false;
    function dismissMore() {
        if (explored) return;
        explored = true;
        if (moreChip) moreChip.classList.add('is-done');
    }
    grid.addEventListener('scroll', () => {
        if (!nudging && Math.abs(grid.scrollLeft) > 60) dismissMore();
    }, { passive: true });

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
        const peekIO = new IntersectionObserver(entries => {
            if (!entries[0].isIntersecting || explored) return;
            peekIO.disconnect();
            const sign = document.documentElement.getAttribute('dir') === 'rtl' ? -1 : 1;
            nudging = true;
            setTimeout(() => {
                grid.scrollBy({ left: sign * 150, behavior: 'smooth' });
                setTimeout(() => {
                    grid.scrollBy({ left: -sign * 150, behavior: 'smooth' });
                    setTimeout(() => { nudging = false; }, 800);
                }, 850);
            }, 650);
        }, { threshold: 0.45 });
        peekIO.observe(grid);
    }

    if (prevBtn) prevBtn.addEventListener('click', () => grid.scrollBy({ left: -pageStep(), behavior: 'smooth' }));
    if (nextBtn) nextBtn.addEventListener('click', () => grid.scrollBy({ left:  pageStep(), behavior: 'smooth' }));
    grid.addEventListener('scroll', updateSliderUI, { passive: true });
    window.addEventListener('resize', updateSliderUI);

    /* Press-and-drag to scroll sideways (mouse / trackpad).
       NOTE: we deliberately do NOT use setPointerCapture — capturing the pointer
       on the track makes the browser retarget the follow-up `click` to the track,
       which swallows the card's click and breaks opening a project. We track
       move/up on the window instead, which also keeps drag working if the cursor
       leaves the track. */
    let down = false, startX = 0, startLeft = 0, moved = 0;
    grid.addEventListener('pointerdown', e => {
        if (e.pointerType === 'touch') return;            // touch scrolls natively
        down = true; moved = 0; startX = e.clientX; startLeft = grid.scrollLeft;
    });
    window.addEventListener('pointermove', e => {
        if (!down) return;
        const dx = e.clientX - startX;
        moved = Math.max(moved, Math.abs(dx));
        if (moved > 3) grid.classList.add('is-dragging');  // only once it's a real drag
        grid.scrollLeft = startLeft - dx;
    });
    window.addEventListener('pointerup', () => {
        if (!down) return;
        down = false;
        grid.classList.remove('is-dragging');
    });
    /* swallow the click that follows a real drag so it doesn't open a project */
    grid.addEventListener('click', e => {
        if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
        moved = 0;                                         // reset so a later clean click works
    }, true);

    window.addEventListener('load', updateSliderUI);
    setTimeout(updateSliderUI, 300);
    updateSliderUI();

    /* ---- Filter + Search --------------------------------- */
    let activeFilter = 'all';
    let searchQuery  = '';

    function applyFilter() {
        if (window.closeProjectOverlay) window.closeProjectOverlay();

        let visibleCount = 0;
        cards.forEach(card => {
            // A project can sit in several categories — match any of them.
            const cats = (card.dataset.categories || card.dataset.category || '').split(/\s+/);
            // The haystack covers name, categories, typology, place and year;
            // data-name is the fallback for a card rendered before that existed.
            const hay  = (card.dataset.search || card.dataset.name || '').toLowerCase();
            const catMatch  = activeFilter === 'all' || cats.indexOf(activeFilter) !== -1;
            const textMatch = !searchQuery || hay.includes(searchQuery);
            const show = catMatch && textMatch;

            card.classList.toggle('pg-hidden', !show);
            if (show) visibleCount++;
        });

        if (emptyEl) emptyEl.hidden = visibleCount > 0;

        // re-home the horizontal scroll when the visible set changes
        grid.scrollTo({ left: 0, behavior: 'smooth' });
        updateSliderUI();
    }

    document.querySelectorAll('.pgf-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pgf-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilter();
        });
    });

    if (search) {
        search.addEventListener('input', () => {
            searchQuery = search.value.trim().toLowerCase();
            applyFilter();
        });
        search.addEventListener('keydown', e => {
            if (e.key === 'Escape') { search.value = ''; searchQuery = ''; applyFilter(); }
        });
    }
})();

/* ---- Projects Map -------------------------------------- */
(function initProjectMap() {
    if (typeof L === 'undefined') return;
    const mapEl = document.getElementById('projectMapEl');
    if (!mapEl) return;

    const card        = document.getElementById('mapCard');
    const cardImg     = document.getElementById('mapCardImg');
    const cardNum     = document.getElementById('mapCardNum');
    const cardName    = document.getElementById('mapCardName');
    const cardMeta    = document.getElementById('mapCardMeta');
    const cardExcerpt = document.getElementById('mapCardExcerpt');
    const cardCta     = document.getElementById('mapCardCta');
    const cardClose   = document.getElementById('mapCardClose');
    const cardBadge   = document.getElementById('mapCardBadge');
    const specType    = document.getElementById('mcSpecType');
    const specYear    = document.getElementById('mcSpecYear');
    const specArea    = document.getElementById('mcSpecArea');
    const specLoc     = document.getElementById('mcSpecLoc');


    // On touch devices a single-finger swipe must scroll the page, not pan the
    // map (otherwise the user gets trapped in this section). The gesture-handling
    // plugin enforces "two fingers to move the map" and manages touch-action /
    // preventDefault correctly — doing this by hand fails because the browser
    // claims the two-finger gesture for page-zoom before Leaflet can react.
    // Scoped to touch only so desktop drag/scroll behaviour is unchanged.
    const isTouchDevice = window.matchMedia('(hover: none), (pointer: coarse)').matches;
    // The UMD plugin registers itself on window.leafletGestureHandling and
    // auto-hooks the `gestureHandling` map option; it does NOT set L.GestureHandling.
    const hasGestureHandling = isTouchDevice && typeof window.leafletGestureHandling !== 'undefined';

    // Init map — no default controls, scroll-zoom off (page scrolling otherwise hijacked)
    const map = L.map('projectMapEl', {
        zoomControl:     false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        attributionControl: true,
        gestureHandling: hasGestureHandling,
    }).setView([36.30, 43.80], 7); // centered on Erbil/Kurdistan region

    // CartoDB light nolabels — inverted in CSS to give black land, white roads
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 18,
    }).addTo(map);

    // Render the Kurdistan border from already-loaded GeoJSON data.
    let mapBorderLayer = null;
    function renderKurdistanBorder() {
        if (typeof KURDISTAN_GEOJSON === 'undefined') return;

        const borderLayer = L.geoJSON(KURDISTAN_GEOJSON, {
            style: {
                fillColor:   '#F5C518',
                fillOpacity: 1,
                stroke:      false,
            },
            smoothFactor: 1.5,
        }).addTo(map);

        // Wrap all border paths in a <g> so the SVG filter applies to the group,
        // processing only border pixels rather than the whole overlay pane.
        const overlayPane = map.getPanes().overlayPane;
        const svg = overlayPane && overlayPane.querySelector('svg');
        if (svg) {
            const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            g.setAttribute('class', 'kurdistan-border');
            borderLayer.eachLayer(l => { if (l._path) g.appendChild(l._path); });
            svg.appendChild(g);
        }

        mapBorderLayer = borderLayer;
        fitMap();
    }

    // Frame Kurdistan tightly — and keep it framed when the window resizes
    // (e.g. dev-tools opening) instead of drifting zoomed-out.
    let mapFitTimer = null;
    function fitMap() {
        if (!mapBorderLayer) return;
        map.invalidateSize();
        map.fitBounds(mapBorderLayer.getBounds(), { padding: [40, 30], maxZoom: 8 });
    }
    window.addEventListener('resize', function () {
        clearTimeout(mapFitTimer);
        mapFitTimer = setTimeout(fitMap, 180);
    });

    // Lazy-load the 1.5 MB border data only when the map section enters the viewport.
    // This keeps the initial page load fast for users who haven't scrolled down yet.
    const mapSection = document.getElementById('map');
    const io = new IntersectionObserver(entries => {
        if (!entries[0].isIntersecting) return;
        io.disconnect();
        if (typeof KURDISTAN_GEOJSON !== 'undefined') {
            renderKurdistanBorder();
        } else {
            const s = document.createElement('script');
            s.src = 'kurdistan-data.js';
            s.onload = renderKurdistanBorder;
            document.head.appendChild(s);
        }
    }, { threshold: 0.05 });
    io.observe(mapSection || document.body);

    let activeIdx = null;

    // Build markers — one per project, positioned by its own coordinates
    PROJECT_DATA.forEach((proj, i) => {
        const coords = projCoords(proj, i);
        if (!proj || !coords) return; // no coordinates set → skip its pin gracefully
        const icon = L.divIcon({
            className: '',
            html: `<div class="map-pin" id="mapPin${i}">
                     <div class="pin__ring"></div>
                     <div class="pin__ring"></div>
                     <div class="pin__dot"><span class="pin__num">${proj.num}</span></div>
                   </div>`,
            iconSize:   [36, 36],
            iconAnchor: [18, 18],
        });

        L.marker(coords, { icon }).addTo(map)
         .on('click', function (e) {
             L.DomEvent.stopPropagation(e);
             showCard(i, this.getLatLng());
         });
    });

    /* ---- Show floating card near the clicked pin --------- */
    function showCard(idx, latlng) {
        const proj = PROJECT_DATA[idx];
        if (!proj) return;

        // Swap active pin class
        if (activeIdx !== null) {
            const prev = document.getElementById('mapPin' + activeIdx);
            if (prev) prev.classList.remove('active');
        }
        activeIdx = idx;
        const pinEl = document.getElementById('mapPin' + idx);
        if (pinEl) pinEl.classList.add('active');

        // Populate card — spec variant for map-only (image-less) projects
        const isKu    = document.documentElement.getAttribute('dir') === 'rtl';
        const dash     = '—';
        const specs   = proj.map_only || !proj.imgs || !proj.imgs.length;
        card.classList.toggle('map-card--specs', specs);

        cardNum.textContent  = proj.num || '';
        cardName.textContent = (isKu && proj.name_ku) ? proj.name_ku : proj.name;

        if (specs) {
            // title, type, year, plot area, location — no photo, no overlay
            cardBadge.textContent = proj.status || '';
            cardBadge.hidden = !proj.status;
            var labels = (proj.category_labels && proj.category_labels.length)
                ? proj.category_labels
                : [proj.category].filter(Boolean);
            cardMeta.textContent = labels.join('  ·  ').toUpperCase();
            specType.textContent = proj.typology || dash;
            specYear.textContent = proj.year || dash;
            specArea.textContent = proj.area || dash;
            specLoc.textContent  = proj.location || dash;
        } else {
            cardBadge.hidden = true;
            cardImg.src          = proj.imgs[0];
            cardImg.alt          = proj.name;
            cardMeta.textContent = [proj.location, proj.year, proj.typology].filter(Boolean).join('  ·  ');
            cardExcerpt.textContent = (isKu && proj.desc_ku) ? proj.desc_ku : proj.desc;
            cardCta.onclick = () => {
                if (typeof window.openProjectOverlay === 'function') {
                    window.openProjectOverlay(idx);
                }
            };
        }

        // Smart positioning — card appears to the right of pin,
        // flips left if too close to right edge, clamps top/bottom
        const pt   = map.latLngToContainerPoint(latlng);
        const mapW = mapEl.offsetWidth;
        const mapH = mapEl.offsetHeight;
        const cW   = 320;
        const cH   = 390; // approximate rendered height
        const gap  = 22;

        let left = pt.x + gap;
        let flip = false;
        if (left + cW > mapW - 16) { left = pt.x - cW - gap; flip = true; }

        let top = pt.y - cH / 2;
        if (top < 16)             top = 16;
        if (top + cH > mapH - 76) top = mapH - cH - 76; // clear stat bar

        card.style.left = left + 'px';
        card.style.top  = top  + 'px';
        card.classList.toggle('flip', flip);
        card.classList.add('open');

        // Pan so the pin stays visible (not hidden behind the card)
        map.panTo(latlng, { animate: true, duration: 0.45, easeLinearity: 0.5 });
    }

    /* ---- Hide card --------------------------------------- */
    function hideCard() {
        card.classList.remove('open');
        if (activeIdx !== null) {
            const el = document.getElementById('mapPin' + activeIdx);
            if (el) el.classList.remove('active');
            activeIdx = null;
        }
    }

    cardClose.addEventListener('click', e => { e.stopPropagation(); hideCard(); });
    map.on('click', hideCard);

    // Zoom buttons
    const zoomInBtn  = document.getElementById('mapZoomIn');
    const zoomOutBtn = document.getElementById('mapZoomOut');
    if (zoomInBtn)  zoomInBtn.addEventListener('click',  e => { e.stopPropagation(); map.zoomIn();  });
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', e => { e.stopPropagation(); map.zoomOut(); });
})();

/* ---- Smooth active nav link on scroll ------------------ */
(function initActiveLink() {
    const sections = document.querySelectorAll('section[id]');
    const links    = document.querySelectorAll('.nav__link');
    if (!sections.length || !links.length) return;

    const io = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                links.forEach(l => l.style.removeProperty('font-weight'));
                const active = document.querySelector(`.nav__link[href="#${e.target.id}"]`);
                if (active) active.style.fontWeight = '500';
            }
        });
    }, { rootMargin: '-40% 0px -55% 0px' });

    sections.forEach(s => io.observe(s));
})();
