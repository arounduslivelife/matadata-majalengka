<?php

$pageTitle = 'Dashboard';

$activeMenu = 'dashboard';

$pageSubtitle = 'Ringkasan layanan admin Matadata Majalengka';

require_once __DIR__ . '/_init.php';

require_once dirname(__DIR__) . '/includes/RealisasiImporter.php';



$importer = new RealisasiImporter($pdo);

$yearStats = $importer->getYearStats();

$tg = AppConfig::telegram();



require_once __DIR__ . '/includes/layout.php';

adminRenderHeader($pageTitle, $activeMenu);

?>



<div class="row admin-row-tight">

    <div class="col-lg-3 col-md-6 col-12">

        <div class="small-box bg-info">

            <div class="inner">

                <h3><?= count(AppConfig::groqKeys()) ?></h3>

                <p>API Key Groq</p>

            </div>

            <div class="icon"><i class="fas fa-key"></i></div>

            <a href="<?= htmlspecialchars(adminProjectUrl('api_monitor.php')) ?>" class="small-box-footer">

                Monitor <i class="fas fa-arrow-circle-right"></i>

            </a>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 col-12">

        <div class="small-box <?= !empty($tg['enabled']) ? 'bg-success' : 'bg-secondary' ?>">

            <div class="inner">

                <h3><?= !empty($tg['enabled']) ? 'ON' : 'OFF' ?></h3>

                <p>Bot Telegram</p>

            </div>

            <div class="icon"><i class="fab fa-telegram"></i></div>

            <a href="telegram.php" class="small-box-footer">

                Pengaturan <i class="fas fa-arrow-circle-right"></i>

            </a>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 col-12">

        <div class="small-box bg-warning">

            <div class="inner">

                <h3><?= count($yearStats) ?></h3>

                <p>Tahun Realisasi</p>

            </div>

            <div class="icon"><i class="fas fa-chart-bar"></i></div>

            <a href="<?= htmlspecialchars(adminProjectUrl('admin_import_realisasi.php')) ?>" class="small-box-footer">

                Import <i class="fas fa-arrow-circle-right"></i>

            </a>

        </div>

    </div>

    <div class="col-lg-3 col-md-6 col-12">

        <div class="small-box bg-primary">

            <div class="inner">

                <h3><i class="fas fa-map"></i></h3>

                <p>Dashboard Peta</p>

            </div>

            <div class="icon"><i class="fas fa-globe-asia"></i></div>

            <a href="<?= htmlspecialchars(adminProjectUrl('index.php')) ?>" class="small-box-footer">

                Buka peta <i class="fas fa-arrow-circle-right"></i>

            </a>

        </div>

    </div>

</div>



<div class="row">

    <div class="col-lg-8 col-12">

        <div class="card card-outline card-primary">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-database"></i> Realisasi per tahun</h3>

            </div>

            <div class="card-body p-0 table-responsive">

                <?php if (empty($yearStats)): ?>

                    <p class="text-muted text-center py-4 mb-0">Belum ada data realisasi. <a href="<?= htmlspecialchars(adminProjectUrl('admin_import_realisasi.php')) ?>">Import CSV</a></p>

                <?php else: ?>

                    <table class="table table-striped table-hover mb-0">

                        <thead>

                            <tr><th>Tahun</th><th class="text-right">Paket</th><th class="text-right">Total Pagu</th></tr>

                        </thead>

                        <tbody>

                        <?php foreach ($yearStats as $s): ?>

                            <tr>

                                <td><span class="badge badge-light border"><?= (int) $s['tahun'] ?></span></td>

                                <td class="text-right"><?= number_format((int) $s['total']) ?></td>

                                <td class="text-right font-weight-bold"><?= formatRupiah($s['pagu']) ?></td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <div class="col-lg-4 col-12">

        <div class="card card-outline card-info">

            <div class="card-header">

                <h3 class="card-title"><i class="fab fa-telegram-plane"></i> Bot Telegram</h3>

            </div>

            <div class="card-body">

                <p class="text-muted small mb-3">Jawaban singkat dari data MySQL + Groq untuk chat Telegram.</p>

                <a href="telegram.php" class="btn btn-info btn-block btn-sm">

                    <i class="fab fa-telegram-plane mr-1"></i> Kelola bot

                </a>

            </div>

        </div>

        <div class="card card-outline card-secondary">

            <div class="card-header">

                <h3 class="card-title"><i class="fas fa-link"></i> Akses cepat</h3>

            </div>

            <div class="card-body p-0">

                <ul class="list-group list-group-flush">

                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">

                        <span><i class="fas fa-users text-muted mr-2"></i> Log pengunjung</span>

                        <a href="<?= htmlspecialchars(adminProjectUrl('visitors.php')) ?>" class="btn btn-xs btn-outline-primary btn-sm">Buka</a>

                    </li>

                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">

                        <span><i class="fas fa-file-upload text-muted mr-2"></i> Import realisasi</span>

                        <a href="<?= htmlspecialchars(adminProjectUrl('admin_import_realisasi.php')) ?>" class="btn btn-xs btn-outline-primary btn-sm">Buka</a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</div>



<?php adminRenderFooter(); ?>

