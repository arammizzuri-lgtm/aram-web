// node og.mjs <slug>: the essay's link preview, its /journal card cover widened to 1200 x 630, one JPG per language
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
const HERE = fileURLToPath(new URL('.', import.meta.url));   // this folder, ending in a separator

const slug = process.argv[2];
const dir = HERE + '../../public/images/journal/';
(await import('node:fs')).mkdirSync(dir, { recursive: true });
const b = await chromium.launch({ channel: 'chrome' });
for (const lang of ['en', 'ku', 'ar']) {
  const p = await b.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
  await p.goto('http://127.0.0.1:8000' + (lang === 'en' ? '' : '/' + lang) + '/journal', { waitUntil: 'networkidle' });
  await p.evaluate(async (slug) => {
    const svg = document.querySelector(`a.jr-card[href$="/journal/${slug}"] .jr-cover`);
    // the sheet is 1600 x 1000; widen the view to 1.905 : 1 and carry the grid out to the edges
    svg.setAttribute('viewBox', '-152.4 0 1904.8 1000');
    const grid = svg.querySelector('rect'); grid.setAttribute('x', -200); grid.setAttribute('width', 2000);
    svg.style.cssText = 'position:fixed;inset:0;width:1200px;height:630px;transition:none;transform:none;background:#0e0e0e;z-index:99999';
    document.body.append(svg);
    for (const el of document.body.children) if (el !== svg) el.style.display = 'none';
    await document.fonts.ready;
  }, slug);
  await p.waitForTimeout(400);
  await p.screenshot({ path: `${dir}${slug}-${lang}.jpg`, type: 'jpeg', quality: 86 });
  await p.close();
}
await b.close();
console.log('ok');
