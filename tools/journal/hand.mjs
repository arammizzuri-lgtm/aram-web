// Walls redrawn by hand: node hand.mjs <key>  reads spec/<key>.mjs, writes anno/<key>.json for build.mjs.
// spec.walls: [p1, p2, th, ops?] a straight wall (metres, centre line), or {pl: [[x, y], ...], th} a bent one.
// then [cap at p1, cap at p2] (1 = runs on half its thickness, as at a corner; default both).
// ops: [s, e, t, h, side] an opening from s to e metres along p1->p2; t: w, d, dd, o; for d, h = 0 hinges at s,
// 1 at e, and side = 1 opens to the right of p1->p2 as drawn (east->south), -1 to the left.
import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator
const key = process.argv[2];
const spec = (await import(pathToFileURL(HERE + 'spec/' + key + '.mjs').href + '?' + Date.now())).default;
const walls = [], open = [...(spec.open || [])];
const at = (p, u, s) => [+(p[0] + u[0] * s).toFixed(3), +(p[1] + u[1] * s).toFixed(3)];
for (const w of spec.walls || []) {
  if (!Array.isArray(w)) { walls.push({ pts: w.pl, th: w.th, cap: w.cap || [1, 1] }); continue; }
  const [p, q, th, ops = [], [cap0, cap1] = [1, 1]] = w, L = Math.hypot(q[0] - p[0], q[1] - p[1]), u = [(q[0] - p[0]) / L, (q[1] - p[1]) / L], n = [-u[1], u[0]];
  let s0 = 0; const sorted = [...ops].sort((a, b) => a[0] - b[0]);
  for (const [s, e, t, h = 0, side = 1] of sorted) {
    if (s > s0 + .01) walls.push({ pts: [at(p, u, s0), at(p, u, s)], th, cap: [s0 === 0 ? cap0 : 0, 0] });
    const P = at(p, u, s), Q = at(p, u, e), o = { t, p: P, q: Q, th };
    if (t === 'd' || t === 'dd') { o.hinge = h ? Q : P; const c = at(p, u, (s + e) / 2); o.into = [+(c[0] + n[0] * side).toFixed(3), +(c[1] + n[1] * side).toFixed(3)]; }
    open.push(o); s0 = e;
  }
  if (L > s0 + .01) walls.push({ pts: [at(p, u, s0), q], th, cap: [s0 === 0 ? cap0 : 0, cap1] });
}
const out = { ...spec, walls, open }; if (!spec.walls) delete out.walls;
fs.writeFileSync(HERE + 'anno/' + key + '.json', JSON.stringify(out));
console.log(key, walls.length, 'walls', open.length, 'openings');
