const { chromium } = require('playwright');
const mysql = require('mysql2/promise');
const fs = require('fs');

async function scrape() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    const targetYear = 2025;
    console.log(`--- JAGA.id Dana Desa Scraper (${targetYear}) ---`);
    
    // Process ALL villages to ensure 2025 data is fresh and complete from JAGA.id
    const [villages] = await db.query("SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages");
    console.log(`Processing all ${villages.length} villages for year ${targetYear}.`);

    let successCount = 0;
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    const page = await context.newPage();

    for (let i = 0; i < villages.length; i++) {
        const v = villages[i];
        const kec = parseInt(v.kd_kecamatan).toString().padStart(2, '0');
        const kel = v.kd_kelurahan.padStart(3, '0');
        const code = `3210${kec}2${kel}`;
        
        const url = `https://jaga.id/pelayanan-publik/desa/${code}/${encodeURIComponent(v.nm_kelurahan)}?year=${targetYear}`;
        
        console.log(`[${i+1}/${villages.length}] Processing ${v.nm_kelurahan} (${code}) for 2025...`);

        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
            await page.waitForTimeout(5000); 
            
            const title = await page.title();
            if (title.includes('404') || title.includes('Error')) {
                throw new Error('Page returned 404/Error');
            }

            const menuSelector = 'text=/.*Informasi Penyaluran Dana Desa.*/i';
            await page.waitForSelector(menuSelector, { timeout: 30000 }).catch(() => null);
            
            const menuExists = await page.locator(menuSelector).count();
            if (menuExists > 0) {
                await page.locator(menuSelector).first().click();
                await page.waitForTimeout(10000); 
            }

            // Scrape Pagu (Nilai Anggaran)
            let totalBudget = 0;
            try {
                const paguText = await page.evaluate(() => {
                    const allElements = Array.from(document.querySelectorAll('div, span, p, b, h6'));
                    const label = allElements.find(e => e.innerText.trim() === 'Nilai Anggaran');
                    if (!label) return "";
                    let p = label.parentElement;
                    for (let j = 0; j < 5; j++) {
                        if (p && p.innerText.includes('Rp')) return p.innerText;
                        p = p.parentElement;
                    }
                    return "";
                });
                const match = paguText.match(/Rp\s*([0-9.,]+)/);
                if (match) {
                    const cleanStr = match[1].split(',')[0].replace(/[^0-9]/g, '');
                    totalBudget = parseInt(cleanStr) || 0;
                }
            } catch (e) {}

            // Scrape Activities Table
            const tableRows = page.locator('table tbody tr');
            const rowCount = await tableRows.count();
            let summedPagu = 0;
            
            if (rowCount > 0) {
                await db.query('DELETE FROM village_activities WHERE village_id = ? AND year = ?', [v.id, targetYear]);
                
                let activityCount = 0;
                for (let j = 0; j < rowCount; j++) {
                    const cells = tableRows.nth(j).locator('td');
                    const cellCount = await cells.count();
                    
                    if (cellCount >= 4) {
                        const uraian = await cells.nth(1).innerText();
                        const volume = await cells.nth(2).innerText();
                        const output = await cells.nth(3).innerText();
                        
                        let anggaran = 0;
                        for (let c = 3; c < cellCount; c++) {
                            const text = await cells.nth(c).innerText();
                            if (text.includes('Rp') || /[0-9]{3}/.test(text)) {
                                const cleanStr = text.split(',')[0].replace(/[^0-9]/g, '');
                                const val = parseInt(cleanStr);
                                if (val > 1000) { 
                                    anggaran = val;
                                    if (text.includes('Rp')) break; 
                                }
                            }
                        }
                        
                        if (uraian.trim() && (uraian.length > 5)) {
                            await db.query(
                                'INSERT INTO village_activities (village_id, year, uraian, volume, output, anggaran) VALUES (?, ?, ?, ?, ?, ?)',
                                [v.id, targetYear, uraian.trim(), volume.trim(), output.trim(), anggaran]
                            );
                            activityCount++;
                            summedPagu += anggaran;
                        }
                    }
                }
                console.log(`  Saved ${activityCount} activities for 2025.`);
            }

            if (totalBudget === 0 && summedPagu > 0) totalBudget = summedPagu;
            console.log(`  Pagu 2025: Rp ${totalBudget.toLocaleString()}`);

            // Update village record for budget_2025
            await db.query('UPDATE villages SET budget_2025 = ? WHERE id = ?', [totalBudget, v.id]);
            successCount++;

        } catch (err) {
            console.error(`  Error for ${v.nm_kelurahan}:`, err.message);
        }
        
        fs.writeFileSync('progress_dana_desa_2025.json', JSON.stringify({
            processed: i + 1,
            remaining: villages.length - (i + 1),
            success: successCount
        }));
        
        await page.waitForTimeout(2000 + Math.random() * 2000);
    }

    console.log(`\nScraping 2025 finished. Successfully updated ${successCount} villages.`);
    await browser.close();
    await db.end();
}

scrape();
