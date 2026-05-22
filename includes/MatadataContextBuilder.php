<?php

require_once __DIR__ . '/format_helpers.php';

class MatadataContextBuilder
{
    private PDO $pdo;
    private int $focusYear;

    public function __construct(PDO $pdo, ?int $focusYear = null)
    {
        $this->pdo = $pdo;
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config.json'), true);
        $this->focusYear = $focusYear ?? (int) ($config['TARGET_YEAR'] ?? 2025);
    }

    public function build(): string
    {
        $parts = [];
        $parts[] = '=== MATADATA KABUPATEN MAJALENGKA (konteks untuk jawaban bot) ===';
        $parts[] = 'Tahun fokus realisasi: ' . $this->focusYear;
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

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(SUM(total_nilai),0) AS total
            FROM realizations WHERE tahun = ?
        ");
        $stmt->execute([$this->focusYear]);
        $tot = $stmt->fetch(PDO::FETCH_ASSOC);
        $lines[] = "Total TA {$this->focusYear}: " . number_format((int) $tot['cnt']) . ' paket, ' . formatRupiah($tot['total']);

        $stmt = $this->pdo->prepare("
            SELECT kecamatan, COUNT(*) AS cnt, SUM(total_nilai) AS pagu
            FROM realizations WHERE tahun = ?
            GROUP BY kecamatan ORDER BY pagu DESC LIMIT 12
        ");
        $stmt->execute([$this->focusYear]);
        $lines[] = 'Ranking kecamatan (realisasi ' . $this->focusYear . '):';
        $rank = 1;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = "{$rank}. {$row['kecamatan']}: " . formatRupiah($row['pagu']) . " ({$row['cnt']} paket)";
            $rank++;
        }

        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, total_nilai, vendor
            FROM realizations WHERE tahun = ? ORDER BY total_nilai DESC LIMIT 5
        ");
        $stmt->execute([$this->focusYear]);
        $lines[] = 'Top 5 paket terbesar:';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $nama = mb_substr($row['nama_paket'], 0, 80);
            $lines[] = "- {$nama} | {$row['kecamatan']} | " . formatRupiah($row['total_nilai']);
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
