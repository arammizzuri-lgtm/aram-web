// The presentation document: its shape, the templates that ship with the editor,
// and the small pure helpers the layout, the editor and the PDF export share.
// Nothing here touches the page — tests/js/presentations.test.mjs runs it under node.

export const MM = 72 / 25.4;   // points per millimetre
export const PAGE_MM = { A4: [210, 297], A3: [297, 420] };
export const ISO_MM = { A0: [841, 1189], A1: [594, 841], A2: [420, 594], A3: [297, 420], A4: [210, 297] };

export const uid = () => Math.random().toString(36).slice(2, 10);
export const clone = (o) => JSON.parse(JSON.stringify(o));
const obj = (o) => (o && typeof o === 'object' && !Array.isArray(o) ? o : {});
const arr = (a) => (Array.isArray(a) ? a : []);

// ── Fonts: the families that are embedded in the PDF (public/fonts/presentations) ──
export const FONTS = {
    'Jost': { key: 'jost', weights: [300, 400, 500] },
    'Inter': { key: 'inter', weights: [400, 500, 600] },
    'Cormorant Garamond': { key: 'cormorant', weights: [400, 500, 600], italic: true },
    'Bebas Neue': { key: 'bebas', weights: [400] },
};
/** Kurdish and Arabic are always set in Speda, whatever the family around them. */
export const RTL_FONT = 'speda-400';

export function fontKey(family, weight = 400, italic = false) {
    const f = FONTS[family] || FONTS.Inter;
    const w = f.weights.reduce((best, x) => (Math.abs(x - weight) < Math.abs(best - weight) ? x : best), f.weights[0]);
    return `${f.key}-${w}${italic && f.italic && w === 400 ? 'i' : ''}`;
}

// ── Templates ──
const base = {
    scale: 1,
    header: { on: false, l: '', c: '', r: '', line: false },
    footer: { on: true, l: '{project}', c: '', r: '{page}', line: false },
    numbers: { skipCover: true, skipDividers: true },
    logo: { at: 'none', size: 1 },
};
export const THEMES = {
    'minimal-white': {
        ...base, name: 'Minimal white',
        colors: { bg: '#ffffff', text: '#141414', muted: '#7b7b7b', accent: '#141414', line: '#d9d9d9' },
        fonts: {
            title: { f: 'Jost', w: 300, up: true, ls: 0.08 },
            body: { f: 'Inter', w: 400, up: false, ls: 0 },
            label: { f: 'Inter', w: 500, up: true, ls: 0.14 },
        },
        margins: { t: 14, r: 14, b: 12, l: 14 },
        cover: 'type', divider: 'line',
    },
    'editorial': {
        ...base, name: 'Editorial architecture',
        colors: { bg: '#f7f5f0', text: '#1c1b19', muted: '#79746b', accent: '#9a5b3c', line: '#cdc8bc' },
        fonts: {
            title: { f: 'Cormorant Garamond', w: 500, up: false, ls: 0 },
            body: { f: 'Inter', w: 400, up: false, ls: 0 },
            label: { f: 'Inter', w: 500, up: true, ls: 0.16 },
        },
        margins: { t: 16, r: 16, b: 14, l: 16 },
        header: { on: true, l: '{project}', c: '', r: '{section}', line: true },
        footer: { on: true, l: '{studio}', c: '', r: '{page} / {pages}', line: false },
        cover: 'image', divider: 'line',
    },
    'dark-portfolio': {
        ...base, name: 'Dark portfolio',
        colors: { bg: '#0f0f10', text: '#f3f1ec', muted: '#9c988f', accent: '#c9a45c', line: '#302f2c' },
        fonts: {
            title: { f: 'Bebas Neue', w: 400, up: true, ls: 0.03 },
            body: { f: 'Inter', w: 400, up: false, ls: 0 },
            label: { f: 'Inter', w: 500, up: true, ls: 0.16 },
        },
        margins: { t: 12, r: 12, b: 11, l: 12 },
        cover: 'image', divider: 'centered',
    },
};

/** A template by its id ("minimal-white", "saved:12") or as stored, with anything missing filled in. */
export function resolveTheme(theme, presets = []) {
    let t = theme;
    if (typeof t === 'string') {
        const saved = t.startsWith('saved:') ? presets.find((p) => p.type === 'theme' && String(p.id) === t.slice(6)) : null;
        t = saved ? saved.data : THEMES[t] || THEMES['minimal-white'];
    }
    const d = THEMES['minimal-white'];
    t = clone(obj(t));
    for (const k of ['colors', 'margins', 'header', 'footer', 'numbers', 'logo']) t[k] = { ...d[k], ...obj(t[k]) };
    t.fonts = obj(t.fonts);
    for (const k of ['title', 'body', 'label']) t.fonts[k] = { ...d.fonts[k], ...obj(t.fonts[k]) };
    return { ...clone(d), ...t };
}

// ── Page layouts ──
export const LAYOUTS = {
    'cover': { name: 'Cover', group: 'Structure', images: 1, texts: [], chrome: false, hint: 'Project name, client, key facts' },
    'divider': { name: 'Section divider', group: 'Structure', images: 1, texts: ['title', 'subtitle'], chrome: false, hint: 'Introduces a section' },
    'contents': { name: 'Contents', group: 'Structure', images: 0, texts: ['title'], hint: 'The sections, listed automatically' },
    'closing': { name: 'Closing page', group: 'Structure', images: 0, texts: ['title'], chrome: false, hint: 'Logo and contact details' },
    'overview': { name: 'Overview — text & images', group: 'Content', images: 1, texts: ['title', 'body'], hint: 'A text column beside one or two images' },
    'image-full': { name: 'Full-page image', group: 'Images', images: 1, texts: [], fit: 'fill', chrome: false, hint: 'Edge to edge' },
    'image-fit': { name: 'Image within margins', group: 'Images', images: 1, texts: [], fit: 'fit', hint: 'The whole image, nothing cropped' },
    'image-caption': { name: 'Image, title & caption', group: 'Images', images: 1, texts: ['title', 'caption'], fit: 'fit', hint: 'One image with words under or beside it' },
    'two-up': { name: 'Two images', group: 'Images', images: 2, texts: ['title'], fit: 'fill', captions: true, hint: 'Side by side' },
    'grid': { name: 'Image grid', group: 'Images', images: 4, texts: ['title'], fit: 'fill', captions: true, hint: 'Three or four images' },
    'materials': { name: 'Materials / mood board', group: 'Content', images: 8, texts: ['title'], fit: 'fill', captions: true, hint: 'Swatches with their names' },
    'drawing': { name: 'Floor plan / drawing', group: 'Drawings', images: 1, texts: ['title', 'notes'], fit: 'fit', hint: 'A drawing with its title, scale and notes' },
    'sheet': { name: 'Drawing sheet', group: 'Drawings', images: 1, texts: [], fit: 'fit', chrome: false, hint: 'A PDF page kept whole, at its own size' },
    'drawing-index': { name: 'Drawing index', group: 'Drawings', images: 0, texts: ['title'], hint: 'Every drawing with its number and scale, listed automatically' },
    'revisions': { name: 'Revision history', group: 'Structure', images: 0, texts: ['title'], hint: 'The revisions issued, from the Project tab' },
    'blank': { name: 'Blank page', group: 'Content', images: 0, texts: [], hint: 'Add your own text, images and lines' },
};
export const COVER_STYLES = [['type', 'Typography', 'The project’s name carries the page'], ['image', 'Image', 'A large picture above the project’s details'], ['logo', 'Logo', 'Your logo in the middle, the project under it']];
export const DIVIDER_STYLES = [['line', 'Line', 'Number, a short line and the title'], ['centered', 'Centred', 'All of it in the middle of the page'], ['block', 'Block', 'A solid page, the words at its foot']];

/** A page given another layout: its pictures, words and added elements kept; the old layout's options and moved places let go. */
export function relayout(page, id, isPdf = () => false) {
    const p = clone(page), fresh = newPage(id), had = p.images.filter((s) => s.asset);
    p.layout = id;
    p.images = fresh.images.map((s, i) => (had[i] ? { ...s, asset: had[i].asset, page: had[i].page, caption: had[i].caption, fit: isPdf(had[i].asset) ? 'fit' : s.fit } : s));
    p.ov = Object.fromEntries(Object.entries(p.ov).filter(([k]) => k.startsWith('free:')));
    p.opts = {};
    delete p.size;
    return p;
}

// ── The words the pages print themselves, in the presentation's language ──
export const LANGS = [['en', 'English'], ['ku', 'Kurdish (Sorani)'], ['ar', 'Arabic']];
const WORDS = {
    client: ['Client', 'خاوەن پرۆژە', 'العميل'],
    location: ['Location', 'شوێن', 'الموقع'],
    type: ['Project type', 'جۆری پرۆژە', 'نوع المشروع'],
    plotArea: ['Plot area', 'ڕووبەری زەوی', 'مساحة الأرض'],
    builtArea: ['Built-up area', 'ڕووبەری بنیاتنراو', 'مساحة البناء'],
    stage: ['Stage', 'قۆناغ', 'المرحلة'],
    date: ['Date', 'بەروار', 'التاريخ'],
    ref: ['Project no.', 'ژمارەی پرۆژە', 'رقم المشروع'],
    revision: ['Revision', 'پێداچوونەوە', 'المراجعة'],
    team: ['Design team', 'تیمی دیزاین', 'فريق التصميم'],
    issuedFor: ['Issued for', 'دەرچووە بۆ', 'صادر لغرض'],
    contents: ['Contents', 'ناوەڕۆک', 'المحتويات'],
    notes: ['Notes', 'تێبینی', 'ملاحظات'],
    drawingNo: ['Drawing no.', 'ژمارەی نەخشە', 'رقم المخطط'],
    area: ['Area', 'ڕووبەر', 'المساحة'],
    scale: ['Scale', 'پێوەر', 'مقياس الرسم'],
    drawing: ['Drawing', 'نەخشە', 'المخطط'],
    drawingIndex: ['Drawing index', 'پێڕستی نەخشەکان', 'فهرس المخططات'],
    page: ['Page', 'پەڕە', 'الصفحة'],
    rev: ['Rev', 'پێداچوونەوەی', 'مراجعة'],
    revisions: ['Revision history', 'مێژووی پێداچوونەوەکان', 'سجل المراجعات'],
    description: ['Description', 'وەسف', 'الوصف'],
    overview: ['Project Overview', 'پوختەی پرۆژە', 'نظرة عامة على المشروع'],
};
const LANG_AT = { ku: 1, ar: 2 };
/** One of the words above in the document's language. */
export const word = (doc, key) => (WORDS[key] ? WORDS[key][LANG_AT[doc.lang] || 0] : key);

/** Section titles offered for a divider — only titles; nothing about the project is made up. */
export const SECTIONS = ['Project Overview', 'Site Analysis', 'Concept Design', 'Site Plan', 'Floor Plans', 'Elevations', 'Sections',
    'Exterior Visualizations', 'Interior Visualizations', 'Materials & Finishes', 'Landscape Design'];
const SECTION_WORDS = {
    'Project Overview': ['پوختەی پرۆژە', 'نظرة عامة على المشروع'], 'Site Analysis': ['شیکاری شوێن', 'تحليل الموقع'],
    'Concept Design': ['بیرۆکەی دیزاین', 'التصميم المبدئي'], 'Site Plan': ['نەخشەی شوێن', 'المخطط العام للموقع'],
    'Floor Plans': ['نەخشەی نهۆمەکان', 'المساقط الأفقية'], 'Elevations': ['ڕووکارەکان', 'الواجهات'], 'Sections': ['بڕگەکان', 'المقاطع'],
    'Exterior Visualizations': ['وێنەی دەرەوە', 'اللقطات الخارجية'], 'Interior Visualizations': ['وێنەی ناوەوە', 'اللقطات الداخلية'],
    'Materials & Finishes': ['کەرەستە و ڕووپۆشەکان', 'المواد والتشطيبات'], 'Landscape Design': ['دیزاینی باخچە و دەوروبەر', 'تنسيق المواقع'],
};
/** A section's title as it is written in the document's language. */
export const sectionName = (title, lang) => (SECTION_WORDS[title] && LANG_AT[lang] ? SECTION_WORDS[title][LANG_AT[lang] - 1] : title);
export const TEXT_LABELS = { title: 'Title', subtitle: 'Subtitle', caption: 'Caption', body: 'Text', notes: 'Notes' };

/** The project's information: filled in once, shown wherever it is used. */
export const INFO = [
    ['project', 'Project name'], ['docTitle', 'Document title', 'e.g. Design presentation'], ['client', 'Client'], ['location', 'Location'],
    ['type', 'Project type', 'Villa, residential, commercial, interior…'], ['plotArea', 'Plot area'], ['builtArea', 'Built-up area'],
    ['stage', 'Stage', 'Concept design, schematic design, final presentation…'], ['date', 'Date'], ['ref', 'Project no.'],
    ['revision', 'Revision'], ['team', 'Architect / design team'], ['issuedFor', 'Issued for', 'e.g. Client review'],
    ['confidential', 'Confidentiality note'],
];
export const AREA_UNITS = ['m²', 'ft²', 'donum', 'ha'];

export const slot = (fit = 'fill') => ({ asset: null, page: 0, fit, fx: 0.5, fy: 0.5, zoom: 1, caption: '' });

export function newPage(layout, extra = {}) {
    const L = LAYOUTS[layout] || LAYOUTS.blank;
    const n = layout === 'grid' ? 4 : layout === 'cover' || layout === 'divider' ? 1 : L.images;
    return {
        id: uid(), layout, title: '', subtitle: '', caption: '', body: '', notes: '',
        images: Array.from({ length: n }, () => slot(L.fit || 'fill')),
        drawing: {}, opts: {}, ov: {}, free: [], ...extra,
    };
}

/** Make a stored document safe to work on: every page whole, every map a map. */
export function normalize(doc, presets = []) {
    doc = obj(doc);
    doc.v = 1;
    doc.size = PAGE_MM[doc.size] ? doc.size : 'A3';
    doc.orient = doc.orient === 'portrait' ? 'portrait' : 'landscape';
    doc.theme = resolveTheme(doc.theme, presets);
    doc.info = { plotUnit: 'm²', builtUnit: 'm²', ...obj(doc.info) };
    doc.partner = doc.partner || null;
    doc.watermark = { on: false, text: 'Client review copy', ...obj(doc.watermark) };
    doc.lang = LANG_AT[doc.lang] ? doc.lang : 'en';
    // the revisions issued, as the history page prints them; `snap` is the saved copy of the presentation at that revision
    doc.revisions = arr(doc.revisions).map((r) => obj(r)).filter((r) => r.id);
    doc.pages = arr(doc.pages).map((p) => {
        p = obj(p);
        const fresh = newPage(LAYOUTS[p.layout] ? p.layout : 'blank');
        const page = { ...fresh, ...p, layout: fresh.layout, id: p.id || fresh.id };
        page.images = arr(p.images).length || !LAYOUTS[page.layout].images ? arr(p.images).map((s) => ({ ...slot(), ...obj(s) })) : fresh.images;
        for (const k of ['drawing', 'opts', 'ov']) page[k] = obj(p[k]);
        page.free = arr(p.free).map((f) => obj(f)).filter((f) => f.id && f.t);
        return page;
    });
    return doc;
}

/**
 * The pages a new presentation opens with: a cover, an overview and a closing page —
 * and, when it was started from a project on the website (doc.seed), that project's
 * own description and photographs: the cover's picture, two beside the overview, the rest a page each.
 */
export function starterPages(doc) {
    const seed = obj(doc.seed), pics = arr(seed.images).filter((h) => typeof h === 'string');
    const pic = (hash, fit = 'fill') => ({ ...slot(fit), asset: hash });
    const cover = newPage('cover', { opts: { variant: doc.theme.cover } });
    if (seed.cover) cover.images = [pic(seed.cover)];
    const rest = pics.filter((h) => h !== seed.cover);
    const overview = newPage('overview', { title: word(doc, 'overview'), body: typeof seed.body === 'string' ? seed.body : '' });
    if (rest.length) overview.images = rest.slice(0, 2).map((h) => pic(h));
    return [cover, overview, ...rest.slice(2).map((h) => newPage('image-full', { images: [pic(h)] })), newPage('closing')];
}

// ── Page size ──
export function pageSize(doc, page) {
    if (page && page.size && page.size.w > 0 && page.size.h > 0) return { w: page.size.w, h: page.size.h, own: true };
    const [a, b] = PAGE_MM[doc.size];
    const orient = (page && page.orient) || doc.orient;
    return orient === 'landscape' ? { w: b * MM, h: a * MM } : { w: a * MM, h: b * MM };
}
export const mm = (pt) => Math.round(pt / MM);

/** "A1" when a sheet (in mm, either way round) is a standard size, else null. */
export function isoName(wMm, hMm) {
    const [s, l] = wMm < hMm ? [wMm, hMm] : [hMm, wMm];
    return Object.keys(ISO_MM).find((k) => Math.abs(ISO_MM[k][0] - s) <= 2 && Math.abs(ISO_MM[k][1] - l) <= 2) || null;
}

// ── Sections and numbering ──
export const isDivider = (p) => p.layout === 'divider';
/** For every page: its number, the section it sits in, and (a divider) its own number. */
export function structure(doc) {
    let section = '', n = 0;
    return doc.pages.map((p, i) => {
        if (isDivider(p)) { section = p.title || ''; n++; }
        return { no: i + 1, section, dividerNo: isDivider(p) ? n : 0 };
    });
}

// ── Linked text ──
// the month names used in the Kurdistan Region and Iraq
const MONTHS = {
    ku: ['کانوونی دووەم', 'شوبات', 'ئازار', 'نیسان', 'ئایار', 'حوزەیران', 'تەممووز', 'ئاب', 'ئەیلوول', 'تشرینی یەکەم', 'تشرینی دووەم', 'کانوونی یەکەم'],
    ar: ['كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'],
};
const eastern = (n) => String(n).replace(/\d/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d]);
/** "2026-11-12" → "12 November 2026" — or, in Kurdish or Arabic, its month and digits (which also set the line right to left). */
export function fmtDate(v, lang = 'en') {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
    if (!m) return v || '';
    if (MONTHS[lang]) return `${eastern(+m[3])} ${MONTHS[lang][+m[2] - 1]} ${eastern(m[1])}`;
    return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
        .format(new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])));
}
export function fmtArea(value, unit) {
    const v = String(value ?? '').trim();
    if (!v) return '';
    const n = Number(v.replace(/,/g, ''));
    return `${Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 2 }) : v} ${unit || 'm²'}`;
}
export function tokens(doc, brand, extra = {}) {
    const i = doc.info;
    return {
        project: i.project, docTitle: i.docTitle, client: i.client, location: i.location, type: i.type, stage: i.stage,
        date: fmtDate(i.date, doc.lang), ref: i.ref, revision: i.revision, team: i.team, issuedFor: i.issuedFor, confidential: i.confidential,
        plotArea: fmtArea(i.plotArea, i.plotUnit), builtArea: fmtArea(i.builtArea, i.builtUnit),
        studio: brand.studio, website: brand.website, phone: brand.phone, email: brand.email, address: brand.address,
        ...extra,
    };
}
/** "{project} · {stage}" with the values in; a separator left dangling by an empty value is dropped. */
export function fillTokens(text, t) {
    const s = String(text ?? '');
    if (!s.includes('{')) return s;
    return s.replace(/\{(\w+)\}/g, (m, k) => (k in t ? String(t[k] ?? '') : m))
        .split('\n').map((line) => line.replace(/^[\s·|/–—-]+|[\s·|/–—-]+$/g, '').replace(/([·|/–—])\s*(?=[·|/–—])/g, '')).join('\n');
}

// ── Drawings ──
/** 100 from "1:100" (or "1/100", "100"); null for anything that is not a scale. */
export function parseScale(v) {
    const m = /^\s*(?:1\s*[:/]\s*)?(\d+(?:\.\d+)?)\s*$/.exec(String(v ?? ''));
    return m && +m[1] > 0 ? +m[1] : null;
}
/**
 * What may be printed as the scale of a PDF drawing placed at `k` times its
 * original size: the scale itself at full size, "1:100 @ A1" when reduced
 * from a standard sheet — and nothing when neither is true. Never a guess.
 */
export function scaleLabel(scale, k, sheetMm) {
    const n = parseScale(scale);
    if (!n || !k || !sheetMm) return null;
    if (Math.abs(k - 1) < 0.005) return `1:${n}`;
    const iso = isoName(sheetMm[0], sheetMm[1]);
    return iso ? `1:${n} @ ${iso}` : null;
}

/** An asset's size as it is seen: pixels for an image (turned as its EXIF says), points for a PDF page. */
export function sourceSize(asset, page = 0) {
    if (!asset) return null;
    if (asset.kind === 'pdf') {
        const p = (asset.meta.pages || [])[Math.max(0, page - 1)];
        return p && p.w > 0 ? { w: p.w, h: p.h } : null;
    }
    const { w, h, orientation = 1 } = asset.meta;
    if (!w || !h) return null;
    return orientation >= 5 ? { w: h, h: w } : { w, h };
}

// ── Export ──
export const PRESETS = {
    print: { name: 'Print — maximum quality', ppi: 0, q: 0, note: 'Drawings stay vector and every image goes in exactly as uploaded. The largest file.' },
    balanced: { name: 'Balanced — client sharing', ppi: 200, q: 0.86, note: 'Drawings stay vector; images larger than 200 PPI at their printed size are resampled once from the original. Looks the same on screen and on an office printer.' },
    small: { name: 'Small file', ppi: 110, q: 0.7, note: 'Drawings stay vector; images are reduced to about 110 PPI with stronger compression. For e-mail and phones — visibly softer in print.' },
};
/** Below this, an image looks soft on paper. */
export const LOW_PPI = 150;

/** "1-3, 7" → [0, 1, 2, 6] (zero-based, in order, within 1…count); null when it cannot be read. */
export function parseRange(text, count) {
    const out = new Set();
    for (const part of String(text).split(',').map((s) => s.trim()).filter(Boolean)) {
        const m = /^(\d+)(?:\s*[-–]\s*(\d+))?$/.exec(part);
        if (!m) return null;
        const a = +m[1], b = m[2] ? +m[2] : a;
        if (a < 1 || b < a || b > count) return null;
        for (let i = a; i <= b; i++) out.add(i - 1);
    }
    return out.size ? [...out].sort((x, y) => x - y) : null;
}

export function fileName(doc, fallback) {
    const i = doc.info;
    const parts = [i.project || fallback, i.stage, i.date, i.revision ? `Rev ${i.revision}` : ''];
    return parts.map((s) => String(s || '').trim()).filter(Boolean).join(' - ').replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140) || 'Presentation';
}

/** The revision after this one: B after A, 3 after 2, A when there is none — empty when it cannot be told. */
export function nextRevision(current) {
    const s = String(current ?? '').trim();
    if (!s) return 'A';
    if (/^\d+$/.test(s)) return String(+s + 1);
    if (/^[A-Ya-y]$/.test(s)) return String.fromCharCode(s.charCodeAt(0) + 1);
    return '';
}

export const bytes = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(n >= 10485760 ? 0 : 1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
