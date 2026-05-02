<?php
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

$host = $config['db']['host'] ?? 'localhost';
$dbname = $config['db']['name'] ?? 'matadata';
$user = $config['db']['user'] ?? 'matadata';
$pass = $config['db']['pass'] ?? 'matadata';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
