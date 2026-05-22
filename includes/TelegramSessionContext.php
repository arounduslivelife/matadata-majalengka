<?php

require_once __DIR__ . '/format_helpers.php';
require_once __DIR__ . '/MatadataContextBuilder.php';

/**
 * Konteks sesi dari percakapan sebelumnya (pertanyaan lanjutan tanpa ulang tema/lokasi/tahun).
 */
class TelegramSessionContext
{
    private const KECAMATAN_NAMES = [
        'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
        'Cigasong', 'Cikijing', 'Cingambul', 'Dawuan', 'Jambu', 'Kadipaten', 'Kasokandel', 'Kertajati',
        'Lemahsugih', 'Leuwimunding', 'Ligung', 'Panyingkiran', 'Rajagaluh', 'Sindang', 'Sindangwangi',
        'Sukahaji', 'Sumberjaya', 'Tomo', 'Cikalong', 'Jatitujuh',
    ];

    public static function isFollowUp(string $question): bool
    {
        $q = mb_strtolower(trim($question));
        if (mb_strlen($q) > 140) {
            return false;
        }
        if (preg_match('/terkait|kecamatan|tahun\s+20\d{2}|vendor\s+\w{4,}/iu', $q)) {
            return false;
        }
        return (bool) preg_match(
            '/^(ada\s+)?berapa|total\s+nilai|nilainya|jumlahnya|jumlah\s+nilai|berapa\s+total|'
            . 'berapa\s+jumlah|vendornya|siapa\s+vendornya|yang\s+tadi|tersebut|itunya|'
            . 'kalau\s+|lanjut|dari\s+(itu|tadi|atas)|maksudnya|yang\s+barusan/iu',
            $q
        );
    }

    /**
     * @param list<array{role:string,content:string}> $history
     * @return array<string, mixed>|null
     */
    public static function deriveFromHistory(array $history): ?array
    {
        $lastUser = null;
        $lastAssistant = null;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($lastUser === null && ($history[$i]['role'] ?? '') === 'user') {
                $lastUser = (string) ($history[$i]['content'] ?? '');
            }
            if ($lastAssistant === null && ($history[$i]['role'] ?? '') === 'assistant') {
                $lastAssistant = (string) ($history[$i]['content'] ?? '');
            }
            if ($lastUser !== null && $lastAssistant !== null) {
                break;
            }
        }
        if ($lastUser === null || trim($lastUser) === '') {
            return null;
        }

        $kec = self::guessKecamatan($lastUser);
        if ($kec === null && $lastAssistant !== null) {
            if (preg_match('/Kecamatan\s+([A-Za-z]+)/u', $lastAssistant, $m)) {
                $kec = trim($m[1]);
            }
        }

        $keywords = self::guessKeywords($lastUser);
        if (count($keywords) === 0 && $lastAssistant !== null) {
            if (preg_match('/terkait\s+"([^"]+)"/u', $lastAssistant, $m)) {
                $keywords[] = $m[1];
            }
        }
        $keywords = expandRealisasiThemeKeywords($keywords);

        $years = [];
        if (preg_match('/2026/u', $lastUser)) {
            $years[] = 2026;
        }
        if (preg_match('/2025/u', $lastUser)) {
            $years[] = 2025;
        }
        if (count($years) === 0 && $lastAssistant !== null && preg_match('/\btahun\s+(2025|2026)\b/u', $lastAssistant, $ym)) {
            $years[] = (int) $ym[1];
        }
        if (count($years) === 0) {
            $years = MatadataContextBuilder::defaultFocusYears();
        }

        if ($kec === null && count($keywords) === 0) {
            return null;
        }

        return [
            'source_question' => $lastUser,
            'keywords' => $keywords,
            'kecamatan' => $kec,
            'tahun' => array_values(array_unique($years)),
        ];
    }

    /**
     * @param array<string, mixed>|null $interpretation
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public static function mergeIntoInterpretation(?array $interpretation, array $session, string $followUpQuestion): array
    {
        $interp = is_array($interpretation) ? $interpretation : [];
        $q = mb_strtolower($followUpQuestion);

        if (empty($interp['keywords']) && !empty($session['keywords'])) {
            $interp['keywords'] = $session['keywords'];
        }
        if (empty($interp['kecamatan']) && !empty($session['kecamatan'])) {
            $interp['kecamatan'] = $session['kecamatan'];
        }
        if (empty($interp['tahun']) && !empty($session['tahun'])) {
            $interp['tahun'] = $session['tahun'];
        }

        $wantsTotal = (bool) preg_match(
            '/total\s+nilai|nilainya|jumlah\s+nilai|berapa\s+total|berapa\s+jumlah|berapa\s+nilai/iu',
            $q
        );
        $wantsTop = (bool) preg_match('/terbesar|tertinggi|paling\s+besar/iu', $q);
        $wantsVendor = (bool) preg_match('/vendor|penyedia/iu', $q);

        $intents = is_array($interp['intents'] ?? null) ? $interp['intents'] : [];
        if ($wantsTotal && !empty($session['kecamatan']) && count($session['keywords'] ?? []) > 0) {
            $intents = ['theme_total_kecamatan'];
        } elseif ($wantsTop && count($session['keywords'] ?? []) > 0) {
            $intents = ['search_top_package'];
        } elseif ($wantsVendor) {
            $intents = array_merge($intents, ['search_kecamatan']);
        } elseif (!empty($session['kecamatan']) && count($session['keywords'] ?? []) > 0) {
            $intents = ['search_kecamatan'];
        }

        $interp['intents'] = array_values(array_unique($intents));
        $interp['ringkasan_maksud'] = trim(
            ($interp['ringkasan_maksud'] ?? 'Lanjutan percakapan.')
            . ' Merujuk: ' . mb_substr((string) ($session['source_question'] ?? ''), 0, 120)
        );
        $interp['_session'] = true;

        return $interp;
    }

    public static function enrichQuestionForInterpreter(string $followUp, array $session): string
    {
        return "Konteks percakapan sebelumnya: \"{$session['source_question']}\"\n"
            . "Pertanyaan lanjutan (jawab dalam konteks di atas): {$followUp}";
    }

    /** @return string[] */
    private static function guessKeywords(string $question): array
    {
        if (preg_match('/(?:terkait|mengenai|seputar|tentang)\s+(.+?)(?:,|\s+yang|\s+pada\s+tahun|\s+di\s+|\?|$)/iu', $question, $m)) {
            $phrase = sanitizeRealisasiThemePhrase(trim($m[1]));
            if (mb_strlen($phrase) >= 3) {
                return [$phrase];
            }
        }
        if (preg_match('/terkait\s+(.+?)\s+di\s+tahun\s+20\d{2}/iu', $question, $m)) {
            $phrase = sanitizeRealisasiThemePhrase(trim($m[1]));
            if (mb_strlen($phrase) >= 3) {
                return [$phrase];
            }
        }
        return [];
    }

    private static function guessKecamatan(string $question): ?string
    {
        $q = mb_strtolower($question);
        $names = self::KECAMATAN_NAMES;
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
