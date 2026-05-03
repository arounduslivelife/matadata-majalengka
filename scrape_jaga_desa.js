const axios = require('axios');
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

    console.log('--- JAGA.id Dana Desa Scraper ---');
    
    // Get villages
    const [villages] = await db.query('SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages');
    console.log(`Found ${villages.length} villages in database.`);

    const year = 2024; // Most complete data
    let successCount = 0;

    for (let i = 0; i < villages.length; i++) {
        const v = villages[i];
        
        // Construct code: 3210 + kec (2 digits) + 2 (flag) + kel (3 digits)
        // Example: Ampel 3210162008 -> kec 16, kel 008
        // Our kd_kecamatan is '016', kd_kelurahan is '008'
        const kec = parseInt(v.kd_kecamatan).toString();
        const kel = v.kd_kelurahan;
        const code = `3210${kec.padStart(2, '0')}2${kel.padStart(3, '0')}`;

        console.log(`[${i+1}/${villages.length}] Processing ${v.nm_kelurahan} (${code})...`);

        try {
            // Step 1: Get Pagu (Total Budget)
            const paguUrl = `https://jaga.id/api/v5/desa/${code}/rincian-pagu?year=${year}`;
            const paguRes = await axios.get(paguUrl, {
                headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' }
            });

            let totalBudget = 0;
            if (paguRes.data && paguRes.data.data) {
                // Usually it's an array or object
                const data = paguRes.data.data;
                totalBudget = data.total_pagu || 0;
            }

            // Step 2: Get Usage Details
            const detailUrl = `https://jaga.id/api/v5/desa/${code}/rincian-dana-desa?year=${year}`;
            const detailRes = await axios.get(detailUrl, {
                headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' }
            });

            if (detailRes.data && detailRes.data.data) {
                const activities = detailRes.data.data;
                
                // Clear old activities for this village/year
                await db.query('DELETE FROM village_activities WHERE village_id = ? AND year = ?', [v.id, year]);

                for (const act of activities) {
                    await db.query(
                        'INSERT INTO village_activities (village_id, year, uraian, volume, output, anggaran) VALUES (?, ?, ?, ?, ?, ?)',
                        [v.id, year, act.uraian_kegiatan, act.volume + ' ' + act.satuan, act.output, act.realisasi || 0]
                    );
                }
            }

            // Update village record
            await db.query('UPDATE villages SET budget_real = ? WHERE id = ?', [totalBudget, v.id]);
            
            successCount++;
            
            // Random delay to avoid blocking
            await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 2000));

        } catch (err) {
            console.error(`  Error for ${v.nm_kelurahan}:`, err.message);
        }
        
        // Break after 5 for testing if user wants to see progress first
        if (process.argv.includes('--test') && successCount >= 5) {
            console.log('Test run completed (5 villages).');
            break;
        }
    }

    console.log(`Scraping finished. Successfully updated ${successCount} villages.`);
    await db.end();
}

scrape();
