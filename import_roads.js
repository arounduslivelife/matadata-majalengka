/**
 * Tujuan: Seeding data kemantapan jalan desa ke tabel district_stats.
 * Caller: Manual (One-shot execution)
 * Dependensi: database.sqlite
 **/
const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

// Ensure column exists (SQLite specific check/add)
try {
    db.exec("ALTER TABLE district_stats ADD COLUMN road_firmness_pct INTEGER DEFAULT 75;");
} catch (e) {
    // Column might already exist
}

const districts = [
    { name: "Lemahsugih", pct: 62 },
    { name: "Bantarujeg", pct: 58 },
    { name: "Malausma", pct: 55 },
    { name: "Cikijing", pct: 82 },
    { name: "Cingambul", pct: 78 },
    { name: "Talaga", pct: 85 },
    { name: "Banjaran", pct: 72 },
    { name: "Argapura", pct: 68 },
    { name: "Maja", pct: 74 },
    { name: "Majalengka", pct: 92 },
    { name: "Cigasong", pct: 88 },
    { name: "Sindang", pct: 65 },
    { name: "Sukahaji", pct: 81 },
    { name: "Sindangwangi", pct: 79 },
    { name: "Rajagaluh", pct: 83 },
    { name: "Leuwimunding", pct: 88 },
    { name: "Palasah", pct: 86 },
    { name: "Jatiwangi", pct: 90 },
    { name: "Dawuan", pct: 84 },
    { name: "Kasokandel", pct: 82 },
    { name: "Panyingkiran", pct: 71 },
    { name: "Kadipaten", pct: 89 },
    { name: "Kertajati", pct: 91 },
    { name: "Jatitujuh", pct: 78 },
    { name: "Ligung", pct: 74 },
    { name: "Sumberjaya", pct: 87 }
];

const update = db.prepare("UPDATE district_stats SET road_firmness_pct = ? WHERE nm_kecamatan = ?");

db.transaction(() => {
    for (const d of districts) {
        update.run(d.pct, d.name);
    }
})();

console.log("Updated road firmness stats for 26 districts.");
