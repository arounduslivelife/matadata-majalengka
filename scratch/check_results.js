const mysql = require('mysql2/promise');
const fs = require('fs');

async function check() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    console.log('--- Checking Scrape Results ---');
    
    // Check total activities
    const [actCount] = await db.query('SELECT COUNT(*) as total FROM village_activities');
    console.log(`Total activities in database: ${actCount[0].total}`);

    // Check villages with real budget > 0
    const [villagesWithBudget] = await db.query('SELECT COUNT(*) as total FROM villages WHERE budget_real > 0');
    console.log(`Villages with budget_real > 0: ${villagesWithBudget[0].total}`);

    // Sample data from activities
    const [samples] = await db.query('SELECT v.nm_kelurahan, a.uraian, a.anggaran FROM village_activities a JOIN villages v ON a.village_id = v.id LIMIT 5');
    console.log('\nSample Activities:');
    samples.forEach(s => {
        console.log(`- [${s.nm_kelurahan}] ${s.uraian}: Rp ${s.anggaran.toLocaleString()}`);
    });

    await db.end();
}

check();
