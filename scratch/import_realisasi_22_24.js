const fs = require('fs');
const Database = require('better-sqlite3');

const db = new Database('database.sqlite');
const KECAMATANS = [
    'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
    'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
    'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
    'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul'
];

const jsonData = JSON.parse(fs.readFileSync('c:/xampp/htdocs/matadata/data/realisasi20222024alldept.json', 'utf8'));
const records = jsonData.data;

const insert = db.prepare(`INSERT OR REPLACE INTO packages 
    (id, satker, nama_paket, pagu, metode, sumber_dana, pemenang, status, kabupaten, kecamatan, tahun, processed) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);

const extractKecamatan = (satker, packageName) => {
    let kecamatan = "KAB. MAJALENGKA";
    const sortedKecamatans = [...KECAMATANS].sort((a, b) => b.length - a.length);
    for (const kec of sortedKecamatans) {
        const regex = new RegExp('\\b' + kec + '\\b', 'i');
        if (regex.test(satker) || regex.test(packageName)) {
            kecamatan = kec;
            break;
        }
    }
    return kecamatan;
};

console.log(`Starting import of ${records.length} records into Layer 1 (packages)...`);

db.transaction((data) => {
    let count = 0;
    for (const record of data) {
        const kecamatan = extractKecamatan(record.nama_satuan_kerja, record.nama_paket);
        insert.run(
            record.kode_paket,
            record.nama_satuan_kerja,
            record.nama_paket,
            record.total_nilai_rp,
            record.metode_pengadaan,
            record.sumber_dana,
            record.nama_penyedia,
            record.status_paket,
            record.nama_instansi,
            kecamatan,
            record.tahun_anggaran,
            0 // processed = 0 (Ready for audit)
        );
        count++;
        if (count % 1000 === 0) console.log(`Imported ${count} records...`);
    }
})(records);

console.log("Successfully imported 2022-2024 records into packages table.");
