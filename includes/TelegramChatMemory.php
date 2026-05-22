<?php

/**
 * Riwayat obrolan per chat_id Telegram (maks 3 pasang user+assistant, tidak dicampur antar user).
 */
class TelegramChatMemory
{
    private const MAX_TURNS = 3;

    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? dirname(__DIR__) . '/data/telegram_chat_memory.json';
    }

    /** @return list<array{role:string,content:string}> */
    public function getMessages(string $chatId): array
    {
        $all = $this->readAll();
        $rows = $all[$chatId] ?? [];
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!empty($row['role']) && isset($row['content'])) {
                $out[] = [
                    'role' => $row['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => (string) $row['content'],
                ];
            }
        }
        return $out;
    }

    public function append(string $chatId, string $userText, string $assistantText): void
    {
        $all = $this->readAll();
        $rows = $all[$chatId] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $rows[] = ['role' => 'user', 'content' => mb_substr(trim($userText), 0, 500)];
        $rows[] = ['role' => 'assistant', 'content' => mb_substr(trim($assistantText), 0, 800)];

        $maxMessages = self::MAX_TURNS * 2;
        if (count($rows) > $maxMessages) {
            $rows = array_slice($rows, -$maxMessages);
        }

        $all[$chatId] = $rows;
        $this->writeAll($all);
    }

    public function clear(string $chatId): void
    {
        $all = $this->readAll();
        unset($all[$chatId]);
        $this->writeAll($all);
    }

    /** @return array<string, mixed> */
    private function readAll(): array
    {
        if (!is_readable($this->file)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->file), true);
        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $data */
    private function writeAll(array $data): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
    }
}
