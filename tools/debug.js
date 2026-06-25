const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 860 });

  const errors = [];
  const logs = [];
  page.on('console', msg => logs.push(msg.type() + ': ' + msg.text()));
  page.on('pageerror', err => errors.push(err.message));
  page.on('requestfailed', req => errors.push('FETCH FAIL: ' + req.url() + ' -> ' + req.failure().errorText));

  await page.goto('file:///C:/Users/Ram%20Computer/aram-mizuri-arch/index.html');
  await page.waitForTimeout(8000);

  console.log('=== ERRORS ===');
  errors.forEach(e => console.log(e));
  console.log('=== LOGS ===');
  logs.slice(-10).forEach(l => console.log(l));

  await browser.close();
})();
