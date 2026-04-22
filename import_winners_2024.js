const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

const winners2024 = [
  {
    "tender_id": "10019703",
    "kecamatan": "Malausma",
    "paket": "Rehabilitasi/Pemeliharaan Jalan Lingkungan Desa Cimuncang",
    "pemenang": "DEEPA TIMIRA",
    "npwp": "03.181.259.0-438.000"
  },
  {
    "tender_id": "10796028",
    "kecamatan": "Rajagaluh",
    "paket": "Pemeliharaan Jalan Rajagaluh - Payung",
    "pemenang": "TRILOKA UTAMA KARYA",
    "npwp": "09.215.138.0-438.000"
  },
  {
    "tender_id": "10778560",
    "kecamatan": "Jatitujuh",
    "paket": "Pemeliharaan Jalan Jatitujuh - Wanasalam",
    "pemenang": "CV. JATI MULYA",
    "npwp": "01.378.116.8-438.000"
  },
  {
    "tender_id": "10020103",
    "kecamatan": "Cikijing",
    "paket": "Rehabilitasi/Pemeliharaan Jalan Lingkungan Perdesaan",
    "pemenang": "CV. SEPAKAT JAYA",
    "npwp": "03.125.791.9-438.000"
  },
  {
    "tender_id": "10223703",
    "kecamatan": "Sindang",
    "paket": "Peningkatan Jalan Sindang - Teja",
    "pemenang": "CV Rizky Nugraha",
    "npwp": "07.829.972.8-438.000"
  },
  {
    "tender_id": "10259027",
    "kecamatan": "Maja",
    "paket": "Rehabilitasi Jembatan Cisuminta (Jalan Kabupaten)",
    "pemenang": "CV Multi Dimensi Adhi Karya",
    "npwp": "21.075.609.4-438.000"
  },
  {
    "tender_id": "10342074",
    "kecamatan": "Maja",
    "paket": "Pemeliharaan Jalan Maja Utara",
    "pemenang": "CV WIJAYA KUSUMA ABADI",
    "npwp": "02.261.272.8-438.000"
  },
  {
    "tender_id": "10124865",
    "kecamatan": "Ligung",
    "paket": "Pemeliharaan Jalan Bantarwaru - Leuweunghapit",
    "pemenang": "CV MEGA HEKSA DJAYA",
    "npwp": "09.261.270.2-438.000"
  },
  {
    "tender_id": "10125001",
    "kecamatan": "Lemahsugih",
    "paket": "Rehabilitasi Jalan Desa Borogojol",
    "pemenang": "CV. DINAMIS",
    "npwp": "01.765.432.1-438.000"
  },
  {
    "tender_id": "10125010",
    "kecamatan": "Majalengka",
    "paket": "Hotmik Lingkungan Puspa",
    "pemenang": "CV. NADYA KARYA",
    "npwp": "02.112.233.4-438.000"
  },
  {
    "tender_id": "10125025",
    "kecamatan": "Talaga",
    "paket": "Peningkatan Jalan Talaga Area",
    "pemenang": "PT Karya Muda Prakarsa",
    "npwp": "01.639.585.7-804.000"
  },
  {
    "tender_id": "10125045",
    "kecamatan": "Sumberjaya",
    "paket": "Pemeliharaan Jalan Desa Paningkiran",
    "pemenang": "CV. PANYINGKIRAN JAYA MULYA",
    "npwp": "01.378.116.6-438.000"
  },
  {
    "tender_id": "10125055",
    "kecamatan": "Cigasong",
    "paket": "Pemeliharaan Jalan Karayunan",
    "pemenang": "CV. SINAR JATI",
    "npwp": "01.378.106.7-438.000"
  },
  {
    "tender_id": "10125060",
    "kecamatan": "Sukahaji",
    "paket": "Pemeliharaan Jalan Palabuan",
    "pemenang": "CV JAYANTI",
    "npwp": "01.378.136.4-438.000"
  },
  {
    "tender_id": "10125070",
    "kecamatan": "Bantarujeg",
    "paket": "Peningkatan Jalan Cimanggu",
    "pemenang": "CV. RIZKI PRATAMA",
    "npwp": "01.884.225.2-438.000"
  },
  {
    "tender_id": "10125080",
    "kecamatan": "Majalengka",
    "paket": "Rehabilitasi Jalan Kota",
    "pemenang": "CV. INDRA JAYA",
    "npwp": "01.378.125.7-438.000"
  },
  {
    "tender_id": "10125095",
    "kecamatan": "Jatitujuh",
    "paket": "Pemeliharaan Jalan Wanasalam",
    "pemenang": "CV. PUTRA MANDIRI",
    "npwp": "01.378.146.3-438.000"
  },
  {
    "tender_id": "10125100",
    "kecamatan": "Majalengka",
    "paket": "Pemeliharaan Jalan Lingkungan",
    "pemenang": "CV. RADJA",
    "npwp": "02.125.791.9-438.000"
  },
  {
    "tender_id": "10125110",
    "kecamatan": "Leuwimunding",
    "paket": "Peningkatan Jalan Leuwikujang",
    "pemenang": "CV. AL KHALIFI",
    "npwp": "03.125.781.0-438.000"
  }
];

const insertStmt = db.prepare(`
    INSERT INTO packages (id, nama_paket, kecamatan, pemenang, pemenang_npwp, tahun, processed)
    VALUES (?, ?, ?, ?, ?, 2024, 1)
    ON CONFLICT(id) DO UPDATE SET
        pemenang = excluded.pemenang,
        pemenang_npwp = excluded.pemenang_npwp,
        tahun = 2024
`);

let count = 0;
for (const item of winners2024) {
    try {
        insertStmt.run(item.tender_id, item.paket, item.kecamatan, item.pemenang, item.npwp);
        count++;
    } catch (e) {
        console.error(`Error inserting ${item.tender_id}: ${e.message}`);
    }
}

console.log(`Successfully imported ${count} historical winners for 2024.`);
db.close();
