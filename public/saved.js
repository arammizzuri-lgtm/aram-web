/* =========================================================
   ARAM MIZURI ARCHITECTURE — saved.js
   The heart on every project, and the "Saved" list it builds.

   The list is kept in this browser only (localStorage: no account, nothing
   on the server) as project slugs, in the order they were saved. The server
   sees it only when it is handed over: in the address of a /saved page, or
   with an enquiry. Everything that shows it reads from here:
     [data-save="slug"]   a heart — toggles that project
     [data-saved-link]    "Saved (n)" in a top bar, hidden while it is empty
     [data-saved-count]   the n
     #formSaved           the enquiry form's chip (homepage)
     .sv-page             the /saved page
   Loaded on every public page; each part does nothing where its markup is
   missing.
   ========================================================= */
(function () {
    'use strict';

    const KEY = 'am_saved';
    const MAX = 24;
    const SLUG = /^[a-z0-9-]{1,191}$/;

    const ui = (function () {
        const el = document.getElementById('savedText');
        try { return (el && JSON.parse(el.textContent)) || {}; } catch (e) { return {}; }
    })();
    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';
    const t = key => { const row = ui[key] || {}; return (isKu() ? row.ku : row.en) || row.en || ''; };

    /* ---- The list ------------------------------------------
       Where storage is blocked (some private windows) the list still works,
       for as long as the page is open. */
    let fallback = [];
    const clean = list => (Array.isArray(list) ? list : [])
        .filter((s, i, all) => typeof s === 'string' && SLUG.test(s) && all.indexOf(s) === i)
        .slice(0, MAX);

    function read() {
        try { return clean(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) { return clean(fallback); }
    }
    function write(list) {
        fallback = clean(list);
        try { localStorage.setItem(KEY, JSON.stringify(fallback)); } catch (e) { /* kept in fallback */ }
        chipOff = false;                    // a fresh change brings the form's chip back
        paint();
    }
    /** Toggle one project; true when it has just been saved. */
    function toggle(slug) {
        const list = read();
        const at = list.indexOf(slug);
        if (at === -1) list.push(slug); else list.splice(at, 1);
        write(list);
        return at === -1;
    }
    /** Slugs from an address: dot-separated (commas accepted too). */
    const split = s => clean(String(s || '').toLowerCase().split(/[.,\s]+/));

    /** The /saved page for a list, in the language on screen. */
    function url(list) {
        list = list || read();
        return (isKu() ? '/ku' : '') + '/saved' + (list.length ? '?p=' + list.join('.') : '');
    }

    /* ---- Hearts --------------------------------------------
       Bound to each button rather than delegated, so that a heart on a
       project card is handled before the card itself, which would otherwise
       open the overlay too — and so that the grid's own "that was a drag,
       not a click" check, which runs first, still gets its say. */
    function onHeart(e) {
        e.preventDefault();
        e.stopPropagation();
        const btn = e.currentTarget;
        const slug = btn.dataset.save;
        if (!slug) return;
        if (toggle(slug)) {
            btn.classList.remove('is-pop'); void btn.offsetWidth; btn.classList.add('is-pop');
            document.querySelectorAll('[data-saved-link]').forEach(a => {
                a.classList.remove('is-bump'); void a.offsetWidth; a.classList.add('is-bump');
            });
            if (window.siteTrack) window.siteTrack('save', { project: slug, placement: btn.dataset.saveAt });
        }
    }
    function bind() {
        document.querySelectorAll('[data-save]').forEach(btn => {
            if (btn.dataset.saveBound) return;
            btn.dataset.saveBound = '1';
            btn.addEventListener('click', onHeart);
        });
    }

    function paint() {
        const list = read();
        document.querySelectorAll('[data-save]').forEach(btn => {
            const on = !!btn.dataset.save && list.indexOf(btn.dataset.save) !== -1;
            btn.classList.toggle('is-saved', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            const label = btn.querySelector('[data-save-label]');
            if (label) label.textContent = t(on ? 'saved' : 'save');
            else btn.setAttribute('aria-label', t(on ? 'saved' : 'save'));
        });
        document.querySelectorAll('[data-saved-count]').forEach(el => { el.textContent = list.length; });
        document.querySelectorAll('[data-saved-link]').forEach(a => {
            a.hidden = list.length === 0;
            a.href = url(list);
        });
        paintChip(list);
        paintPage(list);
    }

    /* ---- The enquiry form's chip (homepage) -----------------
       Shows what goes with the enquiry: the visitor's own list — or, when
       they come from a shared list's "Ask the studio about these"
       (/?saved=a.b#contact), that list. ✕ leaves it off this enquiry. */
    const chip = document.getElementById('formSaved');
    let chipList = null;
    let chipOff = false;
    if (chip) {
        const params = new URLSearchParams(location.search);
        if (params.has('saved')) {
            chipList = split(params.get('saved'));
            params.delete('saved');
            const qs = params.toString();
            if (window.history && history.replaceState) {
                history.replaceState(history.state, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
            }
        }
        const clear = document.getElementById('formSavedClear');
        if (clear) clear.addEventListener('click', () => { chipOff = true; paintChip(read()); });
    }
    const known = () => ((window.__SITE__ || {}).projects || []).filter(p => p.slug);
    function chipSlugs(list) {
        if (!chip || chipOff) return [];
        const projects = known();
        return (chipList || list).filter(s => projects.some(p => p.slug === s));
    }
    function paintChip(list) {
        if (!chip) return;
        const slugs = chipSlugs(list);
        chip.hidden = slugs.length === 0;
        const names = document.getElementById('formSavedNames');
        if (!names) return;
        const projects = known();
        const labels = slugs.map(s => {
            const p = projects.find(x => x.slug === s);
            return (isKu() && p.name_ku) ? p.name_ku : p.name;
        });
        names.textContent = labels.slice(0, 3).join(' · ') + (labels.length > 3 ? ' +' + (labels.length - 3) : '');
    }

    /* ---- The /saved page ------------------------------------
       One address is both the visitor's own shortlist and, on someone else's
       phone, a list shared with them; the inline script in the page's head
       has already told the two apart (html.sv-own). On their own list, a
       heart taken off dims its card, and the address and every way of
       sending the list on follow what is left. */
    const page = document.querySelector('.sv-page');
    function paintPage(list) {
        if (!page) return;
        const own = document.documentElement.classList.contains('sv-own');
        const cards = Array.from(page.querySelectorAll('.sv-card'));
        cards.forEach(card => card.classList.toggle('is-off', own && list.indexOf(card.dataset.slug) === -1));
        if (!own) return;

        const slugs = cards.map(c => c.dataset.slug).filter(s => list.indexOf(s) !== -1);
        const shareUrl = location.origin + url(slugs);
        const ask = page.querySelector('[data-saved-ask]');
        if (ask) {
            ask.href = ask.dataset.base + (slugs.length ? '?saved=' + slugs.join('.') : '') + '#contact';
            ask.classList.toggle('is-disabled', !slugs.length);
        }
        page.querySelectorAll('[data-share-url]').forEach(el => { el.dataset.shareUrl = shareUrl; });
        const wa = page.querySelector('[data-saved-wa]');
        if (wa) wa.href = 'https://wa.me/?text=' + encodeURIComponent(wa.dataset.template.replace('{url}', shareUrl));
        if (window.history && history.replaceState && cards.length) history.replaceState(history.state, '', url(slugs));
    }

    // "Save these to my list", on a list someone shared
    const keep = page && page.querySelector('[data-saved-keep]');
    if (keep) {
        keep.addEventListener('click', () => {
            const list = read();
            page.querySelectorAll('.sv-card').forEach(c => { if (list.indexOf(c.dataset.slug) === -1) list.push(c.dataset.slug); });
            write(list);
            keep.disabled = true;
            const label = keep.querySelector('span');
            if (label) label.textContent = t('kept');
        });
    }

    window.siteSaved = {
        list: read,
        url: url,
        /** after the overlay points its heart at another project */
        refresh: function () { bind(); paint(); },
        /** the slugs to send with an enquiry: what the form's chip shows */
        forEnquiry: function () { return chipSlugs(read()); },
        /** an enquiry went: the chip has said its piece */
        enquirySent: function () { chipOff = true; chipList = null; paintChip(read()); },
    };

    window.addEventListener('storage', e => { if (e.key === KEY) paint(); });
    document.addEventListener('langchange', paint);
    bind();
    paint();
})();
