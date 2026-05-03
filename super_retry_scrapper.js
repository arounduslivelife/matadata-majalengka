const { chromium } = require('playwright');
const mysql = require('mysql2/promise');
const fs = require('fs');

async function superRetry() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    const kelurahanList = ['Majalengka Kulon', 'Majalengka Wetan', 'Munjul', 'Cicurug', 'Cigasong', 'Simpeureum', 'Tarikolot', 'Tonjong', 'Babakan Jawa', 'Sindangkasih'];
    
    // Find all real villages that still have 0 budget in 2024 or 2025
    const [villages] = await db.query(`
        SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan 
        FROM villages 
        WHERE (budget_real = 0 OR budget_2025 = 0 OR budget_2025 = '' OR budget_2025 IS NULL)
        AND nm_kelurahan NOT IN (${kelurahanList.map(k => `'${k}'`).join(',')})
    `);

    console.log(`--- SUPER RETRY SCRAPER ---`);
    console.log(`Targeting ${villages.length} stubborn villages...`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });

    for (let v of villages) {
        console.log(`\n>>> Processing [${v.nm_kelurahan}]...`);
        
        for (let year of [2024, 2025]) {
            let attempt = 0;
            let success = false;
            
            while (attempt < 3 && !success) {
                attempt++;
                console.log(`  Year ${year} - Attempt ${attempt}/3...`);
                
                const page = await context.newPage();
                try {
                    const kec = parseInt(v.kd_kecamatan).toString().padStart(2, '0');
                    const kel = v.kd_kelurahan.padStart(3, '0');
                    const code = `3210${kec}2${kel}`;
                    const url = `https://jaga.id/pelayanan-publik/desa/${code}/${encodeURIComponent(v.nm_kelurahan)}?year=${year}`;

                    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
                    await page.waitForTimeout(10000); 

                    const menuSelector = 'text=/.*Informasi Penyaluran Dana Desa.*/i';
                    await page.waitForSelector(menuSelector, { timeout: 45000 });
                    await page.locator(menuSelector).first().click();
                    await page.waitForTimeout(15000); 

                    // Scrape Pagu & Activities using the robust logic
                    let totalBudget = 0;
                    const paguText = await page.evaluate(() => {
                        const el = Array.from(document.querySelectorAll('*')).find(e => e.innerText.includes('Nilai Anggaran') && e.children.length === 0);
                        if (!el) return "";
                        let p = el.parentElement;
                        for (let j = 0; j < 5; j++) { if (p && p.innerText.includes('Rp')) break; p = p.parentElement; }
                        return p ? p.innerText : "";
                    });
                    const match = paguText.match(/Rp\s*([0-9.,]+)/);
                    if (match) totalBudget = parseInt(match[1].split(',')[0].replace(/[^0-9]/g, '')) || 0;

                    const tableRows = page.locator('table tbody tr');
                    const rowCount = await tableRows.count();
                    let summedPagu = 0;
                    
                    if (rowCount > 0) {
                        await db.query('DELETE FROM village_activities WHERE village_id = ? AND year = ?', [v.id, year]);
                        for (let j = 0; j < rowCount; j++) {
                            const cells = tableRows.nth(j).locator('td');
                            const cellCount = await cells.count();
                            if (cellCount >= 4) {
                                const uraian = await cells.nth(1).innerText();
                                let anggaran = 0;
                                for (let c = 3; c < cellCount; c++) {
                                    const text = await cells.nth(c).innerText();
                                    if (text.includes('Rp')) {
                                        anggaran = parseInt(text.split(',')[0].replace(/[^0-9]/g, '')) || 0;
                                        if (anggaran > 1000) break;
                                    }
                                }
                                if (uraian.trim().length > 5) {
                                    await db.query('INSERT INTO village_activities (village_id, year, uraian, volume, output, anggaran) VALUES (?, ?, ?, ?, ?, ?)', [v.id, year, uraian.trim(), '', '', anggaran]);
                                    summedPagu += anggaran;
                                }
                            }
                        }
                    }
                    if (totalBudget === 0) totalBudget = summedPagu;

                    if (totalBudget > 0) {
                        const col = year === 2024 ? 'budget_real' : 'budget_2025';
                        await db.query(`UPDATE villages SET ${col} = ? WHERE id = ?`, [totalBudget, v.id]);
                        console.log(`    SUCCESS: Pagu ${year} = Rp ${totalBudget.toLocaleString()}`);
                        success = true;
                    } else {
                        console.log(`    Still 0. Trying again...`);
                    }
                } catch (err) {
                    console.error(`    Error: ${err.message}`);
                }
                await page.close();
                if (!success) await new Promise(r => setTimeout(r, 5000));
            }
        }
    }

    console.log(`\nSuper Retry finished.`);
    await browser.close();
    await db.end();
}

superRetry();
