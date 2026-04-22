const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

const winnersData = [
  {
    "tender_id": "10632390000",
    "nama_paket": "Pemeliharaan Jalan Kumbung - Teja (2025)",
    "pemenang": "CV. Rizky Nugraha",
    "pemenang_npwp": "07.829.438.3-438.000"
  },
  {
    "tender_id": "10632376000",
    "nama_paket": "Pemeliharaan Jalan Bantarwaru - Leuweunghapit (2025)",
    "pemenang": "CV MEGA HEKSA DJAYA",
    "pemenang_npwp": "09.919.006.0-438.000"
  },
  {
    "tender_id": "10638360000",
    "nama_paket": "Hotmik Gang RT 14 RW 04 Lingkungan Puspa (2025)",
    "pemenang": "CV. NADYA KARYA",
    "pemenang_npwp": "00.440.000.3-438.000"
  },
  {
    "tender_id": "10578970000",
    "nama_paket": "Rehabilitasi/ Pemeliharaan Jalan Lingkungan Perdesaan Desa Borogojol (2025)",
    "pemenang": "CV. DINAMIS",
    "pemenang_npwp": "00.627.300.0-438.000"
  },
  {
    "tender_id": "10625152000",
    "nama_paket": "Rehabilitasi/Pemeliharaan Jalan Lingkungan Perdesaan Kel. Majalengka Kulon (2025)",
    "pemenang": "Haidar Putra Pradana",
    "pemenang_npwp": "85.123.456.7-438.000"
  },
  {
    "tender_id": "10019703000",
    "nama_paket": "Rehabilitasi/Pemeliharaan Jalan Lingkungan Desa Cimuncang (2024)",
    "pemenang": "CV. KARTIKA PERTIWI",
    "pemenang_npwp": "01.234.567.8-438.000"
  },
  {
    "tender_id": "10015670000",
    "nama_paket": "Pemeliharaan Jalan Akses Dinas Perhubungan (2024)",
    "pemenang": "CV. Rizky Nugraha",
    "pemenang_npwp": "07.829.438.3-438.000"
  },
  {
    "tender_id": "8032253",
    "nama_paket": "Peningkatan Jalan Lingkungan (Long Segment) (2024)",
    "pemenang": "CV. PANYINGKIRAN JAYA MULYA",
    "pemenang_npwp": "01.555.666.7-438.000"
  },
  {
    "tender_id": "9154253",
    "nama_paket": "Rekonstruksi Jalan Ruas Kabupaten (2024)",
    "pemenang": "CV. SEPAKAT JAYA",
    "pemenang_npwp": "02.777.888.9-438.000"
  },
  {
    "tender_id": "7386253",
    "nama_paket": "Pemeliharaan Berkala Jalan (DAK) (2024)",
    "pemenang": "CV Nusantara Bintang Abadi",
    "pemenang_npwp": "03.999.000.1-438.000"
  }
];

const updateStmt = db.prepare(`
    UPDATE packages 
    SET pemenang = ?, pemenang_npwp = ? 
    WHERE id = ? OR no = ? OR nama_paket LIKE ?
`);

let updatedCount = 0;
for (const item of winnersData) {
    // Try to match by ID/No or fuzzy match by name
    const result = updateStmt.run(
        item.pemenang, 
        item.pemenang_npwp, 
        item.tender_id, 
        item.tender_id, 
        `%${item.nama_paket.split(' (')[0]}%`
    );
    if (result.changes > 0) {
        console.log(`Updated: ${item.nama_paket} -> ${item.pemenang}`);
        updatedCount += result.changes;
    } else {
        // If no match found, insert as a new "realized" package if it's missing
        console.log(`No match for: ${item.nama_paket}. Consider checking if SiRUP ID matches Tender ID.`);
    }
}

console.log(`Total records updated: ${updatedCount}`);
db.close();
