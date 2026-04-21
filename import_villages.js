const Database = require('better-sqlite3');
const fs = require('fs');
const db = new Database('database.sqlite');

// Create tables
db.exec(`
    CREATE TABLE IF NOT EXISTS villages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nm_kelurahan TEXT,
        nm_kecamatan TEXT,
        kd_kelurahan TEXT,
        kd_kecamatan TEXT,
        budget_2025 REAL DEFAULT 0,
        risk_score REAL DEFAULT 0
    );
`);

const villageGeo = JSON.parse(fs.readFileSync('villages.geojson', 'utf8'));
const districtGeo = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));

// Map district codes to names for easier lookup
const districtMap = {};
districtGeo.features.forEach(f => {
    districtMap[f.properties.kd_kecamatan] = f.properties.nm_kecamatan;
});

const insert = db.prepare(`
    INSERT INTO villages (nm_kelurahan, nm_kecamatan, kd_kelurahan, kd_kecamatan) 
    VALUES (?, ?, ?, ?)
`);

db.transaction(() => {
    // Clear existing to avoid duplicates if re-run
    db.prepare("DELETE FROM villages").run();
    
    for (const feature of villageGeo.features) {
        const p = feature.properties;
        const districtName = districtMap[p.kd_kecamatan] || 'Unknown';
        insert.run(p.nm_kelurahan, districtName, p.kd_kelurahan, p.kd_kecamatan);
    }
})();

console.log(`Imported ${villageGeo.features.length} villages.`);

// Optional: Initial 2025 Budget Mock/Import
// In a real scenario, this would come from an Excel/CSV.
// I'll set some random realistic values for demonstration if needed, 
// or keep it 0 for manual update later.
