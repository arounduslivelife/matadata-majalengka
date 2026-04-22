<?php
$db = new SQLite3('audit_trail.sqlite');
$db->exec('CREATE TABLE IF NOT EXISTS visitors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    name TEXT,
    photo_url TEXT,
    latitude REAL,
    longitude REAL,
    accuracy REAL,
    ip_address TEXT,
    user_agent TEXT,
    visited_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');
echo "visitors table created successfully.\n";
