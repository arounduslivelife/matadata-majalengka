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

    echo "<b>[1/2] Membersihkan Database Lama...</b>\n";
    // Opsional: Matikan foreign key check agar lancar
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    echo "<b>[2/2] Mengeksekusi File SQL...</b>\n";
    
    // Baca file SQL
    $sql = file_get_contents($sql_file);
    
    // Pisahkan query berdasarkan titik koma (;) tapi abaikan yang di dalam string
    // Untuk keamanan dan kecepatan pada file besar, kita gunakan cara yang lebih robust
    $queries = explode(";\n", $sql);
    
    $count = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            $count++;
            
            if ($count % 500 == 0) {
                echo "Executed $count queries...\n";
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
