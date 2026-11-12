// Text for the page: the embedded fonts loaded into the browser, measured on a
// canvas, broken into lines and into runs of one direction. The preview and the
// PDF both draw exactly the runs made here, so a line never breaks differently
// between the two.
import { RTL_FONT } from '@pm/model';

let fontsBase = '/fonts/presentations';
const loaded = new Map();     // font key → promise
const ready = new Set();      // font keys usable on the canvas
const widths = new Map();     // "key|text" → width at 100px
const metric = new Map();     // key → { asc, desc } per unit of size
let ctx = null;
let onLoad = () => {};

export function initText(base, whenLoaded) {
    fontsBase = base;
    onLoad = whenLoaded || onLoad;
    if (typeof document !== 'undefined') ctx = document.createElement('canvas').getContext('2d');
}
export const fontUrl = (key) => `${fontsBase}/${key}.ttf`;
export const cssFamily = (key) => `"pm-${key}"`;

/** Load fonts for the canvas and the page; resolves when all are usable. */
export function ensureFonts(keys) {
    return Promise.all([...new Set([...keys, RTL_FONT])].map((key) => {
        if (!loaded.has(key)) {
            const face = new FontFace(`pm-${key}`, `url(${fontUrl(key)})`);
            loaded.set(key, face.load().then((f) => { document.fonts.add(f); ready.add(key); })
                .catch(() => { loaded.delete(key); throw new Error(`The font ${key} could not be loaded.`); }));
        }
        return loaded.get(key);
    }));
}
/** A font asked for while laying out but not loaded yet: fetched, and the page drawn again when it arrives. */
function want(key) {
    if (ready.has(key) || loaded.has(key) || typeof FontFace === 'undefined') return;
    ensureFonts([key]).then(() => onLoad(), () => {});
}

function measure(key, text) {
    if (!ctx) return text.length * 50;   // no canvas (tests): a flat estimate
    const id = `${key}|${text}`;
    let w = widths.get(id);
    if (w === undefined) {
        ctx.font = `100px ${cssFamily(key)}`;
        w = ctx.measureText(text).width;
        if (ready.has(key)) widths.set(id, w); else want(key);
    }
    return w;
}
/** Ascent and descent of a font as the browser lays lines out, per unit of size. */
export function metrics(key) {
    let m = metric.get(key);
    if (!m) {
        if (!ctx) return { asc: 0.9, desc: 0.25 };
        ctx.font = `100px ${cssFamily(key)}`;
        const t = ctx.measureText('Hg');
        m = { asc: t.fontBoundingBoxAscent / 100, desc: t.fontBoundingBoxDescent / 100 };
        if (ready.has(key)) metric.set(key, m); else want(key);
    }
    return m;
}
/** From the top of a line box to the baseline, for a font at a size in a line of a height. */
export function baseline(key, size, lineHeight) {
    const m = metrics(key);
    return (lineHeight - (m.asc + m.desc) * size) / 2 + m.asc * size;
}

// ── Direction ──
const R = /[֐-ࣿיִ-﷿ﹰ-ﻼ]/;
const AN = /[٠-٩۰-۹]/;   // Arabic-Indic digits read left to right
const LN = /[\p{L}\p{N}]/u;
const cls = (ch) => (AN.test(ch) ? 'A' : R.test(ch) ? 'R' : LN.test(ch) ? 'L' : 'N');
export const isRtl = (text) => { for (const ch of text) { const c = cls(ch); if (c !== 'N') return c !== 'L'; } return false; };

/**
 * A line as runs of one direction and one font, in the order they are read:
 * Kurdish/Arabic letters (right to left, Speda), their digits (left to right,
 * Speda), everything else (left to right, the style's font). A simplified
 * bidi — enough for names, titles and notes; not for nested quotations.
 */
export function runs(text, key) {
    const chars = [...text];
    const kinds = chars.map(cls);
    const baseCls = isRtl(text) ? 'R' : 'L';
    // a neutral (space, punctuation) joins what surrounds it, else the paragraph
    for (let i = 0; i < kinds.length; i++) {
        if (kinds[i] !== 'N') continue;
        let j = i;
        while (j < kinds.length && kinds[j] === 'N') j++;
        const side = (c) => (c === 'A' ? 'R' : c);   // their digits sit on the right-to-left side
        const before = side(i > 0 ? kinds[i - 1] : baseCls), after = side(j < kinds.length ? kinds[j] : baseCls);
        kinds.fill(before === after ? before : baseCls, i, j);
        i = j - 1;
    }
    const out = [];
    chars.forEach((ch, i) => {
        const last = out[out.length - 1];
        if (last && last.cls === kinds[i]) last.text += ch;
        else out.push({ cls: kinds[i], text: ch });
    });
    return out.map((r) => ({ text: r.text, key: r.cls === 'L' ? key : RTL_FONT, rtl: r.cls === 'R' }));
}

/**
 * Lay text out in a width: lines, each with its runs placed from the line's
 * left edge (x) in the order they sit on the page.
 * style: { key, size, lh, ls } — ls is letter-spacing in em.
 */
export function layoutText(text, style, maxWidth) {
    const { key, size } = style;
    const gap = (style.ls || 0) * size;
    const lineOf = (str) => {
        // joined scripts are never letter-spaced: it would pull the letters apart
        const rs = runs(str, key).map((r) => {
            const ls = r.key === RTL_FONT ? 0 : gap;
            return { ...r, ls, w: measure(r.key, r.text) * size / 100 + ls * [...r.text].length };
        });
        // the spacing after the last letter is not part of the line's width
        return { rs, w: rs.reduce((a, r) => a + r.w, 0) - (rs.length ? rs[rs.length - 1].ls : 0) };
    };
    const lines = [];
    for (const para of String(text).split('\n')) {
        const words = para.split(/(?<= )/);   // each word keeps its trailing space
        let cur = '';
        for (const word of words) {
            if (cur && lineOf((cur + word).trimEnd()).w > maxWidth) { lines.push(cur.trimEnd()); cur = word; } else cur += word;
        }
        lines.push(cur.trimEnd());
    }
    let widest = 0;
    const out = lines.map((str) => {
        const rtl = isRtl(str);
        const { rs, w } = lineOf(str);
        let x = 0;
        const placed = (rtl ? rs.reverse() : rs).map((r) => { const run = { ...r, x }; x += r.w; return run; });
        widest = Math.max(widest, w);
        return { runs: placed, w, rtl };
    });
    return { lines: out, w: widest, h: out.length * size * style.lh };
}
