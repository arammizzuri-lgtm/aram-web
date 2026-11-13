// Maps of the site: a street map (in the template's colours, or in colour) or a
// satellite photograph, made of the map services' square tiles. The layout
// places a map as a box with a centre and a zoom, as web maps count zoom; the
// preview shows the tiles themselves and the PDF gets them drawn into one
// picture. The pin, the plot outline and the places are the layout's own
// shapes on top, so they print sharp. Nothing here but stitch() needs a browser.

export const Q = 0.75;   // points on the page per map pixel: a map printed as it shows on a screen
const EARTH = 6378137;   // metres, as web maps take it

export const MAP_LOOKS = [['template', 'Template'], ['colour', 'Colour'], ['satellite', 'Satellite']];
export const TILESETS = {
    light: { max: 18, px: 512, credit: '© OpenStreetMap contributors © CARTO', short: '© OpenStreetMap © CARTO' },
    dark: { max: 18, px: 512, credit: '© OpenStreetMap contributors © CARTO', short: '© OpenStreetMap © CARTO' },
    voyager: { max: 18, px: 512, credit: '© OpenStreetMap contributors © CARTO', short: '© OpenStreetMap © CARTO' },
    satellite: { max: 19, px: 256, credit: 'Imagery © Esri, Maxar, Earthstar Geographics', short: '© Esri, Maxar' },
};
const luminance = (c) => { const n = parseInt(String(c).slice(1, 7), 16) || 0; return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255; };
/** The tiles a look uses: the template's — a light street map, or a dark one on a dark template —, the coloured one, or the photograph. */
export function mapStyle(look, theme) {
    if (look === 'colour') return 'voyager';
    if (look === 'satellite') return 'satellite';
    return luminance(theme.colors.bg) < 0.5 ? 'dark' : 'light';
}

/** A place on the world map at zoom z, in map pixels from its top-left corner. */
export function world(lat, lng, z) {
    const s = 256 * 2 ** z, r = Math.max(-85, Math.min(85, lat)) * Math.PI / 180;
    return [(lng + 180) / 360 * s, (1 - Math.log(Math.tan(r) + 1 / Math.cos(r)) / Math.PI) / 2 * s];
}
/** The place at a point of the world map at zoom z. */
export function latLng(x, y, z) {
    const s = 256 * 2 ** z;
    return [Math.atan(Math.sinh(Math.PI * (1 - 2 * y / s))) * 180 / Math.PI, x / s * 360 - 180];
}
/** Where a place falls on a map box `m` ({ x, y, w, h, lat, lng, z }), in points on the page. */
export function onMap(m, lat, lng) {
    const [cx, cy] = world(m.lat, m.lng, m.z), [x, y] = world(lat, lng, m.z);
    return [m.x + m.w / 2 + (x - cx) * Q, m.y + m.h / 2 + (y - cy) * Q];
}
export const inside = (m, [x, y], pad = 0) => x >= m.x + pad && x <= m.x + m.w - pad && y >= m.y + pad && y <= m.y + m.h - pad;
/** Metres on the ground per point on the page, at a latitude. */
export const metresPerPt = (lat, z) => 2 * Math.PI * EARTH * Math.cos(lat * Math.PI / 180) / (256 * 2 ** z) / Q;

/** A scale bar no longer than `maxPt`: a round length (1, 2 or 5 × 10ⁿ metres), its length on the page and its label. */
export function scaleBar(lat, z, maxPt) {
    const per = metresPerPt(lat, z), most = per * maxPt;
    let nice = 10 ** Math.floor(Math.log10(most));
    for (const f of [5, 2]) if (nice * f <= most) { nice *= f; break; }
    return { pt: nice / per, label: nice >= 1000 ? `${nice / 1000} km` : `${nice} m` };
}

/** Metres between two places, along the ground. */
export function distance(a, b) {
    const r = Math.PI / 180, dLat = (b.lat - a.lat) * r, dLng = (b.lng - a.lng) * r;
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * r) * Math.cos(b.lat * r) * Math.sin(dLng / 2) ** 2;
    return 2 * EARTH * Math.asin(Math.min(1, Math.sqrt(h)));
}
export const fmtDistance = (m) => (m < 1000 ? `${Math.max(10, Math.round(m / 10) * 10)} m` : `${(m / 1000).toFixed(m < 10000 ? 1 : 0)} km`);
/** The area inside an outline of [lat, lng] corners, in square metres (flat ground — true for a plot). */
export function plotArea(pts) {
    if (pts.length < 3) return 0;
    const r = Math.PI / 180, lat0 = pts[0][0] * r, xy = pts.map(([la, ln]) => [EARTH * ln * r * Math.cos(lat0), EARTH * la * r]);
    let s = 0;
    xy.forEach(([x1, y1], i) => { const [x2, y2] = xy[(i + 1) % xy.length]; s += x1 * y2 - x2 * y1; });
    return Math.abs(s) / 2;
}
export const fmtCoords = (lat, lng) => `${Math.abs(lat).toFixed(5)}° ${lat < 0 ? 'S' : 'N'}, ${Math.abs(lng).toFixed(5)}° ${lng < 0 ? 'W' : 'E'}`;

/**
 * The tiles that cover a map box drawn at `dpp` device pixels per point: each tile's
 * address and its place and size within the box, in points. The zoom of the tiles is the
 * least that is sharp at that size (a map zoomed in past the service's deepest is enlarged).
 */
export function tilesFor(m, dpp) {
    const ts = TILESETS[m.style] || TILESETS.light;
    const tz = Math.max(0, Math.min(ts.max, Math.ceil(m.z + Math.log2(256 * Q * dpp / ts.px) - 0.25)));
    const k = 2 ** (m.z - tz) * Q;   // points per map pixel at the tiles' zoom
    const [cx, cy] = world(m.lat, m.lng, tz), n = 2 ** tz, size = 256 * k, out = [];
    for (let ty = Math.floor((cy - m.h / 2 / k) / 256); ty <= Math.floor((cy + m.h / 2 / k) / 256); ty++) {
        if (ty < 0 || ty >= n) continue;
        for (let tx = Math.floor((cx - m.w / 2 / k) / 256); tx <= Math.floor((cx + m.w / 2 / k) / 256); tx++) {
            out.push({ z: tz, x: ((tx % n) + n) % n, y: ty, left: m.w / 2 + (tx * 256 - cx) * k, top: m.h / 2 + (ty * 256 - cy) * k, size });
        }
    }
    return out;
}
export const tileUrl = (tpl, t) => tpl.replace('{s}', 'abcd'[(t.x + t.y) % 4]).replace('{z}', t.z).replace('{x}', t.x).replace('{y}', t.y).replace('{r}', '@2x');

/** A map box as one picture at `dpp` device pixels per point: its tiles fetched and drawn together (in the browser). */
export async function stitch(m, dpp, tpl) {
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(m.w * dpp)); c.height = Math.max(1, Math.round(m.h * dpp));
    const g = c.getContext('2d');
    g.imageSmoothingQuality = 'high';
    await Promise.all(tilesFor(m, dpp).map(async (t) => {
        const res = await fetch(tileUrl(tpl, t)).catch(() => null);
        if (!res || !res.ok) throw new Error('The map could not be fetched from the map service — check the internet connection and try again.');
        const bmp = await createImageBitmap(await res.blob());
        // whole pixels, a hair over, so no seam shows between tiles
        const x0 = Math.floor(t.left * dpp), y0 = Math.floor(t.top * dpp);
        g.drawImage(bmp, x0, y0, Math.ceil((t.left + t.size) * dpp) - x0, Math.ceil((t.top + t.size) * dpp) - y0);
    }));
    return c;
}
