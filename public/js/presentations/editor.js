// The presentation editor: its state, what can be done to it, and the three
// panes — pages on the left, the page being worked on in the middle, its
// settings on the right (panels.js). The document is saved as it changes.
import { html, render, useState, useEffect, useRef } from 'htm-preact';
import { normalize, starterPages, newPage, slot, uid, clone, LAYOUTS, SECTIONS, structure, resolveTheme, isDivider, sourceSize, relayout, sectionName } from '@pm/model';
import { initText, ensureFonts } from '@pm/text';
import { layoutPage, fontsOf } from '@pm/layout';
import { PageView, createMedia } from '@pm/view';
import { createUploader } from '@pm/uploads';
import { PageTab, ProjectTab, DesignTab, AddPageDialog, LayoutDialog, ImportDialog, LibraryDialog, PreviewDialog, PresentDialog, ExportDialog, BrandPanel, SiteDialog } from '@pm/panels';

const boot = JSON.parse(document.getElementById('boot').textContent);
const root = document.getElementById('app');
const brandMode = !boot.presentation;
const px = (n) => `${n}px`;
const clamp = (v, a = 0, b = 1) => Math.max(a, Math.min(b, v));

// ── State ──
const S = {
    id: brandMode ? null : boot.presentation.id,
    name: brandMode ? '' : boot.presentation.name,
    doc: null,
    page: 0, key: null, picks: new Set(), crop: false,
    tab: 'page', dlg: null, zoom: 'fit', guides: [],
    save: 'saved', saveError: '',
    assets: new Map(boot.assets.map((a) => [a.hash, a])),
    presets: boot.presets, brand: boot.brand, revisions: boot.revisions || [],
};

let queued = false;
function draw() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; render(html`<${App} />`, root); });
}
const cache = new Map();
let sigDoc = null, sig = '';
/** Something outside the document changed what pages look like (a font arrived, a logo was made, the branding). */
function refresh() { cache.clear(); sigDoc = null; draw(); }

const uploader = createUploader(boot);
const media = createMedia(boot, () => S.assets, refresh);
const env = (edit = true) => ({ brand: S.brand, assets: S.assets, edit, logoRatio: media.logoRatio });

/** A page laid out — remembered until the page, or anything it draws on, changes. */
function lay(i, edit = true) {
    const d = S.doc;
    if (sigDoc !== d) {
        sigDoc = d;
        sig = JSON.stringify([d.theme, d.info, d.size, d.orient, d.partner, d.watermark, d.lang, d.site, d.revisions, d.pages.length,
            d.pages.map((p) => (isDivider(p) ? p.title : p.layout === 'drawing' || p.layout === 'sheet' ? [p.title, p.drawing, p.images[0]] : 0))]);
    }
    const p = d.pages[i], id = `${p.id}|${edit}`, key = `${i}|${JSON.stringify(p)}|${sig}`;
    const hit = cache.get(id);
    if (hit && hit.key === key) return hit.lay;
    const made = layoutPage(d, i, env(edit));
    cache.set(id, { key, lay: made });
    return made;
}

// ── Changing the document: every change is a step that can be undone, and is saved ──
const hist = { stack: [], i: -1, tag: null, at: 0 };
function commit(fn, tag) {
    const next = clone(S.doc);
    fn(next);
    const now = Date.now();
    if (tag && tag === hist.tag && now - hist.at < 900) hist.stack[hist.i] = next;   // typing in one field is one step
    else {
        hist.stack.splice(hist.i + 1);
        hist.stack.push(next);
        if (hist.stack.length > 80) hist.stack.shift();
        hist.i = hist.stack.length - 1;
    }
    hist.tag = tag; hist.at = now;
    S.doc = next;
    settle();
    dirty();
}
function settle() {
    S.page = clamp(S.page, 0, Math.max(0, S.doc.pages.length - 1));
    if (S.key && S.doc.pages.length && !lay(S.page).frames[S.key]) { S.key = null; S.crop = false; }
}
function step(by) {
    if (!hist.stack[hist.i + by]) return;
    hist.i += by; hist.tag = null;
    S.doc = hist.stack[hist.i];
    settle();
    dirty();
}

let timer = null, saving = null;
function dirty() {
    S.save = 'dirty';
    clearTimeout(timer);
    timer = setTimeout(saveNow, 1400);
    draw();
}
async function saveNow() {
    clearTimeout(timer);
    if (brandMode) return;
    if (saving) await saving;
    if (S.save === 'saved') return;
    const doc = S.doc, name = S.name;
    S.save = 'saving';
    draw();
    saving = (async () => {
        try {
            const res = await fetch(boot.urls.save, {
                method: 'PUT', credentials: 'same-origin', headers: { ...uploader.headers, 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name.trim() || 'Untitled presentation', doc: JSON.stringify(doc) }),
            });
            if (!res.ok) {
                throw new Error(res.status === 401 || res.status === 419
                    ? 'You have been signed out. Sign in to the admin in another tab — your work is kept here and will be saved.'
                    : `Not saved (${res.status}) — trying again.`);
            }
            S.save = S.doc === doc && S.name === name ? 'saved' : 'dirty';
            S.saveError = '';
        } catch (e) {
            S.save = 'error';
            S.saveError = e instanceof TypeError ? 'Not saved — the connection dropped. Trying again.' : e.message;
        }
    })();
    await saving;
    saving = null;
    if (S.save !== 'saved') timer = setTimeout(saveNow, S.save === 'error' ? 7000 : 500);
    draw();
}
window.addEventListener('beforeunload', (e) => {
    if (S.save !== 'saved') { e.preventDefault(); e.returnValue = ''; }
});

let brandTimer = null;
function setBrand(fn) {
    const b = clone(S.brand);
    fn(b);
    S.brand = b;
    S.save = 'dirty';
    clearTimeout(brandTimer);
    brandTimer = setTimeout(async () => {
        const res = await fetch(boot.urls.brand, { method: 'PUT', credentials: 'same-origin', headers: { ...uploader.headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ data: JSON.stringify(S.brand) }) }).catch(() => null);
        S.save = res && res.ok ? 'saved' : 'error';
        S.saveError = 'The branding was not saved — check the connection.';
        draw();
    }, 700);
    refresh();
}

// ── What can be done ──
const slotFor = (page, key) => (key.startsWith('img:') ? page.images[+key.slice(4)] : page.free.find((f) => f.t === 'image' && `free:${f.id}` === key));
const app = {
    S, boot, media, uploader, lay, env, commit, saveNow, setBrand, call,
    select(key) { S.key = key; if (!key) S.crop = false; else S.tab = 'page'; draw(); },
    tab(name) { S.tab = name; draw(); },
    dialog(d) { S.dlg = d ? { ...d, n: uid() } : null; draw(); },
    openImport(files, target) { app.dialog({ type: 'import', files: files ? [...files] : null, target }); },
    openLibrary(target) { app.dialog({ type: 'library', target }); },
    cropMode(key) { S.crop = !(S.crop && S.key === key); S.key = key; draw(); },
    addAsset(a) { S.assets.set(a.hash, a); refresh(); },
    async removeAsset(a) {
        const res = await fetch(boot.urls.asset.replace('__ID__', a.id), { method: 'DELETE', credentials: 'same-origin', headers: uploader.headers }).catch(() => null);
        if (!res || !res.ok) return alert('It could not be removed — check the connection.');
        S.assets.delete(a.hash);
        refresh();
    },

    addPage(page, at = S.doc.pages.length ? S.page + 1 : 0) {
        commit((d) => { d.pages.splice(at, 0, page); });
        S.page = at; S.key = null; S.picks = new Set([page.id]);
        draw();
    },
    duplicatePage(i) {
        const copy = { ...clone(S.doc.pages[i]), id: uid() };
        commit((d) => { d.pages.splice(i + 1, 0, copy); });
        S.page = i + 1; S.key = null;
        draw();
    },
    deletePages(indices) {
        if (!confirm(indices.length > 1 ? `Delete these ${indices.length} pages?` : `Delete page ${indices[0] + 1}?`)) return;
        commit((d) => { d.pages = d.pages.filter((_, i) => !indices.includes(i)); });
        S.picks = new Set();
    },
    movePages(indices, to) {
        const current = S.doc.pages[S.page] && S.doc.pages[S.page].id;
        commit((d) => {
            const moving = indices.map((i) => d.pages[i]);
            const rest = d.pages.filter((_, i) => !indices.includes(i));
            rest.splice(to - indices.filter((i) => i < to).length, 0, ...moving);
            d.pages = rest;
        });
        S.page = Math.max(0, S.doc.pages.findIndex((p) => p.id === current));
        draw();
    },
    changeLayout(id) {
        commit((d) => { d.pages[S.page] = relayout(d.pages[S.page], id, (h) => S.assets.get(h)?.kind === 'pdf'); });
    },
    /** Put an uploaded image (or a page of a PDF) in an image place, or on the page as a new element. */
    assign(target, asset, pdfPage = 0) {
        const src = sourceSize(asset, pdfPage);
        commit((d) => {
            const p = d.pages[target.page];
            const fill = (s) => Object.assign(s, { asset: asset.hash, page: pdfPage || 0, fx: 0.5, fy: 0.5, zoom: 1 }, asset.kind === 'pdf' ? { fit: 'fit' } : {});
            if (target.free) {
                const l = lay(target.page), id = uid(), w = 0.4, h = src ? w * l.w * (src.h / src.w) / l.h : 0.3;
                p.free.push(fill({ id, t: 'image', ...slot('fit') }));
                p.ov[`free:${id}`] = { f: [0.3, clamp(0.5 - h / 2, 0.02, 0.9), w, Math.min(h, 0.9)] };
            } else {
                fill(target.key ? slotFor(p, target.key) : p.images[target.slot]);
                if (p.layout === 'sheet' && p.size && src) p.size = { w: src.w, h: src.h };
            }
        });
    },
    addFree(t) {
        const id = uid();
        commit((d) => {
            const p = d.pages[S.page];
            p.free.push(t === 'text' ? { id, t, text: 'Text', role: 'body' } : { id, t });
            p.ov[`free:${id}`] = { f: { text: [0.3, 0.46, 0.4, 0.08], line: [0.3, 0.5, 0.4, 0.012], rect: [0.35, 0.35, 0.3, 0.3] }[t] };
        });
        app.select(`free:${id}`);
    },
    removeFree(id) {
        commit((d) => { const p = d.pages[S.page]; p.free = p.free.filter((f) => f.id !== id); delete p.ov[`free:${id}`]; });
    },
    arrange(id, by) {
        commit((d) => {
            const list = d.pages[S.page].free, i = list.findIndex((f) => f.id === id), j = clamp(i + by, 0, list.length - 1);
            list.splice(j, 0, ...list.splice(i, 1));
        });
    },
    applyToSame() {
        commit((d) => {
            const src = d.pages[S.page];
            const style = Object.fromEntries(Object.entries(src.ov).filter(([k]) => !k.startsWith('free:')));
            for (const p of d.pages) {
                if (p === src || p.layout !== src.layout) continue;
                p.opts = clone(src.opts);
                p.ov = { ...clone(style), ...Object.fromEntries(Object.entries(p.ov).filter(([k]) => k.startsWith('free:'))) };
                p.images.forEach((s, i) => { if (src.images[i] && S.assets.get(s.asset)?.kind !== 'pdf') s.fit = src.images[i].fit; });
            }
        });
    },
    applyTheme(id) { commit((d) => { d.theme = resolveTheme(id, S.presets); }); },
    async preset(type, name, data) {
        const form = new FormData();
        form.set('type', type); form.set('name', name); form.set('data', JSON.stringify(data));
        try { S.presets = [...S.presets, (await uploader.send(boot.urls.presets, form)).preset]; draw(); } catch (e) { alert(e.message); }
    },
    saveTheme() {
        const name = (prompt('A name for this template', S.doc.theme.name.replace(/ — mine$/, '') + ' — mine') || '').trim();
        if (!name) return;
        commit((d) => { d.theme.name = name; });
        app.preset('theme', name, { ...S.doc.theme, name });
    },
    saveLayout() {
        const p = S.doc.pages[S.page];
        const name = (prompt('A name for this layout', LAYOUTS[p.layout].name + ' — mine') || '').trim();
        if (!name) return;
        const free = p.free.filter((f) => f.t !== 'image');
        const keep = new Set(free.map((f) => `free:${f.id}`));
        // the arrangement, not the content: places, styles and added lines and text boxes
        app.preset('layout', name, {
            layout: p.layout, opts: p.opts, orient: p.orient, free,
            ov: Object.fromEntries(Object.entries(p.ov).filter(([k]) => !k.startsWith('free:') || keep.has(k))),
            images: p.images.map((s) => slot(s.fit)),
        });
    },
    async deletePreset(p) {
        if (!confirm(`Delete “${p.name}”? Presentations already using it keep their design.`)) return;
        const res = await fetch(boot.urls.preset.replace('__ID__', p.id), { method: 'DELETE', credentials: 'same-origin', headers: uploader.headers }).catch(() => null);
        if (!res || !res.ok) return alert('It could not be deleted — check the connection.');
        S.presets = S.presets.filter((x) => x.id !== p.id);
        draw();
    },
    /** Uploaded files into sections and pages: a divider for each section that is new, then a page per image or PDF page. */
    buildPages(list, renderAs) {
        let first = null;
        commit((d) => {
            const make = (x) => {
                const one = (n) => {
                    const img = { ...slot('fit'), asset: x.asset.hash, page: n || 0 };
                    const drawing = DRAWINGS.includes(x.section) || x.asset.kind === 'pdf';
                    if (x.asset.kind === 'pdf' && x.mode !== 'layout') {
                        const src = sourceSize(x.asset, n);
                        return newPage('sheet', { images: [img], ...(x.mode === 'sheet' && src ? { size: { w: src.w, h: src.h } } : {}) });
                    }
                    if (drawing) return newPage('drawing', { images: [img], title: x.title + (x.pages && x.pages.length > 1 ? ` — ${n}` : '') });
                    return newPage(renderAs, { images: [{ ...img, fit: LAYOUTS[renderAs].fit }] });
                };
                return x.pages ? x.pages.map(one) : [one(0)];
            };
            const groups = new Map();
            for (const x of list) { const k = x.section || ''; groups.set(k, [...(groups.get(k) || []), ...make(x)]); }
            const order = [...SECTIONS.filter((s) => groups.has(s)), ...[...groups.keys()].filter((k) => !SECTIONS.includes(k))];
            // a section that exists already takes its new pages at its end; new sections go before the closing page
            for (const sec of [...order].reverse()) {
                const at = sec ? d.pages.findIndex((p) => isDivider(p) && (p.title === sec || p.title === sectionName(sec, d.lang))) : -1;
                if (at < 0) continue;
                let end = at + 1;
                while (end < d.pages.length && !isDivider(d.pages[end]) && d.pages[end].layout !== 'closing') end++;
                d.pages.splice(end, 0, ...groups.get(sec));
                first = first || groups.get(sec)[0];
                groups.delete(sec);
            }
            const tail = [];
            for (const sec of order) {
                if (!groups.has(sec)) continue;
                if (sec) tail.push(newPage('divider', { title: sectionName(sec, d.lang) }));
                tail.push(...groups.get(sec));
            }
            const last = d.pages[d.pages.length - 1];
            d.pages.splice(last && last.layout === 'closing' ? d.pages.length - 1 : d.pages.length, 0, ...tail);
            first = tail[0] || first;
        });
        if (first) { S.page = Math.max(0, S.doc.pages.findIndex((p) => p.id === first.id)); S.key = null; draw(); }
    },
    /** Issue a revision: its letter in the project's information, a line in the history, and the presentation kept as it now is. */
    async issueRevision(rev, note) {
        const key = uid();
        commit((d) => { d.info.revision = rev; d.revisions.push({ id: key, rev, date: new Date().toLocaleDateString('en-CA'), note }); });
        await saveNow();
        if (S.save !== 'saved') throw new Error('The presentation could not be saved, so no copy of this revision was kept. Check the connection and try again.');
        S.revisions = [(await call(boot.urls.revisions, 'POST', { key, label: rev, note })).revision, ...S.revisions];
        draw();
    },
    /** The presentation as it was at a revision, as a new presentation of its own (in a new tab). */
    async copyRevision(r) {
        const tab = window.open('', '_blank');   // opened now, while it is still the click: a tab opened after a wait is blocked
        try { tab.location = (await call(boot.urls.revisionCopy.replace('__ID__', r.id), 'POST')).url; } catch (e) { tab.close(); throw e; }
    },
    /** Back to how it was at a revision — one step, which Undo takes back. The revision history stays as it is now. */
    async restoreRevision(r) {
        const { doc } = await call(boot.urls.revision.replace('__ID__', r.id), 'GET');
        const was = normalize(doc, S.presets);
        commit((d) => {
            const keep = { revisions: d.revisions, revision: d.info.revision };
            for (const k of Object.keys(d)) delete d[k];
            Object.assign(d, was, { revisions: keep.revisions });
            d.info.revision = keep.revision;
        });
        S.page = Math.min(S.page, Math.max(0, S.doc.pages.length - 1)); S.key = null;
        draw();
    },
    /** A line out of the revision history, with the copy kept for it. */
    async deleteRevision(entry, stored) {
        if (stored) await call(boot.urls.revision.replace('__ID__', stored.id), 'DELETE');
        S.revisions = S.revisions.filter((x) => x !== stored);
        commit((d) => { d.revisions = d.revisions.filter((x) => x.id !== entry.id); });
    },
    /** The pages picked in the list, as "1-3, 6". */
    picksRange() {
        const idx = S.doc.pages.map((p, i) => (S.picks.has(p.id) ? i + 1 : 0)).filter(Boolean);
        const parts = [];
        for (let i = 0; i < idx.length; i++) {
            let j = i;
            while (idx[j + 1] === idx[j] + 1) j++;
            parts.push(j > i ? `${idx[i]}-${idx[j]}` : `${idx[i]}`);
            i = j;
        }
        return parts.join(', ');
    },
};
const DRAWINGS = ['Site Plan', 'Floor Plans', 'Elevations', 'Sections'];
async function call(url, method, body) {
    const res = await fetch(url, { method, credentials: 'same-origin', headers: { ...uploader.headers, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined }).catch(() => null);
    if (!res) throw new Error('The connection dropped — try again.');
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(res.status === 401 || res.status === 419 ? 'You have been signed out. Sign in to the admin in another tab, then try again.' : data.message || `It did not work (${res.status}).`);
    }
    return res.status === 204 ? {} : res.json();
}

// ── Keyboard ──
window.addEventListener('keydown', (e) => {
    if (brandMode) return;
    const mod = e.ctrlKey || e.metaKey, typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName);
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); saveNow(); return; }
    if (typing) return;
    if (mod && e.key.toLowerCase() === 'z') { e.preventDefault(); step(e.shiftKey ? 1 : -1); return; }
    if (mod && e.key.toLowerCase() === 'y') { e.preventDefault(); step(1); return; }
    if (e.key === 'Escape') { if (S.dlg) app.dialog(null); else app.select(null); return; }
    if (S.dlg || !S.doc.pages.length) return;
    const page = S.doc.pages[S.page];
    if (!S.key) {
        if (e.key === 'PageDown' || e.key === 'ArrowDown') { S.page = clamp(S.page + 1, 0, S.doc.pages.length - 1); draw(); e.preventDefault(); }
        if (e.key === 'PageUp' || e.key === 'ArrowUp') { S.page = clamp(S.page - 1, 0, S.doc.pages.length - 1); draw(); e.preventDefault(); }
        return;
    }
    const key = S.key, fr = lay(S.page).frames[key];
    if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        if (key.startsWith('free:')) app.removeFree(key.slice(5));
        else if (key.startsWith('img:')) commit((d) => Object.assign(d.pages[S.page].images[+key.slice(4)], { asset: null, page: 0 }));
        else commit((d) => { const p = d.pages[S.page]; p.ov[key] = { ...p.ov[key], hide: true }; });
        return;
    }
    const by = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
    if (by && fr) {
        e.preventDefault();
        const n = e.shiftKey ? 10 : 1, L = lay(S.page);
        commit((d) => { const p = d.pages[S.page]; p.ov[key] = { ...p.ov[key], f: [(fr.x + by[0] * n) / L.w, (fr.y + by[1] * n) / L.h, fr.w / L.w, fr.h / L.h] }; }, `${page.id}:${key}:nudge`);
    }
});

// ── Top bar ──
const Btn = ({ on, children, kind = '', title, disabled }) => html`<button type="button" class=${`pm-btn ${kind}`} title=${title} disabled=${disabled} onClick=${on}>${children}</button>`;
function TopBar() {
    const status = { saved: 'Saved', dirty: 'Unsaved changes…', saving: 'Saving…', error: S.saveError }[S.save];
    const leave = async (e) => { if (S.save !== 'saved') { e.preventDefault(); await saveNow(); if (S.save === 'saved') location.href = boot.urls.list; } };
    return html`<header class="pm-top">
        <a class="pm-back" href=${boot.urls.list} onClick=${leave} title="Back to all presentations">←</a>
        <input class="pm-name" value=${S.name} maxlength="160" aria-label="The presentation's name" onInput=${(e) => { S.name = e.target.value; dirty(); }} />
        <span class=${`pm-save is-${S.save}`} role="status">${status}</span>
        <span class="pm-sp"></span>
        <${Btn} kind="is-icon" title="Undo (Ctrl+Z)" disabled=${hist.i <= 0} on=${() => step(-1)}>↶<//>
        <${Btn} kind="is-icon" title="Redo (Ctrl+Shift+Z)" disabled=${hist.i >= hist.stack.length - 1} on=${() => step(1)}>↷<//>
        <select class="pm-in pm-zoom" value=${S.zoom} onChange=${(e) => { S.zoom = e.target.value; draw(); }} aria-label="Zoom">
            ${[['fit', 'Fit'], ['0.5', '50%'], ['0.75', '75%'], ['1', '100%'], ['1.5', '150%'], ['2', '200%']].map(([v, l]) => html`<option value=${v} selected=${S.zoom === v}>${l}</option>`)}
        </select>
        <${Btn} on=${() => app.openImport()}>Upload<//>
        <${Btn} on=${() => app.dialog({ type: 'preview' })}>Preview<//>
        <${Btn} on=${() => app.dialog({ type: 'present' })} title="Full screen, a page at a time — for showing it to the client" disabled=${!S.doc.pages.length}>Present<//>
        <${Btn} kind="is-primary" on=${() => app.dialog({ type: 'export' })}>Export<//>
    </header>`;
}

// ── The pages, in their sections ──
function Pages() {
    const [drop, setDrop] = useState(null);
    const drag = useRef(null);
    const st = structure(S.doc);
    const sectionRange = (i) => { let end = i + 1; while (end < S.doc.pages.length && !isDivider(S.doc.pages[end])) end++; return Array.from({ length: end - i }, (_, n) => i + n); };
    const start = (e, indices) => { drag.current = indices; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'pages'); };
    const over = (e, i) => { e.preventDefault(); const r = e.currentTarget.getBoundingClientRect(); setDrop(e.clientY < r.top + r.height / 2 ? i : i + 1); };
    const land = (e) => {
        e.preventDefault();
        if (e.dataTransfer.files.length) app.openImport(e.dataTransfer.files);
        else if (drag.current && drop != null) app.movePages(drag.current, drop);
        drag.current = null;
        setDrop(null);
    };
    const click = (e, i) => {
        const id = S.doc.pages[i].id;
        if (e.ctrlKey || e.metaKey) { const next = new Set(S.picks); if (next.has(id)) next.delete(id); else next.add(id); S.picks = next; }
        else if (e.shiftKey) { const [a, b] = [Math.min(S.page, i), Math.max(S.page, i)]; S.picks = new Set(S.doc.pages.slice(a, b + 1).map((p) => p.id)); }
        else S.picks = new Set([id]);
        S.page = i; S.key = null; S.crop = false;
        draw();
    };
    const picked = () => S.doc.pages.map((p, i) => (S.picks.has(p.id) ? i : -1)).filter((i) => i >= 0);

    return html`<aside class="pm-left" onDragOver=${(e) => e.preventDefault()} onDrop=${land}>
        <div class="pm-left__top">
            <${Btn} kind="is-primary" on=${() => app.dialog({ type: 'add' })}>+ Add page<//>
            <${Btn} on=${() => app.openImport()}>Upload<//>
        </div>
        <div class="pm-pages">
            ${S.doc.pages.map((p, i) => {
        const L = lay(i), w = L.w >= L.h ? 176 : 124, on = i === S.page, pick = S.picks.has(p.id) && S.picks.size > 1;
        return html`
                ${isDivider(p) && html`<div class="pm-sec" draggable="true" title="Drag to move the whole section" onDragStart=${(e) => start(e, sectionRange(i))}>
                    <span>${String(st[i].dividerNo).padStart(2, '0')}</span>${p.title || 'Section'}</div>`}
                ${drop === i && html`<div class="pm-dropline"></div>`}
                <div class=${`pm-thumb${on ? ' is-on' : ''}${pick ? ' is-pick' : ''}`} draggable="true"
                    onDragStart=${(e) => start(e, S.picks.has(p.id) && S.picks.size > 1 ? picked() : [i])} onDragOver=${(e) => over(e, i)} onClick=${(e) => click(e, i)}>
                    <span class="pm-thumb__n">${i + 1}</span>
                    <div class="pm-thumb__p"><${PageView} lay=${L} scale=${w / L.w} media=${media} small=${true} />
                        ${L.warnings.some((x) => x.type === 'lowres') && html`<i class="pm-thumb__w" title="An image here is too small for its printed size">!</i>`}
                        <span class="pm-thumb__tools">
                            <button type="button" title="Duplicate this page" onClick=${(e) => { e.stopPropagation(); app.duplicatePage(i); }}>⧉</button>
                            <button type="button" title="Delete" onClick=${(e) => { e.stopPropagation(); app.deletePages(pick ? picked() : [i]); }}>×</button>
                        </span>
                    </div>
                </div>`;
    })}
            ${drop === S.doc.pages.length && html`<div class="pm-dropline"></div>`}
        </div>
        <p class="pm-left__hint">Drag pages to reorder. Ctrl-click to pick several.</p>
    </aside>`;
}

// ── The page being worked on ──
const HANDLES = { text: ['w', 'e'], group: ['w', 'e'], line: ['w', 'e', 'n', 's'] };
const ALL = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];
function Stage() {
    const wrap = useRef(), sheet = useRef();
    const [box, setBox] = useState({ w: 900, h: 600 });
    useEffect(() => {
        const ro = new ResizeObserver(([e]) => setBox({ w: e.contentRect.width, h: e.contentRect.height }));
        ro.observe(wrap.current);
        return () => ro.disconnect();
    }, []);
    if (!S.doc.pages.length) {
        return html`<main class="pm-stage" ref=${wrap}><div class="pm-blank"><p>This presentation has no pages yet.</p><${Btn} kind="is-primary" on=${() => app.dialog({ type: 'add' })}>Add the first page<//></div></main>`;
    }
    const L = lay(S.page), page = S.doc.pages[S.page];
    const scale = S.zoom === 'fit' ? Math.max(0.05, Math.min((box.w - 4) / L.w, (box.h - 4) / L.h)) : +S.zoom;
    const fr = S.key ? L.frames[S.key] : null;
    const cropItem = S.crop && S.key ? L.items.find((it) => it.t === 'image' && it.key === S.key && it.fit === 'fill') : null;

    const point = (e) => { const r = sheet.current.getBoundingClientRect(); return { x: (e.clientX - r.left) / scale, y: (e.clientY - r.top) / scale }; };
    const hit = (p) => {
        const keys = Object.keys(L.frames), pad = 3 / scale;
        for (let i = keys.length - 1; i >= 0; i--) {
            const f = L.frames[keys[i]];
            if (p.x >= f.x - pad && p.x <= f.x + f.w + pad && p.y >= f.y - pad && p.y <= f.y + f.h + pad) return keys[i];
        }
        return null;
    };
    /** Pull a moving box onto the content area's edges and the page's centre lines when it comes close. */
    const snap = (x, y, w, h) => {
        const tol = 5 / scale, c = L.content, guides = [];
        const xs = [[c.x, 0], [c.x + c.w, 1], [L.w / 2, 0.5], [c.x + c.w / 2, 0.5]], ys = [[c.y, 0], [c.y + c.h, 1], [L.h / 2, 0.5]];
        for (const [gx, a] of xs) if (Math.abs(x + w * a - gx) < tol) { x = gx - w * a; guides.push({ x: gx }); break; }
        for (const [gy, a] of ys) if (Math.abs(y + h * a - gy) < tol) { y = gy - h * a; guides.push({ y: gy }); break; }
        S.guides = guides;
        return [x, y];
    };
    function down(e) {
        if (e.button !== 0) return;
        const handle = e.target.dataset ? e.target.dataset.h : null, p0 = point(e);
        const key = handle ? S.key : hit(p0);
        if (!handle) { if (key !== S.key) S.crop = false; S.key = key; if (key) S.tab = 'page'; draw(); }
        if (!key || !L.frames[key]) return;
        e.preventDefault();
        const f0 = L.frames[key], base = S.doc, s0 = slotFor(page, key);
        const item = L.items.find((it) => it.t === 'image' && it.key === key);
        const panning = S.crop && !handle && item && item.fit === 'fill' && s0;
        let last = null;
        const move = (ev) => {
            const p = point(ev), dx = p.x - p0.x, dy = p.y - p0.y;
            if (!last && Math.hypot(dx, dy) * scale < 3) return;
            if (panning) {
                const ex = item.dw - item.w, ey = item.dh - item.h;
                const fx = clamp((s0.fx ?? 0.5) - (ex > 0.5 ? dx / ex : 0)), fy = clamp((s0.fy ?? 0.5) - (ey > 0.5 ? dy / ey : 0));
                last = (d) => Object.assign(slotFor(d.pages[S.page], key), { fx, fy });
            } else {
                let { x, y, w, h } = f0;
                if (handle) {
                    if (handle.includes('e')) w = Math.max(10, f0.w + dx);
                    if (handle.includes('s')) h = Math.max(10, f0.h + dy);
                    if (handle.includes('w')) { w = Math.max(10, f0.w - dx); x = f0.x + f0.w - w; }
                    if (handle.includes('n')) { h = Math.max(10, f0.h - dy); y = f0.y + f0.h - h; }
                } else [x, y] = snap(x + dx, y + dy, w, h);
                last = (d) => { const pg = d.pages[S.page]; pg.ov[key] = { ...pg.ov[key], f: [x / L.w, y / L.h, w / L.w, h / L.h] }; };
            }
            const next = clone(base);
            last(next);
            S.doc = next;
            draw();
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            S.guides = [];
            if (last) { S.doc = base; commit(last); } else draw();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }
    const dbl = (e) => {
        const key = hit(point(e));
        if (!key) return;
        const f = L.frames[key];
        if (f.kind === 'image' && f.empty) app.openLibrary(key.startsWith('img:') ? { page: S.page, slot: +key.slice(4) } : { page: S.page, key });
        else if (f.kind === 'image' && f.fit === 'fill') { S.key = key; S.crop = true; draw(); }
        else if (f.kind === 'map') app.dialog({ type: 'site' });
    };
    const wheel = (e) => {
        if (!cropItem) return;
        e.preventDefault();
        commit((d) => { const s = slotFor(d.pages[S.page], S.key); s.zoom = clamp((s.zoom || 1) * (e.deltaY < 0 ? 1.06 : 0.94), 1, 4); }, `${page.id}:${S.key}:zoom`);
    };
    const drop = (e) => {
        e.preventDefault();
        if (!e.dataTransfer.files.length) return;
        const key = hit(point(e)), f = key && L.frames[key];
        if (f && f.kind === 'image') app.openImport([e.dataTransfer.files[0]], key.startsWith('img:') ? { page: S.page, slot: +key.slice(4) } : { page: S.page, key });
        else app.openImport(e.dataTransfer.files);
    };

    return html`<main class="pm-stage" ref=${wrap} onPointerDown=${down} onDblClick=${dbl} onWheel=${wheel} onDragOver=${(e) => e.preventDefault()} onDrop=${drop}>
        <div class="pm-stage__in" style=${{ width: px(L.w * scale), height: px(L.h * scale) }} ref=${sheet}>
            <${PageView} lay=${L} scale=${scale} media=${media} />
            ${cropItem && html`<img class="pm-ghost" alt="" draggable=${false} src=${media.url(cropItem.hash, 'preview', cropItem.pdfPage)}
                style=${{ left: px(cropItem.dx * scale), top: px(cropItem.dy * scale), width: px(cropItem.dw * scale), height: px(cropItem.dh * scale) }} />`}
            ${fr && html`<div class=${`pm-sel${cropItem ? ' is-crop' : ''}`} style=${{ left: px(fr.x * scale), top: px(fr.y * scale), width: px(fr.w * scale), height: px(fr.h * scale) }}>
                ${!cropItem && (HANDLES[fr.kind] || ALL).map((h) => html`<i class=${`pm-hd pm-hd--${h}`} data-h=${h}></i>`)}
                ${cropItem && html`<span class="pm-sel__tip">Drag to choose what shows · scroll to zoom · Esc when done</span>`}
            </div>`}
            ${S.guides.map((g) => html`<div class="pm-guide" style=${g.x != null ? { left: px(g.x * scale), top: 0, bottom: 0, width: '1px' } : { top: px(g.y * scale), left: 0, right: 0, height: '1px' }}></div>`)}
        </div>
    </main>`;
}

function Right() {
    const tabs = [['page', 'Page'], ['project', 'Project'], ['design', 'Design']];
    return html`<aside class="pm-right">
        <nav class="pm-tabs">${tabs.map(([id, l]) => html`<button type="button" class=${S.tab === id ? 'is-on' : ''} onClick=${() => app.tab(id)}>${l}</button>`)}</nav>
        <div class="pm-right__body">
            ${S.tab === 'page' && html`<${PageTab} app=${app} />`}
            ${S.tab === 'project' && html`<${ProjectTab} app=${app} />`}
            ${S.tab === 'design' && html`<${DesignTab} app=${app} />`}
        </div>
    </aside>`;
}

/** Branding on its own: the settings, and two sample pages to see the logos on. */
function BrandPage() {
    const [theme, setTheme] = useState('minimal-white');
    useEffect(() => { S.doc = { ...S.doc, theme: resolveTheme(theme, S.presets) }; refresh(); }, [theme]);
    return html`<div class="pm-app pm-app--brand">
        <header class="pm-top">
            <a class="pm-back" href=${boot.urls.list} title="Back to all presentations">←</a>
            <strong class="pm-name">Branding & templates</strong>
            <span class=${`pm-save is-${S.save}`} role="status">${{ saved: 'Saved', dirty: 'Saving…', error: S.saveError }[S.save]}</span>
        </header>
        <div class="pm-brandwrap">
            <${BrandPanel} app=${app} />
            <div class="pm-brandprev">
                <div class="pm-seg">${[['minimal-white', 'On white'], ['editorial', 'On paper'], ['dark-portfolio', 'On dark']].map(([id, l]) => html`<button type="button" class=${theme === id ? 'is-on' : ''} onClick=${() => setTheme(id)}>${l}</button>`)}</div>
                ${S.doc.pages.map((_, i) => { const L = lay(i, false); return html`<${PageView} lay=${L} scale=${Math.min(1, 520 / L.w)} media=${media} />`; })}
                <label class="pm-tg"><input type="checkbox" checked=${!!S.doc.partner} onChange=${(e) => { S.doc = { ...S.doc, partner: e.target.checked ? ((S.brand.partners || [])[0] || {}).id || null : null }; refresh(); }} /><span>Show a partner's logo in this sample</span></label>
            </div>
        </div>
    </div>`;
}

function App() {
    if (brandMode) return html`<${BrandPage} />`;
    const d = S.dlg, close = () => app.dialog(null);
    return html`<div class="pm-app">
        <${TopBar} /><${Pages} /><${Stage} /><${Right} />
        ${d && d.type === 'add' && html`<${AddPageDialog} app=${app} onClose=${close} />`}
        ${d && d.type === 'layout' && html`<${LayoutDialog} app=${app} onClose=${close} />`}
        ${d && d.type === 'present' && html`<${PresentDialog} app=${app} onClose=${close} />`}
        ${d && d.type === 'import' && html`<${ImportDialog} key=${d.n} app=${app} files=${d.files} target=${d.target} onClose=${close} />`}
        ${d && d.type === 'library' && html`<${LibraryDialog} app=${app} target=${d.target} onClose=${close} />`}
        ${d && d.type === 'preview' && html`<${PreviewDialog} app=${app} onClose=${close} />`}
        ${d && d.type === 'export' && html`<${ExportDialog} key=${d.n} app=${app} onClose=${close} />`}
        ${d && d.type === 'site' && html`<${SiteDialog} app=${app} onClose=${close} />`}
    </div>`;
}

// ── Start ──
(async () => {
    initText(boot.urls.fonts, refresh);
    S.doc = normalize(brandMode
        ? { theme: 'minimal-white', info: { project: 'Project name', client: 'Client', location: 'Location', stage: 'Stage' }, pages: [newPage('cover', { opts: { variant: 'logo' } }), newPage('closing')] }
        : boot.presentation.doc, boot.presets);
    const fresh = !brandMode && !S.doc.pages.length;
    if (fresh) S.doc.pages = starterPages(S.doc);   // with a website project's details and pictures, when it was made from one
    delete S.doc.seed;
    hist.stack = [S.doc]; hist.i = 0;
    await Promise.all([
        ensureFonts(fontsOf(S.doc)).catch(() => {}),
        media.prepare([S.brand.logo, ...(S.brand.partners || []).map((p) => p.logo)]),
    ]);
    if (fresh) dirty();   // the starter pages are the presentation now
    draw();
    if (!brandMode) previewImported();
})().catch((e) => { root.innerHTML = `<p class="pm-boot">The editor could not start: ${String(e.message || e).replace(/</g, '&lt;')}</p>`; });

/** Pictures brought in from a website project arrive without previews; they are made here, as for an upload. */
async function previewImported() {
    for (const a of [...S.assets.values()].filter((x) => !x.brand && x.kind === 'image' && !(x.meta.previews || []).length)) {
        try {
            const blob = await (await fetch(media.url(a.hash, 'original'), { credentials: 'same-origin' })).blob();
            app.addAsset(await uploader.imagePreviews(blob, a));
        } catch (e) { console.warn(`No preview for ${a.name}:`, e); }
    }
}

window.pm = app;   // for looking at the state from the browser's console
