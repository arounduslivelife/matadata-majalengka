<?php
/**
 * Advanced System Fix & Diagnostic Tool v2.0
 * Matadata Majalengka
 */

header('Content-Type: text/html; charset=utf-8');
echo "<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 20px auto; padding: 20px; background: #f0f2f5; }
    .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
    .status { font-weight: bold; padding: 2px 8px; border-radius: 4px; }
    .ok { background: #dcfce7; color: #166534; }
    .error { background: #fee2e2; color: #991b1b; }
    pre { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
    h2 { color: #1e293b; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
</style>";

echo "<h2>🔍 Diagnosa Mendalam Sistem Matadata</h2>";

// 1. PHP ENVIRONMENT
echo "<div class='card'>";
echo "<h3>1. Lingkungan PHP (VPS)</h3>";
$exts = ['sqlite3', 'mbstring', 'json'];
foreach ($exts as $ext) {
    $ok = extension_loaded($ext);
    echo "Ekstensi <b>$ext</b>: " . ($ok ? "<span class='status ok'>AKTIF</span>" : "<span class='status error'>TIDAK ADA</span>") . "<br>";
}
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "</div>";

// 2. FILE SYNC CHECK
echo "<div class='card'>";
echo "<h3>2. Pengecekan Sinkronisasi File</h3>";
$files = [
    'database.sqlite',
    'index.php',
    'assets/js/main.js',
    'includes/sidebar.php'
];
foreach ($files as $f) {
    if (file_exists($f)) {
        $mtime = date("d M Y H:i:s", filemtime($f));
        echo "✅ <b>$f</b> ditemukan (Update terakhir: $mtime)<br>";
    } else {
        echo "❌ <b>$f</b> <span class='status error'>HILANG</span><br>";
    }
}
echo "</div>";

// 3. DATABASE DEEP DIVE
echo "<div class='card'>";
echo "<h3>3. Analisis Data 2024 (Database)</h3>";
try {
    $db = new SQLite3('database.sqlite');
    
    // Check Columns
    $res = $db->query("PRAGMA table_info(packages)");
    $has_pemenang = false;
    while($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === 'pemenang') $has_pemenang = true;
    }
    
    if (!$has_pemenang) {
        echo "❌ <span class='status error'>CRITICAL:</span> Kolom <b>'pemenang'</b> tidak ditemukan di tabel packages!<br>";
    } else {
        echo "✅ Kolom <b>'pemenang'</b> tersedia.<br>";
        
        // Sample Check
        $sample = $db->query("SELECT * FROM packages WHERE tahun = 2024 AND pemenang IS NOT NULL AND pemenang != '' LIMIT 1")->fetchArray(SQLITE3_ASSOC);
        if ($sample) {
            echo "✅ Sampel Data 2024 Berhasil Diambil:<br>";
            echo "Paket: <i>" . $sample['nama_paket'] . "</i><br>";
            echo "Pemenang: <b style='color:#3b82f6;'>" . $sample['pemenang'] . "</b><br>";
        } else {
            echo "⚠️ <span class='status error'>WARNING:</span> Data 2024 ditemukan tapi kolom pemenangnya KOSONG SEMUA di database VPS.<br>";
        }
    }

} catch (Exception $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage();
}
echo "</div>";

// 4. ACTION
echo "<div class='card' style='background: #eff6ff; border: 1px solid #3b82f6;'>";
echo "<h3>🚀 Rekomendasi Tindakan</h3>";
echo "<ol>
    <li>Jika semua indikator di atas hijau (OK), berarti masalahnya adalah <b>Cache Server VPS</b>. Silakan bersihkan cache dari cPanel/HPanel Anda.</li>
    <li>Jika Data 2024 kosong, silakan upload ulang file <b>database.sqlite</b> Anda yang ada di localhost ke VPS.</li>
    <li>Pastikan file <b>index.php</b> sudah di-upload karena file tersebut yang menjembatani data ke tampilan.</li>
</ol>";
echo "<a href='index.php?t=".time()."' style='display:inline-block; padding:12px 24px; background:#3b82f6; color:white; border-radius:8px; text-decoration:none; font-weight:bold;'>BUKA DASHBOARD</a>";
echo "</div>";
?>
