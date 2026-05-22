<?php

require_once __DIR__ . '/GroqClient.php';
require_once __DIR__ . '/AppConfig.php';
require_once __DIR__ . '/MatadataContextBuilder.php';
require_once __DIR__ . '/format_helpers.php';

/**
 * Langkah 1: AI menerjemahkan maksud pertanyaan → struktur untuk query database.
 */
class QuestionInterpreter
{
    private const ALLOWED_INTENTS = [
        'search_packages',
        'search_kecamatan',
        'search_top_package',
        'vendor_by_count',
        'vendor_by_value',
        'top_packages',
        'rank_kecamatan',
        'year_summary',
        'compare_years',
        'rank_satker',
        'by_status',
        'kecamatan_detail',
        'vendor_detail',
        'general_realisasi',
        'dana_desa',
        'kemiskinan',
        'meta_project',
    ];

    public function interpret(string $question): array
    {
        $groq = new GroqClient();
        $years = implode(', ', MatadataContextBuilder::defaultFocusYears());

        $system = <<<PROMPT
Anda menerjemahkan pertanyaan pengguna tentang data anggaran Matadata Kabupaten Majalengka menjadi struktur untuk query database MySQL (tabel realizations, tahun fokus: {$years}).

Output HANYA satu objek JSON valid (tanpa markdown, tanpa teks lain):
{
  "ringkasan_maksud": "satu kalimat bahasa Indonesia, jelas",
  "tahun": [2026],
  "intents": ["search_packages"],
  "keywords": ["makanan tamu", "jamuan tamu"],
  "kecamatan": "Bantarujeg atau null (string, bukan array)",
  "vendor": null,
  "jawaban_yang_diharapkan": "ya_tidak_dengan_contoh | ranking | angka_total | penjelasan_umum"
}

intents (pilih yang relevan, bisa lebih dari satu):
- search_packages: cek ada/tidak ada paket, cari tema (makanan tamu, jalan, dll) tanpa filter kecamatan
- search_kecamatan: tema + satu kecamatan (isi kecamatan + keywords). Contoh: "makanan rapat di Bantarujeg 2026" → search_kecamatan, JANGAN search_packages global
- search_top_package: paket NILAI TERBESAR di dalam tema/kata kunci (bukan top seluruh database). Contoh: "makanan tamu paling besar 2026" → search_top_package + keywords, JANGAN top_packages
- vendor_by_count: vendor paling banyak paket/proyek
- vendor_by_value: vendor nilai realisasi terbesar
- top_packages: paket nilai terbesar SELURUH tahun (tanpa filter tema)
- rank_kecamatan: peringkat kecamatan
- year_summary: total paket/nilai satu tahun
- compare_years: bandingkan 2025 vs 2026
- rank_satker: peringkat dinas/satker
- by_status: status paket (selesai, on process)
- kecamatan_detail: detail satu kecamatan (isi nama di kecamatan)
- vendor_detail: detail satu vendor (isi nama di vendor)
- general_realisasi: pertanyaan umum
- dana_desa / kemiskinan: topik di luar realizations
- meta_project: tentang bot/pembuat/tujuan website

keywords: sinonim Indonesia + istilah di data (mis. "makanan tamu" → juga "jamuan tamu", "makanan dan minuman jamuan")
tahun: kosongkan [] jika tidak disebut → artinya [2025, 2026]
PROMPT;

        $raw = $groq->chat($system, 'Pertanyaan pengguna: ' . $question, [
            'max_tokens' => (int) (AppConfig::telegram()['interpreter_max_tokens'] ?? 280),
            'temperature' => 0.1,
        ]);

        return $this->normalize($this->parseJson($raw), $question);
    }

    public function formatForPrompt(array $interp): string
    {
        $lines = [
            $interp['ringkasan_maksud'] ?? '',
            'Tahun: ' . implode(', ', $interp['tahun'] ?? []),
            'Jenis data yang dibutuhkan: ' . implode(', ', $interp['intents'] ?? []),
        ];
        $kw = $interp['keywords'] ?? [];
        if (count($kw) > 0) {
            $lines[] = 'Kata kunci untuk database: ' . implode(', ', $kw);
        }
        if (!empty($interp['kecamatan'])) {
            $lines[] = 'Kecamatan: ' . $interp['kecamatan'];
        }
        if (!empty($interp['vendor'])) {
            $lines[] = 'Vendor: ' . $interp['vendor'];
        }
        return implode("\n", array_filter($lines));
    }

    private function parseJson(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function normalize(array $data, string $question): array
    {
        $focus = MatadataContextBuilder::defaultFocusYears();
        $years = [];
        if (!empty($data['tahun']) && is_array($data['tahun'])) {
            foreach ($data['tahun'] as $y) {
                $y = (int) $y;
                if (in_array($y, $focus, true)) {
                    $years[] = $y;
                }
            }
        }
        if (count($years) === 0) {
            if (preg_match('/2026/u', $question)) {
                $years[] = 2026;
            }
            if (preg_match('/2025/u', $question)) {
                $years[] = 2025;
            }
        }
        if (count($years) === 0) {
            $years = $focus;
        }

        $intents = [];
        if (!empty($data['intents']) && is_array($data['intents'])) {
            foreach ($data['intents'] as $i) {
                $i = strtolower(trim((string) $i));
                if ($i === 'search_text') {
                    $i = 'search_packages';
                }
                if (in_array($i, self::ALLOWED_INTENTS, true)) {
                    $intents[] = $i;
                }
            }
        }
        if (count($intents) === 0) {
            $intents = ['general_realisasi'];
        }

        $keywords = [];
        if (!empty($data['keywords']) && is_array($data['keywords'])) {
            foreach ($data['keywords'] as $k) {
                $k = trim((string) $k);
                if (mb_strlen($k) >= 2) {
                    $keywords[] = $k;
                }
            }
        }
        if (count($keywords) === 0) {
            $keywords = $this->guessKeywordsFromQuestion($question);
        }

        $intents = $this->refineIntents($intents, $keywords, $question);
        $kecamatan = normalizeScalarString($data['kecamatan'] ?? null);
        if ($kecamatan === null) {
            $kecamatan = $this->guessKecamatanFromQuestion($question);
        }
        if ($kecamatan !== null && $kecamatan !== '' && count($keywords) > 0) {
            $intents = array_values(array_unique(array_merge(
                array_diff($intents, ['search_packages', 'search_text', 'kecamatan_detail', 'search_top_package']),
                ['search_kecamatan']
            )));
        }

        return [
            'ringkasan_maksud' => trim((string) ($data['ringkasan_maksud'] ?? 'Pertanyaan tentang data realisasi anggaran.')),
            'tahun' => array_values(array_unique($years)),
            'intents' => array_values(array_unique($intents)),
            'keywords' => array_values(array_unique($keywords)),
            'kecamatan' => ($kecamatan !== null && $kecamatan !== '') ? $kecamatan : null,
            'vendor' => normalizeScalarString($data['vendor'] ?? null),
            'jawaban_yang_diharapkan' => trim((string) ($data['jawaban_yang_diharapkan'] ?? '')),
        ];
    }

    /** @param string[] $intents @param string[] $keywords @return string[] */
    private function refineIntents(array $intents, array $keywords, string $question): array
    {
        $q = mb_strtolower($question);
        $wantsTop = in_array('top_packages', $intents, true)
            || preg_match('/terbesar|tertinggi|paling\s+besar|nilai\s+terbesar|paling\s+tinggi/ui', $q);
        $hasTheme = count($keywords) > 0
            || preg_match('/terkait|mengenai|seputar|tentang|makanan|jamuan|jalan|belanja/ui', $q);

        if ($wantsTop && $hasTheme) {
            $intents = array_values(array_diff($intents, ['top_packages', 'general_realisasi']));
            $intents[] = 'search_top_package';
            if (!in_array('search_packages', $intents, true)) {
                $intents[] = 'search_packages';
            }
        }

        return array_values(array_unique($intents));
    }

    /** @return string[] */
    private function guessKeywordsFromQuestion(string $question): array
    {
        if (preg_match('/(?:terkait|mengenai|seputar|tentang)\s+(.+?)(?:,|\s+yang|\s+di\s+|\?|$)/iu', $question, $m)) {
            $phrase = trim($m[1]);
            $phrase = sanitizeRealisasiThemePhrase($phrase);
            if (mb_strlen($phrase) >= 4) {
                return [$phrase];
            }
        }

        if (preg_match('/terkait\s+(.+?)\s+di\s+tahun\s+20\d{2}/iu', $question, $m)) {
            $phrase = sanitizeRealisasiThemePhrase(trim($m[1]));
            if (mb_strlen($phrase) >= 4) {
                return [$phrase];
            }
        }
        return [];
    }

    private function guessKecamatanFromQuestion(string $question): ?string
    {
        $q = mb_strtolower($question);
        $names = [
            'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
            'Cigasong', 'Cikijing', 'Cingambul', 'Dawuan', 'Jambu', 'Kadipaten', 'Kasokandel', 'Kertajati',
            'Lemahsugih', 'Leuwimunding', 'Ligung', 'Panyingkiran', 'Rajagaluh', 'Sindang', 'Sindangwangi',
            'Sukahaji', 'Sumberjaya', 'Tomo', 'Cikalong',
        ];
        usort($names, fn ($a, $b) => mb_strlen($b) - mb_strlen($a));
        foreach ($names as $name) {
            if (mb_strpos($q, mb_strtolower($name)) !== false) {
                return $name;
            }
        }
        if (preg_match('/kecamatan\s+([A-Za-z]+)/iu', $question, $m)) {
            return ucfirst(strtolower(trim($m[1])));
        }
        return null;
    }
}
