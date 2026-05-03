const { chromium } = require('playwright');
const mysql = require('mysql2/promise');
const fs = require('fs');

async function fixAnomalies() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const anomalies = JSON.parse(fs.readFileSync('anomali_dana_desa.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    const year = 2025;
    console.log(`--- JAGA.id Data Fixer (${year}) ---`);
    console.log(`Targeting ${anomalies.length} anomalous villages...`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    const page = await context.newPage();

    for (let i = 0; i < anomalies.length; i++) {
        const villageName = anomalies[i].village;
        
        const [vRows] = await db.query('SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages WHERE nm_kelurahan = ?', [villageName]);
        if (vRows.length === 0) continue;
        const v = vRows[0];

        const kec = parseInt(v.kd_kecamatan).toString().padStart(2, '0');
        const kel = v.kd_kelurahan.padStart(3, '0');
        const code = `3210${kec}2${kel}`;
        const url = `https://jaga.id/pelayanan-publik/desa/${code}/${encodeURIComponent(v.nm_kelurahan)}?year=${year}`;
        
        console.log(`[${i+1}/${anomalies.length}] Re-processing ${v.nm_kelurahan}...`);

        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
            await page.waitForTimeout(8000); 

            const menuSelector = 'text=/.*Informasi Penyaluran Dana Desa.*/i';
            await page.waitForSelector(menuSelector, { timeout: 30000 }).catch(() => null);
            const menuExists = await page.locator(menuSelector).count();
            if (menuExists > 0) {
                await page.locator(menuSelector).first().click();
                await page.waitForTimeout(10000); 
            }

            // Scrape Pagu
            let totalBudget = 0;
            try {
                const paguText = await page.evaluate(() => {
                    const el = Array.from(document.querySelectorAll('*')).find(e => (e.innerText.includes('Nilai Anggaran')) && e.children.length === 0);
                    if (!el) return "";
                    let p = el.parentElement;
                    for (let j = 0; j < 5; j++) { if (p && p.innerText.includes('Rp')) break; p = p.parentElement; }
                    return p ? p.innerText : "";
                });
                const match = paguText.match(/Rp\s*([0-9.,]+)/);
                if (match) totalBudget = parseInt(match[1].split(',')[0].replace(/[^0-9]/g, '')) || 0;
            } catch (e) {}

            // Scrape Activities
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
                        const volume = await cells.nth(2).innerText();
                        const output = await cells.nth(3).innerText();
                        let anggaran = 0;
                        for (let c = 3; c < cellCount; c++) {
                            const text = await cells.nth(c).innerText();
                            if (text.includes('Rp')) {
                                const val = parseInt(text.split(',')[0].replace(/[^0-9]/g, '')) || 0;
                                if (val > 1000) { anggaran = val; break; }
                            }
                        }
                        if (uraian.trim().length > 5) {
                            await db.query('INSERT INTO village_activities (village_id, year, uraian, volume, output, anggaran) VALUES (?, ?, ?, ?, ?, ?)', [v.id, year, uraian.trim(), volume.trim(), output.trim(), anggaran]);
                            summedPagu += anggaran;
                        }
                    }
                }
            }
            if (totalBudget === 0) totalBudget = summedPagu;
            console.log(`  Fixed Pagu 2025: Rp ${totalBudget.toLocaleString()}`);
            await db.query('UPDATE villages SET budget_2025 = ? WHERE id = ?', [totalBudget, v.id]);

        } catch (err) {
            console.error(`  Error for ${v.nm_kelurahan}:`, err.message);
        }
        await page.waitForTimeout(3000);
    }
    await browser.close();
    await db.end();
}
fixAnomalies();
