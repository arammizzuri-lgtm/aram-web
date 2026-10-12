/* =========================================================
   ARAM MIZURI ARCHITECTURE — careers.js
   The careers page: "Apply for this role" picks the position in the form,
   and the form sends the application — CV and all — without leaving the
   page. Its messages are in the page's language (the form's data-msg-*).
   ========================================================= */
(function () {
    'use strict';

    const form = document.getElementById('careersForm');
    if (!form) return;
    const status = document.getElementById('crStatus');
    const position = form.querySelector('[name="position"]');
    const cvInput = form.querySelector('[name="cv"]');
    const cvName = document.getElementById('crCvName');
    const cvBox = form.querySelector('.cr-file');
    const msg = key => form.dataset['msg' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
    const maxBytes = parseInt(form.dataset.maxBytes, 10) || 8 * 1024 * 1024;

    /* ---- "Apply for this role" ---- */
    document.querySelectorAll('[data-apply]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            if (position) position.value = btn.dataset.apply;
            document.getElementById('apply').scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => { const n = form.querySelector('[name="name"]'); if (n) n.focus({ preventScroll: true }); }, 500);
        });
    });

    /* ---- the CV ---- */
    function cvProblem() {
        const file = cvInput.files && cvInput.files[0];
        if (!file || !/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') return 'cv';
        if (file.size > maxBytes) return 'big';
        return null;
    }
    cvInput.addEventListener('change', () => {
        const file = cvInput.files && cvInput.files[0];
        cvName.textContent = file ? file.name : cvName.dataset.empty;
        cvBox.classList.toggle('has-file', !!file);
        cvInput.closest('.cr-field').classList.remove('is-invalid');
        const problem = file ? cvProblem() : null;
        if (problem) say(problem, true);
        else if (status && status.classList.contains('is-error')) status.hidden = true;
    });

    /* ---- messages ---- */
    function say(key, isError) {
        if (!status) return;
        status.textContent = msg(key);
        status.classList.toggle('is-error', !!isError);
        status.hidden = false;
        status.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function flag(el) {
        const wrap = el && el.closest('.cr-field');
        if (wrap) wrap.classList.add('is-invalid');
    }
    form.addEventListener('input', e => {
        const wrap = e.target.closest('.cr-field');
        if (wrap) wrap.classList.remove('is-invalid');
    });

    /* ---- sending ---- */
    form.addEventListener('submit', e => {
        e.preventDefault();
        const name = form.querySelector('[name="name"]');
        const email = form.querySelector('[name="email"]');
        if (!name.value.trim()) { flag(name); name.focus(); say('check', true); return; }
        if (!email.value.trim() || !email.checkValidity()) { flag(email); email.focus(); say('check', true); return; }
        const problem = cvProblem();
        if (problem) { flag(cvInput); say(problem, true); return; }

        const btnLabel = form.querySelector('.cr-form__send-label');
        const original = btnLabel.textContent;
        btnLabel.textContent = msg('sending');
        form.classList.add('is-sending');
        const token = document.querySelector('meta[name="csrf-token"]');

        fetch('/careers/apply', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: new FormData(form),
        })
        .then(res => {
            if (res.status === 422) {
                return res.json().then(body => {
                    const errors = (body && body.errors) || {};
                    Object.keys(errors).forEach(k => flag(form.querySelector('[name="' + k + '"]')));
                    throw new Error(errors.cv ? 'cv' : 'check');
                });
            }
            if (res.status === 413) throw new Error('big');     // the server refused the size outright
            if (!res.ok) throw new Error('failed');
            return res.json().catch(() => ({}));
        })
        .then(() => {
            form.reset();
            cvName.textContent = cvName.dataset.empty;
            cvBox.classList.remove('has-file');
            say('ok', false);
        })
        .catch(err => say(['cv', 'big', 'check'].indexOf(err && err.message) !== -1 ? err.message : 'failed', true))
        .finally(() => {
            btnLabel.textContent = original;
            form.classList.remove('is-sending');
        });
    });
})();
