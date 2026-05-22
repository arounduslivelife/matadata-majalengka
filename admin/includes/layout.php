<?php

if (!function_exists('adminIsAdminScript')) {
    function adminIsAdminScript(): bool
    {
        return (bool) preg_match('#/admin(/|$)#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
    }
}

if (!function_exists('adminProjectBase')) {
    function adminProjectBase(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/dashboard.php');
        $dir = rtrim(dirname($script), '/');
        if (preg_match('#/admin(/|$)#', $script)) {
            $dir = rtrim(dirname($dir), '/');
        }
        if ($dir === '' || $dir === '.') {
            $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__, 2)) ?: '');
            if ($docRoot !== '' && $projectRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
                $base = rtrim(substr($projectRoot, strlen($docRoot)), '/') ?: '';
                return $base;
            }
        }
        $base = $dir;
        return $base;
    }
}

if (!function_exists('adminUrl')) {
    function adminUrl(string $path = 'dashboard.php'): string
    {
        $path = ltrim($path, '/');
        if (adminIsAdminScript()) {
            return $path;
        }
        return adminProjectBase() . '/admin/' . $path;
    }
}

if (!function_exists('adminProjectUrl')) {
    function adminProjectUrl(string $path): string
    {
        $path = ltrim($path, '/');
        if (adminIsAdminScript()) {
            return '../' . $path;
        }
        return adminProjectBase() . '/' . $path;
    }
}

if (!function_exists('adminAsset')) {
    function adminAsset(string $path): string
    {
        if (strpos($path, 'admin/') === 0) {
            $path = substr($path, 6);
        }
        $path = ltrim($path, '/');
        if (strpos($path, 'assets/') !== 0) {
            $path = 'assets/' . $path;
        }
        if (adminIsAdminScript()) {
            return $path;
        }
        return adminProjectBase() . '/admin/' . $path;
    }
}

if (!function_exists('adminRenderAlert')) {
    function adminRenderAlert(?string $message, string $type = 'success'): void
    {
        if ($message === null || $message === '') {
            return;
        }
        $map = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
        ];
        $class = $map[$type] ?? 'alert-info';
        ?>
        <div class="alert <?= $class ?> alert-dismissible fade show admin-flash-alert" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php
    }
}

if (!function_exists('adminNavItems')) {
    function adminNavItems(): array
    {
        return [
            ['header' => 'Utama'],
            ['key' => 'dashboard', 'url' => adminUrl('dashboard.php'), 'icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard'],
            ['header' => 'Data &amp; Layanan'],
            ['key' => 'import', 'url' => adminProjectUrl('admin_import_realisasi.php'), 'icon' => 'fas fa-file-upload', 'label' => 'Import Realisasi'],
            ['key' => 'telegram', 'url' => adminUrl('telegram.php'), 'icon' => 'fab fa-telegram-plane', 'label' => 'Bot Telegram'],
            ['key' => 'visitors', 'url' => adminProjectUrl('visitors.php'), 'icon' => 'fas fa-users', 'label' => 'Log Pengunjung'],
            ['key' => 'ai', 'url' => adminProjectUrl('api_monitor.php'), 'icon' => 'fas fa-robot', 'label' => 'Monitor Audit AI'],
        ];
    }
}

function adminRenderHeader(string $title, string $activeMenu): void
{
    $user = $GLOBALS['user'] ?? [];
    $displayName = htmlspecialchars($user['name'] ?? $user['email'] ?? 'Admin');
    $userEmail = htmlspecialchars($user['email'] ?? '');
    $photo = $user['photo'] ?? '';
    $subtitle = $GLOBALS['pageSubtitle'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — Matadata Admin</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(adminProjectUrl('favicon.png?v=3')) ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?= htmlspecialchars(adminAsset('vendor/fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(adminAsset('vendor/adminlte/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(adminAsset('css/admin-custom.css?v=4')) ?>">
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Buka menu"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= htmlspecialchars(adminProjectUrl('index.php')) ?>" class="nav-link">
                    <i class="fas fa-map-marked-alt mr-1"></i> Dashboard Peta
                </a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto align-items-center">
            <li class="nav-item d-none d-md-inline-block">
                <span class="nav-link text-muted py-0">
                    <i class="far fa-user-circle mr-1"></i><?= $displayName ?>
                </span>
            </li>
            <li class="nav-item">
                <a href="<?= htmlspecialchars(adminProjectUrl('login.php?logout=1')) ?>" class="nav-link text-danger" title="Keluar">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="d-none d-lg-inline ml-1">Keluar</span>
                </a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?= htmlspecialchars(adminUrl('dashboard.php')) ?>" class="brand-link brand-link-matadata">
            <i class="fas fa-map brand-image elevation-1"></i>
            <span class="brand-text font-weight-light"><b>Matadata</b> Admin</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <?php if ($photo): ?>
                    <div class="image">
                        <img src="<?= htmlspecialchars($photo) ?>" class="img-circle elevation-2 admin-user-photo" alt="" width="34" height="34" referrerpolicy="no-referrer" onerror="this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');">
                        <div class="img-circle elevation-2 bg-primary d-none d-flex align-items-center justify-content-center admin-user-photo-fallback">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="image">
                        <div class="img-circle elevation-2 bg-primary d-flex align-items-center justify-content-center admin-user-photo-fallback">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="info overflow-hidden">
                    <a href="<?= htmlspecialchars(adminUrl('dashboard.php')) ?>" class="d-block text-truncate"><?= $displayName ?></a>
                    <?php if ($userEmail): ?><small class="d-block text-truncate"><?= $userEmail ?></small><?php endif; ?>
                </div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="navigation" data-accordion="false">
                    <?php foreach (adminNavItems() as $item): ?>
                        <?php if (!empty($item['header'])): ?>
                    <li class="nav-header"><?= $item['header'] ?></li>
                        <?php else:
                            $active = ($activeMenu === $item['key']) ? ' active' : '';
                            ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link<?= $active ?>">
                            <i class="nav-icon <?= htmlspecialchars($item['icon']) ?>"></i>
                            <p><?= htmlspecialchars($item['label']) ?></p>
                        </a>
                    </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1 class="m-0 text-dark"><?= htmlspecialchars($title) ?></h1>
                        <?php if ($subtitle !== ''): ?>
                            <p class="text-muted mb-0 mt-1 admin-page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right mb-0">
                            <li class="breadcrumb-item"><a href="<?= htmlspecialchars(adminUrl('dashboard.php')) ?>">Admin</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($title) ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content pt-0">
            <div class="container-fluid">
    <?php
}

function adminRenderFooter(): void
{
    ?>
            </div>
        </section>
    </div>

    <footer class="main-footer text-sm">
        <div class="float-right d-none d-sm-inline-block text-muted">
            AdminLTE 3
        </div>
        <strong>Matadata Majalengka</strong> — Panel Administrasi
    </footer>
</div>

<script src="<?= htmlspecialchars(adminAsset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= htmlspecialchars(adminAsset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= htmlspecialchars(adminAsset('vendor/adminlte/js/adminlte.min.js')) ?>"></script>
<script>
document.querySelectorAll('.custom-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
        var label = input.nextElementSibling;
        if (label && label.classList.contains('custom-file-label')) {
            label.textContent = input.files.length ? input.files[0].name : (label.dataset.browse || 'Pilih file');
        }
    });
});
</script>
</body>
</html>
    <?php
}
