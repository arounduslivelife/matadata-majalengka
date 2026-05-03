<?php
/**
 * Matadata Majalengka - Database Migration & Data Importer
 * Jalankan file ini di browser (matadata.agungds.web.id/db_migration.php) 
 */

require_once 'db.php';

// Naikkan limit memori dan waktu eksekusi karena data JSON cukup besar
ini_set('memory_limit', '256M');
set_time_limit(300);

echo "<html><body style='font-family:sans-serif; background:#0f172a; color:#f8fafc; padding:20px;'>";
echo "<h2>🚀 Matadata System Update</h2>";
echo "<div style='background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; border-left:4px solid #3b82f6;'>";
echo "<pre>";

try {
    // --- STEP 1: MIGRATION ---
    echo "<b>[1/3] Memeriksa Struktur Tabel...</b>\n";
    
    $columns_to_check = [
        'budget_real' => "DECIMAL(20,2) DEFAULT 0",
        'budget_2025' => "DECIMAL(20,2) DEFAULT 0"
    ];

    foreach ($columns_to_check as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM villages LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE villages ADD COLUMN $col $definition");
            echo "✅ Kolom '$col' ditambahkan ke tabel 'villages'.\n";
        }
    }

    $sql_activities = "CREATE TABLE IF NOT EXISTS village_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        village_id INT NOT NULL,
        year INT NOT NULL,
        uraian TEXT,
        volume VARCHAR(255),
        output VARCHAR(255),
        anggaran DECIMAL(20,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_village_year (village_id, year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql_activities);
    echo "✅ Tabel 'village_activities' siap.\n";

    // --- STEP 2: LOAD JSON ---
    echo "\n<b>[2/3] Membaca File JSON...</b>\n";
    $json_file = 'final_dana_desa_2024_2025.json';
    if (!file_exists($json_file)) {
        throw new Exception("File $json_file tidak ditemukan! Pastikan file sudah dipush ke VPS.");
    }
    
    $data = json_decode(file_get_contents($json_file), true);
    if (!$data) {
        throw new Exception("Gagal mem-parsing JSON. Format file mungkin rusak.");
    }
    echo "✅ Berhasil memuat " . count($data) . " data desa.\n";

    // --- STEP 3: IMPORT DATA ---
    echo "\n<b>[3/3] Mengimpor Data ke MySQL...</b>\n";
    
    // Siapkan statement agar cepat
    $update_village = $pdo->prepare("UPDATE villages SET budget_real = ?, budget_2025 = ? WHERE nm_kelurahan = ?");
    $delete_activities = $pdo->prepare("DELETE FROM village_activities WHERE village_id = ?");
    $insert_activity = $pdo->prepare("INSERT INTO village_activities (village_id, year, uraian, volume, output, anggaran) VALUES (?, ?, ?, ?, ?, ?)");

    $pdo->beginTransaction();
    
    $count_villages = 0;
    $count_activities = 0;

    foreach ($data as $item) {
        $village_name = $item['village'];
        $pagu_2024 = $item['data_2024']['pagu'] ?? 0;
        $pagu_2025 = $item['data_2025']['pagu'] ?? 0;

        // 1. Update budget di tabel villages
        $update_village->execute([$pagu_2024, $pagu_2025, $village_name]);
        
        if ($update_village->rowCount() > 0) {
            // Dapatkan ID desa untuk relasi
            $stmt = $pdo->prepare("SELECT id FROM villages WHERE nm_kelurahan = ?");
            $stmt->execute([$village_name]);
            $v_id = $stmt->fetchColumn();

            if ($v_id) {
                $count_villages++;
                
                // Bersihkan data lama agar tidak dobel
                $delete_activities->execute([$v_id]);

                // 2. Simpan rincian kegiatan 2024
                if (!empty($item['data_2024']['activities'])) {
                    foreach ($item['data_2024']['activities'] as $act) {
                        $insert_activity->execute([$v_id, 2024, $act['uraian'], $act['volume'], $act['output'], $act['anggaran']]);
                        $count_activities++;
                    }
                }

                // 3. Simpan rincian kegiatan 2025
                if (!empty($item['data_2025']['activities'])) {
                    foreach ($item['data_2025']['activities'] as $act) {
                        $insert_activity->execute([$v_id, 2025, $act['uraian'], $act['volume'], $act['output'], $act['anggaran']]);
                        $count_activities++;
                    }
                }
            }
        }
    }

    $pdo->commit();
    echo "✅ Berhasil sinkronisasi $count_villages desa.\n";
    echo "✅ Berhasil mengimpor $count_activities rincian kegiatan.\n";

    echo "\n<b>🎉 Update Selesai Sempurna!</b>\n";
    echo "Dashboard sekarang sudah memiliki data terbaru.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "\n❌ <b>Gagal:</b> " . $e->getMessage();
}

echo "</pre></div>";
echo "<p style='text-align:center; opacity:0.5; font-size:0.8rem;'>Matadata Majalengka Automation System</p>";
echo "</body></html>";
?>
