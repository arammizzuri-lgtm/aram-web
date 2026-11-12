// The layout engine: one page of the document in, a flat list of things to draw
// out — in points, top-left origin. The editor's preview (view.js) and the PDF
// export (export.js) both draw this same list, which is why they match.
//
// Layouts balance themselves around what is filled in (an empty field leaves no
// gap; a long title is set smaller). Anything can still be moved, resized,
// restyled or hidden: those choices are kept per element in page.ov[key] and
// applied here.
import { MM, LAYOUTS, INFO_LABEL, LOW_PPI, pageSize, structure, tokens, fillTokens, fontKey, sourceSize, scaleLabel, parseScale, isDivider } from '@pm/model';
import { layoutText } from '@pm/text';

// Type roles — sizes in points on A4, scaled up for larger pages.
const ROLES = {
    display: { font: 'title', size: 46, lh: 1.04 },
    h1: { font: 'title', size: 30, lh: 1.1 },
    h2: { font: 'title', size: 15, lh: 1.22 },
    lead: { font: 'body', size: 11, lh: 1.45 },
    body: { font: 'body', size: 9, lh: 1.55 },
    value: { font: 'body', size: 9, lh: 1.35 },
    caption: { font: 'body', size: 8, lh: 1.45, muted: true },
    label: { font: 'label', size: 6.4, lh: 1.5, muted: true },
};
export const ROLE_NAMES = { display: 'Display', h1: 'Heading', h2: 'Title', lead: 'Lead', body: 'Text', caption: 'Caption', label: 'Label' };

/**
 * env: { brand, assets: Map(hash → asset), edit: bool, logoRatio(hash) → w/h }
 * returns { w, h, bg, items, frames, warnings, content }
 *   frames[key] — where each selectable element is, for the editor
 */
export function layoutPage(doc, index, env) {
    const page = doc.pages[index];
    const th = doc.theme;
    const { w: W, h: H } = pageSize(doc, page);
    const S = structure(doc);
    const st = S[index];
    const land = W > H;
    const k = Math.pow(Math.min(W, H) / 595.28, 0.75) * (th.scale || 1);
    const col = th.colors;
    const m = { t: th.margins.t * MM, r: th.margins.r * MM, b: th.margins.b * MM, l: th.margins.l * MM };
    const T = tokens(doc, env.brand, { page: String(st.no), pages: String(doc.pages.length), section: st.section });

    const items = [], frames = {}, warnings = [];
    let bg = page.opts.bg || col.bg, fg = col.text, muted = col.muted;

    const ovOf = (key) => page.ov[key] || {};
    const denorm = (f) => ({ x: f[0] * W, y: f[1] * H, w: f[2] * W, h: f[3] * H });
    const boxOf = (key, box) => (ovOf(key).f ? denorm(ovOf(key).f) : box);
    const lineH = (role, sz = 1) => ROLES[role].size * k * sz * ROLES[role].lh;
    const asset = (s) => (s && s.asset ? env.assets.get(s.asset) : null);
    const has = (v) => v != null && String(v).trim() !== '';

    function style(role, key, o) {
        const r = ROLES[role], ov = ovOf(o.group || key), f = th.fonts[r.font];
        return {
            key: fontKey(ov.font || f.f, ov.weight || f.w, ov.italic ?? o.italic),
            size: r.size * k * (o.sz || 1) * (ov.sz || 1),
            lh: r.lh,
            ls: ov.ls ?? f.ls ?? 0,
            up: ov.up ?? f.up ?? false,
            color: ov.color || o.color || (r.muted ? muted : fg),
        };
    }
    /** The text set in a width: its style (a long title made smaller to fit) and its lines. */
    function build(key, str, role, width, o) {
        const s = style(role, key, o);
        const up = s.up ? str.toUpperCase() : str;
        let lay = layoutText(up, s, width);
        if (o.fit) for (let i = 0; i < 16 && (lay.lines.length > o.fit || lay.w > width + 0.5); i++) { s.size *= 0.93; lay = layoutText(up, s, width); }
        return { s, lay, h: lay.lines.length * s.size * s.lh };
    }
    const resolve = (raw) => fillTokens(raw, T).trim();
    /** How tall a text will be, without placing it. */
    const hOf = (key, raw, role, width, o = {}) => {
        const str = resolve(raw);
        return !str || ovOf(o.group || key).hide ? 0 : build(key, str, role, width, o).h;
    };
    const wOf = (key, str, role, o = {}) => (has(str) ? build(key, String(str), role, 1e6, o).lay.w : 0);

    /** Place a text; returns its natural height (for stacking what follows). */
    function text(key, raw, role, box, o = {}) {
        const str = resolve(raw);
        if (!str || ovOf(o.group || key).hide) return 0;
        const natural = build(key, str, role, box.w, o);
        const moved = !o.group && ovOf(key).f;
        const b = moved ? denorm(moved) : box;
        const use = moved ? build(key, str, role, b.w, o) : natural;
        const lh = use.s.size * use.s.lh;
        const valign = moved ? 'top' : o.valign || 'top';
        const top = valign === 'bottom' ? b.y + (b.h || 0) - use.h : valign === 'middle' ? b.y + ((b.h || 0) - use.h) / 2 : b.y;
        const align = ovOf(o.group || key).align || o.align || 'start';
        items.push({
            t: 'text', key, color: use.s.color, size: use.s.size, lh, fontKey: use.s.key, opacity: o.opacity,
            lines: use.lay.lines.map((ln, i) => {
                // a right-to-left line sits flush right within the text's own width; the block stays where the layout put it
                const x0 = b.x + (align === 'center' ? (b.w - ln.w) / 2 : align === 'right' ? b.w - ln.w : align === 'start' && ln.rtl ? use.lay.w - ln.w : 0);
                return { top: top + i * lh, runs: ln.runs.map((r) => ({ x: x0 + r.x, w: r.w, text: r.text, key: r.key, rtl: r.rtl, ls: r.ls })) };
            }),
        });
        if (o.select !== false) frames[key] = { x: b.x, y: top, w: b.w, h: use.h, kind: 'text', role };
        return natural.h;
    }

    /** Place an image or a PDF page in a frame: whole ("fit") or filling it ("fill", cropped around a focal point). */
    function image(key, s, box, o = {}) {
        if (ovOf(key).hide) return null;
        const b = boxOf(key, box);
        const a = asset(s);
        const src = a && sourceSize(a, s.page);
        if (!src) {
            if (o.optional) return null;
            if (env.edit) items.push({ t: 'ph', key, ...b, label: a ? 'Preparing the preview…' : o.label || 'Image' });
            frames[key] = { ...b, kind: 'image', empty: true };
            if (!a) warnings.push({ type: 'empty', key });
            return null;
        }
        const fit = s.fit || o.fit || 'fill';
        const sr = src.w / src.h, br = b.w / b.h;
        let dw, dh, dx, dy;
        if (fit === 'fit') {
            if (sr > br) { dw = b.w; dh = dw / sr; } else { dh = b.h; dw = dh * sr; }
            dx = b.x + (b.w - dw) * (o.ax ?? 0.5);
            dy = b.y + (b.h - dh) * (o.ay ?? 0.5);
        } else {
            const z = Math.max(1, s.zoom || 1);
            if (sr > br) { dh = b.h * z; dw = dh * sr; } else { dw = b.w * z; dh = dw / sr; }
            dx = b.x - (dw - b.w) * (s.fx ?? 0.5);
            dy = b.y - (dh - b.h) * (s.fy ?? 0.5);
        }
        const item = { t: 'image', key, x: b.x, y: b.y, w: b.w, h: b.h, dx, dy, dw, dh, hash: a.hash, kind: a.kind, pdfPage: s.page || 0, fit, src, drawing: !!o.drawing };
        if (a.kind === 'pdf') {   // a CAD sheet has no ground of its own: it is given its white paper, on any page colour
            const x0 = Math.max(dx, b.x), y0 = Math.max(dy, b.y);
            items.push({ t: 'rect', x: x0, y: y0, w: Math.min(dx + dw, b.x + b.w) - x0, h: Math.min(dy + dh, b.y + b.h) - y0, fill: '#ffffff' });
        }
        if (a.kind === 'image') {
            item.ppi = src.w / (dw / 72);
            if (item.ppi < LOW_PPI) warnings.push({ type: 'lowres', key, ppi: Math.round(item.ppi), name: a.name });
        } else {
            item.k = dw / src.w;   // how much of its original size the sheet is placed at
        }
        items.push(item);
        frames[key] = { ...b, kind: 'image', fit, ppi: item.ppi, pdf: a.kind === 'pdf', k: item.k };
        return item;
    }

    const rule = (x, y, w, color = col.line, lw = 0.6) => items.push({ t: 'line', x1: x, y1: y, x2: x + w, y2: y, color, lw });

    /** The studio's logo — and, when the presentation is a collaboration, the partner's beside it. */
    function logos(key, box, align = 'left', o = {}) {
        if (ovOf(key).hide) return 0;
        const b = boxOf(key, box);
        const partner = o.partner === false ? null : (env.brand.partners || []).find((p) => p.id === doc.partner);
        const list = [
            env.brand.logo && { hash: env.brand.logo, mono: env.brand.logoMono !== false },
            partner && partner.logo && { hash: partner.logo, mono: partner.mono !== false },
        ].filter(Boolean).map((l) => ({ ...l, w: b.h * env.logoRatio(l.hash) }));
        if (!list.length) return 0;
        const gap = b.h * 0.85;
        const total = list.reduce((a, l) => a + l.w, 0) + gap * (list.length - 1);
        const sc = Math.min(1, b.w / total), h = b.h * sc;
        let x = b.x + (align === 'center' ? (b.w - total * sc) / 2 : align === 'right' ? b.w - total * sc : 0);
        const y = b.y + (b.h - h) / 2;
        list.forEach((l, i) => {
            if (i) items.push({ t: 'line', x1: x - gap * sc / 2, y1: y - h * 0.08, x2: x - gap * sc / 2, y2: y + h * 1.08, color: o.color || muted, lw: 0.5 });
            items.push({ t: 'logo', key, x, y, w: l.w * sc, h, hash: l.hash, color: l.mono ? ovOf(key).color || o.color || fg : null });
            x += (l.w + gap) * sc;
        });
        frames[key] = { ...b, kind: 'logo' };
        return b.h;
    }

    /** Label-over-value cells for the fields that are filled in; anchored to the box's top, or its bottom. */
    const cellsFor = (keys) => keys.map((key) => [INFO_LABEL[key], T[key]]).filter(([, v]) => has(v));
    function grid(key, cells, box, cols, o = {}) {
        if (!cells.length || ovOf(key).hide) return 0;
        const b = boxOf(key, box);
        const gut = 14 * k, cw = (b.w + gut) / cols, gapY = 12 * k, lh = lineH('label') + 2 * k;
        const g = { group: key, select: false };
        const rows = [];
        for (let i = 0; i < cells.length; i += cols) {
            const row = cells.slice(i, i + cols);
            rows.push({ row, h: lh + Math.max(...row.map(([, v], j) => build(`${key}:v${i + j}`, String(v), 'value', cw - gut, { ...g, fit: 2 }).h)) });
        }
        const total = rows.reduce((a, r) => a + r.h, 0) + gapY * (rows.length - 1);
        let y = o.bottom && !ovOf(key).f ? b.y + b.h - total : b.y, n = 0;
        frames[key] = { x: b.x, y, w: b.w, h: total, kind: 'group' };
        for (const { row, h } of rows) {
            row.forEach(([label, value], j) => {
                const x = b.x + j * cw;
                text(`${key}:l${n}`, label, 'label', { x, y, w: cw - gut }, g);
                text(`${key}:v${n}`, String(value), 'value', { x, y: y + lh, w: cw - gut }, { ...g, fit: 2 });
                n++;
            });
            y += h + gapY;
        }
        return total;
    }

    // ── What surrounds the content: header, footer, page number ──
    const L = LAYOUTS[page.layout];
    const chromeOn = page.opts.chrome ?? (L.chrome !== false);
    const numberOnly = !chromeOn && page.opts.chrome === undefined
        && ((page.layout === 'cover' && !th.numbers.skipCover) || (page.layout === 'divider' && !th.numbers.skipDividers));
    const noNumber = (page.layout === 'cover' && th.numbers.skipCover) || (page.layout === 'divider' && th.numbers.skipDividers);
    const logoAt = th.logo.at || 'none';
    const zoneH = lineH('label'), zoneGap = 16 * k;
    const headerOn = chromeOn && (th.header.on || logoAt.startsWith('header'));
    const footerOn = chromeOn && (th.footer.on || logoAt.startsWith('footer'));
    const P = { x: m.l, y: m.t, w: W - m.l - m.r, h: H - m.t - m.b };   // inside the margins
    const C = {   // inside the margins and clear of the header and footer
        x: P.x, w: P.w,
        y: P.y + (headerOn ? zoneH + zoneGap : 0),
        h: P.h - (headerOn ? zoneH + zoneGap : 0) - (footerOn ? zoneH + zoneGap : 0),
    };
    function chrome() {
        const zone = (name, cfg, y, lineY) => {
            const shown = name === 'header' ? headerOn : footerOn;
            if (!shown && !numberOnly) return;
            const where = { l: 'left', c: 'center', r: 'right' };
            for (const pos of ['l', 'c', 'r']) {
                const raw = cfg.on ? cfg[pos] : '';
                if (logoAt === `${name}-${where[pos]}` && shown) {
                    const lh = zoneH * 1.7 * (th.logo.size || 1);
                    logos(`_logo`, { x: P.x, y: y + (zoneH - lh) / 2, w: P.w, h: lh }, where[pos], { partner: false });
                    delete frames._logo;
                    continue;
                }
                if (!raw || (raw.includes('{page}') && noNumber) || (numberOnly && !raw.includes('{page}'))) continue;
                text(`_${name}:${pos}`, raw, 'label', { x: P.x, y, w: P.w }, { align: where[pos], select: false });
            }
            if (cfg.on && cfg.line && shown) rule(P.x, lineY, P.w);
        };
        zone('header', th.header, P.y, P.y + zoneH + zoneGap * 0.45);
        zone('footer', th.footer, P.y + P.h - zoneH, P.y + P.h - zoneH - zoneGap * 0.45);
    }

    // ── The layouts ──
    const imgs = page.images;
    const layouts = {
        cover() {
            const variant = page.opts.variant || th.cover || 'type';
            const note = () => {   // the small print along the bottom edge; returns the space it took
                const contact = doc.info.showContact ? '{website}   ·   {email}   ·   {phone}' : '';
                const hn = hOf('confidential', '{confidential}', 'caption', P.w * 0.58), hc = hOf('contact', contact, 'label', P.w * 0.4);
                const bottom = P.y + P.h;
                text('confidential', '{confidential}', 'caption', { x: P.x, y: bottom - hn, w: P.w * 0.58 });
                text('contact', contact, 'label', { x: P.x + P.w * 0.6, y: bottom - hc, w: P.w * 0.4 }, { align: 'right' });
                return Math.max(hn, hc) ? Math.max(hn, hc) + 14 * k : 0;
            };

            if (variant === 'logo') {
                const lh = Math.min(W, H) * 0.085, tw = P.w * 0.72, x = P.x + (P.w - tw) / 2;
                const hp = hOf('project', '{project}', 'h1', tw, { fit: 2 });
                const where = [T.client, T.location].filter(has).join('   ·   ');
                const hw = hOf('where', where, 'label', tw, { sz: 1.15 });
                const total = lh + 30 * k + 24 * k + hp + (hw ? 12 * k + hw : 0);
                let y = (H - total) / 2 - H * 0.02;
                logos('logo', { x, y, w: tw, h: lh }, 'center');
                y += lh + 30 * k;
                rule(W / 2 - 14 * k, y, 28 * k, col.accent, 0.8);
                y += 24 * k;
                text('project', '{project}', 'h1', { x, y, w: tw }, { fit: 2, align: 'center' });
                text('where', where, 'label', { x, y: y + hp + 12 * k, w: tw }, { align: 'center', sz: 1.15 });
                const issue = [T.docTitle, T.stage, T.date, has(T.revision) && `Rev ${T.revision}`].filter(has).join('   ·   ');
                const taken = note();
                text('issue', issue, 'label', { x, y: P.y + P.h - taken - lineH('label'), w: tw }, { align: 'center' });
                return;
            }

            if (variant === 'image') {
                const ih = H * (land ? 0.6 : 0.54);
                image('img:0', imgs[0], { x: 0, y: 0, w: W, h: ih }, { fit: 'fill', label: 'Cover image' });
                const top = ih + 18 * k, tw = P.w * (land ? 0.56 : 1);
                logos('logo', { x: P.x + P.w * 0.6, y: top, w: P.w * 0.4, h: 17 * k }, 'right');
                let y = top;
                const hd = text('docTitle', '{docTitle}', 'label', { x: P.x, y, w: tw }, { color: col.accent });
                y += hd ? hd + 6 * k : 0;
                y += text('project', '{project}', 'display', { x: P.x, y, w: tw }, { fit: 2, sz: 0.6 });
                text('where', [T.client, T.location].filter(has).join('   ·   '), 'lead', { x: P.x, y: y + 6 * k, w: tw }, { color: muted });
                const taken = note();
                grid('info', cellsFor(['type', 'plotArea', 'builtArea', 'stage', 'date', 'ref', 'revision', 'team', 'issuedFor']),
                    { x: P.x, y: top, w: P.w, h: P.y + P.h - taken - top }, land ? 6 : 3, { bottom: true });
                return;
            }

            // typography-led: the project's name carries the page
            logos('logo', { x: P.x, y: P.y, w: P.w * 0.55, h: 24 * k }, 'left');
            const hs = text('stage', '{stage}', 'label', { x: P.x + P.w * 0.5, y: P.y, w: P.w * 0.5 }, { align: 'right', color: col.accent });
            text('date', '{date}', 'label', { x: P.x + P.w * 0.5, y: P.y + hs, w: P.w * 0.5 }, { align: 'right' });
            let bottom = P.y + P.h - note();
            const gh = grid('info', cellsFor(['client', 'location', 'type', 'plotArea', 'builtArea', 'ref', 'revision', 'team', 'issuedFor']),
                { x: P.x, y: P.y, w: P.w, h: bottom - P.y }, land ? 5 : 3, { bottom: true });
            if (gh) { bottom -= gh + 14 * k; rule(P.x, bottom, P.w); bottom -= 24 * k; }
            const tw = P.w * (land ? 0.82 : 1);
            const hp = hOf('project', '{project}', 'display', tw, { fit: 3 });
            text('project', '{project}', 'display', { x: P.x, y: bottom - hp, w: tw }, { fit: 3 });
            const hd = hOf('docTitle', '{docTitle}', 'label', tw);
            text('docTitle', '{docTitle}', 'label', { x: P.x, y: bottom - hp - 12 * k - hd, w: tw }, { color: col.accent });
        },

        divider() {
            const variant = page.opts.variant || th.divider || 'line';
            if (asset(imgs[0]) && image('img:0', imgs[0], { x: 0, y: 0, w: W, h: H }, { fit: 'fill', optional: true })) {
                items.push({ t: 'rect', x: 0, y: 0, w: W, h: H, fill: '#000000', opacity: page.opts.scrim ?? 0.45 });
                fg = '#ffffff'; muted = '#dcdcdc';
            } else if (variant === 'block') {
                bg = page.opts.bg || col.text; fg = col.bg; muted = col.line;
            }
            const num = page.opts.number === false ? '' : String(st.dividerNo).padStart(2, '0');
            const centered = variant === 'centered';
            const tw = P.w * (centered ? 0.74 : land ? 0.62 : 0.92), x = centered ? P.x + (P.w - tw) / 2 : P.x;
            const align = centered ? 'center' : 'start';
            const hn = hOf('number', num, 'label', tw, { sz: 1.5 });
            const ht = hOf('title', page.title, 'h1', tw, { fit: 3 });
            const hsub = hOf('subtitle', page.subtitle, 'lead', tw);
            const total = (hn ? hn + 14 * k : 0) + 18 * k + ht + (hsub ? 10 * k + hsub : 0);
            let y = centered ? (H - total) / 2 : variant === 'block' ? P.y + P.h - total : H * 0.56 - total / 2;
            if (hn) { text('number', num, 'label', { x, y, w: tw }, { align, sz: 1.5, color: col.accent }); y += hn + 14 * k; }
            const rw = 30 * k;
            rule(centered ? W / 2 - rw / 2 : x, y, rw, col.accent, 0.9);
            y += 18 * k;
            text('title', page.title, 'h1', { x, y, w: tw }, { align, fit: 3 });
            text('subtitle', page.subtitle, 'lead', { x, y: y + ht + 10 * k, w: tw }, { align, color: muted });
        },

        contents() {
            let y = C.y + text('title', page.title || 'Contents', 'h1', { x: C.x, y: C.y, w: C.w }, { sz: 0.8, fit: 2 }) + 26 * k;
            const rw = land ? C.w * 0.62 : C.w, rowH = lineH('lead') * 2.1, g = { group: 'toc', select: false };
            const rows = doc.pages.map((p, i) => (isDivider(p) ? { no: S[i].dividerNo, title: p.title, page: i + 1 } : null)).filter(Boolean);
            if (ovOf('toc').hide) return;
            const top = y;
            rows.forEach((r, i) => {
                const off = (lineH('lead') - lineH('label')) / 2;
                text(`toc:n${i}`, String(r.no).padStart(2, '0'), 'label', { x: C.x, y: y + off, w: 30 * k }, { ...g, color: col.accent });
                text(`toc:t${i}`, r.title || '—', 'lead', { x: C.x + 34 * k, y, w: rw - 80 * k }, { ...g, fit: 1 });
                text(`toc:p${i}`, String(r.page), 'label', { x: C.x, y: y + off, w: rw }, { ...g, align: 'right' });
                rule(C.x, y + rowH - 9 * k, rw);
                y += rowH;
            });
            if (rows.length) frames.toc = { x: C.x, y: top, w: rw, h: y - top, kind: 'group' };
            else if (env.edit) items.push({ t: 'ph', key: 'toc', x: C.x, y, w: rw, h: rowH * 2, label: 'Section dividers will be listed here' });
            if (y > C.y + C.h) warnings.push({ type: 'overflow', key: 'toc' });
        },

        closing() {
            const lh = Math.min(W, H) * 0.07, tw = P.w * 0.7, x = P.x + (P.w - tw) / 2;
            const contact = [T.address, [T.phone, T.email].filter(has).join('   ·   '), T.website].filter(has).join('\n');
            const ht = hOf('title', page.title, 'h2', tw), hc = hOf('contact', contact, 'label', tw, { sz: 1.15 });
            const total = lh + 30 * k + (ht ? ht + 20 * k : 0) + hc;
            let y = (H - total) / 2 - H * 0.02;
            logos('logo', { x, y, w: tw, h: lh }, 'center');
            y += lh + 30 * k;
            if (ht) { text('title', page.title, 'h2', { x, y, w: tw }, { align: 'center' }); y += ht + 20 * k; }
            text('contact', contact, 'label', { x, y, w: tw }, { align: 'center', sz: 1.15 });
        },

        overview() {
            const g = 26 * k, tw = land ? C.w * 0.36 : C.w;
            const facts = page.opts.facts === false ? [] : cellsFor(['client', 'location', 'type', 'plotArea', 'builtArea']);
            let y = C.y + text('title', page.title, 'h1', { x: C.x, y: C.y, w: tw }, { fit: 3, sz: 0.8 });
            y += y > C.y ? 16 * k : 0;
            y += text('body', page.body, 'body', { x: C.x, y, w: tw });
            let area;
            if (land) {
                const fh = grid('facts', facts, { x: C.x, y: C.y, w: tw, h: C.h }, 2, { bottom: true });
                if (y > C.y + C.h - fh - (fh ? 10 * k : 0) + 1) warnings.push({ type: 'overflow', key: 'body' });
                area = { x: C.x + tw + g, y: C.y, w: C.w - tw - g, h: C.h };
            } else {
                const fh = grid('facts', facts, { x: C.x, y: y + (y > C.y ? 18 * k : 0), w: tw, h: 0 }, 3);
                y += fh ? fh + 18 * k : 0;
                area = { x: C.x, y: y + g, w: C.w, h: C.y + C.h - y - g };
                if (area.h < C.h * 0.2) warnings.push({ type: 'overflow', key: 'body' });
            }
            const n = imgs.length, gap = 8 * k;
            imgs.forEach((s, i) => {
                const b = land
                    ? { x: area.x, y: area.y + i * (area.h + gap) / n, w: area.w, h: (area.h - gap * (n - 1)) / n }
                    : { x: area.x + i * (area.w + gap) / n, y: area.y, w: (area.w - gap * (n - 1)) / n, h: area.h };
                if (b.h > 10 && b.w > 10) image(`img:${i}`, s, b, { fit: 'fill' });
            });
        },

        'image-full'() { image('img:0', imgs[0], { x: 0, y: 0, w: W, h: H }, { fit: 'fill' }); },
        'image-fit'() { image('img:0', imgs[0], C, { fit: 'fit' }); },

        'image-caption'() {
            const side = page.opts.textPos === 'side' && land;
            const tw = side ? C.w * 0.24 : C.w * (land ? 0.6 : 1);
            const ht = hOf('title', page.title, 'h2', tw), hc = hOf('caption', page.caption, 'body', tw);
            const th2 = ht + (ht && hc ? 6 * k : 0) + hc;
            if (side) {
                const gap = th2 ? 22 * k : 0, iw = C.w - (th2 ? tw + gap : 0);
                image('img:0', imgs[0], { x: C.x, y: C.y, w: iw, h: C.h }, { fit: 'fit', ax: 0 });
                const x = C.x + iw + gap, y = C.y + C.h - th2;
                text('title', page.title, 'h2', { x, y, w: tw });
                text('caption', page.caption, 'body', { x, y: y + ht + (ht ? 6 * k : 0), w: tw }, { color: muted });
                return;
            }
            const ih = C.h - (th2 ? th2 + 16 * k : 0);
            image('img:0', imgs[0], { x: C.x, y: C.y, w: C.w, h: ih }, { fit: 'fit', ax: 0, ay: 1 });
            const y = C.y + C.h - th2;
            text('title', page.title, 'h2', { x: C.x, y, w: tw });
            text('caption', page.caption, 'body', { x: C.x, y: y + ht + (ht ? 6 * k : 0), w: tw }, { color: muted });
        },

        'two-up'() { tiles(2, 1); },
        'grid'() {
            const n = imgs.length;
            if (n === 3) {   // one large, two beside (or below) it
                const top = titleTop(), gap = 8 * k, a = { x: C.x, y: top, w: C.w, h: C.y + C.h - top };
                const big = land ? { x: a.x, y: a.y, w: a.w * 0.62 - gap / 2, h: a.h } : { x: a.x, y: a.y, w: a.w, h: a.h * 0.58 - gap / 2 };
                image('img:0', imgs[0], big, { fit: 'fill' });
                for (const i of [1, 2]) {
                    image(`img:${i}`, imgs[i], land
                        ? { x: a.x + a.w * 0.62 + gap / 2, y: a.y + (i - 1) * (a.h + gap) / 2, w: a.w * 0.38 - gap / 2, h: (a.h - gap) / 2 }
                        : { x: a.x + (i - 1) * (a.w + gap) / 2, y: a.y + a.h * 0.58 + gap / 2, w: (a.w - gap) / 2, h: a.h * 0.42 - gap / 2 }, { fit: 'fill' });
                }
                return;
            }
            tiles(n <= 4 ? 2 : land ? 3 : 2, Math.ceil(n / (n <= 4 ? 2 : land ? 3 : 2)));
        },
        'materials'() {
            const n = imgs.length, cols = land ? (n <= 4 ? Math.max(n, 2) : n <= 6 ? 3 : 4) : n <= 4 ? 2 : 3;
            tiles(cols, Math.ceil(n / cols), 'label');
        },

        drawing() {
            const d = page.drawing, s = imgs[0];
            const stripH = Math.max(lineH('h2'), lineH('label') + 2 * k + lineH('value'));
            const stripY = C.y + C.h - stripH;
            rule(C.x, stripY - 9 * k, C.w);
            const area = { x: C.x, y: C.y, w: C.w, h: stripY - 18 * k - C.y };
            if (has(page.notes) && !ovOf('notes').hide) {
                const lh = lineH('label') + 5 * k;
                if (land) {
                    const nw = C.w * 0.2, x = C.x + C.w - nw;
                    area.w -= nw + 20 * k;
                    text('notes:l', 'Notes', 'label', { x, y: area.y, w: nw }, { group: 'notes', select: false });
                    const h = text('notes', page.notes, 'body', { x, y: area.y + lh, w: nw });
                    if (lh + h > area.h) warnings.push({ type: 'overflow', key: 'notes' });
                } else {
                    const h = hOf('notes', page.notes, 'body', C.w * 0.8) + lh;
                    area.h -= h + 14 * k;
                    text('notes:l', 'Notes', 'label', { x: C.x, y: area.y + area.h + 14 * k, w: C.w }, { group: 'notes', select: false });
                    text('notes', page.notes, 'body', { x: C.x, y: area.y + area.h + 14 * k + lh, w: C.w * 0.8 });
                    if (area.h < C.h * 0.3) warnings.push({ type: 'overflow', key: 'notes' });
                }
            }
            const img = image('img:0', s, area, { fit: 'fit', label: 'Drawing — a PDF page or an image', drawing: true });

            // A scale is printed only when it is true of the page as it will be printed.
            let scale = null;
            if (has(d.scale)) {
                const a = asset(s);
                if (!parseScale(d.scale)) warnings.push({ type: 'scale', key: 'img:0', text: 'Write the scale as 1:100.' });
                else if (!a || a.kind !== 'pdf' || !img) warnings.push({ type: 'scale', key: 'img:0', text: 'The scale is left off: only a PDF drawing has a known sheet size, so only there can a scale be checked.' });
                else {
                    scale = scaleLabel(d.scale, img.k, [img.src.w / MM, img.src.h / MM]);
                    if (!scale) warnings.push({ type: 'scale', key: 'img:0', text: `The scale is left off: the drawing is placed at ${Math.round(img.k * 100)}% of a sheet that is not a standard size, so 1:${parseScale(d.scale)} would no longer be true.` });
                }
            }
            if (d.north && !ovOf('north').hide) {
                const size = 30 * k, b = boxOf('north', { x: area.x + area.w - size, y: area.y, w: size, h: size });
                items.push({ t: 'north', key: 'north', x: b.x, y: b.y, size: Math.min(b.w, b.h), angle: +d.northAngle || 0, color: fg });
                frames.north = { ...b, kind: 'north' };
            }

            text('title', page.title, 'h2', { x: C.x, y: stripY, w: C.w * 0.5, h: stripH }, { valign: 'bottom', fit: 2 });
            if (ovOf('meta').hide) return;
            const meta = [['Drawing no.', d.number], ['Area', d.area], ['Scale', scale]].filter(([, v]) => has(v));
            let x = C.x + C.w;
            const g = { group: 'meta', select: false };
            const y = stripY + stripH - (lineH('label') + 2 * k + lineH('value'));
            for (let i = meta.length - 1; i >= 0; i--) {
                const [label, value] = meta[i];
                const w = Math.max(wOf(`meta:l${i}`, label, 'label', g), wOf(`meta:v${i}`, value, 'value', g)) + 1;
                x -= w;
                text(`meta:l${i}`, label, 'label', { x, y, w }, g);
                text(`meta:v${i}`, String(value), 'value', { x, y: y + lineH('label') + 2 * k, w }, g);
                x -= 26 * k;
            }
            if (meta.length) frames.meta = { x: x + 26 * k, y, w: C.x + C.w - x - 26 * k, h: stripY + stripH - y, kind: 'group' };
        },

        sheet() { image('img:0', imgs[0], { x: 0, y: 0, w: W, h: H }, { fit: 'fit', label: 'Drawing sheet — a PDF page', drawing: true }); },
        blank() {},
    };

    /** Where the images start under an optional page title. */
    function titleTop() {
        const h = text('title', page.title, 'h2', { x: C.x, y: C.y, w: C.w });
        return C.y + (h ? h + 14 * k : 0);
    }
    /** A grid of image tiles, each with an optional caption under it. */
    function tiles(cols, rows, capRole = 'caption') {
        const top = titleTop(), gap = 8 * k, cw = (C.w - gap * (cols - 1)) / cols;
        const capH = imgs.some((s) => has(s.caption)) ? Math.max(...imgs.map((s, i) => hOf(`cap:${i}`, s.caption, capRole, cw))) + 7 * k : 0;
        const rh = (C.y + C.h - top - gap * (rows - 1)) / rows;
        imgs.forEach((s, i) => {
            const x = C.x + (i % cols) * (cw + gap), y = top + Math.floor(i / cols) * (rh + gap);
            image(`img:${i}`, s, { x, y, w: cw, h: rh - capH }, { fit: 'fill' });
            text(`cap:${i}`, s.caption, capRole, { x, y: y + rh - capH + 7 * k, w: cw });
        });
    }

    (layouts[page.layout] || layouts.blank)();

    // ── What was added by hand, on top ──
    for (const f of page.free) {
        const key = `free:${f.id}`, b = boxOf(key, { x: W * 0.3, y: H * 0.4, w: W * 0.4, h: H * 0.2 });
        if (f.t === 'text') text(key, f.text, f.role || 'body', b);
        else if (f.t === 'image') image(key, f, b, { fit: 'fit' });
        else if (f.t === 'rect') { items.push({ t: 'rect', key, ...b, fill: f.fill || col.line, opacity: f.opacity ?? 1 }); frames[key] = { ...b, kind: 'rect' }; }
        else if (f.t === 'line') {
            const horiz = b.w >= b.h;
            items.push({ t: 'line', key, x1: horiz ? b.x : b.x + b.w / 2, y1: horiz ? b.y + b.h / 2 : b.y, x2: horiz ? b.x + b.w : b.x + b.w / 2, y2: horiz ? b.y + b.h / 2 : b.y + b.h, color: f.color || fg, lw: f.lw || 0.75 });
            frames[key] = { ...b, kind: 'line' };
        }
    }

    chrome();

    if (doc.watermark.on && has(doc.watermark.text)) {
        const key = fontKey('Inter', 600), probe = layoutText(doc.watermark.text.toUpperCase(), { key, size: 100, lh: 1, ls: 0.12 }, 1e6);
        const size = Math.hypot(W, H) * 0.62 / probe.w * 100;
        const lay = layoutText(doc.watermark.text.toUpperCase(), { key, size, lh: 1, ls: 0.12 }, 1e6);
        items.push({ t: 'wm', color: fg, size, fontKey: key, w: lay.w, runs: lay.lines[0].runs, cx: W / 2, cy: H / 2, angle: Math.atan2(H, W) * 180 / Math.PI, opacity: 0.09 });
    }

    return { w: W, h: H, bg, items, frames, warnings, content: C, margins: P };
}

/**
 * The north arrow, turned by an angle, as paths in a 100-unit square: the ring,
 * the arrow (filled) and the letter N (stroked) — the same for the preview and the PDF.
 */
export function northShape(angle = 0) {
    const a = angle * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
    const p = ([x, y]) => `${(50 + (x - 50) * cos - (y - 50) * sin).toFixed(2)} ${(50 + (x - 50) * sin + (y - 50) * cos).toFixed(2)}`;
    const path = (pts, close) => pts.map((pt, i) => `${i ? 'L' : 'M'}${p(pt)}`).join(' ') + (close ? ' Z' : '');
    return {
        ring: 'M22 56 A28 28 0 1 0 78 56 A28 28 0 1 0 22 56',
        arrow: path([[50, 22], [61, 74], [50, 64], [39, 74]], true),
        n: path([[45, 15], [45, 3], [55, 15], [55, 3]], false),
    };
}

/** Every font a document's pages use, so they can be loaded before the first layout. */
export function fontsOf(doc) {
    const keys = new Set([fontKey('Inter', 600)]);
    for (const f of Object.values(doc.theme.fonts)) keys.add(fontKey(f.f, f.w));
    for (const p of doc.pages) for (const ov of Object.values(p.ov)) if (ov.font || ov.weight || ov.italic) {
        for (const f of Object.values(doc.theme.fonts)) keys.add(fontKey(ov.font || f.f, ov.weight || f.w, ov.italic));
    }
    return [...keys];
}
