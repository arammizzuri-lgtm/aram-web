/* =========================================================
   ARAM MIZURI ARCHITECTURE — voices.js
   Clients in their own voices (partials/client-voice): a pencil portrait
   that draws itself — once as it comes into view, and again in step with
   the client's voice note while it plays — a player in the shape of a
   voice note, and the quote lighting up word by word as it is heard.
   Without a voice note, "Read aloud" reads the quote in a computer voice
   instead, with the same drawing and the same words lighting up.
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

    // On the homepage the quotes take turns in one place (engage.js): only
    // the one on show counts as visible.
    const onShow = card => {
        const quote = card.closest('.quote');
        return !quote || quote.classList.contains('is-active');
    };

    /* ---- The drawing ------------------------------------------
       The portrait is an SVG of strokes in the order they were drawn
       (App\Support\PortraitDrawing). It is fetched and set on the paper,
       and pencil.js draws it line after line: guide lines, the outline, the
       features, the hair, the shading. Until then — and without
       JavaScript — the finished drawing shows as a plain image. */
    function Drawing(card) {
        const img = card.querySelector('.cv__sketch');
        const paper = card.querySelector('.cv__paper');
        if (!img || !paper || typeof window.pencil !== 'function' || !window.fetch || !window.DOMParser) return null;
        let pen = null, ready = null, frame = 0;

        // Ready once the drawing has arrived and is on the paper, unseen;
        // false if it never can be (the plain image then stays).
        function whenReady() {
            if (!ready) {
                ready = fetch(img.currentSrc || img.src, { credentials: 'same-origin' })
                    .then(r => (r.ok ? r.text() : Promise.reject()))
                    .then(text => {
                        const doc = new DOMParser().parseFromString(text, 'image/svg+xml');
                        const svg = doc.documentElement;
                        if (!svg || svg.nodeName.toLowerCase() !== 'svg') throw new Error('not a drawing');
                        const drawn = document.importNode(svg, true);
                        drawn.setAttribute('class', 'cv__draw');
                        drawn.setAttribute('aria-hidden', 'true');
                        paper.appendChild(drawn);
                        pen = window.pencil(drawn);
                        pen.render(still ? 1 : 0);
                        card.classList.add('is-ready');
                        return true;
                    })
                    .catch(() => false);
            }
            return ready;
        }

        function stop() {
            cancelAnimationFrame(frame);
            card.classList.remove('is-drawing');
        }

        return {
            whenReady,
            // draws itself, at an even pace — unless a voice is drawing it
            sketch() {
                if (still) return;
                whenReady().then(ok => {
                    if (!ok || card.classList.contains('is-playing')) return;
                    stop();
                    card.classList.add('is-drawing');
                    const t0 = performance.now(), length = 7500;
                    const step = now => {
                        const t = clamp01((now - t0) / length);
                        pen.render(t);
                        if (t < 1) frame = requestAnimationFrame(step);
                        else card.classList.remove('is-drawing');
                    };
                    frame = requestAnimationFrame(step);
                });
            },
            // the voice's progress, from 0 to 1, drawn
            follow(p) {
                stop();
                if (pen && !still) pen.render(p);
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

    let playing = null;    // the one voice heard at a time: a voice note, or a reading

    // The homepage's quotes announce themselves as they turn over; while a
    // voice plays, the words lighting up must not be read out over it.
    function hush(card, on) {
        const announcer = card.closest('[aria-live]');
        if (!announcer) return;
        if (on && announcer.getAttribute('aria-live') !== 'off') {
            announcer.dataset.live = announcer.getAttribute('aria-live');
            announcer.setAttribute('aria-live', 'off');
        } else if (!on && announcer.dataset.live) {
            announcer.setAttribute('aria-live', announcer.dataset.live);
            delete announcer.dataset.live;
        }
    }

    /* ---- The player ----------------------------------------------
       Shown only where this browser says it can play the file. The bars
       start as a likely shape; the first play reads the real one from the
       recording itself. */

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
        audio.addEventListener('play', () => {
            card.classList.add('is-playing');
            hush(card, true);
            label();
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(tick);
        });
        audio.addEventListener('pause', () => {
            card.classList.remove('is-playing');
            hush(card, false);
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
            hush(card, false);
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

    /* ---- Read aloud ------------------------------------------------
       For a quote without a voice note of its own, read in a computer voice
       — and the button says so. English is read by the device itself;
       Kurdish from the recording made when the quote was saved, or, where a
       device happens to have one, by its own Kurdish voice. The button shows
       only when one of those is there for the language on screen. */
    const synth = 'speechSynthesis' in window && typeof SpeechSynthesisUtterance !== 'undefined' ? window.speechSynthesis : null;

    // the device's best voice for English, or for Sorani (ckb) — or none
    function deviceVoice(ku) {
        if (!synth) return null;
        const want = ku ? /^ckb/i : /^en/i;
        const score = v => (/natural|neural|premium|enhanced/i.test(v.name) ? 4 : 0)
            + (/google|samantha|daniel|serena|libby|sonia|aria|jenny|ava|andrew/i.test(v.name) ? 2 : 0)
            + (/^en[-_]gb/i.test(v.lang) ? 1 : 0);
        return synth.getVoices().filter(v => want.test(v.lang || '')).sort((a, b) => score(b) - score(a))[0] || null;
    }

    function Reader(card, drawing, words) {
        const btn = card.querySelector('[data-read]');
        const figure = card.closest('figure');
        const quote = figure && figure.querySelector('blockquote');
        if (!btn || !quote) return null;
        const label = btn.querySelector('.cv-read__label');
        const src = btn.dataset.srcKu;
        let audio = null, utter = null, frame = 0, active = false;

        // how the words on screen can be read, if they can
        const way = () => (isKu() ? (src ? 'file' : deviceVoice(true) && 'device') : deviceVoice(false) && 'device') || null;
        const setLabel = () => { label.textContent = pick(btn, active ? 'stop' : 'label'); };
        const refresh = () => { btn.hidden = !way(); setLabel(); };

        function follow(p) {
            if (drawing) drawing.follow(p / 0.92);
            if (words) words.show(p);
        }
        function begin() {
            active = true;
            card.classList.add('is-playing');
            hush(card, true);
            setLabel();
        }
        function finish(complete) {
            if (!active) return;
            active = false;
            cancelAnimationFrame(frame);
            card.classList.remove('is-playing');
            hush(card, false);
            setLabel();
            if (complete && drawing) drawing.follow(1);
            if (words) words.done();
        }

        function fromFile() {
            if (!audio) {
                audio = new Audio(src);
                audio.addEventListener('ended', () => finish(true));
                audio.addEventListener('error', () => { finish(false); btn.hidden = true; });
            }
            audio.currentTime = 0;
            const started = audio.play();
            if (started && started.catch) started.catch(() => finish(false));
            begin();
            const tick = () => {
                if (!active) return;
                if (isFinite(audio.duration) && audio.duration > 0) follow(clamp01(audio.currentTime / audio.duration));
                frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
        }

        function fromDevice() {
            const text = quote.textContent.trim();
            const voice = deviceVoice(isKu());
            if (!text || !voice) return;
            const u = new SpeechSynthesisUtterance(text);
            u.voice = voice;
            u.lang = voice.lang;
            u.rate = 0.95;
            utter = u;
            // Where a voice reports each word as it reaches it, the drawing
            // and the words follow it; where not, they follow the clock at an
            // ordinary reading pace.
            let said = -1, t0 = performance.now();
            u.onstart = () => { t0 = performance.now(); };
            u.onboundary = e => { if (u === utter && typeof e.charIndex === 'number') said = e.charIndex / text.length; };
            u.onend = () => { if (u === utter) finish(true); };
            u.onerror = () => { if (u === utter) finish(false); };
            if (synth.speaking) synth.cancel();
            synth.speak(u);
            begin();
            const tick = () => {
                if (!active || u !== utter) return;
                const paced = (performance.now() - t0) / 1000 * 14 * u.rate / text.length;
                follow(Math.min(0.97, said >= 0 ? Math.max(said, paced * 0.6) : paced));
                frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
        }

        const api = {
            pause() {
                if (!active) return;
                if (audio) audio.pause();
                if (utter) { utter = null; synth.cancel(); }
                finish(false);
            },
        };

        btn.addEventListener('click', () => {
            if (active) { api.pause(); return; }
            if (playing && playing !== api) playing.pause();
            playing = api;
            if (drawing) drawing.whenReady();
            way() === 'file' ? fromFile() : fromDevice();
        });

        document.addEventListener('langchange', () => { api.pause(); refresh(); });
        // the device's voices arrive a moment after the page does
        if (synth) synth.addEventListener('voiceschanged', refresh);
        refresh();
        return api;
    }

    // a reading left going would carry on after the visitor has moved on
    window.addEventListener('pagehide', () => { if (synth) synth.cancel(); });

    /* ---- Each card --------------------------------------------- */
    document.addEventListener('langchange', () => {
        document.querySelectorAll('.cv__sketch[data-alt-en]').forEach(img => { img.alt = pick(img, 'alt'); });
    });

    const live = cards.map(card => {
        const drawing = card.hasAttribute('data-has-portrait') ? Drawing(card) : null;
        const words = Words(card);
        const player = Player(card, drawing, words);
        const reader = player ? null : Reader(card, drawing, words);
        return { card, drawing, player, reader, inView: false, drawn: false };
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
                if (onShow(l.card)) { arrive(l); return; }
                if (l.player) l.player.pause();
                if (l.reader) l.reader.pause();
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
