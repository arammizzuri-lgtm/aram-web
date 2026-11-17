// node tools/heritage/poster-pdf.mjs
// The heritage poster as a PDF to download, in English, Kurdish and Arabic: the poster page
// (/kurdistan-heritage/poster) printed at A2 landscape with its colours.
// -> public/downloads/heritage-poster-<lang>.pdf, which the poster page links to when they exist.
// Needs the site running locally (php artisan serve --port=8000) and Chrome.
// Run it again after a landmark is added, moved or renamed. The sheet points at the live site, not localhost.
import { chromium } from 'playwright';
import { fileURLToPath } from 'node:url';
import { mkdirSync, statSync } from 'node:fs';
const HERE = fileURLToPath(new URL('.', import.meta.url));
const SITE = 'http://127.0.0.1:8000';
const OUT = HERE + '../../public/downloads/';
mkdirSync(OUT, { recursive: true });

const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage();
for (const lang of ['en', 'ku', 'ar']) {
  const r = await p.goto(SITE + (lang === 'en' ? '' : '/' + lang) + '/kurdistan-heritage/poster', { waitUntil: 'networkidle' });
  if (r.status() !== 200) throw new Error(lang + ': ' + r.status());
  await p.evaluate(() => document.fonts.ready);
  const file = OUT + 'heritage-poster-' + lang + '.pdf';
  await p.pdf({ path: file, preferCSSPageSize: true, printBackground: true });
  console.log(file, Math.round(statSync(file).size / 1024) + ' KB');
}
await b.close();
