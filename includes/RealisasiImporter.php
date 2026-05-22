<?php

class RealisasiImporter
{
    private PDO $pdo;
    private array $centers;
    private array $kecamatans;
    private array $defaultCenter = [-6.837, 108.227];

    private const EXPECTED_HEADERS = [
        'nama instansi',
        'nama satuan kerja',
        'kode paket',
        'kode rup',
        'tahun anggaran',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $centersPath = dirname(__DIR__) . '/scratch/kecamatan_centers.json';
        $this->centers = file_exists($centersPath)
            ? json_decode(file_get_contents($centersPath), true)
            : [];

        $this->kecamatans = [
            'Bantarujeg', 'Banjaran', 'Talaga', 'Palasah', 'Malausma', 'Argapura', 'Maja', 'Jatiwangi',
            'Sumberjaya', 'Ligung', 'Majalengka', 'Dawuan', 'Kertajati', 'Leuwimunding', 'Sukahaji',
            'Sindangwangi', 'Kadipaten', 'Rajagaluh', 'Jatitujuh', 'Panyingkiran', 'Sindang', 'Kasokandel',
            'Cigasong', 'Lemahsugih', 'Cikijing', 'Cingambul',
        ];
        usort($this->kecamatans, fn($a, $b) => strlen($b) - strlen($a));
    }

    public static function parseCsvLine(string $line): array
    {
        $result = [];
        $current = '';
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($ch === '"') {
                $inQuotes = !$inQuotes;
            } elseif ($ch === ',' && !$inQuotes) {
                $result[] = trim($current);
                $current = '';
            } else {
                $current .= $ch;
            }
        }
        $result[] = trim($current);

        return array_map(fn($v) => trim($v, '"'), $result);
    }

    public function validateHeaders(array $headers): ?string
    {
        if (count($headers) < 13) {
            return 'CSV harus memiliki minimal 13 kolom (format Inaproc realisasi).';
        }

        $normalized = array_map(fn($h) => strtolower(trim($h)), $headers);
        foreach (self::EXPECTED_HEADERS as $expected) {
            $found = false;
            foreach ($normalized as $h) {
                if (str_contains($h, $expected)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return "Kolom wajib tidak ditemukan: \"$expected\". Pastikan file export dari Inaproc.";
            }
        }

        return null;
    }

    /**
     * @return array{rows: array<int, array>, years: int[], errors: string[]}
     */
    public function parseFile(string $filepath): array
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return ['rows' => [], 'years' => [], 'errors' => ['Tidak bisa membaca file CSV.']];
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            return ['rows' => [], 'years' => [], 'errors' => ['File CSV kosong.']];
        }

        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $headers = self::parseCsvLine(rtrim($headerLine, "\r\n"));
        $headerError = $this->validateHeaders($headers);
        if ($headerError) {
            fclose($handle);
            return ['rows' => [], 'years' => [], 'errors' => [$headerError]];
        }

        $map = $this->buildColumnMap($headers);
        $rows = [];
        $years = [];
        $errors = [];
        $lineNo = 1;

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $cols = self::parseCsvLine($line);
            if (count($cols) < 13) {
                $errors[] = "Baris $lineNo dilewati (kolom tidak lengkap).";
                continue;
            }

            $row = $this->mapRow($cols, $map);
            if ($row['tahun'] <= 0) {
                $errors[] = "Baris $lineNo: tahun anggaran tidak valid.";
                continue;
            }

            $rows[] = $row;
            $years[$row['tahun']] = true;
        }

        fclose($handle);

        return [
            'rows' => $rows,
            'years' => array_keys($years),
            'errors' => $errors,
        ];
    }

    private function buildColumnMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $key = strtolower(trim($h));
            if (str_contains($key, 'satuan kerja')) {
                $map['satker'] = $i;
            } elseif ($key === 'kode paket') {
                $map['kode_paket'] = $i;
            } elseif (str_contains($key, 'kode rup')) {
                $map['rup'] = $i;
            } elseif (str_contains($key, 'tahun')) {
                $map['tahun'] = $i;
            } elseif (str_contains($key, 'sumber dana')) {
                $map['sumber_dana'] = $i;
            } elseif (str_contains($key, 'penyedia')) {
                $map['vendor'] = $i;
            } elseif (str_contains($key, 'metode')) {
                $map['metode'] = $i;
            } elseif (str_contains($key, 'jenis')) {
                $map['jenis'] = $i;
            } elseif (str_contains($key, 'nama paket')) {
                $map['nama_paket'] = $i;
            } elseif (str_contains($key, 'status')) {
                $map['status'] = $i;
            } elseif (str_contains($key, 'total nilai')) {
                $map['total_nilai'] = $i;
            } elseif (str_contains($key, 'pdn')) {
                $map['pdn'] = $i;
            }
        }

        return $map + [
            'satker' => 1,
            'kode_paket' => 2,
            'rup' => 3,
            'tahun' => 4,
            'sumber_dana' => 6,
            'vendor' => 7,
            'metode' => 8,
            'jenis' => 9,
            'nama_paket' => 10,
            'status' => 11,
            'total_nilai' => 12,
            'pdn' => 13,
        ];
    }

    private function mapRow(array $cols, array $map): array
    {
        $satker = $cols[$map['satker']] ?? '';
        $packageName = $cols[$map['nama_paket']] ?? '';
        $tahun = (int) preg_replace('/\D/', '', $cols[$map['tahun']] ?? '0');
        $total = $this->parseNumber($cols[$map['total_nilai']] ?? '0');
        $pdn = $this->parseNumber($cols[$map['pdn']] ?? '0');

        [$kecamatan, $lat, $lng] = $this->resolveKecamatan($satker, $packageName);

        return [
            'satker' => $satker,
            'kode_paket' => $cols[$map['kode_paket']] ?? '',
            'rup' => $cols[$map['rup']] ?? '',
            'sumber_dana' => $cols[$map['sumber_dana']] ?? '',
            'vendor' => $cols[$map['vendor']] ?? '',
            'metode' => $cols[$map['metode']] ?? '',
            'jenis' => $cols[$map['jenis']] ?? '',
            'nama_paket' => $packageName,
            'status' => $cols[$map['status']] ?? '',
            'total_nilai' => $total,
            'pdn' => $pdn,
            'kecamatan' => $kecamatan,
            'lat' => $lat,
            'lng' => $lng,
            'tahun' => $tahun,
        ];
    }

    private function parseNumber(string $value): float
    {
        return (float) preg_replace('/[^0-9.-]/', '', $value);
    }

    private function resolveKecamatan(string $satker, string $packageName): array
    {
        $kecamatan = 'KAB. MAJALENGKA';
        $lat = $this->defaultCenter[0];
        $lng = $this->defaultCenter[1];

        foreach ($this->kecamatans as $kec) {
            if (preg_match('/\b' . preg_quote($kec, '/') . '\b/i', $satker)
                || preg_match('/\b' . preg_quote($kec, '/') . '\b/i', $packageName)) {
                $kecamatan = $kec;
                if (isset($this->centers[$kec])) {
                    $lat = $this->centers[$kec][0];
                    $lng = $this->centers[$kec][1];
                }
                break;
            }
        }

        return [$kecamatan, $lat, $lng];
    }

    /**
     * @param array<int, array> $rows
     * @param int[] $yearsToReplace
     */
    public function import(array $rows, array $yearsToReplace): array
    {
        if (empty($rows)) {
            return ['inserted' => 0, 'deleted' => 0, 'error' => 'Tidak ada baris valid untuk diimport.'];
        }

        $insertSql = 'INSERT INTO realizations
            (satker, kode_paket, rup, sumber_dana, vendor, metode, jenis, nama_paket, status, total_nilai, pdn, kecamatan, lat, lng, tahun)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $deleted = 0;

        try {
            $this->pdo->beginTransaction();

            if (!empty($yearsToReplace)) {
                $placeholders = implode(',', array_fill(0, count($yearsToReplace), '?'));
                $del = $this->pdo->prepare("DELETE FROM realizations WHERE tahun IN ($placeholders)");
                $del->execute($yearsToReplace);
                $deleted = $del->rowCount();
            }

            $stmt = $this->pdo->prepare($insertSql);
            $inserted = 0;

            foreach ($rows as $row) {
                $stmt->execute([
                    $row['satker'],
                    $row['kode_paket'],
                    $row['rup'],
                    $row['sumber_dana'],
                    $row['vendor'],
                    $row['metode'],
                    $row['jenis'],
                    $row['nama_paket'],
                    $row['status'],
                    $row['total_nilai'],
                    $row['pdn'],
                    $row['kecamatan'],
                    $row['lat'],
                    $row['lng'],
                    $row['tahun'],
                ]);
                $inserted++;
            }

            $this->pdo->commit();

            return ['inserted' => $inserted, 'deleted' => $deleted, 'error' => null];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['inserted' => 0, 'deleted' => 0, 'error' => $e->getMessage()];
        }
    }

    /** @return array<int, array{tahun: int, total: int, pagu: float}> */
    public function getYearStats(): array
    {
        $stmt = $this->pdo->query(
            'SELECT tahun, COUNT(*) AS total, COALESCE(SUM(total_nilai), 0) AS pagu
             FROM realizations GROUP BY tahun ORDER BY tahun DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
