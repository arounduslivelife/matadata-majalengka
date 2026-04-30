const fs = require('fs');
const Database = require('better-sqlite3');
const readline = require('readline');

const db = new Database('database.sqlite');
const centers = JSON.parse(fs.readFileSync('scratch/kecamatan_centers.json', 'utf8'));
const defaultCenter = [-6.837, 108.227]; // Majalengka Central

const KECAMATANS = [
    'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
    'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
    'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
    'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul'
];

// Add columns if not exists
try {
    db.exec("ALTER TABLE packages ADD COLUMN status TEXT");
} catch(e) {}
try {
    db.exec("ALTER TABLE packages ADD COLUMN kabupaten TEXT");
} catch(e) {}

// Clear existing packages for Layer 1 replacement
console.log("Clearing existing packages table for Realization Audit 2025...");
db.exec("DELETE FROM packages");

const insert = db.prepare(`INSERT OR REPLACE INTO packages 
    (id, satker, nama_paket, pagu, metode, sumber_dana, pemenang, status, kabupaten, kecamatan, tahun, processed) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);

// Optional: Keep separate columns in the database if we want, 
// but for compatibility with index.php, we'll map:
// Total Nilai (Rp) -> pagu
// Nama Penyedia -> pemenang

const importData = async () => {
    const fileStream = fs.createReadStream('raw/realisasi2025alldept.csv');
    const rl = readline.createInterface({ input: fileStream, crlfDelay: Infinity });

    let header = null;
    let count = 0;

    for await (const line of rl) {
        if (!header) {
            header = line.split(',');
            continue;
        }

        // CSV Parser for quoted strings
        const parts = line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
        if (parts.length < 13) continue;

        const instansi = parts[0].replace(/"/g, '');
        const satker = parts[1].replace(/"/g, '');
        const kodePacket = parts[2];
        const rup = parts[3];
        const tahun = parseInt(parts[4]) || 2025;
        const vendor = parts[7].replace(/"/g, '');
        const method = parts[8];
        const type = parts[9];
        const packageName = parts[10].replace(/"/g, '');
        const status = parts[11];
        const total = parseFloat(parts[12]) || 0;
        const sourceDana = parts[6];

        let kecamatan = "KAB. MAJALENGKA";
        
        const sortedKecamatans = [...KECAMATANS].sort((a, b) => b.length - a.length);
        for (const kec of sortedKecamatans) {
            const regex = new RegExp('\\b' + kec + '\\b', 'i');
            if (regex.test(satker) || regex.test(packageName)) {
                kecamatan = kec;
                break;
            }
        }

        // For Layer 1, processed is 0 initially so AI can audit it
        insert.run(kodePacket, satker, packageName, total, method, sourceDana, vendor, status, instansi, kecamatan, tahun, 0);
        count++;
        
        if (count % 1000 === 0) console.log(`Processed ${count} records...`);
    }

    console.log(`Successfully ingested ${count} realization records into Layer 1 (packages table).`);
};

importData().catch(err => {
    console.error(err);
    process.exit(1);
});
