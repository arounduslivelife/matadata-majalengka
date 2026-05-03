<?php
require_once 'db.php';

header('Content-Type: application/json');

$village_id = $_GET['id'] ?? null;
$year = $_GET['year'] ?? 2024;

if (!$village_id) {
    echo json_encode(['error' => 'Village ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM village_activities WHERE village_id = ? AND year = ? ORDER BY anggaran DESC");
$stmt->execute([$village_id, $year]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $activities
]);
