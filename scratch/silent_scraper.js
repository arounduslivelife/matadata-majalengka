const { chromium } = require('playwright');
const fs = require('fs');

const KECAMATAN_LIST = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", 
    "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", 
    "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", 
    "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", 
    "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"
];

async function runSilentScraper() {
    console.log("--- STARTING SILENT NODE SCRAPER ---");
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    const allResults = [];

    for (const kecamatan of KECAMATAN_LIST) {
        console.log(`Processing ${kecamatan}...`);
        const query = `Sekolah di ${kecamatan}, Majalengka`;
        
        try {
            await page.goto(`https://www.google.com/maps/search/${encodeURIComponent(query)}`);
            await page.waitForTimeout(4000);

            // Fast Scrolling
            for (let i = 0; i < 5; i++) {
                await page.mouse.wheel(0, 3000);
                await page.waitForTimeout(800);
            }

            // Extract links
            const items = await page.$$eval('a[href*="maps/place"]', links => 
                links.map(l => ({ name: l.getAttribute('aria-label'), href: l.getAttribute('href') }))
            );

            let count = 0;
            for (const item of items) {
                if (!item.name || !item.href) continue;
                
                // Regex !3d (Lat) !4d (Lon)
                const match = item.href.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
                if (match) {
                    allResults.push({
                        nama: item.name,
                        kecamatan: kecamatan,
                        latitude: parseFloat(match[1]),
                        longitude: parseFloat(match[2])
                    });
                    count++;
                }
            }
            console.log(`   Done! Found ${count} items.`);
            
            // Incremental save
            fs.writeFileSync('data/listsekolahmajalengka.json', JSON.stringify(allResults, null, 2));

        } catch (e) {
            console.log(`   Error in ${kecamatan}: ${e.message}`);
        }

        // Delay anti-bot
        await page.waitForTimeout(3000 + Math.random() * 2000);
    }

    await browser.close();
    console.log(`--- FINISHED! Total ${allResults.length} schools scraped. ---`);
}

runSilentScraper();
