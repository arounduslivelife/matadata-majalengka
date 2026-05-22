<?php

require_once __DIR__ . '/RealisasiQueryService.php';
require_once __DIR__ . '/MatadataContextBuilder.php';
require_once __DIR__ . '/MatadataBotProfile.php';

/**
 * Menganalisis pertanyaan → menjalankan query DB realisasi 2025/2026 → konteks untuk AI.
 */
class RealisasiQuestionRouter
{
    private PDO $pdo;
    private RealisasiQueryService $db;
    /** @var array<string, mixed>|null */
    private ?array $interpretation = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->db = new RealisasiQueryService($pdo);
    }

    /**
     * @param array<string, mixed>|null $interpretation Hasil QuestionInterpreter::interpret()
     */
    public function buildContext(string $question, ?array $interpretation = null): string
    {
        $this->interpretation = $interpretation;

        if (MatadataBotProfile::isMetaQuestion($question)
            || in_array('meta_project', $interpretation['intents'] ?? [], true)) {
            return MatadataBotProfile::buildContextBlock();
        }

        $years = $this->resolveYears($question);
        $intents = $this->resolveIntents($question);
        if (in_array('search_top_package', $intents, true)) {
            $intents = array_values(array_unique(array_diff(
                $intents,
                ['top_packages', 'search_packages', 'search_text']
            )));
        }
        if ($this->shouldSearchKecamatan($question, $intents)) {
            $intents = array_values(array_unique(array_merge(
                array_diff($intents, [
                    'search_packages', 'search_text', 'kecamatan_detail', 'search_top_package', 'rank_kecamatan',
                ]),
                ['search_kecamatan']
            )));
        }
        $lines = [
            '=== HASIL QUERY DATABASE (realisasi belanja — jawab HANYA dari data ini) ===',
            'Pertanyaan asli: ' . $question,
        ];
        if ($interpretation !== null && !empty($interpretation['ringkasan_maksud'])) {
            $lines[] = 'Makna pertanyaan (terjemahan sistem): ' . $interpretation['ringkasan_maksud'];
        }
        $lines[] = 'Tahun di-query: ' . implode(', ', $years);
        $lines[] = '';

        foreach ($intents as $intent) {
            $block = $this->runIntent($intent, $question, $years);
            if ($block !== '') {
                $lines[] = $block;
                $lines[] = '';
            }
        }

        $text = implode("\n", $lines);
        if (strlen($text) > 12000) {
            $text = substr($text, 0, 12000) . "\n...[hasil query dipotong]";
        }
        return $text;
    }

    /** @return int[] */
    private function resolveYears(string $question): array
    {
        if (!empty($this->interpretation['tahun']) && is_array($this->interpretation['tahun'])) {
            return array_map('intval', $this->interpretation['tahun']);
        }
        return $this->extractYears($question);
    }

    /** @return string[] */
    private function resolveIntents(string $question): array
    {
        if (!empty($this->interpretation['intents']) && is_array($this->interpretation['intents'])) {
            $intents = $this->interpretation['intents'];
            if (!in_array('search_packages', $intents, true)
                && in_array('search_text', $intents, true)) {
                $intents[] = 'search_packages';
            }
        } else {
            $intents = $this->detectIntents($question);
        }
        return $this->postProcessIntents($intents, $question);
    }

    /** @param string[] $intents @return string[] */
    private function postProcessIntents(array $intents, string $question): array
    {
        $q = mb_strtolower($question);
        $keywords = $this->interpretation['keywords'] ?? [];
        $wantsTop = in_array('top_packages', $intents, true)
            || preg_match('/terbesar|tertinggi|paling\s+besar|nilai\s+terbesar/ui', $q);
        $hasTheme = count($keywords) > 0
            || preg_match('/terkait|mengenai|makanan|jamuan|belanja\s+modal|jalan/ui', $q);

        if ($wantsTop && $hasTheme) {
            $intents = array_values(array_diff($intents, ['top_packages']));
            if (!in_array('search_top_package', $intents, true)) {
                $intents[] = 'search_top_package';
            }
        }
        return array_values(array_unique($intents));
    }

    /** @return int[] */
    private function extractYears(string $question): array
    {
        $found = [];
        if (preg_match('/2025/u', $question)) {
            $found[2025] = 2025;
        }
        if (preg_match('/2026/u', $question)) {
            $found[2026] = 2026;
        }
        if (count($found) > 0) {
            return array_values($found);
        }
        return $this->db->focusYears();
    }

    /** @return string[] */
    private function detectIntents(string $question): array
    {
        $q = mb_strtolower($question);
        $intents = [];

        if (preg_match('/dana\s*desa|alokasi\s*desa/ui', $q)) {
            $intents[] = 'dana_desa';
        }
        if (preg_match('/kpm|kemiskinan|bansos|pkh|bpnt|miskin/ui', $q)) {
            $intents[] = 'kemiskinan';
        }

        if (preg_match('/bandingkan|vs\.?|versus|dibanding|dibandingkan|perbandingan/ui', $q)
            || (preg_match('/2025/u', $q) && preg_match('/2026/u', $q))) {
            $intents[] = 'compare_years';
        }

        if (preg_match('/vendor|penyedia|kontraktor|rekanan/ui', $q)) {
            if (preg_match('/banyak|jumlah|terbanyak|paling\s+banyak|sering|proyek\s+terbanyak|paket\s+terbanyak/ui', $q)) {
                $intents[] = 'vendor_by_count';
            } elseif (preg_match('/nilai|besar|tertinggi|pagu|terbesar/ui', $q)) {
                $intents[] = 'vendor_by_value';
            } else {
                $intents[] = 'vendor_by_count';
                $intents[] = 'vendor_by_value';
            }
        }

        if (preg_match('/kecamatan|kec\.|wilayah\s+mana/ui', $q)) {
            $intents[] = 'rank_kecamatan';
        }

        if (preg_match('/satker|dinas|opd/ui', $q)) {
            $intents[] = 'rank_satker';
        }

        if (preg_match('/status|berjalan|selesai|on\s*process|proses/ui', $q)) {
            $intents[] = 'by_status';
        }

        if (preg_match(
            '/adakah|apakah\s+ada|ada\s+tidak|adanya|terkait\s+|mengenai\s+|seputar\s+|'
            . 'soal\s+|berkaitan\s+dengan|cari\s+(paket|proyek)|nama\s+paket/iu',
            $q
        )) {
            $intents[] = 'search_text';
        }

        if (preg_match('/terkait|mengenai|makanan|jamuan/ui', $q)
            && preg_match('/terbesar|tertinggi|paling\s+besar|nilai\s+terbesar|paling\s+tinggi/ui', $q)) {
            $intents[] = 'search_top_package';
        }

        if (preg_match('/paket|proyek|belanja|pekerjaan|kegiatan|makanan|jamuan|jasa/ui', $q)) {
            if (preg_match('/terbesar|tertinggi|paling\s+besar|top\s*\d*|nominal\s+tertinggi/ui', $q)
                && !in_array('search_top_package', $intents, true)) {
                $intents[] = 'top_packages';
            }
            if (preg_match('/cari|tentang|nama\s+paket|paket\s+(bernama|berjudul)|apakah\s+ada|ada\s+tidak/ui', $q)
                || preg_match('/["「].+["」]/u', $question)) {
                $intents[] = 'search_text';
            }
        } elseif (preg_match('/terbesar|tertinggi|paling\s+besar|top\s*\d*/ui', $q)) {
            $intents[] = 'top_packages';
        }

        if (preg_match('/berapa|jumlah|total|banyaknya|ada\s+berapa|jumlah\s+paket/ui', $q)) {
            $intents[] = 'year_summary';
        }

        if ($this->matchKecamatan($question) !== null) {
            if (preg_match('/terkait|mengenai|makanan|rapat|jamuan|cari|ada\s+tidak|adakah|apakah\s+ada/ui', $q)) {
                $intents[] = 'search_kecamatan';
            } else {
                $intents[] = 'kecamatan_detail';
            }
        }

        if ($this->extractVendorNeedle($question) !== null) {
            $intents[] = 'vendor_detail';
        }

        if (count($intents) === 0) {
            $intents[] = 'general_realisasi';
        }

        return array_values(array_unique($intents));
    }

    /** @param int[] $years */
    private function runIntent(string $intent, string $question, array $years): string
    {
        return match ($intent) {
            'compare_years' => $this->blockCompareYears($years),
            'year_summary' => $this->blockYearSummary($years),
            'rank_kecamatan' => $this->blockRankKecamatan($years),
            'vendor_by_count' => $this->blockVendorByCount($years),
            'vendor_by_value' => $this->blockVendorByValue($years),
            'top_packages' => $this->blockTopPackages($years),
            'rank_satker' => $this->blockRankSatker($years),
            'by_status' => $this->blockByStatus($years),
            'search_text', 'search_packages' => $this->blockSearch($question, $years),
            'search_top_package' => $this->blockSearchTopPackage($question, $years),
            'search_kecamatan' => $this->blockSearchKecamatan($question, $years),
            'kecamatan_detail' => $this->blockKecamatanDetail($question, $years),
            'vendor_detail' => $this->blockVendorDetail($question, $years),
            'general_realisasi' => $this->blockGeneral($years),
            'dana_desa' => $this->supportingSection('dana_desa'),
            'kemiskinan' => $this->supportingSection('kemiskinan'),
            default => '',
        };
    }

    private function supportingSection(string $which): string
    {
        $builder = new MatadataContextBuilder($this->pdo);
        $full = $builder->build();
        if ($which === 'dana_desa' && preg_match('/## DANA DESA.*?(?=## |\z)/s', $full, $m)) {
            return trim($m[0]);
        }
        if ($which === 'kemiskinan' && preg_match('/## KEMISKINAN.*?(?=## |\z)/s', $full, $m)) {
            return trim($m[0]);
        }
        return '';
    }

    /** @param int[] $years */
    private function blockCompareYears(array $years): string
    {
        $data = $this->db->compareYears($years);
        $lines = ['[Perbandingan tahun]'];
        foreach ($data as $year => $row) {
            $lines[] = "TA {$year}: " . number_format($row['cnt'], 0, ',', '.') . ' paket, total ' . formatRupiah($row['total']);
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockYearSummary(array $years): string
    {
        $lines = ['[Ringkasan per tahun]'];
        foreach ($years as $year) {
            $row = $this->db->yearSummary($year);
            if ($row) {
                $lines[] = "TA {$year}: " . number_format((int) $row['cnt'], 0, ',', '.')
                    . ' paket, total realisasi ' . formatRupiah($row['total']);
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockRankKecamatan(array $years): string
    {
        $lines = ['[Ranking kecamatan by nilai realisasi]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            $rank = 1;
            foreach ($this->db->rankKecamatan($year, 12) as $row) {
                $lines[] = "{$rank}. {$row['kecamatan']}: " . formatRupiah($row['total'])
                    . ' (' . $row['cnt'] . ' paket)';
                $rank++;
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockVendorByCount(array $years): string
    {
        $lines = ['[Ranking vendor — paling banyak paket/proyek]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            $rank = 1;
            foreach ($this->db->rankVendorByCount($year, 12) as $row) {
                $lines[] = "{$rank}. {$row['vendor']}: {$row['cnt']} paket, total " . formatRupiah($row['total']);
                $rank++;
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockVendorByValue(array $years): string
    {
        $lines = ['[Ranking vendor — nilai realisasi terbesar]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            $rank = 1;
            foreach ($this->db->rankVendorByValue($year, 10) as $row) {
                $lines[] = "{$rank}. {$row['vendor']}: " . formatRupiah($row['total'])
                    . " ({$row['cnt']} paket)";
                $rank++;
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockTopPackages(array $years): string
    {
        $lines = ['[Paket dengan nilai terbesar]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            $rank = 1;
            foreach ($this->db->topPackages($year, 8) as $row) {
                $nama = mb_substr($row['nama_paket'], 0, 100);
                $lines[] = "{$rank}. {$nama}";
                $lines[] = '   ' . formatRupiah($row['total_nilai'])
                    . " | {$row['kecamatan']} | {$row['vendor']} | {$row['status']}";
                $rank++;
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockRankSatker(array $years): string
    {
        $lines = ['[Ranking satker/OPD by nilai]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            $rank = 1;
            foreach ($this->db->rankSatker($year, 8) as $row) {
                $satker = mb_substr($row['satker'], 0, 80);
                $lines[] = "{$rank}. {$satker}: " . formatRupiah($row['total'])
                    . " ({$row['cnt']} paket)";
                $rank++;
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockByStatus(array $years): string
    {
        $lines = ['[Agregat per status paket]'];
        foreach ($years as $year) {
            $lines[] = "--- TA {$year} ---";
            foreach ($this->db->byStatus($year) as $row) {
                $lines[] = "- {$row['status']}: {$row['cnt']} paket, " . formatRupiah($row['total']);
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockSearch(string $question, array $years): string
    {
        return $this->blockSearchWithKeywords($this->resolveSearchKeywords($question), $years);
    }

    /** @param int[] $years */
    private function blockSearchKecamatan(string $question, array $years): string
    {
        $kec = $this->resolveKecamatanName($question);
        $keywords = $this->resolveSearchKeywords($question);
        if ($kec === null || count($keywords) === 0) {
            return '';
        }

        $lines = [
            '[Pencarian tema + kecamatan — jawab ya/tidak HANYA dari jumlah paket di kecamatan ini, bukan total kabupaten]',
            "Kecamatan: {$kec}",
        ];
        $anyHit = false;
        foreach ($years as $year) {
            foreach ($keywords as $kw) {
                $cnt = $this->db->countSearchPackagesInKecamatan($year, $kw, $kec);
                $lines[] = "--- TA {$year}, kecamatan {$kec}, tema \"{$kw}\" — {$cnt} paket cocok ---";
                if ($cnt === 0) {
                    continue;
                }
                $anyHit = true;
                $rows = $this->db->searchPackagesFlexibleInKecamatan($year, $kw, $kec, 6);
                foreach ($rows as $row) {
                    $lines[] = '- ' . mb_substr($row['nama_paket'] ?? '', 0, 90);
                    $lines[] = '  ' . formatRupiah($row['total_nilai'] ?? 0)
                        . ' | ' . ($row['kecamatan'] ?? '') . ' | ' . ($row['vendor'] ?? '')
                        . ' | ' . ($row['status'] ?? '');
                }
            }
        }
        if (!$anyHit) {
            $lines[] = 'Kesimpulan data: tidak ada paket dengan tema tersebut di kecamatan ' . $kec
                . ' untuk tahun ' . implode(', ', $years) . '.';
        } else {
            $lines[] = 'Kesimpulan data: ada paket — gunakan contoh di atas (semua kolom kecamatan = ' . $kec . ').';
        }
        return implode("\n", $lines);
    }

    private function shouldSearchKecamatan(string $question, array $intents): bool
    {
        if ($this->resolveKecamatanName($question) === null) {
            return false;
        }
        if (count($this->resolveSearchKeywords($question)) > 0) {
            return true;
        }
        return in_array('search_packages', $intents, true)
            || in_array('search_text', $intents, true)
            || in_array('search_top_package', $intents, true)
            || in_array('search_kecamatan', $intents, true);
    }

    private function resolveKecamatanName(string $question): ?string
    {
        if (!empty($this->interpretation['kecamatan'])) {
            $k = trim((string) $this->interpretation['kecamatan']);
            if ($k !== '') {
                return $k;
            }
        }
        return $this->matchKecamatan($question);
    }

    /** @return string[] */
    private function resolveSearchKeywords(string $question): array
    {
        if (!empty($this->interpretation['keywords']) && is_array($this->interpretation['keywords'])) {
            $kw = [];
            foreach ($this->interpretation['keywords'] as $k) {
                $k = trim((string) $k);
                if (mb_strlen($k) >= 2) {
                    $kw[] = $k;
                }
            }
            if (count($kw) > 0) {
                return $kw;
            }
        }
        return $this->extractSearchKeywords($question);
    }

    /** @param int[] $years */
    private function blockSearchTopPackage(string $question, array $years): string
    {
        $keywords = $this->resolveSearchKeywords($question);
        if (count($keywords) === 0) {
            return '';
        }

        $lines = ['[Paket nilai TERBESAR dalam tema/kata kunci — bukan top seluruh database]'];
        foreach ($years as $year) {
            foreach ($keywords as $kw) {
                $rows = $this->db->searchPackagesFlexible($year, $kw, 5);
                if (count($rows) === 0) {
                    $lines[] = "TA {$year}, tema \"{$kw}\": tidak ada paket cocok.";
                    continue;
                }
                $cnt = $this->db->countSearchPackages($year, $kw);
                $top = $rows[0];
                $lines[] = "TA {$year}, tema \"{$kw}\" — {$cnt} paket cocok. #1 nilai terbesar:";
                $lines[] = '- ' . ($top['nama_paket'] ?? '');
                $lines[] = '  ' . formatRupiah($top['total_nilai'] ?? 0)
                    . ' | ' . ($top['kecamatan'] ?? '') . ' | ' . ($top['vendor'] ?? '')
                    . ' | ' . ($top['status'] ?? '');
                if (count($rows) > 1) {
                    $lines[] = '  Runner-up:';
                    foreach (array_slice($rows, 1, 3) as $row) {
                        $lines[] = '  - ' . mb_substr($row['nama_paket'] ?? '', 0, 70) . ' — '
                            . formatRupiah($row['total_nilai'] ?? 0);
                    }
                }
            }
        }
        return implode("\n", $lines);
    }

    /** @param string[] $keywords */
    private function blockSearchWithKeywords(array $keywords, array $years): string
    {
        if (count($keywords) === 0) {
            return "[Pencarian]\nTidak dapat mengekstrak kata kunci dari pertanyaan. Coba: \"cari paket makanan tamu 2026\".";
        }
        $lines = ['[Pencarian teks di nama paket/vendor/satker]'];
        $anyHit = false;
        foreach ($years as $year) {
            foreach ($keywords as $kw) {
                $cnt = $this->db->countSearchPackages($year, $kw);
                $lines[] = "--- TA {$year}, kata kunci \"{$kw}\" — {$cnt} paket cocok ---";
                if ($cnt === 0) {
                    continue;
                }
                $anyHit = true;
                $rows = $this->db->searchPackagesFlexible($year, $kw, 8);
                foreach ($rows as $row) {
                    $lines[] = '- ' . mb_substr($row['nama_paket'], 0, 90);
                    $lines[] = '  ' . formatRupiah($row['total_nilai'])
                        . " | {$row['kecamatan']} | {$row['vendor']} | {$row['status']}";
                }
            }
        }
        if (!$anyHit) {
            $lines[] = 'Tidak ada paket yang cocok di database untuk kata kunci di atas (tahun ' . implode(', ', $years) . ').';
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockKecamatanDetail(string $question, array $years): string
    {
        $kec = $this->resolveKecamatanName($question);
        if ($kec === null) {
            return '';
        }
        $lines = ["[Detail kecamatan: {$kec}]"];
        foreach ($years as $year) {
            $rows = $this->db->filterKecamatan($year, $kec, 8);
            if (count($rows) === 0) {
                $lines[] = "TA {$year}: tidak ada paket untuk kecamatan ini.";
                continue;
            }
            $lines[] = "--- TA {$year}, top paket ---";
            foreach ($rows as $row) {
                $lines[] = '- ' . mb_substr($row['nama_paket'], 0, 85) . ' | '
                    . formatRupiah($row['total_nilai']) . " | {$row['vendor']}";
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockVendorDetail(string $question, array $years): string
    {
        $needle = $this->interpretation['vendor'] ?? null;
        if ($needle === null || $needle === '') {
            $needle = $this->extractVendorNeedle($question);
        }
        if ($needle === null) {
            return '';
        }
        $lines = ["[Detail vendor (cocok \"{$needle}\")]"];
        foreach ($years as $year) {
            $stat = $this->db->vendorStats($year, $needle);
            if ($stat) {
                $lines[] = "TA {$year} — {$stat['vendor']}: {$stat['cnt']} paket, total "
                    . formatRupiah($stat['total']);
            }
            foreach ($this->db->filterVendor($year, $needle, 6) as $row) {
                $lines[] = '- ' . mb_substr($row['nama_paket'], 0, 80) . ' | '
                    . formatRupiah($row['total_nilai']) . " | {$row['kecamatan']}";
            }
        }
        return implode("\n", $lines);
    }

    /** @param int[] $years */
    private function blockGeneral(array $years): string
    {
        $lines = ['[Ringkasan umum realisasi — otomatis untuk pertanyaan umum]'];
        foreach ($years as $year) {
            $row = $this->db->yearSummary($year);
            if ($row) {
                $lines[] = "TA {$year}: " . number_format((int) $row['cnt'], 0, ',', '.')
                    . ' paket, ' . formatRupiah($row['total']);
            }
            $top = $this->db->topPackages($year, 3);
            if (!empty($top)) {
                $lines[] = "Top paket {$year}: " . mb_substr($top[0]['nama_paket'], 0, 70)
                    . ' (' . formatRupiah($top[0]['total_nilai']) . ')';
            }
            $v = $this->db->rankVendorByCount($year, 3);
            if (!empty($v)) {
                $lines[] = "Top vendor (jumlah paket) {$year}: {$v[0]['vendor']} ({$v[0]['cnt']} paket)";
            }
            $k = $this->db->rankKecamatan($year, 3);
            if (!empty($k)) {
                $lines[] = "Top kecamatan {$year}: {$k[0]['kecamatan']} (" . formatRupiah($k[0]['total']) . ')';
            }
        }
        return implode("\n", $lines);
    }

    private function matchKecamatan(string $question): ?string
    {
        $q = mb_strtolower($question);
        $best = null;
        $bestLen = 0;
        foreach ($this->db->listKecamatanNames() as $name) {
            $n = mb_strtolower(trim($name));
            if ($n === '' || mb_strlen($n) < 4) {
                continue;
            }
            if (mb_strpos($q, $n) !== false && mb_strlen($n) > $bestLen) {
                $best = $name;
                $bestLen = mb_strlen($n);
            }
        }
        return $best;
    }

    private function extractVendorNeedle(string $question): ?string
    {
        if (preg_match('/vendor\s+["\']?([^"\'?]+)["\']?/iu', $question, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/penyedia\s+["\']?([^"\'?]+)["\']?/iu', $question, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/["「](.+?)["」]/u', $question, $m) && mb_strlen($m[1]) >= 4) {
            return trim($m[1]);
        }
        return null;
    }

    /** @return string[] */
    private function extractSearchKeywords(string $question): array
    {
        if (preg_match('/["「](.+?)["」]/u', $question, $m)) {
            return [trim($m[1])];
        }

        if (preg_match(
            '/(?:terkait|mengenai|seputar|soal|tentang|berkaitan dengan)\s+(.+?)(?:,|\s+yang|\s+di\s+20|\?|$)/iu',
            $question,
            $m
        )) {
            $phrase = trim($m[1]);
            $phrase = preg_replace('/\s+yang\s+.+$/iu', '', $phrase);
            $phrase = preg_replace('/\s+di\s+(?:kecamatan|kec\.?)\s+.+$/iu', '', $phrase);
            $phrase = preg_replace('/\s+di\s+20\d{2}$/iu', '', $phrase);
            $phrase = preg_replace('/\s+tahun\s+20\d{2}.*$/iu', '', $phrase);
            if (mb_strlen($phrase) >= 3) {
                return [$phrase];
            }
        }

        $q = mb_strtolower($question);
        $q = preg_replace('/^(adakah|apakah|ada\s+tidak|adanya|tolong|bisa)\s+/u', '', $q);
        $q = preg_replace('/\b(proyek|paket|pekerjaan|kegiatan|realisasi)\b\s*(yang\s+)?(terkait|mengenai)?\s*/u', '', $q);
        $q = trim($q, " \t\n\r\0\x0B?.,!");
        if (mb_strlen($q) >= 4) {
            return [$q];
        }

        $stop = [
            'yang', 'mana', 'berapa', 'siapa', 'apa', 'bagaimana', 'di', 'pada', 'tahun',
            'data', 'sistem', 'matadata', 'majalengka', 'kabupaten', 'tolong', 'cari',
            'dengan', 'dari', 'adalah', 'ada', 'tidak', 'vendor', 'penyedia', 'kecamatan',
            '2025', '2026', 'adakah', 'apakah', 'proyek', 'paket', 'dan', 'atau', 'yg',
        ];
        $words = preg_split('/\s+/u', mb_strtolower($question)) ?: [];
        $out = [];
        foreach ($words as $w) {
            $w = trim($w, '.,?!;:');
            if (mb_strlen($w) >= 4 && !in_array($w, $stop, true)) {
                $out[] = $w;
            }
        }
        if (count($out) >= 2) {
            return [implode(' ', array_slice($out, 0, 4))];
        }
        return array_slice(array_unique($out), 0, 3);
    }
}
