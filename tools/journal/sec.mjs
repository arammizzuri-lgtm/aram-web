// Sections and elevations redrawn by hand: node sec.mjs <key>  reads spec/<key>.mjs, writes out/<key>.json in the
// drawing format the site's journal.drawing partial reads (and anno/<key>.json so gen.mjs takes it).
// All in metres, x to the right, y up from ±0.00. An arch is 'r' (round), ['p', rise] (pointed) or ['s', rise] (segmental).
//   cut:   [x, y, w, h] or [[x, y], ...]   what the section cuts through: poche and a heavy line
//   edge:  [x1, y1, x2, y2, ...] | {rect: [x, y, w, h]} | {arch: [x, y, w, h, a]}   lines seen beyond
//   win:   [x, y, w, h, a?, nv?, nh?]   a window: frame, and nv mullions and nh transoms in glass
//   door:  [x, y, w, h, a?, leaves?]
//   grille:[x, y, w, h]   an iron or timber screen;  tex: [x1, y1, ...]  faint lines (brick patterns)
//   over:  dashed lines;  steps: [x, y, run, rise, n, dir]  a flight seen or cut;  levels: [[y, '+4.25'], ...] at levelX
//   rooms: [[x, y, '@word' | 'TEXT'], ...]
import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator
const key = process.argv[2];
const sp = (await import(pathToFileURL(HERE + 'spec/' + key + '.mjs').href + '?' + Date.now())).default;
const fs8 = sp.fs, r1 = (v) => Math.round(v * 10) / 10;
const X = (x) => r1(x * 100), Y = (y) => r1(-y * 100), P = (x, y) => X(x) + ' ' + Y(y);
const d = { axis: '', bubble: '', ctx: '', wall: '', fix: '', stair: '', over: '', leaf: '', open: '', glass: '', arrow: '', dim: '', north: '', scale: '' };
const text = [];
let x0 = 1e9, x1 = -1e9, y0 = 1e9, y1 = -1e9;
const see = (x, y) => { x0 = Math.min(x0, x); x1 = Math.max(x1, x); y0 = Math.min(y0, y); y1 = Math.max(y1, y); };
const line = (pts) => { let s = ''; for (let i = 0; i < pts.length; i += 2) { see(pts[i], pts[i + 1]); s += (i ? 'L' : 'M') + P(pts[i], pts[i + 1]); } return s; };
const rect = ([x, y, w, h]) => { see(x, y); see(x + w, y + h); return 'M' + P(x, y) + 'H' + X(x + w) + 'V' + Y(y + h) + 'H' + X(x) + 'Z'; };
// the top of an opening: from its left springing, over, to its right springing (svg arcs; y is flipped, so sweep 1 is over the top)
function top(x, s, w, a) {
  if (!a) return 'L' + P(x + w, s);
  if (a === 'r') return 'A' + r1(w * 50) + ' ' + r1(w * 50) + ' 0 0 1 ' + P(x + w, s);
  const [t, r] = a;
  if (t === 's') { const R = (w * w / 4 + r * r) / (2 * r); return 'A' + r1(R * 100) + ' ' + r1(R * 100) + ' 0 0 1 ' + P(x + w, s); }
  const R = (w * w / 4 + r * r) / w;   // two-centred: each arc centred on the springing line
  return 'A' + r1(R * 100) + ' ' + r1(R * 100) + ' 0 0 1 ' + P(x + w / 2, s + r) + 'A' + r1(R * 100) + ' ' + r1(R * 100) + ' 0 0 1 ' + P(x + w, s);
}
const rise = (w, a) => (!a ? 0 : a === 'r' ? w / 2 : a[1]);
function shape(x, y, w, h, a) { const s = y + h - rise(w, a); see(x, y); see(x + w, y + h); return 'M' + P(x, y) + 'V' + Y(s) + top(x, s, w, a) + 'V' + Y(y) + 'Z'; }
function archLine(x, y, w, h, a) { const s = y + h - rise(w, a); see(x, y); see(x + w, y + h); return 'M' + P(x, y) + 'V' + Y(s) + top(x, s, w, a) + 'V' + Y(y); }

for (const c of sp.cut || []) d.wall += typeof c[0] === 'number' ? rect(c) : line(c.flat()) + 'Z';
for (const e of sp.edge || []) d.fix += Array.isArray(e) ? line(e) : e.rect ? rect(e.rect) : archLine(...e.arch);
for (const t of sp.tex || []) d.ctx += line(t);
for (const o of sp.over || []) d.over += line(o);
for (const [x, y, w, h, a = 0, nv = 1, nh = 1] of sp.win || []) {
  d.open += shape(x, y, w, h, a);
  const s = y + h - rise(w, a), ins = Math.min(.06, w * .08);   // the glass sits inside a thin frame
  d.glass += shape(x + ins, y + ins, w - 2 * ins, h - 2 * ins, a === 'r' ? 'r' : a ? [a[0], Math.max(.05, a[1] - ins)] : 0);
  for (let i = 1; i <= nv; i++) { const xx = x + w * i / (nv + 1); d.glass += 'M' + P(xx, y + ins) + 'V' + Y(a ? (a === 'r' || a[0] !== 's' ? s + rise(w, a) * Math.sqrt(1 - ((xx - x - w / 2) / (w / 2)) ** 2) - ins : s) : y + h - ins); }
  for (let j = 1; j <= nh; j++) { const yy = a ? s : y + h * j / (nh + 1); if (a && j > 1) break; d.glass += 'M' + P(x + ins, yy) + 'H' + X(x + w - ins); }
}
for (const [x, y, w, h, a = 0, leaves = 2] of sp.door || []) {
  d.open += shape(x, y, w, h, a);
  const ins = .07; d.leaf += shape(x + ins, y, w - 2 * ins, h - ins, a === 'r' ? 'r' : a ? [a[0], Math.max(.05, a[1] - ins)] : 0);
  if (leaves === 2) d.leaf += 'M' + P(x + w / 2, y) + 'V' + Y(y + h - ins);
}
for (const [x, y, w, h] of sp.grille || []) {
  d.open += rect([x, y, w, h]);
  const n = Math.max(2, Math.round(w / .12)); for (let i = 1; i < n; i++) d.ctx += 'M' + P(x + w * i / n, y) + 'V' + Y(y + h);
  d.ctx += 'M' + P(x, y + h * .5) + 'H' + X(x + w);
}
// a flight: n steps from (x, y), each run long and rise high, going right (dir 1) or left (-1)
for (const [x, y, run, ri, n, dir = 1] of sp.steps || []) { let s = 'M' + P(x, y), cx = x, cy = y; for (let i = 0; i < n; i++) { cy += ri; s += 'V' + Y(cy); cx += run * dir; s += 'H' + X(cx); see(cx, cy); } d.stair += s; }
// levels down the side, each a triangle on its line with the figure above it
const LX = sp.levelX ?? (x0 - 1.2);
for (const [y, v] of sp.levels || []) {
  const t = fs8 * .5;
  d.dim += 'M' + r1(X(LX) - t) + ' ' + r1(Y(y) - t * 1.2) + 'h' + r1(t * 2) + 'l' + r1(-t) + ' ' + r1(t * 1.2) + 'z';
  d.over += 'M' + r1(X(LX) + t * 1.6) + ' ' + Y(y) + 'H' + r1(X(x0) - fs8 * .3);
  text.push([r1(X(LX) - t * 1.4), r1(Y(y) - fs8 * .35), 'p-note', v, 'end']);
}
for (const [x, y, v] of sp.rooms || []) text.push([X(x), Y(y), 'p-room', v, 'middle']);
// a 5 m scale bar under the drawing
const SB = 500, sy = Y(y0) + fs8 * 2.4, sx = X(x0);
d.scale = 'M' + sx + ' ' + sy + 'H' + r1(sx + SB); for (let i = 0; i <= 5; i++) d.scale += 'M' + r1(sx + i * 100) + ' ' + r1(sy - fs8 * (i % 5 ? .25 : .4)) + 'V' + r1(sy + fs8 * (i % 5 ? .25 : .4));
text.push([sx, r1(sy + fs8 * 1.4), 'p-note', '0', 'middle'], [r1(sx + SB), r1(sy + fs8 * 1.4), 'p-note', '5 m', 'middle']);
const mg = fs8 * 1.5, L = Math.min(X(x0), r1(X(LX) - fs8 * 3)) - mg, T = Y(y1) - mg, R = X(x1) + mg, B = sy + fs8 * 2.4;
const out = { vb: [r1(L), r1(T), r1(R - L), r1(B - T)], fs: fs8, W: r1(R - L), H: r1(B - T), north: null, np: [0, 0], nr: 0, wallH: 0, d, court: '', title: [r1(R), r1(B)], text, areas: [] };
fs.mkdirSync(HERE + 'out', { recursive: true });
fs.writeFileSync(HERE + 'out/' + key + '.json', JSON.stringify(out));
fs.writeFileSync(HERE + 'anno/' + key + '.json', JSON.stringify({ sec: true }));
console.log(key, 'size', ((x1 - x0)).toFixed(2) + ' x ' + (y1 - y0).toFixed(2) + ' m', Object.entries(d).filter(([, v]) => v).map(([k, v]) => k + ' ' + v.length).join(', '));
