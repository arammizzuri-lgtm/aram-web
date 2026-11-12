// Getting files in: the original goes to the server in pieces (so the host's
// upload limit does not matter) and is kept untouched; the light preview and
// thumbnail the editor shows are made here in the browser and sent after it.

const PREVIEW = 2000, THUMB = 420, PDF_PREVIEW = 2600;

export function createUploader(boot) {
    const headers = { 'X-CSRF-TOKEN': boot.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    async function send(url, body, tries = 3) {
        for (let attempt = 1; ; attempt++) {
            let res;
            try {
                res = await fetch(url, { method: 'POST', headers, body, credentials: 'same-origin' });
            } catch (e) {
                if (attempt >= tries) throw new Error('The connection dropped while uploading. Check the internet and try again.');
                await new Promise((r) => setTimeout(r, 800 * attempt));
                continue;
            }
            if (res.ok) return res.json();
            if (res.status >= 500 && attempt < tries) { await new Promise((r) => setTimeout(r, 800 * attempt)); continue; }
            const data = await res.json().catch(() => ({}));
            if (res.status === 401 || res.status === 419) throw new Error('You have been signed out. Sign in to the admin in another tab, then try again.');
            throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || `The upload was refused (${res.status}).`);
        }
    }

    /** Send a file, whole and unchanged; resolves with the asset the server recorded (or, to another `url`, its answer). */
    async function upload(file, presentation, onProgress = () => {}, url = boot.urls.chunk, fields = {}) {
        if (!/\.(jpe?g|png|pdf)$/i.test(file.name) && !/^(image\/(jpeg|png)|application\/pdf)$/.test(file.type)) {
            throw new Error('Only JPG and PNG images and PDF files can be used.');
        }
        if (file.size > boot.maxBytes) throw new Error(`This file is larger than ${Math.round(boot.maxBytes / 1048576)} MB.`);
        if (!file.size) throw new Error('This file is empty.');
        const id = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
        for (let offset = 0; offset < file.size; offset += boot.chunkBytes) {
            const end = Math.min(file.size, offset + boot.chunkBytes);
            const form = new FormData();
            form.set('upload', id);
            form.set('offset', offset);
            form.set('last', end >= file.size ? 1 : 0);
            form.set('name', file.name.slice(0, 200));
            if (presentation) form.set('presentation', presentation);
            for (const [k, v] of Object.entries(fields)) form.set(k, v);
            form.set('chunk', file.slice(offset, end), 'chunk');
            const data = await send(url, form);
            onProgress(end / file.size);
            if (end >= file.size) return data.asset || data;
        }
        throw new Error('The upload did not finish.');
    }

    const toBlob = (canvas, type, q) => new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('The preview could not be made.'))), type, q));
    function scaled(source, sw, sh, max, bg) {
        const s = Math.min(1, max / Math.max(sw, sh));
        const c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(sw * s)); c.height = Math.max(1, Math.round(sh * s));
        const g = c.getContext('2d');
        if (bg) { g.fillStyle = bg; g.fillRect(0, 0, c.width, c.height); }
        g.imageSmoothingQuality = 'high';
        g.drawImage(source, 0, 0, c.width, c.height);
        return c;
    }
    async function sendPreviews(asset, page, big, alpha, meta) {
        // a PNG may be see-through (a logo), so its preview keeps that; a photograph is a JPEG
        const type = alpha ? 'image/webp' : 'image/jpeg';
        const form = new FormData();
        form.set('page', page);
        form.set('preview', await toBlob(big, type, alpha ? 0.92 : 0.88), 'preview');
        form.set('thumb', await toBlob(scaled(big, big.width, big.height, THUMB), type, 0.82), 'thumb');
        if (meta) form.set('meta', JSON.stringify(meta));
        return (await send(boot.urls.previews.replace('__ID__', asset.id), form)).asset;
    }

    /** The turn a camera recorded for a JPEG (EXIF orientation, 1–8); 1 when there is none. */
    async function orientation(file) {
        try {
            const v = new DataView(await file.slice(0, 131072).arrayBuffer());
            if (v.getUint16(0) !== 0xFFD8) return 1;
            for (let o = 2; o + 12 < v.byteLength;) {
                const marker = v.getUint16(o), size = v.getUint16(o + 2);
                if (marker === 0xFFE1 && v.getUint32(o + 4) === 0x45786966) {
                    const t = o + 10, le = v.getUint16(t) === 0x4949, ifd = t + v.getUint32(t + 4, le);
                    for (let i = 0, n = v.getUint16(ifd, le); i < n; i++) {
                        if (v.getUint16(ifd + 2 + i * 12, le) === 0x0112) return v.getUint16(ifd + 10 + i * 12, le) || 1;
                    }
                    return 1;
                }
                if ((marker & 0xFF00) !== 0xFF00) break;
                o += 2 + size;
            }
        } catch (e) { /* not a readable JPEG header: no turn */ }
        return 1;
    }

    /** The preview and thumbnail of an image just uploaded. */
    async function imagePreviews(file, asset) {
        const turn = await orientation(file);
        const { w, h } = asset.meta;
        const [ow, oh] = turn >= 5 ? [h, w] : [w, h];
        const s = Math.min(1, PREVIEW / Math.max(ow, oh));
        // decoded straight to the preview's size: a 100-megapixel render need not fit in memory whole
        const bmp = await createImageBitmap(file, { imageOrientation: 'from-image', resizeWidth: Math.round(ow * s), resizeHeight: Math.round(oh * s), resizeQuality: 'high' })
            .catch(() => { throw new Error('This image could not be opened to make its preview — it may be damaged.'); });
        return sendPreviews(asset, 0, scaled(bmp, bmp.width, bmp.height, PREVIEW), /png$/i.test(asset.ext), turn > 1 ? { orientation: turn } : null);
    }

    let pdfjs = null;
    /** Open a PDF (a File, or bytes) to read its pages. */
    async function openPdf(source) {
        if (!pdfjs) {
            pdfjs = await import('pdfjs');
            pdfjs.GlobalWorkerOptions.workerSrc = boot.urls.pdfWorker;
        }
        const data = source instanceof ArrayBuffer ? source : await source.arrayBuffer();
        try {
            const doc = await pdfjs.getDocument({ data: data.slice(0) }).promise;
            const pages = [];
            for (let n = 1; n <= doc.numPages; n++) {
                const vp = (await doc.getPage(n)).getViewport({ scale: 1 });
                pages.push({ w: +vp.width.toFixed(2), h: +vp.height.toFixed(2) });
            }
            return { doc, pages };
        } catch (e) {
            if (e && e.name === 'PasswordException') throw new Error('This PDF is password-protected. Save a copy without the password and upload that.');
            throw new Error('This PDF could not be read — it may be damaged.');
        }
    }
    /** Can the export place this PDF's pages? Asked at upload, not discovered at export. */
    async function checkPdf(source) {
        const { PDFDocument } = await import('pdf-lib');
        const data = source instanceof ArrayBuffer ? source : await source.arrayBuffer();
        try {
            await PDFDocument.load(data, { updateMetadata: false });
        } catch (e) {
            throw new Error(/encrypt/i.test(String(e && e.message)) ? 'This PDF is protected against changes. Save an unprotected copy and upload that.' : 'This PDF opens for viewing but its pages cannot be placed in another PDF. Re-save it from your PDF reader and upload that.');
        }
    }
    /** One page drawn on a canvas, its longest side `max` pixels (and at most `cap` times its size in points). */
    async function renderPage(pdf, n, max, cap = 4) {
        const page = await pdf.doc.getPage(n);
        const base = page.getViewport({ scale: 1 });
        const vp = page.getViewport({ scale: Math.min(cap, max / Math.max(base.width, base.height)) });
        const c = document.createElement('canvas');
        c.width = Math.round(vp.width); c.height = Math.round(vp.height);
        const g = c.getContext('2d');
        g.fillStyle = '#ffffff'; g.fillRect(0, 0, c.width, c.height);
        await page.render({ canvasContext: g, viewport: vp }).promise;
        return c;
    }
    /** Small pictures of every page, for choosing which to insert. */
    async function pdfThumbs(pdf, onEach) {
        for (let n = 1; n <= pdf.pages.length; n++) onEach(n, (await renderPage(pdf, n, 300)).toDataURL('image/jpeg', 0.8));
    }
    /** Previews of the chosen pages, stored beside the PDF. */
    async function pdfPreviews(pdf, asset, pageNumbers, onProgress = () => {}) {
        let latest = asset, done = 0;
        for (const n of pageNumbers) {
            if (!(latest.meta.previews || []).includes(n)) {
                latest = await sendPreviews(latest, n, await renderPage(pdf, n, PDF_PREVIEW), false, { pages: pdf.pages });
            }
            onProgress(++done / pageNumbers.length);
        }
        return latest;
    }

    return { upload, imagePreviews, openPdf, checkPdf, pdfThumbs, pdfPreviews, renderPage, send, headers };
}

/** A guess at what an uploaded file shows, from its name — only to pre-select the section; always editable. */
export function guessSection(name, kind) {
    const n = name.toLowerCase();
    const rules = [
        [/site\s*plan|siteplan|master\s*plan/, 'Site Plan'], [/site|context|analysis/, 'Site Analysis'], [/concept|diagram|sketch/, 'Concept Design'],
        [/elev/, 'Elevations'], [/section/, 'Sections'], [/plan|floor|basement|roof/, 'Floor Plans'],
        [/material|finish|mood/, 'Materials & Finishes'], [/landscape|garden/, 'Landscape Design'],
        [/interior|int[\s_-]|living|kitchen|bed|bath|majlis|lobby/, 'Interior Visualizations'],
        [/exterior|ext[\s_-]|facade|render|view|aerial|night/, 'Exterior Visualizations'],
    ];
    const hit = rules.find(([re]) => re.test(n));
    return hit ? hit[1] : kind === 'pdf' ? 'Floor Plans' : 'Exterior Visualizations';
}

/** "ground-floor_plan.pdf" → "Ground floor plan": a draft title taken from the file's own name. */
export function titleFromName(name) {
    const s = name.replace(/\.[a-z0-9]+$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
    return s ? s[0].toUpperCase() + s.slice(1) : '';
}
