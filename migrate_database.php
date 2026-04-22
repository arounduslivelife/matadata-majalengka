<?php
require_once 'auth.php';
requireAuth();
if (!isAdmin()) { header('Location: index.php'); exit; }

echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Database Migration — Matadata</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Outfit", sans-serif; background: #020617; color: #f8fafc; padding: 2rem; line-height: 1.6; }
        .card { background: #0f172a; padding: 2rem; border-radius: 20px; max-width: 600px; margin: 0 auto; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        h1 { color: #3b82f6; font-size: 1.5rem; margin-bottom: 1rem; }
        .log { background: rgba(0,0,0,0.3); padding: 1rem; border-radius: 10px; font-family: monospace; font-size: 0.85rem; overflow-y: auto; max-height: 300px; margin-top: 1rem; }
        .success { color: #10b981; font-weight: 600; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .btn { display: inline-block; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; margin-top: 2rem; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <h1>🚀 Database Migration Tool</h1>
    <p>Migrasi tabel <code>visitors</code> ke <code>audit_trail.sqlite</code>...</p>
    <div class="log">';

function logMsg($msg, $type = 'info') {
    $class = ($type === 'success') ? 'success' : (($type === 'error') ? 'error' : 'warning');
    echo "<div class='$class'>[" . strtoupper($type) . "] $msg</div>";
}

$oldDbFile = 'database.sqlite';
$newDbFile = 'audit_trail.sqlite';

if (!file_exists($oldDbFile)) {
    logMsg("File database lama tidak ditemukan.", "error");
} else {
    try {
        $oldDb = new SQLite3($oldDbFile);
        $newDb = new SQLite3($newDbFile);

        // 1. Setup New Database
        $newDb->exec('CREATE TABLE IF NOT EXISTS visitors (
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
        logMsg("Database baru berhasil diinisialisasi.", "success");

        // 2. Check if old table exists
        $tableExists = $oldDb->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='visitors'");
        
        if ($tableExists) {
            logMsg("Tabel lama ditemukan. Memulai pemindahan data...");
            
            // Get count
            $count = $oldDb->querySingle("SELECT COUNT(*) FROM visitors");
            
            if ($count > 0) {
                // Transfer data
                $results = $oldDb->query("SELECT * FROM visitors");
                $newDb->exec('BEGIN TRANSACTION');
                $stmt = $newDb->prepare('INSERT INTO visitors (email, name, photo_url, latitude, longitude, accuracy, ip_address, user_agent, visited_at) VALUES (:email, :name, :photo, :lat, :lng, :acc, :ip, :ua, :time)');
                
                while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                    $stmt->bindValue(':email', $row['email'], SQLITE3_TEXT);
                    $stmt->bindValue(':name', $row['name'], SQLITE3_TEXT);
                    $stmt->bindValue(':photo', $row['photo_url'], SQLITE3_TEXT);
                    $stmt->bindValue(':lat', $row['latitude'], SQLITE3_FLOAT);
                    $stmt->bindValue(':lng', $row['longitude'], SQLITE3_FLOAT);
                    $stmt->bindValue(':acc', $row['accuracy'], SQLITE3_FLOAT);
                    $stmt->bindValue(':ip', $row['ip_address'], SQLITE3_TEXT);
                    $stmt->bindValue(':ua', $row['user_agent'], SQLITE3_TEXT);
                    $stmt->bindValue(':time', $row['visited_at'], SQLITE3_TEXT);
                    $stmt->execute();
                }
                $newDb->exec('COMMIT');
                logMsg("$count data berhasil dipindahkan.", "success");
            } else {
                logMsg("Tidak ada data untuk dipindahkan.", "warning");
            }

            // 3. Drop Old Table
            logMsg("Menghapus tabel dari database lama untuk optimasi...");
            $oldDb->exec('DROP TABLE visitors');
            $oldDb->exec('VACUUM');
            logMsg("Pembersihan database selesai.", "success");
        } else {
            logMsg("Tabel lama sudah tidak ada atau sudah pernah dimigrasi.", "warning");
        }

        logMsg("🎉 Migrasi Selesai!", "success");

    } catch (Exception $e) {
        logMsg("Terjadi kesalahan: " . $e->getMessage(), "error");
    }
}

echo '    </div>
    <a href="visitors.php" class="btn">Lihat Dashboard Log Baru</a>
</div>
</body>
</html>';
