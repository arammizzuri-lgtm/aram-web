// Redraw one traced plan as a detailed drawing.
// node build.mjs <key> [--pic] [--r=x,y,w,h (cm)] [--w=px] [--o=suffix]
// in:  traces/<key>.json (trace), anno/<key>.json (what we read off the source); with --pic or --mask,
//      traces/<key>.job.json naming the source scan (not kept here: the sources' licences do not allow copies)
// out: out/<key>.json (the drawing, in cm) and, with --pic, reg/<key><suffix>.jpg (source, straightened walls, gaps, 1 m grid)
import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator
const S = HERE;
const arg = (n) => (process.argv.find((a) => a.startsWith('--' + n + '=')) || '').slice(n.length + 3);
const key = process.argv[2];
const tr = JSON.parse(fs.readFileSync(S + 'traces/' + key + '.json', 'utf8'));
const jobFile = S + 'traces/' + key + '.job.json', job = fs.existsSync(jobFile) ? JSON.parse(fs.readFileSync(jobFile, 'utf8')) : null;
const an = JSON.parse(fs.readFileSync(S + 'anno/' + key + '.json', 'utf8'));
const k = 100 / an.upm;
const D = Math.PI / 180;
const T = Object.assign({ snap: 7, minSnap: 8, off: 4, clu: 3, junc: 30, gapMin: 25, gapMax: 260, endMin: 12, endMax: 130, minLoop: 600, step: 4 }, an.tol || {});
const m = (v) => v * 100;                                   // annotations are in metres

const ang = (a, b) => ((Math.atan2(b[1] - a[1], b[0] - a[0]) / D) % 180 + 180) % 180;
const adiff = (a, b) => { const d = Math.abs(a - b) % 180; return Math.min(d, 180 - d); };
const len = (a, b) => Math.hypot(b[0] - a[0], b[1] - a[1]);
const unit = (a, b) => { const L = len(a, b) || 1; return [(b[0] - a[0]) / L, (b[1] - a[1]) / L]; };
const add = (a, b, f = 1) => [a[0] + b[0] * f, a[1] + b[1] * f];
const mid = (a, b) => [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1];

let loops = tr.d.split('Z').filter((s) => s.trim()).map((s) => s.trim().slice(1).split('L').map((p) => p.trim().split(/\s+/).map((v) => +v * k)));

function peaks() {
  const h = new Float64Array(180); let tot = 0;
  for (const l of loops) for (let i = 0; i < l.length; i++) { const a = l[i], b = l[(i + 1) % l.length], L = len(a, b); if (L < 10) continue; h[Math.round(ang(a, b)) % 180] += L; tot += L; }
  const s = new Float64Array(180); for (let i = 0; i < 180; i++) for (let j = -2; j <= 2; j++) s[i] += h[(i + j + 180) % 180];
  const out = [];
  for (let n = 0; n < 8; n++) { let mx = 0, mi = 0; for (let i = 0; i < 180; i++) if (s[i] > mx) { mx = s[i]; mi = i; } if (mx < tot * (an.peakMin || 0.04)) break; out.push(mi); for (let j = -6; j <= 6; j++) s[(mi + j + 180) % 180] = 0; }
  return out.map((p) => { let sw = 0, sa = 0; for (const l of loops) for (let i = 0; i < l.length; i++) { const a = l[i], b = l[(i + 1) % l.length], L = len(a, b); if (L < 10) continue; let d = ang(a, b) - p; if (d > 90) d -= 180; if (d < -90) d += 180; if (Math.abs(d) <= 3) { sw += L; sa += L * d; } } return ((p + sa / (sw || 1)) % 180 + 180) % 180; });
}
let P = peaks();
const p0 = P[0]; const rot = an.rot ?? -(p0 < 45 ? p0 : p0 < 135 ? p0 - 90 : p0 - 180);
{ const c = Math.cos(rot * D), s = Math.sin(rot * D); loops = loops.map((l) => l.map(([x, y]) => [x * c - y * s, x * s + y * c])); }
let mx = 1e9, my = 1e9; for (const l of loops) for (const [x, y] of l) { mx = Math.min(mx, x); my = Math.min(my, y); }
loops = loops.map((l) => l.map(([x, y]) => [x - mx, y - my]));

// ---- the browser does the raster work ------------------------------------
const { chromium } = await import('playwright');
const br = await chromium.launch({ channel: 'chrome' });
const pg = await br.newPage({ viewport: { width: 1700, height: 1000 } });
if ((process.argv.includes('--pic') || process.argv.includes('--mask')) && !job) throw new Error('--pic and --mask need ' + jobFile + ' with the source scan');
if (job) await pg.goto(pathToFileURL(job.img).href, { timeout: 120000 });
await pg.evaluate(() => {
  // boundary loops of a mask (wall on the right), then Douglas-Peucker
  window.loopsOf = (mk, W, H, eps, px, ox, oy) => {
    const at = (x, y) => (x < 0 || y < 0 || x >= W || y >= H ? 0 : mk[y * W + x]);
    const out = new Map(), key = (x, y) => y * (W + 1) + x, ad = (x1, y1, x2, y2) => { const k = key(x1, y1); (out.get(k) || out.set(k, []).get(k)).push([x2, y2]); };
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) { if (!mk[y * W + x]) continue; if (!at(x, y - 1)) ad(x, y, x + 1, y); if (!at(x + 1, y)) ad(x + 1, y, x + 1, y + 1); if (!at(x, y + 1)) ad(x + 1, y + 1, x, y + 1); if (!at(x - 1, y)) ad(x, y + 1, x, y); }
    const loops = [];
    for (const [k0, list] of out) while (list.length) { const x0 = k0 % (W + 1), y0 = (k0 - x0) / (W + 1); const loop = [[x0, y0]]; let [nx, ny] = list.pop(), px0 = x0, py0 = y0;
      for (;;) { if (nx === x0 && ny === y0) break; loop.push([nx, ny]); const l = out.get(key(nx, ny)); if (!l || !l.length) break; let pick = 0; if (l.length > 1) { const dx = nx - px0, dy = ny - py0; pick = l.findIndex(([ax, ay]) => (ax - nx) === -dy && (ay - ny) === dx); if (pick < 0) pick = 0; } px0 = nx; py0 = ny; [nx, ny] = l.splice(pick, 1)[0]; }
      loops.push(loop); }
    const dp = (pts) => { const keep = new Uint8Array(pts.length); keep[0] = keep[pts.length - 1] = 1; const st = [[0, pts.length - 1]]; while (st.length) { const [a, z] = st.pop(); let md = 0, mi = -1; const [ax, ay] = pts[a], [bx, by] = pts[z], L = Math.hypot(bx - ax, by - ay) || 1; for (let i = a + 1; i < z; i++) { const d = Math.abs((bx - ax) * (ay - pts[i][1]) - (ax - pts[i][0]) * (by - ay)) / L; if (d > md) { md = d; mi = i; } } if (md > eps) { keep[mi] = 1; st.push([a, mi], [mi, z]); } } return pts.filter((_, i) => keep[i]); };
    return loops.filter((l) => l.length > 6).map((l) => { let far = 0, fd = 0; for (let i = 0; i < l.length; i++) { const dd = (l[i][0] - l[0][0]) ** 2 + (l[i][1] - l[0][1]) ** 2; if (dd > fd) { fd = dd; far = i; } } const a = dp(l.slice(0, far + 1)), z = dp(l.slice(far).concat([l[0]])); return a.concat(z.slice(1, -1)).map(([x, y]) => [x * px + ox, y * px + oy]); });
  };
  window.canvasFor = (W, H) => { const c = document.createElement('canvas'); c.width = W; c.height = H; const g = c.getContext('2d', { willReadFrequently: true }); return [c, g]; };
  window.poly = (g, pts) => { g.moveTo(pts[0][0], pts[0][1]); for (const p of pts.slice(1)) g.lineTo(p[0], p[1]); g.closePath(); };
});
// walls redrawn on a 2 cm raster, with our corrections: cut (rubbed out) and add (filled in), as [x, y, w, h] or [[x, y], ...] in metres
loops = await pg.evaluate(({ loops, cut, add, walls, solid, voids, holes }) => {
  const PX = 2, M = 60; let Mx = 0, My = 0; for (const l of loops) for (const [x, y] of l) { Mx = Math.max(Mx, x); My = Math.max(My, y); }
  const W = Math.ceil((Mx + 2 * M) / PX), H = Math.ceil((My + 2 * M) / PX), [c, g] = canvasFor(W, H);
  g.setTransform(1 / PX, 0, 0, 1 / PX, M / PX, M / PX);
  g.fillStyle = '#fff'; g.fillRect(-M, -M, Mx + 2 * M, My + 2 * M);
  g.fillStyle = '#000'; g.strokeStyle = '#000';
  const shape = (s) => { g.beginPath(); if (typeof s[0] === 'number') g.rect(s[0] * 100, s[1] * 100, s[2] * 100, s[3] * 100); else poly(g, s.map(([x, y]) => [x * 100, y * 100])); g.fill(); };
  if (!walls) { g.beginPath(); for (const l of loops) poly(g, l); g.fill('evenodd'); }
  // walls redrawn by hand: [[x, y], [x, y], ..., th] in metres, a polyline of wall centre lines th thick
  // {pts, th, cap: [start, end]}: an end with cap runs on half the thickness (a corner), without stops square (a jamb)
  else for (const w of walls) {
    const pts = w.pts.map(([x, y]) => [x * 100, y * 100]), th = w.th * 100, cap = w.cap || [1, 1];
    const ext = (a, b, d) => { const L = Math.hypot(b[0] - a[0], b[1] - a[1]) || 1; return [a[0] + (a[0] - b[0]) / L * d, a[1] + (a[1] - b[1]) / L * d]; };
    if (cap[0]) pts[0] = ext(pts[0], pts[1], th / 2);
    if (cap[1]) pts[pts.length - 1] = ext(pts[pts.length - 1], pts[pts.length - 2], th / 2);
    g.lineWidth = th; g.lineCap = 'butt'; g.lineJoin = 'miter'; g.miterLimit = 6; g.beginPath(); g.moveTo(...pts[0]); for (const q of pts.slice(1)) g.lineTo(...q); g.stroke();
  }
  // a mass of wall: the outline filled, then the rooms carved out of it
  for (const s of solid) shape(s);
  g.fillStyle = '#fff'; for (const s of voids) shape(s); g.fillStyle = '#000';
  // openings placed by hand go through the wall
  g.fillStyle = '#fff'; for (const o of holes) { const L = Math.hypot(o.q[0] - o.p[0], o.q[1] - o.p[1]), n = [-(o.q[1] - o.p[1]) / L * o.th * .6, (o.q[0] - o.p[0]) / L * o.th * .6]; shape([[o.p[0] + n[0], o.p[1] + n[1]], [o.q[0] + n[0], o.q[1] + n[1]], [o.q[0] - n[0], o.q[1] - n[1]], [o.p[0] - n[0], o.p[1] - n[1]]]); }
  for (const s of cut) shape(s);
  g.fillStyle = '#000'; for (const s of add) shape(s);
  const px = g.getImageData(0, 0, W, H).data, mk = new Uint8Array(W * H); for (let i = 0; i < W * H; i++) mk[i] = px[i * 4] < 128 ? 1 : 0;
  return loopsOf(mk, W, H, .9, PX, -M, -M);
}, { loops, cut: an.cut || [], add: an.add || [], walls: an.walls || (an.solid ? [] : null), solid: an.solid || [], voids: an.voids || [], holes: an.solid ? (an.open || []).filter((o) => o.p) : [] });
P = peaks().map((p) => (adiff(p, 0) < 1.5 ? 0 : adiff(p, 90) < 1.5 ? 90 : p));
P = [...new Set(P.map((p) => +p.toFixed(2)).concat(an.angles || []))];

// ---- straighten: runs of edges along one direction become one line ---------
const nrm = (t) => [-Math.sin(t * D), Math.cos(t * D)];
const offOf = (t, p) => { const n = nrm(t); return p[0] * n[0] + p[1] * n[1]; };
function runsOf(l) {
  const n = l.length, E = [];
  for (let i = 0; i < n; i++) { const a = l[i], b = l[(i + 1) % n], L = len(a, b), t = ang(a, b); let q = null; if (L >= T.minSnap) { let best = 99; for (const p of P) { const d = adiff(t, p); if (d < best) { best = d; q = p; } } if (best > T.snap) q = L >= 14 ? t : null; } E.push({ a, b, L, t: q }); }
  const st = E.findIndex((e, i) => e.t !== null && (E[(i - 1 + n) % n].t === null || adiff(E[(i - 1 + n) % n].t, e.t) > .01 || Math.abs(offOf(e.t, E[(i - 1 + n) % n].a) - offOf(e.t, e.a)) > T.off));
  if (st < 0) return null;
  const runs = []; let cur = null;
  for (let j = 0; j < n; j++) {
    const e = E[(st + j) % n];
    if (e.t === null) continue;
    const o = offOf(e.t, mid(e.a, e.b));
    if (cur && adiff(cur.t, e.t) < .01 && Math.abs(o - cur.o) < T.off) { cur.sw += e.L; cur.so += e.L * o; cur.o = cur.so / cur.sw; cur.end = e.b; }
    else { cur = { t: e.t, sw: e.L, so: e.L * o, o, start: e.a, end: e.b }; runs.push(cur); }
  }
  if (runs.length > 1) { const f = runs[0], z = runs[runs.length - 1]; if (adiff(f.t, z.t) < .01 && Math.abs(f.o - z.o) < T.off) { f.sw += z.sw; f.so += z.so; f.o = f.so / f.sw; f.start = z.start; runs.pop(); } }
  // one face, traced as steps: a chain of runs whose ends all lie within T.step of one straight line is
  // that line (a slanted wall the trace drew as flat steps); niches go deeper than T.step and stay
  const dl = (p, s, e) => { const u = unit(s, e); return Math.abs((p[0] - s[0]) * u[1] - (p[1] - s[1]) * u[0]); };
  for (let i = 0; i < runs.length; i++) {
    let best = i;
    for (let j = i + 1; j < Math.min(runs.length, i + 16); j++) {
      const s0 = runs[i].start, e0 = runs[j].end; if (len(s0, e0) < 60) continue;
      let ok = true; for (let q = i; q <= j && ok; q++) ok = dl(runs[q].start, s0, e0) <= T.step && dl(runs[q].end, s0, e0) <= T.step;
      if (ok) best = j; else if (j > best + 2) break;
    }
    if (best === i) continue;
    const s0 = runs[i].start, e0 = runs[best].end; let t = ang(s0, e0); for (const p of P) if (adiff(t, p) < 1) t = p;
    const sw = runs.slice(i, best + 1).reduce((n, r) => n + r.sw, 0), o = offOf(t, mid(s0, e0));
    runs.splice(i, best - i + 1, { t, o, sw, so: o * sw, start: s0, end: e0 });
  }
  return runs;
}
const all = loops.map(runsOf).filter((r) => r && r.length >= 3);
if (process.env.DBG) for (const rs of all) if (rs.some((r) => len(r.start, [19, 150]) < 40)) console.log(rs.map((r) => [r.t.toFixed(2), r.o.toFixed(1), r.sw.toFixed(0), r.start.map(Math.round).join(',')].join(' ')).join(' | '));
for (const p of new Set(all.flat().map((r) => r.t))) {
  const rs = all.flat().filter((r) => r.t === p).sort((a, b) => a.o - b.o);
  for (let i = 0; i < rs.length;) { let j = i, sw = 0, so = 0; while (j < rs.length && rs[j].o - rs[i].o < T.clu) { sw += rs[j].sw; so += rs[j].sw * rs[j].o; j++; } for (let q = i; q < j; q++) rs[q].o = so / sw; i = j; }
}
const proj = (t, o, p) => { const n = nrm(t), d = dot(p, n) - o; return [p[0] - n[0] * d, p[1] - n[1] * d]; };
function cross(r, q, J) {
  const n1 = nrm(r.t), n2 = nrm(q.t), det = n1[0] * n2[1] - n1[1] * n2[0];
  if (Math.abs(det) > Math.sin(3 * D)) { const x = (r.o * n2[1] - q.o * n1[1]) / det, y = (n1[0] * q.o - n2[0] * r.o) / det; if (len([x, y], J) < T.junc) return [[x, y]]; }
  return [proj(r.t, r.o, r.end), proj(q.t, q.o, q.start)];
}
const area2 = (l) => l.reduce((a, p, i) => { const q = l[(i + 1) % l.length]; return a + p[0] * q[1] - q[0] * p[1]; }, 0) / 2;
let walls = all.map((runs) => { const pts = []; for (let i = 0; i < runs.length; i++) pts.push(...cross(runs[i], runs[(i + 1) % runs.length], runs[i].end)); return pts; });
walls = walls.map((l) => { let ch = true; while (ch && l.length > 3) { ch = false; for (let i = 0; i < l.length; i++) { const a = l[(i - 1 + l.length) % l.length], b = l[i], c = l[(i + 1) % l.length]; const cr = (b[0] - a[0]) * (c[1] - b[1]) - (b[1] - a[1]) * (c[0] - b[0]); if (len(a, b) < .6 || Math.abs(cr) < .02 * Math.max(1, len(a, b), len(b, c))) { l.splice(i, 1); ch = true; break; } } } return l; })
  .filter((l) => l.length >= 3 && Math.abs(area2(l)) >= T.minLoop).map((l) => l.map(([x, y]) => [+x.toFixed(1), +y.toFixed(1)]));
let W = 0, H = 0; for (const l of walls) for (const [x, y] of l) { W = Math.max(W, x); H = Math.max(H, y); }

// ---- gaps: an end of wall facing a wall across an opening -------------------
const inside = (p) => { let n = 0; for (const l of walls) for (let i = 0, j = l.length - 1; i < l.length; j = i++) { const [xi, yi] = l[i], [xj, yj] = l[j]; if ((yi > p[1]) !== (yj > p[1]) && p[0] < (xj - xi) * (p[1] - yi) / (yj - yi) + xi) n++; } return n % 2 === 1; };
const E = []; walls.forEach((l) => l.forEach((a, i) => { const b = l[(i + 1) % l.length]; E.push({ a, b, L: len(a, b), pv: l[(i - 1 + l.length) % l.length], nx: l[(i + 2) % l.length] }); }));
const gaps = [];
for (const e of E) {
  if (e.L < T.endMin || e.L > T.endMax) continue;
  const d = unit(e.a, e.b), out = [d[1], -d[0]];
  const pv = unit(e.pv, e.a), nx = unit(e.b, e.nx);
  if (dot(pv, out) < .9 || dot(nx, out) > -.9 || len(e.pv, e.a) < 10 || len(e.b, e.nx) < 10) continue;
  let best = null;
  for (const f of E) {
    if (f === e) continue;
    if (dot(d, unit(f.a, f.b)) > -0.985) continue;
    const g = dot([f.a[0] - e.a[0], f.a[1] - e.a[1]], out);
    if (g < T.gapMin || g > T.gapMax) continue;
    const ta = dot([f.a[0] - e.a[0], f.a[1] - e.a[1]], d), tb = dot([f.b[0] - e.a[0], f.b[1] - e.a[1]], d);
    if (Math.min(e.L, Math.max(ta, tb)) - Math.max(0, Math.min(ta, tb)) < e.L * .6) continue;
    if (best && g >= best.g) continue;
    let clear = true; for (const fr of [.25, .5, .75]) for (const fw of [.3, .5, .7]) if (inside([e.a[0] + d[0] * e.L * fr + out[0] * g * fw, e.a[1] + d[1] * e.L * fr + out[1] * g * fw])) clear = false;
    if (clear) best = { g };
  }
  if (!best) continue;
  const q1 = add(e.a, out, best.g), q2 = add(e.b, out, best.g);
  const g = { a1: e.a, b1: e.b, a2: q1, b2: q2, w: +best.g.toFixed(1), th: +e.L.toFixed(1), c: mid(mid(e.a, e.b), mid(q1, q2)) };
  if (gaps.some((h) => len(h.c, g.c) < 15 && Math.abs(h.w - g.w) < 15)) continue;
  gaps.push(g);
}
gaps.sort((a, b) => (Math.round(a.c[1] / 150) - Math.round(b.c[1] / 150)) || a.c[0] - b.c[0]);
gaps.forEach((g, i) => { g.id = i + 1; });
console.log(key, 'rot', rot.toFixed(2), 'peaks', P.join(','), 'size', (W / 100).toFixed(2) + ' x ' + (H / 100).toFixed(2) + ' m', 'loops', walls.length, 'pts', walls.reduce((n, l) => n + l.length, 0), 'gaps', gaps.length);

// ---- openings, from the annotations ---------------------------------------
// {at:[x,y]} picks the nearest gap; or {p:[x,y], q:[x,y], th} draws one where the trace has none.
// t: d (door; hinge:[x,y] picks the jamb, into:[x,y] the side), dd (double door), w (window), o (open, an arch), n (nothing)
const opens = [];
for (const o of an.open || []) {
  let g;
  if (o.p) { const p = o.p.map(m), q = o.q.map(m), u = unit(p, q), n = [-u[1], u[0]], h = m(o.th) / 2; g = { a1: add(p, n, h), b1: add(p, n, -h), a2: add(q, n, h), b2: add(q, n, -h), w: len(p, q), th: m(o.th) }; g.c = mid(p, q); }
  else { const at = o.at.map(m); g = gaps.reduce((b, x) => (!b || len(x.c, at) < len(b.c, at) ? x : b), null); if (!g || len(g.c, at) > 60) { console.log('  no gap near', o.at); continue; } g.used = true; }
  if (o.t === 'n') continue;
  opens.push({ ...o, g });
}
const r1 = (v) => +v.toFixed(1), pt = (p) => r1(p[0]) + ' ' + r1(p[1]);
let dOpen = '', dGlass = '', dOver = '', dLeaf = '';
for (const { t, g, hinge, into } of opens) {
  const fa = [g.a1, g.a2], fb = [g.b1, g.b2];
  if (t === 'w') {
    dOpen += 'M' + pt(g.a1) + 'L' + pt(g.a2) + 'M' + pt(g.b1) + 'L' + pt(g.b2);
    const c1 = mid(g.a1, g.b1), c2 = mid(g.a2, g.b2), n = unit(g.b1, g.a1), e = Math.min(4, g.th / 6);
    dGlass += 'M' + pt(add(c1, n, e)) + 'L' + pt(add(c2, n, e)) + 'M' + pt(add(c1, n, -e)) + 'L' + pt(add(c2, n, -e));
  } else if (t === 'o') {
    dOver += 'M' + pt(g.a1) + 'L' + pt(g.a2) + 'M' + pt(g.b1) + 'L' + pt(g.b2);
  } else {
    // which face it opens to
    const ip = into.map(m), sideA = len(mid(...fa), ip) < len(mid(...fb), ip);
    const F = sideA ? fa : fb, Bk = sideA ? fb : fa;
    const leaf = (H, O, Hb, w) => { const n = unit(Hb, H), E = add(H, n, w); const cr = (E[0] - H[0]) * (O[1] - H[1]) - (E[1] - H[1]) * (O[0] - H[0]); dLeaf += 'M' + pt(H) + 'L' + pt(E); dOpen += 'M' + pt(E) + 'A' + r1(w) + ' ' + r1(w) + ' 0 0 ' + (cr > 0 ? 1 : 0) + ' ' + pt(O); };
    if (t === 'dd') { const c = mid(F[0], F[1]), w = len(F[0], F[1]) / 2; leaf(F[0], c, Bk[0], w); leaf(F[1], c, Bk[1], w); }
    else { const hp = hinge.map(m), j = len(F[0], hp) <= len(F[1], hp) ? 0 : 1; leaf(F[j], F[1 - j], Bk[j], len(F[0], F[1])); }
  }
}

// ---- stairs, fittings, overhead lines ---------------------------------------
let dStair = '', dFix = '', dArrow = '';
const text = [];
for (const s of an.stairs || []) {
  if (s.arc) {   // a winding stair: [cx, cy, r1, r2, a0, a1] (degrees), n treads
    const [cx, cy, ra, rb, a0, a1] = s.arc.map((v, i) => (i < 4 ? m(v) : v)), pa = (r, a) => [cx + r * Math.cos(a * D), cy + r * Math.sin(a * D)];
    const sw = a1 > a0 ? 1 : 0, big = Math.abs(a1 - a0) > 180 ? 1 : 0;
    dStair += 'M' + pt(pa(ra, a0)) + 'A' + r1(ra) + ' ' + r1(ra) + ' 0 ' + big + ' ' + sw + ' ' + pt(pa(ra, a1)) + 'M' + pt(pa(rb, a0)) + 'A' + r1(rb) + ' ' + r1(rb) + ' 0 ' + big + ' ' + sw + ' ' + pt(pa(rb, a1));
    for (let i = 0; i <= s.n; i++) { const a = a0 + (a1 - a0) * i / s.n; dStair += 'M' + pt(pa(ra, a)) + 'L' + pt(pa(rb, a)); }
    const rm = (ra + rb) / 2, aE = a1 - (a1 - a0) * .06; dArrow += 'M' + pt(pa(rm, a0 + (a1 - a0) * .06)) + 'A' + r1(rm) + ' ' + r1(rm) + ' 0 ' + big + ' ' + sw + ' ' + pt(pa(rm, aE));
    const tip = pa(rm, aE), tg = [-Math.sin(aE * D) * (sw ? 1 : -1), Math.cos(aE * D) * (sw ? 1 : -1)], nn = [-tg[1], tg[0]];
    dArrow += 'M' + pt(add(add(tip, tg, -14), nn, 7)) + 'L' + pt(tip) + 'L' + pt(add(add(tip, tg, -14), nn, -7));
    if (s.lbl) text.push([...add(tip, tg, 22).map(r1), 'p-note', s.lbl, 'middle']);
    continue;
  }
  const p = s.p.map(m), u = [Math.cos(s.dir * D), Math.sin(s.dir * D)], n = [-u[1], u[0]], w = m(s.w) / 2, L = m(s.t) * s.n;
  const c1 = add(p, n, w), c2 = add(p, n, -w);
  dStair += 'M' + pt(c1) + 'L' + pt(add(c1, u, L)) + 'M' + pt(c2) + 'L' + pt(add(c2, u, L));
  for (let i = 0; i <= s.n; i++) dStair += 'M' + pt(add(c1, u, m(s.t) * i)) + 'L' + pt(add(c2, u, m(s.t) * i));
  const a = add(p, u, m(s.t) * .5), z = add(p, u, L - m(s.t) * .3);
  dArrow += 'M' + pt(a) + 'L' + pt(z) + 'M' + pt(add(add(z, u, -14), n, 7)) + 'L' + pt(z) + 'L' + pt(add(add(z, u, -14), n, -7));
  dArrow += 'M' + pt(add(a, n, 5)) + 'a5 5 0 1 0 0 -0.01';
  if (s.lbl) text.push([...add(z, u, 24).map(r1), 'p-note', s.lbl, 'middle']);
}
const circ = (c, r) => 'M' + r1(c[0] - r) + ' ' + r1(c[1]) + 'a' + r1(r) + ' ' + r1(r) + ' 0 1 0 ' + r1(2 * r) + ' 0a' + r1(r) + ' ' + r1(r) + ' 0 1 0 ' + r1(-2 * r) + ' 0';
const rect = (x, y, w, h) => 'M' + r1(x) + ' ' + r1(y) + 'h' + r1(w) + 'v' + r1(h) + 'h' + r1(-w) + 'z';
const cols = [];
for (const [t, ...v] of an.fix || []) {
  const q = v.map(m);
  if (t === 'col') { const [x, y, s] = q; dFix += rect(x - s / 2, y - s / 2, s, s) + circ([x, y], s * .3); cols.push([x, y]); }
  else if (t === 'colr') { dFix += circ([q[0], q[1]], q[2]); cols.push([q[0], q[1]]); }
  else if (t === 'pool') { const [x, y, w, h] = q; dFix += rect(x, y, w, h) + rect(x + 8, y + 8, w - 16, h - 16); }
  else if (t === 'basin') { const [x, y, rx, ry] = q; dFix += 'M' + r1(x - rx) + ' ' + r1(y) + 'a' + r1(rx) + ' ' + r1(ry) + ' 0 1 0 ' + r1(2 * rx) + ' 0a' + r1(rx) + ' ' + r1(ry) + ' 0 1 0 ' + r1(-2 * rx) + ' 0' + 'M' + r1(x - rx + 8) + ' ' + r1(y) + 'a' + r1(rx - 8) + ' ' + r1(ry - 8) + ' 0 1 0 ' + r1(2 * rx - 16) + ' 0a' + r1(rx - 8) + ' ' + r1(ry - 8) + ' 0 1 0 ' + r1(-2 * rx + 16) + ' 0' + circ([x, y], 6); }
  else if (t === 'tandoor' || t === 'well') { const [x, y, r] = q; dFix += circ([x, y], r) + circ([x, y], r * .6) + (t === 'well' ? rect(x - r - 8, y - r - 8, 2 * r + 16, 2 * r + 16) : ''); }
  else if (t === 'tree') { const [x, y, r] = q; dFix += circ([x, y], r) + circ([x, y], r * .72) + circ([x, y], 6); }
  else if (t === 'rect') dFix += rect(...q);
  else if (t === 'line') dFix += 'M' + pt([q[0], q[1]]) + 'L' + pt([q[2], q[3]]);
  else if (t === 'circ') dFix += circ([q[0], q[1]], q[2]);
}
for (const o of an.over || []) dOver += 'M' + o.map(m).reduce((s, v, i) => s + (i % 2 ? r1(v) : (i ? 'L' : '') + r1(v) + ' '), '');

// ---- areas: each room flood-filled with its openings shut ------------------
const rooms = an.rooms || [];
const ar = await pg.evaluate(({ dbg, walls, shut, split, seeds, court }) => {
  const PX = 2, M = 40; let Mx = 0, My = 0; for (const l of walls) for (const [x, y] of l) { Mx = Math.max(Mx, x); My = Math.max(My, y); }
  const W = Math.ceil((Mx + 2 * M) / PX), H = Math.ceil((My + 2 * M) / PX), [c, g] = canvasFor(W, H);
  g.setTransform(1 / PX, 0, 0, 1 / PX, M / PX, M / PX); g.fillStyle = '#fff'; g.fillRect(-M, -M, Mx + 2 * M, My + 2 * M);
  g.fillStyle = '#000'; g.beginPath(); for (const l of walls) poly(g, l); g.fill('evenodd');
  for (const q of shut) { g.beginPath(); poly(g, q); g.fill(); g.lineWidth = 3; g.strokeStyle = '#000'; g.stroke(); }
  g.lineWidth = 4; g.strokeStyle = '#000'; for (const s of split) { g.beginPath(); g.moveTo(s[0], s[1]); for (let i = 2; i < s.length; i += 2) g.lineTo(s[i], s[i + 1]); g.stroke(); }
  const px = g.getImageData(0, 0, W, H).data, wall = new Uint8Array(W * H); for (let i = 0; i < W * H; i++) wall[i] = px[i * 4] < 128 ? 1 : 0;
  const fill = (sx, sy) => { const x0 = Math.round((sx + M) / PX), y0 = Math.round((sy + M) / PX), mk = new Uint8Array(W * H), st = [y0 * W + x0]; let n = 0, edge = false; if (wall[st[0]]) return { n: -1 }; mk[st[0]] = 1;
    while (st.length) { const j = st.pop(), x = j % W, y = (j - x) / W; n++; if (x === 0 || y === 0 || x === W - 1 || y === H - 1) { edge = true; continue; } for (const q of [j - 1, j + 1, j - W, j + W]) if (!wall[q] && !mk[q]) { mk[q] = 1; st.push(q); } }
    return { n, edge, mk }; };
  const areas = seeds.map(([x, y]) => { const f = fill(x, y); return f.n < 0 ? -1 : f.edge ? -2 : f.n * PX * PX / 1e4; });
  let courtLoops = null, leak = null;
  // which way a room leaks out: its fill in red over the walls (--leak)
  // the way out from each leaking room, as points in metres (--leak)
  // ...or to the next room it runs into (the same area as an earlier one)
  const cell = ([sx, sy]) => Math.round((sy + M) / PX) * W + Math.round((sx + M) / PX);
  const paths = !dbg ? [] : seeds.map((sd, i) => { const to = areas[i] === -2 ? -1 : areas.findIndex((a, k) => k < i && a === areas[i] && a > 0); if (areas[i] !== -2 && to < 0) return null; const goal = to >= 0 ? cell(seeds[to]) : -1, par = new Int32Array(W * H).fill(-1), q = [cell(sd)]; par[q[0]] = q[0]; let end = -1;
    for (let h = 0; h < q.length && end < 0; h++) { const j = q[h], x = j % W, y = (j - x) / W; if (j === goal || (goal < 0 && (x === 0 || y === 0 || x === W - 1 || y === H - 1))) { end = j; break; } for (const k of [j - 1, j + 1, j - W, j + W]) if (!wall[k] && par[k] < 0) { par[k] = j; q.push(k); } }
    const pts = []; for (let j = end; j >= 0 && par[j] !== j; j = par[j]) pts.push([((j % W) * PX - M) / 100, ((j - j % W) / W * PX - M) / 100]); return pts.reverse().filter((_, k) => k % 15 === 0).map((p) => p.map((v) => v.toFixed(2)).join(',')).join(' '); });
  if (dbg) { let i = areas.findIndex((a) => a === -2); if (i < 0) i = paths.findIndex((p) => p); if (i >= 0) { const f = fill(...seeds[i]); const id = g.getImageData(0, 0, W, H); for (let j = 0; j < W * H; j++) if (f.mk[j]) { id.data[j * 4] = 255; id.data[j * 4 + 1] = 0; id.data[j * 4 + 2] = 0; } g.putImageData(id, 0, 0); leak = c.toDataURL('image/png'); } }
  if (court) { const f = fill(court[0], court[1]); if (f.mk && !f.edge) courtLoops = loopsOf(f.mk, W, H, 1.2, PX, -M, -M); }
  return { areas, courtLoops, leak, paths };
}, { dbg: process.argv.includes('--leak'), walls, shut: opens.map(({ g, p }) => { if (!p) return [g.a1, g.a2, g.b2, g.b1]; const u = unit(g.a1, g.a2); return [add(g.a1, u, -10), add(g.a2, u, 10), add(g.b2, u, 10), add(g.b1, u, -10)]; }), split: (an.split || []).map((s) => s.map(m)), seeds: rooms.map((r) => [m(r[0]), m(r[1])]), court: an.court ? an.court.map(m) : null });

if (ar.paths) ar.paths.forEach((p, i) => p && console.log('  leak', i, rooms[i][2], p));
if (ar.leak) fs.writeFileSync(S + 'reg/' + key + '-leak.png', Buffer.from(ar.leak.split(',')[1], 'base64'));
// ---- frame: axes, dimensions, labels -------------------------------------
const fs8 = an.fs || Math.max(W, H) / 64 * (an.textScale || 1);   // one text unit (cm): set per plate so its floors match
const mg = fs8 * 7.5;
const ax = an.axes || { x: [], y: [] }, AX = ax.x.map(m), AY = ax.y.map(m);
let dAxis = '', dBub = '', dDim = '';
const top = -mg, left = -mg, bR = fs8 * 1.25;
for (const [i, x] of AX.entries()) { dAxis += 'M' + r1(x) + ' ' + r1(top + bR) + 'V' + r1(H + mg * .45); dBub += circ([x, top], bR); text.push([r1(x), r1(top + fs8 * .42), 'p-tag', String(i + 1), 'middle']); }
for (const [i, y] of AY.entries()) { dAxis += 'M' + r1(left + bR) + ' ' + r1(y) + 'H' + r1(W + mg * .45); dBub += circ([left, y], bR); text.push([r1(left), r1(y + fs8 * .42), 'p-tag', 'ABCDEFGH'[i], 'middle']); }
const tick = (x, y) => 'M' + r1(x - fs8 * .45) + ' ' + r1(y + fs8 * .45) + 'L' + r1(x + fs8 * .45) + ' ' + r1(y - fs8 * .45);
const fmt = (v) => (v / 100).toFixed(2);
if (AX.length > 1 && !an.noScale) { const y = top + mg * .45; dDim += 'M' + r1(AX[0]) + ' ' + r1(y) + 'H' + r1(AX[AX.length - 1]); for (const x of AX) dDim += tick(x, y); for (let i = 1; i < AX.length; i++) text.push([r1((AX[i - 1] + AX[i]) / 2), r1(y - fs8 * .4), 'p-dim', fmt(AX[i] - AX[i - 1]), 'middle']); }
if (AY.length > 1 && !an.noScale) { const x = left + mg * .45; dDim += 'M' + r1(x) + ' ' + r1(AY[0]) + 'V' + r1(AY[AY.length - 1]); for (const y of AY) dDim += tick(x, y); for (let i = 1; i < AY.length; i++) text.push([r1(x - fs8 * .4), r1((AY[i - 1] + AY[i]) / 2), 'p-dim', fmt(AY[i] - AY[i - 1]), 'middle', -90]); }
// rooms: name, then number and area
rooms.forEach(([x, y, name, opts = {}], i) => {
  // a room keyed to the plate's legend shows that number; once any is keyed, the rest show their area alone
  const a = ar.areas[i], keyed = rooms.some((r) => r[3] && r[3].no), no = opts.no ?? (keyed ? '' : an.numbers === false ? '' : String(i + 1).padStart(2, '0'));   // no: '' for none
  text.push([r1(m(x)), r1(m(y) - fs8 * .2), 'p-room', '@' + name, 'middle']);
  const area = a > 0 && an.areas !== false && !an.noScale && !opts.noArea ? a.toFixed(1) + ' m²' : '', note = [no, area].filter(Boolean).join(' · ');
  if (note) text.push([r1(m(x)), r1(m(y) + fs8 * .95), 'p-note', note, 'middle']);
  if (a < 0) console.log('  room', no, name, a === -1 ? 'seed in a wall' : 'leaks out of the plan');
});
for (const [x, y, v] of an.levels || []) { const X = m(x), Y = m(y); dDim += 'M' + r1(X - fs8 * .35) + ' ' + r1(Y - fs8 * .7) + 'h' + r1(fs8 * .7) + 'l' + r1(-fs8 * .35) + ' ' + r1(fs8 * .6) + 'z'; text.push([r1(X + fs8 * .55), r1(Y - fs8 * .15), 'p-note', v, 'start']); }
for (const [x, y, v] of an.tags || []) text.push([r1(m(x)), r1(m(y)), 'p-note', v, 'middle']);   // legend numbers for what is not a room (entry, stair)
for (const [x, y, v, a = 'middle', r] of an.notes || []) text.push([r1(m(x)), r1(m(y)), 'p-room', v.startsWith('@') ? v : v, a, r]);
// the entrance: an arrow in from outside
if (an.entry) { const [x, y, dg] = an.entry, p = [m(x), m(y)], u = [Math.sin(dg * D), -Math.cos(dg * D)], n = [-u[1], u[0]], L = fs8 * 2.2; const a = add(p, u, -L); dArrow += 'M' + pt(a) + 'L' + pt(p) + 'M' + pt(add(add(p, u, -fs8 * .7), n, fs8 * .4)) + 'L' + pt(p) + 'L' + pt(add(add(p, u, -fs8 * .7), n, -fs8 * .4)); }
// context: the alley's edge and neighbours' lines
let dCtx = ''; for (const o of an.ctx || []) dCtx += 'M' + o.map(m).reduce((s, v, i) => s + (i % 2 ? r1(v) : (i ? 'L' : '') + r1(v) + ' '), '');
// north point and scale bar, bottom right and bottom left
const NP = [W + mg * .25, H + mg * .62], NR = fs8 * 1.3;
let dNorth = '';
if (an.north !== undefined && an.north !== null) { const u = [Math.sin(an.north * D), -Math.cos(an.north * D)]; dNorth = circ(NP, NR) + 'M' + pt(add(NP, u, -NR)) + 'L' + pt(add(NP, u, NR * 1.05)) + 'M' + pt(add(add(NP, u, NR * .35), [-u[1], u[0]], NR * .4)) + 'L' + pt(add(NP, u, NR * 1.05)) + 'L' + pt(add(add(NP, u, NR * .35), [-u[1], u[0]], -NR * .4)); text.push([...add(NP, u, NR * 1.75).map(r1), 'p-tag', 'N', 'middle']); }
const SB = 500, sy = H + mg * .62; let dScale = an.noScale ? '' : 'M0 ' + r1(sy) + 'H' + SB; if (!an.noScale) for (let i = 0; i <= 5; i++) dScale += 'M' + i * 100 + ' ' + r1(sy - (i % 5 ? fs8 * .3 : fs8 * .5)) + 'V' + r1(sy + (i % 5 ? fs8 * .3 : fs8 * .5));
if (!an.noScale) text.push([0, r1(sy + fs8 * 1.6), 'p-dim', '0', 'middle'], [SB, r1(sy + fs8 * 1.6), 'p-dim', '5 m', 'middle']);

const walld = walls.map((l) => 'M' + l.map(pt).join('L') + 'Z').join('');
const court = ar.courtLoops ? ar.courtLoops.map((l) => 'M' + l.map(pt).join('L') + 'Z').join('') : null;
const vb = [r1(left - bR - fs8 * .6), r1(top - bR - fs8 * .6), 0, 0]; vb[2] = r1(NP[0] + NR * 2.4 - vb[0]); vb[3] = r1(sy + fs8 * 2.6 - vb[1]);
const outp = { vb, fs: r1(fs8), W: r1(W), H: r1(H), north: an.north ?? null, np: NP.map(r1), nr: r1(NR), wallH: an.wallH || 400,
  d: { axis: dAxis, bubble: dBub, ctx: dCtx, wall: walld, fix: dFix, stair: dStair, over: dOver, leaf: dLeaf, open: dOpen, glass: dGlass, arrow: dArrow, dim: dDim, north: dNorth, scale: dScale },
  court, title: [r1(left - bR), r1(sy + fs8 * 1.6)], text, areas: ar.areas };
fs.mkdirSync(S + 'out', { recursive: true });
fs.writeFileSync(S + 'out/' + key + '.json', JSON.stringify(outp));
console.log('  openings', opens.length, 'of', gaps.length, 'gaps (unused:', gaps.filter((g) => !g.used).map((g) => g.id + '@' + (g.c[0] / 100).toFixed(1) + ',' + (g.c[1] / 100).toFixed(1)).join(' ') + ')', 'rooms', rooms.map((r, i) => r[2] + ' ' + ar.areas[i]?.toFixed?.(1)).join(', '), 'court', !!court);

// ---- the check picture ---------------------------------------------------
if (process.argv.includes('--mask')) {
  // the source in this frame at 5 cm a pixel: 1 where it is inked (wall fill or line), for scan.mjs
  const m = await pg.evaluate(({ job, k, rot, shift, W, H }) => {
    const img = document.images[0];
    const [cx, cy, cw, ch] = job.crop, up = job.up || 1, jr = ((job.rot || 0) % 360 + 360) % 360;
    const sw = Math.round(cw * up), sh = Math.round(ch * up), Wc = jr % 180 ? sh : sw, Hc = jr % 180 ? sw : sh;
    const [src, sg] = canvasFor(Wc, Hc); sg.fillStyle = '#fff'; sg.fillRect(0, 0, Wc, Hc); sg.translate(Wc / 2, Hc / 2); sg.rotate(jr * Math.PI / 180); sg.drawImage(img, cx, cy, cw, ch, -sw / 2, -sh / 2, sw, sh);
    const P = 5, ox = -100, oy = -100, w = Math.ceil((W + 200) / P), h = Math.ceil((H + 200) / P), [v, g] = canvasFor(w, h);
    g.fillStyle = '#fff'; g.fillRect(0, 0, w, h); g.setTransform(1 / P, 0, 0, 1 / P, -ox / P, -oy / P);
    g.translate(-shift[0], -shift[1]); g.rotate(rot * Math.PI / 180); g.scale(1000 / Wc * k, 1000 / Wc * k); g.drawImage(src, 0, 0);
    const d = g.getImageData(0, 0, w, h).data; let bits = '';
    for (let i = 0; i < w * h; i++) { const r = d[i * 4], gg = d[i * 4 + 1], b = d[i * 4 + 2]; bits += (r + gg + b) / 3 < 232 && !(b > r + 25) ? (r - b > 60 && gg < 150 ? '2' : '1') : '0'; }
    return { w, h, ox, oy, P, bits };
  }, { job, k, rot, shift: [mx, my], W, H });
  fs.writeFileSync(S + 'reg/' + key + '.mask.json', JSON.stringify(m));
}
if (process.argv.includes('--pic')) {
  const reg = (arg('r') || '').split(',').filter(Boolean).map(Number);
  const pic = await pg.evaluate(({ job, k, rot, shift, walls, gaps, W, H, picW, region, bare }) => {
    const img = document.images[0];
    const [cx, cy, cw, ch] = job.crop, up = job.up || 1, jr = ((job.rot || 0) % 360 + 360) % 360;
    const sw = Math.round(cw * up), sh = Math.round(ch * up), Wc = jr % 180 ? sh : sw, Hc = jr % 180 ? sw : sh;
    const [src, sg] = canvasFor(Wc, Hc); sg.fillStyle = '#fff'; sg.fillRect(0, 0, Wc, Hc); sg.translate(Wc / 2, Hc / 2); sg.rotate(jr * Math.PI / 180); sg.drawImage(img, cx, cy, cw, ch, -sw / 2, -sh / 2, sw, sh);
    const [rx, ry, rw, rh] = region.length === 4 ? region : [-60, -60, W + 120, H + 120];
    const sc = picW / rw, [v, g] = canvasFor(picW, Math.round(rh * sc));
    g.fillStyle = '#fff'; g.fillRect(0, 0, v.width, v.height); g.setTransform(sc, 0, 0, sc, -rx * sc, -ry * sc);
    g.save(); g.translate(-shift[0], -shift[1]); g.rotate(rot * Math.PI / 180); g.scale(1000 / Wc * k, 1000 / Wc * k); g.globalAlpha = .55; g.drawImage(src, 0, 0); g.restore();
    g.lineWidth = 1 / sc; g.font = (11 / sc) + 'px sans-serif'; g.fillStyle = '#08f';
    for (let x = 0; x <= W + 100; x += 50) { g.strokeStyle = x % 100 ? 'rgba(0,120,255,.12)' : 'rgba(0,120,255,.35)'; g.beginPath(); g.moveTo(x, ry); g.lineTo(x, ry + rh); g.stroke(); if (!(x % 100)) { g.fillText(x / 100, x + 2 / sc, ry + 12 / sc); g.fillText(x / 100, x + 2 / sc, ry + rh - 3 / sc); } }
    for (let y = 0; y <= H + 100; y += 50) { g.strokeStyle = y % 100 ? 'rgba(0,120,255,.12)' : 'rgba(0,120,255,.35)'; g.beginPath(); g.moveTo(rx, y); g.lineTo(rx + rw, y); g.stroke(); if (!(y % 100)) { g.fillText(y / 100, rx + 2 / sc, y - 2 / sc); g.fillText(y / 100, rx + rw - 22 / sc, y - 2 / sc); } }
    if (bare) return v.toDataURL('image/jpeg', .9);
    g.beginPath(); for (const l of walls) poly(g, l); g.fillStyle = 'rgba(255,0,120,.18)'; g.fill('evenodd'); g.strokeStyle = 'rgba(230,0,90,.95)'; g.lineWidth = 1.6 / sc; g.stroke();
    for (const q of gaps) { g.fillStyle = q.used ? 'rgba(0,200,80,.3)' : 'rgba(0,160,255,.25)'; g.beginPath(); poly(g, [q.a1, q.a2, q.b2, q.b1]); g.fill(); g.font = 'bold ' + (13 / sc) + 'px sans-serif'; g.fillStyle = '#000'; g.textAlign = 'center'; g.textBaseline = 'middle'; g.fillText(q.id, q.c[0], q.c[1]); }
    return v.toDataURL('image/jpeg', .9);
  }, { job, k, rot, shift: [mx, my], walls, gaps, W, H, picW: +(arg('w') || 1800), region: reg, bare: process.argv.includes('--bare') });
  fs.writeFileSync(S + 'reg/' + key + (arg('o') || '') + '.jpg', Buffer.from(pic.split(',')[1], 'base64'));
}
await br.close();
