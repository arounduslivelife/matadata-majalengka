<?php

/**
 * Jawaban ringkas tanpa Groq — dari blok hasil query database.
 */
class RealisasiFallbackFormatter
{
    public static function format(string $question, string $dbContext): string
    {
        if (strpos($dbContext, 'PROFIL PROYEK') !== false) {
            return self::formatProfile($dbContext);
        }

        $sections = preg_split('/\n(?=\[[^\]]+\])/u', $dbContext) ?: [];
        $parts = [
            '⚠️ Layanan AI Groq sedang tidak tersedia (kuota/rate limit free tier).',
            'Berikut ringkasan langsung dari database:',
            '',
        ];

        $blocks = 0;
        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '' || strpos($section, '===') === 0 || strpos($section, 'Pertanyaan:') === 0) {
                continue;
            }
            $lines = array_filter(explode("\n", $section), fn ($l) => trim($l) !== '' && !preg_match('/^--- TA /', $l));
            if (count($lines) === 0) {
                continue;
            }
            $parts[] = implode("\n", array_slice($lines, 0, 14));
            $parts[] = '';
            $blocks++;
            if ($blocks >= 2) {
                break;
            }
        }

        if ($blocks === 0) {
            return '⚠️ Kuota Groq habis sementara dan tidak ada ringkasan data yang cocok. Coba lagi nanti atau tambah API key di config.json.';
        }

        $parts[] = '💡 Coba lagi beberapa menit, atau tambah akun Groq (GROQ_API_KEYS) di config.json.';
        return trim(implode("\n", $parts));
    }

    private static function formatProfile(string $ctx): string
    {
        $lines = [];
        foreach (explode("\n", $ctx) as $line) {
            if (preg_match('/^(Nama proyek|Pembuat|Tujuan|Batasan):/u', $line)) {
                $lines[] = $line;
            }
        }
        if (count($lines) === 0) {
            return '⚠️ Kuota Groq habis. Matadata adalah dashboard transparansi anggaran Kabupaten Majalengka.';
        }
        return "⚠️ Mode tanpa AI (kuota Groq):\n\n" . implode("\n", $lines);
    }

    public static function isQuotaError(Throwable $e): bool
    {
        $m = mb_strtolower($e->getMessage());
        return str_contains($m, '429')
            || str_contains($m, 'rate limit')
            || str_contains($m, 'quota')
            || str_contains($m, 'tokens per day')
            || str_contains($m, 'insufficient')
            || str_contains($m, 'limit exceeded');
    }
}
