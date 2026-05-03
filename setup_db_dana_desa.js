const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

async function setup() {
    const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
    
    const db = await mysql.createConnection({
        host: config.db.host,
        user: config.db.user,
        password: config.db.pass,
        database: config.db.name
    });

    try {
        console.log('Updating villages table...');
        // MySQL 8+ syntax for ADD COLUMN IF NOT EXISTS is not standard, so we use a procedure or just try catch
        try {
            await db.query('ALTER TABLE villages ADD COLUMN budget_real DECIMAL(20,2) DEFAULT 0');
        } catch (e) {
            if (!e.message.includes('Duplicate column name')) throw e;
        }

        try {
            await db.query('ALTER TABLE villages ADD COLUMN status_idm VARCHAR(50)');
        } catch (e) {
            if (!e.message.includes('Duplicate column name')) throw e;
        }

        console.log('Creating village_activities table...');
        await db.query(`
            CREATE TABLE IF NOT EXISTS village_activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                village_id INT,
                year INT,
                uraian TEXT,
                volume TEXT,
                output TEXT,
                anggaran DECIMAL(20,2)
            )
        `);

        console.log('Database setup complete.');
    } catch (error) {
        console.error('Error during setup:', error);
    } finally {
        await db.end();
    }
}

setup();
