/* =========================================================
   ARAM MIZURI ARCHITECTURE — estimate.js
   The cost estimator (/estimate). The page works without this file — its
   form is a plain GET that the server answers with the result. With it:
     · the result follows each answer, fetched from /estimate/calc (the sums
       are only ever done on the server: App\Support\CostEstimate)
     · the address follows too, so an estimate can be reloaded or shared
     · the − / + steppers, and "I know my built area"
     · "Send this to the studio": a short enquiry, sent with the answers
     · the house drawn beside the figures (estimate-house.js, told by an
       "estimate:house" event) and the projects on plots like theirs below
   ========================================================= */
(function () {
    'use strict';

    const form = document.getElementById('esForm');
    if (!form) return;

    const result     = document.getElementById('esResult');
    const similar    = document.getElementById('esSimilar');
    const send       = document.getElementById('esSend');
    const builtAuto  = document.getElementById('esBuiltAuto');
    const builtOwn   = document.getElementById('esBuiltOwn');
    const builtWrap  = document.getElementById('esBuiltWrap');
    const builtInput = document.getElementById('es-built');
    const text = (function () {
        try { return JSON.parse(document.getElementById('esText').textContent) || {}; } catch (e) { return {}; }
    })();
    const isKu = () => document.documentElement.getAttribute('dir') === 'rtl';

    form.classList.add('is-live');          // its button is for pages without JavaScript

    /** The answers, as the address carries them (empty ones left out). */
    function params() {
        const p = new URLSearchParams();
        new FormData(form).forEach((v, k) => { if (v !== '') p.append(k, v); });
        return p;
    }

    /* ---- The result, as the answers change ------------------ */
    let timer = null, asked = 0, counted = false;
    function count() {
        // once per visit: "an estimate was worked out", for the dashboard
        if (counted || !window.siteTrack) return;
        counted = true;
        window.siteTrack('estimate');
    }
    function update() {
        const p = params();
        const mine = ++asked;
        if (window.history && history.replaceState) {
            history.replaceState(history.state, '', location.pathname + (p.toString() ? '?' + p : ''));
        }
        fetch(form.dataset.calc + '?' + p, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => (r.ok ? r.json() : Promise.reject(r.status)))
            .then(data => {
                if (mine !== asked) return;      // a newer answer is on its way
                result.innerHTML = data.html;
                if (similar && typeof data.similar === 'string') similar.innerHTML = data.similar;
                document.dispatchEvent(new CustomEvent('estimate:house', { detail: data.house || null }));
                builtAuto.textContent = data.auto_built ? Number(data.auto_built).toLocaleString('en') : '—';
                send.hidden = !data.ok;
                if (data.ok) count();
            })
            .catch(() => { /* the last result stays; the next change asks again */ });
    }
    const soon = () => { clearTimeout(timer); timer = setTimeout(update, 220); };
    form.addEventListener('input', soon);
    form.addEventListener('change', soon);
    form.addEventListener('submit', e => { e.preventDefault(); update(); });

    // an estimate that arrived with the page (reloaded, or shared) counts too
    if (!send.hidden) window.addEventListener('load', count);

    /* ---- − / + ---------------------------------------------- */
    form.querySelectorAll('[data-step]').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = btn.parentElement.querySelector('input');
            const min = Number(input.min || 0), max = Number(input.max || 99);
            input.value = Math.max(min, Math.min(max, (parseInt(input.value, 10) || 0) + Number(btn.dataset.step)));
            soon();
        });
    });

    /* ---- Built area: worked out from the plot, or their own -- */
    builtOwn.addEventListener('change', () => {
        builtWrap.hidden = !builtOwn.checked;
        if (builtOwn.checked) {
            if (!builtInput.value) builtInput.value = (builtAuto.textContent || '').replace(/\D/g, '');
            builtInput.focus();
        } else {
            builtInput.value = '';
        }
        soon();
    });

    /* ---- Send this to the studio ----------------------------- */
    const open   = document.getElementById('esSendOpen');
    const sform  = document.getElementById('esSendForm');
    const status = document.getElementById('esSendStatus');
    open.addEventListener('click', () => {
        open.hidden = true;
        sform.hidden = false;
        sform.querySelector('[name="name"]').focus();
    });

    function say(key, isError) {
        status.textContent = text[key] || '';
        status.hidden = false;
        status.classList.toggle('is-error', !!isError);
    }

    sform.addEventListener('submit', e => {
        e.preventDefault();
        const value = name => ((sform.querySelector('[name="' + name + '"]') || {}).value || '').trim();
        if (!value('name')) { say('form_check', true); sform.querySelector('[name="name"]').focus(); return; }
        if (!value('phone') && !value('email')) { say('form_need_contact', true); sform.querySelector('[name="phone"]').focus(); return; }

        const btn = sform.querySelector('button[type="submit"]');
        const token = document.querySelector('meta[name="csrf-token"]');
        btn.disabled = true;
        say('form_sending');

        const body = {
            name: value('name'), phone: value('phone'), email: value('email'),
            message: value('message'), website: value('website'),     // the last is the spam trap
            estimate: Object.fromEntries(params()),
            lang: isKu() ? 'ku' : 'en',
            page: location.pathname,
        };
        Object.keys(body).forEach(k => { if (body[k] === '') delete body[k]; });

        fetch('/contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: JSON.stringify(body),
        })
            .then(r => {
                if (r.status === 422) throw new Error('form_check');
                if (!r.ok) throw new Error('form_failed');
            })
            .then(() => {
                sform.classList.add('is-sent');
                say('est_sent');
            })
            .catch(err => say(err && /^form_/.test(err.message) ? err.message : 'form_failed', true))
            .finally(() => { btn.disabled = false; });
    });
})();
