<?php

require_once __DIR__ . '/AppConfig.php';

/**
 * Identitas bot & tujuan website (bukan data realisasi di MySQL).
 */
class MatadataBotProfile
{
    public static function info(): array
    {
        $tg = AppConfig::telegram();
        $custom = $tg['project_info'] ?? [];
        if (!is_array($custom)) {
            $custom = [];
        }

        return array_merge([
            'nama_proyek' => 'Matadata Majalengka',
            'nama_bot' => 'Asisten Matadata (bot Telegram)',
            'pembuat' => 'Tim pengembang dashboard transparansi anggaran Kabupaten Majalengka',
            'kontak' => 'aroundusplay@gmail.com',
            'repositori' => 'https://github.com/arounduslivelife/matadata-majalengka',
            'tujuan' => 'Menyediakan monitoring dan transparansi anggaran publik Kabupaten Majalengka: realisasi belanja (Inaproc), Dana Desa, kemiskinan/KPM, infrastruktur, serta temuan audit pengadaan — dalam bentuk peta dan ringkasan yang mudah diakses warga.',
            'batasan_bot' => 'Saya menjawab data realisasi tahun 2025–2026, dana desa, dan KPM dari database Matadata. Bukan chatbot umum di luar topik tersebut.',
        ], $custom);
    }

    public static function isMetaQuestion(string $question): bool
    {
        $q = mb_strtolower($question);
        return (bool) preg_match(
            '/siapa\s+(yang\s+)?(membuat|buat|menciptakan|develop)|pembuat|pencipta|'
            . 'tujuan\s+(web|website|situs|dashboard|matadata|ini)|'
            . 'untuk\s+apa\s+(web|website|situs|dashboard|ini)|'
            . 'apa\s+itu\s+matadata|tentang\s+matadata|'
            . 'siapa\s+kamu|kamu\s+siapa|anda\s+siapa|'
            . 'fungsi\s+(web|website|dashboard|bot)|'
            . 'transparansi\s+apa|kegunaan\s+(web|website|dashboard)/iu',
            $q
        );
    }

    public static function buildContextBlock(): string
    {
        $i = self::info();
        $lines = [
            '=== PROFIL PROYEK & BOT (bukan query database realisasi) ===',
            'Nama proyek: ' . $i['nama_proyek'],
            'Nama bot: ' . $i['nama_bot'],
            'Pembuat / pengembang: ' . $i['pembuat'],
            'Kontak: ' . $i['kontak'],
            'Repositori: ' . $i['repositori'],
            'Tujuan website & dashboard: ' . $i['tujuan'],
            'Batasan: ' . $i['batasan_bot'],
        ];
        return implode("\n", $lines);
    }
}
