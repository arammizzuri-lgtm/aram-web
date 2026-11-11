/* =========================================================
   ARAM MIZURI ARCHITECTURE — landmark.js
   A landmark's page (/kurdistan-heritage/{slug}): sections drifting in as
   they arrive, figures counting up, the annotated photograph's points, the
   before/after handle, the cinematic opening's slow drift, and the lightbox
   every photograph opens in. The map is heritage.js's.
   ========================================================= */
(function () {
    'use strict';

    const html = document.documentElement;
    html.classList.add('js');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rtl = html.getAttribute('dir') === 'rtl';
    const eastern = html.lang !== 'en';                 // Kurdish and Arabic pages count in Eastern Arabic digits
    const DIGITS = '٠١٢٣٤٥٦٧٨٩';
    const local = s => eastern ? String(s).replace(/\d/g, d => DIGITS[d]) : String(s);

    /* ── Sections arrive ─────────────────────────────────────────────── */
    const revealed = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && !reduce) {
        const io = new IntersectionObserver(entries => entries.forEach(e => {
            if (!e.isIntersecting) return;
            e.target.classList.add('is-in');
            io.unobserve(e.target);
        }), { rootMargin: '0px 0px -6% 0px', threshold: 0.03 });
        revealed.forEach(el => io.observe(el));
    } else {
        revealed.forEach(el => el.classList.add('is-in'));
    }

    /* ── Figures count up ───────────────────────────────────────────── */
    document.querySelectorAll('.lm-stat__value[data-count]').forEach(el => {
        const text = el.textContent.trim();
        const m = text.match(/[\d٠-٩][\d٠-٩,٬]*/);
        if (!m || reduce) return;
        const raw = m[0];
        const ascii = raw.replace(/[٠-٩]/g, d => DIGITS.indexOf(d)).replace(/[,٬]/g, '');
        if (!/^\d+$/.test(ascii)) return;
        const target = parseInt(ascii, 10);
        if (!target || target > 1e9) return;
        const sep = raw.includes(',') ? ',' : (raw.includes('٬') ? '٬' : '');
        const fmt = v => { let s = String(v); if (sep) s = s.replace(/\B(?=(\d{3})+(?!\d))/g, sep); return local(s); };
        const prefix = text.slice(0, m.index), suffix = text.slice(m.index + raw.length);
        const o = new IntersectionObserver(entries => {
            if (!entries[0].isIntersecting) return;
            o.disconnect();
            const t0 = performance.now(), dur = 1400 + Math.min(600, String(target).length * 120);
            const tick = now => {
                const p = Math.min(1, (now - t0) / dur), e = 1 - Math.pow(1 - p, 3);
                el.textContent = prefix + fmt(Math.round(target * e)) + suffix;
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }, { threshold: 0.4 });
        o.observe(el);
    });

    /* ── The annotated photograph: a point and its line, linked ─────── */
    document.querySelectorAll('.lm-anno').forEach(sec => {
        const pts = sec.querySelectorAll('.lm-anno__pt'), items = sec.querySelectorAll('.lm-anno__item');
        const on = (k, scroll) => {
            pts.forEach(p => p.classList.toggle('is-on', p.dataset.point === k));
            items.forEach(it => {
                const hit = it.dataset.point === k;
                it.classList.toggle('is-on', hit);
                if (hit && scroll && window.innerWidth < 1000) it.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
            });
        };
        pts.forEach(p => p.addEventListener('click', () => on(p.dataset.point, true)));
        items.forEach(it => {
            it.addEventListener('click', () => on(it.dataset.point, false));
            it.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); on(it.dataset.point, false); } });
        });
        if (pts[0]) on(pts[0].dataset.point, false);
    });

    /* ── Before / after ─────────────────────────────────────────────── */
    document.querySelectorAll('.lm-cmp__stage').forEach(stage => {
        const range = stage.querySelector('.lm-cmp__range');
        if (!range) return;
        const set = () => stage.style.setProperty('--pos', range.value + '%');
        range.addEventListener('input', set);
        set();
    });

    /* ── The cinematic opening drifts, slowly, as the page starts ───── */
    const bg = document.querySelector('.lm-hero--cinematic .lm-hero__bg img');
    if (bg && !reduce) {
        let raf = null;
        const move = () => {
            raf = null;
            const y = Math.min(window.scrollY, window.innerHeight);
            bg.style.transform = 'translateY(' + (y * 0.18).toFixed(1) + 'px)';
        };
        window.addEventListener('scroll', () => { if (!raf) raf = requestAnimationFrame(move); }, { passive: true });
    }

    /* ── How it was built: a drawing in steps ───────────────────────── */
    // The drawing's parts carry data-step; the steps beside it are buttons.
    // It plays through once when it scrolls into view — each step's lines
    // drawing themselves in — and after that the visitor picks a step, or
    // plays it again. Reduced motion: the finished drawing, no playing.
    document.querySelectorAll('[data-tq]').forEach(tq => {
        const parts = Array.from(tq.querySelectorAll('.tq__svg [data-step]'));
        const items = Array.from(tq.querySelectorAll('[data-tq-step]'));
        const replay = tq.querySelector('[data-tq-replay]');
        const fig = tq.querySelector('.tq__fig');
        if (!parts.length || !items.length) return;
        const last = items.length;

        // a line draws itself in from nothing: it needs its own length
        tq.querySelectorAll('.tq__svg [data-step] .tq-l, .tq__svg [data-step] .tq-t:not(.tq-dash)').forEach(el => {
            try { el.style.setProperty('--len', Math.ceil(el.getTotalLength()) + 2); } catch (e) { /* not a shape with a length */ }
        });

        let at = 0, timer = null;
        function paint(n) {
            at = n;
            parts.forEach(p => {
                const s = parseInt(p.dataset.step, 10);
                // the last step of the words shows whatever is left of the drawing
                p.classList.toggle('is-now', s === at || (at === last && s > at));
                p.classList.toggle('is-done', s < at);
            });
            items.forEach((it, i) => {
                it.classList.toggle('is-now', i + 1 === at);
                it.classList.toggle('is-done', i + 1 < at);
                it.querySelector('.tq__pick').setAttribute('aria-current', i + 1 === at ? 'step' : 'false');
            });
            if (replay) replay.hidden = at < last;
        }
        const stop = () => { clearTimeout(timer); timer = null; };
        function play() {
            stop();
            paint(1);
            const next = () => { if (at < last) timer = setTimeout(() => { paint(at + 1); next(); }, 3400); };
            next();
        }

        tq.classList.add('is-live');
        items.forEach((it, i) => it.querySelector('.tq__pick').addEventListener('click', () => { stop(); paint(i + 1); }));
        if (replay) replay.addEventListener('click', play);

        if (reduce || !('IntersectionObserver' in window)) { paint(last); return; }
        paint(0);
        const seen = new IntersectionObserver(entries => {
            if (!entries[0].isIntersecting) return;
            seen.disconnect();
            play();
        }, { threshold: 0.5 });
        seen.observe(fig || tq);
    });

    /* ── The lightbox ───────────────────────────────────────────────── */
    const dlg = document.getElementById('lmLightbox');
    const links = Array.from(document.querySelectorAll('a[data-lightbox]'));
    if (!dlg || typeof dlg.showModal !== 'function' || !links.length) return;

    const img = dlg.querySelector('.lm-lb__img'), cap = dlg.querySelector('.lm-lb__text'),
        credit = dlg.querySelector('.lm-lb__credit'), num = dlg.querySelector('.lm-lb__n'),
        arrows = dlg.querySelectorAll('.lm-lb__arrow');
    let i = 0;

    function show(k) {
        i = (k + links.length) % links.length;
        const a = links[i];
        img.src = a.href;
        img.alt = a.getAttribute('aria-label') || '';
        cap.textContent = a.dataset.caption || '';
        credit.textContent = a.dataset.credit || '';
        num.textContent = links.length > 1 ? local(i + 1) + ' / ' + local(links.length) : '';
        arrows.forEach(b => { b.hidden = links.length < 2; });
        // the neighbours, ready before they are asked for
        [links[(i + 1) % links.length], links[(i - 1 + links.length) % links.length]].forEach(n => { const p = new Image(); p.src = n.href; });
    }
    function open(k) {
        show(k);
        if (!dlg.open) dlg.showModal();
        document.body.classList.add('lm-lb-open');
    }
    links.forEach((a, k) => a.addEventListener('click', e => { e.preventDefault(); open(k); }));
    dlg.querySelector('[data-lb-close]').addEventListener('click', () => dlg.close());
    dlg.querySelector('[data-lb-prev]').addEventListener('click', () => show(i - 1));
    dlg.querySelector('[data-lb-next]').addEventListener('click', () => show(i + 1));
    dlg.addEventListener('close', () => { document.body.classList.remove('lm-lb-open'); img.removeAttribute('src'); });
    // a tap on the dark around the photograph closes it
    dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    dlg.addEventListener('keydown', e => {
        const fwd = rtl ? 'ArrowLeft' : 'ArrowRight', back = rtl ? 'ArrowRight' : 'ArrowLeft';
        if (e.key === fwd) show(i + 1);
        else if (e.key === back) show(i - 1);
    });
    // a swipe turns the page
    let x0 = null;
    dlg.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
    dlg.addEventListener('touchend', e => {
        if (x0 === null) return;
        const dx = e.changedTouches[0].clientX - x0;
        x0 = null;
        if (Math.abs(dx) < 40) return;
        show((dx < 0) !== rtl ? i + 1 : i - 1);
    });
})();
