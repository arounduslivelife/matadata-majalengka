const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

console.log("Preparing packages table for unified realization audit...");

// Add necessary columns if they don't exist
try { db.exec("ALTER TABLE packages ADD COLUMN lat REAL"); } catch(e) {}
try { db.exec("ALTER TABLE packages ADD COLUMN lng REAL"); } catch(e) {}
try { db.exec("ALTER TABLE packages ADD COLUMN jenis TEXT"); } catch(e) {}

// Migrate data from realization_2026 to packages
console.log("Migrating 2026 data...");
const data2026 = db.prepare("SELECT * FROM realization_2026").all();

const insert = db.prepare(`INSERT OR REPLACE INTO packages 
    (id, satker, nama_paket, pagu, metode, sumber_dana, pemenang, status, kecamatan, tahun, processed, lat, lng, jenis) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);

let count = 0;
const transaction = db.transaction((rows) => {
    for (const r of rows) {
        // Map realization_2026 columns to packages columns
        // realization_2026.id is autoincrement integer, we use kode_paket as id if available
        const id = r.kode_paket || `real2026_${r.id}`;
        insert.run(
            id, 
            r.satker, 
            r.nama_paket, 
            r.total_nilai, 
            r.metode, 
            r.sumber_dana, 
            r.vendor, 
            r.status, 
            r.kecamatan, 
            2026, // tahun
            1,    // processed (consider 2026 data as processed or verified for now)
            r.lat, 
            r.lng,
            r.jenis
        );
        count++;
    }
});

transaction(data2026);
console.log(`Successfully migrated ${count} records from 2026 data to unified packages table.`);

// Optional: Drop the old table to clean up
// db.exec("DROP TABLE realization_2026");
console.log("Migration complete.");
