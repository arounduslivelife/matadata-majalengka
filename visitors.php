<?php
require_once 'auth.php';
requireAuth();
if (!isAdmin()) { header('Location: index.php'); exit; }

$db = new SQLite3('database.sqlite');

// Query 1: Aggregated User Insights
// Note: We use MAX(visited_at) to find the "Last Login"
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
    // Routine Logic
    $status = 'Baru';
    $color = '#94a3b8';
    if ($row['total_visits'] > 10) { $status = 'Rutin'; $color = '#10b981'; }
    elseif ($row['total_visits'] >= 3) { $status = 'Aktif'; $color = '#3b82f6'; }
    
    $row['status'] = $status;
    $row['status_color'] = $color;
    $userInsights[] = $row;
}

// Query 2: Raw Audit Logs (Limited to 100 recent)
$visitors = $db->query('SELECT * FROM visitors ORDER BY visited_at DESC LIMIT 100');
$rawLogs = [];
while ($r = $visitors->fetchArray(SQLITE3_ASSOC)) { $rawLogs[] = $r; }

$totalVisitors = $db->querySingle('SELECT COUNT(*) FROM visitors');
$uniqueEmails = count($userInsights);
$gpsGranted = $db->querySingle('SELECT COUNT(*) FROM visitors WHERE latitude IS NOT NULL');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <title>User Insights — Matadata Admin</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #020617; --card: #0f172a; --accent: #3b82f6; --success: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: #f8fafc; padding: 2rem; scroll-behavior: smooth; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        h1 { font-size: 1.5rem; margin-bottom: 0.3rem; color: var(--accent); }
        .subtitle { opacity: 0.5; font-size: 0.85rem; margin-bottom: 2rem; }
        
        .stats { display: flex; gap: 1rem; margin-bottom: 2.5rem; flex-wrap: wrap; }
        .stat-box { background: var(--card); padding: 1.2rem 1.8rem; border-radius: 18px; border: 1px solid rgba(255,255,255,0.05); min-width: 200px; flex: 1; }
        .stat-box .val { font-size: 2rem; font-weight: 700; color: #fff; }
        .stat-box .label { font-size: 0.75rem; opacity: 0.5; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }

        .back-btn { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 1.5rem; padding: 10px 20px; background: rgba(255,255,255,0.05); border-radius: 12px; color: #94a3b8; text-decoration: none; font-size: 0.85rem; transition: 0.2s; border: 1px solid rgba(255,255,255,0.03); }
        .back-btn:hover { background: var(--accent); color: white; transform: translateX(-5px); }
        
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; margin-top: 3rem; }
        .section-header h2 { font-size: 1.1rem; opacity: 0.8; }

        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 20px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.03); }
        th { background: rgba(59,130,246,0.1); padding: 16px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; text-align: left; opacity: 0.6; }
        td { padding: 14px 16px; font-size: 0.85rem; border-bottom: 1px solid rgba(255,255,255,0.03); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge { padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-cell img { width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.1); }
        .time-cell { opacity: 0.5; font-size: 0.75rem; }
        
        .gps-status { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; background: rgba(16,185,129,0.15); color: var(--success); }
        .gps-status.denied { background: rgba(239,68,68,0.15); color: #ef4444; }

        .logout-btn { position: fixed; top: 20px; right: 20px; padding: 10px 24px; background: rgba(239,68,68,0.1); border-radius: 12px; color: #ef4444; text-decoration: none; font-size: 0.85rem; border: 1px solid rgba(239,68,68,0.2); transition: 0.3s; }
        .logout-btn:hover { background: #ef4444; color: white; }

        .live-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16,185,129,0.1);
            color: #10b981;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1px;
            border: 1px solid rgba(16,185,129,0.2);
            margin-left: 15px;
            vertical-align: middle;
        }
        .live-dot {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.3; transform: scale(1.2); } 100% { opacity: 1; transform: scale(1); } }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← Kembali ke Dashboard</a>
        <a href="login.php?logout=1" class="logout-btn">Logout</a>
        
        <header>
            <h1>🔍 User Engagement Insights <span class="live-tag"><span class="live-dot"></span> LIVE MONITORING</span></h1>
            <p class="subtitle">Memantau keaktifan dan rutinitas pengunjung Matadata Majalengka (Auto-refresh 1m)</p>
        </header>

        <div class="stats">
            <div class="stat-box"><div class="val"><?= number_format($totalVisitors) ?></div><div class="label">Total Kunjungan</div></div>
            <div class="stat-box"><div class="val"><?= number_format($uniqueEmails) ?></div><div class="label">Pengunjung Unik</div></div>
            <div class="stat-box"><div class="val"><?= number_format($gpsGranted) ?></div><div class="label">Akses GPS Diberikan</div></div>
        </div>

        <div class="section-header">
            <h2>Daftar User & Status Keaktifan</h2>
            <a href="#raw-logs" style="font-size: 0.8rem; color: var(--accent); text-decoration: none;">Lihat Raw Logs ↓</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>User / Profil</th>
                    <th>Email</th>
                    <th>Keaktifan</th>
                    <th style="text-align: center;">Total Kunjung</th>
                    <th>Login Terakhir</th>
                    <th>GPS Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($userInsights as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <?php if ($u['photo_url']): ?>
                                <img src="<?= htmlspecialchars($u['photo_url']) ?>" alt="">
                            <?php else: ?>
                                <div style="width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-size:0.7rem;">?</div>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($u['name'] ?? 'User Tanpa Nama') ?></span>
                        </div>
                    </td>
                    <td style="opacity: 0.7;"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge" style="background: <?= $u['status_color'] ?>22; color: <?= $u['status_color'] ?>; border: 1px solid <?= $u['status_color'] ?>44;">
                            <?= $u['status'] ?>
                        </span>
                    </td>
                    <td style="text-align: center; font-weight: 600;"><?= $u['total_visits'] ?>x</td>
                    <td class="time-cell"><?= date('d M Y, H:i', strtotime($u['last_login'] . ' UTC')) ?></td>
                    <td>
                        <span class="gps-status <?= ($u['gps_grants'] == 0) ? 'denied' : '' ?>">
                            <?= round(($u['gps_grants'] / $u['total_visits']) * 100) ?>% GPS
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="section-header" id="raw-logs">
            <h2>Raw Visit Logs (100 Terakhir)</h2>
            <a href="#" style="font-size: 0.8rem; color: var(--accent); text-decoration: none;">Kembali ke Atas ↑</a>
        </div>

        <table style="margin-bottom: 5rem;">
            <thead>
                <tr><th>User</th><th>Email</th><th>GPS</th><th>IP Address</th><th>Waktu</th></tr>
            </thead>
            <tbody>
                <?php foreach($rawLogs as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['email']) ?></td>
                    <td>
                        <?php if ($v['latitude']): ?>
                            <a href="https://www.google.com/maps?q=<?= $v['latitude'] ?>,<?= $v['longitude'] ?>" target="_blank" style="color:var(--success); text-decoration:none; font-size:0.7rem;">📍 View Map</a>
                        <?php else: ?>
                            <span style="opacity:0.3; font-size:0.7rem;">No GPS</span>
                        <?php endif; ?>
                    </td>
                    <td style="opacity: 0.5; font-size: 0.75rem;"><?= htmlspecialchars($v['ip_address']) ?></td>
                    <td class="time-cell"><?= date('d/m/y H:i', strtotime($v['visited_at'] . ' UTC')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
