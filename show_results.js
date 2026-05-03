const db = require('./db');

async function run() {
    const [rows] = await db.query(`
        SELECT kecamatan, nama_paket, risk_score, audit_note 
        FROM packages 
        WHERE processed = 1 
        AND (audit_note LIKE '%jalan%' OR audit_note LIKE '%kemantapan%' OR audit_note LIKE '%neglected%')
        LIMIT 10
    `);

    console.log("\n=== CONTOH HASIL AUDIT INFRASTRUKTUR MATADATA ===");
    rows.forEach((row, i) => {
        console.log(`\n[${i+1}] KECAMATAN: ${row.kecamatan}`);
        console.log(`    Paket: ${row.nama_paket}`);
        console.log(`    Risk: ${row.risk_score}`);
        console.log(`    Note: ${row.audit_note}`);
    });
}

run();

