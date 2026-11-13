// Drawing a laid-out page in the browser, and the media it shows: preview
// images served from the private disk, and the logos recoloured to suit the page.
import { html } from 'htm-preact';
import { baseline, cssFamily } from '@pm/text';
import { northShape } from '@pm/layout';
import { tilesFor, tileUrl } from '@pm/map';

/**
 * Where files are fetched from, and the logos as single-colour artwork:
 * a logo's ink is kept as a mask (trimmed of its empty border), then filled
 * with whatever colour the page asks for.
 */
export function createMedia(boot, assets, changed) {
    const masks = new Map();   // hash → { canvas, ratio } | promise
    const tinted = new Map();  // "hash|color" → object URL
    const url = (hash, variant, page = 0) => boot.urls.file.replace('__HASH__', hash).replace('__VARIANT__', variant) + (page ? `?page=${page}` : '');

    async function mask(hash) {
        if (masks.has(hash)) return masks.get(hash);
        const job = (async () => {
            const blob = await (await fetch(url(hash, 'original'))).blob();
            const bmp = await createImageBitmap(blob);
            const sc = Math.min(1, 2400 / bmp.width);
            const c = document.createElement('canvas');
            c.width = Math.round(bmp.width * sc); c.height = Math.round(bmp.height * sc);
            const g = c.getContext('2d', { willReadFrequently: true });
            g.drawImage(bmp, 0, 0, c.width, c.height);
            const px = g.getImageData(0, 0, c.width, c.height), d = px.data;
            let opaque = true;
            for (let i = 3; i < d.length; i += 4) if (d[i] < 250) { opaque = false; break; }
            let x0 = c.width, y0 = c.height, x1 = 0, y1 = 0;
            for (let i = 0, p = 0; i < d.length; i += 4, p++) {
                // on a white ground the ink is what is dark; on a clear one, what is there
                const a = opaque ? 255 - (d[i] * 0.299 + d[i + 1] * 0.587 + d[i + 2] * 0.114) : d[i + 3];
                d[i] = d[i + 1] = d[i + 2] = 255; d[i + 3] = a;
                if (a > 12) { const x = p % c.width, y = (p / c.width) | 0; if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
            }
            g.putImageData(px, 0, 0);
            const w = Math.max(1, x1 - x0 + 1), h = Math.max(1, y1 - y0 + 1);
            const out = document.createElement('canvas');
            out.width = w; out.height = h;
            out.getContext('2d').drawImage(c, x0, y0, w, h, 0, 0, w, h);
            const done = { canvas: out, ratio: w / h };
            masks.set(hash, done);
            return done;
        })();
        masks.set(hash, job);
        return job;
    }
    async function tint(hash, color) {
        const m = await mask(hash);
        const c = document.createElement('canvas');
        c.width = m.canvas.width; c.height = m.canvas.height;
        const g = c.getContext('2d');
        g.fillStyle = color; g.fillRect(0, 0, c.width, c.height);
        g.globalCompositeOperation = 'destination-in';
        g.drawImage(m.canvas, 0, 0);
        return new Promise((resolve) => c.toBlob(resolve, 'image/png'));
    }

    return {
        url,
        /** Make every logo's mask before the first layout, so the layout knows its true proportions. */
        prepare: (hashes) => Promise.all(hashes.filter(Boolean).map((h) => mask(h).catch(() => null))),
        logoRatio(hash) {
            const m = masks.get(hash);
            if (m && m.ratio) return m.ratio;
            const a = assets().get(hash);
            return a && a.meta.w ? a.meta.w / a.meta.h : 3;
        },
        /** A logo in a colour, for an <img>; null until it has been made (the page is then drawn again). */
        logoUrl(hash, color) {
            const id = `${hash}|${color}`;
            if (!tinted.has(id)) {
                tinted.set(id, null);
                tint(hash, color).then((blob) => { tinted.set(id, URL.createObjectURL(blob)); changed(); }, () => {});
            }
            return tinted.get(id);
        },
        /** The same, as PNG bytes for the PDF. */
        logoPng: async (hash, color) => new Uint8Array(await (await tint(hash, color)).arrayBuffer()),
        /** The address of a map style's tiles ({z}/{x}/{y}); null when the map service has no key. */
        tiles: (style) => (boot.maps || {})[style] || null,
    };
}

const px = (n) => `${n}px`;

function Text({ it }) {
    return it.lines.map((line) => {
        const base = line.top + baseline(it.fontKey, it.size, it.lh);
        return line.runs.map((r) => html`<span class="pm-t" dir=${r.rtl ? 'rtl' : 'ltr'} style=${{
            left: px(r.x), top: px(base - baseline(r.key, it.size, it.lh)), color: it.color, opacity: it.opacity,
            font: `${it.size}px/${it.lh}px ${cssFamily(r.key)}`, letterSpacing: r.ls ? px(r.ls) : null,
        }}>${r.text}</span>`);
    });
}

function Item({ it, media, small, scale }) {
    switch (it.t) {
        case 'text': return html`<${Text} it=${it} />`;
        case 'rect': return html`<div class="pm-a" style=${{ left: px(it.x), top: px(it.y), width: px(it.w), height: px(it.h), background: it.fill, opacity: it.opacity }}></div>`;
        case 'line': {
            const horiz = it.y1 === it.y2;
            return html`<div class="pm-a" style=${{
                left: px(horiz ? it.x1 : it.x1 - it.lw / 2), top: px(horiz ? it.y1 - it.lw / 2 : it.y1),
                width: px(horiz ? it.x2 - it.x1 : it.lw), height: px(horiz ? it.lw : it.y2 - it.y1), background: it.color,
            }}></div>`;
        }
        case 'image': return html`<div class="pm-a pm-img" style=${{ left: px(it.x), top: px(it.y), width: px(it.w), height: px(it.h) }}>
            <img src=${media.url(it.hash, small ? 'thumb' : 'preview', it.pdfPage)} draggable=${false} alt=""
                style=${{ left: px(it.dx - it.x), top: px(it.dy - it.y), width: px(it.dw), height: px(it.dh) }} />
        </div>`;
        case 'logo': {
            const src = it.color ? media.logoUrl(it.hash, it.color) : media.url(it.hash, 'preview');
            return src && html`<img class="pm-a" src=${src} draggable=${false} alt="" style=${{ left: px(it.x), top: px(it.y), width: px(it.w), height: px(it.h) }} />`;
        }
        case 'map': {
            const tpl = media.tiles(it.style);
            return html`<div class="pm-a pm-img pm-map" style=${{ left: px(it.x), top: px(it.y), width: px(it.w), height: px(it.h) }}>
                ${tpl ? tilesFor(it, scale * (window.devicePixelRatio || 1)).map((t) => html`<img src=${tileUrl(tpl, t)} draggable=${false} alt="" key=${`${t.z}/${t.x}/${t.y}`}
                    style=${{ left: px(t.left), top: px(t.top), width: px(t.size + 0.4), height: px(t.size + 0.4) }} />`)
        : html`<span>The map service has no key (CARTO_BASEMAPS_KEY)</span>`}
            </div>`;
        }
        case 'path': {
            const c = it.clip;
            return html`<svg class="pm-a" viewBox=${`${c.x} ${c.y} ${c.w} ${c.h}`} style=${{ left: px(c.x), top: px(c.y), width: px(c.w), height: px(c.h), overflow: 'hidden' }}>
                <path d=${it.d} fill=${it.fill || 'none'} fill-opacity=${it.opacity} stroke=${it.stroke} stroke-width=${it.lw} stroke-linejoin="round" />
            </svg>`;
        }
        case 'ph': return html`<div class="pm-a pm-ph" style=${{ left: px(it.x), top: px(it.y), width: px(it.w), height: px(it.h) }}>
            <span style=${{ fontSize: px(Math.max(9, Math.min(it.w, it.h) * 0.06)) }}>${it.label}</span>
        </div>`;
        case 'north': {
            const s = northShape(it.angle);
            return html`<svg class="pm-a" viewBox="0 0 100 100" style=${{ left: px(it.x), top: px(it.y), width: px(it.size), height: px(it.size) }}>
                <path d=${s.ring} fill="none" stroke=${it.color} stroke-width="1.6" />
                <path d=${s.arrow} fill=${it.color} />
                <path d=${s.n} fill="none" stroke=${it.color} stroke-width="2.4" />
            </svg>`;
        }
        case 'wm': return html`<div class="pm-a pm-wm" style=${{
            left: px(it.cx - it.w / 2), top: px(it.cy - it.size / 2), width: px(it.w), height: px(it.size),
            transform: `rotate(${-it.angle}deg)`, opacity: it.opacity,
        }}>${it.runs.map((r) => html`<span class="pm-t" style=${{
            left: px(r.x), top: px(baseline(it.fontKey, it.size, it.size) - baseline(r.key, it.size, it.size)),
            font: `${it.size}px/${it.size}px ${cssFamily(r.key)}`, letterSpacing: r.ls ? px(r.ls) : null, color: it.color,
        }}>${r.text}</span>`)}</div>`;
        default: return null;
    }
}

/** A page at a scale. `small` uses thumbnails for its images (the page list). */
export function PageView({ lay, scale, media, small }) {
    return html`<div class="pm-page" style=${{ width: px(lay.w * scale), height: px(lay.h * scale) }}>
        <div class="pm-sheet" style=${{ width: px(lay.w), height: px(lay.h), transform: `scale(${scale})`, background: lay.bg }}>
            ${lay.items.map((it) => html`<${Item} it=${it} media=${media} small=${small} scale=${scale} />`)}
        </div>
    </div>`;
}
