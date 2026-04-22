const Database = require('better-sqlite3');
const db = new Database('database.sqlite');
const rows = db.prepare("SELECT kecamatan, COUNT(*) as count, SUM(total_nilai) as total FROM realization_2026 GROUP BY kecamatan ORDER BY total DESC").all();
console.log(JSON.stringify(rows, null, 2));
