const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

// Load config
const configPath = path.join(__dirname, 'config.json');
const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));

const pool = mysql.createPool({
  host: config.db.host || 'localhost',
  user: config.db.user || 'matadata',
  password: config.db.pass || 'matadata',
  database: config.db.name || 'matadata',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

module.exports = pool;
