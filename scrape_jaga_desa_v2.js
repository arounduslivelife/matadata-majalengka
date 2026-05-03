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

    console.log('--- JAGA.id Dana Desa Scraper v2 (Playwright) ---');
    
    // Get villages - only those that haven't been successfully scraped (budget_real still 0)
    const [villages] = await db.query('SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages WHERE budget_real = 0 OR budget_real IS NULL');
    console.log(`Found ${villages.length} villages remaining to be scraped.`);

    const year = 2024;
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
        
        const url = `https://jaga.id/pelayanan-publik/desa/${code}/${encodeURIComponent(v.nm_kelurahan)}?year=${year}`;
        
        console.log(`[${i+1}/${villages.length}] Processing ${v.nm_kelurahan} (${code})...`);

        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
            await page.waitForTimeout(5000); 
            
            const title = await page.title();
            if (title.includes('404') || title.includes('Error')) {
                throw new Error('Page returned 404/Error');
            }

            const menuSelector = 'text=/.*Informasi Penyaluran Dana Desa.*/i';
            await page.waitForSelector(menuSelector, { timeout: 30000 });
            await page.locator(menuSelector).first().click();
            
            await page.waitForTimeout(10000); 
            
            // Scrape Pagu (Nilai Anggaran) - Enhanced Detection
            let totalBudget = 0;
            try {
                const paguText = await page.evaluate(() => {
                    // Look for any element that strictly has "Nilai Anggaran"
                    const allElements = Array.from(document.querySelectorAll('div, span, p, b, h6'));
                    const label = allElements.find(e => e.innerText.trim() === 'Nilai Anggaran');
                    if (!label) return "";
                    
                    // Traverse up to find a container that has Rp
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
            } catch (e) {
                const altPagu = await page.locator('.text-h6:has-text("Rp"), .text-h5:has-text("Rp"), .q-card >> text=/Rp/').first().innerText().catch(() => "0");
                const cleanStr = altPagu.split(',')[0].replace(/[^0-9]/g, '');
                totalBudget = parseInt(cleanStr) || 0;
            }
            
            if (isNaN(totalBudget) || totalBudget === 0) {
                // If Pagu extraction failed, try to sum the table as fallback
                const anggaranCells = await page.locator('table tbody tr td:nth-child(5)').allInnerTexts().catch(() => []);
                if (anggaranCells.length > 0) {
                    const sum = anggaranCells.reduce((acc, text) => {
                        const val = parseInt(text.split(',')[0].replace(/[^0-9]/g, '')) || 0;
                        return acc + val;
                    }, 0);
                    if (sum > 0) totalBudget = sum;
                }
            }


            // Scrape Activities Table
            const tableRows = page.locator('table tbody tr');
            const rowCount = await tableRows.count();
            let summedPagu = 0;
            
            if (rowCount > 0) {
                await db.query('DELETE FROM village_activities WHERE village_id = ? AND year = ?', [v.id, year]);
                
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
                                [v.id, year, uraian.trim(), volume.trim(), output.trim(), anggaran]
                            );
                            activityCount++;
                            summedPagu += anggaran;
                        }
                    }
                }
                console.log(`  Saved ${activityCount} activities.`);
            } else {
                console.log('  No activities found.');
            }

            // Fallback: If Pagu extraction failed, use the sum of activities
            if (totalBudget === 0 && summedPagu > 0) {
                totalBudget = summedPagu;
            }

            console.log(`  Pagu Total: Rp ${totalBudget.toLocaleString()}`);

            // Update village record - Only update if we found some data to avoid resetting
            if (totalBudget > 0 || rowCount > 0) {
                await db.query('UPDATE villages SET budget_real = ? WHERE id = ?', [totalBudget, v.id]);
                successCount++;
            }

        } catch (err) {
            console.error(`  Error for ${v.nm_kelurahan}:`, err.message);
        }
        
        fs.writeFileSync('progress_dana_desa.json', JSON.stringify({
            processed: i + 1,
            remaining: villages.length - (i + 1),
            success: successCount
        }));

        if (process.argv.includes('--test3') && successCount >= 3) {
            console.log('Test run completed (3 villages).');
            break;
        }
        
        await page.waitForTimeout(2000 + Math.random() * 2000);
    }

    console.log(`\nScraping finished. Successfully updated ${successCount} villages.`);
    await browser.close();
    await db.end();
}

scrape();
