<?php
require_once 'auth.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    session_start(); // restart clean session
}

// If already logged in, go to dashboard
if (isset($_SESSION['user_email'])) {
    header('Location: ./');
    exit;
}

$config = getConfig();
$clientId = $config['GOOGLE_CLIENT_ID'];
$redirectUri = $config['GOOGLE_REDIRECT_URI'];

// Generate a random state for CSRF protection
if (empty($_SESSION['oauth_state'])) {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(32));
}

$googleAuthUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => $_SESSION['oauth_state']
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Matadata Majalengka</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --accent: #3b82f6;
            --bg: #020617;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Animated background */
        .bg-grid {
            position: fixed;
            inset: 0;
            background-image: 
                linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 20s linear infinite;
        }
        @keyframes gridMove {
            0% { transform: translate(0,0); }
            100% { transform: translate(60px,60px); }
        }

        .bg-glow {
            position: fixed;
            width: 500px; height: 500px;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
        }
        .bg-glow-1 { background: #3b82f6; top: -200px; right: -100px; }
        .bg-glow-2 { background: #8b5cf6; bottom: -200px; left: -100px; animation-delay: -4s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(30px); }
        }

        .login-card {
            position: relative;
            z-index: 10;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            width: 420px;
            max-width: 90vw;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            animation: cardIn 0.6s ease;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(59,130,246,0.3);
            overflow: hidden;
            border: 2px solid rgba(255,255,255,0.1);
        }
        .logo-icon img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(1) brightness(1.2); }

        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 0.3rem;
            background: linear-gradient(135deg, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .subtitle {
            font-size: 0.85rem;
            opacity: 0.5;
            margin-bottom: 2rem;
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 24px;
            background: #ffffff;
            color: #1f2937;
            border: none;
            border-radius: 14px;
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .google-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59,130,246,0.3);
            background: #f0f4ff;
        }
        .google-btn:active { transform: translateY(0); }
        .google-btn svg { width: 20px; height: 20px; flex-shrink: 0; }

        .notice {
            margin-top: 1.5rem;
            font-size: 0.7rem;
            opacity: 0.4;
            line-height: 1.5;
        }
        .notice a { color: var(--accent); text-decoration: none; }

        .badge {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 6px 14px;
            background: rgba(59,130,246,0.1);
            border: 1px solid rgba(59,130,246,0.2);
            border-radius: 20px;
            font-size: 0.7rem;
            color: #60a5fa;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow bg-glow-1"></div>
    <div class="bg-glow bg-glow-2"></div>

    <div class="login-card">
        <div class="logo-icon"><img src="favicon.png" alt="Logo"></div>
        <h1>MATADATA MAJALENGKA</h1>
        <p class="subtitle">Public Monitoring Dashboard</p>

        <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="google-btn">
            <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Masuk dengan Google
        </a>

        <div class="badge">🔒 Akses Terbatas — Verifikasi Identitas</div>

        <p class="notice">
            Dengan masuk, Anda menyetujui bahwa data email dan lokasi GPS Anda akan dicatat untuk keamanan dan transparansi penggunaan dashboard publik ini.
        </p>
    </div>
</body>
</html>
