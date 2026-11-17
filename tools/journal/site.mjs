// A site plan from OpenStreetMap: node site.mjs <key> [--fetch]   reads spec/<key>.mjs, writes out/<key>.json and anno/<key>.json.
// --fetch asks Overpass for the spec's box and keeps what falls within r of the centre in osm/<key>.json (ODbL:
// the plate and the sources must say "© OpenStreetMap contributors"); without it, that saved copy is drawn.
import fs from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator
const key = process.argv[2];
const sp = (await import(pathToFileURL(HERE + 'spec/' + key + '.mjs').href + '?' + Date.now())).default;
const [lat0, lon0] = sp.center, K = Math.cos(lat0 * Math.PI / 180);
const xy = ([lat, lon]) => [(lon - lon0) * 111320 * K * 100, (lat0 - lat) * 110574 * 100];   // cm, x east, y south
const inside = (g) => g.every((p) => Math.hypot(...xy(p)) <= sp.r * 100);

if (process.argv.includes('--fetch')) {
  const q = '[out:json][timeout:90];(way(' + sp.fetch.join(',') + ');way(' + sp.citadel + ');way(' + sp.hatch + '););out geom tags;';
  const r = await fetch(process.env.OVERPASS || 'https://overpass.private.coffee/api/interpreter', { method: 'POST',
    headers: { 'User-Agent': 'journal-drawing-script', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'data=' + encodeURIComponent(q) });
  if (!r.ok) throw new Error('Overpass: ' + r.status);
  const j = await r.json(), keep = [sp.citadel, sp.hatch, ...sp.over];
  const ways = j.elements.filter((e) => e.geometry && (keep.includes(e.id) || (e.tags?.building && inside(e.geometry.map((p) => [p.lat, p.lon])))))
    .map((e) => ({ id: e.id, b: e.tags?.building ? 1 : 0, g: e.geometry.map((p) => [+p.lat.toFixed(7), +p.lon.toFixed(7)]) }));
  fs.mkdirSync(HERE + 'osm', { recursive: true });
  fs.writeFileSync(HERE + 'osm/' + key + '.json', JSON.stringify({ source: '© OpenStreetMap contributors, ODbL 1.0, openstreetmap.org/copyright', osm_base: j.osm3s?.timestamp_osm_base, ways }));
}

const { ways, osm_base } = JSON.parse(fs.readFileSync(HERE + 'osm/' + key + '.json', 'utf8'));
const r1 = (v) => Math.round(v * 10) / 10, fs8 = sp.fs;
const path = (g, close = true) => g.map((p, i) => (i ? 'L' : 'M') + xy(p).map(r1).join(' ')).join('') + (close ? 'Z' : '');
const byId = (id) => ways.find((w) => w.id === id);
// a building is the bazaar's when its middle lies within the hatched outline
const hatchPts = byId(sp.hatch).g.map(xy);
const within = (pt, poly) => { let c = false; for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) { const [xi, yi] = poly[i], [xj, yj] = poly[j]; if ((yi > pt[1]) !== (yj > pt[1]) && pt[0] < (xj - xi) * (pt[1] - yi) / (yj - yi) + xi) c = !c; } return c; };
const mid = (g) => { const p = g.map(xy); return [p.reduce((s, q) => s + q[0], 0) / p.length, p.reduce((s, q) => s + q[1], 0) / p.length]; };
const blds = ways.filter((w) => w.b && w.id !== sp.citadel);
const ours = blds.filter((w) => within(mid(w.g), hatchPts));

const d = { axis: '', bubble: '', ctx: '', wall: '', fix: '', stair: '', over: '', leaf: '', open: '', glass: '', arrow: '', dim: '', north: '', scale: '' };
d.wall = path(byId(sp.citadel).g) + ours.map((w) => path(w.g)).join('');
d.ctx = blds.filter((w) => !ours.includes(w)).map((w) => path(w.g)).join('');
d.over = sp.over.map((id) => path(byId(id).g)).join('');
const court = path(byId(sp.hatch).g);
const text = sp.labels.map(([lat, lon, v, cls]) => [...xy([lat, lon]).map(r1), cls, v, 'middle']);

// below the round edge of what is kept: a north point and a 100 m bar below it
const R = sp.r * 100, NR = fs8 * 1.3, NP = [R - NR, R + fs8 * 2.2], sy = R + fs8 * 2.2;
d.north = 'M' + r1(NP[0] + NR) + ' ' + NP[1] + 'A' + NR + ' ' + NR + ' 0 1 0 ' + r1(NP[0] - NR) + ' ' + NP[1] + 'A' + NR + ' ' + NR + ' 0 1 0 ' + r1(NP[0] + NR) + ' ' + NP[1]
  + 'M' + NP[0] + ' ' + r1(NP[1] + NR) + 'V' + r1(NP[1] - NR * 1.05) + 'L' + r1(NP[0] - NR * .4) + ' ' + r1(NP[1] - NR * .35) + 'M' + NP[0] + ' ' + r1(NP[1] - NR * 1.05) + 'L' + r1(NP[0] + NR * .4) + ' ' + r1(NP[1] - NR * .35);
text.push([NP[0], r1(NP[1] - NR * 1.5), 'p-dim', 'N', 'middle']);
const x0 = -R;
d.scale = 'M' + x0 + ' ' + sy + 'H' + (x0 + 10000);
for (let i = 0; i <= 5; i++) d.scale += 'M' + (x0 + i * 2000) + ' ' + r1(sy - (i % 5 ? fs8 * .3 : fs8 * .5)) + 'V' + r1(sy + (i % 5 ? fs8 * .3 : fs8 * .5));
text.push([x0, r1(sy + fs8 * 1.6), 'p-dim', '0', 'middle'], [x0 + 10000, r1(sy + fs8 * 1.6), 'p-dim', '100 m', 'middle']);
text.push([x0 + 10000 + fs8 * 2, r1(sy + fs8 * .35), 'p-dim', '© OpenStreetMap contributors', 'start']);
const vb = [-R - fs8, -R - fs8, 2 * R + fs8 * 2, 2 * R + fs8 * 5.6].map(r1);

const out = { vb, fs: fs8, W: 2 * R, H: 2 * R, north: null, np: NP.map(r1), nr: r1(NR), wallH: 0, d, court, title: [x0, r1(sy + fs8 * 1.6)], text, areas: [] };
fs.mkdirSync(HERE + 'out', { recursive: true });
fs.writeFileSync(HERE + 'out/' + key + '.json', JSON.stringify(out));
fs.writeFileSync(HERE + 'anno/' + key + '.json', JSON.stringify({ map: true }));
console.log(key, blds.length, 'buildings,', ours.length, 'in the bazaar; OSM as of', osm_base);
