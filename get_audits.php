<?php
require_once 'auth.php';
requireAuth();
require_once 'db.php';

header('Content-Type: application/json');

ini_set('display_errors', 0); // Disable for JSON output to prevent corruption
error_reporting(E_ALL);
ini_set('memory_limit', '512M');

// Fallback for older PHP versions
if (!defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    define('JSON_INVALID_UTF8_SUBSTITUTE', 0);
}


try {
    $stmt = $pdo->prepare("SELECT id, kecamatan, nama_paket, total_nilai, risk_score, audit_note, satker, vendor, status, tahun, lat, lng FROM realizations");
    $stmt->execute();

    // Clear any previous output (warnings/notices)
    if (ob_get_length()) ob_clean();

    echo '[';
    $first = true;
    while ($p = $stmt->fetch()) {
        if (!$first) echo ',';
        
        $item = [
            'id' => $p['id'],
            'kecamatan' => $p['kecamatan'],
            'nama' => $p['nama_paket'],
            'pagu' => $p['total_nilai'],
            'risk' => $p['risk_score'],
            'note' => $p['audit_note'],
            'satker' => $p['satker'],
            'vendor' => $p['vendor'],
            'status' => $p['status'],
            'tahun' => $p['tahun'],
            'lat' => $p['lat'],
            'lng' => $p['lng']
        ];
        
        echo json_encode($item, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        $first = false;
        
        // Optional: Flush every 1000 rows
        if ($p['id'] % 1000 == 0) {
            flush();
        }
    }
    echo ']';

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
