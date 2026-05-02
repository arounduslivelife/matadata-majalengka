const fs = require('fs');
const Database = require('better-sqlite3');

const db = new Database('database.sqlite');
const centers = JSON.parse(fs.readFileSync('scratch/kecamatan_centers.json', 'utf8'));
const defaultCenter = [-6.837, 108.227]; // Majalengka Central

const KECAMATANS = [
    'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
    'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
    'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
    'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul'
];

const insert = db.prepare(`INSERT INTO realizations 
    (satker, kode_paket, rup, sumber_dana, vendor, metode, jenis, nama_paket, status, total_nilai, pdn, kecamatan, lat, lng, tahun) 
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`);

const importData = () => {
    console.log("Reading data/realisasi20222024alldept.json...");
    const rawData = fs.readFileSync('data/realisasi20222024alldept.json', 'utf8');
    const jsonData = JSON.parse(rawData);
    const data = jsonData.data;
    
    console.log(`Starting ingestion of ${data.length} records...`);
    
    const transaction = db.transaction((rows) => {
        let count = 0;
        for (const row of rows) {
            const satker = row.nama_satuan_kerja || "";
            const kodePacket = row.kode_paket || "";
            const rup = row.kode_rup || "";
            const sourceDana = row.sumber_dana || "";
            const vendor = row.nama_penyedia || "";
            const method = row.metode_pengadaan || "";
            const type = row.jenis_pengadaan || "";
            const packageName = row.nama_paket || "";
            const status = row.status_paket || "";
            const total = parseFloat(row.total_nilai_rp) || 0;
            const pdn = parseFloat(row.nilai_pdn_rp) || 0;
            const tahun = parseInt(row.tahun_anggaran) || 0;

            let kecamatan = "KAB. MAJALENGKA";
            let lat = defaultCenter[0];
            let lng = defaultCenter[1];

            const sortedKecamatans = [...KECAMATANS].sort((a, b) => b.length - a.length);
            for (const kec of sortedKecamatans) {
                const regex = new RegExp('\\b' + kec + '\\b', 'i');
                if (regex.test(satker) || regex.test(packageName)) {
                    kecamatan = kec;
                    if (centers[kec]) {
                        lat = centers[kec][0];
                        lng = centers[kec][1];
                    }
                    break;
                }
            }

            insert.run(satker, kodePacket, rup, sourceDana, vendor, method, type, packageName, status, total, pdn, kecamatan, lat, lng, tahun);
            count++;
            if (count % 1000 === 0) console.log(`Ingested ${count} records...`);
        }
        return count;
    });

    const count = transaction(data);
    console.log(`Successfully ingested ${count} realization records.`);
};

try {
    importData();
} catch (err) {
    console.error(err);
    process.exit(1);
}
