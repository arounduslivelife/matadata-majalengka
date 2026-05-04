<?php
date_default_timezone_set('Asia/Jakarta');

// SECURE SESSION CONFIG
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Check if HTTPS is active for secure cookie
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

session_start();

function getConfig() {
    static $config = null;
    if ($config === null) {
        $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    }
    return $config;
}

function requireAuth() {
    if (!isset($_SESSION['user_email'])) {
        header('Location: landing');
        exit;
    }
}

function getCurrentUser() {
    return [
        'email' => $_SESSION['user_email'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'photo' => $_SESSION['user_photo'] ?? null,
        'gps_granted' => $_SESSION['gps_granted'] ?? false
    ];
}

function isAdmin() {
    $config = getConfig();
    $adminEmails = $config['ADMIN_EMAILS'] ?? [];
    return in_array($_SESSION['user_email'] ?? '', $adminEmails);
}
