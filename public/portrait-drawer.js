/* =========================================================
   ARAM MIZURI ARCHITECTURE — portrait-drawer.js
   Turns a client's photo into a pencil portrait made of strokes — the way
   an architect draws a face: light guide lines first, then the outline,
   the features, the hair, and the shading last. Runs in the admin's
   browser (the face finding needs MediaPipe, which a PHP host cannot run);
   the server only receives the strokes, as numbers, and builds the drawing
   from them (App\Support\PortraitDrawing).

   1. MediaPipe finds the face and its landmarks; the photo is cut to the
      head, and its segmenter keeps only face, hair and glasses — the room
      behind and the clothes are left as blank paper.
   2. The lines come from a difference of gaussians (the "XDoG" sketch
      filter): dark where the tone falls away at an edge. They are thinned
      to their centres and traced into strokes.
   3. Shadows on the face and neck are hatched with short slanting strokes,
      cross-hatched where darkest.
   4. Guide lines from the landmarks — the centre line, and the lines of
      the eyes, the base of the nose and the mouth — open the drawing.
   ========================================================= */

const MP = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@1.0.0';
const FACE_MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';
const SEG_MODEL = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_multiclass_256x256/float32/latest/selfie_multiclass_256x256.tflite';

export const SIZE = 512;

// the segmenter's classes
const HAIR = 1, BODY = 2, FACE = 3, OTHER = 5;

// landmark groups of MediaPipe's face mesh, for putting strokes in order
const GROUPS = {
    oval: [10, 338, 297, 332, 284, 251, 389, 356, 454, 323, 361, 288, 397, 365, 379, 378, 400, 377, 152, 148, 176, 149, 150, 136, 172, 58, 132, 93, 234, 127, 162, 21, 54, 103, 67, 109],
    eyes: [33, 7, 163, 144, 145, 153, 154, 155, 133, 246, 161, 160, 159, 158, 157, 173, 263, 249, 390, 373, 374, 380, 381, 382, 362, 466, 388, 387, 386, 385, 384, 398,
        46, 53, 52, 65, 55, 70, 63, 105, 66, 107, 276, 283, 282, 295, 285, 300, 293, 334, 296, 336],
    nose: [1, 2, 98, 327, 4, 5, 195, 197, 6, 168, 45, 275, 220, 440, 115, 344, 49, 279, 64, 294, 48, 278],
    mouth: [61, 146, 91, 181, 84, 17, 314, 405, 321, 375, 291, 185, 40, 39, 37, 0, 267, 269, 270, 409, 78, 95, 88, 178, 87, 14, 317, 402, 318, 324, 308, 191, 80, 81, 82, 13, 312, 311, 310, 415],
};
const ORDER = ['guide', 'oval', 'eyes', 'nose', 'mouth', 'face', 'hair', 'neck', 'hatch'];
// the inner edge of the lips, round the mouth's opening
const INNER_LIPS = [78, 191, 80, 81, 82, 13, 312, 311, 310, 415, 308, 324, 318, 402, 317, 14, 87, 178, 88, 95];

// how much is drawn: [line threshold, shortest stroke, shading below, hatch spacing]
const DETAIL = {
    soft: [0.042, 16, 0.30, 7],
    balanced: [0.03, 12, 0.36, 6],
    strong: [0.021, 9, 0.43, 5],
};

let tasks = null;

/** MediaPipe's face landmarker and segmenter, fetched once (about 20 MB). */
function models() {
    if (!tasks) {
        tasks = (async () => {
            const { FilesetResolver, FaceLandmarker, ImageSegmenter } = await import(MP + '/vision_bundle.mjs');
            const fileset = await FilesetResolver.forVisionTasks(MP + '/wasm');
            const face = await FaceLandmarker.createFromOptions(fileset, {
                baseOptions: { modelAssetPath: FACE_MODEL, delegate: 'CPU' }, runningMode: 'IMAGE', numFaces: 1,
            });
            const seg = await ImageSegmenter.createFromOptions(fileset, {
                baseOptions: { modelAssetPath: SEG_MODEL, delegate: 'CPU' }, runningMode: 'IMAGE',
                outputCategoryMask: true, outputConfidenceMasks: false,
            });
            return { face, seg };
        })().catch(e => { tasks = null; throw e; });
    }
    return tasks;
}

/**
 * Draw the face in $img (a loaded <img>).
 * Returns { size, strokes: [{ k: kind, w: width, o: opacity, p: [x, y, x, y, …] }] }
 * in drawing order, or throws an Error with a message fit to show.
 */
export async function drawPortrait(img, { detail = 'balanced', shading = true, guides = true } = {}) {
    const [lineThr, minLen, shadeBelow, spacing] = DETAIL[detail] || DETAIL.balanced;
    const { face, seg } = await models();

    const found = face.detect(img).faceLandmarks;
    if (!found || !found.length) throw new Error('No face was found in this photo. Try one where the face is clear and looks towards the camera.');
    const W = img.naturalWidth, H = img.naturalHeight;
    const lmImg = found[0].map(p => [p.x * W, p.y * H]);

    // ---- the head, framed: looked at wide first, then cut to fit it ----
    const top = lmImg[10], chin = lmImg[152];
    const faceH = Math.hypot(chin[0] - top[0], chin[1] - top[1]);
    const xs = GROUPS.oval.map(i => lmImg[i][0]);
    const wide = faceH * 2.3;
    let view = look(img, seg, lmImg, (Math.min(...xs) + Math.max(...xs)) / 2 - wide / 2, (top[1] + chin[1]) / 2 - faceH * 0.15 - wide / 2, wide);
    const box = headBox(view);
    if (box) {
        const side = Math.max(box.w, box.h) * 1.1;
        view = look(img, seg, lmImg, view.sx + (box.x + box.w / 2) / view.scale - side / view.scale / 2,
            view.sy + (box.y + box.h / 2 + side * 0.03) / view.scale - side / view.scale / 2, side / view.scale);
    }
    const { ctx, lm, fH, cat, mw, mh, keep } = view;
    const soft = blur(keep, 2);

    // ---- tone on paper ----
    const px = ctx.getImageData(0, 0, SIZE, SIZE).data;
    const tone = new Float32Array(SIZE * SIZE);
    for (let i = 0; i < SIZE * SIZE; i++) {
        const l = (0.299 * px[i * 4] + 0.587 * px[i * 4 + 1] + 0.114 * px[i * 4 + 2]) / 255;
        tone[i] = soft[i] * l + (1 - soft[i]);
    }
    const flat = stretch(tone, soft);

    // ---- lines: difference of gaussians, dark side of each edge ----
    // A strong edge starts a line; a fainter one carries it on, so lines run
    // unbroken instead of in dashes (hysteresis, as Canny's edges do).
    const g1 = blur(flat, 1.2), g2 = blur(flat, 1.2 * 1.6);
    const dog = new Float32Array(SIZE * SIZE);
    for (let i = 0; i < SIZE * SIZE; i++) dog[i] = soft[i] > 0.02 ? g2[i] - g1[i] : 0;
    const ink = hysteresis(dog, lineThr, lineThr * 0.35);
    // inside an open mouth, the teeth: a hand draws the lips, not each tooth
    const inner = INNER_LIPS.map(i => lm[i]);
    for (let i = 0; i < SIZE * SIZE; i++) {
        if (ink[i] && inside(inner, i % SIZE + 0.5, ((i / SIZE) | 0) + 0.5, -1.5)) ink[i] = 0;
    }
    dropSpecks(ink, 10);
    const labels = label(ink);
    const skel = thin(ink);
    // the pixel steps smoothed out of each line before it is simplified
    const traced = trace(skel).map(path => simplify(steady(path), 0.8));
    const reach = new Map();     // how long the whole line a stroke belongs to is
    traced.forEach(path => {
        const lab = labels[(path[0][1] | 0) * SIZE + (path[0][0] | 0)];
        reach.set(lab, (reach.get(lab) || 0) + pathLength(path));
    });
    const kept = traced.filter(path => pathLength(path) >= 4
        && reach.get(labels[(path[0][1] | 0) * SIZE + (path[0][0] | 0)]) >= minLen);

    // Pressure: a strong edge (the jaw against the wall, the line of the
    // lips) is drawn darker and wider than a faint one (a crease, a strand).
    const strokes = kept.map(path => {
        let edge = 0;
        path.forEach(([x, y]) => { edge += dog[(Math.min(SIZE - 1, y) | 0) * SIZE + (Math.min(SIZE - 1, x) | 0)]; });
        const kind = kindOf(path, lm, cat, mw, mh, fH);
        // the features carry a portrait: a hand presses harder on them
        const lean = kind === 'eyes' || kind === 'mouth' || kind === 'nose' ? 0.3 : 0;
        const press = Math.max(0, Math.min(1, (edge / path.length - lineThr * 0.6) / (lineThr * 3) + lean));
        return {
            k: kind,
            w: round(1.5 + press * 2.0),
            o: round(0.5 + press * 0.45),
            p: path,
        };
    });

    if (shading) strokes.push(...hatch(blur(flat, 3), keep, cat, mw, mh, shadeBelow, spacing));
    if (guides) strokes.push(...guideLines(lm, fH));

    return { size: SIZE, strokes: order(strokes).map(s => ({ ...s, p: s.p.flat().map(round) })) };
}

/* ---- what is drawn ------------------------------------------------ */

/**
 * The photo cut to the square (sx, sy, side) and drawn at SIZE, with its
 * landmarks moved in, and what of it to draw: face, hair and glasses, the
 * neck fading out below the chin — and nothing far from this face, such as
 * someone standing beside them.
 */
function look(img, seg, lmImg, sx, sy, side) {
    const scale = SIZE / side;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = SIZE;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, SIZE, SIZE);
    ctx.drawImage(img, sx, sy, side, side, 0, 0, SIZE, SIZE);
    const lm = lmImg.map(([x, y]) => [(x - sx) * scale, (y - sy) * scale]);
    const fH = Math.hypot(lm[152][0] - lm[10][0], lm[152][1] - lm[10][1]);
    const fW = Math.hypot(lm[454][0] - lm[234][0], lm[454][1] - lm[234][1]);
    const ecx = (lm[454][0] + lm[234][0]) / 2, ecy = (lm[10][1] + lm[152][1]) / 2 - fH * 0.12;

    const result = seg.segment(canvas);
    const cat = result.categoryMask.getAsUint8Array().slice();
    const mw = result.categoryMask.width, mh = result.categoryMask.height;
    if (result.close) result.close();

    const chinY = lm[152][1];
    let keep = new Float32Array(SIZE * SIZE);
    for (let y = 0; y < SIZE; y++) {
        for (let x = 0; x < SIZE; x++) {
            const c = cat[Math.min(mh - 1, (y * mh / SIZE) | 0) * mw + Math.min(mw - 1, (x * mw / SIZE) | 0)];
            let k = c === FACE || c === HAIR || c === OTHER ? 1 : 0;
            if (c === BODY) k = Math.max(0, 1 - (y - chinY) / (fH * 0.28));   // the neck, fading
            // round the head, fading out: long hair and shoulders fall away
            const e = Math.hypot((x - ecx) / (fW * 0.95), (y - ecy) / (fH * 0.95));
            k *= Math.max(0, Math.min(1, (1.25 - e) / 0.25));
            keep[y * SIZE + x] = Math.max(0, Math.min(1, k));
        }
    }
    keep = largestAround(keep, lm[1]);        // the head the nose belongs to
    return { ctx, lm, fH, cat, mw, mh, keep, sx, sy, scale };
}

/** The bounds of the head in a look() — face, hair, glasses — or null. */
function headBox({ keep, lm, fH }) {
    let x0 = SIZE, y0 = SIZE, x1 = -1, y1 = -1;
    const below = lm[152][1] + fH * 0.12;
    for (let y = 0; y < SIZE; y++) for (let x = 0; x < SIZE; x++) {
        if (keep[y * SIZE + x] < 0.5 || y > below) continue;
        if (x < x0) x0 = x; if (x > x1) x1 = x;
        if (y < y0) y0 = y; if (y > y1) y1 = y;
    }
    return x1 < 0 ? null : { x: x0, y: y0, w: x1 - x0 + 1, h: y1 - y0 + 1 };
}

/* ---- the order a hand draws in ---------------------------------- */

function kindOf(path, lm, cat, mw, mh, fH) {
    const [mx, my] = path[(path.length / 2) | 0];
    const c = cat[Math.min(mh - 1, (my * mh / SIZE) | 0) * mw + Math.min(mw - 1, (mx * mw / SIZE) | 0)];
    if (c === HAIR) return 'hair';
    if (c === BODY || my > lm[152][1] + fH * 0.04) return 'neck';
    let best = 'face', bestD = Infinity;
    for (const g of ['oval', 'eyes', 'nose', 'mouth']) {
        for (const i of GROUPS[g]) {
            const d = Math.hypot(lm[i][0] - mx, lm[i][1] - my);
            if (d < bestD) { bestD = d; best = g; }
        }
    }
    const reach = best === 'oval' ? fH * 0.06 : fH * 0.12;
    return bestD <= reach ? best : 'face';
}

/** By kind, in ORDER; within a kind, each stroke starts near where the last one ended. */
function order(strokes) {
    const out = [];
    let pen = [SIZE / 2, 0];
    for (const kind of ORDER) {
        const left = strokes.filter(s => s.k === kind);
        // guides go down the page as drawn; everything else follows the pen
        if (kind === 'guide') { out.push(...left); continue; }
        while (left.length) {
            let best = 0, bestD = Infinity, flip = false;
            left.forEach((s, n) => {
                const a = s.p[0], b = s.p[s.p.length - 1];
                const da = Math.hypot(a[0] - pen[0], a[1] - pen[1]), db = Math.hypot(b[0] - pen[0], b[1] - pen[1]);
                // the long lines of a kind come first, then the pen wanders
                const weight = kind === 'hatch' ? 1 : 1 / Math.sqrt(1 + pathLength(s.p) / 40);
                if (Math.min(da, db) * weight < bestD) { bestD = Math.min(da, db) * weight; best = n; flip = db < da; }
            });
            const s = left.splice(best, 1)[0];
            if (flip) s.p.reverse();
            out.push(s);
            pen = s.p[s.p.length - 1];
        }
    }
    return out;
}

/* ---- guide lines ---------------------------------------------------- */

function guideLines(lm, fH) {
    const line = (a, b, ext) => {
        const dx = b[0] - a[0], dy = b[1] - a[1], len = Math.hypot(dx, dy) || 1;
        const ux = dx / len, uy = dy / len;
        return [[a[0] - ux * ext, a[1] - uy * ext], [b[0] + ux * ext, b[1] + uy * ext]];
    };
    // the face's own tilt: across the eyes, and down the middle
    const L = lm[33], R = lm[263];
    const ex = R[0] - L[0], ey = R[1] - L[1], el = Math.hypot(ex, ey) || 1;
    const across = (p, half) => [[p[0] - ex / el * half, p[1] - ey / el * half], [p[0] + ex / el * half, p[1] + ey / el * half]];
    const width = Math.hypot(lm[454][0] - lm[234][0], lm[454][1] - lm[234][1]);
    const g = p => ({ k: 'guide', w: 1.3, o: 0.3, p });
    return [
        g(line(lm[10], lm[152], fH * 0.12)),
        g(line(L, R, width * 0.16)),
        g(across(lm[2], width * 0.42)),
        g(across([(lm[13][0] + lm[14][0]) / 2, (lm[13][1] + lm[14][1]) / 2], width * 0.36)),
    ];
}

/* ---- hatching ------------------------------------------------------- */

function hatch(shade, keep, cat, mw, mh, below, spacing) {
    const out = [];
    const at = (x, y) => (x < 0 || y < 0 || x >= SIZE || y >= SIZE) ? 1 : shade[(y | 0) * SIZE + (x | 0)];
    const where = (x, y) => {
        if (x < 0 || y < 0 || x >= SIZE || y >= SIZE || keep[(y | 0) * SIZE + (x | 0)] < 0.5) return false;
        const c = cat[Math.min(mh - 1, (y * mh / SIZE) | 0) * mw + Math.min(mw - 1, (x * mw / SIZE) | 0)];
        return c === FACE || c === BODY;   // the hair is drawn in strands, not shaded
    };
    // one pass at -58°, a second across it where it is darkest
    [[-58, below], [32, below * 0.45]].forEach(([deg, limit]) => {
        const t = deg * Math.PI / 180, ux = Math.cos(t), uy = Math.sin(t), nx = -uy, ny = ux;
        const reach = SIZE * 1.5;
        for (let off = -reach; off < reach; off += spacing) {
            let run = null;
            for (let s = -reach; s < reach; s += 1) {
                const x = SIZE / 2 + nx * off + ux * s, y = SIZE / 2 + ny * off + uy * s;
                const dark = where(x, y) && at(x, y) < limit;
                if (dark && !run) run = { start: [x, y], depth: 0, n: 0 };
                if (dark) { run.depth += limit - at(x, y); run.n++; }
                const ended = run && (!dark || run.n > 22 + (Math.abs(off * 7.3) % 14));
                if (ended) {
                    if (run.n >= 5) {
                        const o = Math.min(0.55, 0.2 + run.depth / run.n * 2.5);
                        out.push({ k: 'hatch', w: 1.2, o: round(o), p: [run.start, [x - ux, y - uy]] });
                    }
                    run = dark ? { start: [x, y], depth: 0, n: 0 } : null;
                }
            }
        }
    });
    return out;
}

/* ---- image helpers ---------------------------------------------------- */

function round(v) { return Math.round(v * 10) / 10; }

/**
 * Gaussian blur, separable, with a true gaussian kernel: the line finder
 * subtracts two blurs only a little apart, and a box approximation rounds
 * small ones to the same size.
 */
function blur(src, sigma) {
    const r = Math.ceil(sigma * 3), kernel = [];
    let total = 0;
    for (let k = -r; k <= r; k++) { const v = Math.exp(-(k * k) / (2 * sigma * sigma)); kernel.push(v); total += v; }
    for (let k = 0; k < kernel.length; k++) kernel[k] /= total;
    const tmp = new Float32Array(src.length), out = new Float32Array(src.length), last = SIZE - 1;
    for (let y = 0; y < SIZE; y++) {
        for (let x = 0; x < SIZE; x++) {
            let s = 0;
            for (let k = -r; k <= r; k++) s += src[y * SIZE + Math.max(0, Math.min(last, x + k))] * kernel[k + r];
            tmp[y * SIZE + x] = s;
        }
    }
    for (let x = 0; x < SIZE; x++) {
        for (let y = 0; y < SIZE; y++) {
            let s = 0;
            for (let k = -r; k <= r; k++) s += tmp[Math.max(0, Math.min(last, y + k)) * SIZE + x] * kernel[k + r];
            out[y * SIZE + x] = s;
        }
    }
    return out;
}

/** Stretch the face's tones to their own range, so a dim photo draws as fully as a bright one. */
function stretch(tone, soft) {
    const vals = [];
    for (let i = 0; i < tone.length; i += 3) if (soft[i] > 0.9) vals.push(tone[i]);
    if (vals.length < 100) return tone;
    vals.sort((p, q) => p - q);
    const lo = vals[(vals.length * 0.02) | 0], hi = vals[(vals.length * 0.98) | 0];
    const span = Math.max(0.15, hi - lo);
    return tone.map((v, i) => soft[i] < 0.02 ? 1 : Math.max(0, Math.min(1, (v - lo) / span)) * soft[i] + (1 - soft[i]));
}

/** Keep only the part of the mask joined to the point given (the nose). */
function largestAround(mask, [px, py]) {
    const out = new Float32Array(mask.length);
    const start = (Math.round(py) * SIZE + Math.round(px));
    if (!(mask[start] > 0.5)) return mask;
    const stack = [start];
    out[start] = mask[start];
    while (stack.length) {
        const i = stack.pop(), x = i % SIZE, y = (i / SIZE) | 0;
        for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
            const nx = x + dx, ny = y + dy;
            if (nx < 0 || ny < 0 || nx >= SIZE || ny >= SIZE) continue;
            const j = ny * SIZE + nx;
            if (!out[j] && mask[j] > 0.5) { out[j] = mask[j]; stack.push(j); }
        }
    }
    // keep the fading neck below what was reached, too
    for (let i = 0; i < mask.length; i++) if (!out[i] && mask[i] > 0 && mask[i] <= 0.5) out[i] = mask[i];
    return out;
}

/** Connected groups of ink, 8-way: an id per pixel. */
function label(ink) {
    const lab = new Int32Array(ink.length);
    let next = 0;
    for (let i = 0; i < ink.length; i++) {
        if (!ink[i] || lab[i]) continue;
        next++;
        const stack = [i];
        lab[i] = next;
        while (stack.length) {
            const j = stack.pop(), x = j % SIZE, y = (j / SIZE) | 0;
            for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
                const nx = x + dx, ny = y + dy;
                if (nx < 0 || ny < 0 || nx >= SIZE || ny >= SIZE) continue;
                const k = ny * SIZE + nx;
                if (ink[k] && !lab[k]) { lab[k] = next; stack.push(k); }
            }
        }
    }
    return lab;
}

/**
 * Whether (x, y) lies inside the polygon, at least $margin within its edge
 * (a negative margin: that far inside).
 */
function inside(poly, x, y, margin) {
    let isIn = false, near = Infinity;
    for (let a = 0, b = poly.length - 1; a < poly.length; b = a++) {
        const [ax, ay] = poly[a], [bx, by] = poly[b];
        if ((ay > y) !== (by > y) && x < (bx - ax) * (y - ay) / (by - ay) + ax) isIn = !isIn;
        const dx = bx - ax, dy = by - ay, t = Math.max(0, Math.min(1, ((x - ax) * dx + (y - ay) * dy) / (dx * dx + dy * dy || 1)));
        near = Math.min(near, Math.hypot(x - ax - t * dx, y - ay - t * dy));
    }
    return isIn && near >= -margin;
}

/** Ink wherever $v passes $hi, and on from there wherever it still passes $lo. */
function hysteresis(v, hi, lo) {
    const ink = new Uint8Array(v.length), stack = [];
    for (let i = 0; i < v.length; i++) if (v[i] > hi) { ink[i] = 1; stack.push(i); }
    while (stack.length) {
        const i = stack.pop(), x = i % SIZE, y = (i / SIZE) | 0;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
            const nx = x + dx, ny = y + dy;
            if (nx < 0 || ny < 0 || nx >= SIZE || ny >= SIZE) continue;
            const j = ny * SIZE + nx;
            if (!ink[j] && v[j] > lo) { ink[j] = 1; stack.push(j); }
        }
    }
    return ink;
}

function dropSpecks(ink, min) {
    const lab = label(ink), count = new Map();
    for (let i = 0; i < ink.length; i++) if (ink[i]) count.set(lab[i], (count.get(lab[i]) || 0) + 1);
    for (let i = 0; i < ink.length; i++) if (ink[i] && count.get(lab[i]) < min) ink[i] = 0;
}

/** Zhang–Suen thinning: each line down to its one-pixel centre. */
function thin(src) {
    const img = Uint8Array.from(src);
    const at = (x, y) => (x < 0 || y < 0 || x >= SIZE || y >= SIZE) ? 0 : img[y * SIZE + x];
    let changed = true;
    while (changed) {
        changed = false;
        for (const step of [0, 1]) {
            const kill = [];
            for (let y = 0; y < SIZE; y++) for (let x = 0; x < SIZE; x++) {
                if (!img[y * SIZE + x]) continue;
                const p = [at(x, y - 1), at(x + 1, y - 1), at(x + 1, y), at(x + 1, y + 1), at(x, y + 1), at(x - 1, y + 1), at(x - 1, y), at(x - 1, y - 1)];
                const b = p.reduce((s, v) => s + v, 0);
                if (b < 2 || b > 6) continue;
                let a = 0;
                for (let k = 0; k < 8; k++) if (!p[k] && p[(k + 1) % 8]) a++;
                if (a !== 1) continue;
                if (step === 0 ? (p[0] * p[2] * p[4] || p[2] * p[4] * p[6]) : (p[0] * p[2] * p[6] || p[0] * p[4] * p[6])) continue;
                kill.push(y * SIZE + x);
            }
            kill.forEach(i => { img[i] = 0; });
            if (kill.length) changed = true;
        }
    }
    return img;
}

/** Follow the thinned lines into strokes: from each loose end to the next end or junction. */
function trace(skel) {
    const nbrs = i => {
        const x = i % SIZE, y = (i / SIZE) | 0, out = [];
        for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, 1], [1, -1], [-1, -1]]) {
            const nx = x + dx, ny = y + dy;
            if (nx >= 0 && ny >= 0 && nx < SIZE && ny < SIZE && skel[ny * SIZE + nx]) out.push(ny * SIZE + nx);
        }
        return out;
    };
    const seen = new Uint8Array(skel.length), paths = [];
    const xy = i => [i % SIZE, (i / SIZE) | 0];
    // Through a junction the pen keeps going the way it was heading; the
    // branches it leaves become strokes of their own.
    const walk = start => {
        const path = [start];
        seen[start] = 1;
        let cur = start, dir = null;
        for (;;) {
            const next = nbrs(cur).filter(j => !seen[j]);
            if (!next.length) break;
            let pick = next[0];
            if (dir && next.length > 1) {
                const [cx, cy] = xy(cur);
                let best = -Infinity;
                next.forEach(j => {
                    const [jx, jy] = xy(j);
                    const dx = jx - cx, dy = jy - cy, d = Math.hypot(dx, dy);
                    const along = (dx * dir[0] + dy * dir[1]) / d;
                    if (along > best) { best = along; pick = j; }
                });
            }
            const [ax, ay] = xy(cur), [bx, by] = xy(pick);
            // the heading, smoothed over the last few steps
            const step = [bx - ax, by - ay], sl = Math.hypot(step[0], step[1]);
            dir = dir ? [dir[0] * 0.7 + step[0] / sl * 0.3, dir[1] * 0.7 + step[1] / sl * 0.3] : [step[0] / sl, step[1] / sl];
            cur = pick;
            seen[cur] = 1;
            path.push(cur);
        }
        if (path.length > 1) paths.push(path.map(i => [i % SIZE + 0.5, ((i / SIZE) | 0) + 0.5]));
    };
    for (let i = 0; i < skel.length; i++) if (skel[i] && !seen[i] && nbrs(i).length === 1) walk(i);
    for (let i = 0; i < skel.length; i++) if (skel[i] && !seen[i]) walk(i);   // closed loops
    return paths;
}

/** A moving average along the line: the staircase of pixels becomes a curve; the ends stay put. */
function steady(path) {
    if (path.length < 5) return path;
    return path.map((pt, k) => {
        if (k < 2 || k > path.length - 3) return pt;
        let x = 0, y = 0;
        for (let j = -2; j <= 2; j++) { x += path[k + j][0]; y += path[k + j][1]; }
        return [x / 5, y / 5];
    });
}

function pathLength(p) {
    let len = 0;
    for (let k = 1; k < p.length; k++) len += Math.hypot(p[k][0] - p[k - 1][0], p[k][1] - p[k - 1][1]);
    return len;
}

/** Ramer–Douglas–Peucker: as few points as keep the line's shape. */
function simplify(points, eps) {
    if (points.length < 3) return points;
    const [a, b] = [points[0], points[points.length - 1]];
    const dx = b[0] - a[0], dy = b[1] - a[1], len = Math.hypot(dx, dy) || 1;
    let far = 0, at = 0;
    for (let k = 1; k < points.length - 1; k++) {
        const d = Math.abs(dy * points[k][0] - dx * points[k][1] + b[0] * a[1] - b[1] * a[0]) / len;
        if (d > far) { far = d; at = k; }
    }
    if (far <= eps) return [a, b];
    return simplify(points.slice(0, at + 1), eps).slice(0, -1).concat(simplify(points.slice(at), eps));
}

/**
 * The strokes as an SVG drawing, for the preview — the same drawing the
 * server builds for the website (App\Support\PortraitDrawing), down to the
 * kind of each stroke, which sets how fast pencil.js draws it.
 */
export function toSvg({ size, strokes }) {
    const paths = strokes.map(s => {
        const p = s.p;
        let d = 'M' + p[0] + ' ' + p[1];
        if (p.length === 4) d += 'L' + p[2] + ' ' + p[3];
        for (let k = 2; k + 3 < p.length; k += 2) {
            const mx = round((p[k] + p[k + 2]) / 2), my = round((p[k + 1] + p[k + 3]) / 2);
            d += 'Q' + p[k] + ' ' + p[k + 1] + ' ' + mx + ' ' + my;
        }
        if (p.length > 4) d += 'L' + p[p.length - 2] + ' ' + p[p.length - 1];
        const kind = s.k === 'guide' ? 'g' : s.k === 'hatch' ? 'h' : 'l';
        return '<path d="' + d + '" stroke-width="' + s.w + '" stroke-opacity="' + s.o + '" data-k="' + kind + '"/>';
    }).join('');
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" fill="none" stroke="#33312e" stroke-linecap="round" stroke-linejoin="round">' + paths + '</svg>';
}
