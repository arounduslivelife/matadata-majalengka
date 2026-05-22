<?php

require_once __DIR__ . '/format_helpers.php';

/**
 * Jawaban pasti dari blok query DB — tanpa Groq — untuk pola ya/tidak yang sudah jelas.
 */
class RealisasiDirectAnswer
{
    public static function tryAnswer(string $dbContext): ?string
    {
        if (str_contains($dbContext, '[Pencarian tema + kecamatan')) {
            return self::fromSearchKecamatan($dbContext);
        }
        if (str_contains($dbContext, '[Paket nilai TERBESAR dalam tema/kata kunci')) {
            return self::fromSearchTop($dbContext);
        }
        return null;
    }

    private static function fromSearchKecamatan(string $ctx): ?string
    {
        if (!preg_match('/Kecamatan:\s*(.+)$/mu', $ctx, $km)) {
            return null;
        }
        $kec = trim($km[1]);
        $years = '2026';
        if (preg_match('/Tahun di-query:\s*([0-9,\s]+)/u', $ctx, $ym)) {
            $years = trim($ym[1]);
        }

        if (str_contains($ctx, 'Kesimpulan data: tidak ada')) {
            return "Tidak ada paket yang cocok di Kecamatan {$kec} (tahun {$years}) untuk tema yang Anda tanyakan, menurut data realisasi di sistem.";
        }

        if (!str_contains($ctx, 'Kesimpulan data: ada paket')) {
            return null;
        }

        $theme = 'tema tersebut';
        $count = 0;
        $examples = [];

        if (preg_match_all(
            '/--- TA (\d{4}), kecamatan [^,]+, tema "(.+)" — (\d+) paket cocok ---/u',
            $ctx,
            $rows,
            PREG_SET_ORDER
        )) {
            foreach ($rows as $row) {
                $c = (int) $row[3];
                if ($c > 0) {
                    $count += $c;
                    $theme = $row[2];
                }
            }
        }

        $lines = explode("\n", $ctx);
        for ($i = 0; $i < count($lines); $i++) {
            if (!preg_match('/^- /u', $lines[$i])) {
                continue;
            }
            $nama = trim(mb_substr($lines[$i], 2));
            $detail = $lines[$i + 1] ?? '';
            $nilai = '';
            $vendor = '';
            if (preg_match('/Rp\s+[^\|]+/u', $detail, $nm)) {
                $nilai = trim($nm[0]);
            }
            if (preg_match('/\|\s*([^|]+)\s*\|\s*([^|]+)\s*\|/u', $detail, $vm)) {
                $vendor = trim($vm[2]);
            }
            $examples[] = ['nama' => $nama, 'nilai' => $nilai, 'vendor' => $vendor];
            if (count($examples) >= 3) {
                break;
            }
        }

        if ($count === 0) {
            return null;
        }

        $themeClean = sanitizeRealisasiThemePhrase($theme);
        $out = "Ya, ada {$count} paket terkait \"{$themeClean}\" di Kecamatan {$kec} (tahun {$years}).\n\nContoh:\n";
        foreach ($examples as $ex) {
            $line = '• ' . mb_substr($ex['nama'], 0, 75);
            if ($ex['nilai'] !== '') {
                $line .= ' — ' . $ex['nilai'];
            }
            if ($ex['vendor'] !== '') {
                $line .= ' (' . $ex['vendor'] . ')';
            }
            $out .= $line . "\n";
        }
        return trim($out);
    }

    private static function fromSearchTop(string $ctx): ?string
    {
        if (!preg_match('/TA (\d{4}), tema "(.+)" — (\d+) paket cocok\.\s*#1 nilai terbesar:\s*\n- (.+)$/mu', $ctx, $m)) {
            return null;
        }
        $year = $m[1];
        $theme = sanitizeRealisasiThemePhrase($m[2]);
        $nama = trim($m[4]);
        $nilai = '';
        if (preg_match('/^\s+(Rp\s+[^\n|]+)/mu', $ctx, $nm)) {
            $nilai = trim($nm[1]);
        }
        return "Paket nilai terbesar untuk \"{$theme}\" di tahun {$year}: {$nama}"
            . ($nilai !== '' ? " ({$nilai})" : '') . '.';
    }
}
