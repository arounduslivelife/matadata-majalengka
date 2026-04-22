<?php
require_once 'auth.php';
requireAuth();
$user = getCurrentUser();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$db = new SQLite3('database.sqlite');

// Get audit stats per kecamatan
function formatPagu($p) {
    if (!$p || $p === 0) return "Rp 0";
    if ($p >= 1000000000) return "Rp" . number_format($p/1000000000, 1) . " Miliar";
    if ($p >= 1000000) return "Rp" . number_format($p/1000000, 0) . " Juta";
    return "Rp" . number_format($p, 0, ',', '.');
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$results = $db->query("
    SELECT kecamatan, 
           COUNT(*) as total, 
           SUM(pagu) as total_pagu,
           SUM(CASE WHEN risk_score = 'High' OR risk_score = 'ABSURD' THEN 1 ELSE 0 END) as high_risk_count
    FROM packages 
    WHERE processed = 1 
    GROUP BY kecamatan
");

$stats = [];
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    if ($row['kecamatan']) {
        $stats[$row['kecamatan']] = [
            'total' => $row['total'],
            'total_pagu' => $row['total_pagu'],
            'high_risk' => $row['high_risk_count']
        ];
    }
}

// Get all audited packages for the map details
$audits_query = $db->query("SELECT * FROM packages WHERE processed = 1");
$all_audits = [];
while ($p = $audits_query->fetchArray(SQLITE3_ASSOC)) {
    $all_audits[] = [
        'id' => $p['id'],
        'kecamatan' => $p['kecamatan'],
        'nama' => $p['nama_paket'],
        'pagu' => $p['pagu'],
        'risk' => $p['risk_score'],
        'note' => $p['audit_note'],
        'satker' => $p['satker']
    ];
}

// Get recent high risk packages for the sidebar
$high_risk_packets = $db->query("
    SELECT * FROM packages 
    WHERE processed = 1 AND (risk_score = 'High' OR risk_score = 'ABSURD')
    ORDER BY pagu DESC LIMIT 5
");

// NEW: Get Dana Desa stats per village
$village_results = $db->query("SELECT * FROM villages");
$village_stats = [];
while ($row = $village_results->fetchArray(SQLITE3_ASSOC)) {
    $village_stats[$row['nm_kelurahan']] = [
        'kecamatan' => $row['nm_kecamatan'],
        'budget' => $row['budget_2025'],
        'risk' => $row['risk_score']
    ];
}

// NEW: Get Dana Desa stats per kecamatan (rankings)
$kec_dd_results = $db->query("
    SELECT nm_kecamatan, SUM(budget_2025) as total_budget, COUNT(*) as village_count
    FROM villages 
    GROUP BY nm_kecamatan 
    ORDER BY total_budget DESC
");
$kec_dd_stats = [];
while ($row = $kec_dd_results->fetchArray(SQLITE3_ASSOC)) {
    $kec_dd_stats[] = $row;
}
$total_majalengka_dd = $db->querySingle("SELECT SUM(budget_2025) FROM villages");

// NEW: Get Poverty stats per kecamatan
$poverty_results = $db->query("SELECT * FROM district_stats ORDER BY poverty_count DESC");
$poverty_stats = [];
while ($row = $poverty_results->fetchArray(SQLITE3_ASSOC)) {
    $poverty_stats[$row['nm_kecamatan']] = [
        'count' => $row['poverty_count'],
        'pkh' => $row['kpm_pkh'],
        'bpnt' => $row['kpm_bpnt'],
        'road_pct' => $row['road_firmness_pct'] // Added road stats
    ];
}
$total_kpm_majalengka = $db->querySingle("SELECT SUM(poverty_count) FROM district_stats");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matadata Majalengka - AI Audit Dashboard</title>
    <link rel="icon" type="image/png" href="favicon.png?v=3">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --accent: #3b82f6;
            --danger: #ef4444;
            --bg: #020617;
            --card: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: #f8fafc;
            margin: 0;
            display: flex;
            flex-direction: row;
            height: 100vh;
            overflow: hidden;
        }

        /* Responsive Layout */
        @media (max-width: 768px) {
            body { flex-direction: column; overflow: hidden; height: 100vh; }
            .sidebar { 
                position: fixed;
                left: -100%;
                top: 0;
                width: 85% !important;
                height: 100vh;
                transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 9999;
                order: unset;
            }
            .sidebar.active { left: 0; }
            #map { width: 100%; height: 100vh !important; order: unset; flex-shrink: 0; }
            .hamburger { display: flex !important; }
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            width: 45px;
            height: 45px;
            background: var(--accent);
            color: white;
            border-radius: 10px;
            z-index: 9000;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-size: 1.2rem;
        }

        /* Sidebar */
        .sidebar {
            width: 350px;
            background: rgba(15, 23, 42, 0.7);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 10px 0 30px rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.05);
        }

        h1 { font-size: 1.5rem; margin: 0; color: var(--accent); letter-spacing: -0.05em; }
        .subtitle { font-size: 0.8rem; opacity: 0.6; margin-top: -1rem; }

        .stat-card {
            background: var(--card);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }

        .stat-card h3 { margin: 0; font-size: 0.9rem; opacity: 0.8; }
        .stat-card .value { font-size: 2rem; font-weight: 600; }

        .packet-list { flex-grow: 1; overflow-y: auto; }
        .packet-item {
            background: rgba(255,255,255,0.05);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .packet-item:hover { background: rgba(255,255,255,0.1); }
        .packet-item .tag { 
            background: var(--danger); 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-size: 0.7rem; 
            font-weight: bold; 
        }

        .close-sidebar { display: none; }
        @media (max-width: 768px) {
            .close-sidebar { display: block !important; }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--primary);
            padding: 2.5rem;
            border-radius: 20px;
            width: 90%;
            max-width: 650px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 2rem;
            cursor: pointer;
            opacity: 0.5;
            transition: 0.3s;
        }
        .close-modal:hover { opacity: 1; color: var(--accent); }

        .info-btn {
            position: fixed;
            bottom: 305px; /* Di atas Legenda */
            right: 30px;
            width: 45px;
            height: 45px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            z-index: 6500;
            transition: 0.3s;
            font-size: 1.2rem;
            font-weight: bold;
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--accent);
        }
        .info-btn:hover { transform: scale(1.1) rotate(15deg); background: var(--accent); color: white; }

        /* Futuristic Search Bar */
        .search-container {
            position: fixed;
            top: 25px;
            right: 30px;
            width: 300px;
            z-index: 7000;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-icon {
            position: absolute;
            left: 15px;
            opacity: 0.6;
            font-size: 1rem;
            pointer-events: none;
            color: var(--accent);
        }
        #searchInput {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(15px);
            color: white;
            font-family: 'Outfit';
            font-size: 0.9rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            outline: none;
            transition: all 0.3s;
        }
        #searchInput:focus {
            border-color: var(--accent);
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.4);
            width: 380px;
            transform: translateX(-80px);
            background: rgba(15, 23, 42, 0.9);
        }
        .search-results {
            position: absolute;
            top: calc(100% + 12px);
            left: -80px;
            width: 380px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(25px);
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.08);
            max-height: 450px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 25px 50px rgba(0,0,0,0.7);
            padding: 8px;
        }
        .search-results.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .result-item {
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .result-item:last-child { border-bottom: none; }
        .result-item:hover {
            background: rgba(59, 130, 246, 0.15);
            transform: translateX(4px);
        }
        .result-item .type {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--accent);
            opacity: 0.8;
            margin-bottom: 4px;
        }
        .result-item .name {
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-meta {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 1.5rem;
            padding: 12px;
            background: rgba(0,0,0,0.25);
            border-radius: 10px;
            border-left: 3px solid var(--accent);
            line-height: 1.6;
        }
        .sidebar-meta b { opacity: 1; color: rgba(255,255,255,0.9); }
        .sidebar-why {
            font-size: 0.75rem;
            margin-top: 12px;
            padding: 12px;
            background: rgba(59,130,246,0.08);
            border-radius: 10px;
            line-height: 1.6;
            opacity: 0.8;
            font-style: italic;
        }

        /* Map Legend transformed into Popup Modal */
        .map-legend {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 28px;
            border: 1px solid rgba(255,255,255,0.15);
            z-index: 11000; /* Di atas segalanya */
            width: 320px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.7);
            display: none; /* Hidden by default */
            animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .legend-title { 
            font-size: 0.65rem; 
            font-weight: 800; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            margin-bottom: 15px; 
            opacity: 0.6;
            color: var(--accent);
        }
        .legend-item { display: flex; align-items: center; gap: 12px; font-size: 0.75rem; margin-bottom: 12px; }
        .legend-color { width: 14px; height: 14px; border-radius: 4px; box-shadow: 0 0 10px rgba(0,0,0,0.3); flex-shrink: 0; }
        .close-legend {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.4;
            transition: 0.3s;
        }
        .close-legend:hover { opacity: 1; color: var(--danger); }

        /* Custom Zoom Controls */
        .leaflet-control-zoom { border: none !important; margin-left: 20px !important; margin-top: 20px !important; }
        .leaflet-control-zoom-in, .leaflet-control-zoom-out { 
            background: rgba(15, 23, 42, 0.8) !important; 
            color: white !important; 
            border: 1px solid rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(8px);
            width: 35px !important;
            height: 35px !important;
            line-height: 35px !important;
            border-radius: 10px !important;
            margin-bottom: 5px !important;
        }
        .leaflet-control-zoom-in:hover, .leaflet-control-zoom-out:hover { background: var(--accent) !important; color: white !important; }



        .logic-grid {
            margin-top: 1.5rem;
            display: grid;
            gap: 1.2rem;
        }
        .logic-item {
            background: rgba(255,255,255,0.03);
            padding: 1rem;
            border-radius: 12px;
            border-left: 3px solid var(--accent);
        }
        .logic-item b { color: var(--accent); display: block; margin-bottom: 5px; }
        .logic-item p { margin: 0; font-size: 0.9rem; opacity: 0.7; line-height: 1.4; }

        /* Map Container */
        #map {
            flex-grow: 1;
            background: #111;
            height: 100vh;
        }

        /* Leaflet Overrides */
        .leaflet-container { background: #020617 !important; }
        .info-box {
            background: rgba(15, 23, 42, 0.9);
            padding: 1rem;
            border-radius: 8px;
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(8px);
        }

        /* Mode Switcher - Bottom Right */
        .mode-switcher {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 6000;
            display: flex;
            flex-direction: column;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            padding: 8px;
            border-radius: 18px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
            gap: 6px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .mode-btn {
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
            color: rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            gap: 10px;
            width: 160px;
        }
        .mode-btn.active {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }
        .mode-btn.active.green { background: var(--success); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }

        /* Smooth Interactions */
        .stat-card, .packet-item, .mode-btn, .info-btn, .legend-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .legend-btn {
            position: fixed;
            bottom: 250px; /* Tepat di atas panel mode switcher */
            right: 30px;
            width: 45px;
            height: 45px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            z-index: 6500;
            font-size: 1.1rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .legend-btn:hover { transform: scale(1.1); background: var(--accent); }
        .stat-card:hover, .packet-item:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.08);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .mode-btn:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(-5px);
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        .kec-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        /* GPS Blur Overlay */
        .gps-blur-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            backdrop-filter: blur(25px);
            background: rgba(2,6,23,0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, backdrop-filter 0.5s ease;
        }
        .gps-blur-overlay.minimized { 
            background: rgba(2,6,23,0.3);
            pointer-events: none; /* Tetap blur tapi biarkan user lihat dashboard di baliknya */
        }
        .gps-blur-overlay.minimized .gps-prompt { display: none; }
        .gps-blur-overlay.minimized .gps-blur-msg { display: block; }
        .gps-blur-overlay.hidden { opacity: 0; pointer-events: none; backdrop-filter: blur(0); }
        
        .gps-blur-msg {
            display: none;
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            max-width: 300px;
            opacity: 0.8;
            text-shadow: 0 0 20px rgba(59,130,246,0.5);
            animation: fadeIn 1s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 0.8; } }
        .gps-prompt {
            text-align: center;
            max-width: 400px;
            padding: 2.5rem;
            background: rgba(15,23,42,0.9);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            animation: cardIn 0.5s ease;
        }
        @keyframes cardIn { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
        .gps-prompt h2 { font-size: 1.3rem; margin-bottom: 0.5rem; }
        .gps-prompt p { font-size: 0.85rem; opacity: 0.6; margin-bottom: 1.5rem; line-height: 1.5; }
        .gps-btn {
            display: inline-block; padding: 12px 28px; background: var(--accent); color: white;
            border: none; border-radius: 14px; font-family: 'Outfit'; font-size: 0.95rem;
            font-weight: 600; cursor: pointer; transition: 0.3s;
        }
        .gps-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(59,130,246,0.4); }
        .gps-float-btn {
            position: fixed; bottom: 30px; left: 30px; z-index: 100001; /* Di atas kaca buram */
            padding: 10px 20px; background: rgba(59,130,246,0.9); color: white;
            border-radius: 14px; font-family: 'Outfit'; font-size: 0.8rem;
            font-weight: 600; cursor: pointer; border: none;
            box-shadow: 0 8px 25px rgba(59,130,246,0.3);
            display: none; transition: 0.3s; backdrop-filter: blur(8px);
        }
        .gps-float-btn:hover { transform: translateY(-2px); }

        /* User Profile */
        .user-bar {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; background: rgba(255,255,255,0.04);
            border-radius: 12px; margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .user-bar img { width: 32px; height: 32px; border-radius: 50%; }
        .user-bar .uname { font-size: 0.8rem; font-weight: 600; flex: 1; }
        .user-bar .uemail { font-size: 0.65rem; opacity: 0.4; }
        .user-bar a { font-size: 0.7rem; color: #ef4444; text-decoration: none; opacity: 0.6; }
        .user-bar a:hover { opacity: 1; }

        /* Sawer Saya Button */
        .sawer-btn {
            position: fixed;
            bottom: 360px; /* Posisi paling atas */
            right: 30px;
            z-index: 6000; /* Balik ke bawah kaca buram */
            background: #f59e0b;
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-family: 'Outfit';
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            box-shadow: 0 10px 25px rgba(217, 119, 6, 0.3);
            transition: all 0.3s;
            animation: sawerBlink 1.5s infinite;
        }
        @keyframes sawerBlink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.95); }
        }
        .sawer-btn:hover { animation: none; opacity: 1; transform: scale(1.05); }
        .sawer-btn:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 15px 30px rgba(217, 119, 6, 0.4); }

        /* Share Button */
        .share-btn {
            position: fixed;
            bottom: 410px; /* Di atas sawer-btn */
            right: 30px;
            z-index: 6000;
            background: var(--accent);
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            font-family: 'Outfit';
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            transition: all 0.3s;
            animation: shareBlink 1.5s infinite;
        }
        @keyframes shareBlink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.98); }
        }
        .share-btn:hover { animation: none; transform: scale(1.05); }
        
        /* Share Modal (reusing sawer modal styles) */
        .share-modal {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(12px);
            background: rgba(2,6,23,0.8);
            padding: 20px;
        }
        #share-options { display: flex; gap: 10px; flex-direction: column; margin-top: 1rem; }
        .share-option-btn {
            background: var(--accent);
            color: white;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .share-option-btn:hover { opacity: 0.9; transform: translateY(-2px); }
        
        /* Sawer Modal */
        .sawer-modal {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(12px);
            background: rgba(2,6,23,0.8);
            padding: 20px;
        }
        .sawer-content {
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 28px;
            padding: 2.5rem;
            max-width: 450px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 70px rgba(0,0,0,0.6);
            animation: modalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.9) translateY(20px); } }
        .sawer-content img { width: 100%; max-width: 300px; border-radius: 16px; margin-bottom: 1.5rem; border: 4px solid white; }
        .sawer-content h2 { font-size: 1.3rem; margin-bottom: 1rem; color: #f59e0b; }
        .sawer-content p { font-size: 0.95rem; opacity: 0.8; line-height: 1.6; margin-bottom: 1.8rem; }
        .close-sawer {
            background: rgba(255,255,255,0.05);
            border: none;
            color: white;
            padding: 10px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .close-sawer:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>


<!-- Hamburger Menu (Mobile Only) -->
<div class="hamburger" onclick="toggleSidebar()">☰</div>

<!-- GPS Blur Overlay -->
<div class="gps-blur-overlay" id="gpsOverlay">
    <div class="gps-prompt">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📍</div>
        <h2>Izinkan Akses Lokasi</h2>
        <p>Dashboard ini memerlukan lokasi GPS Anda untuk keamanan dan transparansi. Data lokasi akan dicatat bersama email Anda.</p>
        <button class="gps-btn" onclick="requestGPS()">Izinkan Lokasi GPS</button>
        <div style="margin-top: 1rem;">
            <a href="#" onclick="skipGPS()" style="color: #94a3b8; font-size: 0.75rem; text-decoration: none;">Lewati tanpa GPS →</a>
        </div>
    </div>
    <div class="gps-blur-msg">
        <div style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;">🔒</div>
        Mohon ijinkan akses lokasi untuk membuka dashboard
    </div>
</div>

<!-- Floating GPS Button (shown when GPS was skipped) -->
<button class="gps-float-btn" id="gpsFloatBtn" onclick="requestGPS()">📍 Berikan Akses Lokasi</button>

<!-- Share Button -->
<button class="share-btn" onclick="toggleShare(true)">Bantu Share</button>

<!-- Sawer Saya Button -->
<button class="sawer-btn" onclick="toggleSawer(true)">Sawer Saya</button>

<!-- Share Modal -->
<div class="share-modal" id="shareModal">
    <div class="sawer-content" style="border-top: 5px solid var(--accent);">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📢</div>
        <h2>Ayo Bantu Share!</h2>
        <p>Merasa project ini bermanfaat? Yuk bantu share website ini ke teman-teman agar Majalengka lebih terbuka!</p>
        
        <div id="share-options">
            <button onclick="shareWeb()" class="share-option-btn">🔗 Salin Link / Share Website</button>
            <a href="https://wa.me/?text=Cek website Matadata Majalengka: AI Audit Pengadaan dan Dana Desa! Transparansi untuk Majalengka: <?= urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-option-btn" style="background: #10b981;">📱 Share ke WhatsApp</a>
        </div>
        
        <button class="close-sawer" style="margin-top: 1.5rem;" onclick="toggleShare(false)">Nanti Saja</button>
    </div>
</div>

<!-- Sawer Modal -->
<div class="sawer-modal" id="sawerModal">
    <div class="sawer-content">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">☕</div>
        <img src="qrsumbangan.jpeg" alt="QR Sumbangan">
        <h2>Dukungan Kopi</h2>
        <p>Bantu saya untuk mengembangkan project ini, untuk Majalengka yang lebih transparan</p>
        <button class="close-sawer" onclick="toggleSawer(false)">Tutup</button>
    </div>
</div>


<div class="sidebar" id="sidebar">
    <!-- User Profile Bar -->
    <div class="user-bar">
        <?php if ($user['photo']): ?><img src="<?= htmlspecialchars($user['photo']) ?>" alt=""><?php else: ?><div style="width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;">👤</div><?php endif; ?>
        <div style="flex:1; min-width:0;">
            <div class="uname"><?= htmlspecialchars($user['name']) ?></div>
            <div class="uemail"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <a href="?logout=1" title="Logout">✕</a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <h1 id="sidebar-title">MATADATA MAJALENGKA</h1>
        <div class="close-sidebar" onclick="toggleSidebar()" style="cursor:pointer; font-size: 1.5rem; opacity: 0.5; display: none;">&times;</div>
    </div>
    <p class="subtitle" id="sidebar-subtitle">Operasi Ratu Boko • AI Audit Pengadaan</p>

    <!-- Sidebar Section: SIRUP -->
    <div id="sidebar-sirup">
        <div class="stat-card">
            <h3>Total Paket Diaudit</h3>
            <div class="value"><?php 
                echo $db->querySingle("SELECT COUNT(*) FROM packages WHERE processed=1"); 
            ?></div>
        </div>

        <div class="stat-card" style="border-left-color: var(--danger); margin-top: 1.5rem;">
            <h3>Temuan Anomali (High)</h3>
            <div class="value"><?php 
                echo $db->querySingle("SELECT COUNT(*) FROM packages WHERE processed=1 AND (risk_score='High' OR risk_score='ABSURD')");
            ?></div>
        </div>

        <div class="sidebar-meta">
            <div>📅 <b>Tahun Anggaran:</b> 2025</div>
            <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
            <div>📡 <b>Sumber:</b> SiRUP LKPP RI (sirup.lkpp.go.id)</div>
            <?php if (isAdmin()): ?>
            <div style="margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 5px;">
                <a href="api_monitor.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">📊 API Health Monitor →</a>
            </div>
            <?php endif; ?>
        </div>
        <div class="sidebar-why">
            💡 <b>Kenapa data ini penting?</b><br>
            Rencana pengadaan barang/jasa adalah tahap paling awal di mana potensi pemborosan bisa dideteksi. Dengan memahami data ini, masyarakat dapat mengawasi APBD sejak tahap perencanaan — sebelum uang dibelanjakan.
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Temuan Terbesar</h3>
            <div class="packet-list">
                <?php 
                // We reused high_risk_packets from before
                $high_risk_packets->reset(); 
                while($p = $high_risk_packets->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="packet-item" onclick="selectPackage('<?= e($p['id']) ?>', '<?= e($p['kecamatan']) ?>')">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span class="tag"><?= e($p['risk_score']) ?></span>
                            <b style="color: var(--accent)"><?= formatPagu($p['pagu']) ?></b>
                        </div>
                        <div><?= e($p['nama_paket']) ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.5; margin-top: 5px;"><?= e($p['satker']) ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Section: DANA DESA -->
    <div id="sidebar-danadesa" style="display: none;">
        <div class="stat-card" style="border-left-color: var(--success);">
            <h3>Total Alokasi 2025</h3>
            <div class="value"><?= formatPagu($total_majalengka_dd) ?></div>
        </div>

        <div class="stat-card" style="border-left-color: var(--success); margin-top: 1.5rem;">
            <h3>Jumlah Desa Penerima</h3>
            <div class="value">343</div>
        </div>

        <div class="sidebar-meta" style="border-left-color: var(--success);">
            <div>📅 <b>Tahun Anggaran:</b> 2025</div>
            <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
            <div>📡 <b>Sumber:</b> Portal TKD Kemenkeu RI</div>
        </div>
        <div class="sidebar-why" style="background: rgba(16,185,129,0.08);">
            💡 <b>Kenapa data ini penting?</b><br>
            Dana Desa adalah hak setiap desa. Dengan melihat transparansi alokasi ini, warga dapat memastikan desa mereka mendapat bagian yang adil dan proporsional sesuai kebutuhan.
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Ranking Alokasi Kecamatan</h3>
            <div style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                <?php foreach($kec_dd_stats as $k): ?>
                    <div class="kec-list-item">
                        <span style="font-weight: 500;"><?= $k['nm_kecamatan'] ?></span>
                        <span style="color: var(--success); font-weight: 600;"><?= formatPagu($k['total_budget']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Section: KEMISKINAN -->
    <div id="sidebar-kemiskinan" style="display: none;">
        <div class="stat-card" style="border-left-color: var(--warning);">
            <h3>Total KPM Bansos (Eks. Terpadu)</h3>
            <div class="value"><?= number_format($total_kpm_majalengka, 0, ',', '.') ?></div>
        </div>

        <div class="stat-card" style="border-left-color: var(--warning); margin-top: 1.5rem;">
            <h3>Indikator Utama</h3>
            <div style="font-size: 0.85rem; opacity: 0.8; margin-top: 5px;">Keluarga Penerima Manfaat (BPNT/PKH)</div>
        </div>

        <div class="sidebar-meta" style="border-left-color: var(--warning);">
            <div>📅 <b>Periode Data:</b> 2024/2025</div>
            <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
            <div>📡 <b>Sumber:</b> DTKS Kemensos RI via Dinsos Majalengka</div>
        </div>
        <div class="sidebar-why" style="background: rgba(245,158,11,0.08);">
            💡 <b>Kenapa data ini penting?</b><br>
            Mengetahui sebaran kemiskinan membantu warga mengawasi apakah bantuan sosial (PKH, BPNT) sudah tepat sasaran. Area dengan KPM tinggi seharusnya diprioritaskan pemerintah.
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Kecamatan Terpadat (KPM)</h3>
            <div style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                <?php foreach($poverty_stats as $name => $p): ?>
                    <div class="kec-list-item">
                        <span style="font-weight: 500;"><?= $name ?></span>
                        <span style="color: var(--warning); font-weight: 600;"><?= number_format($p['count'], 0, ',', '.') ?> KPM</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Section: INFRASTRUKTUR -->
    <div id="sidebar-infrastruktur" style="display: none;">
        <div class="stat-card" style="border-left-color: #06b6d4;">
            <h3>Indeks Kemantapan Jalan Desa</h3>
            <div class="value">74.5%</div>
            <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 5px;">Kondisi Baik & Sedang (Baseline 2024)</div>
        </div>

        <div class="sidebar-meta" style="border-left-color: #06b6d4;">
            <div>📅 <b>Baseline Data:</b> 2024</div>
            <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
            <div>📡 <b>Sumber:</b></div>
            <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                <a href="https://majalengkakab.go.id" target="_blank" style="color: #22d3ee; text-decoration: none; font-size: 0.7rem;">🔗 Open Data Kab. Majalengka ↗</a>
                <a href="https://overpass-turbo.eu/" target="_blank" style="color: #22d3ee; text-decoration: none; font-size: 0.7rem;">🔗 OpenStreetMap via Overpass ↗</a>
                <a href="https://www.lapor.go.id/" target="_blank" style="color: #22d3ee; text-decoration: none; font-size: 0.7rem;">🔗 SP4N-LAPOR! ↗</a>
            </div>
        </div>
        <div class="sidebar-why" style="background: rgba(6,182,212,0.08);">
            💡 <b>Kenapa data ini penting?</b><br>
            Jalan adalah urat nadi ekonomi desa. Peta ini menunjukkan kecamatan mana yang jalannya paling rusak agar warga bisa menuntut prioritas perbaikan dari pemerintah.
        </div>
        <div style="margin-top: 8px; font-size: 0.65rem; opacity: 0.5; padding: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; line-height: 1.5;">
            ⚠️ <b>Catatan:</b> Data per kecamatan menggunakan baseline 2024. Data resmi DPUTR Majalengka menyebutkan kemantapan jalan kabupaten naik ke ~88,9% di tahun 2025 (<a href="https://rri.co.id/majalengka" target="_blank" style="color: #22d3ee;">sumber</a>). Geometri jalan dari OpenStreetMap (kontribusi komunitas, bukan data resmi pemerintah).
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Kecamatan (Jalan Desa Rusak)</h3>
            <div style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                <?php 
                $road_ranking = $poverty_stats;
                uasort($road_ranking, function($a, $b) { return $a['road_pct'] <=> $b['road_pct']; });
                foreach($road_ranking as $name => $p): ?>
                    <div class="kec-list-item">
                        <span style="font-weight: 500;"><?= $name ?></span>
                        <span style="color: #ef4444; font-weight: 600;"><?= 100 - $p['road_pct'] ?>% Rusak</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Legal Links -->
    <div style="padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Kebijakan Privasi</a>
        <span style="opacity: 0.2; margin: 0 5px;">•</span>
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Syarat & Ketentuan</a>
    </div>
</div>

<div id="map"></div>

<!-- Modal Logic (Dynamic) -->
<div id="logicModal" class="modal" onclick="if(event.target == this) toggleModal()">
    <div class="modal-content">
        <span class="close-modal" onclick="toggleModal()">&times;</span>
        <h2 id="modal-title" style="color: var(--accent); margin-top: 0;">Transparansi Algoritma</h2>
        <p id="modal-subtitle" style="opacity: 0.6; font-size: 0.9rem; margin-bottom: 1.5rem;">Memuat detail algoritma...</p>
        
        <div id="modal-logic-body" class="logic-grid">
            <!-- Filled by JS -->
        </div>

        <h3 style="margin-top: 1.5rem; font-size: 1rem;">Metadata & Sumber Data</h3>
        <ul id="modal-sources" style="opacity: 0.8; font-size: 0.85rem; line-height: 1.6;">
            <!-- Filled by JS -->
        </ul>
    </div>
</div>

<!-- Packet Detail Modal -->
<div id="packetModal" class="modal" onclick="if(event.target == this) togglePacketModal()">
    <div class="modal-content" style="max-width: 500px; border-top: 5px solid var(--accent);">
        <span class="close-modal" onclick="togglePacketModal()">&times;</span>
        <h2 id="p-title" style="margin: 0; font-size: 1.2rem;">Nama Paket</h2>
        <p id="p-satker" style="opacity: 0.6; font-size: 0.8rem; margin-bottom: 1rem;">Satuan Kerja</p>
        
        <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <span id="p-risk" class="tag" style="font-size: 0.8rem; padding: 4px 10px;">LOW</span>
                <b id="p-pagu" style="font-size: 1.4rem; color: var(--accent);">Rp 0</b>
            </div>
            <div style="font-size: 0.9rem; line-height: 1.6; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <b style="display: block; font-size: 0.75rem; opacity: 0.5; margin-bottom: 4px;">CATATAN AUDIT AI:</b>
                <span id="p-note">...</span>
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-direction: column;">
            <div style="font-size: 0.8rem; opacity: 0.7;">
                <b>ID SIRUP:</b> <span id="p-sirup-id">000000</span>
            </div>
            <a id="p-sirup-link" href="#" target="_blank" style="display: block; background: var(--accent); color: white; text-align: center; padding: 12px; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s;">
                🔍 Lihat Detil di SIRUP LKPP ↗
            </a>
        </div>
    </div>
</div>

    <!-- Map Legend -->
    <div id="map-legend" class="map-legend"></div>

    <!-- Mode Switcher -->
    <div class="mode-switcher">
        <div class="mode-btn active" id="btn-sirup" onclick="switchMode('sirup')">
            <span>Audit Pengadaan</span>
        </div>
        <div class="mode-btn" id="btn-danadesa" onclick="switchMode('danadesa')">
            <span>Dana Desa</span>
        </div>
        <div class="mode-btn" id="btn-kemiskinan" onclick="switchMode('kemiskinan')">
            <span>Kemiskinan</span>
        </div>
        <div class="mode-btn" id="btn-infrastruktur" onclick="switchMode('infrastruktur')">
            <span>Infrastruktur</span>
        </div>
    </div>

    <!-- Futuristic Search Bar -->
    <div class="search-container">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Cari Kecamatan atau Paket..." oninput="handleSearch()">
        </div>
        <div id="searchResults" class="search-results"></div>
    </div>
    <div class="legend-btn" onclick="toggleLegend()" title="Legenda Peta">🗺️</div>
    <div class="info-btn" onclick="toggleModal()" title="Transparansi Algoritma">?</div>
</div>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }

    const ALGO_EXPLANATIONS = {
        'sirup': {
            title: 'Algoritma Audit SIRUP',
            subtitle: 'Bagaimana AI mendeteksi risiko pengadaan barang/jasa?',
            logic: [
                { b: 'WHAT: Audit Resiko Pengadaan', p: 'Mendeteksi potensi penyimpangan pada rencana pengadaan barang dan jasa pemerintah.' },
                { b: 'WHO: Auditor AI Matadata', p: 'Mesin LLM (Gemini/Groq) yang dilatih dengan pola korupsi pengadaan di Indonesia.' },
                { b: 'WHY: Akuntabilitas Anggaran', p: 'Mencegah paket "titipan", pemecahan paket (splitting), dan pemborosan anggaran sejak tahap perencanaan.' },
                { b: 'WHERE: Seluruh OPD Majalengka', p: 'Mencakup seluruh Dinas, Badan, dan Kantor di lingkungan Pemerintah Kabupaten Majalengka.' },
                { b: 'WHEN: T.A 2025', p: 'Fokus pada rencana belanja tahun anggaran berjalan.' },
                { b: 'HOW: Analisis Rasio & Keyword', p: 'AI memproses deskripsi paket vs nilai pagu serta mencari kata kunci "abu-abu" yang tidak produktif.' }
            ],
            sources: [
                'SiRUP LKPP T.A 2025 (Portal resmi pengadaan nasional)',
                'Standard Harga Satuan Regional (Referensi pembanding)',
                'History pengadaan tahun sebelumnya (Pattern recognition)'
            ]
        },
        'danadesa': {
            title: 'Algoritma Analisis Dana Desa',
            subtitle: 'Evaluasi pemerataan alokasi dana per desa terhadap status perkembangan.',
            logic: [
                { b: 'WHAT: Analisis Pemerataan Dana', p: 'Memastikan alokasi Dana Desa (DD) dan Alokasi Dana Desa (ADD) tersalurkan secara proporsional.' },
                { b: 'WHO: Integrasi Data Kemenkeu', p: 'Data bersumber dari Transfer Ke Daerah (TKD) Nasional dan DPMD Majalengka.' },
                { b: 'WHY: Keadilan Fiskal Desa', p: 'Mencegah ketimpangan di mana desa maju mendapatkan dana jauh lebih besar dari desa tertinggal tanpa alasan jelas.' },
                { b: 'WHERE: 330+ Desa di Majalengka', p: 'Pemetaan menyeluruh di tingkat desa/kelurahan.' },
                { b: 'WHEN: T.A 2025', p: 'Dataset alokasi terbaru berdasarkan pagu Kemenkeu.' },
                { b: 'HOW: AI Correlation IDM', p: 'Menghitung skor risiko berdasarkan korelasi antara Indeks Desa Membangun (IDM) dengan total pagu yang diterima.' }
            ],
            sources: [
                'Portal TKD Kemenkeu RI (Data Alokasi 2025)',
                'DPMD Majalengka (Database Desa)',
                'Indeks Desa Membangun / IDM (Benchmark status desa)'
            ]
        },
        'kemiskinan': {
            title: 'Metodologi Audit Kemiskinan',
            subtitle: 'Visualisasi dan transparansi profil penerima manfaat bantuan sosial.',
            logic: [
                { b: 'WHAT: Mapping Profil KPM', p: 'Visualisasi sebaran Keluarga Penerima Manfaat (KPM) untuk bantuan PKH dan BPNT.' },
                { b: 'WHO: Data Terpadu Kesejahteraan Sosial', p: 'Bersumber dari DTKS Kemensos RI yang dikelola oleh Dinsos Majalengka.' },
                { b: 'WHY: Transparansi Bantuan', p: 'Memastikan publik tahu area mana yang memiliki beban sosial tertinggi untuk monitoring ketepatan sasaran.' },
                { b: 'WHERE: Seluruh Kecamatan Majalengka', p: 'Data agregat yang dipetakan per wilayah kecamatan.' },
                { b: 'WHEN: Periode 2024/2025', p: 'Data penerima aktif dalam sistem jaminan sosial.' },
                { b: 'HOW: Density Analysis', p: 'Menghitung rasio KPM terhadap total populasi kecamatan untuk menentukan zona kebutuhan ekonomi.' }
            ],
            sources: [
                'Data Terpadu Kesejahteraan Sosial (DTKS) Kemensos RI',
                'Dinas Sosial Kabupaten Majalengka',
                'Survey Sosial Ekonomi Nasional (Data Pendukung)'
            ]
        },
        'infrastruktur': {
            title: 'Algoritma Audit Infrastruktur',
            subtitle: 'Analisis gap antara kondisi rill jaringan jalan dengan alokasi anggaran perbaikan.',
            logic: [
                { b: 'WHAT: Infra-Budget Gap Analysis', p: 'Mendeteksi area yang jalannya rusak namun tidak mendapatkan prioritas anggaran.' },
                { b: 'WHO: Geometri Rill OpenStreetMap', p: 'Menggunakan data koordinat jalan asli (OSM) vs Laporan Statistik DPUTR.' },
                { b: 'WHY: Prioritas Pembangunan', p: 'Mencegah penumpukan proyek di jalan yang sudah baik sambil membiarkan jalan rusak di area terpencil.' },
                { b: 'WHERE: Jaringan Jalan Desa Majalengka', p: 'Mencakup 14.000+ ruas jalan lokal dan residensial.' },
                { b: 'WHEN: Kondisi 2024 vs Budget 2025', p: 'Membandingkan baseline kerusakan terakhir dengan rencana belanja tahun ini.' },
                { b: 'HOW: Spatial Overlap Audit', p: 'AI menandai "Neglected Area" pada kecamatan yang memiliki indeks kemantapan jalan < 70% namun minim pagu konstruksi.' }
            ],
            sources: [
                'OpenStreetMap (OSM) - Geometri Jalan Terkini',
                'DPUTR Majalengka (Statistik Kemantapan Jalan)',
                'SP4N-LAPOR! (Database Keluhan Infrastruktur Masyarakat)'
            ]
        }
    };

    function updateModalContent(mode) {
        const data = ALGO_EXPLANATIONS[mode];
        if (!data) return;

        document.getElementById('modal-title').innerText = data.title;
        document.getElementById('modal-subtitle').innerText = data.subtitle;

        let logicHtml = '';
        data.logic.forEach(item => {
            logicHtml += `<div class="logic-item"><b>${item.b}</b><p>${item.p}</p></div>`;
        });
        document.getElementById('modal-logic-body').innerHTML = logicHtml;

        let sourcesHtml = '';
        data.sources.forEach(src => {
            sourcesHtml += `<li>${src}</li>`;
        });
        document.getElementById('modal-sources').innerHTML = sourcesHtml;
    }

    function toggleModal() {
        const modal = document.getElementById('logicModal');
        modal.classList.toggle('show');
    }


    const stats = <?php echo json_encode($stats); ?>;
    const allAudits = <?php echo json_encode($all_audits); ?>;
    const villageStats = <?php echo json_encode($village_stats); ?>;
    const povertyStats = <?php echo json_encode($poverty_stats); ?>;
    
    let currentMode = 'sirup'; // 'sirup', 'danadesa', 'kemiskinan', or 'infrastruktur'
    let geoLayer = null;
    let roadLayer = null;
    let districtLayers = {}; 
    let villageLayers = {};
    let activeLayer = null;

    // Mode Switcher Logic
    function updateLegend(mode) {
        const legend = document.getElementById('map-legend');
        if (!legend) return;
        let html = '';
        if (mode === 'sirup') {
            html = `
                <div class="legend-title">Risiko Pengadaan</div>
                <div class="legend-item"><div class="legend-color" style="background:#800026"></div><span>Kritis (>5 Temuan)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#BD0026"></div><span>Tinggi (3-5 Temuan)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#E31A1C"></div><span>Sedang (1-2 Temuan)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#3b82f6"></div><span>Aman (0 Temuan)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#1e293b"></div><span>Belum Diaudit</span></div>
            `;
        } else if (mode === 'danadesa') {
            html = `
                <div class="legend-title">Alokasi Dana Desa</div>
                <div class="legend-item"><div class="legend-color" style="background:#064e3b"></div><span>> Rp1,2 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#065f46"></div><span>> Rp1 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#059669"></div><span>> Rp800 Juta</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#10b981"></div><span>≤ Rp800 Juta</span></div>
            `;
        } else if (mode === 'kemiskinan') {
            html = `
                <div class="legend-title">Sebaran KPM Bansos</div>
                <div class="legend-item"><div class="legend-color" style="background:#7c2d12"></div><span>> 8.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#9a3412"></div><span>> 6.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#c2410c"></div><span>> 4.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#ea580c"></div><span>> 2.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#f97316"></div><span>≤ 2.000 KPM</span></div>
            `;
        } else if (mode === 'infrastruktur') {
            html = `
                <div class="legend-title">Kemantapan Jalan</div>
                <div class="legend-item"><div class="legend-color" style="background:#d946ef"></div><span>Mantap (≥90%)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#c026d3"></div><span>Baik (80-89%)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#a21caf"></div><span>Sedang (70-79%)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#701a75"></div><span>Rusak (60-69%)</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#4a044e"></div><span>Kritis (<60%)</span></div>
            `;
        }
        legend.innerHTML = html + '<div class="close-legend" onclick="toggleLegend()">&times;</div>';
    }

    // Mode Switcher Logic
    function switchMode(mode) {
        if (mode === currentMode) return;
        currentMode = mode;

        // Update Buttons
        document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active', 'green'));
        const activeBtn = document.getElementById(`btn-${mode}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
            if (mode === 'danadesa') activeBtn.classList.add('green');
        }

        // Update Legend
        updateLegend(mode);

        // Update Theme
        let accentColor = '#3b82f6';
        if (mode === 'danadesa') accentColor = '#10b981';
        if (mode === 'kemiskinan') accentColor = '#f59e0b';
        if (mode === 'infrastruktur') accentColor = '#06b6d4';
        document.documentElement.style.setProperty('--accent', accentColor);

        // Update Sidebar
        const sections = ['sirup', 'danadesa', 'kemiskinan', 'infrastruktur'];
        sections.forEach(s => {
            const el = document.getElementById(`sidebar-${s}`);
            if (el) el.style.display = (s === mode) ? 'block' : 'none';
        });
        
        const titleEl = document.getElementById('sidebar-title');
        const subtitleEl = document.getElementById('sidebar-subtitle');
        if (titleEl) {
            titleEl.innerText = mode === 'sirup' ? 'MATADATA MAJALENGKA' : 
                               (mode === 'danadesa' ? 'TRANSPARANSI DESA' : 
                               (mode === 'kemiskinan' ? 'AUDIT KEMISKINAN' : 'AUDIT INFRASTRUKTUR'));
        }
        if (subtitleEl) {
            subtitleEl.innerText = mode === 'sirup' ? 'Operasi Ratu Boko • AI Audit Pengadaan' : 
                                  (mode === 'danadesa' ? 'Alokasi Alur Dana Desa 2025' : 
                                  (mode === 'kemiskinan' ? 'Profil KPM Bansos Per Kecamatan' : 'Kondisi & Anggaran Jalan Desa'));
        }

        // Update Placeholder
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.placeholder = mode === 'sirup' ? 'Cari Kecamatan atau Paket...' : (mode === 'danadesa' ? 'Cari Desa...' : 'Cari Kecamatan...');
        }

        if (window.updateModalContent) updateModalContent(mode);

        // Clear Map and Load New Data
        if (geoLayer) map.removeLayer(geoLayer);
        if (roadLayer) map.removeLayer(roadLayer);
        activeLayer = null;
        loadMapData();
    }

    function handleSearch() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const resultsBox = document.getElementById('searchResults');
        
        if (query.length < 2) {
            resultsBox.classList.remove('show');
            return;
        }

        let html = '';
        
        if (currentMode === 'sirup') {
            const matchDistricts = Object.keys(stats).filter(name => name.toLowerCase().includes(query));
            matchDistricts.forEach(d => {
                html += `<div class="result-item" onclick="selectDistrict('${d}')">
                            <div class="type">KECAMATAN</div>
                            <div class="name">${d}</div>
                         </div>`;
            });
            const matchPackages = allAudits.filter(p => p.nama.toLowerCase().includes(query)).slice(0, 5);
            matchPackages.forEach(p => {
                html += `<div class="result-item" onclick="selectPackage('${p.id}', '${p.kecamatan}')">
                            <div class="type">PAKET</div>
                            <div class="name">${p.nama}</div>
                         </div>`;
            });
        } else {
            const matchItems = currentMode === 'danadesa' ? Object.keys(villageStats) : Object.keys(povertyStats);
            const matches = matchItems.filter(name => name.toLowerCase().includes(query));
            matches.forEach(m => {
                const type = currentMode === 'danadesa' ? 'DESA / KELURAHAN' : 'KECAMATAN';
                const sub = currentMode === 'danadesa' ? `Kec. ${villageStats[m].kecamatan}` : `Kab. Majalengka`;
                html += `<div class="result-item" onclick="select${currentMode === 'danadesa' ? 'Village' : 'District'}('${m}')">
                            <div class="type">${type}</div>
                            <div class="name">${m} - <span style='opacity:0.6'>${sub}</span></div>
                         </div>`;
            });
        }

        if (html) {
            resultsBox.innerHTML = html;
            resultsBox.classList.add('show');
        } else {
            resultsBox.classList.remove('show');
        }
    }

    function selectDistrict(name) {
        document.getElementById('searchResults').classList.remove('show');
        const layer = districtLayers[name];
        if (layer) {
            if (activeLayer) resetHighlight(activeLayer);
            activeLayer = layer;
            map.fitBounds(layer.getBounds(), { padding: [50, 50] });
            highlightLayer(layer);
            layer.openPopup();
        }
    }

    function selectVillage(name) {
        document.getElementById('searchResults').classList.remove('show');
        const layer = villageLayers[name];
        if (layer) {
            if (activeLayer) resetHighlight(activeLayer);
            activeLayer = layer;
            map.fitBounds(layer.getBounds(), { padding: [50, 50] });
            highlightLayer(layer);
            layer.openPopup();
        }
    }

    function highlightLayer(layer) {
        layer.setStyle({ weight: 4, color: '#ffffff', opacity: 1 });
    }

    function resetHighlight(layer) {
        layer.setStyle({ weight: 1, color: 'rgba(255,255,255,0.1)' });
    }

    function togglePacketModal() {
        const modal = document.getElementById('packetModal');
        modal.classList.toggle('show');
    }

    function selectPackage(id, kecamatanName) {
        const p = allAudits.find(x => x.id === id);
        if (p) {
            document.getElementById('p-title').innerText = p.nama;
            document.getElementById('p-satker').innerText = p.satker;
            document.getElementById('p-pagu').innerText = formatPaguJS(p.pagu);
            document.getElementById('p-risk').innerText = p.risk;
            document.getElementById('p-risk').style.background = p.risk === 'High' || p.risk === 'ABSURD' ? 'var(--danger)' : (p.risk === 'Medium' ? 'var(--warning)' : 'var(--success)');
            document.getElementById('p-note').innerText = p.note;
            document.getElementById('p-sirup-id').innerText = p.id;
            document.getElementById('p-sirup-link').href = `https://sirup.lkpp.go.id/sirup/rekap/detailPaketAnggaran?idPaket=${p.id}`;
            
            togglePacketModal();
        }
        selectDistrict(kecamatanName);
    }

    function updateProgress() {
        fetch('progress.json?t=' + Date.now())
            .then(r => r.json())
            .then(data => {
                const overlay = document.getElementById('progress-overlay');
                if (data.status === 'RUNNING' || data.status === 'WAITING') {
                    overlay.style.display = 'block';
                    const percent = Math.round((data.processed / data.total) * 100);
                    document.getElementById('progress-percent').innerText = percent + '%';
                    document.getElementById('progress-fill').style.width = percent + '%';
                    let text = `Memproses: ${data.kecamatan}`;
                    if (data.status === 'WAITING') text = "⏳ " + data.kecamatan;
                    document.getElementById('progress-kecamatan').innerText = text;
                } else {
                    overlay.style.display = 'none';
                }
            })
            .catch(() => {});
    }
    setInterval(updateProgress, 5000);
    updateProgress();

    const map = L.map('map').setView([-6.837, 108.227], 11);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
    }).addTo(map);

    // Create a specific pane for roads to keep them on top of polygons
    map.createPane('roadPane');
    map.getPane('roadPane').style.zIndex = 650;
    map.getPane('roadPane').style.pointerEvents = 'auto';

    function escapeHTML(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatPaguJS(p) {
        if (!p || p === 0) return "Rp0";
        if (p >= 1000000000) return "Rp" + (p/1000000000).toFixed(1) + " M";
        if (p >= 1000000) return "Rp" + Math.round(p/1000000) + " Jt";
        return "Rp" + p.toLocaleString('id-ID');
    }

    function getSIRUPColor(name) {
        const d = stats[name] ? stats[name].high_risk : 0;
        return d > 5  ? '#800026' : d > 2  ? '#BD0026' : d > 0  ? '#E31A1C' : stats[name] ? '#3b82f6' : '#1e293b';
    }

    function getDDColor(name) {
        const d = villageStats[name] ? villageStats[name].budget : 0;
        return d > 1200000000 ? '#064e3b' : d > 1000000000 ? '#065f46' : d > 800000000  ? '#059669' : d > 0 ? '#10b981' : '#1e293b';
    }

    function getPovertyColor(name) {
        const p = povertyStats[name] ? povertyStats[name].count : 0;
        return p > 8000 ? '#7c2d12' : p > 6000 ? '#9a3412' : p > 4000 ? '#c2410c' : p > 2000 ? '#ea580c' : p > 0 ? '#f97316' : '#1e293b';
    }

    function getRoadHeatColor(name) {
        const p = povertyStats[name] ? povertyStats[name].road_pct : 100;
        return p < 60 ? '#4a044e' : p < 70 ? '#701a75' : p < 80 ? '#a21caf' : p < 90 ? '#c026d3' : '#d946ef';
    }

    function loadMapData() {
        const file = currentMode === 'sirup' || currentMode === 'kemiskinan' || currentMode === 'infrastruktur' ? 'districts.geojson' : 'villages.geojson';
        districtLayers = {};
        villageLayers = {};

        fetch(file)
            .then(r => r.json())
            .then(data => {
                geoLayer = L.geoJson(data, {
                    style: function(f) {
                        const name = (currentMode === 'sirup' || currentMode === 'kemiskinan' || currentMode === 'infrastruktur') ? f.properties.nm_kecamatan : f.properties.nm_kelurahan;
                        let color = '#1e293b';
                        if (currentMode === 'sirup') color = getSIRUPColor(name);
                        else if (currentMode === 'danadesa') color = getDDColor(name);
                        else if (currentMode === 'kemiskinan') color = getPovertyColor(name);
                        else if (currentMode === 'infrastruktur') color = getRoadHeatColor(name);
                        
                        return {
                            fillColor: color,
                            weight: 1, opacity: 1, color: 'rgba(255,255,255,0.1)', fillOpacity: 0.6
                        };
                    },
                    onEachFeature: function(f, layer) {
                        const name = (currentMode === 'sirup' || currentMode === 'kemiskinan' || currentMode === 'infrastruktur') ? f.properties.nm_kecamatan : f.properties.nm_kelurahan;
                        
                        if (currentMode === 'sirup' || currentMode === 'kemiskinan' || currentMode === 'infrastruktur') {
                            districtLayers[name] = layer;
                            
                            if (currentMode === 'sirup') {
                                const d = stats[name] || { total: 0, high_risk: 0, total_pagu: 0 };
                                const packets = allAudits.filter(p => p.kecamatan === name);
                                let packetHtml = packets.length > 0 ? '<div style="margin-top:10px; max-height:200px; overflow-y:auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top:10px;">' : '';
                                packets.forEach(p => {
                                    const color = p.risk === 'High' || p.risk === 'ABSURD' ? '#ef4444' : (p.risk === 'Medium' ? '#f59e0b' : '#10b981');
                                    packetHtml += `<div style="margin-bottom:10px; font-size: 0.8rem;"><div style="display:flex; justify-content:space-between;"><b style="color: ${color}">${escapeHTML(p.risk)}</b><span style="opacity:0.6;">${formatPaguJS(p.pagu)}</span></div><div style="font-weight:600;">${escapeHTML(p.nama)}</div><div style="font-size:0.75rem; opacity:0.8; font-style:italic;">"${escapeHTML(p.note)}"</div></div>`;
                                });
                                if (packetHtml) packetHtml += '</div>';

                                const center = layer.getBounds().getCenter();
                                const lat = center.lat.toFixed(5);
                                const lng = center.lng.toFixed(5);

                                layer.bindPopup(`<div class="info-box" style="width:250px;"><b style="font-size:1.1rem; color:#3b82f6;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Sumber: SiRUP LKPP T.A 2025</span><hr style="opacity:0.2; margin:8px 0;"><b>Anggaran Audit:</b> <span style="color:var(--accent)">${formatPaguJS(d.total_pagu)}</span><br>Temuan High Risk: <span style="color:${d.high_risk > 0 ? '#ef4444':'#10b981'}">${d.high_risk}</span>${packetHtml}<hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(59,130,246,0.2); border-radius:8px; color:#60a5fa; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                            } else {
                                // Kemiskinan Popup
                                const p = povertyStats[name] || { count: 0, pkh: 0, bpnt: 0, road_pct: 75 };
                                if (currentMode === 'kemiskinan') {
                                    const center = layer.getBounds().getCenter();
                                    const lat = center.lat.toFixed(5);
                                    const lng = center.lng.toFixed(5);
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#f59e0b;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">T.A 2024/2025 | Sumber: Dinsos/DTKS</span><hr style="opacity:0.2; margin:8px 0;"><b>Jumlah KPM Miskin:</b><br><span style="font-size:1.8rem; font-weight:600; color:#f59e0b;">${p.count.toLocaleString('id-ID')}</span><div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px;"><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM BPNT</div><div style="font-weight:bold;">${p.bpnt.toLocaleString('id-ID')}</div></div><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM PKH</div><div style="font-weight:bold;">${p.pkh.toLocaleString('id-ID')}</div></div></div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(245,158,11,0.2); border-radius:8px; color:#fbbf24; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                                } else {
                                    // Infrastruktur Heatmap Popup
                                    const center = layer.getBounds().getCenter();
                                    const lat = center.lat.toFixed(5);
                                    const lng = center.lng.toFixed(5);
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#06b6d4;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data 2024 | Sumber: DPUTR Majalengka</span><hr style="opacity:0.2; margin:8px 0;"><b>Level Kemantapan:</b><br><span style="font-size:1.8rem; font-weight:600; color:#06b6d4;">${p.road_pct}%</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Indeks berdasarkan integrasi SP4N-LAPOR! & Statistik Jalan Kabupaten.</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                                }
                            }
                        } else {
                            villageLayers[name] = layer;
                            const v = villageStats[name] || { budget: 0, risk: 0, kecamatan: 'Unknown' };
                            const center = layer.getBounds().getCenter();
                            const lat = center.lat.toFixed(5);
                            const lng = center.lng.toFixed(5);
                            layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#10b981;">Desa ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Kecamatan ${v.kecamatan}</span><hr style="opacity:0.2; margin:8px 0;"><b>Alokasi Dana Desa T.A 2025:</b><br><span style="font-size:1.4rem; font-weight:600; color:#10b981;">${formatPaguJS(v.budget)}</span><br><div style="margin-top:10px; font-size:0.75rem; opacity:0.7; line-height:1.4;">Sumber: Alokasi TKD Kemenkeu RI T.A 2025</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(16,185,129,0.2); border-radius:8px; color:#34d399; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                        }

                        layer.on('mouseover', function() { this.setStyle({ fillOpacity: 0.9, weight: 2 }); });
                        layer.on('mouseout', function() { this.setStyle({ fillOpacity: 0.6, weight: this === activeLayer ? 4 : 1, color: this === activeLayer ? '#ffffff' : 'rgba(255,255,255,0.1)' }); });
                    }
                }).addTo(map);

                // Load Road Polylines IF in Infrastruktur Mode
                if (currentMode === 'infrastruktur') {
                    fetch('roads_desa.geojson')
                        .then(r => r.json())
                        .then(roadData => {
                            roadLayer = L.geoJson(roadData, {
                                style: function(f) {
                                    let color = '#22d3ee';
                                    if (f.properties.status === 'Rusak') color = '#ef4444';
                                    if (f.properties.status === 'Perbaikan') color = '#f59e0b';
                                    return { 
                                        color: color, 
                                        weight: 4, 
                                        opacity: 0.9, 
                                        pane: 'roadPane',
                                        smoothFactor: 1.5
                                    };
                                },
                                onEachFeature: function(f, layer) {
                                    const coords = layer.getLatLngs ? layer.getLatLngs() : [];
                                    let lat = 0, lng = 0;
                                    if (coords.length > 0) {
                                        const mid = Array.isArray(coords[0]) ? coords[0][Math.floor(coords[0].length/2)] : coords[Math.floor(coords.length/2)];
                                        if (mid) { lat = mid.lat.toFixed(5); lng = mid.lng.toFixed(5); }
                                    }
                                    const statusColor = f.properties.status === 'Rusak' ? '#ef4444' : (f.properties.status === 'Perbaikan' ? '#f59e0b' : '#22d3ee');
                                    layer.bindPopup(`<div class="info-box" style="width:230px;"><b style="color:var(--accent)">${f.properties.name || 'Jalan Tanpa Nama'}</b><br><span style="font-size:0.75rem; opacity:0.6;">Klasifikasi: ${f.properties.highway}</span><hr style="opacity:0.2; margin:5px 0;">Status: <b style="color:${statusColor}">${f.properties.status}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data: OpenStreetMap 2024</span><hr style="opacity:0.1; margin:6px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                                }
                            }).addTo(map);
                        });
                }
            });
    }

    window.onload = () => {
        if (window.updateModalContent) updateModalContent(currentMode);
        updateLegend(currentMode);
        loadMapData();

        // Check if GPS was already granted in this session
        <?php if ($user['gps_granted']): ?>
            document.getElementById('gpsOverlay').classList.add('hidden');
        <?php endif; ?>
    };

    // GPS Capture Logic
    function requestGPS() {
        if (!navigator.geolocation) {
            alert('Browser Anda tidak mendukung GPS.');
            skipGPS();
            return;
        }

        // Update button state
        const btn = document.querySelector('.gps-btn');
        if (btn) { btn.textContent = 'Meminta akses...'; btn.disabled = true; }

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                // Success - send to server
                fetch('log_visit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                        accuracy: pos.coords.accuracy
                    })
                }).then(() => {
                    document.getElementById('gpsOverlay').classList.add('hidden');
                    document.getElementById('gpsFloatBtn').style.display = 'none';
                }).catch(() => {
                    document.getElementById('gpsOverlay').classList.add('hidden');
                });
            },
            function(err) {
                // Denied or error - log visit without GPS
                fetch('log_visit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ latitude: null, longitude: null, accuracy: null })
                });
                skipGPS();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    function skipGPS() {
        // Sembunyikan box prompt saja, tapi dashboard tetap blur (minimized)
        document.getElementById('gpsOverlay').classList.add('minimized');
        document.getElementById('gpsFloatBtn').style.display = 'block';
        
        // Log visit tanpa GPS
        fetch('log_visit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude: null, longitude: null, accuracy: null })
        });
    }

    function toggleShare(show) {
        document.getElementById('shareModal').style.display = show ? 'flex' : 'none';
    }

    async function shareWeb() {
        const shareData = {
            title: 'Matadata Majalengka',
            text: 'Cek website Matadata Majalengka: AI Audit Pengadaan dan Dana Desa!',
            url: window.location.href
        };
        
        try {
            if (navigator.share) {
                await navigator.share(shareData);
            } else {
                await navigator.clipboard.writeText(window.location.href);
                alert('Link dashboard telah disalin ke clipboard!');
            }
        } catch (err) {
            console.error('Error sharing:', err);
        }
    }

    function toggleLegend() {
        const leg = document.getElementById('map-legend');
        const isHidden = window.getComputedStyle(leg).display === 'none';
        leg.style.display = isHidden ? 'block' : 'none';
    }

</script>

<?php if (isAdmin()): ?>
<a href="visitors.php" style="position:fixed; top:15px; left:50%; transform:translateX(-50%); z-index:9000; padding:6px 16px; background:rgba(139,92,246,0.2); border:1px solid rgba(139,92,246,0.3); border-radius:10px; color:#a78bfa; text-decoration:none; font-family:'Outfit'; font-size:0.75rem; font-weight:600; backdrop-filter:blur(8px);">👁️ Admin: Visitor Log</a>
<?php endif; ?>

</body>
</html>
