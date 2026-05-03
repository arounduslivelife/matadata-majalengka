const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    console.log('Navigating to JAGA.id...');
    try {
        const start = Date.now();
        await page.goto('https://jaga.id', { waitUntil: 'domcontentloaded', timeout: 30000 });
        console.log(`Loaded JAGA.id in ${Date.now() - start}ms`);
        console.log('Title:', await page.title());
    } catch (e) {
        console.error('Error:', e.message);
    }
    await browser.close();
})();
