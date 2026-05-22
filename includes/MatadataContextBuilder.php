<?php

require_once __DIR__ . '/format_helpers.php';

class MatadataContextBuilder
{
    private PDO $pdo;
    /** @var int[] */
    private array $focusYears;

    public function __construct(PDO $pdo, ?array $focusYears = null)
    {
        $this->pdo = $pdo;
        $this->focusYears = self::normalizeFocusYears($focusYears);
    }

    /** Tahun yang dipakai bot Telegram & konteks AI (default 2025–2026). */
    public static function defaultFocusYears(): array
    {
        if (!class_exists('AppConfig', false)) {
            require_once __DIR__ . '/AppConfig.php';
        }
        $c = AppConfig::load();
        $fromTg = $c['telegram']['focus_years'] ?? null;
        if (is_array($fromTg) && count($fromTg) > 0) {
            return self::normalizeFocusYears($fromTg);
        }
        if (!empty($c['TELEGRAM_FOCUS_YEARS']) && is_array($c['TELEGRAM_FOCUS_YEARS'])) {
            return self::normalizeFocusYears($c['TELEGRAM_FOCUS_YEARS']);
        }
        return [2025, 2026];
    }

    /** @param int[]|int|null $years */
    public static function normalizeFocusYears($years): array
    {
        if ($years === null) {
            return self::defaultFocusYears();
        }
        if (is_int($years) || is_string($years)) {
            $years = [(int) $years];
        }
        if (!is_array($years)) {
            return self::defaultFocusYears();
        }
        $out = [];
        foreach ($years as $y) {
            $y = (int) $y;
            if ($y >= 2000 && $y <= 2100) {
                $out[$y] = $y;
            }
        }
        if (count($out) === 0) {
            return self::defaultFocusYears();
        }
        ksort($out);
        return array_values($out);
    }

    public function getFocusYears(): array
    {
        return $this->focusYears;
    }

    public function build(): string
    {
        $parts = [];
        $parts[] = '=== MATADATA KABUPATEN MAJALENGKA (konteks untuk jawaban bot) ===';
        $parts[] = 'Tahun fokus realisasi: ' . implode(', ', $this->focusYears);
        $parts[] = 'Jika pengguna menyebut tahun lain di luar daftar, jelaskan data hanya untuk tahun fokus di atas.';
        $parts[] = 'Waktu generate: ' . date('Y-m-d H:i') . ' WIB';
        $parts[] = '';

        $parts[] = $this->sectionRealisasi();
        $parts[] = $this->sectionDanaDesa();
        $parts[] = $this->sectionKemiskinan();
        $parts[] = $this->sectionAudit();

        $text = implode("\n", $parts);
        if (strlen($text) > 14000) {
            $text = substr($text, 0, 14000) . "\n...[konteks dipotong]";
        }
        return $text;
    }

    private function sectionRealisasi(): string
    {
        $lines = ['## REALISASI BELANJA (tabel realizations)'];
        $nilai = sqlRealisasiNilai('total_nilai');

        foreach ($this->focusYears as $year) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS cnt, COALESCE(SUM({$nilai}), 0) AS total
                FROM realizations WHERE tahun = ?
            ");
            $stmt->execute([$year]);
            $tot = $stmt->fetch(PDO::FETCH_ASSOC);
            $lines[] = "Total TA {$year}: " . number_format((int) $tot['cnt']) . ' paket, ' . formatRupiah($tot['total']);

            $stmt = $this->pdo->prepare("
                SELECT nama_paket, kecamatan, total_nilai, vendor, status
                FROM realizations WHERE tahun = ?
                ORDER BY {$nilai} DESC LIMIT 1
            ");
            $stmt->execute([$year]);
            $top1 = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($top1) {
                $lines[] = "#1 paket terbesar {$year}: {$top1['nama_paket']}";
                $lines[] = '   Nilai: ' . formatRupiah($top1['total_nilai'])
                    . " | {$top1['kecamatan']} | vendor: {$top1['vendor']} | status: {$top1['status']}";
            }

            $stmt = $this->pdo->prepare("
                SELECT kecamatan, COUNT(*) AS cnt, SUM({$nilai}) AS pagu
                FROM realizations WHERE tahun = ?
                GROUP BY kecamatan ORDER BY pagu DESC LIMIT 8
            ");
            $stmt->execute([$year]);
            $lines[] = "Ranking kecamatan (realisasi {$year}, top 8):";
            $rank = 1;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lines[] = "{$rank}. {$row['kecamatan']}: " . formatRupiah($row['pagu']) . " ({$row['cnt']} paket)";
                $rank++;
            }

            $stmt = $this->pdo->prepare("
                SELECT nama_paket, kecamatan, total_nilai, vendor
                FROM realizations WHERE tahun = ? ORDER BY {$nilai} DESC LIMIT 5
            ");
            $stmt->execute([$year]);
            $lines[] = "Top 5 paket terbesar {$year} (urutan nilai numerik):";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $nama = mb_substr($row['nama_paket'], 0, 90);
                $lines[] = "- {$nama} | {$row['kecamatan']} | " . formatRupiah($row['total_nilai']);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function sectionDanaDesa(): string
    {
        $lines = ['## DANA DESA (tabel villages, budget_2025 = alokasi 2025)'];

        $total = (float) $this->pdo->query('SELECT COALESCE(SUM(budget_2025),0) FROM villages')->fetchColumn();
        $desa = (int) $this->pdo->query('SELECT COUNT(*) FROM villages')->fetchColumn();
        $lines[] = 'Total alokasi 2025 seluruh desa: ' . formatRupiah($total) . " ({$desa} desa/kelurahan)";

        $stmt = $this->pdo->query("
            SELECT nm_kecamatan, SUM(budget_2025) AS total, COUNT(*) AS jml_desa
            FROM villages GROUP BY nm_kecamatan ORDER BY total DESC LIMIT 12
        ");
        $lines[] = 'Ranking kecamatan (dana desa 2025):';
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = "{$rank}. {$row['nm_kecamatan']}: " . formatRupiah($row['total']) . " ({$row['jml_desa']} desa)";
            $rank++;
        }

        $stmt = $this->pdo->query("
            SELECT nm_kelurahan, nm_kecamatan, budget_2025
            FROM villages ORDER BY budget_2025 DESC LIMIT 5
        ");
        $lines[] = 'Top 5 desa alokasi tertinggi 2025:';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = "- {$row['nm_kelurahan']} ({$row['nm_kecamatan']}): " . formatRupiah($row['budget_2025']);
        }

        return implode("\n", $lines);
    }

    private function sectionKemiskinan(): string
    {
        $lines = ['## KEMISKINAN / KPM BANSOS (tabel district_stats)'];

        $total = (int) $this->pdo->query('SELECT COALESCE(SUM(poverty_count),0) FROM district_stats')->fetchColumn();
        $lines[] = 'Total KPM (indikator poverty_count) Kab. Majalengka: ' . number_format($total, 0, ',', '.');

        $stmt = $this->pdo->query('
            SELECT nm_kecamatan, poverty_count, kpm_pkh, kpm_bpnt
            FROM district_stats ORDER BY poverty_count DESC LIMIT 12
        ');
        $lines[] = 'Ranking kecamatan (jumlah KPM):';
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = "{$rank}. {$row['nm_kecamatan']}: " . number_format((int) $row['poverty_count'], 0, ',', '.') . ' KPM';
            $rank++;
        }

        return implode("\n", $lines);
    }

    private function sectionAudit(): string
    {
        $lines = ['## AUDIT / RISIKO (jika ada)'];
        try {
            $stmt = $this->pdo->query('SELECT kecamatan, total_packages, high_risk_count FROM audit_district_stats ORDER BY high_risk_count DESC LIMIT 8');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lines[] = "- {$row['kecamatan']}: {$row['high_risk_count']} paket risiko tinggi / {$row['total_packages']} total";
            }
        } catch (Throwable $e) {
            $lines[] = '(Data audit_district_stats belum tersedia)';
        }
        return implode("\n", $lines);
    }
}
