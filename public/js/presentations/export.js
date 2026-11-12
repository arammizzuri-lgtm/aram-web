// The PDF, built in the browser from the original files — never from pictures
// of the editor. Each page is laid out by the same engine as the preview and
// its list of items is drawn with pdf-lib:
//   · a PDF drawing is placed as it is, still vector;
//   · an image goes in as uploaded (Print), or resampled once from the original (Balanced, Small);
//   · text is real text in the embedded, subsetted fonts.
import { PRESETS } from '@pm/model';
import { layoutPage, northShape } from '@pm/layout';
import { baseline, fontUrl } from '@pm/text';

const hex = (c) => { const n = parseInt(c.slice(1, 7), 16); return [(n >> 16) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255]; };

/**
 * { doc, indices, env, media, preset, title, onProgress({ done, total, label }) } → { bytes, pages }
 * env is the layout's (brand, assets, logoRatio); `indices` are the pages to export, in order.
 */
export async function exportPdf({ doc, indices, env, media, preset, title, onProgress = () => {} }) {
    const [lib, { default: fontkit }] = await Promise.all([import('pdf-lib'), import('fontkit')]);
    const { PDFDocument, PDFOperator, PDFHexString, PDFNumber, rgb, pushGraphicsState, popGraphicsState, beginText, endText,
        setFontAndSize, setTextMatrix, setFillingColor, setGraphicsState, concatTransformationMatrix, drawObject, rectangle, clip, endPath } = lib;
    const P = PRESETS[preset];
    const lays = indices.map((i) => layoutPage(doc, i, { ...env, edit: false }));

    // What each image is asked for, at its largest use: pixels across, for the preset's resolution.
    const need = new Map(), drawings = new Set(), sources = new Set();
    for (const lay of lays) for (const it of lay.items) {
        if (it.t !== 'image') continue;
        sources.add(it.kind === 'pdf' ? `${it.hash}|pdf` : it.hash);
        if (it.kind !== 'image') continue;
        // a drawing saved as an image keeps half as much again: thin lines show softness first
        need.set(it.hash, Math.max(need.get(it.hash) || 0, it.dw / 72 * P.ppi * (it.drawing ? 1.5 : 1)));
        if (it.drawing) drawings.add(it.hash);
    }
    let done = 0;
    const total = sources.size + lays.length + 1;
    const step = (label) => onProgress({ done: Math.min(++done, total), total, label });

    const pdf = await PDFDocument.create();
    pdf.registerFontkit(fontkit);
    pdf.setTitle(title);
    pdf.setAuthor(env.brand.studio || '');
    pdf.setCreator('Aram Mizuri — Project presentations');

    async function get(url, what) {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`${what} could not be read from the server (${res.status === 401 || res.status === 419 ? 'you have been signed out' : res.status}).`);
        return res;
    }
    const nameOf = (hash) => `“${(env.assets.get(hash) || {}).name || 'A file'}”`;

    const fonts = new Map();
    async function font(key) {
        if (!fonts.has(key)) fonts.set(key, get(fontUrl(key), `The font ${key}`).then((r) => r.arrayBuffer()).then((b) => pdf.embedFont(b, { subset: true })));
        return fonts.get(key);
    }

    const images = new Map();
    async function image(it) {
        if (images.has(it.hash)) return images.get(it.hash);
        const job = (async () => {
            const a = env.assets.get(it.hash);
            const blob = await (await get(media.url(it.hash, 'original'), nameOf(it.hash))).blob();
            const want = Math.ceil(need.get(it.hash) || 0);
            let out;
            if (P.ppi && it.src.w > want * 1.15) {   // larger than the preset needs: resampled once, from the original
                const w = Math.max(1, want), h = Math.max(1, Math.round(want * it.src.h / it.src.w));
                const bmp = await createImageBitmap(blob, { imageOrientation: 'from-image', resizeWidth: w, resizeHeight: h, resizeQuality: 'high' });
                const c = document.createElement('canvas');
                c.width = w; c.height = h;
                c.getContext('2d').drawImage(bmp, 0, 0);
                // a line drawing, or anything see-through, stays lossless; a photograph is a JPEG
                const png = a.ext === 'png' && (drawings.has(it.hash) || seeThrough(c));
                const made = await new Promise((r) => c.toBlob(r, png ? 'image/png' : 'image/jpeg', P.q));
                const data = new Uint8Array(await made.arrayBuffer());
                out = { img: png ? await pdf.embedPng(data) : await pdf.embedJpg(data), turn: 1 };
            } else {   // exactly as uploaded — a JPEG is not even re-encoded
                const data = new Uint8Array(await blob.arrayBuffer());
                out = { img: a.ext === 'png' ? await pdf.embedPng(data) : await pdf.embedJpg(data), turn: a.meta.orientation || 1 };
            }
            step(a.name);
            return out;
        })();
        images.set(it.hash, job);
        return job;
    }
    function seeThrough(canvas) {
        const c = document.createElement('canvas');
        c.width = c.height = 64;
        const g = c.getContext('2d', { willReadFrequently: true });
        g.drawImage(canvas, 0, 0, 64, 64);
        const d = g.getImageData(0, 0, 64, 64).data;
        for (let i = 3; i < d.length; i += 4) if (d[i] < 250) return true;
        return false;
    }

    const pdfs = new Map(), sheets = new Map();
    async function sheet(it) {
        const id = `${it.hash}|${it.pdfPage}`;
        if (!sheets.has(id)) sheets.set(id, (async () => {
            if (!pdfs.has(it.hash)) {
                pdfs.set(it.hash, get(media.url(it.hash, 'original'), nameOf(it.hash)).then((r) => r.arrayBuffer())
                    .then((b) => PDFDocument.load(b, { updateMetadata: false }))
                    .then((d) => { step((env.assets.get(it.hash) || {}).name); return d; }, () => { throw new Error(`${nameOf(it.hash)} could not be placed: its pages cannot be read into another PDF.`); }));
            }
            const src = (await pdfs.get(it.hash)).getPage(it.pdfPage - 1);
            const box = src.getCropBox();
            const emb = await pdf.embedPage(src, { left: box.x, bottom: box.y, right: box.x + box.width, top: box.y + box.height });
            // pdf-lib leaves a sheet's own rotation out, so it is applied when the sheet is placed
            const turn = { 90: 6, 180: 3, 270: 8 }[((src.getRotation().angle % 360) + 360) % 360] || 1;
            return { ref: emb.ref, w0: box.width, h0: box.height, turn };
        })());
        return sheets.get(id);
    }

    const logos = new Map();
    async function logo(it) {
        const id = `${it.hash}|${it.color}`;
        if (!logos.has(id)) logos.set(id, (async () => {
            if (it.color) return pdf.embedPng(await media.logoPng(it.hash, it.color));
            const a = env.assets.get(it.hash);
            const data = await (await get(media.url(it.hash, 'original'), nameOf(it.hash))).arrayBuffer();
            return a.ext === 'png' ? pdf.embedPng(data) : pdf.embedJpg(data);
        })());
        return logos.get(id);
    }

    /** An image (a unit square) or a sheet (w0 × h0) into its place, turned as it must be, cropped to its frame. */
    function place(page, name, it, turn, w0 = 1, h0 = 1) {
        const H = page.getHeight(), x = it.dx, y = H - it.dy - it.dh, w = it.dw, h = it.dh;
        const m = {
            1: [w, 0, 0, h, x, y], 2: [-w, 0, 0, h, x + w, y], 3: [-w, 0, 0, -h, x + w, y + h], 4: [w, 0, 0, -h, x, y + h],
            5: [0, -h, -w, 0, x + w, y + h], 6: [0, -h, w, 0, x, y + h], 7: [0, h, w, 0, x, y], 8: [0, h, -w, 0, x + w, y],
        }[turn];
        page.pushOperators(pushGraphicsState(),
            rectangle(it.x, H - it.y - it.h, it.w, it.h), clip(), endPath(),
            concatTransformationMatrix(m[0] / w0, m[1] / w0, m[2] / h0, m[3] / h0, m[4], m[5]),
            drawObject(name), popGraphicsState());
    }

    /** One run of text, as the glyphs fontkit shaped (joined, kerned, right-to-left where it is) at a text matrix. */
    function run(page, f, r, matrix, size, color, opacity) {
        const emb = f.embedder, fk = emb.font, upm = fk.unitsPerEm;
        f.encodeText(r.text);   // enters the glyphs in the subset
        const shaped = fk.layout(r.text, undefined, undefined, undefined, r.rtl ? 'rtl' : 'ltr');
        const arr = pdf.context.obj([]);
        shaped.glyphs.forEach((g, i) => {
            let id = emb.glyphIdMap.get(g.id);
            if (id === undefined) {   // a glyph only this direction produces
                id = emb.subset.includeGlyph(g); emb.glyphs[id - 1] = g; emb.glyphIdMap.set(g.id, id); emb.glyphCache.invalidate();
            }
            arr.push(PDFHexString.of(id.toString(16).padStart(4, '0')));
            const adjust = (g.advanceWidth - shaped.positions[i].xAdvance) * 1000 / upm - (r.ls || 0) * 1000 / size;
            if (Math.abs(adjust) > 0.01) arr.push(PDFNumber.of(+adjust.toFixed(2)));
        });
        page.setFont(f);
        const ops = [pushGraphicsState()];
        if (opacity != null && opacity < 1) ops.push(setGraphicsState(page.node.newExtGState('GS', pdf.context.obj({ Type: 'ExtGState', ca: opacity, CA: opacity }))));
        ops.push(beginText(), setFillingColor(rgb(...hex(color))), setFontAndSize(page.fontKey, size), setTextMatrix(...matrix),
            PDFOperator.of('TJ', [arr]), endText(), popGraphicsState());
        page.pushOperators(...ops);
    }

    for (const lay of lays) {
        const page = pdf.addPage([lay.w, lay.h]);
        const H = lay.h;
        page.drawRectangle({ x: 0, y: 0, width: lay.w, height: H, color: rgb(...hex(lay.bg)) });
        for (const it of lay.items) {
            if (it.t === 'rect') {
                page.drawRectangle({ x: it.x, y: H - it.y - it.h, width: it.w, height: it.h, color: rgb(...hex(it.fill)), opacity: it.opacity ?? 1 });
            } else if (it.t === 'line') {
                page.drawLine({ start: { x: it.x1, y: H - it.y1 }, end: { x: it.x2, y: H - it.y2 }, thickness: it.lw, color: rgb(...hex(it.color)) });
            } else if (it.t === 'text') {
                for (const line of it.lines) {
                    const y = H - (line.top + baseline(it.fontKey, it.size, it.lh));
                    for (const r of line.runs) run(page, await font(r.key), r, [1, 0, 0, 1, r.x, y], it.size, it.color, it.opacity);
                }
            } else if (it.t === 'image' && it.kind === 'image') {
                const { img, turn } = await image(it);
                place(page, page.node.newXObject('Image', img.ref), it, turn);
            } else if (it.t === 'image') {
                const s = await sheet(it);
                place(page, page.node.newXObject('Sheet', s.ref), it, s.turn, s.w0, s.h0);
            } else if (it.t === 'logo') {
                page.drawImage(await logo(it), { x: it.x, y: H - it.y - it.h, width: it.w, height: it.h });
            } else if (it.t === 'north') {
                const s = northShape(it.angle), o = { x: it.x, y: H - it.y, scale: it.size / 100 }, c = rgb(...hex(it.color));
                page.drawSvgPath(s.ring, { ...o, borderColor: c, borderWidth: 1.6 });
                page.drawSvgPath(s.arrow, { ...o, color: c });
                page.drawSvgPath(s.n, { ...o, borderColor: c, borderWidth: 2.4 });
            } else if (it.t === 'wm') {
                const a = it.angle * Math.PI / 180, cos = Math.cos(a), sin = Math.sin(a);
                for (const r of it.runs) {
                    const lx = r.x - it.w / 2, ly = it.size / 2 - baseline(it.fontKey, it.size, it.size);
                    run(page, await font(r.key), r, [cos, sin, -sin, cos, it.cx + lx * cos - ly * sin, H - it.cy + lx * sin + ly * cos], it.size, it.color, it.opacity);
                }
            }
        }
        step(`Page ${lays.indexOf(lay) + 1} of ${lays.length}`);
    }

    onProgress({ done: total - 1, total, label: 'Writing the file' });
    const bytes = await pdf.save({ useObjectStreams: true });
    onProgress({ done: total, total, label: 'Done' });
    return { bytes, pages: lays.length };
}

/** Hand a finished PDF to the browser as a download. */
export function download(bytes, name) {
    downloadBlob(new Blob([bytes], { type: 'application/pdf' }), name.endsWith('.pdf') ? name : `${name}.pdf`);
}
export function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

/** Image sizes offered for JPG pages: the longest side in pixels, or 300 PPI at the page's printed size. */
export const JPG_SIZES = [['2400', '2400 px — screens, WhatsApp'], ['4000', '4000 px — large screens'], ['300ppi', '300 PPI — for print']];
const MAX_PX = 8000;

/**
 * The pages as JPG pictures: drawn once as a print-quality PDF (the same pages, exactly), then
 * each page rendered at the chosen size. One page is one .jpg; several come in a .zip.
 */
export async function exportImages({ doc, indices, env, media, title, size, uploader, onProgress = () => {} }) {
    const { bytes } = await exportPdf({ doc, indices, env, media, preset: 'print', title, onProgress: (p) => onProgress({ done: p.done, total: p.total * 2, label: p.done < p.total ? p.label : 'Drawing the pictures' }) });
    const pdf = await uploader.openPdf(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
    const n = pdf.pages.length, base = title.replace(/\.pdf$/i, ''), files = [];
    for (let i = 1; i <= n; i++) {
        onProgress({ done: n + i - 1, total: n * 2, label: `Making picture ${i} of ${n}` });
        const p = pdf.pages[i - 1], long = Math.max(p.w, p.h);
        const px = size === '300ppi' ? Math.min(MAX_PX, long / 72 * 300) : +size;
        const canvas = await uploader.renderPage(pdf, i, px, Infinity);
        const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.92));
        if (!blob) throw new Error('The browser could not make a picture this large. Choose a smaller size.');
        files.push({ name: `${base}${n > 1 ? ` — ${String(indices[i - 1] + 1).padStart(2, '0')}` : ''}.jpg`, data: new Uint8Array(await blob.arrayBuffer()) });
        canvas.width = canvas.height = 0;   // let the memory go before the next page
    }
    onProgress({ done: n * 2, total: n * 2, label: 'Done' });
    return files.length === 1
        ? { blob: new Blob([files[0].data], { type: 'image/jpeg' }), name: files[0].name, pages: 1 }
        : { blob: zipStore(files), name: `${base}.zip`, pages: n };
}

const CRC = new Uint32Array(256).map((_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
export function crc32(data) {
    let c = 0xFFFFFFFF;
    for (let i = 0; i < data.length; i++) c = CRC[(c ^ data[i]) & 255] ^ (c >>> 8);
    return (c ^ 0xFFFFFFFF) >>> 0;
}
/** A .zip that stores the files as they are (JPGs gain nothing from compressing). Names are UTF-8. */
export function zipStore(files) {
    const enc = new TextEncoder(), parts = [], central = [];
    let offset = 0;
    const head = (sig, size) => { const v = new DataView(new ArrayBuffer(size)); v.setUint32(0, sig, true); return v; };
    for (const f of files) {
        const name = enc.encode(f.name), crc = crc32(f.data), len = f.data.length;
        const local = head(0x04034b50, 30);
        local.setUint16(4, 20, true); local.setUint16(6, 0x0800, true); local.setUint16(12, 0x21, true);   // version 2.0; UTF-8 names; dated 1 Jan 1980
        local.setUint32(14, crc, true); local.setUint32(18, len, true); local.setUint32(22, len, true);
        local.setUint16(26, name.length, true);
        const dir = head(0x02014b50, 46);
        dir.setUint16(4, 20, true); dir.setUint16(6, 20, true); dir.setUint16(8, 0x0800, true); dir.setUint16(14, 0x21, true);
        dir.setUint32(16, crc, true); dir.setUint32(20, len, true); dir.setUint32(24, len, true);
        dir.setUint16(28, name.length, true); dir.setUint32(42, offset, true);
        parts.push(local, name, f.data);
        central.push(dir, name);
        offset += 30 + name.length + len;
    }
    const size = central.reduce((s, x) => s + x.byteLength, 0), end = head(0x06054b50, 22);
    end.setUint16(8, files.length, true); end.setUint16(10, files.length, true);
    end.setUint32(12, size, true); end.setUint32(16, offset, true);
    return new Blob([...parts, ...central, end], { type: 'application/zip' });
}
