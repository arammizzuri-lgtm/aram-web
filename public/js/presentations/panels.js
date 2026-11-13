// The editor's right-hand panels (page, project, design) and its dialogs
// (add a page, upload, pick an image, preview, export, branding).
// Everything here reads and changes the app through `app` (editor.js).
import { html, useState, useEffect, useRef, useMemo } from 'htm-preact';
import { LAYOUTS, TEXT_LABELS, SECTIONS, INFO, AREA_UNITS, FONTS, THEMES, PRESETS, LOW_PPI, PAGE_MM, COVER_STYLES, DIVIDER_STYLES, LANGS,
    newPage, slot, uid, clone, resolveTheme, pageSize, mm, isoName, parseRange, fileName, bytes, sourceSize, structure, relayout, sectionName, nextRevision, fmtDate,
    word, toNum, fmtNum, areaTotals } from '@pm/model';
import { ROLE_NAMES, layoutPage } from '@pm/layout';
import { PageView } from '@pm/view';
import { guessSection, titleFromName } from '@pm/uploads';
import { exportPdf, exportImages, download, downloadBlob, JPG_SIZES } from '@pm/export';
import { Q, MAP_LOOKS, TILESETS, mapStyle, world, latLng, onMap, tilesFor, tileUrl, plotArea, distance, fmtDistance, fmtCoords } from '@pm/map';

// ── Small form pieces ──
const Field = ({ label, hint, children, row }) => html`<label class=${`pm-f${row ? ' pm-f--row' : ''}`}>
    ${label && html`<span class="pm-f__l">${label}</span>`}${children}${hint && html`<span class="pm-f__h">${hint}</span>`}
</label>`;
const Text = ({ value, on, placeholder, area, type, dir = 'auto' }) => (area
    ? html`<textarea class="pm-in" rows=${area} dir=${dir} placeholder=${placeholder} value=${value || ''} onInput=${(e) => on(e.target.value)}></textarea>`
    : html`<input class="pm-in" type=${type || 'text'} dir=${type ? null : dir} placeholder=${placeholder} value=${value || ''} onInput=${(e) => on(e.target.value)} />`);
const Select = ({ value, on, options }) => html`<select class="pm-in" value=${value ?? ''} onChange=${(e) => on(e.target.value)}>
    ${options.map(([v, l]) => html`<option value=${v} selected=${String(v) === String(value ?? '')}>${l}</option>`)}
</select>`;
const Toggle = ({ value, on, label }) => html`<label class="pm-tg"><input type="checkbox" checked=${!!value} onChange=${(e) => on(e.target.checked)} /><span>${label}</span></label>`;
const Seg = ({ value, on, options }) => html`<div class="pm-seg">${options.map(([v, l, title]) => html`<button type="button" title=${title} class=${String(v) === String(value) ? 'is-on' : ''} onClick=${() => on(v)}>${l}</button>`)}</div>`;
const Range = ({ value, on, min, max, step = 1, show }) => html`<div class="pm-rg"><input type="range" min=${min} max=${max} step=${step} value=${value} onInput=${(e) => on(+e.target.value)} /><span>${show ? show(value) : value}</span></div>`;
const Color = ({ value, on }) => html`<span class="pm-col"><input type="color" value=${value} onInput=${(e) => on(e.target.value)} /><input class="pm-in" value=${value} onChange=${(e) => /^#[0-9a-f]{6}$/i.test(e.target.value) && on(e.target.value.toLowerCase())} /></span>`;
const Btn = ({ on, children, kind = '', title, disabled }) => html`<button type="button" class=${`pm-btn ${kind}`} title=${title} disabled=${disabled} onClick=${on}>${children}</button>`;
const Group = ({ title, children, open = true }) => html`<details class="pm-g" open=${open}><summary>${title}</summary><div class="pm-g__b">${children}</div></details>`;
const Dialog = ({ title, onClose, children, wide, foot }) => html`<div class="pm-dlg" onMouseDown=${(e) => e.target === e.currentTarget && onClose()}>
    <div class=${`pm-dlg__box${wide ? ' pm-dlg__box--wide' : ''}`} role="dialog" aria-label=${title}>
        <header><h2>${title}</h2><button type="button" class="pm-x" aria-label="Close" onClick=${onClose}>×</button></header>
        <div class="pm-dlg__body">${children}</div>
        ${foot && html`<footer>${foot}</footer>`}
    </div>
</div>`;

// ── Designs shown as they will look ──
// The real layout engine draws a sample page in this presentation's template, with its project's
// details and, where pictures go, this presentation's own pictures. Only the picture is sampled:
// a page that is added is added empty.
const SAMPLE_TITLE = { drawing: 'Ground Floor Plan', 'two-up': 'Exterior Views', grid: 'Exterior Views', materials: 'Materials & Finishes', 'image-caption': 'Title' };
function pictures(S) {
    const logos = new Set([S.brand.logo, ...(S.brand.partners || []).map((p) => p.logo)]);
    const ready = [...S.assets.values()].filter((a) => !logos.has(a.hash) && (a.meta.previews || []).length);
    return {
        renders: ready.filter((a) => a.kind === 'image').map((a) => ({ asset: a.hash, page: 0 })),
        drawings: ready.filter((a) => a.kind === 'pdf').map((a) => ({ asset: a.hash, page: a.meta.previews.find((n) => n > 0) || 1 })),
    };
}
function sampled(page, pics, doc) {
    const p = clone(page), L = LAYOUTS[p.layout] || LAYOUTS.blank;
    const drawing = p.layout === 'drawing' || p.layout === 'sheet';
    const all = drawing && pics.drawings.length ? pics.drawings : pics.renders;
    // the page's own pictures are not offered again for its empty places, unless there are no others
    const used = new Set(p.images.map((s) => s.asset).filter(Boolean));
    const others = all.filter((x) => !used.has(x.asset));
    const pool = others.length ? others : all;
    let n = 0;
    if (p.layout !== 'divider') p.images.forEach((s) => { if (!s.asset && pool.length) Object.assign(s, pool[n++ % pool.length], drawing ? { fit: 'fit' } : {}); });
    if (L.texts.includes('title') && !p.title) p.title = p.layout === 'divider' ? sectionName('Floor Plans', doc.lang) : p.layout === 'overview' ? 'Project Overview' : SAMPLE_TITLE[p.layout] || '';
    if (L.texts.includes('body') && !p.body) p.body = 'A few words about the project — the brief, the site and the idea behind the design.';
    if (L.texts.includes('caption') && !p.caption) p.caption = 'A caption under the picture';
    if (L.captions) p.images.forEach((s) => { s.caption = s.caption || 'Caption'; });
    if (p.layout === 'areas' && !(p.areas || []).some((f) => f.rooms.length)) {
        const floor = (name, rooms) => ({ id: name, name, rooms: rooms.map(([n, a], i) => ({ id: `${name}${i}`, name: n, area: String(a) })) });
        p.areas = [floor('Ground floor', [['Living room', 46], ['Kitchen', 22], ['Guest room', 18], ['Bathroom', 6]]), floor('First floor', [['Bedroom', 24], ['Bedroom', 20], ['Bathroom', 8]])];
    }
    return p;
}
/** A page drawn small as it would be at `at` (inserted there, or in place of the page there). */
export function DesignThumb({ app, page, at, replace = false, w = 180 }) {
    const { S } = app;
    const lay = useMemo(() => {
        const pages = S.doc.pages.slice();
        pages.splice(at, replace ? 1 : 0, sampled(page, pictures(S), S.doc));
        return layoutPage({ ...S.doc, pages }, Math.min(at, pages.length - 1), app.env(true));
    }, [S.doc, page, at, replace, S.assets.size]);
    const width = lay.w >= lay.h ? w : w * 0.7;
    return html`<div class="pm-design__p"><${PageView} lay=${lay} scale=${width / lay.w} media=${app.media} small=${true} /></div>`;
}
/** A choice between styles, each shown as `page` would look in it (drawn in place of the current page). */
function StylePick({ app, options, value, page, on }) {
    const pages = useMemo(() => options.map(([v]) => ({ ...page, opts: { ...page.opts, variant: v } })), [page]);
    return html`<div class="pm-styles">${options.map(([v, label, hint], i) => html`<button type="button" title=${hint} class=${v === value ? 'is-on' : ''} onClick=${() => on(v)}>
        <${DesignThumb} app=${app} page=${pages[i]} at=${app.S.page} replace=${true} w=${84} /><span>${label}</span></button>`)}</div>`;
}
const SAMPLE = { cover: newPage('cover'), divider: newPage('divider') };

const KEY_NAMES = { title: 'Title', subtitle: 'Subtitle', caption: 'Caption', body: 'Text', notes: 'Notes', project: 'Project name', docTitle: 'Document title',
    stage: 'Stage', date: 'Date', info: 'Project facts', facts: 'Key facts', logo: 'Logo', where: 'Client & location', issue: 'Issue line',
    confidential: 'Confidentiality note', contact: 'Contact details', number: 'Section number', toc: 'Contents list', meta: 'Drawing details', north: 'North arrow', list: 'The list',
    map: 'Map', cityMap: 'City map', address: 'Address', coords: 'Coordinates', places: 'Places around the site',
    qr: 'QR code', areas: 'Area schedule', total: 'Total area' };
export function keyName(key, page) {
    if (key.startsWith('img:')) return `Image ${+key.slice(4) + 1}`;
    if (key.startsWith('cap:')) return `Caption ${+key.slice(4) + 1}`;
    if (key.startsWith('free:')) {
        const f = page.free.find((x) => `free:${x.id}` === key);
        return f ? { text: 'Text box', image: 'Image', rect: 'Rectangle', line: 'Line' }[f.t] : 'Element';
    }
    return KEY_NAMES[key] || 'Element';
}
const TOKENS_HINT = 'Linked fields: {project} {client} {stage} {date} {revision} {section} {page} {pages} {studio} {website}';

// ── The selected element ──
function ElementBox({ app }) {
    const { S } = app, page = S.doc.pages[S.page], key = S.key, lay = app.lay(S.page);
    const fr = lay.frames[key], ov = page.ov[key] || {}, free = key.startsWith('free:') ? page.free.find((f) => `free:${f.id}` === key) : null;
    if (!fr && !free) return null;
    const setOv = (patch, tag) => app.commit((d) => { const p = d.pages[S.page]; p.ov[key] = { ...p.ov[key], ...patch }; }, tag && `${page.id}:${key}:${tag}`);
    const setFree = (patch, tag) => app.commit((d) => Object.assign(d.pages[S.page].free.find((f) => f.id === free.id), patch), tag && `${page.id}:${key}:${tag}`);
    const kind = fr ? fr.kind : free.t;
    const isText = kind === 'text' || kind === 'group';
    const th = S.doc.theme.colors;
    const alignTo = (ax, ay) => app.commit((d) => {
        const p = d.pages[S.page], c = lay.content, f = [fr.x, fr.y, fr.w, fr.h];
        if (ax != null) f[0] = c.x + (c.w - fr.w) * ax;
        if (ay != null) f[1] = c.y + (c.h - fr.h) * ay;
        p.ov[key] = { ...p.ov[key], f: [f[0] / lay.w, f[1] / lay.h, f[2] / lay.w, f[3] / lay.h] };
    });
    const field = { title: 1, subtitle: 1, caption: 1, body: 1, notes: 1 }[key] ? key : null;

    return html`<section class="pm-el">
        <header><strong>${keyName(key, page)}</strong>
            ${free ? html`<${Btn} kind="is-danger is-sm" on=${() => app.removeFree(free.id)}>Delete<//>`
        : html`<${Btn} kind="is-sm" on=${() => { setOv({ hide: true }); app.select(null); }} title="Take it off this page (it can be put back below)">Hide<//>`}
        </header>
        ${field && html`<${Field} label="Words"><${Text} area=${key === 'body' || key === 'notes' ? 6 : 2} value=${page[field]} on=${(v) => app.commit((d) => { d.pages[S.page][field] = v; }, `${page.id}:${field}`)} /><//>`}
        ${free && free.t === 'text' && html`
            <${Field} label="Words" hint=${TOKENS_HINT}><${Text} area=${4} value=${free.text} on=${(v) => setFree({ text: v }, 'text')} /><//>
            <${Field} label="Style"><${Select} value=${free.role || 'body'} on=${(v) => setFree({ role: v })} options=${Object.entries(ROLE_NAMES)} /><//>`}
        ${free && free.t === 'rect' && html`
            <${Field} label="Colour"><${Color} value=${free.fill || th.line} on=${(v) => setFree({ fill: v }, 'fill')} /><//>
            <${Field} label="Opacity"><${Range} min=${5} max=${100} step=${5} value=${Math.round((free.opacity ?? 1) * 100)} on=${(v) => setFree({ opacity: v / 100 }, 'op')} show=${(v) => `${v}%`} /><//>`}
        ${free && free.t === 'line' && html`
            <${Field} label="Colour"><${Color} value=${free.color || th.text} on=${(v) => setFree({ color: v }, 'color')} /><//>
            <${Field} label="Weight"><${Range} min=${0.25} max=${6} step=${0.25} value=${free.lw || 0.75} on=${(v) => setFree({ lw: v }, 'lw')} show=${(v) => `${v} pt`} /><//>`}
        ${isText && html`
            <${Field} label="Size"><${Range} min=${50} max=${250} step=${5} value=${Math.round((ov.sz || 1) * 100)} on=${(v) => setOv({ sz: v / 100 }, 'sz')} show=${(v) => `${v}%`} /><//>
            <${Field} label="Align"><${Seg} value=${ov.align || ''} on=${(v) => setOv({ align: v || undefined })} options=${[['', 'Auto'], ['left', 'Left'], ['center', 'Centre'], ['right', 'Right']]} /><//>
            <${Field} label="Colour"><div class="pm-sw">
                ${[['', 'Template'], [th.text, 'Text'], [th.muted, 'Muted'], [th.accent, 'Accent']].map(([c, l]) => html`<button type="button" class=${(ov.color || '') === c ? 'is-on' : ''} title=${l} style=${{ background: c || 'transparent' }} onClick=${() => setOv({ color: c || undefined })}>${c ? '' : '∅'}</button>`)}
                <input type="color" value=${ov.color || th.text} onInput=${(e) => setOv({ color: e.target.value }, 'color')} title="Another colour" />
            </div><//>
            <${Field} label="Font"><${Select} value=${ov.font || ''} on=${(v) => setOv({ font: v || undefined, weight: undefined })} options=${[['', 'Template'], ...Object.keys(FONTS).map((f) => [f, f])]} /><//>
            ${ov.font && html`<${Field} label="Weight"><${Select} value=${ov.weight || ''} on=${(v) => setOv({ weight: +v || undefined })} options=${[['', 'Template'], ...FONTS[ov.font].weights.map((w) => [w, { 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'Semibold' }[w]])]} /><//>`}
            <${Field} label="Letters"><${Seg} value=${ov.up === undefined ? '' : ov.up ? '1' : '0'} on=${(v) => setOv({ up: v === '' ? undefined : v === '1' })} options=${[['', 'Template'], ['1', 'CAPITALS'], ['0', 'As typed']]} /><//>`}
        ${kind === 'logo' && html`<${Field} label="Colour"><${Color} value=${ov.color || th.text} on=${(v) => setOv({ color: v }, 'color')} /><//>`}
        ${fr && html`<${Field} label="Place on the page"><div class="pm-seg pm-seg--icons">
            ${[['⇤', 0, null, 'Left'], ['↔', 0.5, null, 'Centre'], ['⇥', 1, null, 'Right'], ['⤒', null, 0, 'Top'], ['↕', null, 0.5, 'Middle'], ['⤓', null, 1, 'Bottom']]
        .map(([icon, ax, ay, title]) => html`<button type="button" title=${`${title} of the page's content area`} onClick=${() => alignTo(ax, ay)}>${icon}</button>`)}
        </div><//>`}
        ${free && html`<div class="pm-row"><${Btn} kind="is-sm" on=${() => app.arrange(free.id, 1)}>Bring forward<//><${Btn} kind="is-sm" on=${() => app.arrange(free.id, -1)}>Send back<//></div>`}
        ${(ov.f || ov.sz || ov.align || ov.color || ov.font || ov.up !== undefined) && !free && html`<${Btn} kind="is-sm" on=${() => app.commit((d) => { delete d.pages[S.page].ov[key]; })}>Back to the layout's place and style<//>`}
    </section>`;
}

// ── One image slot ──
function SlotBox({ app, i, page, lay, captions, capHint }) {
    const { S } = app, s = page.images[i], a = s.asset ? S.assets.get(s.asset) : null, fr = lay.frames[`img:${i}`] || {};
    const set = (patch, tag) => app.commit((d) => Object.assign(d.pages[S.page].images[i], patch), tag && `${page.id}:img${i}:${tag}`);
    return html`<div class=${`pm-slot${S.key === `img:${i}` ? ' is-on' : ''}`} onClick=${() => app.select(`img:${i}`)}>
        <div class="pm-slot__top">
            <div class="pm-slot__pic">${a ? html`<img src=${app.media.url(a.hash, 'thumb', s.page)} alt="" />` : html`<span>Empty</span>`}</div>
            <div class="pm-slot__meta">
                <strong>${a ? a.name : `Image ${i + 1}`}${a && a.kind === 'pdf' ? ` — page ${s.page}` : ''}</strong>
                ${a && a.kind === 'pdf' && html`<small>PDF drawing — stays vector${fr.k ? `, placed at ${Math.round(fr.k * 100)}% of its sheet` : ''}</small>`}
                ${a && a.kind === 'image' && fr.ppi && html`<small class=${fr.ppi < LOW_PPI ? 'is-warn' : ''}>
                    ${fr.ppi < LOW_PPI ? `Prints at ${Math.round(fr.ppi)} PPI — it will look soft. Only a larger original can fix this; a higher DPI setting adds no detail.` : `Prints at ${Math.round(fr.ppi)} PPI`}
                </small>`}
                <div class="pm-row">
                    <${Btn} kind="is-sm" on=${(e) => { e.stopPropagation(); app.openLibrary({ page: S.page, slot: i }); }}>${a ? 'Replace…' : 'Choose…'}<//>
                    ${a && html`<${Btn} kind="is-sm" on=${(e) => { e.stopPropagation(); set({ asset: null, page: 0 }); }}>Remove<//>`}
                </div>
            </div>
        </div>
        ${a && html`<div class="pm-row">
            <${Seg} value=${s.fit} on=${(v) => set({ fit: v })} options=${[['fit', 'Fit', 'The whole image, nothing cut off'], ['fill', 'Fill', 'Fills the frame; the edges that do not fit are cropped']]} />
            ${s.fit === 'fill' && html`<${Btn} kind=${`is-sm${S.crop && S.key === `img:${i}` ? ' is-on' : ''}`} on=${(e) => { e.stopPropagation(); app.cropMode(`img:${i}`); }}>Adjust crop<//>`}
        </div>`}
        ${a && s.fit === 'fill' && html`<${Field} label="Zoom" row><${Range} min=${100} max=${300} step=${5} value=${Math.round((s.zoom || 1) * 100)} on=${(v) => set({ zoom: v / 100 }, 'zoom')} show=${(v) => `${v}%`} /><//>`}
        ${captions && html`<${Text} placeholder=${capHint || 'Caption (optional)'} value=${s.caption} on=${(v) => set({ caption: v }, 'cap')} />`}
    </div>`;
}

// ── Page tab ──
export function PageTab({ app }) {
    const { S } = app, page = S.doc.pages[S.page];
    if (!page) return html`<p class="pm-empty">Add a page to begin.</p>`;
    const L = LAYOUTS[page.layout], lay = app.lay(S.page), size = pageSize(S.doc, page);
    const set = (fn, tag) => app.commit((d) => fn(d.pages[S.page], d), tag && `${page.id}:${tag}`);
    const opt = (k, v) => set((p) => { if (v === undefined || v === '') delete p.opts[k]; else p.opts[k] = v; });
    const d = page.drawing;
    const setD = (k, v, tag) => set((p) => { p.drawing[k] = v; }, tag);
    const slots = (n) => set((p) => { while (p.images.length < n) p.images.push(slot(L.fit || 'fill')); p.images.length = n; });
    const hidden = Object.keys(page.ov).filter((k) => page.ov[k].hide);
    const own = lay.frames['img:0'] && page.layout === 'sheet';
    const a0 = page.images[0] && page.images[0].asset ? S.assets.get(page.images[0].asset) : null;
    const src0 = a0 ? sourceSize(a0, page.images[0].page) : null;

    return html`
        ${S.key && html`<${ElementBox} app=${app} />`}
        <${Group} title=${L.name}>
            <${Field} label="Layout"><${Btn} kind="is-wide" on=${() => app.dialog({ type: 'layout' })} title="See this page in each layout">${L.name} — change…<//><//>
            ${L.texts.map((f) => html`<${Field} label=${page.layout === 'drawing' && f === 'title' ? 'Floor / drawing name' : TEXT_LABELS[f]}>
                <${Text} area=${f === 'body' || f === 'notes' ? 7 : f === 'caption' ? 3 : null} value=${page[f]}
                    placeholder=${f === 'body' ? 'Write the project overview here.' : f === 'title' && page.layout === 'contents' ? 'Contents' : ''}
                    on=${(v) => set((p) => { p[f] = v; }, f)} /><//>`)}
            ${page.layout === 'divider' && html`<div class="pm-chips">${SECTIONS.map((t) => sectionName(t, S.doc.lang)).map((t) => html`<button type="button" onClick=${() => set((p) => { p.title = t; })}>${t}</button>`)}</div>`}

            ${page.layout === 'cover' && html`
                <${Field} label="Cover style"><${StylePick} app=${app} options=${COVER_STYLES} value=${page.opts.variant || S.doc.theme.cover} page=${page} on=${(v) => opt('variant', v)} /><//>
                <p class="pm-note">The cover shows the project's information — fill it in under <a href="#" onClick=${(e) => { e.preventDefault(); app.tab('project'); }}>Project</a>. Empty fields leave no gap.</p>`}
            ${page.layout === 'divider' && html`
                <${Field} label="Style"><${StylePick} app=${app} options=${DIVIDER_STYLES} value=${page.opts.variant || S.doc.theme.divider} page=${page} on=${(v) => opt('variant', v)} /><//>
                <${Toggle} label="Section number" value=${page.opts.number !== false} on=${(v) => opt('number', v ? undefined : false)} />`}
            ${(page.layout === 'cover' || page.layout === 'closing') && html`
                <${Toggle} label="A QR code" value=${page.opts.qr} on=${(v) => opt('qr', v || undefined)} />
                ${page.opts.qr && html`<${QrPick} app=${app} />`}`}
            ${page.layout === 'compare' && html`<p class="pm-note">Each picture is labelled “${word(S.doc, 'existing')}” and “${word(S.doc, 'proposed')}” in the presentation's language — write another label under Images. Use the same viewpoint for both: Adjust crop lines them up.</p>`}
            ${page.layout === 'image-caption' && html`<${Field} label="Words"><${Seg} value=${page.opts.textPos || 'below'} on=${(v) => opt('textPos', v)} options=${[['below', 'Below'], ['side', 'Beside']]} /><//>`}
            ${page.layout === 'overview' && html`<${Toggle} label="Key facts from the project's information" value=${page.opts.facts !== false} on=${(v) => opt('facts', v ? undefined : false)} />`}
            ${page.layout === 'location' && html`
                <${Toggle} label="Two maps: the city, and a close-up" value=${page.opts.zooms !== 1} on=${(v) => opt('zooms', v ? undefined : 1)} />
                <${MapOpts} app=${app} page=${page} opt=${opt} set=${set} zoom=${[14, 19, 17]} />
                ${page.opts.zooms !== 1 && html`<${Field} label="City map" row><${Range} min=${9} max=${15} step=${0.5} value=${page.opts.cityZoom ?? 13} on=${(v) => set((p) => { p.opts.cityZoom = v; }, 'czoom')} show=${zoomName} /><//>`}`}
            ${(page.layout === 'cover' || page.layout === 'overview') && html`
                <${Toggle} label="A small map of the site" value=${page.opts.map} on=${(v) => opt('map', v || undefined)} />
                ${page.opts.map && html`<${MapOpts} app=${app} page=${page} opt=${opt} set=${set} zoom=${[11, 18, 15]} />`}`}
            ${page.layout === 'drawing' && html`
                <div class="pm-2"><${Field} label="Drawing no."><${Text} value=${d.number} on=${(v) => setD('number', v, 'dn')} /><//>
                <${Field} label="Area"><${Text} value=${d.area} placeholder="e.g. 245 m²" on=${(v) => setD('area', v, 'da')} /><//></div>
                <${Field} label="Scale of the drawing" hint="As drawn on its original sheet, e.g. 1:100. It is printed only for a PDF drawing — full size, or as “1:100 @ A1” when reduced from a standard sheet. It is never guessed.">
                    <${Text} value=${d.scale} placeholder="1:100" on=${(v) => setD('scale', v, 'ds')} /><//>
                <${Toggle} label="North arrow" value=${d.north} on=${(v) => setD('north', v)} />
                ${d.north && html`<${Field} label="North points" row><${Range} min=${-180} max=${180} step=${5} value=${+d.northAngle || 0} on=${(v) => setD('northAngle', v, 'na')} show=${(v) => `${v}°`} /><//>`}`}
            ${page.layout === 'sheet' && html`
                <p class="pm-note">For the drawing index — nothing is printed on the sheet itself.</p>
                <${Field} label="Drawing name"><${Text} value=${page.title} placeholder=${a0 ? titleFromName(a0.name) : ''} on=${(v) => set((p) => { p.title = v; }, 'title')} /><//>
                <div class="pm-2"><${Field} label="Drawing no."><${Text} value=${d.number} on=${(v) => setD('number', v, 'dn')} /><//>
                <${Field} label="Scale"><${Text} value=${d.scale} placeholder="1:100" on=${(v) => setD('scale', v, 'ds')} /><//></div>`}
            ${page.layout === 'sheet' && src0 && html`
                <${Toggle} label="Keep the sheet's own paper size" value=${!!page.size} on=${(v) => set((p) => { if (v) p.size = { w: src0.w, h: src0.h }; else delete p.size; })} />
                <p class="pm-note">${page.size
        ? `This page is ${mm(size.w)} × ${mm(size.h)} mm${isoName(mm(size.w), mm(size.h)) ? ` (${isoName(mm(size.w), mm(size.h))})` : ''} in the PDF — the drawing at full size, true to its scale. The other pages keep the presentation's size; a printer set to “fit to page” scales it.`
        : `The sheet is scaled to fit the presentation's page (${S.doc.size}); it is no longer at its drawn scale.`}</p>`}
        <//>

        ${page.layout === 'areas' && html`<${Group} title="Rooms and areas"><${AreasEditor} app=${app} page=${page} set=${set} /><//>`}

        ${L.images > 0 && !(page.layout === 'cover' && (page.opts.variant || S.doc.theme.cover) !== 'image') && html`<${Group} title=${page.layout === 'cover' ? 'Cover image' : page.layout === 'divider' ? 'Background image (optional)' : 'Images'}>
            ${page.images.map((s, i) => html`<${SlotBox} app=${app} i=${i} page=${page} lay=${lay} captions=${L.captions || page.layout === 'compare'}
                capHint=${page.layout === 'compare' ? `Label — “${word(S.doc, i ? 'proposed' : 'existing')}”` : null} />`)}
            ${page.layout === 'divider' && page.images[0].asset && html`<${Field} label="Darken the image" row><${Range} min=${0} max=${80} step=${5} value=${Math.round((page.opts.scrim ?? 0.45) * 100)} on=${(v) => set((p) => { p.opts.scrim = v / 100; }, 'scrim')} show=${(v) => `${v}%`} /><//>`}
            ${{ overview: [1, 3], grid: [3, 6], materials: [2, 12] }[page.layout] && html`<${Field} label="Number of images" row>
                <${Seg} value=${page.images.length} on=${slots} options=${(() => { const [a, b] = { overview: [1, 3], grid: [3, 6], materials: [2, 12] }[page.layout]; return Array.from({ length: b - a + 1 }, (_, n) => [a + n, a + n]); })()} /><//>`}
        <//>`}

        <${Group} title="Add to this page" open=${page.layout === 'blank'}>
            <div class="pm-row pm-row--wrap">
                <${Btn} kind="is-sm" on=${() => app.addFree('text')}>Text box<//><${Btn} kind="is-sm" on=${() => app.openLibrary({ page: S.page, free: true })}>Image<//>
                <${Btn} kind="is-sm" on=${() => app.addFree('line')}>Line<//><${Btn} kind="is-sm" on=${() => app.addFree('rect')}>Rectangle<//>
            </div>
            <p class="pm-note">Drag anything on the page to move it; drag its handles to resize. Arrow keys nudge.</p>
            ${hidden.length > 0 && html`<div class="pm-chips">${hidden.map((k) => html`<button type="button" onClick=${() => set((p) => { delete p.ov[k].hide; })}>Show ${keyName(k, page).toLowerCase()}</button>`)}</div>`}
        <//>

        <${Group} title="This page" open=${false}>
            <${Field} label="Header, footer & page number"><${Seg} value=${page.opts.chrome === undefined ? '' : page.opts.chrome ? '1' : '0'} on=${(v) => opt('chrome', v === '' ? undefined : v === '1')} options=${[['', 'As the layout'], ['1', 'Show'], ['0', 'Hide']]} /><//>
            ${!page.size && html`<${Field} label="Orientation" hint="A page may be turned on its own; the PDF then mixes the two, each page at its true size.">
                <${Seg} value=${page.orient || ''} on=${(v) => set((p) => { if (v) p.orient = v; else delete p.orient; })} options=${[['', 'As the presentation'], ['landscape', 'Landscape'], ['portrait', 'Portrait']]} /><//>`}
            <${Field} label="Background"><div class="pm-row"><${Color} value=${page.opts.bg || lay.bg} on=${(v) => set((p) => { p.opts.bg = v; }, 'bg')} />${page.opts.bg && html`<${Btn} kind="is-sm" on=${() => opt('bg')}>Template's<//>`}</div><//>
            <div class="pm-row pm-row--wrap">
                <${Btn} kind="is-sm" on=${() => app.applyToSame()} title="Copies this page's style choices and layout options to every page with the same layout">Apply to all “${L.name}” pages<//>
                <${Btn} kind="is-sm" on=${() => app.saveLayout()}>Save as a layout…<//>
            </div>
        <//>

        ${lay.warnings.length > 0 && html`<div class="pm-warns">${lay.warnings.map((w) => html`<p>${warningText(w)}</p>`)}</div>`}
    `;
}
/** What the QR code opens — a project's page on the website or a client's room — the same on every page that shows it. */
function QrPick({ app }) {
    const { S } = app, qr = S.doc.qr, [busy, setBusy] = useState(false), [err, setErr] = useState('');
    async function pick(to) {
        if (!to) return;
        setBusy(true); setErr('');
        try {
            const made = await app.call(`${app.boot.urls.qr}?to=${encodeURIComponent(to)}`, 'GET');
            app.commit((d) => { d.qr = made; });
        } catch (e) { setErr(e.message); }
        setBusy(false);
    }
    const options = [['', busy ? 'Making the code…' : 'Choose what it opens…'],
        ...(app.boot.pages || []).map((p) => [`project:${p.id}`, `Project page — ${p.name}`]),
        ...(app.boot.rooms || []).map((r) => [`room:${r.id}`, `Client's room — ${r.label}`])];
    if (qr && !options.some(([v]) => v === qr.to)) options.push([qr.to, qr.label]);
    return html`<${Field} label="The QR code opens"><${Select} value=${qr ? qr.to : ''} on=${pick} options=${options} /><//>
        ${err && html`<p class="pm-note is-warn">${err}</p>`}
        ${qr && html`<p class="pm-note">${qr.to.startsWith('room')
        ? html`<b>${qr.url}</b> — their room, which still asks for the room's PIN.`
        : html`<b>${qr.url}</b> — the project's page, in the language each phone is set to. Scans are counted on the dashboard as “Presentations (QR)”.`}
            The same code is on every page that shows one.</p>`}`;
}

/** The area schedule's floors and their rooms. Rows copied from a spreadsheet (a name and an area to a line) can be pasted in. */
function AreasEditor({ app, page, set }) {
    const floors = page.areas || [], unit = app.S.doc.info.builtUnit || 'm²', { sums, total } = areaTotals(floors);
    const names = useRef({}), [focus, setFocus] = useState(null);
    useEffect(() => { if (focus && names.current[focus]) { names.current[focus].focus(); setFocus(null); } });
    const blank = () => ({ id: uid(), name: '', area: '' });
    const addRoom = (fi) => { const r = blank(); set((p) => { p.areas[fi].rooms.push(r); }); setFocus(r.id); };
    const addFloor = () => { const r = blank(); set((p) => { p.areas = [...(p.areas || []), { id: uid(), name: '', rooms: [r] }]; }); setFocus(r.id); };
    const paste = (fi, ri) => (e) => {
        const t = e.clipboardData.getData('text');
        if (!/[\t\n]/.test(t.trim())) return;
        e.preventDefault();
        const rows = t.split(/\r?\n/).map((l) => l.split('\t').map((c) => c.trim())).filter((c) => c.some(Boolean)).map((c) => {
            let ai = c.findLastIndex((x, j) => j > 0 && !Number.isNaN(toNum(x)));   // the area: the last cell that is a number…
            if (ai < 1 && c.length > 1) ai = c.length - 1;                            // …or else the last cell, as written
            return { id: uid(), name: (ai > 0 ? c.slice(0, ai) : c).filter(Boolean).join(' '), area: ai > 0 ? c[ai] : '' };
        });
        set((p) => { const rooms = p.areas[fi].rooms, cur = rooms[ri]; rooms.splice(cur.name || cur.area ? ri + 1 : ri, cur.name || cur.area ? 0 : 1, ...rows); });
    };
    const enter = (fi) => (e) => { if (e.key === 'Enter') { e.preventDefault(); addRoom(fi); } };
    return html`
        ${floors.map((f, fi) => html`<div class="pm-floor" key=${f.id}>
            <div class="pm-floor__h">
                <input class="pm-in" dir="auto" value=${f.name} placeholder="Floor — e.g. Ground floor" onInput=${(e) => set((p) => { p.areas[fi].name = e.target.value; }, `af:${f.id}`)} />
                <button type="button" class="pm-x" title="Remove this floor and its rooms" onClick=${() => set((p) => { p.areas.splice(fi, 1); })}>×</button>
            </div>
            ${f.rooms.map((r, ri) => html`<div class="pm-room" key=${r.id}>
                <input class="pm-in" dir="auto" ref=${(el) => { names.current[r.id] = el; }} value=${r.name} placeholder="Room" onPaste=${paste(fi, ri)} onKeyDown=${enter(fi)}
                    onInput=${(e) => set((p) => { p.areas[fi].rooms[ri].name = e.target.value; }, `ar:${r.id}`)} />
                <input class=${`pm-in${r.area.trim() && Number.isNaN(toNum(r.area)) ? ' is-warn' : ''}`} dir="ltr" inputmode="decimal" value=${r.area} placeholder=${unit} onKeyDown=${enter(fi)}
                    title=${r.area.trim() && Number.isNaN(toNum(r.area)) ? 'Not a number: it is printed as written, and not added up' : ''}
                    onInput=${(e) => set((p) => { p.areas[fi].rooms[ri].area = e.target.value; }, `aa:${r.id}`)} />
                <button type="button" class="pm-x" title="Remove this room" onClick=${() => set((p) => { p.areas[fi].rooms.splice(ri, 1); })}>×</button>
            </div>`)}
            <div class="pm-row"><${Btn} kind="is-sm" on=${() => addRoom(fi)}>+ Room<//><span class="pm-floor__sum">${fmtNum(sums[fi])} ${unit}</span></div>
        </div>`)}
        <${Btn} kind="is-sm" on=${addFloor}>+ Floor<//>
        <p class="pm-note">Enter adds the next room. Rows copied from a spreadsheet — each room's name and its area — can be pasted into a room's name. The area's unit is the built-up area's, under Project.</p>
        ${total > 0 && html`<div class="pm-row pm-row--wrap"><strong class="pm-floor__sum">Total ${fmtNum(total)} ${unit}</strong>
            <${Btn} kind="is-sm" on=${() => app.commit((d) => { d.info.builtArea = String(Math.round(total * 100) / 100); })}>Use it as the built-up area<//></div>`}`;
}

const zoomName = (z) => `${z >= 17 ? 'Street' : z >= 15 ? 'Neighbourhood' : z >= 12 ? 'City' : 'Region'} · ${z}`;
/** How a page's map looks and how close it is. */
function MapOpts({ app, page, opt, set, zoom: [lo, hi, def] }) {
    const pinned = Number.isFinite(app.S.doc.site.lat);
    return html`
        <${Field} label="Map"><${Seg} value=${page.opts.mapLook || 'template'} on=${(v) => opt('mapLook', v === 'template' ? undefined : v)} options=${MAP_LOOKS} /><//>
        <${Field} label=${page.layout === 'location' ? 'Close-up' : 'Zoom'} row><${Range} min=${lo} max=${hi} step=${0.5} value=${page.opts.mapZoom ?? def} on=${(v) => set((p) => { p.opts.mapZoom = v; }, 'mzoom')} show=${zoomName} /><//>
        <${Btn} kind="is-sm" on=${() => app.dialog({ type: 'site' })}>${pinned ? 'The site on the map…' : 'Pin the site on the map…'}<//>
        ${page.opts.mapLook === 'satellite' && html`<p class="pm-note">Satellite pictures are Esri’s, free for non-commercial use. For client work Esri asks for an ArcGIS account (a free one will do).</p>`}`;
}
export function warningText(w) {
    if (w.type === 'nopin') return 'The map needs the site: pin it under Project → Site on the map.';
    if (w.type === 'empty') return 'An image place on this page is empty.';
    if (w.type === 'lowres') return `“${w.name}” prints at ${w.ppi} PPI here — below ${LOW_PPI} it looks soft on paper. Use a larger original or give it less space.`;
    if (w.type === 'overflow') return 'There is more text than fits — shorten it or give it more room.';
    return w.text;
}

// ── Project tab ──
export function ProjectTab({ app }) {
    const { S } = app, i = S.doc.info;
    const set = (k, v) => app.commit((d) => { d.info[k] = v; }, `info:${k}`);
    return html`
        <${Group} title="Project information">
            <p class="pm-note">Filled in once, used wherever it appears: the cover, headers and footers, the key facts. Leave a field empty to leave it out.</p>
            ${INFO.map(([k, label, ph]) => {
        if (k === 'plotArea' || k === 'builtArea') {
            const u = k === 'plotArea' ? 'plotUnit' : 'builtUnit';
            return html`<${Field} label=${label}><div class="pm-row"><${Text} dir="ltr" value=${i[k]} on=${(v) => set(k, v)} placeholder="e.g. 450" /><${Select} value=${i[u]} on=${(v) => set(u, v)} options=${AREA_UNITS.map((x) => [x, x])} /></div><//>`;
        }
        return html`<${Field} label=${label}><${Text} type=${k === 'date' ? 'date' : null} area=${k === 'confidential' ? 2 : null} value=${i[k]} placeholder=${ph} on=${(v) => set(k, v)} /><//>`;
    })}
            <${Toggle} label="Website, e-mail and phone on the cover" value=${i.showContact} on=${(v) => set('showContact', v)} />
        <//>
        <${SiteGroup} app=${app} />
        <${Group} title="Language of the pages">
            <${Select} value=${S.doc.lang} on=${(v) => app.commit((d) => { d.lang = v; })} options=${LANGS} />
            <p class="pm-note">The words the pages print themselves — “Client”, “Scale”, “Contents”, the dates — in this language. What you write is printed as you write it, so write the project’s details in it too. The layouts stay as they are, left to right.</p>
        <//>
        <${Revisions} app=${app} />
        <${Group} title="Collaboration">
            <${Field} label="Partner's logo beside yours" hint="For projects done together — on the cover and the closing page. Partners are kept under Branding.">
                <${Select} value=${S.doc.partner || ''} on=${(v) => app.commit((d) => { d.partner = v || null; })} options=${[['', 'None — only your logo'], ...(S.brand.partners || []).map((p) => [p.id, p.name])]} /><//>
        <//>
        <${Group} title="Review copy" open=${S.doc.watermark.on}>
            <${Toggle} label="Watermark across every page" value=${S.doc.watermark.on} on=${(v) => app.commit((d) => { d.watermark.on = v; })} />
            ${S.doc.watermark.on && html`<${Field} label="Words"><${Text} value=${S.doc.watermark.text} on=${(v) => app.commit((d) => { d.watermark.text = v; }, 'wm')} /><//>`}
        <//>`;
}

/** Where the site is: pinned, outlined and with the places around it, in the Site dialog. */
function SiteGroup({ app }) {
    const { S } = app, site = S.doc.site, pinned = Number.isFinite(site.lat), area = plotArea(site.plot);
    return html`<${Group} title="Site on the map" open=${pinned}>
        <p class="pm-note">${pinned
        ? [fmtCoords(site.lat, site.lng), area && `plot outline ${Math.round(area).toLocaleString('en-US')} m²`, site.places.length && `${site.places.length} ${site.places.length > 1 ? 'places' : 'place'} around it`].filter(Boolean).join(' · ')
        : 'Pin the site for a Location page, and for a small map on the cover or the overview.'}</p>
        <div class="pm-row pm-row--wrap">
            <${Btn} kind="is-sm" on=${() => app.dialog({ type: 'site' })}>${pinned ? 'Change…' : 'Pin the site…'}<//>
            ${pinned && !S.doc.pages.some((p) => p.layout === 'location') && html`<${Btn} kind="is-sm" on=${() => app.addPage(newPage('location'))}>Add a Location page<//>`}
        </div>
    <//>`;
}

// ── The site on the map ──
const ERBIL = { lat: 36.1912, lng: 44.0094 };
const MARK = '#e8b931';
const MODES = [['pin', 'Pin the site', 'Click the map where the site is.'],
    ['plot', 'Draw the plot', 'Optional. Click each corner of the plot in turn; it prints as an outline on the close-up map. Leave it empty and the pin is shown.'],
    ['place', 'Add places', 'Click a place that matters to the client — a school, a hospital, the airport — and name it. Its distance from the site is printed beside the map.']];
export function SiteDialog({ app, onClose }) {
    const { S } = app, site = S.doc.site, pinned = Number.isFinite(site.lat), info = S.doc.info;
    const [view, setView] = useState(() => ({ ...(pinned ? { lat: site.lat, lng: site.lng } : ERBIL), z: pinned ? 17 : 12 }));
    const [look, setLook] = useState('template');
    const [mode, setMode] = useState('pin');
    const [size, setSize] = useState(null);
    const [q, setQ] = useState('');
    const [msg, setMsg] = useState('');
    const [named, setNamed] = useState(null);
    const box = useRef(), names = useRef({});
    useEffect(() => {
        const ro = new ResizeObserver(([e]) => setSize({ w: e.contentRect.width, h: e.contentRect.height }));
        ro.observe(box.current);
        return () => ro.disconnect();
    }, []);
    useEffect(() => { if (named && names.current[named]) { names.current[named].focus(); setNamed(null); } }, [named, S.doc]);

    const setSite = (fn, tag) => app.commit((d) => fn(d.site, d), tag);
    const style = mapStyle(look, S.doc.theme), tpl = app.media.tiles(style);
    const m = size && { x: 0, y: 0, w: size.w * Q, h: size.h * Q, ...view, style };
    const scr = (lat, lng) => onMap(m, lat, lng).map((v) => v / Q);
    const at = (e) => { const r = box.current.getBoundingClientRect(); return [e.clientX - r.left, e.clientY - r.top]; };
    const where = ([x, y], v = view) => { const [cx, cy] = world(v.lat, v.lng, v.z); return latLng(cx + x - size.w / 2, cy + y - size.h / 2, v.z); };
    const zoomTo = (z, p = [size.w / 2, size.h / 2]) => {
        z = Math.max(3, Math.min(19, z));
        const [lat, lng] = where(p), [wx, wy] = world(lat, lng, z), c = latLng(wx - (p[0] - size.w / 2), wy - (p[1] - size.h / 2), z);
        setView({ lat: c[0], lng: c[1], z });
    };
    function click(ll) {
        const [lat, lng] = ll.map((n) => Math.round(n * 1e6) / 1e6);
        if (mode === 'pin') setSite((s) => { s.lat = lat; s.lng = lng; });
        else if (mode === 'plot') setSite((s) => { s.plot.push([lat, lng]); });
        else { const id = uid(); setSite((s) => { s.places.push({ id, name: '', lat, lng }); }); setNamed(id); }
    }
    // dragging moves the map; a click without a drag does what the mode says
    function down(e) {
        if (e.button !== 0 || !size) return;
        const start = at(e), v0 = view, [cx, cy] = world(v0.lat, v0.lng, v0.z);
        let moved = false;
        const move = (ev) => {
            const p = at(ev), dx = p[0] - start[0], dy = p[1] - start[1];
            if (!moved && Math.hypot(dx, dy) < 4) return;
            moved = true;
            const [lat, lng] = latLng(cx - dx, cy - dy, v0.z);
            setView({ ...v0, lat, lng });
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (!moved) click(where(start, v0));
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }
    async function search(e) {
        e.preventDefault();
        const t = q.trim();
        setMsg('');
        if (!t) return;
        const ll = /^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,\s]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/.exec(t);   // coordinates, as Google Maps copies them
        if (ll) return setView({ lat: +ll[1], lng: +ll[2], z: 17 });
        try {
            const hits = await (await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(t)}`, { headers: { 'Accept-Language': 'en' } })).json();
            if (hits[0]) setView({ lat: +hits[0].lat, lng: +hits[0].lon, z: 16 });
            else setMsg('Nothing found by that name. Try the district or the city, or paste the coordinates from Google Maps.');
        } catch { setMsg('The search could not be reached — check the connection.'); }
    }
    const area = plotArea(site.plot), typed = Number(String(info.plotArea || '').replace(/,/g, ''));
    const hint = MODES.find(([v]) => v === mode)[2];

    return html`<${Dialog} title="The site on the map" wide onClose=${onClose} foot=${html`<${Btn} kind="is-primary" on=${onClose}>Done<//>`}>
        <div class="pm-site">
            <div class="pm-site__map">
                <form class="pm-row" onSubmit=${search}>
                    <input class="pm-in" value=${q} onInput=${(e) => setQ(e.target.value)} placeholder="Search a place, or paste coordinates: 36.1912, 44.0094" />
                    <${Btn} kind="is-sm" on=${search}>Find<//>
                </form>
                ${msg && html`<p class="pm-note is-warn">${msg}</p>`}
                <div class=${`pm-site__box is-${mode}`} ref=${box} onPointerDown=${down} onWheel=${(e) => { e.preventDefault(); if (size) zoomTo(view.z + (e.deltaY < 0 ? 0.5 : -0.5), at(e)); }}>
                    ${m && tpl && tilesFor(m, (window.devicePixelRatio || 1) / Q).map((t) => html`<img key=${`${t.z}/${t.x}/${t.y}`} src=${tileUrl(tpl, t)} alt="" draggable=${false}
                        style=${{ left: `${t.left / Q}px`, top: `${t.top / Q}px`, width: `${t.size / Q + 0.5}px`, height: `${t.size / Q + 0.5}px` }} />`)}
                    ${m && html`<svg width=${size.w} height=${size.h}>
                        ${site.plot.length > 0 && html`<polygon points=${site.plot.map(([la, ln]) => scr(la, ln).join(',')).join(' ')} fill=${MARK} fill-opacity="0.22" stroke=${MARK} stroke-width="2" stroke-linejoin="round" />`}
                        ${site.plot.map(([la, ln]) => { const [x, y] = scr(la, ln); return html`<circle cx=${x} cy=${y} r="4" fill="#fff" stroke=${MARK} stroke-width="2" />`; })}
                        ${site.places.map((p, i) => { const [x, y] = scr(p.lat, p.lng); return html`<g><circle cx=${x} cy=${y} r="10" fill="#141414" stroke="#fff" stroke-width="1.5" /><text x=${x} y=${y + 4} text-anchor="middle" fill="#fff" font-size="11" font-weight="600">${i + 1}</text></g>`; })}
                        ${pinned && (() => { const [x, y] = scr(site.lat, site.lng); return html`<path d=${`M${x} ${y} l-7 -15 a9 9 0 1 1 14 0 z`} fill=${MARK} stroke="#141414" stroke-width="1.5" /><circle cx=${x} cy=${y - 21} r="3.4" fill="#141414" />`; })()}
                    </svg>`}
                    <div class="pm-site__zoom"><button type="button" onPointerDown=${(e) => e.stopPropagation()} onClick=${() => zoomTo(view.z + 1)}>+</button><button type="button" onPointerDown=${(e) => e.stopPropagation()} onClick=${() => zoomTo(view.z - 1)}>−</button></div>
                    <small class="pm-site__credit">${TILESETS[style].credit}</small>
                </div>
                <div class="pm-row"><${Seg} value=${look} on=${setLook} options=${MAP_LOOKS} /><small class="pm-note">Drag to move, scroll to zoom. The look here is only for finding the site; each page chooses its own.</small></div>
            </div>
            <div class="pm-site__side">
                <${Seg} value=${mode} on=${setMode} options=${MODES.map(([v, l]) => [v, l])} />
                <p class="pm-note">${hint}</p>
                ${mode === 'pin' && html`
                    ${pinned ? html`<p class="pm-note">${fmtCoords(site.lat, site.lng)}</p>` : html`<p class="pm-note is-warn">Not pinned yet.</p>`}
                    ${app.boot.pins && app.boot.pins.length > 0 && html`<${Field} label="Or take a website project's pin">
                        <${Select} value="" on=${(v) => { const p = app.boot.pins[+v]; if (p) { setSite((s) => { s.lat = p.lat; s.lng = p.lng; }); setView({ lat: p.lat, lng: p.lng, z: 17 }); } }}
                            options=${[['', 'Choose a project…'], ...app.boot.pins.map((p, i) => [i, p.name])]} /><//>`}
                    ${pinned && html`<${Btn} kind="is-sm" on=${() => setSite((s) => { delete s.lat; delete s.lng; })}>Remove the pin<//>`}`}
                ${mode === 'plot' && html`
                    ${area > 0 && html`<p class="pm-note">The outline is <strong>${Math.round(area).toLocaleString('en-US')} m²</strong>${typed > 0 && info.plotUnit === 'm²' ? ` — the plot area in the project's information is ${typed.toLocaleString('en-US')} m²` : ''}.</p>`}
                    <div class="pm-row pm-row--wrap">
                        ${area > 0 && html`<${Btn} kind="is-sm" on=${() => app.commit((d) => { d.info.plotArea = String(Math.round(area)); d.info.plotUnit = 'm²'; })}>Use it as the plot area<//>`}
                        <${Btn} kind="is-sm" disabled=${!site.plot.length} on=${() => setSite((s) => { s.plot.pop(); })}>Undo last corner<//>
                        <${Btn} kind="is-sm" disabled=${!site.plot.length} on=${() => setSite((s) => { s.plot = []; })}>Clear the outline<//>
                    </div>`}
                ${mode === 'place' && html`<div class="pm-places">
                    ${site.places.map((p, i) => html`<div class="pm-place" key=${p.id}>
                        <b>${i + 1}</b>
                        <input class="pm-in" ref=${(el) => { names.current[p.id] = el; }} value=${p.name} placeholder="Name it, e.g. Erbil Citadel"
                            onInput=${(e) => setSite((s) => { s.places.find((x) => x.id === p.id).name = e.target.value; }, `place:${p.id}`)} />
                        <small>${pinned ? fmtDistance(distance(site, p)) : '—'}</small>
                        <button type="button" class="pm-x" title="Take it off" onClick=${() => setSite((s) => { s.places = s.places.filter((x) => x.id !== p.id); })}>×</button>
                    </div>`)}
                    ${!site.places.length && html`<p class="pm-note">No places yet.</p>`}
                </div>`}
            </div>
        </div>
    <//>`;
}

/** Revisions issued: each a line in the revision history, and a copy of the presentation as it then was. */
function Revisions({ app }) {
    const { S } = app, list = S.doc.revisions, current = S.doc.info.revision;
    const [rev, setRev] = useState(nextRevision(current));
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState('');
    useEffect(() => { setRev(nextRevision(current)); }, [current]);
    const stored = (e) => S.revisions.find((r) => r.key === e.id);
    const taken = list.some((e) => e.rev === rev.trim());
    const act = async (label, fn) => { setBusy(label); try { await fn(); } catch (e) { alert(e.message); } finally { setBusy(''); } };
    return html`<${Group} title="Revisions" open=${list.length > 0}>
        <p class="pm-note">Issuing a revision keeps the presentation exactly as it is now — to open as a copy or go back to later — puts its letter in the project’s information and adds it to the history a “Revision history” page prints.</p>
        <div class="pm-2"><${Field} label="Revision"><${Text} dir="ltr" value=${rev} on=${setRev} placeholder="A" /><//>
        <${Field} label="What changed"><${Text} value=${note} on=${setNote} placeholder="e.g. Kitchen moved to the north side" /><//></div>
        ${taken && html`<p class="pm-note is-warn">Revision ${rev.trim()} has been issued already.</p>`}
        <${Btn} kind="is-sm is-primary" disabled=${!rev.trim() || taken || !!busy} on=${() => act('issue', async () => { await app.issueRevision(rev.trim(), note.trim()); setNote(''); })}>${busy === 'issue' ? 'Keeping a copy…' : `Issue revision ${rev.trim()}`}<//>
        ${list.length > 0 && html`<div class="pm-revs">${list.slice().reverse().map((e) => {
        const r = stored(e);
        return html`<div class="pm-rev">
                <div class="pm-rev__h"><strong>Rev ${e.rev}</strong><small>${fmtDate(e.date)}</small>
                    <button type="button" class="pm-x" title="Take it out of the history (and delete its copy)" disabled=${!!busy}
                        onClick=${() => confirm(`Take revision ${e.rev} out of the history?${r ? ' Its stored copy is deleted.' : ''}`) && act(`del${e.id}`, () => app.deleteRevision(e, r))}>×</button></div>
                <${Text} value=${e.note} placeholder="What changed" on=${(v) => app.commit((d) => { d.revisions.find((x) => x.id === e.id).note = v; }, `rev:${e.id}`)} />
                ${r ? html`<div class="pm-row">
                    <${Btn} kind="is-sm" disabled=${!!busy} on=${() => act(`copy${e.id}`, () => app.copyRevision(r))}>${busy === `copy${e.id}` ? 'Copying…' : 'Open a copy'}<//>
                    <${Btn} kind="is-sm" disabled=${!!busy} title="The pages and design as they were; the revision history stays. Undo takes it back." on=${() => confirm(`Put the pages back as they were at revision ${e.rev}?`) && act(`back${e.id}`, () => app.restoreRevision(r))}>Go back to this<//>
                </div>` : html`<small class="pm-note">No copy was kept of this one.</small>`}
            </div>`;
    })}</div>`}
    <//>`;
}

// ── Design tab ──
export function DesignTab({ app }) {
    const { S } = app, t = S.doc.theme;
    const set = (fn, tag) => app.commit((d) => fn(d.theme, d), tag && `theme:${tag}`);
    const saved = S.presets.filter((p) => p.type === 'theme');
    const zone = (name, label) => html`<${Group} title=${label} open=${false}>
        <${Toggle} label=${`Show the ${name}`} value=${t[name].on} on=${(v) => set((th) => { th[name].on = v; })} />
        ${t[name].on && html`
            <${Field} label="Left"><${Text} value=${t[name].l} on=${(v) => set((th) => { th[name].l = v; }, `${name}l`)} /><//>
            <${Field} label="Centre"><${Text} value=${t[name].c} on=${(v) => set((th) => { th[name].c = v; }, `${name}c`)} /><//>
            <${Field} label="Right" hint=${TOKENS_HINT}><${Text} value=${t[name].r} on=${(v) => set((th) => { th[name].r = v; }, `${name}r`)} /><//>
            <${Toggle} label="A thin line beside it" value=${t[name].line} on=${(v) => set((th) => { th[name].line = v; })} />`}
    <//>`;
    const font = (k, label) => html`<${Group} title=${label} open=${false}>
        <${Field} label="Family"><${Select} value=${t.fonts[k].f} on=${(v) => set((th) => { th.fonts[k].f = v; th.fonts[k].w = FONTS[v].weights.includes(th.fonts[k].w) ? th.fonts[k].w : FONTS[v].weights[0]; })} options=${Object.keys(FONTS).map((f) => [f, f])} /><//>
        <${Field} label="Weight"><${Select} value=${t.fonts[k].w} on=${(v) => set((th) => { th.fonts[k].w = +v; })} options=${FONTS[t.fonts[k].f].weights.map((w) => [w, { 300: 'Light', 400: 'Regular', 500: 'Medium', 600: 'Semibold' }[w]])} /><//>
        <${Toggle} label="CAPITALS" value=${t.fonts[k].up} on=${(v) => set((th) => { th.fonts[k].up = v; })} />
        <${Field} label="Letter-spacing" row><${Range} min=${0} max=${30} value=${Math.round((t.fonts[k].ls || 0) * 100)} on=${(v) => set((th) => { th.fonts[k].ls = v / 100; }, `ls${k}`)} show=${(v) => `${v}%`} /><//>
    <//>`;

    return html`
        <${Group} title="Template">
            <div class="pm-themes">
                ${Object.entries(THEMES).map(([id, th]) => html`<button type="button" class=${t.name === th.name ? 'is-on' : ''} onClick=${() => app.applyTheme(id)}>
                    <i style=${{ background: th.colors.bg, color: th.colors.text, borderColor: th.colors.line }}><b style=${{ background: th.colors.accent }}></b>Aa</i>${th.name}</button>`)}
                ${saved.map((p) => html`<button type="button" class=${t.name === p.name ? 'is-on' : ''} onClick=${() => app.applyTheme(`saved:${p.id}`)}>
                    <i style=${{ background: p.data.colors.bg, color: p.data.colors.text, borderColor: p.data.colors.line }}><b style=${{ background: p.data.colors.accent }}></b>Aa</i>${p.name}
                    <span class="pm-themes__x" title="Delete this template" onClick=${(e) => { e.stopPropagation(); app.deletePreset(p); }}>×</span></button>`)}
            </div>
            <p class="pm-note">Changing template restyles every page and keeps all the content.</p>
            <${Btn} kind="is-sm" on=${() => app.saveTheme()}>Save this design as a template…<//>
        <//>
        <${Group} title="Page">
            <div class="pm-2"><${Field} label="Size"><${Seg} value=${S.doc.size} on=${(v) => app.commit((d) => { d.size = v; })} options=${Object.keys(PAGE_MM).map((k) => [k, k])} /><//>
            <${Field} label="Orientation"><${Seg} value=${S.doc.orient} on=${(v) => app.commit((d) => { d.orient = v; })} options=${[['landscape', 'Landscape'], ['portrait', 'Portrait']]} /><//></div>
            <${Field} label="Margins (mm)"><div class="pm-4">${[['t', 'Top'], ['r', 'Right'], ['b', 'Bottom'], ['l', 'Left']].map(([k, l]) => html`<label><input class="pm-in" type="number" min="0" max="80" value=${t.margins[k]} title=${l}
                onInput=${(e) => set((th) => { th.margins[k] = Math.max(0, Math.min(80, +e.target.value || 0)); }, `m${k}`)} /><span>${l}</span></label>`)}</div><//>
        <//>
        <${Group} title="Colours" open=${false}>
            ${[['bg', 'Page'], ['text', 'Text'], ['muted', 'Quiet text'], ['accent', 'Accent'], ['line', 'Lines']].map(([k, l]) => html`<${Field} label=${l} row><${Color} value=${t.colors[k]} on=${(v) => set((th) => { th.colors[k] = v; }, `c${k}`)} /><//>`)}
        <//>
        ${font('title', 'Headings')}${font('body', 'Text')}${font('label', 'Labels')}
        <${Group} title="Type size" open=${false}>
            <${Field} label="All text" row><${Range} min=${75} max=${140} step=${5} value=${Math.round((t.scale || 1) * 100)} on=${(v) => set((th) => { th.scale = v / 100; }, 'scale')} show=${(v) => `${v}%`} /><//>
        <//>
        ${zone('header', 'Header')}${zone('footer', 'Footer')}
        <${Group} title="Page numbers & logo" open=${false}>
            <p class="pm-note">The number is printed wherever <code>{page}</code> stands in the header or footer.</p>
            <${Toggle} label="No number on the cover" value=${t.numbers.skipCover} on=${(v) => set((th) => { th.numbers.skipCover = v; })} />
            <${Toggle} label="No number on section dividers" value=${t.numbers.skipDividers} on=${(v) => set((th) => { th.numbers.skipDividers = v; })} />
            <${Field} label="Your logo on every content page"><${Select} value=${t.logo.at} on=${(v) => set((th) => { th.logo.at = v; })}
                options=${[['none', 'Not shown'], ['header-left', 'Header, left'], ['header-right', 'Header, right'], ['footer-left', 'Footer, left'], ['footer-center', 'Footer, centre'], ['footer-right', 'Footer, right']]} /><//>
            ${t.logo.at !== 'none' && html`<${Field} label="Logo size" row><${Range} min=${60} max=${220} step=${10} value=${Math.round((t.logo.size || 1) * 100)} on=${(v) => set((th) => { th.logo.size = v / 100; }, 'logosz')} show=${(v) => `${v}%`} /><//>`}
        <//>
        <${Group} title="Covers & dividers" open=${false}>
            <${Field} label="Cover style"><${StylePick} app=${app} options=${COVER_STYLES} value=${t.cover} page=${SAMPLE.cover} on=${(v) => set((th) => { th.cover = v; })} /><//>
            <${Field} label="Divider style"><${StylePick} app=${app} options=${DIVIDER_STYLES} value=${t.divider} page=${SAMPLE.divider} on=${(v) => set((th) => { th.divider = v; })} /><//>
            <p class="pm-note">A page set to its own style keeps it.</p>
        <//>`;
}

// ── Add a page ──
const GROUPS = ['Structure', 'Content', 'Images', 'Drawings'];
export function AddPageDialog({ app, onClose }) {
    const { S } = app, at = S.doc.pages.length ? S.page + 1 : 0, divider = S.doc.theme.divider;
    const cards = useMemo(() => {
        const out = [];
        for (const [id, L] of Object.entries(LAYOUTS)) {
            if (id === 'cover') COVER_STYLES.forEach(([v, name, hint]) => out.push({ group: L.group, name: `Cover — ${name}`, hint, page: newPage('cover', { opts: { variant: v } }) }));
            // the template's own divider style is left unset, so the page follows the template if it changes
            else if (id === 'divider') DIVIDER_STYLES.forEach(([v, name, hint]) => out.push({ group: L.group, name: `Divider — ${name}`, hint, page: newPage('divider', v === divider ? {} : { opts: { variant: v } }) }));
            else out.push({ group: L.group, name: L.name, hint: L.hint, page: newPage(id) });
        }
        for (const p of S.presets.filter((x) => x.type === 'layout')) {
            out.push({ group: 'Yours', preset: p, name: p.name, hint: (LAYOUTS[p.data.layout] || LAYOUTS.blank).name, page: { ...newPage(p.data.layout), ...clone(p.data) } });
        }
        return out;
    }, [S.presets, divider]);
    const own = pictures(S).renders.length > 0;
    const add = (page) => { app.addPage({ ...clone(page), id: uid() }); onClose(); };
    return html`<${Dialog} title="Add a page" onClose=${onClose} wide>
        <p class="pm-note">Each design is drawn in this presentation’s template${own ? ', with its own pictures as an example — the new page arrives empty, ready for the pictures you choose' : ''}. It goes after page ${at || 1}.</p>
        ${[...GROUPS, 'Yours'].map((g) => {
        const list = cards.filter((c) => c.group === g);
        return list.length > 0 && html`<h3 class="pm-h">${g === 'Yours' ? 'Your layouts' : g}</h3><div class="pm-designs">
            ${list.map((c) => html`<button type="button" class="pm-design" onClick=${() => add(c.page)}>
                <${DesignThumb} app=${app} page=${c.page} at=${at} /><strong>${c.name}</strong><span>${c.hint}</span>
                ${c.preset && html`<i class="pm-design__x" title="Delete this layout" onClick=${(e) => { e.stopPropagation(); app.deletePreset(c.preset); }}>×</i>`}
            </button>`)}</div>`;
    })}
        <h3 class="pm-h">A section divider, by its name</h3>
        <div class="pm-chips">${SECTIONS.map((t) => html`<button type="button" onClick=${() => add(newPage('divider', { title: sectionName(t, S.doc.lang) }))}>${sectionName(t, S.doc.lang)}</button>`)}</div>
    <//>`;
}

/** This page in every layout, to change it to one. */
export function LayoutDialog({ app, onClose }) {
    const { S } = app, page = S.doc.pages[S.page];
    const cards = useMemo(() => Object.entries(LAYOUTS).map(([id, L]) => ({ id, L, page: id === page.layout ? page : relayout(page, id, (h) => S.assets.get(h)?.kind === 'pdf') })), [page]);
    return html`<${Dialog} title=${`Page ${S.page + 1} in another layout`} onClose=${onClose} wide>
        <p class="pm-note">Its pictures, words and anything added by hand come along; the old layout’s own settings and moved places do not. Empty places show this presentation’s pictures only as an example. Undo (Ctrl+Z) takes it back.</p>
        ${GROUPS.map((g) => html`<h3 class="pm-h">${g}</h3><div class="pm-designs">
            ${cards.filter((c) => c.L.group === g).map((c) => html`<button type="button" class=${`pm-design${c.id === page.layout ? ' is-on' : ''}`} onClick=${() => { if (c.id !== page.layout) app.changeLayout(c.id); onClose(); }}>
                <${DesignThumb} app=${app} page=${c.page} at=${S.page} replace=${true} /><strong>${c.L.name}</strong><span>${c.id === page.layout ? 'Its layout now' : c.L.hint}</span>
            </button>`)}</div>`)}
    <//>`;
}

// ── Upload, and turn uploads into pages ──
const DRAWING_SECTIONS = ['Site Plan', 'Floor Plans', 'Elevations', 'Sections'];
export function ImportDialog({ app, files, target, onClose }) {
    const [rows, setRows] = useState([]);
    const [renderAs, setRenderAs] = useState('image-full');
    const [busy, setBusy] = useState(false);
    const live = useRef([]);
    const input = useRef();
    const patch = (id, p) => { live.current = live.current.map((r) => (r.id === id ? { ...r, ...p } : r)); setRows(live.current); };

    async function take(list) {
        const added = [...list].map((file) => ({ id: uid(), file, name: file.name, kind: /\.pdf$/i.test(file.name) || file.type === 'application/pdf' ? 'pdf' : 'image',
            status: 'Waiting', progress: 0, section: guessSection(file.name, /\.pdf$/i.test(file.name) ? 'pdf' : 'image'), thumbs: {}, picked: [], mode: 'sheet' }));
        live.current = [...live.current, ...added];
        setRows(live.current);
        for (const row of added) {
            try {
                let pdf = null;
                if (row.kind === 'pdf') {   // read it first: a PDF that cannot be used should not be uploaded at all
                    patch(row.id, { status: 'Reading the PDF' });
                    pdf = await app.uploader.openPdf(row.file);
                    await app.uploader.checkPdf(row.file);
                    patch(row.id, { pdf, picked: pdf.pages.map((_, i) => i + 1) });
                    app.uploader.pdfThumbs(pdf, (n, url) => patch(row.id, { thumbs: { ...live.current.find((r) => r.id === row.id).thumbs, [n]: url } }));
                }
                patch(row.id, { status: 'Uploading' });
                let asset = await app.uploader.upload(row.file, app.S.id, (p) => patch(row.id, { progress: p }));
                patch(row.id, { status: 'Making the preview' });
                // a PDF records its pages and shows its first one at once, so it can be found in the library later
                asset = row.kind === 'image' ? await app.uploader.imagePreviews(row.file, asset) : await app.uploader.pdfPreviews(pdf, asset, [1]);
                app.addAsset(asset);
                patch(row.id, { status: 'Ready', asset, progress: 1 });
            } catch (e) {
                patch(row.id, { status: 'Failed', error: e.message });
            }
        }
    }
    useEffect(() => { if (files && files.length) take(files); }, []);

    const ready = rows.filter((r) => r.asset);
    const working = rows.some((r) => !r.asset && !r.error);

    /** Previews for the PDF pages that were picked, then the assets as they now are. */
    async function finishPdfs() {
        for (const r of live.current.filter((x) => x.asset && x.kind === 'pdf' && x.picked.length)) {
            patch(r.id, { status: 'Preparing the pages' });
            const asset = await app.uploader.pdfPreviews(r.pdf, r.asset, r.picked, (p) => patch(r.id, { progress: p }));
            app.addAsset(asset);
            patch(r.id, { asset, status: 'Ready' });
        }
        return live.current.filter((x) => x.asset);
    }
    async function finish(build) {
        setBusy(true);
        try {
            const done = await finishPdfs();
            if (target) {
                const r = done[0];
                if (r) app.assign(target, r.asset, r.kind === 'pdf' ? r.picked[0] || 1 : 0);
            } else if (build) {
                app.buildPages(done.map((r) => ({ asset: r.asset, section: r.section, pages: r.kind === 'pdf' ? r.picked : null, mode: r.mode, title: titleFromName(r.name) })), renderAs);
            }
            onClose();
        } catch (e) {
            alert(e.message);
        } finally {
            setBusy(false);
        }
    }

    return html`<${Dialog} title=${target ? 'Upload an image or a PDF page' : 'Upload drawings and renders'} onClose=${() => !busy && onClose()} wide
        foot=${html`
            ${!target && ready.length > 0 && html`<label class="pm-inline">Renders become <${Select} value=${renderAs} on=${setRenderAs} options=${[['image-full', 'full-page images'], ['image-fit', 'images within the margins'], ['image-caption', 'images with a caption']]} /></label>`}
            <span class="pm-sp"></span>
            <${Btn} on=${onClose} disabled=${busy}>${ready.length ? 'Keep in the library only' : 'Close'}<//>
            ${target ? html`<${Btn} kind="is-primary" disabled=${busy || working || !ready.length} on=${() => finish(false)}>Use it<//>`
        : html`<${Btn} kind="is-primary" disabled=${busy || working || !ready.length} on=${() => finish(true)}>${busy ? 'Preparing…' : `Create pages (${ready.reduce((n, r) => n + (r.kind === 'pdf' ? r.picked.length : 1), 0)})`}<//>`}`}>
        <div class="pm-drop" onDragOver=${(e) => e.preventDefault()} onDrop=${(e) => { e.preventDefault(); take(e.dataTransfer.files); }} onClick=${() => input.current.click()}>
            <strong>Drop files here, or click to choose</strong>
            <span>JPG and PNG images, and PDF drawings — several at once. Originals are stored untouched; up to ${Math.round(app.boot.maxBytes / 1048576)} MB each.</span>
            <input ref=${input} type="file" multiple=${!target} accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" hidden onChange=${(e) => { take(e.target.files); e.target.value = ''; }} />
        </div>
        ${rows.map((r) => html`<div class=${`pm-up${r.error ? ' is-error' : ''}`}>
            <div class="pm-up__head">
                <div class="pm-up__pic">${r.asset && r.kind === 'image' ? html`<img src=${app.media.url(r.asset.hash, 'thumb')} alt="" />` : r.thumbs[1] ? html`<img src=${r.thumbs[1]} alt="" />` : html`<span>${r.kind === 'pdf' ? 'PDF' : '…'}</span>`}</div>
                <div class="pm-up__info">
                    <strong>${r.name}</strong>
                    <small>${r.error || `${r.status}${r.asset ? ` · ${bytes(r.asset.size)}${r.kind === 'image' ? ` · ${r.asset.meta.w} × ${r.asset.meta.h} px` : ` · ${r.pdf.pages.length} page${r.pdf.pages.length > 1 ? 's' : ''}, vector kept`}` : ''}`}</small>
                    ${!r.asset && !r.error && html`<progress max="1" value=${r.progress}></progress>`}
                </div>
                ${!target && r.asset && html`<label class="pm-up__sec">Section <${Select} value=${r.section} on=${(v) => patch(r.id, { section: v })} options=${[...SECTIONS.map((s) => [s, s]), ['', 'No section']]} /></label>`}
            </div>
            ${r.kind === 'pdf' && r.pdf && html`<div class="pm-up__pdf">
                ${!target && html`<${Seg} value=${r.mode} on=${(v) => patch(r.id, { mode: v })} options=${[
        ['sheet', 'Full drawing sheets', 'Each page whole, at the sheet\'s own paper size'],
        ['sheet-fit', 'Sheets scaled to the page', 'Each page whole, scaled onto the presentation\'s page size'],
        ['layout', 'Inside a page layout', 'Within the margins, with a title, details and notes']]} />`}
                ${r.pdf.pages.length > 1 && html`<div class="pm-pages">
                    ${r.pdf.pages.map((p, i) => { const n = i + 1, on = r.picked.includes(n); return html`<button type="button" class=${on ? 'is-on' : ''}
                        onClick=${() => patch(r.id, { picked: target ? [n] : on ? r.picked.filter((x) => x !== n) : [...r.picked, n].sort((a, b) => a - b) })}>
                        ${r.thumbs[n] ? html`<img src=${r.thumbs[n]} alt="" />` : html`<i></i>`}<span>${n} · ${mm(p.w)}×${mm(p.h)} mm</span></button>`; })}
                </div>`}
            </div>`}
        </div>`)}
    <//>`;
}

// ── Pick an image already uploaded ──
export function LibraryDialog({ app, target, onClose }) {
    const [busy, setBusy] = useState('');
    const assets = [...app.S.assets.values()].filter((a) => !a.brand);
    const used = JSON.stringify(app.S.doc.pages);
    const remove = (e, a) => { e.stopPropagation(); if (confirm(`Remove “${a.name}” from this presentation? The stored file is deleted.`)) app.removeAsset(a); };
    const x = (a) => !used.includes(a.hash) && html`<b class="pm-lib__x" title="Not used on any page — remove the file" onClick=${(e) => remove(e, a)}>×</b>`;
    async function pickPdf(a, n) {
        try {
            if (!(a.meta.previews || []).includes(n)) {
                setBusy(`Preparing page ${n}…`);
                const data = await (await fetch(app.media.url(a.hash, 'original'))).arrayBuffer();
                a = await app.uploader.pdfPreviews(await app.uploader.openPdf(data), a, [n]);
                app.addAsset(a);
            }
            app.assign(target, a, n);
            onClose();
        } catch (e) { alert(e.message); } finally { setBusy(''); }
    }
    return html`<${Dialog} title="Choose an image or a drawing" onClose=${onClose} wide
        foot=${html`<span class="pm-sp">${busy}</span><${Btn} on=${onClose}>Cancel<//><${Btn} kind="is-primary" on=${() => { onClose(); app.openImport(null, target); }}>Upload a new file…<//>`}>
        ${!assets.length && html`<p class="pm-empty">Nothing has been uploaded for this presentation yet.</p>`}
        <div class="pm-lib">
            ${assets.map((a) => (a.kind === 'image'
        ? html`<button type="button" onClick=${() => { app.assign(target, a, 0); onClose(); }}><img src=${app.media.url(a.hash, 'thumb')} alt="" loading="lazy" /><span>${a.name}</span><small>${a.meta.w} × ${a.meta.h} px</small>${x(a)}</button>`
        : (a.meta.pages || []).map((p, i) => html`<button type="button" onClick=${() => pickPdf(a, i + 1)}>
                ${(a.meta.previews || []).includes(i + 1) ? html`<img src=${app.media.url(a.hash, 'thumb', i + 1)} alt="" loading="lazy" />` : html`<i>PDF</i>`}
                <span>${a.name}</span><small>page ${i + 1} · ${mm(p.w)}×${mm(p.h)} mm · vector</small>${i === 0 && x(a)}</button>`)))}
        </div>
    <//>`;
}

// ── The whole document, page after page ──
export function PreviewDialog({ app, onClose }) {
    const box = useRef();
    const [w, setW] = useState(900);
    useEffect(() => { setW(Math.min(1300, box.current.clientWidth - 80)); }, []);
    return html`<div class="pm-preview" ref=${box}>
        <header><strong>${app.S.name}</strong><span>${app.S.doc.pages.length} pages — as they will be in the PDF</span><span class="pm-sp"></span>
            <${Btn} on=${() => app.dialog({ type: 'present' })}>Present<//><${Btn} kind="is-primary" on=${() => app.dialog({ type: 'export' })}>Export…<//><${Btn} on=${onClose}>Back to editing<//></header>
        <div class="pm-preview__pages">${app.S.doc.pages.map((p, i) => { const lay = app.lay(i, false); return html`<figure><${PageView} lay=${lay} scale=${Math.min(w / lay.w, (window.innerHeight - 140) / lay.h)} media=${app.media} /><figcaption>${i + 1}</figcaption></figure>`; })}</div>
    </div>`;
}

// ── Present: full screen, a page at a time ──
export function PresentDialog({ app, onClose }) {
    const { S } = app, n = S.doc.pages.length;
    const [i, setI] = useState(S.page);
    const [box, setBox] = useState({ w: window.innerWidth, h: window.innerHeight });
    const el = useRef();
    const go = (by) => setI((x) => Math.max(0, Math.min(n - 1, x + by)));
    useEffect(() => {
        document.activeElement?.blur();   // or Space would press the button that opened it
        el.current.requestFullscreen?.().catch(() => {});
        const key = (e) => {
            const by = { ArrowRight: 1, ArrowDown: 1, PageDown: 1, ' ': 1, Enter: 1, ArrowLeft: -1, ArrowUp: -1, PageUp: -1, Backspace: -1 }[e.key];
            if (by) { e.preventDefault(); go(by); } else if (e.key === 'Home') setI(0); else if (e.key === 'End') setI(n - 1);
        };
        const size = () => setBox({ w: window.innerWidth, h: window.innerHeight });
        // leaving full screen (Esc) ends the presentation
        const left = () => { if (!document.fullscreenElement) onClose(); };
        window.addEventListener('keydown', key);
        window.addEventListener('resize', size);
        document.addEventListener('fullscreenchange', left);
        return () => {
            window.removeEventListener('keydown', key); window.removeEventListener('resize', size); document.removeEventListener('fullscreenchange', left);
            if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        };
    }, []);
    const view = (k) => { const lay = app.lay(k, false); return html`<${PageView} lay=${lay} scale=${Math.min(box.w / lay.w, box.h / lay.h)} media=${app.media} />`; };
    return html`<div class="pm-present" ref=${el} onClick=${(e) => go(e.clientX < box.w / 3 ? -1 : 1)}>
        ${view(i)}
        ${i + 1 < n && html`<div class="pm-present__next" aria-hidden="true">${view(i + 1)}</div>`}
        <span class="pm-present__n">${i + 1} / ${n}</span>
        <button type="button" class="pm-present__x" title="End (Esc)" onClick=${(e) => { e.stopPropagation(); onClose(); }}>×</button>
    </div>`;
}

// ── Export ──
export function ExportDialog({ app, onClose }) {
    const { S } = app, n = S.doc.pages.length, rooms = app.boot.rooms || [];
    const [format, setFormat] = useState('pdf');
    const [preset, setPreset] = useState('balanced');
    const [px, setPx] = useState('2400');
    const [to, setTo] = useState('download');
    const [room, setRoom] = useState(rooms[0] ? String(rooms[0].id) : '');
    const [note, setNote] = useState('');
    const [which, setWhich] = useState(S.picks.size > 1 ? 'range' : 'all');
    const [range, setRange] = useState(S.picks.size > 1 ? app.picksRange() : `1-${n}`);
    const [name, setName] = useState(fileName(S.doc, S.name));
    const [state, setState] = useState(null);   // { done, total, label } | { error } | { size } | { sent }
    const indices = which === 'all' ? S.doc.pages.map((_, i) => i) : which === 'page' ? [S.page] : parseRange(range, n);
    const warns = (indices || []).flatMap((i) => app.lay(i, false).warnings.map((w) => ({ ...w, page: i + 1 })));
    const lowres = warns.filter((w) => w.type === 'lowres'), empty = warns.filter((w) => w.type === 'empty'), other = warns.filter((w) => w.type !== 'lowres' && w.type !== 'empty');
    const mixed = new Set((indices || []).map((i) => { const s = pageSize(S.doc, S.doc.pages[i]); return `${mm(s.w)}×${mm(s.h)}`; }));
    const running = state && state.total && state.done < state.total;
    const sending = format === 'pdf' && to === 'room';

    async function run() {
        setState({ done: 0, total: 1, label: 'Starting' });
        try {
            await app.saveNow();
            const job = { doc: S.doc, indices, env: app.env(false), media: app.media, title: name, onProgress: setState };
            if (format === 'jpg') {
                const out = await exportImages({ ...job, size: px, uploader: app.uploader });
                downloadBlob(out.blob, out.name);
                return setState({ size: out.blob.size, pages: out.pages, file: out.name });
            }
            const { bytes: data, pages } = await exportPdf({ ...job, preset });
            if (!sending) { download(data, name); return setState({ size: data.length, pages }); }
            const file = new File([data], name.endsWith('.pdf') ? name : `${name}.pdf`, { type: 'application/pdf' });
            setState({ done: 0, total: 100, label: 'Sending to the room' });
            const sent = await app.uploader.upload(file, null, (p) => setState({ done: Math.min(99, Math.round(p * 100)), total: 100, label: 'Sending to the room' }),
                app.boot.urls.toRoom, { room, title: name.replace(/\.pdf$/i, ''), note });
            setState({ sent, size: data.length, pages });
        } catch (e) {
            console.error(e);
            setState({ error: e.message || 'It could not be made.' });
        }
    }

    const verb = format === 'jpg' ? 'Export pictures' : sending ? 'Send to the room' : 'Export PDF';
    return html`<${Dialog} title="Export" onClose=${() => !running && onClose()}
        foot=${html`<span class="pm-sp"></span><${Btn} on=${onClose} disabled=${running}>Close<//><${Btn} kind="is-primary" disabled=${running || !indices || (sending && !room)} on=${run}>${running ? 'Working…' : verb}<//>`}>
        <${Field} label="As"><${Seg} value=${format} on=${setFormat} options=${[['pdf', 'PDF document'], ['jpg', 'JPG pictures', 'Each page as a picture — for WhatsApp, social media or a screen']]} /><//>
        ${format === 'pdf' && html`<${Field} label="Quality"><div class="pm-presets">${Object.entries(PRESETS).map(([id, p]) => html`<label class=${preset === id ? 'is-on' : ''}>
            <input type="radio" name="preset" checked=${preset === id} onChange=${() => setPreset(id)} /><strong>${p.name}</strong><span>${p.note}</span></label>`)}</div><//>`}
        ${format === 'jpg' && html`<${Field} label="Picture size" hint="Several pages come in one .zip file. In a JPG a drawing is a picture too — send the PDF where it must stay sharp at any zoom.">
            <${Seg} value=${px} on=${setPx} options=${JPG_SIZES} /><//>`}
        <${Field} label="Pages"><${Seg} value=${which} on=${setWhich} options=${[['all', `All ${n}`], ['page', `This page (${S.page + 1})`], ['range', 'Choose…']]} /><//>
        ${which === 'range' && html`<${Field} hint=${indices ? `${indices.length} page${indices.length > 1 ? 's' : ''}` : `Write page numbers between 1 and ${n}, like 1-4, 7`}><${Text} dir="ltr" value=${range} on=${setRange} placeholder="1-4, 7" /><//>`}
        <${Field} label=${sending ? 'Title in the room' : 'File name'}><${Text} value=${name} on=${setName} /><//>
        ${format === 'pdf' && html`<${Field} label="Then"><${Seg} value=${to} on=${setTo} options=${[['download', 'Save to this computer'], ['room', 'Send to a client room', 'It appears in the client’s room, waiting for their approval']]} /><//>`}
        ${sending && (rooms.length ? html`
            <${Field} label="Room"><${Select} value=${room} on=${setRoom} options=${rooms.map((r) => [r.id, r.label])} /><//>
            <${Field} label="A note for the client (optional)"><${Text} area=${2} value=${note} on=${setNote} /><//>`
        : html`<p class="pm-note">There are no client rooms yet — make one under Clients → Client rooms.</p>`)}
        ${format === 'pdf' && mixed.size > 1 && html`<p class="pm-note">This PDF mixes page sizes (${[...mixed].join(', ')} mm): each page is exactly its own size. When printing, choose “fit to page” or print the larger sheets separately.</p>`}
        ${(lowres.length > 0 || empty.length > 0 || other.length > 0) && html`<div class="pm-warns">
            ${lowres.length > 0 && html`<p><strong>${lowres.length} image${lowres.length > 1 ? 's are' : ' is'} too small for the space ${lowres.length > 1 ? 'they fill' : 'it fills'}</strong> and will look soft on paper: ${lowres.map((w) => `page ${w.page} (${w.ppi} PPI)`).join(', ')}. No export setting can add the missing detail — a larger original can.</p>`}
            ${empty.length > 0 && html`<p>Empty image places are left blank: page${empty.length > 1 ? 's' : ''} ${[...new Set(empty.map((w) => w.page))].join(', ')}.</p>`}
            ${other.map((w) => html`<p>Page ${w.page}: ${warningText(w)}</p>`)}
        </div>`}
        ${state && state.total && html`<div class="pm-progress"><progress max=${state.total} value=${state.done}></progress><span>${state.label}</span></div>`}
        ${state && state.error && html`<p class="pm-error">${state.error}</p>`}
        ${state && state.sent && html`<div class="pm-ok"><p>Sent — ${state.pages} ${state.pages === 1 ? 'page' : 'pages'},${bytes(state.size)}. It is in ${state.sent.client}’s room, waiting for their approval.</p>
            <div class="pm-row"><a class="pm-btn is-sm" href=${state.sent.room} target="_blank" rel="noopener">Open the room</a>
            ${state.sent.whatsapp && html`<a class="pm-btn is-sm" href=${state.sent.whatsapp} target="_blank" rel="noopener">Tell them on WhatsApp</a>`}</div></div>`}
        ${state && state.size && !state.sent && html`<p class="pm-ok">Done — ${state.file || `${state.pages} ${state.pages === 1 ? 'page' : 'pages'}`}, ${bytes(state.size)}. It is in your downloads.</p>`}
    <//>`;
}

// ── Branding ──
export function BrandPanel({ app }) {
    const { S } = app, b = S.brand;
    const set = (fn) => app.setBrand(fn);
    const input = useRef(), slotRef = useRef(null);
    const [busy, setBusy] = useState('');
    async function pick(e) {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        if (/\.pdf$/i.test(file.name)) return alert('A logo must be a PNG or JPG image.');
        setBusy('Uploading…');
        try {
            const asset = await app.uploader.imagePreviews(file, await app.uploader.upload(file, null));
            app.addAsset(asset);
            await app.media.prepare([asset.hash]);
            const where = slotRef.current;
            set((x) => { if (where === 'studio') x.logo = asset.hash; else x.partners.find((p) => p.id === where).logo = asset.hash; });
        } catch (err) { alert(err.message); } finally { setBusy(''); }
    }
    const choose = (where) => { slotRef.current = where; input.current.click(); };
    const logo = (hash) => (hash ? html`<img src=${app.media.logoUrl(hash, '#141414') || ''} alt="" />` : html`<span>No logo</span>`);

    return html`<div class="pm-brand">
        <input ref=${input} type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" hidden onChange=${pick} />
        <${Group} title="Your studio">
            <${Field} label="Studio name"><${Text} value=${b.studio} on=${(v) => set((x) => { x.studio = v; })} /><//>
            <${Field} label="Logo" hint="A dark logo on a white or clear ground works best: it is recoloured to suit light and dark pages.">
                <div class="pm-logo">${logo(b.logo)}<${Btn} kind="is-sm" on=${() => choose('studio')}>${busy || 'Replace…'}<//></div><//>
            <${Toggle} label="One colour — recolour it to suit each page" value=${b.logoMono !== false} on=${(v) => set((x) => { x.logoMono = v; })} />
            <${Field} label="Address"><${Text} area=${2} value=${b.address} on=${(v) => set((x) => { x.address = v; })} /><//>
            <div class="pm-2"><${Field} label="Phone"><${Text} dir="ltr" value=${b.phone} on=${(v) => set((x) => { x.phone = v; })} /><//>
            <${Field} label="E-mail"><${Text} dir="ltr" value=${b.email} on=${(v) => set((x) => { x.email = v; })} /><//></div>
            <${Field} label="Website"><${Text} dir="ltr" value=${b.website} on=${(v) => set((x) => { x.website = v; })} /><//>
        <//>
        <${Group} title="Partners' logos">
            <p class="pm-note">For projects done together. A partner's logo appears beside yours only in the presentations where you choose it (Project → Collaboration).</p>
            ${(b.partners || []).map((p) => html`<div class="pm-partner">
                <div class="pm-logo">${logo(p.logo)}<${Btn} kind="is-sm" on=${() => choose(p.id)}>Replace…<//></div>
                <${Text} value=${p.name} on=${(v) => set((x) => { x.partners.find((y) => y.id === p.id).name = v; })} />
                <${Toggle} label="One colour" value=${p.mono !== false} on=${(v) => set((x) => { x.partners.find((y) => y.id === p.id).mono = v; })} />
                <${Btn} kind="is-sm is-danger" on=${() => confirm(`Remove ${p.name}?`) && set((x) => { x.partners = x.partners.filter((y) => y.id !== p.id); })}>Remove<//>
            </div>`)}
            <${Btn} kind="is-sm" on=${() => set((x) => { x.partners = [...(x.partners || []), { id: uid(), name: 'New partner', logo: null, mono: true }]; })}>Add a partner<//>
        <//>
        <${Group} title="New presentations start with">
            <${Field} label="Template"><${Select} value=${(b.defaults || {}).theme} on=${(v) => set((x) => { x.defaults = { ...x.defaults, theme: v }; })}
                options=${[...Object.entries(THEMES).map(([id, t]) => [id, t.name]), ...S.presets.filter((p) => p.type === 'theme').map((p) => [`saved:${p.id}`, `${p.name} (yours)`])]} /><//>
            <div class="pm-2"><${Field} label="Page size"><${Seg} value=${(b.defaults || {}).size} on=${(v) => set((x) => { x.defaults = { ...x.defaults, size: v }; })} options=${[['A3', 'A3'], ['A4', 'A4']]} /><//>
            <${Field} label="Orientation"><${Seg} value=${(b.defaults || {}).orient} on=${(v) => set((x) => { x.defaults = { ...x.defaults, orient: v }; })} options=${[['landscape', 'Landscape'], ['portrait', 'Portrait']]} /><//></div>
        <//>
        <${Group} title="Your saved templates and layouts">
            ${!S.presets.length && html`<p class="pm-note">None yet. In a presentation, use Design → “Save this design as a template”, or Page → “Save as a layout”.</p>`}
            ${S.presets.map((p) => html`<div class="pm-preset"><span>${p.name}</span><small>${p.type === 'theme' ? 'Template' : `Layout — ${(LAYOUTS[p.data.layout] || LAYOUTS.blank).name}`}</small>
                <${Btn} kind="is-sm is-danger" on=${() => app.deletePreset(p)}>Delete<//></div>`)}
        <//>
    </div>`;
}
