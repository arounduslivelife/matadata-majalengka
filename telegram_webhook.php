<?php

/**
 * Webhook Telegram → Groq + data MySQL Matadata
 * Pasang URL ini dari panel Admin → Bot Telegram
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/AppConfig.php';
require_once __DIR__ . '/includes/TelegramBotService.php';

header('Content-Type: application/json');

$tg = AppConfig::telegram();
$secret = $tg['webhook_secret'] ?? '';
if ($secret !== '') {
    $given = $_GET['secret'] ?? '';
    if (!hash_equals($secret, $given)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

if (!$tg['enabled'] || empty($tg['bot_token'])) {
    echo json_encode(['ok' => true, 'skipped' => 'bot disabled']);
    exit;
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!is_array($update)) {
    echo json_encode(['ok' => true]);
    exit;
}

try {
    $bot = new TelegramBotService($pdo);
    $bot->handleWebhook($update);
} catch (Throwable $e) {
    error_log('telegram_webhook: ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
