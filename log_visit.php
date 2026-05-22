<?php
require_once 'auth.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$accuracy = $input['accuracy'] ?? null;
$limitedMode = !empty($input['limited_mode']);

$dbPath = __DIR__ . '/audit_trail.sqlite';
if (!file_exists($dbPath)) {
    echo json_encode(['error' => 'Audit database not initialized']);
    exit;
}
$db = new SQLite3($dbPath);

$stmt = $db->prepare('INSERT INTO visitors (email, name, photo_url, latitude, longitude, accuracy, ip_address, user_agent) VALUES (:email, :name, :photo, :lat, :lng, :acc, :ip, :ua)');
$stmt->bindValue(':email', $_SESSION['user_email'], SQLITE3_TEXT);
$stmt->bindValue(':name', $_SESSION['user_name'], SQLITE3_TEXT);
$stmt->bindValue(':photo', $_SESSION['user_photo'], SQLITE3_TEXT);
$stmt->bindValue(':lat', $latitude, SQLITE3_FLOAT);
$stmt->bindValue(':lng', $longitude, SQLITE3_FLOAT);
$stmt->bindValue(':acc', $accuracy, SQLITE3_FLOAT);
$stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
$stmt->bindValue(':ua', $_SERVER['HTTP_USER_AGENT'] ?? '', SQLITE3_TEXT);
$stmt->execute();

if ($latitude !== null && $longitude !== null) {
    $_SESSION['gps_granted'] = true;
    unset($_SESSION['gps_limited_ack']);
} else {
    $_SESSION['gps_granted'] = false;
    if ($limitedMode) {
        $_SESSION['gps_limited_ack'] = true;
    }
}

echo json_encode([
    'success' => true,
    'gps' => !empty($_SESSION['gps_granted']),
    'limited' => !empty($_SESSION['gps_limited_ack'])
]);
