<?php



require_once __DIR__ . '/AppConfig.php';

require_once __DIR__ . '/GroqClient.php';

require_once __DIR__ . '/format_helpers.php';

require_once __DIR__ . '/MatadataContextBuilder.php';

require_once __DIR__ . '/RealisasiQuestionRouter.php';
require_once __DIR__ . '/MatadataBotProfile.php';
require_once __DIR__ . '/RealisasiFallbackFormatter.php';



class TelegramBotService

{

    private PDO $pdo;

    private array $tg;



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

            $this->sendMessage($chatId, "Perintah:\n/start — sapaan\n/help — bantuan\n/about — tentang Matadata\n\nData realisasi fokus: {$years}.\n\nContoh:\n• Vendor paling banyak proyek 2026?\n• Siapa yang membuat Matadata?\n• Tujuan website ini apa?");

            return;

        }



        if (preg_match('/^\/about\b/i', $text)) {

            $this->sendMessage($chatId, $this->aboutMessage());

            return;

        }



        if (preg_match('/^\/(status|ping)\b/i', $text)) {

            $this->sendMessage($chatId, '✅ Bot Matadata aktif. Groq keys: ' . count(AppConfig::groqKeys()));

            return;

        }



        if (MatadataBotProfile::isPrivateInfoRequest($text)) {

            $this->sendMessage($chatId, MatadataBotProfile::privateInfoDeclineMessage());

            return;

        }



        $this->sendChatAction($chatId, 'typing');



        try {

            $answer = $this->answerQuestion($text);

            $this->sendMessage($chatId, $answer);

        } catch (Throwable $e) {

            error_log('Telegram bot: ' . $e->getMessage());

            $fallback = $this->fallbackAnswer($text, $e);

            $this->sendMessage($chatId, $fallback ?: '⚠️ Maaf, gagal memproses pertanyaan. Coba lagi nanti atau hubungi admin.');

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

        $router = new RealisasiQuestionRouter($this->pdo);

        $dbContext = $router->buildContext($question);



        $groq = new GroqClient();

        $system = $this->buildSystemPrompt();

        $isMeta = MatadataBotProfile::isMetaQuestion($question);

        $user = "Pertanyaan pengguna:\n{$question}\n\n";

        if ($isMeta) {

            $user .= "Pertanyaan tentang proyek/bot/pembuat. Jawab dari PROFIL PROYEK (bukan tabel realisasi).\n\n";

        } else {

            $user .= "Jawab singkat berdasarkan HASIL QUERY DATABASE di bawah (data realisasi 2025/2026 dari MySQL).\n\n";

        }

        $user .= $dbContext;



        $raw = $groq->chat($system, $user);

        return $this->trimAnswer($raw);

    }



    private function buildSystemPrompt(): string

    {

        $extra = trim($this->tg['system_extra'] ?? '');

        $years = implode(', ', MatadataContextBuilder::defaultFocusYears());

        $profile = MatadataBotProfile::buildContextBlock();



        return <<<PROMPT

Anda adalah asisten Telegram Matadata Kabupaten Majalengka (transparansi anggaran).



{$profile}



SUMBER DATA ANGGARAN: Pertanyaan data disertai HASIL QUERY DATABASE (tabel `realizations`, tahun fokus {$years}).

Untuk pertanyaan pembuat, tujuan website, atau "siapa kamu" — jawab hanya dari PROFIL PROYEK; jangan mengarang nama orang.
DILARANG menyebut email, kontak pribadi, URL GitHub, atau repositori — arahkan ke penolakan sopan jika ditanya.



ATURAN JAWABAN (WAJIB):

- Bahasa Indonesia, santai tapi profesional.

- Maksimal 4–8 kalimat atau bullet singkat (max 6 item).

- Data angka: WAJIB dari HASIL QUERY; dilarang mengarang.

- Bedakan: vendor **banyak paket** vs **nilai terbesar** — ikuti label di query.

- Sebut tahun (2025/2026) bila relevan.

- Jika hasil query kosong, katakan "data tidak tersedia di sistem untuk pertanyaan ini".

- Tanpa salam panjang.



{$extra}

PROMPT;

    }



    private function fallbackAnswer(string $question, Throwable $e): ?string
    {
        if (MatadataBotProfile::isMetaQuestion($question)) {
            return $this->aboutMessage();
        }

        if (!RealisasiFallbackFormatter::isQuotaError($e) && !str_contains(mb_strtolower($e->getMessage()), 'groq')) {
            return null;
        }

        $router = new RealisasiQuestionRouter($this->pdo);
        $ctx = $router->buildContext($question);

        return RealisasiFallbackFormatter::format($question, $ctx);
    }

    private function aboutMessage(): string
    {
        $i = MatadataBotProfile::stripPrivateFields(MatadataBotProfile::info());
        return "ℹ️ {$i['nama_proyek']}\n\n"
            . "🎯 Tujuan:\n{$i['tujuan']}\n\n"
            . "👤 Pembuat:\n{$i['pembuat']}\n\n"
            . "Saya {$i['nama_bot']} — tanya data realisasi 2025/2026, dana desa, atau KPM.\n\n"
            . '(Kontak pengembang & repositori tidak dibagikan via bot.)';
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


