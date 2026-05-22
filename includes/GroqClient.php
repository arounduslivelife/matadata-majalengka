<?php

require_once __DIR__ . '/AppConfig.php';

class GroqClient
{
    private int $keyIndex = 0;

    public function chat(string $systemPrompt, string $userMessage, array $options = []): string
    {
        return $this->chatWithHistory($systemPrompt, [], $userMessage, $options);
    }

    /**
     * @param list<array{role:string,content:string}> $history
     */
    public function chatWithHistory(string $systemPrompt, array $history, string $userMessage, array $options = []): string
    {
        $keys = AppConfig::groqKeys();
        if (empty($keys)) {
            throw new RuntimeException('GROQ_API_KEYS belum dikonfigurasi di config.json');
        }

        $tg = AppConfig::telegram();
        $model = $options['model'] ?? $tg['model'] ?? 'llama-3.3-70b-versatile';
        $maxTokens = (int) ($options['max_tokens'] ?? $tg['max_tokens'] ?? 400);
        $temperature = (float) ($options['temperature'] ?? 0.2);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            if (empty($turn['role']) || !isset($turn['content'])) {
                continue;
            }
            $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) $turn['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $lastError = 'Unknown error';
        $attempts = count($keys);

        for ($i = 0; $i < $attempts; $i++) {
            $key = $keys[($this->keyIndex + $i) % $attempts];
            try {
                $text = $this->request($key, $payload);
                $this->keyIndex = ($this->keyIndex + $i + 1) % $attempts;
                return $text;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException($lastError);
    }

    private function request(string $apiKey, array $payload): string
    {
        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Groq cURL: ' . $err);
        }

        $json = json_decode($body, true);
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? substr($body, 0, 200);
            throw new RuntimeException('Groq HTTP ' . $code . ': ' . $msg);
        }

        return trim($json['choices'][0]['message']['content'] ?? '');
    }
}
