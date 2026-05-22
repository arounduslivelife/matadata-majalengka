<?php

class AppConfig
{
    private static ?array $config = null;
    private static string $path;

    public static function path(): string
    {
        if (!isset(self::$path)) {
            self::$path = dirname(__DIR__) . '/config.json';
        }
        return self::$path;
    }

    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $file = self::path();
        if (!is_readable($file)) {
            self::$config = [];
            return self::$config;
        }
        $data = json_decode(file_get_contents($file), true);
        self::$config = is_array($data) ? $data : [];
        return self::$config;
    }

    public static function save(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $ok = file_put_contents(self::path(), $json . "\n", LOCK_EX) !== false;
        if ($ok) {
            self::$config = $config;
        }
        return $ok;
    }

    public static function get(string $key, $default = null)
    {
        $c = self::load();
        return $c[$key] ?? $default;
    }

    public static function telegram(): array
    {
        $c = self::load();
        $tg = $c['telegram'] ?? [];
        return array_merge([
            'enabled' => false,
            'bot_token' => '',
            'webhook_secret' => '',
            'allowed_chat_ids' => [],
            'focus_years' => [2025, 2026],
            'model' => 'llama-3.3-70b-versatile',
            'welcome_message' => "Halo! Saya asisten Matadata Majalengka. Data realisasi fokus tahun 2025 & 2026. Tanya realisasi, dana desa, atau kemiskinan — jawaban singkat & berbasis data.",
            'system_extra' => '',
            'max_tokens' => 400,
            'use_interpreter' => true,
            'use_direct_answers' => true,
            'interpreter_max_tokens' => 280,
            'project_info' => [
                'nama_proyek' => 'Matadata Majalengka',
                'nama_bot' => 'Asisten Matadata (bot Telegram)',
                'pembuat' => 'Tim pengembang dashboard transparansi anggaran Kabupaten Majalengka',
                'tujuan' => 'Monitoring dan transparansi anggaran publik Kabupaten Majalengka (realisasi belanja, Dana Desa, kemiskinan/KPM, infrastruktur, audit pengadaan).',
            ],
        ], is_array($tg) ? $tg : []);
    }

    public static function saveTelegram(array $telegram): bool
    {
        $c = self::load();
        $c['telegram'] = array_merge(self::telegram(), $telegram);
        return self::save($c);
    }

    public static function groqKeys(): array
    {
        $c = self::load();
        $keys = $c['GROQ_API_KEYS'] ?? [];
        if (!is_array($keys)) {
            $keys = [];
        }
        if (empty($keys) && !empty($c['GROQ_API_KEY'])) {
            $keys = [$c['GROQ_API_KEY']];
        }
        return array_values(array_filter($keys));
    }
}
