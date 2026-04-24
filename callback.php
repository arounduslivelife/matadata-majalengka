<?php
require_once 'auth.php';

$config = getConfig();
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// Verify state to prevent CSRF
if (!$state || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    header('Location: login?error=invalid_state');
    exit;
}

if (!$code) {
    header('Location: login');
    exit;
}

// Exchange authorization code for access token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$tokenData = [
    'code' => $code,
    'client_id' => $config['GOOGLE_CLIENT_ID'],
    'client_secret' => $config['GOOGLE_CLIENT_SECRET'],
    'redirect_uri' => $config['GOOGLE_REDIRECT_URI'],
    'grant_type' => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$tokenResponse = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($tokenResponse['access_token'])) {
    // Token exchange failed
    header('Location: login?error=token_failed');
    exit;
}

// Get user info from Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenResponse['access_token']]);
$userInfo = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($userInfo['email'])) {
    header('Location: login?error=no_email');
    exit;
}

// Store in session
$_SESSION['user_email'] = $userInfo['email'];
$_SESSION['user_name'] = $userInfo['name'] ?? $userInfo['email'];
$_SESSION['user_photo'] = $userInfo['picture'] ?? '';
$_SESSION['gps_granted'] = false;

// Redirect to dashboard (GPS will be captured there)
header('Location: ./');
exit;
