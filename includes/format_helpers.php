<?php

/** Ekspresi SQL: total_nilai disimpan sebagai TEXT, harus di-CAST untuk SUM/ORDER BY numerik. */
function sqlRealisasiNilai(string $column = 'total_nilai'): string
{
    return 'CAST(NULLIF(TRIM(' . $column . "), '') AS DECIMAL(20,2))";
}

/** Bersihkan frasa tema dari sisa "di tahun 2026", "di kecamatan X", dll. */
function sanitizeRealisasiThemePhrase(string $phrase): string
{
    $phrase = trim($phrase);
    $phrase = preg_replace('/\s*,\s*di\s+(?:kecamatan|kec\.?).+$/iu', '', $phrase);
    $phrase = preg_replace('/\s+di\s+(?:kecamatan|kec\.?).+$/iu', '', $phrase);
    $phrase = preg_replace('/\s+di\s+tahun\s+20\d{2}.*$/iu', '', $phrase);
    $phrase = preg_replace('/\s+tahun\s+20\d{2}.*$/iu', '', $phrase);
    $phrase = preg_replace('/\s+di\s*$/u', '', $phrase);
    return trim($phrase);
}

function formatRupiah($amount): string
{
    $p = (float) $amount;
    if ($p >= 1_000_000_000_000) {
        return 'Rp ' . number_format($p / 1_000_000_000_000, 2, ',', '.') . ' T';
    }
    if ($p >= 1_000_000_000) {
        return 'Rp ' . number_format($p / 1_000_000_000, 2, ',', '.') . ' Miliar';
    }
    if ($p >= 1_000_000) {
        return 'Rp ' . number_format($p / 1_000_000, 1, ',', '.') . ' Juta';
    }
    return 'Rp ' . number_format($p, 0, ',', '.');
}
