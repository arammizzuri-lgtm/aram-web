/* =========================================================
   ARAM MIZURI ARCHITECTURE — engage.js
   The parts of a page that invite a visitor to get in touch, or to pass a
   project on: the floating WhatsApp button, the share buttons, and the
   client quotes that turn over one at a time. Loaded on the homepage, the
   project pages and the saved list; each part does nothing on a page that
   lacks its markup.
   ========================================================= */
(function () {
    'use strict';

    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';

    /* ---- Counting --------------------------------------------
       A small anonymous ping for what a page view cannot show — a project
       opened in the overlay, a WhatsApp button pressed. sendBeacon hands it
       to the browser to deliver even as the visitor leaves for WhatsApp, and
       never holds anything up; if it is lost, nothing on the page notices. */
    window.siteTrack = function (type, data) {
        try {
            const body = new FormData();
            body.append('type', type);
            Object.keys(data || {}).forEach(k => { if (data[k]) body.append(k, data[k]); });
            if (!(navigator.sendBeacon && navigator.sendBeacon('/t', body))) {
                fetch('/t', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' }).catch(() => {});
            }
        } catch (e) { /* counting must never get in the way */ }
    };

    // Every WhatsApp button on the page, wherever it sits (data-wa says where).
    // Sharing on WhatsApp is not a chat with the studio: it is counted below.
    document.addEventListener('click', e => {
        const a = e.target.closest && e.target.closest('a[href^="https://wa.me/"]');
        if (!a || a.hasAttribute('data-share')) return;
        window.siteTrack('whatsapp_click', { placement: a.dataset.wa, project: a.dataset.waProject });
    }, true);

    // Every share button: on WhatsApp, the phone's share sheet, or a copied link
    document.addEventListener('click', e => {
        const el = e.target.closest && e.target.closest('[data-share]');
        if (!el) return;
        window.siteTrack('share', { placement: el.dataset.shareAt, project: el.dataset.shareProject });
    }, true);

    /* ---- Sharing -----------------------------------------
       [data-share="whatsapp"] is a wa.me link with no number in it, so
       WhatsApp asks whom to send it to. [data-share="link"] opens the phone's
       own share sheet (Telegram, Viber, Instagram…) where there is one, and
       copies the link where there is not — so on a computer it reads "Copy
       link", kept as the label's language pair for the homepage's toggle. */
    (function initShare() {
        const buttons = document.querySelectorAll('[data-share="link"]');
        if (!buttons.length) return;
        const canShare = typeof navigator.share === 'function';
        const pick = (el, name) => (isKu() ? el.dataset[name + 'Ku'] : el.dataset[name + 'En']) || el.dataset[name + 'En'] || '';

        function copy(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
            return new Promise((resolve, reject) => {
                const ta = document.createElement('textarea');
                ta.value = text; ta.setAttribute('readonly', '');
                ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                let ok = false;
                try { ok = document.execCommand('copy'); } catch (err) { /* reported below */ }
                document.body.removeChild(ta);
                ok ? resolve() : reject();
            });
        }

        buttons.forEach(btn => {
            const label = btn.querySelector('[data-share-label]');
            if (!canShare && label) {
                label.dataset.en = btn.dataset.copyEn;
                label.dataset.ku = btn.dataset.copyKu;
                label.textContent = pick(btn, 'copy');
            }
            let timer = null;
            btn.addEventListener('click', () => {
                const url = btn.dataset.shareUrl || location.href;
                if (canShare) {
                    navigator.share({ title: btn.dataset.shareTitle || document.title, url: url })
                        .catch(() => { /* dismissed — nothing to do */ });
                    return;
                }
                copy(url).then(() => {
                    if (!label) return;
                    label.textContent = pick(btn, 'copied');
                    btn.classList.add('is-done');
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        label.textContent = isKu() ? label.dataset.ku : label.dataset.en;
                        btn.classList.remove('is-done');
                    }, 2200);
                }, () => window.prompt('', url));
            });
        });
    })();

    /* ---- Floating WhatsApp button ------------------------
       Steps aside while one of the sections named in data-hide-over fills
       the bottom of the screen, where the button sits: where it would cover
       controls (the map's zoom buttons) or where the page already offers
       WhatsApp in its own words (the contact section, a project's call to
       action). */
    (function initFab() {
        const fab = document.getElementById('waFab');
        if (!fab) return;

        const sections = (fab.dataset.hideOver || '').split(',')
            .map(s => s.trim()).filter(Boolean)
            .map(sel => document.querySelector(sel)).filter(Boolean);

        if (sections.length && 'IntersectionObserver' in window) {
            const over = new Set();
            // the watched band is the bottom fifth of the screen — the button's corner
            const io = new IntersectionObserver(entries => {
                entries.forEach(e => (e.isIntersecting ? over.add(e.target) : over.delete(e.target)));
                fab.classList.toggle('is-away', over.size > 0);
            }, { rootMargin: '-80% 0px 0px 0px' });
            sections.forEach(s => io.observe(s));
        }
        requestAnimationFrame(() => fab.classList.add('is-ready'));
    })();

    /* ---- Client quotes -----------------------------------
       One quote at a time, crossfading every eight seconds; resting while
       the pointer or focus is on it, and not moving at all for visitors who
       ask for reduced motion. Only quotes given in the language on screen
       take part — the set is rebuilt when the language is switched. */
    (function initQuotes() {
        const stage = document.getElementById('quotesStage');
        if (!stage) return;
        const dotsEl = document.getElementById('quotesDots');
        const all = Array.from(stage.querySelectorAll('.quote'));
        const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let list = [], cur = 0, timer = null, resting = false;

        function show(i) {
            if (!list.length) return;
            cur = (i + list.length) % list.length;
            all.forEach(q => q.classList.remove('is-active'));
            list[cur].classList.add('is-active');
            if (dotsEl) Array.from(dotsEl.children).forEach((d, n) => d.classList.toggle('is-active', n === cur));
        }

        function restart() {
            clearInterval(timer);
            if (list.length > 1 && !still) timer = setInterval(() => { if (!resting) show(cur + 1); }, 8000);
        }

        function build() {
            list = all.filter(q => q.hasAttribute(isKu() ? 'data-has-ku' : 'data-has-en'));
            if (dotsEl) {
                dotsEl.innerHTML = '';
                if (list.length > 1) {
                    list.forEach((_, n) => {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'quotes__dot';
                        dot.tabIndex = -1;
                        dot.addEventListener('click', () => { show(n); restart(); });
                        dotsEl.appendChild(dot);
                    });
                }
            }
            show(0);
            restart();
        }

        stage.addEventListener('mouseenter', () => { resting = true; });
        stage.addEventListener('mouseleave', () => { resting = false; });
        stage.addEventListener('focusin',    () => { resting = true; });
        stage.addEventListener('focusout',   () => { resting = false; });
        document.addEventListener('langchange', build);
        build();
    })();
})();
