<?php

/** Ekspresi SQL: total_nilai disimpan sebagai TEXT, harus di-CAST untuk SUM/ORDER BY numerik. */
function sqlRealisasiNilai(string $column = 'total_nilai'): string
{
    return 'CAST(NULLIF(TRIM(' . $column . "), '') AS DECIMAL(20,2))";
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
