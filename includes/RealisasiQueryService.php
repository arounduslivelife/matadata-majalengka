<?php

require_once __DIR__ . '/format_helpers.php';
require_once __DIR__ . '/MatadataContextBuilder.php';

/**
 * Query aman (parameterized) ke tabel realizations — tahun fokus 2025/2026.
 */
class RealisasiQueryService
{
    private PDO $pdo;
    private string $nilai;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->nilai = sqlRealisasiNilai('total_nilai');
    }

    public function focusYears(): array
    {
        return MatadataContextBuilder::defaultFocusYears();
    }

    public function yearSummary(int $year): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS cnt, COALESCE(SUM({$this->nilai}), 0) AS total
            FROM realizations WHERE tahun = ?
        ");
        $stmt->execute([$year]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<int, array{cnt:int,total:float}> */
    public function compareYears(array $years): array
    {
        $out = [];
        foreach ($years as $year) {
            $row = $this->yearSummary((int) $year);
            if ($row) {
                $out[(int) $year] = [
                    'cnt' => (int) $row['cnt'],
                    'total' => (float) $row['total'],
                ];
            }
        }
        return $out;
    }

    /** @return list<array{kecamatan:string,cnt:int,total:float}> */
    public function rankKecamatan(int $year, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT kecamatan, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations WHERE tahun = ? AND kecamatan IS NOT NULL AND TRIM(kecamatan) != ''
            GROUP BY kecamatan ORDER BY total DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{vendor:string,cnt:int,total:float}> */
    public function rankVendorByCount(int $year, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare("
            SELECT vendor, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations
            WHERE tahun = ? AND vendor IS NOT NULL AND TRIM(vendor) != ''
            GROUP BY vendor ORDER BY cnt DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{vendor:string,cnt:int,total:float}> */
    public function rankVendorByValue(int $year, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT vendor, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations
            WHERE tahun = ? AND vendor IS NOT NULL AND TRIM(vendor) != ''
            GROUP BY vendor ORDER BY total DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array> */
    public function topPackages(int $year, int $limit = 8): array
    {
        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, vendor, total_nilai, status, satker
            FROM realizations WHERE tahun = ?
            ORDER BY {$this->nilai} DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array> */
    public function rankSatker(int $year, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT satker, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations WHERE tahun = ? AND satker IS NOT NULL AND TRIM(satker) != ''
            GROUP BY satker ORDER BY total DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{status:string,cnt:int,total:float}> */
    public function byStatus(int $year, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations WHERE tahun = ? AND status IS NOT NULL AND TRIM(status) != ''
            GROUP BY status ORDER BY cnt DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array> */
    public function filterKecamatan(int $year, string $kecamatan, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT nama_paket, vendor, total_nilai, status
            FROM realizations
            WHERE tahun = ? AND kecamatan LIKE ?
            ORDER BY {$this->nilai} DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year, '%' . $kecamatan . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array> */
    public function filterVendor(int $year, string $vendor, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, total_nilai, status, COUNT(*) OVER () AS total_rows
            FROM realizations
            WHERE tahun = ? AND vendor LIKE ?
            ORDER BY {$this->nilai} DESC LIMIT " . (int) $limit
        );
        // MySQL older may not have window functions — use two queries
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM realizations WHERE tahun = ? AND vendor LIKE ?
        ");
        $stmt->execute([$year, '%' . $vendor . '%']);
        $totalRows = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, total_nilai, status
            FROM realizations
            WHERE tahun = ? AND vendor LIKE ?
            ORDER BY {$this->nilai} DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year, '%' . $vendor . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['_vendor_total_paket'] = $totalRows;
        }
        return $rows;
    }

    /** @return list<array> */
    public function searchPackages(int $year, string $keyword, int $limit = 8): array
    {
        $like = '%' . $keyword . '%';
        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, vendor, total_nilai, status, tahun
            FROM realizations
            WHERE tahun = ? AND (nama_paket LIKE ? OR vendor LIKE ? OR satker LIKE ?)
            ORDER BY {$this->nilai} DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$year, $like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countSearchPackages(int $year, string $keyword): int
    {
        $phrase = trim($keyword);
        $like = '%' . $phrase . '%';
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM realizations
            WHERE tahun = ? AND (nama_paket LIKE ? OR vendor LIKE ? OR satker LIKE ?)
        ");
        $stmt->execute([$year, $like, $like, $like]);
        $cnt = (int) $stmt->fetchColumn();

        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($phrase)) ?: [],
            fn ($w) => mb_strlen(trim($w)) >= 3
        ));
        if (count($words) >= 2) {
            $conds = ['tahun = ?'];
            $params = [$year];
            foreach ($words as $w) {
                $conds[] = 'LOWER(nama_paket) LIKE ?';
                $params[] = '%' . $w . '%';
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM realizations WHERE ' . implode(' AND ', $conds));
            $stmt->execute($params);
            $cnt = max($cnt, (int) $stmt->fetchColumn());
        }

        return $cnt;
    }

    /**
     * Frasa lengkap dulu, lalu kata-kata (AND di nama_paket) agar "makanan tamu" cocok ke "Jamuan tamu".
     *
     * @return list<array>
     */
    public function searchPackagesFlexible(int $year, string $phrase, int $limit = 10): array
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return [];
        }

        $seen = [];
        $out = [];

        $merge = function (array $rows) use (&$seen, &$out, $limit) {
            foreach ($rows as $row) {
                $key = ($row['nama_paket'] ?? '') . '|' . ($row['total_nilai'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $row;
                if (count($out) >= $limit) {
                    return;
                }
            }
        };

        $merge($this->searchPackages($year, $phrase, $limit));

        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($phrase)) ?: [],
            fn ($w) => mb_strlen(trim($w)) >= 3
        ));

        if (count($words) >= 2) {
            $conds = ['tahun = ?'];
            $params = [$year];
            foreach ($words as $w) {
                $conds[] = 'LOWER(nama_paket) LIKE ?';
                $params[] = '%' . $w . '%';
            }
            $sql = "
                SELECT nama_paket, kecamatan, vendor, total_nilai, status, tahun
                FROM realizations
                WHERE " . implode(' AND ', $conds) . "
                ORDER BY {$this->nilai} DESC
                LIMIT " . (int) $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $merge($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        foreach ($words as $w) {
            if (count($out) >= $limit) {
                break;
            }
            $merge($this->searchPackages($year, $w, $limit));
        }

        return $out;
    }

    public function countSearchPackagesInKecamatan(int $year, string $keyword, string $kecamatan): int
    {
        $phrase = trim($keyword);
        $likeKec = '%' . trim($kecamatan) . '%';
        $like = '%' . $phrase . '%';
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM realizations
            WHERE tahun = ? AND kecamatan LIKE ?
            AND (nama_paket LIKE ? OR vendor LIKE ? OR satker LIKE ?)
        ");
        $stmt->execute([$year, $likeKec, $like, $like, $like]);
        $cnt = (int) $stmt->fetchColumn();

        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($phrase)) ?: [],
            fn ($w) => mb_strlen(trim($w)) >= 3
        ));
        if (count($words) >= 2) {
            $conds = ['tahun = ?', 'kecamatan LIKE ?'];
            $params = [$year, $likeKec];
            foreach ($words as $w) {
                $conds[] = 'LOWER(nama_paket) LIKE ?';
                $params[] = '%' . $w . '%';
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM realizations WHERE ' . implode(' AND ', $conds));
            $stmt->execute($params);
            $cnt = max($cnt, (int) $stmt->fetchColumn());
        }

        return $cnt;
    }

    /**
     * @return list<array>
     */
    public function searchPackagesFlexibleInKecamatan(int $year, string $phrase, string $kecamatan, int $limit = 10): array
    {
        $phrase = trim($phrase);
        $kecamatan = trim($kecamatan);
        if ($phrase === '' || $kecamatan === '') {
            return [];
        }

        $likeKec = '%' . $kecamatan . '%';
        $seen = [];
        $out = [];

        $merge = function (array $rows) use (&$seen, &$out, $limit) {
            foreach ($rows as $row) {
                $key = ($row['nama_paket'] ?? '') . '|' . ($row['total_nilai'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $row;
                if (count($out) >= $limit) {
                    return;
                }
            }
        };

        $like = '%' . $phrase . '%';
        $stmt = $this->pdo->prepare("
            SELECT nama_paket, kecamatan, vendor, total_nilai, status, tahun
            FROM realizations
            WHERE tahun = ? AND kecamatan LIKE ?
            AND (nama_paket LIKE ? OR vendor LIKE ? OR satker LIKE ?)
            ORDER BY {$this->nilai} DESC
            LIMIT " . (int) $limit
        );
        $stmt->execute([$year, $likeKec, $like, $like, $like]);
        $merge($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $words = array_values(array_filter(
            preg_split('/\s+/u', mb_strtolower($phrase)) ?: [],
            fn ($w) => mb_strlen(trim($w)) >= 3
        ));

        if (count($words) >= 2) {
            $conds = ['tahun = ?', 'kecamatan LIKE ?'];
            $params = [$year, $likeKec];
            foreach ($words as $w) {
                $conds[] = 'LOWER(nama_paket) LIKE ?';
                $params[] = '%' . $w . '%';
            }
            $sql = "
                SELECT nama_paket, kecamatan, vendor, total_nilai, status, tahun
                FROM realizations
                WHERE " . implode(' AND ', $conds) . "
                ORDER BY {$this->nilai} DESC
                LIMIT " . (int) $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $merge($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        foreach ($words as $w) {
            if (count($out) >= $limit) {
                break;
            }
            $likeW = '%' . $w . '%';
            $stmt = $this->pdo->prepare("
                SELECT nama_paket, kecamatan, vendor, total_nilai, status, tahun
                FROM realizations
                WHERE tahun = ? AND kecamatan LIKE ?
                AND (nama_paket LIKE ? OR vendor LIKE ? OR satker LIKE ?)
                ORDER BY {$this->nilai} DESC
                LIMIT " . (int) $limit
            );
            $stmt->execute([$year, $likeKec, $likeW, $likeW, $likeW]);
            $merge($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return $out;
    }

    /** @return list<string> */
    public function listKecamatanNames(): array
    {
        $years = implode(',', array_map('intval', $this->focusYears()));
        $stmt = $this->pdo->query("
            SELECT DISTINCT kecamatan FROM realizations
            WHERE tahun IN ({$years}) AND kecamatan IS NOT NULL AND TRIM(kecamatan) != ''
            ORDER BY kecamatan
        ");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'kecamatan');
    }

    public function vendorStats(int $year, string $vendorLike): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT vendor, COUNT(*) AS cnt, SUM({$this->nilai}) AS total
            FROM realizations
            WHERE tahun = ? AND vendor LIKE ?
            GROUP BY vendor ORDER BY cnt DESC LIMIT 1
        ");
        $stmt->execute([$year, '%' . $vendorLike . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
