// gathers out/*.json into the site's resources/journal/: q-* into qaysari-drawings.json, the rest into citadel-drawings.json
import fs from 'fs';
import { fileURLToPath } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator
// the box round what is built (walls, fixtures, openings), without the axes, levels and scale around it: the journal card frames by it
function bb(d) {
  let x = 0, y = 0;
  const b = [1e9, 1e9, -1e9, -1e9], see = () => { b[0] = Math.min(b[0], x); b[1] = Math.min(b[1], y); b[2] = Math.max(b[2], x); b[3] = Math.max(b[3], y); };
  for (const [, c, a] of d.matchAll(/([MLHVAZmlhvaz])([^MLHVAZmlhvaz]*)/g)) {
    const n = (a.match(/-?[\d.]+(?:e-?\d+)?/g) || []).map(Number);
    if (c === 'M' || c === 'L') for (let i = 0; i < n.length; i += 2) { x = n[i]; y = n[i + 1]; see(); }
    else if (c === 'H') for (const v of n) { x = v; see(); }
    else if (c === 'V') for (const v of n) { y = v; see(); }
    else if (c === 'A') for (let i = 0; i < n.length; i += 7) { x = n[i + 5]; y = n[i + 6]; see(); }
    else if (c === 'l') for (let i = 0; i < n.length; i += 2) { x += n[i]; y += n[i + 1]; see(); }
    else if (c === 'h') for (const v of n) { x += v; see(); }
    else if (c === 'v') for (const v of n) { y += v; see(); }
  }
  return [b[0], b[1], b[2] - b[0], b[3] - b[1]].map(Math.round);
}
const o = {};
for (const f of fs.readdirSync(HERE + 'out').filter((f) => f.endsWith('.json') && ((a) => (a.rooms || []).length || a.sec || a.map)(JSON.parse(fs.readFileSync(HERE + 'anno/' + f)))).sort()) {
  const g = JSON.parse(fs.readFileSync(HERE + 'out/' + f));
  g.bb = bb(g.d.wall + g.d.fix + g.d.open);
  console.assert(g.bb[2] > 0 && g.bb[3] > 0, f);
  o[f.slice(0, -5)] = g;
}
for (const [file, keep] of [['citadel', (k) => !k.startsWith('q-')], ['qaysari', (k) => k.startsWith('q-')]]) {
  const to = HERE + '../../resources/journal/' + file + '-drawings.json';
  const part = Object.fromEntries(Object.entries(o).filter(([k]) => keep(k)));
  fs.writeFileSync(to, JSON.stringify(part));
  console.log(file, Object.keys(part).join(' '), (fs.statSync(to).size / 1024).toFixed(0) + 'KB');
}
