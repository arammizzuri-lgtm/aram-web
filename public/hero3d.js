/* =========================================================
   Hero 3D — "The Ring of Proof"
   A cinematic Three.js stage behind the hero headline.

   Scene grammar (why each element exists):
   • CONTOUR MAP        — real topographic iso-lines generated from a
     noise field (marching squares): the Kurdistan highlands drawn the
     way an architect draws land. Near-black, so light OWNS the frame.
   • GLASS STAT RING    — the practice's five figures as glass cards
     standing on the map in an infinite carousel. Proof you can touch:
     it drifts on its own, you can grab and spin it, and it keeps
     your momentum (inertia) — the site answers the hand.
   • LIGHT RIPPLES      — each card pours light onto the contour lines
     beneath it; ripples radiate outward and intensify with spin
     speed. Data literally illuminates the territory.
   • SKYLINE            — dim wireframe towers on the horizon ring:
     built work standing behind the numbers that measure it.
   • SUN + DUST         — the Kurdish-sun brand mark as light source,
     golden-hour air. Atmosphere, not decoration.
   • CAMERA             — slow crane ellipse + lerped pointer parallax;
     scrolling away cranes the camera up: you LEAVE the site the way
     a drone leaves a masterplan, straight into the projects map
     section (which is literally a map — the metaphor hands over).

   Usability guardrails:
   • Stats stay in the DOM (visually hidden) for screen readers; the
     canvas is aria-hidden inside .hero__bg.
   • prefers-reduced-motion → static composed frame; dragging still
     re-renders (user-initiated motion only), no auto-spin, no ripple.
   • No WebGL / no three.js → CSS aurora background remains untouched.
   • DPR capped, loop pauses off-screen / hidden tab.
   ========================================================= */
(function heroScene() {
    'use strict';

    var canvas = document.getElementById('heroCanvas');
    if (!canvas || typeof THREE === 'undefined') return;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
    } catch (e) { return; } // no WebGL → CSS background remains
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));

    var hero = canvas.closest('.hero') || document.body;
    hero.classList.add('hero--3d'); // CSS dims the 2D grid + disables text selection

    var scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x070707, 0.024);

    var camera = new THREE.PerspectiveCamera(38, 1, 0.1, 220);
    var CAM_BASE = { x: 0, y: 7.4, z: 27 };
    camera.position.set(CAM_BASE.x, CAM_BASE.y, CAM_BASE.z);

    var GOLD = new THREE.Color(0xf5c518);

    /* ---------- deterministic value noise ---------- */
    function hash(x, z) {
        var h = Math.sin(x * 127.1 + z * 311.7) * 43758.5453;
        return h - Math.floor(h);
    }
    function noise(x, z) {
        var xi = Math.floor(x), zi = Math.floor(z);
        var xf = x - xi, zf = z - zi;
        var u = xf * xf * (3 - 2 * xf), v = zf * zf * (3 - 2 * zf);
        var a = hash(xi, zi), b = hash(xi + 1, zi), c = hash(xi, zi + 1), d = hash(xi + 1, zi + 1);
        return a + (b - a) * u + (c - a) * v + (a - b - c + d) * u * v;
    }
    function terrainHeight(x, z) {
        // broad ridges + fine detail, flattened into a central plain the
        // ring stands on — the "city plain ringed by highlands" of Erbil
        var h = noise(x * 0.055, z * 0.055) * 4.2 + noise(x * 0.16, z * 0.16) * 1.1;
        var plain = Math.min(1, Math.sqrt(x * x + z * z) / 18);
        return h * plain * plain;
    }

    /* ---------- contour map: marching squares over the noise field ----------
       Iso-lines at fixed heights — a topographic survey of the scene.
       Static geometry; ALL life comes from the ripple shader below. */
    var CARD_COUNT = 5;
    var contourMat = new THREE.ShaderMaterial({
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        uniforms: {
            uTime:      { value: 0 },
            uAgitation: { value: 0 },                      // spin speed → ripple intensity
            uIntro:     { value: 0 },                      // 0→1: the sheet "inks in" on load
            uCards:     { value: new Array(CARD_COUNT).fill(0).map(function () { return new THREE.Vector2(0, 0); }) }
        },
        vertexShader: [
            'varying vec3 vPos;',
            'void main() {',
            '  vPos = position;',
            '  gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);',
            '}'
        ].join('\n'),
        fragmentShader: [
            'uniform float uTime;',
            'uniform float uAgitation;',
            'uniform float uIntro;',
            'uniform vec2 uCards[' + CARD_COUNT + '];',
            'varying vec3 vPos;',
            'void main() {',
            // each card is a light source; waves radiate from it across the lines
            '  float glow = 0.0;',
            '  for (int i = 0; i < ' + CARD_COUNT + '; i++) {',
            '    float d = distance(vPos.xz, uCards[i]);',
            '    float wave = 0.5 + 0.5 * sin(d * 2.3 - uTime * 2.6);',
            '    glow += exp(-d * 0.30) * wave;',
            '  }',
            '  glow *= (0.55 + uAgitation);',
            '  vec3 col = vec3(0.96, 0.77, 0.09) * (0.10 + 1.2 * glow);',
            '  float a = 0.15 + min(glow, 1.0) * 0.6;',
            // draw-in: the map inks outward from the centre of the sheet
            '  float reveal = smoothstep(length(vPos.xz), length(vPos.xz) + 9.0, uIntro * 72.0);',
            '  gl_FragColor = vec4(col, a * reveal);',
            '}'
        ].join('\n')
    });

    (function buildContours() {
        var LEVELS = [0.3, 0.7, 1.15, 1.65, 2.2, 2.8, 3.45, 4.15];
        var N = 104, EXT = 52;                      // grid resolution / half-extent
        var step = (EXT * 2) / N;
        var verts = [];
        // sample field once
        var field = new Float32Array((N + 1) * (N + 1));
        for (var i = 0; i <= N; i++)
            for (var j = 0; j <= N; j++)
                field[i * (N + 1) + j] = terrainHeight(-EXT + i * step, -EXT + j * step);

        function lerpPt(x0, z0, x1, z1, h0, h1, L) {
            var t = (L - h0) / (h1 - h0);
            return [x0 + (x1 - x0) * t, L + 0.02, z0 + (z1 - z0) * t];
        }
        LEVELS.forEach(function (L) {
            for (var i = 0; i < N; i++) {
                for (var j = 0; j < N; j++) {
                    var x0 = -EXT + i * step, z0 = -EXT + j * step;
                    var x1 = x0 + step,       z1 = z0 + step;
                    var h00 = field[i * (N + 1) + j],       h10 = field[(i + 1) * (N + 1) + j];
                    var h01 = field[i * (N + 1) + j + 1],   h11 = field[(i + 1) * (N + 1) + j + 1];
                    var pts = [];
                    if ((h00 < L) !== (h10 < L)) pts.push(lerpPt(x0, z0, x1, z0, h00, h10, L));
                    if ((h10 < L) !== (h11 < L)) pts.push(lerpPt(x1, z0, x1, z1, h10, h11, L));
                    if ((h01 < L) !== (h11 < L)) pts.push(lerpPt(x0, z1, x1, z1, h01, h11, L));
                    if ((h00 < L) !== (h01 < L)) pts.push(lerpPt(x0, z0, x0, z1, h00, h01, L));
                    if (pts.length >= 2) { verts.push(pts[0], pts[1]); }
                    if (pts.length === 4) { verts.push(pts[2], pts[3]); }
                }
            }
        });
        var pos = new Float32Array(verts.length * 3);
        for (var v = 0; v < verts.length; v++) {
            pos[v * 3] = verts[v][0]; pos[v * 3 + 1] = verts[v][1]; pos[v * 3 + 2] = verts[v][2];
        }
        var geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        scene.add(new THREE.LineSegments(geo, contourMat));
    })();

    /* ---------- skyline: dim towers on the horizon ring ---------- */
    var towers = [];
    (function buildSkyline() {
        var group = new THREE.Group();
        for (var gx = -24; gx <= 24; gx += 2.8) {
            for (var gz = -22; gz <= 2; gz += 2.8) {
                var jx = gx + (hash(gx, gz) - 0.5) * 2;
                var jz = gz + (hash(gz, gx) - 0.5) * 2;
                var r = Math.sqrt(jx * jx + jz * jz);
                if (r < 14 || r > 26) continue;             // horizon band — clear of the ring
                if (hash(jx * 3, jz * 3) < 0.5) continue;
                var h = 1.5 + hash(jx, jz) * 6;
                var w = 0.6 + hash(jz, jx) * 0.9;
                var geo = new THREE.BoxGeometry(w, h, w);
                geo.translate(0, h / 2, 0);
                var line = new THREE.LineSegments(
                    new THREE.EdgesGeometry(geo),
                    new THREE.LineBasicMaterial({ color: GOLD, transparent: true, opacity: 0.10 + hash(jx, h) * 0.12 })
                );
                line.position.set(jx, terrainHeight(jx, jz) * 0.3, jz);
                line.scale.y = reduceMotion ? 1 : 0.0001;
                group.add(line);
                towers.push({ mesh: line, delay: 0.5 + hash(jx, jz) * 1.1, dur: 1.2 });
            }
        }
        scene.add(group);
    })();

    /* ---------- sun + dust ---------- */
    function makeGlowTexture() {
        var c = document.createElement('canvas');
        c.width = c.height = 256;
        var g = c.getContext('2d');
        var grad = g.createRadialGradient(128, 128, 0, 128, 128, 128);
        grad.addColorStop(0, 'rgba(255,224,122,.95)');
        grad.addColorStop(0.25, 'rgba(245,197,24,.55)');
        grad.addColorStop(0.6, 'rgba(245,197,24,.12)');
        grad.addColorStop(1, 'rgba(245,197,24,0)');
        g.fillStyle = grad;
        g.fillRect(0, 0, 256, 256);
        return new THREE.CanvasTexture(c);
    }
    var glowTex = makeGlowTexture();
    var sun = new THREE.Sprite(new THREE.SpriteMaterial({
        map: glowTex, transparent: true, opacity: 0.85, depthWrite: false, blending: THREE.AdditiveBlending
    }));
    sun.scale.set(28, 28, 1);
    sun.position.set(0, 4.8, -18);
    scene.add(sun);

    var MOTES = 240;
    var moteGeo = new THREE.BufferGeometry();
    var mp = new Float32Array(MOTES * 3);
    for (var m = 0; m < MOTES; m++) {
        mp[m * 3] = (hash(m, 1) - 0.5) * 70;
        mp[m * 3 + 1] = hash(m, 2) * 16 + 0.5;
        mp[m * 3 + 2] = (hash(m, 3) - 0.5) * 60;
    }
    moteGeo.setAttribute('position', new THREE.BufferAttribute(mp, 3));
    scene.add(new THREE.Points(moteGeo, new THREE.PointsMaterial({
        color: GOLD, size: 0.09, transparent: true, opacity: 0.5, depthWrite: false, blending: THREE.AdditiveBlending
    })));

    /* ---------- the glass stat ring ----------
       Data source is the visually-hidden DOM list (#heroStatsData) so
       Blade stays the single source of truth and screen readers are
       served the same numbers. */
    // mid-ground ring of monumental TYPE — no card slabs, just heavy gold
    // numerals standing on the map like cut brass letters on a model base;
    // wide planes because the type is wide. On phones the ring tightens
    // and the type shrinks so it doesn't crowd the narrow frame
    // (RING_R + card scale are set responsively in resize()).
    var RING_R = 9.0, CARD_Y = 1.62, CARD_W = 3.1, CARD_H = 2.1;
    var TAU = Math.PI * 2;
    var ring = { theta: 0, omega: 0, idle: reduceMotion ? 0 : 0.055, dragging: false, agitation: 0 };
    window.__heroRing = ring; // test/debug handle

    var statsData = [];
    (function readStats() {
        var host = document.getElementById('heroStatsData');
        if (!host) return;
        Array.prototype.forEach.call(host.children, function (el) {
            statsData.push({
                value:  parseInt(el.getAttribute('data-value'), 10) || 0,
                suffix: el.getAttribute('data-suffix') || '',
                en:     el.getAttribute('data-en') || '',
                ku:     el.getAttribute('data-ku') || ''
            });
        });
    })();

    var cards = [];
    function drawCard(card, progress) {
        var ctx = card.ctx, W = card.cv.width, H = card.cv.height;
        var isKu = document.documentElement.getAttribute('dir') === 'rtl';
        ctx.clearRect(0, 0, W, H);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // BULK numeral — ultra-heavy sans, near-white → gold, with a warm
        // halo so the type reads instantly against the black map
        var num = Math.round(card.stat.value * progress).toLocaleString('en-US') + card.stat.suffix;
        // size against the FINAL string (not the mid-count one) so the type
        // never jitters, and shrink-to-fit so wide values like "902K" are
        // never cut — 46px margin each side also keeps the halo on-canvas
        var finalNum = Math.round(card.stat.value).toLocaleString('en-US') + card.stat.suffix;
        var size = finalNum.length <= 2 ? 300 : (finalNum.length <= 4 ? 250 : 200);
        var maxW = W - 92;
        ctx.font = '900 ' + size + 'px Inter, Arial Black, sans-serif';
        while (ctx.measureText(finalNum).width > maxW && size > 80) {
            size -= 8;
            ctx.font = '900 ' + size + 'px Inter, Arial Black, sans-serif';
        }
        var ng = ctx.createLinearGradient(0, H * 0.12, 0, H * 0.66);
        ng.addColorStop(0, 'rgba(255,255,255,1)');
        ng.addColorStop(0.5, 'rgba(255,236,170,0.98)');
        ng.addColorStop(1, 'rgba(245,197,24,0.98)');
        ctx.save();
        ctx.shadowColor = 'rgba(245,197,24,0.45)';
        ctx.shadowBlur = 42;
        ctx.fillStyle = ng;
        ctx.fillText(num, W / 2, H * 0.38);
        ctx.restore();

        // dimension line with end ticks — the architect's mark, now the
        // pedestal the type stands on
        ctx.strokeStyle = 'rgba(245,197,24,0.85)';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(W * 0.24, H * 0.72); ctx.lineTo(W * 0.76, H * 0.72);
        ctx.moveTo(W * 0.24, H * 0.70); ctx.lineTo(W * 0.24, H * 0.74);
        ctx.moveTo(W * 0.76, H * 0.70); ctx.lineTo(W * 0.76, H * 0.74);
        ctx.stroke();

        // label — small, wide, unmistakable
        ctx.fillStyle = 'rgba(255,255,255,0.88)';
        if (isKu) {
            ctx.font = '500 52px Speda, Vazirmatn, sans-serif';
            ctx.fillText(card.stat.ku, W / 2, H * 0.87);
        } else {
            ctx.font = '700 36px Inter, sans-serif';
            try { ctx.letterSpacing = '9px'; } catch (e) {}
            ctx.fillText(card.stat.en.toUpperCase(), W / 2, H * 0.865);
            try { ctx.letterSpacing = '0px'; } catch (e) {}
        }
        card.tex.needsUpdate = true;
    }

    // 2.5D extrusion: each stat is a stack of type-planes stepping back
    // toward the ring centre, shaded bright→bronze. All layers share one
    // texture, so the count-up and language redraw stay a single canvas
    // draw — the depth is free.
    var DEPTH_LAYERS = 6, DEPTH_GAP = 0.055;
    var cardGeo = new THREE.PlaneGeometry(CARD_W, CARD_H); // shared by every layer
    (function buildCards() {
        statsData.forEach(function (stat, i) {
            var cv = document.createElement('canvas');
            cv.width = 640; cv.height = 432; // wide: matches the type-plane aspect
            var tex = new THREE.CanvasTexture(cv);
            tex.anisotropy = renderer.capabilities.getMaxAnisotropy ? Math.min(4, renderer.capabilities.getMaxAnisotropy()) : 1;
            var group = new THREE.Group();
            var mats = [];
            for (var L = 0; L < DEPTH_LAYERS; L++) {
                var mat = new THREE.MeshBasicMaterial({ map: tex, transparent: true, side: THREE.DoubleSide, depthWrite: false });
                if (L > 0) {
                    // extruded side: bronze darkening with depth
                    var k = 1 - L / DEPTH_LAYERS;
                    mat.color.setRGB(0.14 + 0.52 * k, 0.10 + 0.40 * k, 0.02 + 0.10 * k);
                }
                var m = new THREE.Mesh(cardGeo, mat);
                m.position.z = -DEPTH_GAP * L;      // step inward: the face sits proud
                m.renderOrder = DEPTH_LAYERS - L;   // face paints last, over its sides
                group.add(m);
                mats.push(mat);
            }
            var glow = new THREE.Sprite(new THREE.SpriteMaterial({
                map: glowTex, transparent: true, opacity: 0.25, depthWrite: false, blending: THREE.AdditiveBlending
            }));
            glow.scale.set(4.0, 4.0, 1);
            scene.add(group);
            scene.add(glow);
            var card = {
                stat: stat, cv: cv, ctx: cv.getContext('2d'), tex: tex, group: group, mats: mats, glow: glow,
                angle: (i / statsData.length) * TAU,
                delay: 0.7 + i * 0.13, dur: 1.35, lastNum: -1
            };
            drawCard(card, reduceMotion ? 1 : 0);
            cards.push(card);
        });
    })();

    // crisp text once the display faces (Playfair/Speda) finish loading,
    // and on every language toggle
    function redrawAll() { cards.forEach(function (c) { drawCard(c, 1); }); if (reduceMotion) renderOnce(); }
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (introDone()) redrawAll(); else fontsLoaded = true; });
    var fontsLoaded = false;
    document.addEventListener('langchange', redrawAll);
    function introDone() { return cards.every(function (c) { return c.lastNum === c.stat.value; }); }

    /* ---------- drag / inertia ----------
       Grab anywhere on the hero that isn't a link or control. Threshold
       keeps clicks intact; momentum keeps the gesture alive after
       release; the ring never stops dead — it settles into idle drift. */
    var drag = null;
    hero.addEventListener('pointerdown', function (e) {
        if (e.target.closest('a, button, input, textarea, select, .nav')) return;
        drag = { x: e.clientX, lastX: e.clientX, lastT: performance.now(), vel: 0, moved: false };
    });
    window.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var dx = e.clientX - drag.lastX;
        if (Math.abs(e.clientX - drag.x) > 5) { drag.moved = true; ring.dragging = true; }
        if (drag.moved) {
            var now = performance.now();
            var dt = Math.max(8, now - drag.lastT);
            ring.theta += dx * 0.0042;
            drag.vel = 0.8 * drag.vel + 0.2 * (dx * 0.0042 * (1000 / dt) / 60);
            drag.lastT = now;
            if (reduceMotion) renderOnce();
        }
        drag.lastX = e.clientX;
    }, { passive: true });
    window.addEventListener('pointerup', function () {
        if (drag && drag.moved) ring.omega = drag.vel * 60; // per-second angular velocity
        drag = null;
        ring.dragging = false;
    });

    /* ---------- sizing ---------- */
    function resize() {
        var w = hero.clientWidth, h = hero.clientHeight;
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        // phones: tighter ring, smaller type, standing lower so the front
        // numeral clears the CTA button
        var mobile = w <= 768;
        RING_R = mobile ? 7.4 : 9.0;
        CARD_Y = mobile ? 1.15 : 1.62;
        var cs = mobile ? 0.72 : 1;
        cards.forEach(function (c) {
            c.group.scale.setScalar(cs);
            c.glow.scale.set(4.0 * cs, 4.0 * cs, 1);
        });
        if (reduceMotion) renderOnce();
    }
    resize();
    window.addEventListener('resize', resize);

    /* ---------- pointer parallax ---------- */
    var mouse = { x: 0, y: 0 }, eased = { x: 0, y: 0 };
    if (!reduceMotion) {
        window.addEventListener('pointermove', function (e) {
            if (drag && drag.moved) return; // dragging owns the pointer
            mouse.x = (e.clientX / window.innerWidth) * 2 - 1;
            mouse.y = (e.clientY / window.innerHeight) * 2 - 1;
        }, { passive: true });
    }

    var running = true, inView = true;
    document.addEventListener('visibilitychange', function () { running = !document.hidden; });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) { inView = entries[0].isIntersecting; }, { threshold: 0.02 })
            .observe(hero);
    }

    function easeOutCubic(p) { return 1 - Math.pow(1 - p, 3); }

    /* ---------- shared per-frame scene update ---------- */
    var t0 = performance.now(), lastT = t0;
    function updateScene(now) {
        var t = (now - t0) / 1000;
        var dt = Math.min(0.05, (now - lastT) / 1000);
        lastT = now;

        // towers rise once — the skyline builds itself behind the data
        for (var i = 0; i < towers.length; i++) {
            var tw = towers[i];
            if (tw.done) continue;
            var p = (t - tw.delay) / tw.dur;
            if (p >= 1) { tw.mesh.scale.y = 1; tw.done = true; }
            else if (p > 0) tw.mesh.scale.y = Math.max(0.0001, easeOutCubic(p));
        }

        // ring physics: inertia decays toward the idle drift, never to zero —
        // the carousel is always quietly alive
        if (!ring.dragging) {
            ring.omega *= 0.955;
            var target = ring.idle;
            ring.theta += (ring.omega + target) * dt;
        }
        // agitation follows spin speed → ripples surge as you spin
        var speed = Math.abs(ring.omega) + (ring.dragging ? 0.9 : 0);
        ring.agitation += (Math.min(1.4, speed * 1.6) - ring.agitation) * 0.06;

        // place cards on the ring; front cards solid, rear cards ghosts
        for (var c = 0; c < cards.length; c++) {
            var card = cards[c];
            var a = card.angle + ring.theta;
            var cx = Math.sin(a) * RING_R, cz = Math.cos(a) * RING_R;
            card.group.position.set(cx, CARD_Y + Math.sin(t * 0.7 + c) * 0.06, cz); // gentle float: glass, not stone
            card.group.rotation.y = a;                                              // face outward (layers extrude inward)
            var f = (cz / RING_R + 1) / 2;                                          // 1 = front, 0 = back
            // pure type needs a harder depth falloff: rear numerals become
            // faint ghosts instead of legible mirrored text
            var fade = 0.10 + 0.90 * f * f;
            for (var L3 = 0; L3 < card.mats.length; L3++) card.mats[L3].opacity = fade;
            card.glow.position.set(cx, CARD_Y - 0.5, cz);
            card.glow.material.opacity = 0.05 + 0.24 * f;
            contourMat.uniforms.uCards.value[c].set(cx, cz);

            // intro count-up on the card face itself
            var pr = Math.min(1, Math.max(0, (t - card.delay) / card.dur));
            var shown = Math.round(card.stat.value * easeOutCubic(pr));
            if (shown !== card.lastNum) {
                card.lastNum = shown;
                drawCard(card, easeOutCubic(pr));
                if (pr >= 1 && fontsLoaded) { fontsLoaded = false; redrawAll(); }
            }
        }
        contourMat.uniforms.uTime.value = t;
        contourMat.uniforms.uAgitation.value = ring.agitation;
        contourMat.uniforms.uIntro.value = reduceMotion ? 1 : Math.min(1, easeOutCubic(Math.min(1, t / 2.2)));

        // dust drifts up
        var mpos = moteGeo.attributes.position;
        for (var d = 0; d < MOTES; d++) {
            var y = mpos.getY(d) + 0.006;
            mpos.setY(d, y > 17 ? 0.4 : y);
        }
        mpos.needsUpdate = true;

        // camera: crane ellipse + eased parallax + scroll crane-up
        eased.x += (mouse.x - eased.x) * 0.035;
        eased.y += (mouse.y - eased.y) * 0.035;
        var sy = Math.min(1, (window.scrollY || 0) / Math.max(1, hero.clientHeight));
        camera.position.x = CAM_BASE.x + Math.sin(t * 0.07) * 2.0 + eased.x * 1.5;
        camera.position.y = CAM_BASE.y + Math.sin(t * 0.11) * 0.5 - eased.y * 0.8 + sy * 6;
        camera.position.z = CAM_BASE.z + Math.cos(t * 0.07) * 1.1;
        camera.lookAt(0, 2.6 - sy * 1.5, 0);

        sun.material.opacity = 0.82 + Math.sin(t * 0.8) * 0.06;
    }

    function renderOnce() {
        updateScene(performance.now());
        renderer.render(scene, camera);
    }

    if (reduceMotion) {
        // one composed still; dragging re-renders frame by frame
        cards.forEach(function (c) { c.lastNum = c.stat.value; drawCard(c, 1); });
        renderOnce();
    } else {
        (function frame(now) {
            requestAnimationFrame(frame);
            if (!running || !inView) { lastT = now; return; }
            updateScene(now);
            renderer.render(scene, camera);
        })(performance.now());
    }
})();
