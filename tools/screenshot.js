const { chromium } = require('playwright');
const http = require('http');
const fs   = require('fs');
const path = require('path');
const root = 'C:\\Users\\Ram Computer\\aram-mizuri-arch';

const TYPES = { '.html':'text/html','.css':'text/css','.js':'application/javascript',
                '.png':'image/png','.jpg':'image/jpeg','.svg':'image/svg+xml',
                '.json':'application/json','.woff2':'font/woff2','.geojson':'application/json' };

const server = http.createServer((req, res) => {
  let fp = path.join(root, decodeURIComponent(req.url).split('?')[0] || '/index.html');
  if (!fs.existsSync(fp) || fs.statSync(fp).isDirectory()) fp = path.join(root, 'index.html');
  const ext = path.extname(fp);
  res.setHeader('Content-Type', TYPES[ext] || 'application/octet-stream');
  fs.createReadStream(fp).pipe(res);
});

server.listen(7999, '127.0.0.1', async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 860 });

  page.on('pageerror', e => console.log('ERR:', e.message));

  await page.goto('http://127.0.0.1:7999/index.html', { waitUntil: 'domcontentloaded' });

  // Wait for loader to finish (it becomes display:none)
  try {
    await page.waitForFunction(() => {
      const loader = document.getElementById('loader');
      return !loader || loader.style.display === 'none' || loader.style.opacity === '0' ||
             getComputedStyle(loader).opacity === '0';
    }, { timeout: 8000 });
  } catch { console.log('Loader did not hide — forcing scroll anyway'); }

  // Scroll to map section
  await page.evaluate(() => {
    const el = document.getElementById('map');
    if (el) el.scrollIntoView({ behavior: 'instant' });
  });

  // Wait for the Kurdistan border path to appear
  try {
    await page.waitForFunction(() =>
      document.querySelectorAll('#projectMapEl .leaflet-overlay-pane path').length > 0,
      { timeout: 8000 }
    );
    console.log('Border visible');
  } catch { console.log('Border timeout'); }

  await page.waitForTimeout(1200);
  await page.screenshot({ path: root + '\\map-screenshot.png' });
  console.log('done');
  await browser.close();
  server.close();
});
