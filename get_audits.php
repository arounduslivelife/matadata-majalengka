<?php
require_once 'auth.php';
requireAuth();

header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M'); // Increase memory limit just in case

try {
    $db = new SQLite3('database.sqlite', SQLITE3_OPEN_READONLY);
    
    // Check if table exists
    $tableCheck = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='realizations'");
    if (!$tableCheck) {
        throw new Exception("Tabel 'realizations' tidak ditemukan!");
    }

    $audits_query = $db->query("SELECT id, kecamatan, nama_paket, total_nilai, risk_score, audit_note, satker, vendor, status, tahun, lat, lng FROM realizations");
    if (!$audits_query) {
        throw new Exception("Gagal menjalankan query: " . $db->lastErrorMsg());
    }

    // STREAMING JSON START
    echo '[';
    $first = true;
    while ($p = $audits_query->fetchArray(SQLITE3_ASSOC)) {
        if (!$first) echo ',';
        
        $item = [
            'id' => $p['id'],
            'kecamatan' => $p['kecamatan'],
            'nama' => $p['nama_paket'],
            'pagu' => $p['total_nilai'],
            'risk' => $p['risk_score'],
            'note' => $p['audit_note'],
            'satker' => $p['satker'],
            'vendor' => mb_convert_encoding($p['vendor'] ?? '', 'UTF-8', 'UTF-8'),
            'status' => $p['status'],
            'tahun' => $p['tahun'],
            'lat' => $p['lat'],
            'lng' => $p['lng']
        ];
        
        echo json_encode($item, JSON_INVALID_UTF8_SUBSTITUTE);
        $first = false;
        
        // Flush buffer every 1000 rows to keep memory low
        if ($p['id'] % 1000 == 0) {
            flush();
        }
    }
    echo ']';
    // STREAMING JSON END

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
