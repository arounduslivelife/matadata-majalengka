<?php

require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/GroqClient.php';
require_once __DIR__ . '/format_helpers.php';
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
            $years = implode(' & ', MatadataContextBuilder::defaultFocusYears());
            $this->sendMessage($chatId, "Perintah:\n/start — sapaan\n/help — bantuan\n\nData realisasi fokus: {$years}.\n\nContoh pertanyaan:\n• Kecamatan mana realisasi tertinggi 2026?\n• Bandingkan total realisasi 2025 vs 2026\n• Top dana desa 2025?\n• Kecamatan dengan KPM terbanyak?");
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
        $extra = $this->supplementalContext($question);
        $user = "Pertanyaan pengguna:\n{$question}\n\nJawab singkat berdasarkan KONTEKS DATA di system prompt.";
        if ($extra !== '') {
            $user .= "\n\n{$extra}";
        }

        $raw = $groq->chat($system, $user);
        return $this->trimAnswer($raw);
    }

    /** Lookup DB untuk pertanyaan terbesar / nama paket spesifik (di luar ringkasan statis). */
    private function supplementalContext(string $question): string
    {
        $nilai = sqlRealisasiNilai('total_nilai');
        $lines = [];
        $years = MatadataContextBuilder::defaultFocusYears();

        if (preg_match('/2026/u', $question)) {
            $years = [2026];
        } elseif (preg_match('/2025/u', $question)) {
            $years = [2025];
        }

        if (preg_match('/terbesar|tertinggi|paling besar|nominal tertinggi|top\s*1|top\s*paket/ui', $question)) {
            foreach ($years as $year) {
                $stmt = $this->pdo->prepare("
                    SELECT nama_paket, kecamatan, vendor, total_nilai, status
                    FROM realizations WHERE tahun = ?
                    ORDER BY {$nilai} DESC LIMIT 1
                ");
                $stmt->execute([$year]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $lines[] = "Paket #1 terbesar {$year}: {$row['nama_paket']}";
                    $lines[] = 'Nilai: ' . formatRupiah($row['total_nilai'])
                        . " | {$row['kecamatan']} | {$row['vendor']} | {$row['status']}";
                }
            }
        }

        $needle = null;
        if (preg_match('/["「](.+?)["」]/u', $question, $m)) {
            $needle = trim($m[1]);
        } elseif (preg_match('/belanja\s+modal|penanganan\s+long|jalan\s+payung/iu', $question)) {
            $needle = $question;
        }

        if ($needle && mb_strlen($needle) >= 20) {
            $stmt = $this->pdo->prepare("
                SELECT tahun, nama_paket, kecamatan, vendor, total_nilai, status
                FROM realizations
                WHERE nama_paket LIKE ?
                ORDER BY tahun DESC, {$nilai} DESC
                LIMIT 5
            ");
            $stmt->execute(['%' . mb_substr($needle, 0, 80) . '%']);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!in_array((int) $row['tahun'], MatadataContextBuilder::defaultFocusYears(), true)) {
                    continue;
                }
                $lines[] = "Paket cocok ({$row['tahun']}): {$row['nama_paket']}";
                $lines[] = 'Nilai: ' . formatRupiah($row['total_nilai'])
                    . " | {$row['kecamatan']} | {$row['vendor']} | {$row['status']}";
            }
        }

        if (count($lines) === 0) {
            return '';
        }

        return "DATA TAMBAHAN (query langsung database, prioritaskan ini):\n" . implode("\n", $lines);
    }

    private function buildSystemPrompt(): string
    {
        if ($this->dataContext === null) {
            $builder = new MatadataContextBuilder($this->pdo);
            $this->dataContext = $builder->build();
        }

        $extra = trim($this->tg['system_extra'] ?? '');

        $years = implode(', ', MatadataContextBuilder::defaultFocusYears());

        return <<<PROMPT
Anda adalah asisten Telegram untuk dashboard Matadata Kabupaten Majalengka (transparansi anggaran).

TAHUN FOKUS REALISASI: {$years}. Prioritas jawaban untuk tahun 2025 dan 2026. Jika ditanya tahun lain, jelaskan batasan ini.

ATURAN JAWABAN (WAJIB):
- Bahasa Indonesia, santai tapi profesional.
- Maksimal 4–6 kalimat pendek, langsung ke inti.
- Gunakan angka/format dari KONTEKS DATA; jangan mengarang data.
- Untuk "paket terbesar / realisasi tertinggi", pakai baris "#1 paket terbesar" atau Top 5 di konteks (bukan tebakan nama paket).
- Sebut tahun angka jika relevan (2025 atau 2026).
- Jika DATA TAMBAHAN disertakan di pertanyaan, jawab dari situ.
- Jika data tidak ada di konteks maupun DATA TAMBAHAN, katakan jelas "data tidak tersedia di sistem".
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
