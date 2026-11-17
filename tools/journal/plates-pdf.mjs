// node plates-pdf.mjs <lang> <out.pdf>: the essay's plates printed as the button prints them
import { chromium } from 'playwright';

const [lang, out, slug = 'citadel-house-plans'] = process.argv.slice(2);
const b = await chromium.launch({ channel: 'chrome' });
const p = await b.newPage();
await p.goto('http://127.0.0.1:8000' + (lang === 'en' ? '' : '/' + lang) + '/journal/' + slug, { waitUntil: 'networkidle' });
await p.evaluate(() => document.documentElement.classList.add('ja-printing'));
await p.emulateMedia({ media: 'print' });
await p.pdf({ path: out, preferCSSPageSize: true });
console.log('ok');
await b.close();
