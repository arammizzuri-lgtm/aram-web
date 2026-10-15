/* =========================================================
   ARAM MIZURI ARCHITECTURE — voices.js
   Clients in their own voices (partials/client-voice): a pencil portrait
   that draws itself — once as it comes into view, and again in step with
   the client's voice note while it plays — a player in the shape of a
   voice note, and the quote lighting up word by word as it is heard.
   Loaded on the homepage and the project pages.
   ========================================================= */
(function () {
    'use strict';

    const cards = Array.from(document.querySelectorAll('[data-cv]'));
    if (!cards.length) return;

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';
    const pick = (el, name) => el.dataset[name + (isKu() ? 'Ku' : 'En')] || el.dataset[name + 'En'] || '';
    const clamp01 = v => (v < 0 ? 0 : v > 1 ? 1 : v);
    const smooth = t => t * t * (3 - 2 * t);

    // On the homepage the quotes take turns in one place (engage.js): only
    // the one on show counts as visible.
    const onShow = card => {
        const quote = card.closest('.quote');
        return !quote || quote.classList.contains('is-active');
    };

    /* ---- The drawing ------------------------------------------
       The drawing's pixels go down in an order a hand might follow: the
       darkest lines first (the outline and the features), the shading
       last, starting at the face and working outwards region by region.
       Every pixel gets a moment between 0 and 1, spread evenly over the
       drawing, and render(p) shows the pixels whose moment has come — each
       fading in over a short stretch, so the lines grow rather than pop. */
    function Drawing(card) {
        const img = card.querySelector('.cv__sketch');
        const canvas = card.querySelector('.cv__draw');
        if (!img || !canvas || !canvas.getContext) return null;
        const ctx = canvas.getContext('2d');
        const n = canvas.width;
        let alpha, order, moment, out, ready = null, frame = 0, shown = -1;

        function prepare() {
            const off = document.createElement('canvas');
            off.width = off.height = n;
            const octx = off.getContext('2d', { willReadFrequently: true });
            octx.drawImage(img, 0, 0, n, n);
            const src = octx.getImageData(0, 0, n, n);
            const px = src.data;

            alpha = new Uint8ClampedArray(n * n);
            const inked = [];
            for (let i = 0; i < n * n; i++) {
                alpha[i] = px[i * 4 + 3];
                px[i * 4 + 3] = 0;                     // the drawing starts as blank paper
                if (alpha[i] > 3) inked.push(i);
            }
            order = Uint32Array.from(inked);
            out = src;

            // regions: smooth value noise over a coarse grid of random heights
            const G = 6, grid = [];
            for (let k = 0; k < (G + 1) * (G + 1); k++) grid.push(Math.random());
            const at = (gx, gy) => grid[gy * (G + 1) + gx];

            const raw = new Float32Array(order.length);
            let lo = Infinity, hi = -Infinity;
            for (let k = 0; k < order.length; k++) {
                const i = order[k], x = i % n, y = (i / n) | 0;
                const dx = x / n - 0.5, dy = y / n - 0.42;
                const fromFace = Math.min(1, Math.sqrt(dx * dx + dy * dy) / 0.6);
                const gx = x / n * G, gy = y / n * G, x0 = gx | 0, y0 = gy | 0;
                const fx = smooth(gx - x0), fy = smooth(gy - y0);
                const top = at(x0, y0) + (at(x0 + 1, y0) - at(x0, y0)) * fx;
                const bottom = at(x0, y0 + 1) + (at(x0 + 1, y0 + 1) - at(x0, y0 + 1)) * fx;
                const region = top + (bottom - top) * fy;
                const v = 0.5 * (1 - alpha[i] / 255) + 0.28 * fromFace + 0.22 * region;
                raw[k] = v;
                if (v < lo) lo = v;
                if (v > hi) hi = v;
            }

            // spread evenly: each pixel's moment is its rank among them all
            const B = 1024, hist = new Uint32Array(B), span = (hi - lo) || 1;
            const bin = v => Math.min(B - 1, ((v - lo) / span * B) | 0);
            for (let k = 0; k < raw.length; k++) hist[bin(raw[k])]++;
            const before = new Float32Array(B);
            for (let b = 1; b < B; b++) before[b] = before[b - 1] + hist[b - 1];
            moment = new Float32Array(raw.length);
            for (let k = 0; k < raw.length; k++) moment[k] = before[bin(raw[k])] / raw.length;
        }

        function render(p) {
            p = clamp01(p);
            if (Math.abs(p - shown) < 0.002 && p > 0 && p < 1) return;
            shown = p;
            const data = out.data, soft = 0.07, edge = p * (1 + soft);
            for (let k = 0; k < order.length; k++) {
                const i = order[k], f = (edge - moment[k]) / soft;
                data[i * 4 + 3] = f <= 0 ? 0 : f >= 1 ? alpha[i] : alpha[i] * f;
            }
            ctx.putImageData(out, 0, 0);
        }

        // Ready once the drawing's image has arrived; null if it never can be
        // (the canvas is then never shown, and the plain image stays).
        function whenReady() {
            if (!ready) {
                ready = (img.decode ? img.decode() : Promise.resolve())
                    .then(() => { prepare(); render(still ? 1 : 0); card.classList.add('is-ready'); return true; })
                    .catch(() => false);
            }
            return ready;
        }

        return {
            whenReady,
            // draws itself over a few seconds — unless the voice is drawing it
            sketch() {
                if (still) return;
                whenReady().then(ok => {
                    if (!ok || card.classList.contains('is-playing')) return;
                    cancelAnimationFrame(frame);
                    const t0 = performance.now(), length = 3400;
                    const step = now => {
                        const t = clamp01((now - t0) / length);
                        render(1 - Math.pow(1 - t, 1.7));
                        if (t < 1) frame = requestAnimationFrame(step);
                    };
                    frame = requestAnimationFrame(step);
                });
            },
            // the voice's progress, from 0 to 1, drawn
            follow(p) {
                cancelAnimationFrame(frame);
                if (alpha && !still) render(p);
            },
        };
    }

    /* ---- The quote, word by word ---------------------------------
       The words are wrapped as they are needed — the homepage's language
       toggle replaces the text, and with it the wrapping. A word lights up
       when the voice is as far through as the word is through the quote:
       not a transcript's timing, but close enough to read along by. */
    function Words(card) {
        const figure = card.closest('figure');
        const quote = figure && figure.querySelector('blockquote');
        if (!quote) return null;
        let words = [];

        function wrap() {
            if (words.length && words[0].isConnected) return;
            const text = quote.textContent;
            const total = text.replace(/\s+/g, '').length || 1;
            let seen = 0;
            quote.textContent = '';
            words = [];
            text.split(/(\s+)/).forEach(part => {
                if (!part) return;
                if (/^\s+$/.test(part)) { quote.appendChild(document.createTextNode(part)); return; }
                const span = document.createElement('span');
                span.className = 'cv-w';
                span.textContent = part;
                span.dataset.at = String(seen / total);
                seen += part.length;
                quote.appendChild(span);
                words.push(span);
            });
        }

        return {
            show(p) {
                wrap();
                quote.classList.add('cv-reading');
                words.forEach(w => w.classList.toggle('is-said', +w.dataset.at <= p));
            },
            done() { quote.classList.remove('cv-reading'); },
        };
    }

    /* ---- The player ----------------------------------------------
       Shown only where this browser says it can play the file. The bars
       start as a likely shape; the first play reads the real one from the
       recording itself. */
    let playing = null;    // the one player heard at a time

    function Player(card, drawing, words) {
        const box = card.querySelector('.cv__player');
        if (!box) return null;
        const probe = document.createElement('audio');
        if (!probe.canPlayType || (box.dataset.type && !probe.canPlayType(box.dataset.type))) return null;

        const btn = box.querySelector('.cv__play');
        const wave = box.querySelector('.cv__wave');
        const time = box.querySelector('.cv__time');
        const audio = new Audio();
        audio.preload = 'none';
        audio.src = box.dataset.src;
        let length = 0, frame = 0, shaped = false;

        // a likely shape until the real one is known: the same every visit
        let seed = (+box.dataset.seed || 1) * 9301 + 49297;
        const rand = () => ((seed = (seed * 9301 + 49297) % 233280) / 233280);
        const bars = [];
        for (let b = 0; b < 36; b++) {
            const bar = document.createElement('span');
            bar.className = 'cv__bar';
            bar.style.setProperty('--h', (0.22 + rand() * 0.7).toFixed(2));
            wave.appendChild(bar);
            bars.push(bar);
        }

        const fmt = s => { s = Math.max(0, Math.round(s)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
        const knownLength = () => (isFinite(audio.duration) && audio.duration > 0 ? audio.duration : length);

        function label() {
            btn.setAttribute('aria-label', pick(btn, audio.paused ? 'play' : 'pause'));
            wave.setAttribute('aria-label', pick(wave, 'label'));
        }

        function show(p) {
            const lit = Math.round(p * bars.length);
            bars.forEach((bar, b) => bar.classList.toggle('is-played', b < lit));
            wave.setAttribute('aria-valuenow', String(Math.round(p * 100)));
        }

        function tick() {
            const len = knownLength();
            const p = len ? clamp01(audio.currentTime / len) : 0;
            show(p);
            time.textContent = fmt(audio.currentTime);
            // the drawing is finished a little before the voice is
            if (drawing) drawing.follow(p / 0.92);
            if (words) words.show(p);
            if (!audio.paused) frame = requestAnimationFrame(tick);
        }

        function shape() {
            if (shaped) return;
            shaped = true;
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC || !window.fetch) return;
            fetch(box.dataset.src, { credentials: 'same-origin' })
                .then(r => r.arrayBuffer())
                .then(buf => new Promise((resolve, reject) => {
                    const ac = new AC();
                    ac.decodeAudioData(buf, decoded => { if (ac.close) ac.close(); resolve(decoded); },
                        err => { if (ac.close) ac.close(); reject(err); });
                }))
                .then(decoded => {
                    length = decoded.duration;
                    const data = decoded.getChannelData(0);
                    const per = Math.floor(data.length / bars.length) || 1;
                    const step = Math.max(1, Math.floor(per / 400));
                    const levels = bars.map((_, b) => {
                        let sum = 0, count = 0;
                        for (let i = b * per; i < (b + 1) * per && i < data.length; i += step) { sum += data[i] * data[i]; count++; }
                        return Math.sqrt(sum / (count || 1));
                    });
                    const top = Math.max.apply(null, levels) || 1;
                    levels.forEach((v, b) => bars[b].style.setProperty('--h', (0.14 + 0.86 * Math.pow(v / top, 0.8)).toFixed(2)));
                })
                .catch(() => { /* the likely shape stays */ });
        }

        function seek(p) {
            const len = knownLength();
            if (!len) return;
            audio.currentTime = clamp01(p) * len;
            if (audio.paused) {
                show(clamp01(p));
                time.textContent = fmt(audio.currentTime);
                if (drawing) drawing.follow(clamp01(p) / 0.92);
                if (words) words.show(clamp01(p));
            }
        }

        const api = {
            pause() { audio.pause(); },
            warm() {
                // enough to know the length, without fetching the whole note
                if (audio.preload === 'none') { audio.preload = 'metadata'; audio.load(); }
            },
        };

        btn.addEventListener('click', () => {
            if (!audio.paused) { audio.pause(); return; }
            if (playing && playing !== api) playing.pause();
            playing = api;
            // straight away, inside the tap: Safari plays nothing started later.
            // The drawing joins in as soon as it is ready.
            const started = audio.play();
            if (started && started.catch) started.catch(() => {});
            if (drawing) drawing.whenReady();
            shape();
        });

        audio.addEventListener('loadedmetadata', () => {
            if (audio.paused && audio.currentTime === 0 && knownLength()) time.textContent = fmt(knownLength());
        });
        // The homepage's quotes announce themselves as they turn over; while a
        // voice plays, the words lighting up must not be read out over it.
        const announcer = card.closest('[aria-live]');
        const quiet = on => {
            if (!announcer) return;
            if (on && announcer.getAttribute('aria-live') !== 'off') {
                announcer.dataset.live = announcer.getAttribute('aria-live');
                announcer.setAttribute('aria-live', 'off');
            } else if (!on && announcer.dataset.live) {
                announcer.setAttribute('aria-live', announcer.dataset.live);
                delete announcer.dataset.live;
            }
        };

        audio.addEventListener('play', () => {
            card.classList.add('is-playing');
            quiet(true);
            label();
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(tick);
        });
        audio.addEventListener('pause', () => {
            card.classList.remove('is-playing');
            quiet(false);
            label();
            cancelAnimationFrame(frame);
        });
        audio.addEventListener('ended', () => {
            show(1);
            if (drawing) drawing.follow(1);
            if (words) words.done();
            audio.currentTime = 0;
            time.textContent = fmt(knownLength());
        });
        // a file the browser turns out not to manage after all: the quote
        // carries on without its player
        audio.addEventListener('error', () => {
            if (!audio.src) return;
            box.hidden = true;
            card.classList.remove('is-playing');
            quiet(false);
            if (words) words.done();
        });

        // tap or drag along the bars to move through the note; arrow keys too
        const fraction = e => {
            const r = wave.getBoundingClientRect();
            return (e.clientX - r.left) / r.width;
        };
        let dragging = false;
        wave.addEventListener('pointerdown', e => { dragging = true; wave.setPointerCapture(e.pointerId); seek(fraction(e)); });
        wave.addEventListener('pointermove', e => { if (dragging) seek(fraction(e)); });
        wave.addEventListener('pointerup', () => { dragging = false; });
        wave.addEventListener('pointercancel', () => { dragging = false; });
        wave.addEventListener('keydown', e => {
            const len = knownLength();
            if (!len) return;
            const moves = { ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5 };
            if (e.key in moves) seek((audio.currentTime + moves[e.key]) / len);
            else if (e.key === 'Home') seek(0);
            else if (e.key === 'End') seek(0.999);
            else return;
            e.preventDefault();
        });

        document.addEventListener('langchange', label);
        box.hidden = false;
        label();
        return api;
    }

    /* ---- Each card --------------------------------------------- */
    document.addEventListener('langchange', () => {
        document.querySelectorAll('.cv__sketch[data-alt-en]').forEach(img => { img.alt = pick(img, 'alt'); });
    });

    const live = cards.map(card => {
        const drawing = card.hasAttribute('data-has-portrait') ? Drawing(card) : null;
        const words = Words(card);
        const player = Player(card, drawing, words);
        return { card, drawing, player, inView: false, drawn: false };
    });

    function arrive(item) {
        if (!item.inView || !onShow(item.card)) return;
        if (item.player) item.player.warm();
        if (item.drawing && !item.card.classList.contains('is-playing')) {
            // on the homepage a portrait draws itself each time its quote
            // comes round; elsewhere, once
            if (!item.drawn || item.card.closest('.quote')) item.drawing.sketch();
            item.drawn = true;
        }
    }

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            entries.forEach(e => {
                const item = live.find(l => l.card === e.target);
                const was = item.inView;
                item.inView = e.isIntersecting;
                if (item.inView && !was) arrive(item);
            });
        }, { threshold: 0.35 });
        live.forEach(l => io.observe(l.card));
    } else {
        live.forEach(l => { l.inView = true; arrive(l); });
    }

    // the homepage's quotes turning over: the one leaving falls silent, the
    // one arriving draws itself
    const stage = document.getElementById('quotesStage');
    if (stage) {
        stage.addEventListener('quoteshow', () => {
            live.forEach(l => {
                if (!onShow(l.card) && l.player) l.player.pause();
                else arrive(l);
            });
        });
    }

    /* ---- Depth -------------------------------------------------
       The portrait leans toward the pointer, or with the phone, so its
       layers shift against each other. Not for visitors who ask for less
       motion; on iPhones, which ask permission for the phone's motion,
       the portrait simply stays still. */
    if (!still) {
        const tilted = live.filter(l => l.drawing).map(l => ({ item: l, el: l.card.querySelector('.cv__tilt') }));
        const unit = v => Math.max(-1, Math.min(1, v));
        // x and y from -1 to 1: how far to lean right, and down
        const lean = (t, x, y) => {
            t.el.style.transform = 'rotateY(' + (x * 12).toFixed(2) + 'deg) rotateX(' + (-y * 12).toFixed(2) + 'deg)';
        };

        if (tilted.length && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            let queued = false, px = 0, py = 0;
            document.addEventListener('pointermove', e => {
                px = e.clientX; py = e.clientY;
                if (queued) return;
                queued = true;
                requestAnimationFrame(() => {
                    queued = false;
                    tilted.forEach(t => {
                        if (!t.item.inView) return;
                        const r = t.el.getBoundingClientRect();
                        lean(t, unit((px - (r.left + r.width / 2)) / (window.innerWidth / 2)),
                                unit((py - (r.top + r.height / 2)) / (window.innerHeight / 2)));
                    });
                });
            }, { passive: true });
        } else if (tilted.length) {
            let rest = null;
            window.addEventListener('deviceorientation', e => {
                if (e.gamma == null || e.beta == null) return;
                if (!rest) rest = { g: e.gamma, b: e.beta };
                // drift the resting angle slowly, so a phone held at a new
                // angle settles back to a level portrait
                rest.g += (e.gamma - rest.g) * 0.01;
                rest.b += (e.beta - rest.b) * 0.01;
                const x = unit((e.gamma - rest.g) / 25), y = unit((e.beta - rest.b) / 25);
                tilted.forEach(t => { if (t.item.inView) lean(t, x, y); });
            });
        }
    }
})();
