// Quick headless-browser smoke test for the live site.
// Loads the page from the running local server, waits for the loader to clear,
// scrolls to the map, and reports any console / page errors plus a screenshot.
const { chromium } = require('playwright');

(async () => {
  const URL = 'http://127.0.0.1:8080/index.html';
  const errors = [];

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 860 });

  page.on('pageerror', e => errors.push('PAGEERROR: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push('CONSOLE: ' + m.text()); });

  await page.goto(URL, { waitUntil: 'domcontentloaded' });

  // Wait for the loader overlay to hide
  try {
    await page.waitForFunction(() => {
      const l = document.getElementById('loader');
      return !l || getComputedStyle(l).opacity === '0' || getComputedStyle(l).display === 'none';
    }, { timeout: 8000 });
    console.log('✓ Loader cleared');
  } catch { console.log('✗ Loader did not clear in time'); }

  // Scroll to the map and wait for the Kurdistan border to render
  await page.evaluate(() => document.getElementById('map')?.scrollIntoView());
  try {
    await page.waitForFunction(
      () => document.querySelectorAll('#projectMapEl .leaflet-overlay-pane path').length > 0,
      { timeout: 8000 }
    );
    console.log('✓ Map + Kurdistan border rendered');
  } catch { console.log('✗ Map border did not render'); }

  await page.waitForTimeout(800);
  await page.screenshot({ path: 'smoke-screenshot.png', fullPage: false });
  console.log('✓ Screenshot saved: smoke-screenshot.png');

  console.log(errors.length ? '\n--- ERRORS ---\n' + errors.join('\n') : '\n✓ No page/console errors');
  await browser.close();
})();
