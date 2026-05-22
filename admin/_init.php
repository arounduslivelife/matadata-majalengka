<?php

require_once dirname(__DIR__) . '/auth.php';
requireAuth();
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/includes/AppConfig.php';
require_once dirname(__DIR__) . '/includes/format_helpers.php';

$user = getCurrentUser();
$adminBase = 'admin';
$pageTitle = $pageTitle ?? 'Admin';
$activeMenu = $activeMenu ?? 'dashboard';

function webhookPublicUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/admin/dashboard.php';
    $basePath = str_replace('\\', '/', dirname(dirname($script)));
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }
    $secret = AppConfig::telegram()['webhook_secret'] ?? '';
    $q = $secret !== '' ? ('?secret=' . urlencode($secret)) : '';
    return $scheme . '://' . $host . $basePath . '/telegram_webhook.php' . $q;
}
