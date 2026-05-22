<?php

/** Ekspresi SQL: total_nilai disimpan sebagai TEXT, harus di-CAST untuk SUM/ORDER BY numerik. */
function sqlRealisasiNilai(string $column = 'total_nilai'): string
{
    return 'CAST(NULLIF(TRIM(' . $column . "), '') AS DECIMAL(20,2))";
}

/** Normalisasi nilai dari JSON interpreter (kadang array → string "Array" di PHP). */
function normalizeScalarString(mixed $value): ?string
{
    if (is_array($value)) {
        $value = $value[0] ?? null;
    }
    if ($value === null || is_bool($value)) {
        return null;
    }
    $s = trim((string) $value);
    if ($s === '' || strcasecmp($s, 'Array') === 0) {
        return null;
    }
    return $s;
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

/**
 * Perluas kata kunci untuk typo/singkatan umum pengguna (tanpa Groq).
 *
 * @param string[] $keywords
 * @return string[]
 */
function expandRealisasiThemeKeywords(array $keywords): array
{
    $out = [];
    foreach ($keywords as $kw) {
        $kw = trim($kw);
        if ($kw === '') {
            continue;
        }
        $out[] = $kw;
        $low = mb_strtolower($kw);
        if (preg_match('/makan\s+rapat/u', $low) && !str_contains($low, 'makanan')) {
            $out[] = 'makanan rapat';
        }
        if (preg_match('/makan\s+tamu/u', $low)) {
            $out[] = 'makanan tamu';
            $out[] = 'jamuan tamu';
        }
        if (preg_match('/makan\s+rapat/u', $low) === 0 && str_contains($low, 'rapat') && !str_contains($low, 'makanan')) {
            $out[] = 'makanan rapat';
        }
    }
    return array_values(array_unique($out));
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
