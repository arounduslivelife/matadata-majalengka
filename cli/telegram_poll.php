<?php
/**
 * Polling Telegram (development / tanpa HTTPS webhook)
 * Usage: php cli/telegram_poll.php
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/AppConfig.php';
require_once dirname(__DIR__) . '/includes/TelegramBotService.php';

$tg = AppConfig::telegram();
if (empty($tg['bot_token'])) {
    fwrite(STDERR, "bot_token kosong di config.json\n");
    exit(1);
}

$bot = new TelegramBotService($pdo);
$offset = 0;

echo "Polling Telegram bot... Ctrl+C untuk berhenti.\n";

while (true) {
    $ch = curl_init('https://api.telegram.org/bot' . $tg['bot_token'] . '/getUpdates?' . http_build_query([
        'offset' => $offset,
        'timeout' => 25,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($body, true);
    if (empty($json['ok']) || empty($json['result'])) {
        sleep(2);
        continue;
    }

    foreach ($json['result'] as $update) {
        $offset = $update['update_id'] + 1;
        $bot->handleWebhook($update);
        echo '.';
    }
    sleep(1);
}
