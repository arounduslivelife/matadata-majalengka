<?php
/**
 * Matadata Majalengka - Server Side Audit Processor
 * Menjalankan logika audit (clustering & risk scoring) satu kali di server
 * agar dashboard tidak berat saat dibuka oleh user.
 */

require_once 'db.php';

set_time_limit(600);

echo "<html><body style='font-family:sans-serif; background:#0f172a; color:#f8fafc; padding:20px;'>";
echo "<h2>🧠 Processing Audit Intelligence...</h2>";
echo "<pre style='background:rgba(0,0,0,0.3); padding:15px; border-radius:10px; border:1px solid #334155;'>";

try {
    // 1. Ambil data tahun 2025 (Fokus audit saat ini)
    echo "Fetching realization data for 2025... ";
    $stmt = $pdo->query("SELECT id, satker, vendor, kecamatan, total_nilai FROM realizations WHERE tahun = 2025");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Done (" . count($data) . " records).\n";

    // 2. Logika Clustering (Sama seperti di main.js)
    echo "Analyzing clusters (Satker & Vendor)... \n";
    $clusters = [
        'satker' => [], // [kecamatan][satker] = count
        'vendor' => [], // [kecamatan][vendor] = count
        'global_vendor' => [] // [vendor] = total_count
    ];

    foreach ($data as $p) {
        $kec = $p['kecamatan'];
        $satker = $p['satker'];
        $vendor = $p['vendor'];
        $pagu = $p['total_nilai'];

        // Filter Zona Kritis (180jt - 200jt) untuk deteksi pemecahan paket
        if ($pagu >= 180000000 && $pagu < 200000000) {
            if ($satker) {
                if (!isset($clusters['satker'][$kec])) $clusters['satker'][$kec] = [];
                $clusters['satker'][$kec][$satker] = ($clusters['satker'][$kec][$satker] ?? 0) + 1;
            }
            if ($vendor) {
                if (!isset($clusters['vendor'][$kec])) $clusters['vendor'][$kec] = [];
                $clusters['vendor'][$kec][$vendor] = ($clusters['vendor'][$kec][$vendor] ?? 0) + 1;
            }
        }
        
        // Monopoli Global (semua pagu < 200jt)
        if ($vendor && $pagu < 200000000) {
            $clusters['global_vendor'][$vendor] = ($clusters['global_vendor'][$vendor] ?? 0) + 1;
        }
    }

    // 3. Simpan Hasil Rekapitulasi per Kecamatan ke Tabel Khusus
    echo "Updating risk scores in database... \n";
    
    // Pastikan tabel rekap ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_district_stats (
        kecamatan VARCHAR(100) PRIMARY KEY,
        score_satker INT DEFAULT 0,
        score_vendor INT DEFAULT 0,
        score_monopoly INT DEFAULT 0,
        top_satker_name VARCHAR(255),
        top_vendor_name VARCHAR(255),
        last_updated DATETIME
    )");

    $stmt_update = $pdo->prepare("REPLACE INTO audit_district_stats 
        (kecamatan, score_satker, score_vendor, score_monopoly, top_satker_name, top_vendor_name, last_updated) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())");

    $kecamatans = array_unique(array_column($data, 'kecamatan'));
    foreach ($kecamatans as $kec) {
        if (empty($kec)) continue;

        // Hitung Score 1 (Satker Split)
        $max_satker_count = 0;
        $top_satker = "";
        if (isset($clusters['satker'][$kec])) {
            arsort($clusters['satker'][$kec]);
            $top_satker = key($clusters['satker'][$kec]);
            $max_satker_count = current($clusters['satker'][$kec]);
        }
        $score1 = min(100, $max_satker_count * 25);

        // Hitung Score 2 (Vendor Split)
        $max_vendor_count = 0;
        $top_vendor = "";
        if (isset($clusters['vendor'][$kec])) {
            arsort($clusters['vendor'][$kec]);
            $top_vendor = key($clusters['vendor'][$kec]);
            $max_vendor_count = current($clusters['vendor'][$kec]);
        }
        $score2 = min(100, $max_vendor_count * 30);

        // Hitung Score 3 (Monopoli Global di kec tersebut)
        $score3 = (count($data) > 500) ? 70 : 30; // Dummy logic matching main.js

        $stmt_update->execute([$kec, $score1, $score2, $score3, $top_satker, $top_vendor]);
    }

    echo "✅ Audit processing complete!\n";
    echo "Dashboard now will load pre-calculated results instantly.";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage();
}

echo "</pre>";
echo "</body></html>";
?>
