const mysql = require('mysql2/promise');
const fs = require('fs');

async function checkStatus() {
    try {
        const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
        const db = await mysql.createConnection({
            host: config.db.host,
            user: config.db.user,
            password: config.db.password || config.db.pass,
            database: config.db.name
        });

        const [villagesWithBudget] = await db.query('SELECT COUNT(*) as count FROM villages WHERE budget_real > 0');
        const [totalVillages] = await db.query('SELECT COUNT(*) as count FROM villages');
        const [totalActivities] = await db.query('SELECT COUNT(*) as count FROM village_activities');
        
        console.log(JSON.stringify({
            villagesWithBudget: villagesWithBudget[0].count,
            totalVillages: totalVillages[0].count,
            totalActivities: totalActivities[0].count
        }, null, 2));

        await db.end();
    } catch (err) {
        console.error('Error:', err.message);
    }
}

checkStatus();
