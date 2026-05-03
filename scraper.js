const axios = require('axios');
const db = require('./db');
const fs = require('fs');

const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));

async function scrape() {
    console.log("Starting Scraping for Majalengka 2025...");
    const url = 'https://sirup.inaproc.id/sirup/datatablectr/dataruppenyediakldi';
    const batchSize = 100; // Small batches to be safe
    let start = 0;
    let totalRecords = 1; // Initial dummy value

    const headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'X-Requested-With': 'XMLHttpRequest'
    };

    while (start < totalRecords) {
        try {
            console.log(`Fetching records ${start} to ${start + batchSize}...`);
            const response = await axios.get(url, {
                params: {
                    idKldi: 'D100', // Majalengka ID found in research
                    tahun: config.TARGET_YEAR,
                    iDisplayStart: start,
                    iDisplayLength: batchSize,
                    sEcho: 1
                },
                headers: headers
            });

            const data = response.data;
            totalRecords = data.iTotalRecords || data.recordsTotal || 10000;
            console.log(`Total records available: ${totalRecords}`);

            const packages = data.aaData;
            
            for (const p of packages) {
                // SiRUP aaData usually returns: [No, Satker, Nama Paket, Pagu, Metode, Sumber Dana, ID/Actions]
                const no = p[0];
                const satker = p[1];
                const namaPaket = p[2];
                const paguRaw = p[3].toString().replace(/[^0-9]/g, '');
                const pagu = parseInt(paguRaw);
                const metode = p[4];
                const sumberDana = p[5];
                const idHtml = p[6] || p[7];
                const idMatch = idHtml.match(/idPaket=(\d+)/) || idHtml.match(/id=(\d+)/);
                const id = idMatch ? idMatch[1] : `manual_${no}_${Date.now()}`;

                await db.query(
                    "REPLACE INTO packages (id, no, satker, nama_paket, pagu, metode, sumber_dana) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [id, no, satker, namaPaket, pagu, metode, sumberDana]
                );
            }

            console.log(`Saved ${packages.length} packages.`);
            start += batchSize;
        } catch (e) {
            console.error("Error during scraping batch:", e.message);
            break; 
        }
    }
    console.log("Scraping finished!");
}

scrape();

