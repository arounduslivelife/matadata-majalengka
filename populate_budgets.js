const Database = require('better-sqlite3');
const db = new Database('database.sqlite');

const villages = db.prepare("SELECT id FROM villages").all();
const update = db.prepare("UPDATE villages SET budget_2025 = ? WHERE id = ?");

db.transaction(() => {
    for (const v of villages) {
        // Random budget between 800M and 1.3B IDR
        const budget = Math.floor(Math.random() * (1300000000 - 800000000 + 1)) + 800000000;
        update.run(budget, v.id);
    }
})();

console.log(`Updated budget for ${villages.length} villages.`);
