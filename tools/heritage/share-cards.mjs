// node tools/heritage/share-cards.mjs [slug ...]
// The link preview of each landmark page, in English, Kurdish and Arabic:
// its cover photograph at 1200 x 630 with its name, place and age, the photo's
// credit (the licences ask for it) and the studio's mark.
// -> public/images/heritage/og/<slug>-<lang>.jpg, which Landmark::shareImage() serves.
// Needs the site running locally (php artisan serve --port=8000) and Chrome.
// Run it again after changing a landmark's name, place, age or cover.
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import { mkdirSync, readFileSync } from 'node:fs';
const HERE = fileURLToPath(new URL('.', import.meta.url));
const SITE = 'http://127.0.0.1:8000';
const OUT = HERE + '../../public/images/heritage/og/';
mkdirSync(OUT, { recursive: true });
const mark = readFileSync(HERE + '../../public/favicon.svg', 'utf8');

const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });

let slugs = process.argv.slice(2);
if (!slugs.length) {
  await p.goto(SITE + '/kurdistan-heritage', { waitUntil: 'domcontentloaded' });
  slugs = await p.evaluate(() => [...new Set([...document.querySelectorAll('a[href*="/kurdistan-heritage/"]')]
    .map(a => new URL(a.href).pathname.split('/').pop()).filter(Boolean))]);
}

for (const slug of slugs) {
  for (const lang of ['en', 'ku', 'ar']) {
    const r = await p.goto(SITE + (lang === 'en' ? '' : '/' + lang) + '/kurdistan-heritage/' + slug, { waitUntil: 'domcontentloaded' });
    if (r.status() !== 200) { console.log('skip', slug, lang, r.status()); continue; }
    const ok = await p.evaluate(async (mark) => {
      const q = s => document.querySelector(s);
      const txt = s => (q(s)?.textContent || '').replace(/\s+/g, ' ').trim();
      const graph = JSON.parse(q('script[type="application/ld+json"]').textContent)['@graph'];
      const place = graph.find(g => g['@type'] === 'LandmarksOrHistoricalBuildings');
      const crumbs = graph.find(g => g['@type'] === 'BreadcrumbList').itemListElement;
      const cover = place.image;   // the cover photograph (og:image is this card once it exists)
      if (!cover) return false;    // no cover: the page keeps the site's default preview
      // the cover's credit, as the page's structured data gives it, after the page's word for "Photo"
      const shot = (graph.find(g => g['@type'] === 'Article').image || []).find(i => i.contentUrl === cover);
      const word = (q('.lm-credit')?.textContent || 'Photo:').split(':')[0].trim();
      const d = {
        name: place.name, meta: [...document.querySelectorAll('.lm-meta li:not(.lm-meta__unesco)'), q('.lm-hero__big')].filter(Boolean).map(e => e.textContent.trim()).join(' · '),
        label: crumbs[1].name, credit: shot?.creditText ? `${word}: ${shot.creditText}` : '',
        brand: txt('.pp-brand__name'), sub: txt('.pp-brand__sub'),
      };
      const rtl = document.documentElement.dir === 'rtl';
      const font = rtl ? "'Vazirmatn', 'Inter', sans-serif" : "'Inter', sans-serif";
      const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
      const card = document.createElement('div');
      card.dir = rtl ? 'rtl' : 'ltr';
      card.innerHTML = `
        <style>
          .sc { position: fixed; inset: 0; width: 1200px; height: 630px; z-index: 99999; overflow: hidden; background: #090909; color: #fff; font-family: ${font}; }
          .sc img.bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
          .sc .shade { position: absolute; inset: 0; background:
              linear-gradient(to top, rgba(9,9,9,.92) 0%, rgba(9,9,9,.6) 38%, rgba(9,9,9,0) 70%),
              linear-gradient(to bottom, rgba(9,9,9,.75) 0%, rgba(9,9,9,.35) 18%, rgba(9,9,9,0) 34%); }
          .sc .top { position: absolute; top: 44px; inset-inline: 60px; display: flex; justify-content: space-between; align-items: center; }
          .sc .label { font-size: ${rtl ? 21 : 15}px; letter-spacing: ${rtl ? 0 : '.32em'}; text-transform: uppercase; color: #F5C518; font-weight: 500; }
          .sc .brand { display: flex; align-items: center; gap: 14px; }
          .sc .brand svg { width: 46px; height: 46px; }
          .sc .brand b { display: block; font-size: ${rtl ? 21 : 16}px; font-weight: 500; letter-spacing: ${rtl ? 0 : '.22em'}; text-transform: uppercase; }
          .sc .brand small { display: block; margin-top: 3px; font-size: ${rtl ? 15 : 11}px; letter-spacing: ${rtl ? 0 : '.26em'}; text-transform: uppercase; color: rgba(255,255,255,.7); }
          .sc .foot { position: absolute; bottom: 52px; inset-inline: 60px; }
          .sc .rule { width: 64px; height: 2px; background: #F5C518; margin-bottom: 22px; }
          .sc h1 { margin: 0; font-size: 68px; line-height: 1.08; font-weight: ${rtl ? 500 : 300}; letter-spacing: ${rtl ? 0 : '-.01em'}; max-width: 1000px; text-wrap: balance; }
          .sc .meta { margin-top: 16px; font-size: ${rtl ? 25 : 22}px; font-weight: 300; color: rgba(255,255,255,.82); }
          .sc .credit { position: absolute; bottom: 18px; inset-inline-end: 24px; max-width: 640px; font-size: 12px; color: rgba(255,255,255,.62); unicode-bidi: plaintext; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Inter', sans-serif; }
        </style>
        <div class="sc">
          <img class="bg" src="${esc(cover)}" alt="">
          <div class="shade"></div>
          <div class="top">
            <span class="label">${esc(d.label)}</span>
            <span class="brand">${mark}<span><b>${esc(d.brand)}</b><small>${esc(d.sub)}</small></span></span>
          </div>
          <div class="foot">
            <div class="rule"></div>
            <h1>${esc(d.name)}</h1>
            <p class="meta">${esc(d.meta)}</p>
          </div>
          ${d.credit ? `<div class="credit">${esc(d.credit)}</div>` : ''}
        </div>`;
      document.body.append(card);
      for (const el of document.body.children) if (el !== card) el.style.display = 'none';
      const h1 = card.querySelector('h1');   // a long name steps down until it fits on two lines
      for (let s = 68; h1.offsetHeight > s * 1.08 * 2 + 4 && s > 40; s -= 4) h1.style.fontSize = (s - 4) + 'px';
      await card.querySelector('img').decode();
      await document.fonts.ready;
      return true;
    }, mark);
    if (!ok) { console.log('no cover', slug, lang); continue; }
    await p.waitForTimeout(150);
    await p.screenshot({ path: `${OUT}${slug}-${lang}.jpg`, type: 'jpeg', quality: 74 });
  }
  console.log('ok', slug);
}
await b.close();
