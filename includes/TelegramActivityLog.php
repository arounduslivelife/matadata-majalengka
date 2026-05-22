<?php

/**
 * Log pengguna Telegram & 20 percakapan terakhir per chat (untuk panel admin).
 */
class TelegramActivityLog
{
    public const MAX_CONVERSATIONS = 20;

    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? dirname(__DIR__) . '/data/telegram_private/telegram_activity.json';
    }

    /**
     * @param array<string, mixed> $from  message['from']
     * @param array<string, mixed> $chat message['chat']
     */
    public function touchUser(string $chatId, array $from, array $chat, bool $allowed = true): void
    {
        if ($chatId === '') {
            return;
        }

        $data = $this->read();
        $now = date('c');
        $existing = $data['users'][$chatId] ?? [];

        $data['users'][$chatId] = [
            'chat_id' => $chatId,
            'username' => (string) ($from['username'] ?? $existing['username'] ?? ''),
            'first_name' => (string) ($from['first_name'] ?? $existing['first_name'] ?? ''),
            'last_name' => (string) ($from['last_name'] ?? $existing['last_name'] ?? ''),
            'language_code' => (string) ($from['language_code'] ?? $existing['language_code'] ?? ''),
            'is_bot' => !empty($from['is_bot']),
            'chat_type' => (string) ($chat['type'] ?? $existing['chat_type'] ?? 'private'),
            'chat_title' => (string) ($chat['title'] ?? $existing['chat_title'] ?? ''),
            'allowed' => $allowed,
            'first_seen_at' => $existing['first_seen_at'] ?? $now,
            'last_seen_at' => $now,
            'message_count' => (int) ($existing['message_count'] ?? 0) + 1,
        ];

        if (!isset($data['conversations'][$chatId])) {
            $data['conversations'][$chatId] = [];
        }

        $this->write($data);
    }

    public function logExchange(string $chatId, string $question, string $answer): void
    {
        if ($chatId === '') {
            return;
        }

        $data = $this->read();
        $rows = $data['conversations'][$chatId] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $rows[] = [
            'at' => date('c'),
            'question' => mb_substr(trim($question), 0, 1000),
            'answer' => mb_substr(trim($answer), 0, 2000),
        ];

        if (count($rows) > self::MAX_CONVERSATIONS) {
            $rows = array_slice($rows, -self::MAX_CONVERSATIONS);
        }

        $data['conversations'][$chatId] = $rows;
        $this->write($data);
    }

    /** @return list<array<string, mixed>> */
    public function listUsers(): array
    {
        $data = $this->read();
        $users = $data['users'] ?? [];
        if (!is_array($users)) {
            return [];
        }
        $list = array_values($users);
        usort($list, fn ($a, $b) => strcmp($b['last_seen_at'] ?? '', $a['last_seen_at'] ?? ''));
        return $list;
    }

    /** @return list<array{at:string,question:string,answer:string}> */
    public function getConversations(string $chatId, int $limit = self::MAX_CONVERSATIONS): array
    {
        $data = $this->read();
        $rows = $data['conversations'][$chatId] ?? [];
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($rows, -$limit);
    }

    public function getUser(string $chatId): ?array
    {
        $data = $this->read();
        $u = $data['users'][$chatId] ?? null;
        return is_array($u) ? $u : null;
    }

    /** @return array{user_count:int,conversation_count:int,total_messages:int} */
    public function stats(): array
    {
        $data = $this->read();
        $users = is_array($data['users'] ?? null) ? $data['users'] : [];
        $convs = is_array($data['conversations'] ?? null) ? $data['conversations'] : [];
        $totalMessages = 0;
        foreach ($users as $u) {
            $totalMessages += (int) ($u['message_count'] ?? 0);
        }
        return [
            'user_count' => count($users),
            'conversation_count' => count($convs),
            'total_messages' => $totalMessages,
        ];
    }

    public static function displayName(array $user): string
    {
        $parts = array_filter([
            trim($user['first_name'] ?? ''),
            trim($user['last_name'] ?? ''),
        ]);
        $name = implode(' ', $parts);
        if ($name !== '') {
            return $name;
        }
        if (!empty($user['chat_title'])) {
            return (string) $user['chat_title'];
        }
        if (!empty($user['username'])) {
            return '@' . $user['username'];
        }
        return 'Chat ' . ($user['chat_id'] ?? '?');
    }

    /** @return array{users: array<string, mixed>, conversations: array<string, mixed>} */
    private function read(): array
    {
        if (!is_readable($this->file)) {
            return ['users' => [], 'conversations' => []];
        }
        $raw = json_decode(file_get_contents($this->file), true);
        if (!is_array($raw)) {
            return ['users' => [], 'conversations' => []];
        }
        return [
            'users' => is_array($raw['users'] ?? null) ? $raw['users'] : [],
            'conversations' => is_array($raw['conversations'] ?? null) ? $raw['conversations'] : [],
        ];
    }

    /** @param array{users: array<string, mixed>, conversations: array<string, mixed>} $data */
    private function write(array $data): void
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
