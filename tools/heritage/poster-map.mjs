// node tools/heritage/poster-map.mjs <ne_10m_rivers_lake_centerlines.geojson> <ne_10m_lakes.geojson>
// The base map of the heritage poster (views/heritage/poster): the outline of Greater Kurdistan from the site's
// own map (public/kurdistan-data.js), and the rivers and lakes around it from Natural Earth (public domain,
// naturalearthdata.com; the 10m files are on github.com/nvkelso/natural-earth-vector, geojson/), clipped to the
// poster's frame and thinned to what A2 can show.
// -> resources/heritage/poster-map.json, in longitude/latitude; the page projects it.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));
const [riversFile, lakesFile] = process.argv.slice(2);
if (!riversFile || !lakesFile) { console.error('usage: node tools/heritage/poster-map.mjs <rivers.geojson> <lakes.geojson>'); process.exit(1); }

const TOL = 0.012;   // degrees, about 1.1 km: under half a millimetre on the poster
const r3 = (n) => Math.round(n * 1000) / 1000;

// Douglas–Peucker, in plain degrees (good enough at this scale)
function thin(pts, tol = TOL) {
  if (pts.length < 3) return pts;
  const [ax, ay] = pts[0], [bx, by] = pts[pts.length - 1];
  const dx = bx - ax, dy = by - ay, len = Math.hypot(dx, dy) || 1e-12;
  let far = 0, at = 0;
  for (let i = 1; i < pts.length - 1; i++) {
    const d = Math.abs(dy * pts[i][0] - dx * pts[i][1] + bx * ay - by * ax) / len;
    if (d > far) { far = d; at = i; }
  }
  return far > tol ? [...thin(pts.slice(0, at + 1), tol).slice(0, -1), ...thin(pts.slice(at), tol)] : [pts[0], pts[pts.length - 1]];
}
const round = (pts) => pts.map(([x, y]) => [r3(x), r3(y)]);
// a closed ring starts and ends on the same point, so cut it in two at the point farthest from its start
function thinRing(ring, tol = TOL) {
  let at = 0, far = 0;
  ring.forEach(([x, y], i) => { const d = Math.hypot(x - ring[0][0], y - ring[0][1]); if (d > far) { far = d; at = i; } });
  return [...thin(ring.slice(0, at + 1), tol).slice(0, -1), ...thin(ring.slice(at), tol)];
}

// the outline: the site's map, its outer rings, without the specks
const src = readFileSync(HERE + '../../public/kurdistan-data.js', 'utf8');
const geo = JSON.parse(src.slice(src.indexOf("JSON.parse('") + 12, src.lastIndexOf("')")));
const outline = geo.geometry.coordinates.map((poly) => poly[0]).filter((ring) => ring.length > 40).map((ring) => round(thinRing(ring))).filter((ring) => ring.length > 6);
const all = outline.flat();
const pad = 0.6;
const box = [Math.min(...all.map((p) => p[0])) - pad, Math.min(...all.map((p) => p[1])) - pad, Math.max(...all.map((p) => p[0])) + pad, Math.max(...all.map((p) => p[1])) + pad];
const inBox = ([x, y]) => x >= box[0] && x <= box[2] && y >= box[1] && y <= box[3];

// rivers: each line cut where it leaves the frame
const rivers = [];
for (const f of JSON.parse(readFileSync(riversFile, 'utf8')).features) {
  if (!f.geometry || f.properties.scalerank > 9) continue;
  const lines = f.geometry.type === 'LineString' ? [f.geometry.coordinates] : f.geometry.coordinates;
  for (const line of lines) {
    let run = [];
    for (const p of line) {
      if (inBox(p)) { run.push(p); continue; }
      if (run.length > 1) rivers.push({ name: f.properties.name_en || f.properties.name, line: round(thin(run)) });
      run = [];
    }
    if (run.length > 1) rivers.push({ name: f.properties.name_en || f.properties.name, line: round(thin(run)) });
  }
}

// lakes and reservoirs whose middle is in the frame
const lakes = [];
for (const f of JSON.parse(readFileSync(lakesFile, 'utf8')).features) {
  if (!f.geometry) continue;
  const polys = f.geometry.type === 'Polygon' ? [f.geometry.coordinates] : f.geometry.coordinates;
  for (const poly of polys) {
    const ring = poly[0];
    const mid = [ring.reduce((s, p) => s + p[0], 0) / ring.length, ring.reduce((s, p) => s + p[1], 0) / ring.length];
    if (inBox(mid) && ring.length > 8) lakes.push({ name: f.properties.name_en || f.properties.name, ring: round(thinRing(ring, TOL / 2)) });
  }
}

// self-check: the outline is closed and the frame holds it
console.assert(outline.every((r) => r.length > 3), 'outline rings survive thinning');
console.assert(outline.flat().every(inBox), 'the frame holds the outline');

mkdirSync(HERE + '../../resources/heritage', { recursive: true });
const out = { source: 'Outline: the site map (public/kurdistan-data.js). Rivers and lakes: Natural Earth 1:10m, public domain.', box: box.map(r3), outline, rivers, lakes };
writeFileSync(HERE + '../../resources/heritage/poster-map.json', JSON.stringify(out));
console.log('outline', outline.length, 'rings', outline.flat().length, 'points; rivers', rivers.length, 'lines; lakes', lakes.map((l) => l.name).join(', '));
