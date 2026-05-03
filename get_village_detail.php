<?php
header('Content-Type: application/json');
require_once 'db.php';

$village_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 2024;

if (!$village_id) {
    echo json_encode(['error' => 'ID Desa tidak ditemukan']);
    exit;
}

try {

    // Get Village Summary
    $stmt = $pdo->prepare("SELECT id, nm_kelurahan, budget_real, budget_2025 FROM villages WHERE id = ?");
    $stmt->execute([$village_id]);
    $village = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$village) {
        echo json_encode(['error' => 'Desa tidak ditemukan']);
        exit;
    }

    // Get Activities
    $stmt = $pdo->prepare("SELECT uraian, volume, output, anggaran FROM village_activities WHERE village_id = ? AND year = ? ORDER BY anggaran DESC");
    $stmt->execute([$village_id, $year]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'village' => $village,
        'year' => $year,
        'pagu' => ($year == 2025) ? $village['budget_2025'] : $village['budget_real'],
        'activities' => $activities
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
