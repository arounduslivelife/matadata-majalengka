<?php
require_once 'auth.php';
requireAuth();
if (!isAdmin()) { header('Location: index.php'); exit; }

$statusFile = 'api_status.json';
$progressFile = 'progress.json';

$apiStatus = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
$progress = file_exists($progressFile) ? json_decode(file_get_contents($progressFile), true) : ['processed' => 0, 'total' => 0, 'status' => 'OFFLINE'];

function formatTime($iso) {
    if (!$iso) return "Never";
    $dt = new DateTime($iso);
    return $dt->format('H:i:s');
}

function getStatusColor($status) {
    switch($status) {
        case 'ACTIVE': return '#10b981'; // Green
        case 'RATE_LIMITED': return '#f59e0b'; // Amber
        case 'DAILY_LIMIT_HIT': return '#ef4444'; // Red
        default: return '#64748b'; // Gray
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Monitor - Matadata Majalengka</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #020617;
            --card: #1e293b;
            --accent: #3b82f6;
            --text: #f8fafc;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 1000px;
            width: 100%;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        h1 { margin: 0; color: var(--accent); font-size: 2rem; }
        .back-link { color: var(--text); text-decoration: none; opacity: 0.6; transition: 0.3s; }
        .back-link:hover { opacity: 1; color: var(--accent); }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .api-card {
            background: var(--card);
            padding: 1.5rem;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
            transition: 0.3s;
        }
        .api-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .api-card.active {
            border-color: var(--accent);
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #1e293b, #27354d);
            border-width: 2px;
        }

        .active-label {
            font-size: 0.65rem;
            background: var(--accent);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
            vertical-align: middle;
            animation: pulse-active 1.5s infinite;
        }

        @keyframes pulse-active {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }

        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            box-shadow: 0 0 15px currentColor;
        }

        .key-name { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
        .status-text { font-size: 0.8rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 1px; }

        .stats {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        .stat-item b { display: block; font-size: 1rem; color: var(--accent); }

        .error-log {
            margin-top: 1rem;
            padding: 10px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            font-size: 0.75rem;
            color: #ef4444;
            max-height: 50px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .progress-section {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .bar-container {
            height: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 5px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: var(--accent);
            width: <?php echo ($progress['total'] > 0) ? ($progress['processed'] / $progress['total'] * 100) : 0; ?>%;
            box-shadow: 0 0 20px var(--accent);
            transition: 1s ease-in-out;
        }

        .control-panel {
            background: var(--card);
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .ctrl-btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: none;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .ctrl-btn.start {
            background: var(--accent);
            color: white;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }
        .ctrl-btn.start:hover { transform: scale(1.05); box-shadow: 0 0 25px rgba(59, 130, 246, 0.5); }
        .ctrl-btn.start:disabled { background: #475569; cursor: not_allowed; opacity: 0.5; transform: none; }

        .ctrl-btn.stop {
            background: #ef4444;
            color: white;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
        }
        .ctrl-btn.stop:hover { transform: scale(1.05); box-shadow: 0 0 25px rgba(239, 68, 68, 0.5); }
        .ctrl-btn.stop:disabled { background: #475569; cursor: not_allowed; opacity: 0.5; transform: none; }
    </style>
    <script>
        async function controlAudit(action) {
            const btn = action === 'start' ? document.getElementById('start-btn') : document.getElementById('stop-btn');
            const originalText = btn.innerText;
            btn.innerText = 'Processing...';
            btn.disabled = true;

            try {
                const response = await fetch('control_audit.php?action=' + action);
                const data = await response.json();
                alert(data.message);
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                alert('Error: ' + e.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }
    </script>
    <meta http-equiv="refresh" content="10">
</head>
<body>

<div class="container">
    <header>
        <div>
            <h1>API Monitor Center</h1>
            <p style="opacity: 0.6; margin: 5px 0 0 0;">Monitoring Hybrid Multi-Provider Rotation</p>
        </div>
        <a href="index.php" class="back-link">← Kembali ke Dashboard</a>
    </header>

    <div class="control-panel">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div class="status-badge" style="position: static; display: inline-block; background-color: <?php echo ($progress['status'] === 'RUNNING' || $progress['status'] === 'WAITING') ? '#10b981' : '#64748b'; ?>; box-shadow: 0 0 15px <?php echo ($progress['status'] === 'RUNNING' || $progress['status'] === 'WAITING') ? '#10b981' : '#64748b'; ?>;"></div>
            <h2 style="margin: 0; font-size: 1.2rem;">AI Engine Controller</h2>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button onclick="controlAudit('start')" class="ctrl-btn start" id="start-btn" <?php echo ($progress['status'] === 'RUNNING' || $progress['status'] === 'WAITING') ? 'disabled' : ''; ?>>Start Audit</button>
            <button onclick="controlAudit('stop')" class="ctrl-btn stop" id="stop-btn" <?php echo ($progress['status'] !== 'RUNNING' && $progress['status'] !== 'WAITING') ? 'disabled' : ''; ?>>Stop Audit</button>
        </div>
    </div>

    <div class="progress-section">
        <div class="progress-header">
            <span>Overall Audit Progress</span>
            <b><?php echo number_format(($progress['total'] > 0) ? ($progress['processed'] / $progress['total'] * 100) : 0, 1); ?>%</b>
        </div>
        <div class="bar-container">
            <div class="bar-fill"></div>
        </div>
        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.85rem; opacity: 0.6;">
            <span><?php echo number_format($progress['processed'], 0, ',', '.'); ?> dari <?php echo number_format($progress['total'], 0, ',', '.'); ?> Paket</span>
            <span>Status: <b style="color: var(--accent);"><?php echo $progress['status']; ?></b></span>
        </div>
    </div>

    <div class="dashboard-grid">
        <?php foreach($apiStatus as $index => $key): ?>
            <div class="api-card <?php echo ($key['is_active'] ?? false) ? 'active' : ''; ?>">
                <div class="status-badge" style="color: <?php echo getStatusColor($key['status']); ?>; background-color: currentColor;"></div>
                <div style="font-size: 0.7rem; color: var(--accent); font-weight: 600; margin-bottom: 5px;">
                    <?php echo $key['provider'] ?? 'GROQ'; ?>
                </div>
                <div class="key-name">
                    <?php echo $key['key_name']; ?>
                    <?php if($key['is_active'] ?? false): ?>
                        <span class="active-label">TARGET ACTIVE</span>
                    <?php endif; ?>
                </div>
                <div class="status-text"><?php echo str_replace('_', ' ', $key['status']); ?></div>

                <div class="stats">
                    <div class="stat-item">
                        <span>Last Used</span>
                        <b><?php echo formatTime($key['last_used']); ?></b>
                    </div>
                    <div class="stat-item">
                        <span>Errors Today</span>
                        <b><?php echo $key['error_count']; ?></b>
                    </div>
                </div>

                <?php if($key['last_error']): ?>
                    <div class="error-log" title="<?php echo htmlspecialchars($key['last_error']); ?>">
                        ⚠️ <?php echo htmlspecialchars($key['last_error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="refresh-hint">
        Halaman diperbarui otomatis setiap 10 detik.
    </div>
</div>

</body>
</html>
