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
            'tujuan' => 'Menyediakan monitoring dan transparansi anggaran publik Kabupaten Majalengka: realisasi belanja (Inaproc), Dana Desa, kemiskinan/KPM, infrastruktur, serta temuan audit pengadaan — dalam bentuk peta dan ringkasan yang mudah diakses warga.',
            'batasan_bot' => 'Saya menjawab data realisasi tahun 2025–2026, dana desa, dan KPM dari database Matadata. Bukan chatbot umum di luar topik tersebut.',
        ], $custom);
    }

    /** Pertanyaan kontak/email/github — tidak dibocorkan lewat bot. */
    public static function isPrivateInfoRequest(string $question): bool
    {
        $q = mb_strtolower($question);
        return (bool) preg_match(
            '/kontak|email|e-?mail|hubungi\s+(kami|admin|pembuat|developer)|'
            . 'github|repositori|repo\s|source\s*code|kode\s*sumber|'
            . 'link\s*(github|repo)|alamat\s*email/ui',
            $q
        );
    }

    public static function privateInfoDeclineMessage(): string
    {
        return 'Informasi kontak pengembang dan repositori sumber tidak dibagikan melalui bot Telegram. '
            . 'Silakan gunakan kanal resmi pemerintah daerah atau akses dashboard Matadata untuk layanan publik.';
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

    /** Field yang tidak boleh dikirim ke AI / pengguna via bot. */
    public static function stripPrivateFields(array $info): array
    {
        unset($info['kontak'], $info['repositori'], $info['email'], $info['github']);

        return $info;
    }

    public static function buildContextBlock(): string
    {
        $i = self::stripPrivateFields(self::info());
        $lines = [
            '=== PROFIL PROYEK & BOT (bukan query database realisasi) ===',
            'Nama proyek: ' . $i['nama_proyek'],
            'Nama bot: ' . $i['nama_bot'],
            'Pembuat / pengembang: ' . $i['pembuat'],
            'Tujuan website & dashboard: ' . $i['tujuan'],
            'Batasan: ' . $i['batasan_bot'],
            'RAHASIA (jangan sebut ke pengguna): kontak email dan URL repositori GitHub tidak dibagikan via bot.',
        ];
        return implode("\n", $lines);
    }
}
