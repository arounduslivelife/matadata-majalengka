const axios = require('axios');
const mysql = require('mysql2/promise');
const fs = require('fs');

async function testScrape() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    console.log('--- Testing JAGA.id Scraper for 2 Villages ---');
    
    // Get 2 villages
    const [villages] = await db.query('SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages LIMIT 2');
    
    const year = 2024;
    const results = [];

    for (const v of villages) {
        const kec = parseInt(v.kd_kecamatan).toString();
        const kel = v.kd_kelurahan;
        const code = `3210${kec.padStart(2, '0')}2${kel.padStart(3, '0')}`;

        console.log(`\nFetching data for: ${v.nm_kelurahan} (${code})...`);

        try {
            const headers = {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'application/json, text/plain, */*',
                'Referer': `https://jaga.id/pelayanan-publik/desa/${code}/${encodeURIComponent(v.nm_kelurahan)}?year=${year}`,
                'Origin': 'https://jaga.id'
            };

            // Try Endpoint 1: Rincian Pagu
            const paguUrl = `https://jaga.id/api/v5/desa/${code}/rincian-pagu?year=${year}`;
            const paguRes = await axios.get(paguUrl, { headers });
            
            // Try Endpoint 2: Penyaluran
            const penyaluranUrl = `https://jaga.id/api/v5/desa/dana-desa/penyaluran/${code}?year=${year}`;
            const penyaluranRes = await axios.get(penyaluranUrl, { headers });

            // Try Endpoint 3: Rincian Dana Desa
            const detailUrl = `https://jaga.id/api/v5/desa/${code}/rincian-dana-desa?year=${year}`;
            const detailRes = await axios.get(detailUrl, { headers });


            results.push({
                village: v.nm_kelurahan,
                code: code,
                pagu_data: paguRes.data,
                penyaluran_data: penyaluranRes.data,
                detail_data: detailRes.data
            });

        } catch (err) {

            console.error(`Error for ${v.nm_kelurahan}:`, err.message);
            results.push({ village: v.nm_kelurahan, error: err.message });
        }
    }

    console.log('\n--- RESULTS ---');
    console.log(JSON.stringify(results, null, 2));

    await db.end();
}

testScrape();
