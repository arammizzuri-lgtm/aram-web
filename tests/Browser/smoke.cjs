/*
 * Post-deploy smoke test: log in and load every screen in a real browser.
 *
 * The PHPUnit suite renders pages through Livewire, which catches server-side
 * breakage but not a broken asset build or a JS error — the two things a deploy
 * is most likely to introduce. This loads each page for real and fails on a
 * non-200 or any uncaught JS error.
 *
 *   CHROME_PATH=/usr/bin/chromium node tests/Browser/smoke.cjs /tmp [base-url]
 *
 * Needs the dev dependencies (puppeteer). Run it from a workstation against the
 * deployed URL, not on the production server.
 */
const puppeteer = require('puppeteer');

const BASE = process.argv[3] || 'http://127.0.0.1:8000';

const PAGES = [
  ['Dashboard', '/admin'],
  ['Crystals price list', '/admin/crystal-price-list'],
  ['Textile', '/admin/catalogue-price-list?section=textile'],
  ['Packaging', '/admin/catalogue-price-list?section=packaging'],
  ['Furniture', '/admin/catalogue-price-list?section=furniture'],
  ['Products', '/admin/products'],
  ['Suppliers', '/admin/suppliers'],
  ['Customers', '/admin/customers'],
  ['Purchase orders', '/admin/purchase-orders'],
  ['Shipments', '/admin/shipments'],
  ['Stock', '/admin/stock-levels'],
  ['Movements', '/admin/stock-movements'],
  ['Invoices', '/admin/invoices'],
  ['Sales orders', '/admin/sales-orders'],
  ['Payments', '/admin/payments'],
  ['Expenses', '/admin/expenses'],
  ['Reports', '/admin/reports'],
  ['Ask (AI)', '/admin/ai-assistant'],
  ['Price list import', '/admin/price-list-import'],
  ['Company profile', '/admin/company-profile'],
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: process.env.CHROME_PATH,
    args: ['--no-sandbox'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1600, height: 1000 });

  const jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push(e.message));

  await page.goto(BASE + '/admin/login', { waitUntil: 'networkidle2' });
  await page.type('#form\\.email', process.env.SMOKE_EMAIL || 'owner@example.com');
  await page.type('#form\\.password', process.env.SMOKE_PASSWORD || 'password');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('button[type=submit]'),
  ]);
  console.log('login ->', page.url());

  const out = process.argv[2];
  let failed = 0;

  for (const [name, path] of PAGES) {
    const before = jsErrors.length;
    let status = '?';
    try {
      const res = await page.goto(BASE + path, { waitUntil: 'networkidle2' });
      status = res.status();
    } catch (e) {
      status = 'ERR ' + e.message.slice(0, 40);
    }
    await new Promise((r) => setTimeout(r, 400));

    const title = await page.$eval('h1', (e) => e.innerText.trim()).catch(() => '(no h1)');
    const errs = jsErrors.length - before;
    const ok = status === 200 && errs === 0;
    if (!ok) failed++;
    console.log(`${ok ? 'OK  ' : 'FAIL'} ${String(status).padEnd(4)} ${name.padEnd(20)} ${title}${errs ? `  [${errs} js errors]` : ''}`);

    if (name === 'Dashboard') await page.screenshot({ path: `${out}/dashboard.png` });
    if (name === 'Reports') await page.screenshot({ path: `${out}/reports.png` });
    if (name === 'Crystals price list') await page.screenshot({ path: `${out}/crystals.png` });
  }

  console.log(`\n${PAGES.length - failed}/${PAGES.length} pages OK`);
  if (jsErrors.length) console.log('JS errors:\n' + [...new Set(jsErrors)].join('\n'));
  await browser.close();
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error('FAILED', e.message); process.exit(1); });
