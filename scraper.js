const axios = require('axios');
const Database = require('better-sqlite3');
const fs = require('fs');

const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
const DB_PATH = 'database.sqlite';

// Initialize Database
const db = new Database(DB_PATH);
db.exec(`
  CREATE TABLE IF NOT EXISTS packages (
    id TEXT PRIMARY KEY,
    no INTEGER,
    satker TEXT,
    nama_paket TEXT,
    pagu INTEGER,
    metode TEXT,
    sumber_dana TEXT,
    kecamatan TEXT,
    risk_score TEXT,
    audit_note TEXT,
    processed INTEGER DEFAULT 0
  )
`);

const insertStmt = db.prepare(`
  INSERT OR REPLACE INTO packages (id, no, satker, nama_paket, pagu, metode, sumber_dana)
  VALUES (?, ?, ?, ?, ?, ?, ?)
`);

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
            console.log("Response keys:", Object.keys(data));
            totalRecords = data.iTotalRecords || data.recordsTotal || 10000;
            console.log(`Total records available: ${totalRecords}`);

            const packages = data.aaData;
            const transaction = db.transaction((pkgs) => {
                for (const p of pkgs) {
                    // SiRUP aaData usually returns: [No, Satker, Nama Paket, Pagu, Metode, Sumber Dana, ID/Actions]
                    // We need to strip HTML from ID if it exists and parse Pagu
                    const no = p[0];
                    const satker = p[1];
                    const namaPaket = p[2];
                    const paguRaw = p[3].toString().replace(/[^0-9]/g, '');
                    const pagu = parseInt(paguRaw);
                    const metode = p[4];
                    const sumberDana = p[5];
                    const idHtml = p[6] || p[7]; // Usually the last column contains the ID or link
                    const idMatch = idHtml.match(/idPaket=(\d+)/) || idHtml.match(/id=(\d+)/);
                    const id = idMatch ? idMatch[1] : `manual_${no}_${Date.now()}`;

                    insertStmt.run(id, no, satker, namaPaket, pagu, metode, sumberDana);
                }
            });

            transaction(packages);
            console.log(`Saved ${packages.length} packages.`);

            start += batchSize;
        } catch (e) {
            console.error("Error during scraping batch:", e.message);
            break; // Stop on error
        }
    }
    console.log("Scraping finished!");
}

scrape();
