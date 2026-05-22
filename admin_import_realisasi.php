<?php

require_once 'auth.php';

requireAuth();

if (!isAdmin()) {

    header('Location: index.php');

    exit;

}



require_once 'db.php';

require_once 'includes/RealisasiImporter.php';

require_once 'includes/format_helpers.php';



if (empty($_SESSION['admin_csrf'])) {

    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));

}

$csrf = $_SESSION['admin_csrf'];



if (isset($_GET['cancel'])) {

    unset($_SESSION['import_preview']);

    header('Location: admin_import_realisasi.php');

    exit;

}



$importer = new RealisasiImporter($pdo);

$yearStats = $importer->getYearStats();



$message = null;

$messageType = 'info';

$preview = null;



const MAX_BYTES = 25 * 1024 * 1024;



function adminFlash(string $type, string $text): void

{

    global $message, $messageType;

    $message = $text;

    $messageType = $type;

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf'] ?? '';

    if (!hash_equals($csrf, $token)) {

        adminFlash('error', 'Token keamanan tidak valid. Muat ulang halaman dan coba lagi.');

    } elseif (isset($_POST['confirm_import'])) {

        $payload = $_SESSION['import_preview'] ?? null;

        if (!$payload || empty($payload['rows'])) {

            adminFlash('error', 'Sesi preview kedaluwarsa. Upload ulang file CSV.');

        } else {

            $replaceYears = !empty($_POST['replace_years']) ? array_map('intval', (array) $_POST['replace_years']) : [];

            $result = $importer->import($payload['rows'], $replaceYears);

            if ($result['error']) {

                adminFlash('error', 'Import gagal: ' . $result['error']);

            } else {

                unset($_SESSION['import_preview']);

                adminFlash('success', sprintf(

                    'Import berhasil: %s baris ditambahkan. %s baris lama dihapus (tahun yang diganti).',

                    number_format($result['inserted']),

                    number_format($result['deleted'])

                ));

                $yearStats = $importer->getYearStats();

            }

        }

    } elseif (isset($_POST['preview'])) {

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {

            $code = $_FILES['csv_file']['error'] ?? -1;

            adminFlash('error', 'Upload gagal (kode error: ' . $code . ').');

        } elseif ($_FILES['csv_file']['size'] > MAX_BYTES) {

            adminFlash('error', 'File terlalu besar. Maksimum 25 MB.');

        } else {

            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

            if ($ext !== 'csv') {

                adminFlash('error', 'Hanya file .csv yang diperbolehkan.');

            } else {

                $uploadDir = __DIR__ . '/uploads';

                if (!is_dir($uploadDir)) {

                    mkdir($uploadDir, 0755, true);

                }

                $tmpName = $uploadDir . '/preview_' . time() . '_' . bin2hex(random_bytes(4)) . '.csv';

                if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpName)) {

                    adminFlash('error', 'Gagal menyimpan file sementara.');

                } else {

                    $parsed = $importer->parseFile($tmpName);

                    @unlink($tmpName);



                    if (!empty($parsed['errors']) && empty($parsed['rows'])) {

                        adminFlash('error', implode(' ', array_slice($parsed['errors'], 0, 3)));

                    } else {

                        $totalPagu = array_sum(array_column($parsed['rows'], 'total_nilai'));

                        $preview = [

                            'row_count' => count($parsed['rows']),

                            'years' => $parsed['years'],

                            'total_pagu' => $totalPagu,

                            'sample' => array_slice($parsed['rows'], 0, 5),

                            'warnings' => array_slice($parsed['errors'], 0, 10),

                        ];

                        $_SESSION['import_preview'] = [

                            'rows' => $parsed['rows'],

                            'years' => $parsed['years'],

                            'filename' => $_FILES['csv_file']['name'],

                        ];

                    }

                }

            }

        }

    }

}



$pageTitle = 'Import Realisasi';

$activeMenu = 'import';

$pageSubtitle = 'Upload CSV Inaproc ke tabel realizations untuk layer peta';

$user = getCurrentUser();



require_once __DIR__ . '/admin/includes/layout.php';

adminRenderHeader($pageTitle, $activeMenu);

adminRenderAlert($message, $messageType);

?>



<?php if (!$preview): ?>

<div class="card card-outline card-primary">

    <div class="card-header">

        <h3 class="card-title"><i class="fas fa-file-upload"></i> Upload CSV Realisasi</h3>

    </div>

    <div class="card-body">

        <form method="post" enctype="multipart/form-data">

            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group">

                <label for="csv_file">File CSV Realisasi (format Inaproc)</label>

                <div class="custom-file">

                    <input type="file" class="custom-file-input" name="csv_file" id="csv_file" accept=".csv,text/csv" required>

                    <label class="custom-file-label" for="csv_file" data-browse="Pilih">Pilih file CSV…</label>

                </div>

            </div>

            <button type="submit" name="preview" value="1" class="btn btn-primary">

                <i class="fas fa-search mr-1"></i> Preview Data

            </button>

        </form>

        <hr class="my-3">

        <p class="text-muted small mb-0">

            <i class="fas fa-info-circle mr-1"></i>

            Format kolom wajib sama dengan export Inaproc, misalnya <code>raw/realisasi2026alldept.csv</code>.

            Tahun dari kolom <strong>Tahun Anggaran</strong>. Kecamatan diturunkan otomatis dari nama satker/paket.

        </p>

    </div>

</div>

<?php endif; ?>



<?php if ($preview): ?>

<div class="row admin-row-tight">

    <div class="col-lg-4 col-md-6 col-12">

        <div class="small-box bg-info">

            <div class="inner">

                <h3><?= number_format($preview['row_count']) ?></h3>

                <p>Baris valid</p>

            </div>

            <div class="icon"><i class="fas fa-list"></i></div>

        </div>

    </div>

    <div class="col-lg-4 col-md-6 col-12">

        <div class="small-box bg-success">

            <div class="inner">

                <h3 style="font-size:1.05rem;"><?= formatRupiah($preview['total_pagu']) ?></h3>

                <p>Total nilai</p>

            </div>

            <div class="icon"><i class="fas fa-coins"></i></div>

        </div>

    </div>

    <div class="col-lg-4 col-md-12 col-12">

        <div class="small-box bg-warning">

            <div class="inner">

                <h3 style="font-size:1.2rem;"><?= htmlspecialchars(implode(', ', $preview['years'])) ?></h3>

                <p>Tahun terdeteksi</p>

            </div>

            <div class="icon"><i class="fas fa-calendar-alt"></i></div>

        </div>

    </div>

</div>



<div class="card card-outline card-warning">

    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">

        <h3 class="card-title mb-0"><i class="fas fa-table"></i> Preview Import</h3>

        <span class="text-muted small">File: <?= htmlspecialchars($_SESSION['import_preview']['filename'] ?? '-') ?></span>

    </div>

    <div class="card-body">

        <?php if (!empty($preview['warnings'])): ?>

            <div class="callout callout-info">

                <strong>Catatan:</strong> <?= count($preview['warnings']) ?> baris dilewati.

                <?= htmlspecialchars(implode(' ', $preview['warnings'])) ?>

            </div>

        <?php endif; ?>



        <div class="table-responsive">

            <table class="table table-striped table-sm mb-0">

                <thead>

                    <tr><th>Tahun</th><th>Kecamatan</th><th>Nama Paket</th><th class="text-right">Nilai</th></tr>

                </thead>

                <tbody>

                    <?php foreach ($preview['sample'] as $row): ?>

                    <tr>

                        <td><?= (int) $row['tahun'] ?></td>

                        <td><?= htmlspecialchars($row['kecamatan']) ?></td>

                        <td><?= htmlspecialchars(mb_substr($row['nama_paket'], 0, 60)) ?><?= mb_strlen($row['nama_paket']) > 60 ? '…' : '' ?></td>

                        <td class="text-right"><?= formatRupiah($row['total_nilai']) ?></td>

                    </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>



        <form method="post" class="mt-4 border-top pt-3">

            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

            <p class="text-muted small">Centang tahun yang ingin <strong>diganti</strong> (data lama dihapus sebelum insert).</p>

            <?php foreach ($preview['years'] as $y): ?>

                <?php

                $existing = 0;

                foreach ($yearStats as $s) {

                    if ((int) $s['tahun'] === (int) $y) {

                        $existing = (int) $s['total'];

                    }

                }

                ?>

                <div class="custom-control custom-checkbox mb-2">

                    <input type="checkbox" class="custom-control-input" name="replace_years[]" value="<?= (int) $y ?>" id="year_<?= (int) $y ?>" checked>

                    <label class="custom-control-label" for="year_<?= (int) $y ?>">

                        Tahun <?= (int) $y ?> — ganti <?= number_format($existing) ?> record lama

                    </label>

                </div>

            <?php endforeach; ?>

            <button type="submit" name="confirm_import" value="1" class="btn btn-danger"

                    onclick="return confirm('Yakin import? Data tahun tercentang akan dihapus dan diganti.');">

                <i class="fas fa-database mr-1"></i> Konfirmasi Import

            </button>

            <a href="admin_import_realisasi.php?cancel=1" class="btn btn-default ml-1">Batal</a>

        </form>

    </div>

</div>

<?php endif; ?>



<?php if (!empty($yearStats)): ?>

<div class="card card-outline card-secondary">

    <div class="card-header">

        <h3 class="card-title"><i class="fas fa-database"></i> Data saat ini</h3>

    </div>

    <div class="card-body p-0 table-responsive">

        <table class="table table-striped mb-0">

            <thead><tr><th>Tahun</th><th class="text-right">Paket</th><th class="text-right">Total</th></tr></thead>

            <tbody>

                <?php foreach ($yearStats as $s): ?>

                <tr>

                    <td><span class="badge badge-light border"><?= (int) $s['tahun'] ?></span></td>

                    <td class="text-right"><?= number_format((int) $s['total']) ?></td>

                    <td class="text-right font-weight-bold"><?= formatRupiah((float) $s['pagu']) ?></td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>



<?php adminRenderFooter(); ?>

