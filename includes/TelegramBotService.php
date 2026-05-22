<?php

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/GroqClient.php';
require_once __DIR__ . '/MatadataContextBuilder.php';

class TelegramBotService
{
    private PDO $pdo;
    private array $tg;
    private ?string $dataContext = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tg = AppConfig::telegram();
    }

    public function isEnabled(): bool
    {
        return !empty($this->tg['enabled']) && !empty($this->tg['bot_token']);
    }

    public function handleWebhook(array $update): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message || empty($message['text'])) {
            return;
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim($message['text']);

        if ($chatId === '' || !$this->isChatAllowed($chatId)) {
            $this->sendMessage($chatId, '⛔ Chat ini tidak diizinkan mengakses bot Matadata.');
            return;
        }

        if (preg_match('/^\/start\b/i', $text)) {
            $this->sendMessage($chatId, $this->tg['welcome_message']);
            return;
        }

        if (preg_match('/^\/help\b/i', $text)) {
            $this->sendMessage($chatId, "Perintah:\n/start — sapaan\n/help — bantuan\n\nContoh pertanyaan:\n• Kecamatan mana dana desa tertinggi?\n• Top realisasi 2025?\n• Kecamatan dengan KPM terbanyak?");
            return;
        }

        if (preg_match('/^\/(status|ping)\b/i', $text)) {
            $this->sendMessage($chatId, '✅ Bot Matadata aktif. Groq keys: ' . count(AppConfig::groqKeys()));
            return;
        }

        $this->sendChatAction($chatId, 'typing');

        try {
            $answer = $this->answerQuestion($text);
            $this->sendMessage($chatId, $answer);
        } catch (Throwable $e) {
            error_log('Telegram bot: ' . $e->getMessage());
            $this->sendMessage($chatId, '⚠️ Maaf, gagal memproses pertanyaan. Coba lagi nanti atau hubungi admin.');
        }
    }

    public function isChatAllowed(string $chatId): bool
    {
        $allowed = $this->tg['allowed_chat_ids'] ?? [];
        if (!is_array($allowed) || count($allowed) === 0) {
            return true;
        }
        return in_array($chatId, array_map('strval', $allowed), true);
    }

    public function answerQuestion(string $question): string
    {
        $groq = new GroqClient();
        $system = $this->buildSystemPrompt();
        $user = "Pertanyaan pengguna:\n{$question}\n\nJawab singkat berdasarkan KONTEKS DATA di system prompt.";

        $raw = $groq->chat($system, $user);
        return $this->trimAnswer($raw);
    }

    private function buildSystemPrompt(): string
    {
        if ($this->dataContext === null) {
            $builder = new MatadataContextBuilder($this->pdo);
            $this->dataContext = $builder->build();
        }

        $extra = trim($this->tg['system_extra'] ?? '');

        return <<<PROMPT
Anda adalah asisten Telegram untuk dashboard Matadata Kabupaten Majalengka (transparansi anggaran).

ATURAN JAWABAN (WAJIB):
- Bahasa Indonesia, santai tapi profesional.
- Maksimal 4–6 kalimat pendek, langsung ke inti.
- Gunakan angka/format dari KONTEKS DATA; jangan mengarang data.
- Jika data tidak ada di konteks, katakan jelas "data tidak tersedia di sistem".
- Boleh bullet singkat jika perlu ranking (max 5 item).
- Tanpa salam panjang, tanpa disclaimer panjang.

{$extra}

KONTEKS DATA:
{$this->dataContext}
PROMPT;
    }

    private function trimAnswer(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        if (mb_strlen($text) > 3500) {
            $text = mb_substr($text, 0, 3497) . '...';
        }
        return $text ?: 'Tidak ada jawaban.';
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        return $this->api('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);
    }

    public function sendChatAction(string $chatId, string $action = 'typing'): bool
    {
        return $this->api('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function setWebhook(string $url): array
    {
        return $this->apiRaw('setWebhook', ['url' => $url]);
    }

    public function getWebhookInfo(): array
    {
        return $this->apiRaw('getWebhookInfo', []);
    }

    private function api(string $method, array $params): bool
    {
        $res = $this->apiRaw($method, $params);
        return !empty($res['ok']);
    }

    private function apiRaw(string $method, array $params): array
    {
        $token = $this->tg['bot_token'] ?? '';
        if ($token === '') {
            return ['ok' => false, 'description' => 'bot_token kosong'];
        }

        $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => $params,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($body ?: '{}', true);
        return is_array($json) ? $json : ['ok' => false];
    }
}
