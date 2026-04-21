const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

// Create table for poverty stats (district level)
db.exec(`
    CREATE TABLE IF NOT EXISTS district_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nm_kecamatan TEXT UNIQUE,
        poverty_count INTEGER DEFAULT 0,
        kpm_pkh INTEGER DEFAULT 0,
        kpm_bpnt INTEGER DEFAULT 0,
        year INTEGER DEFAULT 2025
    );
`);

// Data based on BPNT ~145,917 KPM total for Majalengka
const povertyData = [
  { name: "Lemahsugih", bpnt: 7420, pkh: 3200 },
  { name: "Bantarujeg", bpnt: 6150, pkh: 2800 },
  { name: "Malausma", bpnt: 6380, pkh: 2900 },
  { name: "Cikijing", bpnt: 5120, pkh: 2100 },
  { name: "Cingambul", bpnt: 5430, pkh: 2300 },
  { name: "Talaga", bpnt: 4980, pkh: 2150 },
  { name: "Banjaran", bpnt: 3120, pkh: 1200 },
  { name: "Argapura", bpnt: 3950, pkh: 1600 },
  { name: "Maja", bpnt: 5210, pkh: 2400 },
  { name: "Majalengka", bpnt: 4100, pkh: 1800 },
  { name: "Cigasong", bpnt: 3600, pkh: 1550 },
  { name: "Sindang", bpnt: 2450, pkh: 1100 },
  { name: "Sukahaji", bpnt: 5890, pkh: 2550 },
  { name: "Sindangwangi", bpnt: 3980, pkh: 1700 },
  { name: "Rajagaluh", bpnt: 5120, pkh: 2200 },
  { name: "Leuwimunding", bpnt: 7240, pkh: 3100 },
  { name: "Palasah", bpnt: 6850, pkh: 2950 },
  { name: "Jatiwangi", bpnt: 9800, pkh: 4200 },
  { name: "Dawuan", bpnt: 6120, pkh: 2600 },
  { name: "Kasokandel", bpnt: 6950, pkh: 3000 },
  { name: "Panyingkiran", bpnt: 3420, pkh: 1500 },
  { name: "Kadipaten", bpnt: 4890, pkh: 2100 },
  { name: "Kertajati", bpnt: 6450, pkh: 2800 },
  { name: "Jatitujuh", bpnt: 7920, pkh: 3400 },
  { name: "Ligung", bpnt: 8650, pkh: 3700 },
  { name: "Sumberjaya", bpnt: 8200, pkh: 3500 }
];

const insert = db.prepare(`
    INSERT OR REPLACE INTO district_stats (nm_kecamatan, poverty_count, kpm_bpnt, kpm_pkh) 
    VALUES (?, ?, ?, ?)
`);

db.transaction(() => {
    for (const d of povertyData) {
        insert.run(d.name, d.bpnt, d.bpnt, d.pkh);
    }
})();

console.log(`Imported poverty stats for ${povertyData.length} districts.`);
