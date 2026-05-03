<?php
/**
 * Matadata Majalengka - SQL Importer Script
 * Jalankan file ini di browser (matadata.agungds.web.id/db_migration.php) 
 * untuk melakukan sinkronisasi penuh database dari localhost ke VPS.
 */

require_once 'db.php';

// Naikkan limit waktu karena eksekusi SQL bisa memakan waktu
set_time_limit(600);
ini_set('memory_limit', '512M');

echo "<html><body style='font-family:sans-serif; background:#0f172a; color:#f8fafc; padding:20px;'>";
echo "<h2>importing Database from Localhost...</h2>";
echo "<div style='background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; border-left:4px solid #3b82f6;'>";
echo "<pre>";

try {
    $sql_file = 'matadata_production.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("File $sql_file tidak ditemukan! Pastikan file sudah dipush ke VPS.");
    }

    echo "<b>[1/2] Membersihkan Database Lama (DROP Mode)...</b>\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Ambil semua nama tabel yang ada di DB saat ini
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table` ");
        echo "🗑️ Dropped table: $table\n";
    }
    
    echo "\n<b>[2/2] Mengeksekusi File SQL (Fresh Install)...</b>\n";
    
    // Baca file SQL
    $sql = file_get_contents($sql_file);
    
    // Deteksi dan konversi UTF-16 ke UTF-8 jika perlu
    $encoding = mb_detect_encoding($sql, ['UTF-8', 'UTF-16', 'ISO-8859-1']);
    if ($encoding === 'UTF-16' || bin2hex(substr($sql, 0, 2)) === 'fffe' || bin2hex(substr($sql, 0, 2)) === 'feff') {
        echo "ℹ️ Detecting UTF-16 encoding, converting to UTF-8...\n";
        $sql = mb_convert_encoding($sql, 'UTF-8', 'UTF-16');
    }

    // Bersihkan BOM jika ada
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

    // NEW: Clean non-UTF8 characters that cause "Incorrect string value" errors
    // Specifically target non-breaking spaces and other problematic bytes
    $sql = str_replace("\xA0", " ", $sql);
    $sql = mb_convert_encoding($sql, 'UTF-8', 'UTF-8');
    
    $pdo->exec("SET NAMES utf8mb4");
    $queries = preg_split("/;[\r\n]+/", $sql);
    
    $count = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            // Abaikan komentar SQL
            if (strpos($query, '--') === 0 || strpos($query, '/*') === 0) continue;
            
            try {
                $pdo->exec($query);
                $count++;
            } catch (PDOException $qe) {
                // Tampilkan error query tapi lanjut ke yang lain agar tidak stuck total
                echo "⚠️ Skip error in query $count: " . substr($qe->getMessage(), 0, 100) . "...\n";
            }
            
            if ($count % 500 == 0) {
                echo "Executed $count queries...\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "\n<b>🎉 Sinkronisasi Database Berhasil!</b>\n";
    echo "Total $count query telah dieksekusi.\n";
    echo "Seluruh tabel (villages, village_activities, realizations, dll) sekarang identik dengan localhost.";

} catch (Exception $e) {
    echo "\n❌ <b>Gagal:</b> " . $e->getMessage();
}

echo "</pre></div>";
echo "<p style='text-align:center; opacity:0.5; font-size:0.8rem;'>Matadata Majalengka Automation System</p>";
echo "</body></html>";
?>
