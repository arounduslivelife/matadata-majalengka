<?php
require_once 'auth.php';
requireAuth();
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$statusFile = 'api_status.json';
$progressFile = 'progress.json';

$apiStatus = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
$progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : ['processed' => 0, 'total' => 0, 'status' => 'OFFLINE'];

function formatTime($iso)
{
    if (!$iso) {
        return 'Never';
    }
    $dt = new DateTime($iso);
    return $dt->format('H:i:s');
}

function apiStatusBadge(string $status): string
{
    switch ($status) {
        case 'ACTIVE':
            return 'success';
        case 'RATE_LIMITED':
            return 'warning';
        case 'DAILY_LIMIT_HIT':
            return 'danger';
        default:
            return 'secondary';
    }
}

$progressPct = ($progress['total'] > 0) ? ($progress['processed'] / $progress['total'] * 100) : 0;
$isRunning = in_array($progress['status'] ?? '', ['RUNNING', 'WAITING'], true);

$pageTitle = 'Monitor Audit AI';
$activeMenu = 'ai';
$pageSubtitle = 'Kontrol engine audit & rotasi API key Groq (auto-refresh 10 detik)';
$user = getCurrentUser();

require_once __DIR__ . '/admin/includes/layout.php';
adminRenderHeader($pageTitle, $activeMenu);
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="fas fa-robot"></i> AI Engine Controller</h3>
        <span class="badge badge-<?= $isRunning ? 'success badge-pill-live' : 'secondary' ?>">
            <?php if ($isRunning): ?><i class="fas fa-circle mr-1"></i><?php endif; ?>
            <?= htmlspecialchars($progress['status'] ?? 'OFFLINE') ?>
        </span>
    </div>
    <div class="card-body">
        <p class="text-muted small">Monitoring Hybrid Multi-Provider Rotation</p>
        <div class="btn-group mb-3">
            <button type="button" class="btn btn-primary" id="start-btn" <?= $isRunning ? 'disabled' : '' ?> onclick="controlAudit('start')">
                <i class="fas fa-play mr-1"></i> Start Audit
            </button>
            <button type="button" class="btn btn-danger" id="stop-btn" <?= !$isRunning ? 'disabled' : '' ?> onclick="controlAudit('stop')">
                <i class="fas fa-stop mr-1"></i> Stop Audit
            </button>
        </div>
        <div class="d-flex justify-content-between mb-1">
            <span class="admin-stat-label">Progress audit</span>
            <strong><?= number_format($progressPct, 1) ?>%</strong>
        </div>
        <div class="progress mb-2" style="height:14px;">
            <div class="progress-bar bg-primary progress-bar-striped <?= $isRunning ? 'progress-bar-animated' : '' ?>" role="progressbar"
                 style="width:<?= min(100, max(0, $progressPct)) ?>%;"
                 aria-valuenow="<?= (int) $progressPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between text-muted small">
            <span><i class="fas fa-boxes mr-1"></i><?= number_format($progress['processed'] ?? 0, 0, ',', '.') ?> / <?= number_format($progress['total'] ?? 0, 0, ',', '.') ?> paket</span>
        </div>
    </div>
</div>

<div class="row admin-row-tight">
    <?php foreach ($apiStatus as $key): ?>
        <?php
        $active = !empty($key['is_active']);
        $badge = apiStatusBadge($key['status'] ?? '');
        ?>
    <div class="col-lg-4 col-md-6 col-12">
        <div class="card h-100 <?= $active ? 'card-primary card-outline' : 'card-outline card-secondary' ?>">
            <div class="card-header">
                <span class="badge badge-<?= $badge ?> float-right"><?= htmlspecialchars(str_replace('_', ' ', $key['status'] ?? '')) ?></span>
                <h3 class="card-title mb-0" style="font-size:0.95rem;">
                    <?= htmlspecialchars($key['key_name'] ?? 'Key') ?>
                    <?php if ($active): ?><span class="badge badge-primary ml-1">ACTIVE</span><?php endif; ?>
                </h3>
                <div class="text-muted small mt-1"><?= htmlspecialchars($key['provider'] ?? 'GROQ') ?></div>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 border-right">
                        <div class="admin-stat-label">Last Used</div>
                        <strong class="d-block"><?= htmlspecialchars(formatTime($key['last_used'] ?? null)) ?></strong>
                    </div>
                    <div class="col-6">
                        <div class="admin-stat-label">Errors Today</div>
                        <strong class="d-block text-<?= ((int) ($key['error_count'] ?? 0)) > 0 ? 'danger' : 'dark' ?>"><?= (int) ($key['error_count'] ?? 0) ?></strong>
                    </div>
                </div>
                <?php if (!empty($key['last_error'])): ?>
                    <div class="callout callout-danger mt-3 mb-0 py-2">
                        <small><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($key['last_error']) ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($apiStatus)): ?>
    <div class="col-12">
        <div class="callout callout-warning mb-0">
            Belum ada data status API. Jalankan audit untuk mengisi <code>api_status.json</code>.
        </div>
    </div>
    <?php endif; ?>
</div>

<p class="text-muted small mb-0"><i class="fas fa-sync-alt mr-1"></i> Halaman diperbarui otomatis setiap 10 detik.</p>

<meta http-equiv="refresh" content="10">
<script>
async function controlAudit(action) {
    var btn = action === 'start' ? document.getElementById('start-btn') : document.getElementById('stop-btn');
    var originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';
    btn.disabled = true;
    try {
        var response = await fetch('control_audit.php?action=' + action);
        var data = await response.json();
        alert(data.message);
        setTimeout(function () { location.reload(); }, 1000);
    } catch (e) {
        alert('Error: ' + e.message);
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}
</script>
<?php adminRenderFooter(); ?>
