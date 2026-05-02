<?php
echo "<h2>🔍 VPS Diagnostic Tool</h2>";

$files = [
    'database.sqlite',
    'districts.geojson',
    'villages.geojson',
    'assets/js/main.js',
    'data/pad_majalengka.json'
];

foreach ($files as $f) {
    if (file_exists($f)) {
        echo "✅ File <b>$f</b> ditemukan. (Size: " . round(filesize($f)/1024/1024, 2) . " MB)<br>";
    } else {
        echo "❌ File <b>$f</b> TIDAK ADA!<br>";
    }
}

try {
    $db = new SQLite3('database.sqlite');
    echo "✅ Database Koneksi OK.<br>";
    $res = $db->querySingle("SELECT COUNT(*) FROM realizations");
    echo "📊 Jumlah data audit: $res baris.<br>";
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

echo "<br><b>Saran:</b> Jika ada file yang 'TIDAK ADA', silakan upload manual dari localhost Anda.";
?>
