const mysql = require('mysql2/promise');
const fs = require('fs');

async function getVillages() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    const [rows] = await db.query('SELECT id, nm_kelurahan, kd_kecamatan, kd_kelurahan FROM villages');
    console.log(`Found ${rows.length} villages.`);
    
    const villageList = rows.map(v => {
        const kec = parseInt(v.kd_kecamatan).toString().padStart(2, '0');
        const kel = v.kd_kelurahan.toString().padStart(3, '0');
        return {
            id: v.id,
            name: v.nm_kelurahan,
            code: `3210${kec}2${kel}`
        };
    });

    fs.writeFileSync('scratch/village_codes.json', JSON.stringify(villageList, null, 2));
    await db.end();
}

getVillages();
