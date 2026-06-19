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

    if (spin) {
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

    buildKurdishSun(loaderSun, { size: 90, color: '#F5C518', spin: true, spinSpeed: 0.03 });

    window.addEventListener('load', () => {
        setTimeout(() => loader.classList.add('out'), 1700);
    });
})();

/* ---- Kurdistan Sun placements -------------------------- */
(function placeSuns() {
    // Hero — full-opacity sun + stat pills revealed on each full rotation
    var heroWrap = document.getElementById('heroSun');
    if (heroWrap) {
        // Two nested rotation layers so each transform stays independent and they
        // never fight each other:
        //   .hs-idle → a gentle, perpetual drift driven by CSS (keeps it alive)
        //   .hs-spin → deliberate single 360° turns driven by JS between stats
        var heroIdle = document.createElement('div'); heroIdle.className = 'hs-idle';
        var heroSpin = document.createElement('div'); heroSpin.className = 'hs-spin';
        heroIdle.appendChild(heroSpin);
        heroWrap.appendChild(heroIdle);

        var heroSvg = buildKurdishSun(heroSpin, { size: 520, color: '#F5C518' });
        heroSvg.style.width  = '100%';
        heroSvg.style.height = '100%';

        // Four stats — each pinned to a compass point around the sun.
        var HERO_STATS = [
            { num: '21',   lbl: 'Projects',    dir: 'n' },
            { num: '16',   lbl: 'Cities',      dir: 'e' },
            { num: '870K', lbl: 'm² Designed', dir: 's' },
            { num: '34',   lbl: 'Partners',    dir: 'w' },
        ];

        // Build an anchor → pill → connector beam for every stat. We split each
        // number into its target value and suffix (e.g. "870K" → 870 + "K") so the
        // number can count up when its pill is revealed.
        var heroAnchors = HERO_STATS.map(function (st) {
            var parts  = String(st.num).match(/^([\d.]+)(\D*)$/);
            var target = parts ? parts[1] : '0';
            var suffix = parts ? parts[2] : '';

            var anchor = document.createElement('div');
            anchor.className = 'hs-anchor hs-anchor--' + st.dir;
            anchor.innerHTML =
                '<div class="hs-stat">' +
                    '<span class="hs-stat__num" data-target="' + target + '" data-suffix="' + suffix + '">' + st.num + '</span>' +
                    '<span class="hs-stat__lbl">' + st.lbl + '</span>' +
                    '<div class="hs-stat__stem"></div>' +
                '</div>';
            heroWrap.appendChild(anchor);
            return anchor;
        });

        // Quick easing count-up for a single pill's number.
        function heroCountUp(anchor) {
            var el = anchor.querySelector('.hs-stat__num');
            if (!el) return;
            var target = parseFloat(el.getAttribute('data-target')) || 0;
            var suffix = el.getAttribute('data-suffix') || '';
            var dur = 900, start = null;
            (function frame(t) {
                if (start === null) start = t;
                var p = Math.min((t - start) / dur, 1);
                var eased = 1 - Math.pow(1 - p, 3);            // easeOutCubic
                el.textContent = Math.round(target * eased) + suffix;
                if (p < 1) requestAnimationFrame(frame);
                else el.textContent = target + suffix;
            })(performance.now());
        }

        // Respect users who ask for less motion: skip the whole show and just
        // present every stat, settled in place.
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduceMotion) {
            heroAnchors.forEach(function (a) { a.classList.add('is-active'); });
        } else {
            var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

            // Pause the cycle whenever the hero is scrolled out of view.
            var heroInView = true;
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (entries) {
                    heroInView = entries[0].isIntersecting;
                }, { threshold: 0.12 }).observe(heroWrap);
            }

            // One deliberate full turn of the sun.
            var spinAngle = 0;
            var SPIN_MS   = 1300;
            function heroSpinOnce() {
                spinAngle += 360;
                heroSpin.style.transition = 'transform ' + SPIN_MS + 'ms cubic-bezier(.45,.05,.25,1)';
                heroSpin.style.transform  = 'rotate(' + spinAngle + 'deg)';
            }

            // The choreography, looping forever:
            //   draw a beam out of the sun → reveal one stat → hold it so it can
            //   be read → retract it back into the sun → spin the sun once → next.
            (async function runHeroCycle() {
                await wait(700);                              // let the hero settle in
                var i = 0;
                while (true) {
                    while (!heroInView) { await wait(400); }  // stay idle off-screen

                    var anchor = heroAnchors[i];
                    anchor.classList.add('is-active');        // beam draws out, pill blooms
                    heroCountUp(anchor);                      // number ticks up
                    await wait(3000);                         // hold so it can be read

                    anchor.classList.remove('is-active');     // pill + beam retract into sun
                    await wait(640);

                    heroSpinOnce();                           // sun spins one full turn
                    await wait(SPIN_MS);

                    i = (i + 1) % heroAnchors.length;
                }
            })();
        }
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

    let mx = 0, my = 0, fx = 0, fy = 0;

    document.addEventListener('mousemove', e => {
        mx = e.clientX; my = e.clientY;
        cursor.style.left = mx + 'px';
        cursor.style.top  = my + 'px';
    });

    (function tick() {
        fx += (mx - fx) * 0.1;
        fy += (my - fy) * 0.1;
        follower.style.left = fx + 'px';
        follower.style.top  = fy + 'px';
        requestAnimationFrame(tick);
    })();

    const hoverTargets = 'a, button, .pcard, input, textarea, .filter-btn, .lang-toggle';
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

/* ---- Animated Stat Counters ---------------------------- */
(function initCounters() {
    const stats = document.querySelectorAll('.stat__num[data-target]');
    if (!stats.length) return;

    const io = new IntersectionObserver(entries => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const el     = e.target;
            const target = parseInt(el.dataset.target, 10);
            let current  = 0;
            const step   = target / 60;

            const timer = setInterval(() => {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = Math.floor(current);
            }, 22);

            io.unobserve(el);
        });
    }, { threshold: 0.5 });

    stats.forEach(el => io.observe(el));
})();

/* ---- Language Toggle ----------------------------------- */
(function initLang() {
    const btn = document.getElementById('langToggle');
    if (!btn) return;
    let isKu = false;

    btn.addEventListener('click', () => {
        isKu = !isKu;
        btn.textContent = isKu ? 'English' : 'کوردی';

        // direction
        document.documentElement.setAttribute('dir', isKu ? 'rtl' : 'ltr');
        document.documentElement.setAttribute('lang', isKu ? 'ku' : 'en');

        // swap text on all elements that carry data-en / data-ku
        document.querySelectorAll('[data-en][data-ku]').forEach(el => {
            const text = isKu ? el.dataset.ku : el.dataset.en;
            // headings may contain inner HTML (em tags)
            if (el.tagName === 'H2' && text.includes('<')) {
                el.innerHTML = text;
            } else {
                el.textContent = text;
            }
        });
    });
})();

/* ---- Contact Form -------------------------------------- */
(function initForm() {
    const form    = document.getElementById('contactForm');
    const success = document.getElementById('formSuccess');
    if (!form) return;

    form.addEventListener('submit', e => {
        e.preventDefault();
        const btn   = form.querySelector('.form-submit__label');
        btn.textContent = 'Sending…';

        // Simulate send (replace with real endpoint / EmailJS / Formspree)
        setTimeout(() => {
            btn.textContent = 'Send Message';
            form.reset();
            if (success) { success.hidden = false; }
            setTimeout(() => { if (success) success.hidden = true; }, 5000);
        }, 1200);
    });
})();

/* ---- Project Data -------------------------------------- */
const PROJECT_DATA = [
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


    const cursor   = document.getElementById('cursor');
    const follower = document.getElementById('cursorFollower');

    let currentProjIdx = 0;
    let currentImgIdx  = 0;
    let isOpen         = false;
    let allCards       = [];

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
        }, 200);
        thumbsEl.querySelectorAll('.od-thumb').forEach((t, i) => {
            t.classList.toggle('active', i === currentImgIdx);
        });
    }

    /* ---- Generate hashtags from project data ------------ */
    function buildTags(proj) {
        var tags = [];
        // Category
        tags.push('#' + proj.category.replace(/[\s-]+/g, ''));
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

        var statusClass = proj.status === 'Completed'          ? 's-done'
                        : proj.status === 'Under Construction' ? 's-build'
                        : 's-concept';

        // Status dot + badge
        var dotEl   = document.getElementById('overlayDot');
        var badgeEl = document.getElementById('overlayStatusBadge');
        dotEl.className   = 'od-dot '    + statusClass;
        badgeEl.textContent = proj.status;
        badgeEl.className = 'od-status-text ' + statusClass;

        // Num + name
        document.getElementById('overlayNum').textContent      = proj.num;
        document.getElementById('overlayName').textContent     = proj.name;

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
        document.getElementById('overlayDesc').textContent = proj.desc;

        // Counter
        counterEl.textContent = (index + 1) + ' / ' + PROJECT_DATA.length;

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
        }, 60);

        if (topbarEl) topbarEl.classList.remove('bar-solid');
    }

    /* ---- Stagger grid cards ------------------------------ */
    function staggerCards(selectedIdx) {
        allCards.forEach((card, i) => {
            card.classList.remove('proj-fall', 'proj-selected');
            if (i === selectedIdx) {
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
        const total = PROJECT_DATA.length;
        newIndex = ((newIndex % total) + total) % total;
        currentProjIdx = newIndex;
        // Briefly collapse open state so CSS stagger animations replay
        overlay.classList.remove('open');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                populateOverlay(newIndex);
                overlay.classList.add('open');
            });
        });
        allCards.forEach((card, i) => {
            card.classList.toggle('proj-selected', i === newIndex);
            card.classList.toggle('proj-fall',     i !== newIndex);
        });
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
    projPrevBtn && projPrevBtn.addEventListener('click', e => { e.stopPropagation(); switchProject(currentProjIdx - 1); });
    projNextBtn && projNextBtn.addEventListener('click', e => { e.stopPropagation(); switchProject(currentProjIdx + 1); });
    imgPrevBtn  && imgPrevBtn.addEventListener('click',  e => { e.stopPropagation(); setImg(currentImgIdx - 1); });
    imgNextBtn  && imgNextBtn.addEventListener('click',  e => { e.stopPropagation(); setImg(currentImgIdx + 1); });

    document.addEventListener('keydown', e => {
        if (!isOpen) return;
        if (e.key === 'Escape')     closeOverlay();
        if (e.key === 'ArrowLeft')  setImg(currentImgIdx - 1);
        if (e.key === 'ArrowRight') setImg(currentImgIdx + 1);
        if (e.key === 'ArrowUp')    { e.preventDefault(); switchProject(currentProjIdx - 1); }
        if (e.key === 'ArrowDown')  { e.preventDefault(); switchProject(currentProjIdx + 1); }
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

    /* ---- Filter + Search --------------------------------- */
    let activeFilter = 'all';
    let searchQuery  = '';

    function applyFilter() {
        if (window.closeProjectOverlay) window.closeProjectOverlay();

        let visibleCount = 0;
        cards.forEach(card => {
            const cat     = card.dataset.category || '';
            const name    = (card.dataset.name || '').toLowerCase();
            const catMatch  = activeFilter === 'all' || cat === activeFilter;
            const textMatch = !searchQuery || name.includes(searchQuery);
            const show = catMatch && textMatch;

            card.classList.toggle('pg-hidden', !show);
            if (show) visibleCount++;
        });

        if (emptyEl) emptyEl.hidden = visibleCount > 0;
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

    // Accurate coordinates for each project
    const COORDS = [
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

    // Init map — no default controls, scroll-zoom off (page scrolling otherwise hijacked)
    const map = L.map('projectMapEl', {
        zoomControl:     false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        attributionControl: true,
    }).setView([36.30, 43.80], 7); // centered on Erbil/Kurdistan region

    // CartoDB light nolabels — inverted in CSS to give black land, white roads
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 18,
    }).addTo(map);

    // Render the Kurdistan border from already-loaded GeoJSON data.
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

        map.fitBounds(borderLayer.getBounds(), { padding: [72, 56] });
    }

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

    // Build markers
    COORDS.forEach((coords, i) => {
        const proj = PROJECT_DATA[i];
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

        // Populate card
        cardImg.src          = proj.imgs[0];
        cardImg.alt          = proj.name;
        cardNum.textContent  = proj.num;
        cardName.textContent = proj.name;
        cardMeta.textContent = proj.location + '  ·  ' + proj.year + '  ·  ' + proj.typology;
        cardExcerpt.textContent = proj.desc;
        cardCta.onclick = () => {
            if (typeof window.openProjectOverlay === 'function') {
                window.openProjectOverlay(idx);
            }
        };

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
