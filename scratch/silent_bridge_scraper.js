const { chromium } = require('playwright');
const fs = require('fs');

const KECAMATAN_LIST = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", 
    "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", 
    "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", 
    "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", 
    "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"
];

async function runExtremeBridgeScraper() {
    console.log("--- STARTING EXTREME SILENT BRIDGE SCRAPER ---");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 1024 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    const page = await context.newPage();
    const allResults = [];
    const seen = new Set();

    for (const kecamatan of KECAMATAN_LIST) {
        // Coba 2 query berbeda per kecamatan untuk menjaring jembatan kecil
        const queries = [
            `Jembatan di ${kecamatan}, Majalengka`,
            `Jembatan Sungai di ${kecamatan}, Majalengka`
        ];

        console.log(`Extreme Scrape in ${kecamatan}...`);

        for (const query of queries) {
            try {
                await page.goto(`https://www.google.com/maps/search/${encodeURIComponent(query)}`);
                await page.waitForTimeout(4000);

                // EXPLICIT ELEMENT SCROLLING (THE CORE FIX)
                const feedSelector = 'div[role="feed"]';
                if (await page.$(feedSelector)) {
                    for (let i = 0; i < 15; i++) {
                        await page.evaluate((sel) => {
                            const el = document.querySelector(sel);
                            if (el) el.scrollTop = el.scrollHeight;
                        }, feedSelector);
                        await page.waitForTimeout(1000);
                    }
                }

                // Extract all valid results
                const items = await page.$$eval('a.hfpxzc', links => 
                    links.map(l => ({ name: l.getAttribute('aria-label'), href: l.getAttribute('href') }))
                );

                let added = 0;
                for (const item of items) {
                    if (!item.name || !item.href || seen.has(item.name)) continue;
                    
                    const coordMatch = item.href.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
                    if (coordMatch) {
                        allResults.push({
                            nama: item.name,
                            kecamatan: kecamatan,
                            latitude: parseFloat(coordMatch[1]),
                            longitude: parseFloat(coordMatch[2])
                        });
                        seen.add(item.name);
                        added++;
                    }
                }
                console.log(`   Query [${query}]: +${added} items.`);
            } catch (e) {
                console.log(`   Error in query: ${e.message}`);
            }
        }
        
        console.log(`   Total for ${kecamatan}: ${seen.size} unique structures found so far.`);
        fs.writeFileSync('data/listjembatanmajalengka.json', JSON.stringify(allResults, null, 2));
        await page.waitForTimeout(2000);
    }

    await browser.close();
    console.log(`--- EXTREME SCRAPING FINISHED! Total global: ${allResults.length} ---`);
}

runExtremeBridgeScraper();
