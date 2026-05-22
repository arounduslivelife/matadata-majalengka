<?php
require_once 'auth.php';
requireAuth();
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$db = new SQLite3('audit_trail.sqlite');

if (isset($_GET['clear_logs'])) {
    $db->exec('DELETE FROM visitors');
    $db->exec('VACUUM');
    header('Location: visitors.php?cleared=1');
    exit;
}

$qInsights = $db->query("
    SELECT
        email, name, photo_url,
        COUNT(*) as total_visits,
        MIN(visited_at) as first_visit,
        MAX(visited_at) as last_login,
        COUNT(latitude) as gps_grants
    FROM visitors
    GROUP BY email
    ORDER BY last_login DESC
");
$userInsights = [];
while ($row = $qInsights->fetchArray(SQLITE3_ASSOC)) {
    $status = 'Baru';
    $badge = 'secondary';
    if ($row['total_visits'] > 10) {
        $status = 'Rutin';
        $badge = 'success';
    } elseif ($row['total_visits'] >= 3) {
        $status = 'Aktif';
        $badge = 'primary';
    }
    $row['status'] = $status;
    $row['status_badge'] = $badge;
    $userInsights[] = $row;
}

$visitors = $db->query('SELECT * FROM visitors ORDER BY visited_at DESC LIMIT 100');
$rawLogs = [];
while ($r = $visitors->fetchArray(SQLITE3_ASSOC)) {
    $rawLogs[] = $r;
}

$totalVisitors = $db->querySingle('SELECT COUNT(*) FROM visitors');
$uniqueEmails = count($userInsights);
$gpsGranted = $db->querySingle('SELECT COUNT(*) FROM visitors WHERE latitude IS NOT NULL');

$pageTitle = 'Log Pengunjung';
$activeMenu = 'visitors';
$pageSubtitle = 'Insight keaktifan pengunjung dan log kunjungan (auto-refresh 1 menit)';
$user = getCurrentUser();

require_once __DIR__ . '/admin/includes/layout.php';
adminRenderHeader($pageTitle, $activeMenu);
if (!empty($_GET['cleared'])) {
    adminRenderAlert('Semua log kunjungan telah dikosongkan.', 'success');
}
?>

<div class="row admin-row-tight">
    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($totalVisitors) ?></h3>
                <p>Total Kunjungan</p>
            </div>
            <div class="icon"><i class="fas fa-door-open"></i></div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-12 mb-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= number_format($uniqueEmails) ?></h3>
                <p>Pengunjung Unik</p>
            </div>
            <div class="icon"><i class="fas fa-user-friends"></i></div>
        </div>
    </div>
    <div class="col-lg-4 col-md-12 col-12 mb-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= number_format($gpsGranted) ?></h3>
                <p>Akses GPS Diberikan</p>
            </div>
            <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="fas fa-user-check"></i> Daftar User &amp; Status Keaktifan</h3>
        <span class="badge badge-success badge-pill-live"><i class="fas fa-circle mr-1"></i> Live · 1 mnt</span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>User / Profil</th>
                    <th>Email</th>
                    <th>Keaktifan</th>
                    <th class="text-center">Total Kunjung</th>
                    <th>Login Terakhir</th>
                    <th>GPS Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userInsights as $u): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if ($u['photo_url']): ?>
                                <img src="<?= htmlspecialchars($u['photo_url']) ?>" alt="" class="img-circle mr-2" width="32" height="32" style="object-fit:cover;" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <span class="img-circle mr-2 bg-secondary d-inline-flex align-items-center justify-content-center text-white" style="width:32px;height:32px;font-size:12px;">?</span>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($u['name'] ?? 'User Tanpa Nama') ?></span>
                        </div>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($u['status_badge']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                    <td class="text-center font-weight-bold"><?= (int) $u['total_visits'] ?>x</td>
                    <td class="text-muted small"><?= date('d M Y, H:i', strtotime($u['last_login'] . ' UTC')) ?></td>
                    <td>
                        <?php $gpsPct = $u['total_visits'] > 0 ? round(($u['gps_grants'] / $u['total_visits']) * 100) : 0; ?>
                        <span class="badge badge-<?= $gpsPct > 0 ? 'success' : 'danger' ?>"><?= $gpsPct ?>% GPS</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($userInsights)): ?>
                <tr class="table-empty"><td colspan="6">Belum ada data pengunjung.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card card-outline card-secondary" id="raw-logs">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="fas fa-history"></i> Raw Visit Logs (100 Terakhir)</h3>
        <div>
            <a href="?clear_logs=1" class="btn btn-danger btn-sm"
               onclick="return confirm('Apakah Anda yakin ingin mengosongkan semua log kunjungan? Tindakan ini tidak dapat dibatalkan.');">
                <i class="fas fa-trash-alt"></i> Kosongkan Log
            </a>
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr><th>User</th><th>Email</th><th>GPS</th><th>IP Address</th><th>Waktu</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rawLogs as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['email']) ?></td>
                    <td>
                        <?php if ($v['latitude']): ?>
                            <a href="https://www.google.com/maps?q=<?= $v['latitude'] ?>,<?= $v['longitude'] ?>" target="_blank" rel="noopener" class="text-success small">
                                <i class="fas fa-map-pin"></i> Peta
                            </a>
                            <?php if ($v['accuracy']): ?>
                                <div class="text-muted" style="font-size:0.7rem;">± <?= round($v['accuracy']) ?>m</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">No GPS</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($v['ip_address']) ?></td>
                    <td class="text-muted small"><?= date('d/m/y H:i', strtotime($v['visited_at'] . ' UTC')) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($rawLogs)): ?>
                <tr class="table-empty"><td colspan="5">Belum ada log kunjungan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<meta http-equiv="refresh" content="60">
<?php adminRenderFooter(); ?>
