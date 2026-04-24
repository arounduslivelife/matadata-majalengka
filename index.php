<?php
// SECURITY HARDENING HEADERS (PENTEST COMPLIANT)
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' unpkg.com fonts.googleapis.com; style-src 'self' 'unsafe-inline' unpkg.com fonts.googleapis.com; img-src 'self' data: https: tile.openstreetmap.org *.basemaps.cartocdn.com; font-src 'self' fonts.gstatic.com; connect-src 'self' https:;");

require_once 'auth.php';
requireAuth();
$user = getCurrentUser();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login');
    exit;
}

$db = new SQLite3('database.sqlite');

function formatPagu($p) {
    if (!$p || $p === 0) return "Rp 0";
    if ($p >= 1000000000) return "Rp" . number_format($p/1000000000, 1) . " Miliar";
    if ($p >= 1000000) return "Rp" . number_format($p/1000000, 0) . " Juta";
    return "Rp" . number_format($p, 0, ',', '.');
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Unified stats per kecamatan and per year
$stats_query = $db->query("
    SELECT tahun, kecamatan, 
           COUNT(*) as total, 
           SUM(pagu) as total_pagu,
           SUM(CASE WHEN risk_score IN ('High', 'ABSURD') THEN 1 ELSE 0 END) as high_risk_count
    FROM packages 
    GROUP BY tahun, kecamatan
");

$stats = [];
while ($row = $stats_query->fetchArray(SQLITE3_ASSOC)) {
    $y = $row['tahun'];
    $k = $row['kecamatan'];
    if (!isset($stats[$y])) $stats[$y] = [];
    $stats[$y][$k] = [
        'total' => $row['total'],
        'total_pagu' => $row['total_pagu'],
        'high_risk' => $row['high_risk_count']
    ];
}

// Global year totals
$year_totals = [];
$yt_query = $db->query("SELECT tahun, SUM(pagu) as total_pagu, COUNT(*) as pkg_count FROM packages GROUP BY tahun");
while($row = $yt_query->fetchArray(SQLITE3_ASSOC)) {
    $year_totals[$row['tahun']] = $row;
}

// Get all packages for the map details
$audits_query = $db->query("SELECT * FROM packages");
$all_audits = [];
while ($p = $audits_query->fetchArray(SQLITE3_ASSOC)) {
    $all_audits[] = [
        'id' => $p['id'],
        'kecamatan' => $p['kecamatan'],
        'nama' => $p['nama_paket'],
        'pagu' => $p['pagu'],
        'risk' => $p['risk_score'],
        'note' => $p['audit_note'],
        'satker' => $p['satker'],
        'vendor' => $p['pemenang'],
        'status' => $p['status'],
        'tahun' => $p['tahun'],
        'lat' => $p['lat'],
        'lng' => $p['lng']
    ];
}

// Dana Desa & Poverty stats (unchanged)
$village_results = $db->query("SELECT * FROM villages");
$village_stats = [];
while ($row = $village_results->fetchArray(SQLITE3_ASSOC)) {
    $village_stats[$row['nm_kelurahan']] = [
        'kecamatan' => $row['nm_kecamatan'],
        'budget' => $row['budget_2025'],
        'risk' => $row['risk_score']
    ];
}

$kec_dd_results = $db->query("SELECT nm_kecamatan, SUM(budget_2025) as total_budget, COUNT(*) as village_count FROM villages GROUP BY nm_kecamatan ORDER BY total_budget DESC");
$kec_dd_stats = [];
while ($row = $kec_dd_results->fetchArray(SQLITE3_ASSOC)) { $kec_dd_stats[] = $row; }
$total_majalengka_dd = $db->querySingle("SELECT SUM(budget_2025) FROM villages");

$poverty_results = $db->query("SELECT * FROM district_stats ORDER BY poverty_count DESC");
$poverty_stats = [];
while ($row = $poverty_results->fetchArray(SQLITE3_ASSOC)) {
    $poverty_stats[$row['nm_kecamatan']] = [
        'count' => $row['poverty_count'],
        'pkh' => $row['kpm_pkh'],
        'bpnt' => $row['kpm_bpnt'],
        'road_pct' => $row['road_firmness_pct']
    ];
}
$total_kpm_majalengka = $db->querySingle("SELECT SUM(poverty_count) FROM district_stats");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matadata Majalengka - Public Monitoring Dashboard</title>
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

        /* Responsive Layout - STABLE SIDE DRAWER */
        /* UNIVERSAL macOS DOCK - PREMIUM UI */
        .control-dock {
            display: flex;
            position: fixed;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(30px);
            padding: 10px 22px;
            border-radius: 40px;
            gap: 22px;
            z-index: 10000;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 15px 50px rgba(0,0,0,0.6);
            align-items: center;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .dock-item { 
            width: 32px; 
            height: 32px; 
            cursor: pointer; 
            transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: rgba(255,255,255,0.7);
        }
        .dock-item svg { width: 100%; height: 100%; stroke-width: 1.5; }
        .dock-item:hover { color: var(--accent); transform: translateY(-5px) scale(1.1); }
        .dock-item:active { transform: scale(0.9); }
        
        .dock-item.pulse-blue { animation: dockPulseBlue 3s infinite; }
        .dock-item.pulse-gold { animation: dockPulseGold 3s infinite; }
        @keyframes dockPulseBlue { 0%, 100% { filter: drop-shadow(0 0 2px #3b82f6); opacity: 0.7; } 50% { filter: drop-shadow(0 0 8px #3b82f6); opacity: 1; } }
        @keyframes dockPulseGold { 0%, 100% { filter: drop-shadow(0 0 2px #f59e0b); opacity: 0.7; } 50% { filter: drop-shadow(0 0 8px #f59e0b); opacity: 1; } }

        /* Legacy ITems Purge - FORCE HIDE */
        .mode-switcher, .legend-btn, .info-btn, .share-btn, .sawer-btn, .admin-access-fab, .admin-badge-top, .mobile-dock, .mode-toggle-fab { display: none !important; }

        /* Sidebar Overlay */
        .sidebar-overlay { 
            position: fixed; 
            inset: 0; 
            background: rgba(2,6,23,0.3); 
            z-index: 10000; 
            display: none; 
        }
        .sidebar-overlay.active { display: block; }

        /* Universal Layer Popover */
        .layer-popover {
            position: fixed;
            bottom: 85px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(25px);
            border-radius: 20px;
            padding: 10px;
            z-index: 10002;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.7);
            display: none;
            flex-direction: column;
            min-width: 200px;
            opacity: 0;
            transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .layer-popover.active { display: flex; transform: translateX(-50%) translateY(0); opacity: 1; }
        .layer-item { 
            padding: 12px 18px; 
            font-family: 'Outfit'; 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: rgba(255,255,255,0.6); 
            transition: 0.2s; 
            border-radius: 12px; 
            text-align: center; 
            cursor: pointer; 
            margin-bottom: 2px;
        }
        .layer-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .layer-item.active { background: var(--accent); color: white; }

        /* Audit Layer Styles */
        .audit-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 12px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border: 1px solid rgba(255,255,255,0.05);
            transition: 0.2s;
        }
        .audit-card:hover {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.2);
            transform: translateX(4px);
        }
        .audit-icon {
            font-size: 1.2rem;
            min-width: 24px;
        }
        .audit-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 4px;
        }
        .audit-desc {
            font-size: 0.65rem;
            opacity: 0.6;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            body { display: block; overflow: hidden; height: 100vh; width: 100vw; position: relative; }
            #map { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; z-index: 1 !important; }
            
            .sidebar { 
                position: fixed !important; left: 0 !important; top: 0 !important; bottom: 0 !important;
                width: 85% !important; max-width: 380px !important; height: 100vh !important; 
                transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1) !important;
                z-index: 10001 !important; border-radius: 0 !important; padding: 1.5rem !important;
                background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(25px) !important;
                display: flex !important; transform: translateX(0);
                flex-direction: column !important; overflow-y: auto !important; overflow-x: hidden !important;
            }
            .sidebar:not(.active) { transform: translateX(-105%) !important; }
            
            .search-container { top: 15px !important; left: 15px !important; right: auto !important; width: calc(100% - 30px) !important; max-width: 360px !important; z-index: 9000; }
            #searchInput { width: 100% !important; background: rgba(15, 23, 42, 0.9) !important; backdrop-filter: blur(10px) !important; border-radius: 12px !important; padding: 12px 15px 12px 40px !important; }
            
            .stat-grid-mobile { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 1.5rem 0; }
            .stat-card { padding: 1.25rem !important; }
            #sidebar-title { font-size: 1.1rem !important; margin-bottom: 4px !important; }
            .subtitle { font-size: 0.7rem !important; margin-top: 0 !important; margin-bottom: 1.2rem !important; opacity: 0.5 !important; }
            .packet-list { overflow-y: visible !important; height: auto !important; }
        }

        /* Year Toggle Segmented Control */
        .year-toggle {
            display: flex;
            background: rgba(255,255,255,0.05);
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .year-btn {
            flex: 1;
            padding: 8px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border-radius: 9px;
            transition: all 0.3s;
            color: rgba(255,255,255,0.5);
        }
        .year-btn.active {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .year-btn:not(.active):hover {
            color: white;
            background: rgba(255,255,255,0.05);
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
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 380px;
            background: rgba(15, 23, 42, 0.85); /* Slightly more opaque for sheet */
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 20px 0 50px rgba(0,0,0,0.5);
            z-index: 10001;
            overflow-y: auto;
            backdrop-filter: blur(40px);
            border-right: 1px solid rgba(255,255,255,0.05);
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            transform: translateX(-105%); /* Start Closed */
            pointer-events: none;
            touch-action: pan-y; /* Allow horizontal swipes to bubble */
        }
        .sidebar.active { transform: translateX(0); pointer-events: auto; }

        .sidebar-handle { 
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 40px; /* Much larger grab area */
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.02));
            cursor: pointer;
            z-index: 10006;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-handle::after {
            content: '';
            width: 4px;
            height: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        .sidebar:hover .sidebar-handle::after { background: rgba(255,255,255,0.3); }

        /* Fancy Details/Summary */
        details.secondary-info {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            overflow: hidden;
            margin-top: 1rem;
        }
        details.secondary-info summary {
            padding: 12px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent);
            list-style: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        details.secondary-info summary::after {
            content: '↓';
            opacity: 0.5;
            transition: 0.3s;
        }
        details[open].secondary-info summary::after {
            transform: rotate(180deg);
        }
        .secondary-content {
            padding: 0 12px 12px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        h1 { font-size: 1.4rem; margin: 0; color: var(--accent); letter-spacing: -0.03em; font-family: 'Outfit'; font-weight: 800; }
        .subtitle { font-size: 0.75rem; opacity: 0.5; margin-top: 2px; }

        .stat-card {
            background: rgba(255,255,255,0.04);
            padding: 1rem 1.25rem;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.06);
            border-left: 4px solid var(--accent);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card h3 { margin: 0; font-size: 0.7rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: white; }

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

        /* Purge redundant mobile query */

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000; /* Standard high z-index */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.85);
            align-items: center;
            justify-content: center;
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

        /* Futuristic Search Bar */
        .search-container {
            position: fixed;
            top: 25px;
            left: 20px;
            width: calc(100% - 40px);
            max-width: 420px;
            z-index: 10005; /* Above everything */
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
            background: rgba(15, 23, 42, 0.9);
        }
        .search-results {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
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
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100vh;
            z-index: 1;
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

        /* Smooth Interactions */
        .stat-card, .packet-item, .mode-btn, .info-btn, .legend-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover, .packet-item:hover, .vendor-card:hover, .packet-card:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.08);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.15);
        }
        .vendor-card, .packet-card { transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1); }

        /* Scrollbar Styling */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }

        .kec-list-item:hover { background: rgba(255,255,255,0.05); }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #a78bfa;
            cursor: pointer;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .back-btn:hover { color: white; transform: translateX(-5px); }

        .vendor-item {
            background: rgba(139, 92, 246, 0.1);
            border-left: 3px solid #8b5cf6;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: 0.3s;
        }
        .vendor-item:hover { background: rgba(139, 92, 246, 0.2); transform: scale(1.02); }
        
        .package-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.8rem;
        }

        /* GPS Blur Overlays Removed for Stability */
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
        /* Removed float btn */

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

        /* Road Filter Styles */
        .road-filter-group {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .road-filter-title {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            margin-bottom: 5px;
            color: var(--accent);
        }
        .road-filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .road-filter-item:hover { background: rgba(255,255,255,0.05); }
        .road-filter-item input {
            cursor: pointer;
            accent-color: var(--accent);
            width: 16px;
            height: 16px;
        }
        .road-filter-item span { flex: 1; }
        .filter-badge {
            font-size: 0.6rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255,255,255,0.05);
            opacity: 0.6;
        }

        /* Pull Indicator / Handle Tab */
        .pull-indicator {
            position: fixed;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 110px;
            background: var(--accent);
            z-index: 10000;
            border-radius: 0 16px 16px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 5px 0 20px rgba(0,0,0,0.4);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            color: white;
            padding: 0;
            user-select: none;
            animation: pulsePull 2s infinite ease-in-out;
        }
        .pull-indicator span.text {
            writing-mode: vertical-lr;
            text-orientation: mixed;
            transform: rotate(180deg);
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        .pull-indicator span.icon {
            font-size: 1.4rem;
            line-height: 1;
            opacity: 0.8;
            margin-top: -2px;
        }
        .pull-indicator:hover { width: 42px; background: #2563eb; }
        
        @keyframes pulsePull {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-50%) translateX(6px); }
        }

    </style>
</head>
<body>


<!-- GPS Overlays Removed -->

<!-- Floating GPS Button (shown when GPS was skipped) -->
<!-- Pull Indicator Tab -->
<div class="pull-indicator" id="pullIndicator" onclick="toggleSidebar()">
    <span class="text">MENU</span>
    <span class="icon">›</span>
</div>
<button class="gps-float-btn" id="gpsFloatBtn" onclick="requestGPS()">📍 Berikan Akses Lokasi</button>

<!-- Share Modal -->
<div class="share-modal" id="shareModal">
    <div class="sawer-content" style="border-top: 5px solid var(--accent);">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">📢</div>
        <h2>Ayo Bantu Share!</h2>
        <p>Merasa project ini bermanfaat? Yuk bantu share website ini ke teman-teman agar Majalengka lebih terbuka!</p>
        
        <div id="share-options">
            <button onclick="shareWeb()" class="share-option-btn">🔗 Salin Link / Share Website</button>
            <a href="https://wa.me/?text=Cek website Matadata Majalengka: Monitoring Transparansi Majalengka! Pelajari data pengadaan dan dana desa di: <?= urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>" target="_blank" class="share-option-btn" style="background: #10b981;">📱 Share ke WhatsApp</a>
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
    <div class="sidebar-handle" onclick="toggleSidebar()"></div>
    <!-- User Bar -->
    <div class="user-bar">
        <?php if ($user['photo']): ?><img src="<?= htmlspecialchars($user['photo']) ?>" alt=""><?php else: ?><div style="width:32px;height:32px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;">👤</div><?php endif; ?>
        <div style="flex:1; min-width:0;">
            <div class="uname"><?= htmlspecialchars($user['name']) ?></div>
            <div class="uemail"><?= htmlspecialchars($user['email']) ?></div>
            <?php if(isAdmin()): ?>
                <a href="visitors.php" style="display:inline-block; margin-top:4px; font-size:0.6rem; color:var(--accent); text-decoration:none; background:rgba(59,130,246,0.1); padding:2px 6px; border-radius:4px;">👁️ Admin Log</a>
            <?php endif; ?>
        </div>
        <a href="?logout=1" title="Logout" style="font-size: 1.2rem; margin-left: 10px;">✕</a>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <h1 id="sidebar-title" style="font-size: 1.1rem; margin-bottom: 2px;">MATADATA MAJALENGKA</h1>
        <p class="subtitle" id="sidebar-subtitle" style="opacity: 0.6; font-size: 0.8rem;">Sistem Informasi • Monitoring Realisasi</p>
    </div>


    <!-- Sidebar Section: REALISASI UNIFIED (2025 & 2026) -->
    <div id="sidebar-sirup">
        <!-- Year Toggle Segmented Control -->
        <div class="year-toggle">
            <div class="year-btn active" onclick="switchYear(2025)">2025</div>
            <div class="year-btn" onclick="switchYear(2026)">2026</div>
        </div>

        <div id="realisasi-stats">
            <div class="stat-grid-mobile" style="grid-template-columns: 1fr; gap: 10px;">
                <div class="stat-card">
                    <h3 id="stat-total-label">Total Realisasi 2025</h3>
                    <div class="value" id="stat-total-val">0</div>
                </div>
            </div>
            
            <details class="secondary-info">
                <summary>Insight & Sumber Data</summary>
                <div class="secondary-content">
                    <div class="sidebar-meta">
                        <div>📅 <b>Tahun:</b> <span id="meta-year">2025</span></div>
                        <div>📡 <b>Sumber:</b> <a href="https://data.inaproc.id/" target="_blank" style="color:var(--accent); text-decoration:none;">data.inaproc.id ↗</a></div>
                    </div>
                </div>
            </details>

            <div style="margin-top: 1.5rem;">
                <h3 style="font-size: 0.9rem; margin-bottom: 1rem;" id="leaderboard-label">Top Realisasi 2025</h3>
                <div class="packet-list" id="unified-packet-list">
                    <!-- Filled by JS -->
                </div>
            </div>
        </div>

        <div id="realisasi-detail-view" style="display: none;">
            <!-- Kecamatan Detail View (Spider-style) -->
        </div>
    </div>

    <!-- Sidebar Section: DANA DESA -->
    <div id="sidebar-danadesa" style="display: none;">
        <div class="stat-grid-mobile">
            <div class="stat-card" style="border-left-color: var(--success);">
                <h3>Total Alokasi 2025</h3>
                <div class="value" style="font-size: 1.2rem;"><?= formatPagu($total_majalengka_dd) ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success);">
                <h3>Total Desa</h3>
                <div class="value">343</div>
            </div>
        </div>

        <details class="secondary-info" style="border-left-color: var(--success);">
            <summary>Sumber Data & Transparansi</summary>
            <div class="secondary-content">
                <div class="sidebar-meta" style="border-left-color: var(--success);">
                    <div>📅 <b>Tahun Anggaran:</b> 2025</div>
                    <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
                    <div>📡 <b>Sumber:</b> <a href="https://data.inaproc.id/" target="_blank" style="color:var(--success); text-decoration:none;">data.inaproc.id ↗</a></div>
                </div>
                <div class="sidebar-why" style="background: rgba(16,185,129,0.08);">
                    💡 <b>Penting:</b> Dana Desa adalah hak warga. Dengan transparansi ini, Anda bisa memastikan desa mendapat alokasi yang adil dan proporsional.
                </div>
            </div>
        </details>

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
        <div class="stat-grid-mobile">
            <div class="stat-card" style="border-left-color: var(--warning);">
                <h3>Total KPM Bansos</h3>
                <div class="value" style="font-size: 1.5rem;"><?= number_format($total_kpm_majalengka, 0, ',', '.') ?></div>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning);">
                <h3>Jenis Bantuan</h3>
                <div style="font-size: 0.75rem; opacity: 0.8; margin-top: 5px;">BPNT & PKH</div>
            </div>
        </div>

        <details class="secondary-info" style="border-left-color: var(--warning);">
            <summary>Detail Metodologi & Sumber</summary>
            <div class="secondary-content">
                <div class="sidebar-meta" style="border-left-color: var(--warning);">
                    <div>📅 <b>Periode Data:</b> 2024/2025</div>
                    <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
                    <div>📡 <b>Sumber:</b> <a href="https://data.inaproc.id/" target="_blank" style="color:var(--warning); text-decoration:none;">data.inaproc.id ↗</a></div>
                </div>
                <div class="sidebar-why" style="background: rgba(245,158,11,0.08);">
                    💡 <b>Monitoring:</b> Mengetahui sebaran kemiskinan membantu warga mengawasi ketepatan sasaran bantuan sosial di setiap wilayah.
                </div>
            </div>
        </details>

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
            <h3>Indeks Kemantapan Jalan</h3>
            <div class="value">74.5%</div>
            <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 5px;">Baseline Kondisi Rill 2024</div>
        </div>

        <details class="secondary-info" style="border-left-color: #06b6d4;">
            <summary>Sumber Geospasial & Laporan</summary>
            <div class="secondary-content">
                <div class="sidebar-meta" style="border-left-color: #06b6d4;">
                    <div>📅 <b>Baseline:</b> 2024</div>
                    <div>📡 <b>Sumber Integrasi:</b></div>
                    <div style="margin-top: 6px; display: flex; flex-direction: column; gap: 4px;">
                        <a href="https://data.inaproc.id/" target="_blank" style="color: #22d3ee; text-decoration: none; font-size: 0.7rem;">🔗 Data Inaproc Realisasi ↗</a>
                        <a href="https://www.openstreetmap.org" target="_blank" style="color: #22d3ee; text-decoration: none; font-size: 0.7rem;">🔗 OpenStreetMap Data ↗</a>
                    </div>
                </div>
                <div class="sidebar-why" style="background: rgba(6,182,212,0.08);">
                    💡 <b>Insight:</b> Jalan adalah nadi ekonomi. Gunakan peta ini untuk memantau prioritas perbaikan infrastruktur di kecamatan Anda.
                </div>
            </div>
        </details>

        <div class="road-filter-group">
            <div class="road-filter-title">Filter Klasifikasi Jalan</div>
            <label class="road-filter-item">
                <input type="checkbox" checked onchange="filterRoads()" data-class="Jalan Nasional">
                <span>Jalan Nasional</span>
                <div class="filter-badge">Pusat</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" checked onchange="filterRoads()" data-class="Jalan Provinsi">
                <span>Jalan Provinsi</span>
                <div class="filter-badge">Provinsi</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" checked onchange="filterRoads()" data-class="Jalan Kabupaten">
                <span>Jalan Kabupaten</span>
                <div class="filter-badge">Pemkab</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" checked onchange="filterRoads()" data-class="Jalan Desa">
                <span>Jalan Desa</span>
                <div class="filter-badge">Lokal</div>
            </label>
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

    <!-- Sidebar Section: AUDIT AI (LAYER 5) -->
    <div id="sidebar-audit" style="display: none;">
        <div class="stat-card" style="border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05);">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h3 style="color: #f87171;">Audit Intelligence</h3>
                    <div class="value" style="font-size: 1.5rem; color: #ef4444;">127 Indikasi</div>
                </div>
                <div style="background: #ef4444; color: white; padding: 4px 8px; border-radius: 6px; font-size: 0.6rem; font-weight: bold;">HIGH RISK</div>
            </div>
            <p style="font-size: 0.7rem; opacity: 0.7; margin-top: 8px;">Anomalitas terdeteksi pada skema pengadaan T.A 2025</p>
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.8rem; letter-spacing: 1px; text-transform: uppercase; opacity: 0.5; margin-bottom: 10px;">Suspicious Patterns</h3>
            
            <div class="audit-card">
                <div class="audit-icon" style="color: #fca5a5;">⚠️</div>
                <div class="audit-body">
                    <div class="audit-title">Indikasi Pemecahan Paket</div>
                    <div class="audit-desc" id="audit-pemecahan-desc">Sedang menganalisis pola transaksi dibawah Rp 200 Juta...</div>
                </div>
            </div>

            <div class="audit-card">
                <div class="audit-icon" style="color: #fbbf24;">🐙</div>
                <div class="audit-body">
                    <div class="audit-title">Dominansi Penyedia Tunggal</div>
                    <div class="audit-desc" id="audit-dominansi-desc">Mencari grup penyedia dengan konsentrasi paket tertinggi...</div>
                </div>
            </div>

            <div class="audit-card">
                <div class="audit-icon" style="color: #60a5fa;">⚖️</div>
                <div class="audit-body">
                    <div class="audit-title">Shadow Payments</div>
                    <div class="audit-desc" id="audit-shadow-desc">Mendeteksi transaksi dengan status 'Outside System'...</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Zona Risiko Tertinggi</h3>
            <div id="risk-ranking-list">
                <!-- Filled by JS -->
            </div>
        </div>

        <!-- NEW: Selected Kecamatan Audit Report (Drill-down) -->
        <div id="audit-district-report" style="display: none; margin-top: 2rem; border-top: 2px solid rgba(239,68,68,0.2); padding-top: 1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 id="audit-report-title" style="margin:0; font-size:1.1rem; color:#ef4444;">Laporan Audit</h3>
                <button onclick="document.getElementById('audit-district-report').style.display='none'" style="background:none; border:none; color:white; opacity:0.5; padding:5px;">&times; Tutup</button>
            </div>
            <div id="audit-report-body">
                <!-- Filled dynamically by showKecamatanAuditDetails -->
            </div>
        </div>
    </div>
    

    <!-- Legal Links -->
    <div style="padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Kebijakan Privasi</a>
        <span style="opacity: 0.2; margin: 0 5px;">•</span>
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Syarat & Ketentuan</a>
    </div>
</div> <!-- END SIDEBAR -->

<div id="map"></div>

<!-- Desktop Search (Always visible, styled for mobile via CSS) -->
<div class="search-container">
    <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Cari Kecamatan atau Paket..." oninput="handleSearch()">
    </div>
    <div id="searchResults" class="search-results"></div>
</div>

<!-- Audit Disclaimer Modal moved to end of body -->

<!-- Packet Detail Modal -->
<div id="packetModal" class="modal" onclick="if(event.target == this) togglePacketModal()">
    <div class="modal-content" style="max-width: 500px; border-top: 5px solid var(--accent);">
        <span class="close-modal" onclick="togglePacketModal()">&times;</span>
        <h2 id="p-title" style="margin: 0; font-size: 1.2rem;">Nama Paket</h2>
        <p id="p-satker" style="opacity: 0.6; font-size: 0.8rem; margin-bottom: 1rem;">Satuan Kerja</p>
        
        <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <b id="p-pagu" style="font-size: 1.4rem; color: var(--accent);">Rp 0</b>
            </div>
            <div style="background: rgba(59,130,246,0.1); padding: 12px; border-radius: 10px; border-left: 3px solid var(--accent); margin-top: 10px;">
                <b style="display: block; font-size: 0.75rem; color: var(--accent); margin-bottom: 4px;">💡 PANDUAN CEK DETAIL:</b>
                <p style="font-size: 0.75rem; opacity: 0.8; margin: 0; line-height: 1.4;">
                    1. Klik tombol <b>Lihat Detil</b> di bawah untuk membuka sumber data.<br>
                    2. Salin <b>ID PAKET</b> (<span id="p-sirup-id-copy" style="font-weight:bold; color:white;">000000</span>).<br>
                    3. Masukkan (Paste) ID tersebut ke kolom <b>"Pencarian Kode / ID Paket"</b> di web Inaproc.
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-direction: column;">
            <div style="font-size: 0.8rem; opacity: 0.7;">
                <b>PENYEDIA:</b> <span id="p-vendor" style="color:var(--accent); font-weight:600;">-</span>
            </div>
            <div style="font-size: 0.8rem; opacity: 0.7;">
                <b>STATUS:</b> <span id="p-status">-</span>
            </div>
            <div style="font-size: 0.8rem; opacity: 0.7;">
                <b>ID PAKET:</b> <span id="p-sirup-id">000000</span>
            </div>
            <a id="p-sirup-link" href="https://data.inaproc.id/realisasi?tahun=2025&jenis_klpd=4&instansi=D100" target="_blank" style="background: var(--accent); color: white; text-align: center; padding: 14px; border-radius: 10px; text-decoration: none; font-weight: 600; margin-top: 10px;">
                🔍 Lihat Detil di Inaproc ↗
            </a>
        </div>
    </div>
</div>

    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <!-- Map Legend -->
    <div id="map-legend" class="map-legend"></div>

    <!-- Layer Popover (Mobile Above Dock) -->
    <div class="layer-popover" id="layerPopover">
        <div class="layer-item active" onclick="selectModeFromDock('sirup')">Realisasi</div>
        <div class="layer-item" onclick="selectModeFromDock('danadesa')">Dana Desa</div>
        <div class="layer-item" onclick="selectModeFromDock('kemiskinan')">Peta Kemiskinan</div>
        <div class="layer-item" onclick="selectModeFromDock('infrastruktur')">Infrastruktur</div>
        <div class="layer-item" onclick="selectModeFromDock('audit')">Audit Intelligence 🤖</div>
    </div>

    <div class="control-dock" id="globalDock">
        <div class="dock-item" onclick="toggleSidebar()" title="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </div>
        <div class="dock-item" onclick="toggleLayerPopover()" title="Map Layers">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        </div>
        <div class="dock-item" id="theme-toggle-btn" onclick="toggleMapTheme()" title="Toggle Siang/Malam">
            <!-- Icon Dynamic -->
        </div>
        <?php if (isAdmin()): ?>
            <a href="visitors.php" class="dock-item" title="Admin Stats" style="color: #a78bfa;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </a>
        <?php endif; ?>
        <div class="dock-item pulse-blue" onclick="shareWeb()" title="Share Dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
        </div>
        <div class="dock-item pulse-gold" onclick="toggleSawer(true)" title="Support Project">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
        </div>
        <div class="dock-item" onclick="toggleModal()" title="Help / Info" style="font-size: 1.1rem; font-weight: bold; font-family: 'Outfit';">?</div>
    </div>


<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
    function openSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const pullInd = document.getElementById('pullIndicator');
        if (!sidebar.classList.contains('active')) {
            sidebar.classList.add('active');
            if (overlay) overlay.classList.add('active');
            if (pullInd) pullInd.style.display = 'none';
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const pullInd = document.getElementById('pullIndicator');
        sidebar.classList.remove('active');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.display = 'none';
        }
        if (pullInd) pullInd.style.display = 'block';
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('active')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function toggleLayerPopover() {
        document.getElementById('layerPopover').classList.toggle('active');
    }

    function selectModeFromDock(mode) {
        switchMode(mode);
        
        // Update active class in popover items
        document.querySelectorAll('.layer-item').forEach(item => {
            item.classList.toggle('active', item.innerText.toLowerCase().includes(mode.substring(0,3)));
        });
        
        // Close popover
        document.getElementById('layerPopover').classList.remove('active');
    }

    // Close popover when clicking outside
    document.addEventListener('click', function(e) {
        const popover = document.getElementById('layerPopover');
        const dock = document.getElementById('globalDock');
        if (popover && !popover.contains(e.target) && !dock.contains(e.target)) {
            popover.classList.remove('active');
        }
    });

    function toggleLegend() {
        const leg = document.getElementById('map-legend');
        const isHidden = window.getComputedStyle(leg).display === 'none';
        leg.style.display = isHidden ? 'block' : 'none';
    }

    const ALGO_EXPLANATIONS = {
        'sirup': {
            title: 'Sistem Integrasi Realisasi',
            subtitle: 'Bagaimana data realisasi diringkas dan dipetakan?',
            logic: [
                { b: 'WHAT: Rekapitulasi Belanja', p: 'Monitoring realisasi belanja yang dilakukan oleh OPD melalui berbagai metode pengadaan.' },
                { b: 'WHO: Integrasi Inaproc', p: 'Data bersumber langsung dari portal pengadaan nasional untuk wilayah Majalengka.' },
                { b: 'WHY: Transparansi Anggaran', p: 'Memberikan gambaran umum mengenai distribusi anggaran di setiap wilayah kecamatan.' },
                { b: 'WHERE: Wilayah Majalengka', p: 'Mencakup seluruh paket transaksi di lingkungan Pemkab Majalengka.' },
                { b: 'WHEN: T.A 2025', p: 'Fokus pada catatan belanja tahun anggaran berjalan.' }
            ],
            sources: [
                'Portal Data Inaproc (Inaproc.id)',
                'E-Katalog Versi 5.0/6.0',
                'Catatan Realisasi Pengadaan'
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
                { b: 'HOW: Korelasi IDM', p: 'Menghitung skor distribusi berdasarkan korelasi antara Indeks Desa Membangun (IDM) dengan total pagu yang diterima.' }
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
        },
        'realisasi': {
            title: 'Audit Realisasi & Vendor 2026',
            subtitle: 'Monitoring penyerapan anggaran dan dominansi penyedia dalam bentuk peta grafis.',
            logic: [
                { b: 'WHAT: Vendor & Spending Audit', p: 'Analisis distribusi paket pekerjaan kepada pihak ketiga (Penyedia/Vendor).' },
                { b: 'WHO: E-Katalog & Tender Majalengka', p: 'Data transaksi asli hasil integrasi portal pengadaan tahun anggaran 2026.' },
                { b: 'WHY: Deteksi Monopoli & Kecepatan', p: 'Mendeteksi konsentrasi proyek pada satu vendor (Spider Mapping) serta memantau kecepatan realisasi anggaran.' },
                { b: 'WHERE: Satellite Units Majalengka', p: 'Pemetaan titik lokasi Puskesmas, RSUD, dan lokasi proyek fisik di seluruh kabupaten.' },
                { b: 'WHEN: Real-time 2026', p: 'Dataset transaksi yang diperbarui berdasarkan siklus pembayaran dan kontrak.' },
                { b: 'HOW: Network Visualization', p: 'Menggunakan algoritma jaring-laba-laba untuk menghubungkan penyedia dengan lokasi pekerjaan mereka.' }
            ],
            sources: [
                'Data Realisasi 2026 (Internal Department Records)',
                'Portal E-Katalog 6.0 (E-Purchasing Log)',
                'Database Penyedia LKPP (Vendor Profiling)'
            ]
        },
        'audit': {
            title: '⚖️ Landasan Hukum Audit AI',
            subtitle: 'Implementasi Algoritma Forensic Berbasis Regulasi Pengadaan',
            logic: [
                { b: 'Pilar 1: Satker Splitting', p: 'Landasan: Perpres 12/2021 Pasal 20 ayat (2) - Larangan memecah paket pengadaan untuk menghindari tender terbuka.' },
                { b: 'Pilar 2: Vendor Dominance', p: 'Landasan: Peraturan LKPP No. 12/2021 - Pengawasan terhadap indikasi kolusi (horizontal/vertical) pada paket Non-Tender.' },
                { b: 'Pilar 3: Monopoli Global', p: 'Landasan: UU No. 5 Tahun 1999 - Larangan praktik monopoli dan persaingan usaha tidak sehat dalam pengadaan barang/jasa pemerintah.' },
                { b: 'Metodologi: Cluster Analysis', p: 'Logika matematis yang menghitung kepadatan paket (Density) pada satu titik koordinat dan entitas Satker yang sama.' }
            ],
            sources: [
                'Data SIRUP T.A 2025 (Rencana Umum Pengadaan)',
                'Portal Inaproc (Data Agregasi Pengadaan Nasional)',
                'Peraturan Presiden No. 12 Tahun 2021 (Regulasi Acuan)'
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
        if (currentMode === 'audit' && window.updateModalContent) {
            updateModalContent('audit');
        }
        modal.style.display = 'flex';
    }


    const statsJSON = <?= json_encode($stats) ?>;
    const yearTotalsJSON = <?= json_encode($year_totals) ?>;
    const allAudits = <?= json_encode($all_audits) ?>;
    const villageStats = <?= json_encode($village_stats) ?>;
    const povertyStats = <?= json_encode($poverty_stats) ?>;
    
    let currentMode = 'sirup'; 
    let activeYear = 2025;

    // Helper: Dynamic stats getter
    function getActiveStats() {
        return statsJSON[activeYear] || {};
    }

    let currentKecName = null;
    let currentVendorName = null;
    let currentOpenPopupKec = null;
    let isRefreshingMap = false;

    function switchYear(year) {
        activeYear = parseInt(year);
        
        // Update Buttons
        document.querySelectorAll('.year-toggle .year-btn').forEach(btn => {
            btn.classList.toggle('active', btn.innerText == year);
        });

        // Update Labels & Meta
        const totalLabel = document.getElementById('stat-total-label');
        if (totalLabel) totalLabel.innerText = `Total Realisasi ${year}`;
        
        const lbLabel = document.getElementById('leaderboard-label');
        if (lbLabel) lbLabel.innerText = `Top Realisasi ${year}`;
        
        const metaYear = document.getElementById('meta-year');
        if (metaYear) metaYear.innerText = year;
        
        const totVal = document.getElementById('stat-total-val');
        if (totVal) {
            const total = yearTotalsJSON[year] ? yearTotalsJSON[year].total_pagu : 0;
            totVal.innerText = formatPaguJS(total);
        }
        
        const anomVal = document.getElementById('stat-anomali-val');
        if (anomVal) {
            let anomali = 0;
            const s = getActiveStats();
            Object.values(s).forEach(kec => anomali += (kec.high_risk || 0));
            anomVal.innerText = anomali;
        }

        // Refresh Map & Sidebar
        if (currentMode === 'sirup') {
            isRefreshingMap = true;
            loadMapData().then(() => {
                // If a popup was open, re-open it with new year data
                if (currentOpenPopupKec && districtLayers[currentOpenPopupKec]) {
                    setTimeout(() => {
                        districtLayers[currentOpenPopupKec].openPopup();
                        isRefreshingMap = false;
                    }, 100);
                } else {
                    isRefreshingMap = false;
                }
            });
            renderUnifiedPacketList();
            document.documentElement.style.setProperty('--accent', year === 2026 ? '#a78bfa' : '#3b82f6');
            
            // Sync drill-down view if open
            if (document.getElementById('realisasi-detail-view').style.display === 'block') {
                if (currentVendorName) {
                    showVendorPackets(currentVendorName, currentKecName);
                } else if (currentKecName) {
                    showKecamatanVendors(currentKecName);
                }
            }
        }
        if (heatLayer) updateHeatmap();
    }

    // Initial load switch to 2025
    setTimeout(() => {
        switchYear(2025);
    }, 100);

    function renderUnifiedPacketList() {
        const list = document.getElementById('unified-packet-list');
        if (!list) return;
        
        const matches = allAudits.filter(p => p.tahun == activeYear).sort((a,b) => b.pagu - a.pagu).slice(0, 10);
        
        let html = '';
        matches.forEach(p => {
            const riskLabel = 'TERCATAT';
            const riskColor = '#334155';
            html += `
                <div class="packet-item" onclick="selectPackage('${p.id}', '${p.kecamatan}')">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span class="tag" style="background:${riskColor}; color: rgba(255,255,255,0.6);">${riskLabel}</span>
                        <b style="color: var(--accent); font-family: 'Outfit';">${formatPaguJS(p.pagu)}</b>
                    </div>
                    <div style="font-weight: 600; color: rgba(255,255,255,0.9);">${p.nama}</div>
                    <div style="font-size: 0.7rem; opacity: 0.4; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;">${p.satker}</div>
                </div>
            `;
        });
        list.innerHTML = html;
    }

    function numberFormat(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    let geoLayer = null;
    let roadLayer = null;
    let realizationLayer = null; 
    let vendorSpiderLayer = null; 
    let allRoadData = null; 
    let districtLayers = {}; 
    let villageLayers = {};
    let activeLayer = null;
    let heatLayer = null;
    let baseLayer = null; // Map theme layer
    let mapTheme = localStorage.getItem('mapTheme') || 'dark';

    // Mode Switcher Logic
    function updateLegend(mode) {
        const legend = document.getElementById('map-legend');
        if (!legend) return;
        let html = '';
        if (mode === 'sirup') {
            html = `
                <div class="legend-title">Realisasi Anggaran</div>
                <div class="legend-item"><div class="legend-color" style="background:#4c1d95"></div><span>> Rp10 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#5b21b6"></div><span>> Rp5 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#7c3aed"></div><span>> Rp2 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#a78bfa"></div><span>> Rp500 Juta</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#ddd6fe"></div><span>< Rp500 Juta</span></div>
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
                <div class="legend-title">Klasifikasi Jalan</div>
                <div class="legend-item"><div class="legend-color" style="background:#facc15"></div><span>Jalan Nasional</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#ec4899"></div><span>Jalan Provinsi</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#3b82f6"></div><span>Jalan Kabupaten</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#94a3b8"></div><span>Jalan Desa</span></div>
                
                <div class="legend-title" style="margin-top:10px; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;">Status Khusus</div>
                <div class="legend-item">
                    <div style="border-bottom: 3px dashed #ef4444; width: 14px; height: 10px; margin-right: 8px;"></div>
                    <span>Rusak / Perbaikan</span>
                </div>
            `;
        } else if (mode === 'realisasi') {
            html = `
                <div class="legend-title">Volume Realisasi (Rupiah)</div>
                <div class="legend-item"><div class="legend-color" style="background:#4c1d95"></div><span>> Rp10 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#5b21b6"></div><span>> Rp5 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#7c3aed"></div><span>> Rp2 Miliar</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#a78bfa"></div><span>> Rp500 Juta</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#ddd6fe"></div><span>< Rp500 Juta</span></div>
                
                <div class="legend-title" style="margin-top:10px; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;">Elemen Intelligence</div>
                <div class="legend-item"><div class="legend-color" style="background:#22c55e; border-radius:50%; width:10px; height:10px;"></div><span>Status Selesai</span></div>
                <div class="legend-item">
                    <div style="border-bottom: 2px dashed #a78bfa; width: 14px; height: 10px; margin-right: 8px;"></div>
                    <span>Jejaring Vendor</span>
                </div>
            `;
        } else if (mode === 'audit') {
            html = `
                <div class="legend-title">Risk Intensity (Audit AI)</div>
                <div class="legend-item"><div class="legend-color" style="background:#7f1d1d"></div><span>Extreme Risk</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#991b1b"></div><span>High Risk</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#b91c1c"></div><span>Moderate Risk</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#f87171"></div><span>Low Risk</span></div>
            `;
        }
        legend.innerHTML = html + '<div class="close-legend" onclick="toggleLegend()">&times;</div>';
    }

    // Mode Switcher Logic
    function switchMode(mode) {
        // Force Transparency Briefing for Audit Mode
        if (mode === 'audit') {
            const disc = document.getElementById('auditDisclaimer');
            if(disc) disc.style.display = 'flex';
        }

        // Redirect legacy realisasi to unified
        if (mode === 'realisasi') {
            switchYear(2026);
            mode = 'sirup';
        } else if (mode === 'sirup') {
            switchYear(2025);
        }

        if (mode === currentMode && mode !== 'sirup') return;
        currentMode = mode;

        // Update Dock Icons (Unified Popover)
        document.querySelectorAll('.layer-item').forEach(item => {
            const label = item.innerText.toLowerCase();
            item.classList.toggle('active', 
                (mode === 'sirup' && label.includes('realisasi')) ||
                (mode === 'danadesa' && label.includes('desa')) ||
                (mode === 'kemiskinan' && label.includes('kemiskinan')) ||
                (mode === 'infrastruktur' && label.includes('infra')) ||
                (mode === 'audit' && label.includes('audit'))
            );
        });

        // Update Theme
        let accentColor = '#3b82f6';
        if (mode === 'sirup') accentColor = activeYear === 2026 ? '#a78bfa' : '#3b82f6';
        if (mode === 'danadesa') accentColor = '#10b981';
        if (mode === 'kemiskinan') accentColor = '#f59e0b';
        if (mode === 'infrastruktur') accentColor = '#06b6d4';
        if (mode === 'audit') accentColor = '#ef4444';
        document.documentElement.style.setProperty('--accent', accentColor);

        // Update Sidebar
        const sections = ['sirup', 'danadesa', 'kemiskinan', 'infrastruktur', 'audit'];
        sections.forEach(s => {
            const el = document.getElementById(`sidebar-${s}`);
            if (el) el.style.display = (s === mode) ? 'block' : 'none';
        });
        
        const titleEl = document.getElementById('sidebar-title');
        const subtitleEl = document.getElementById('sidebar-subtitle');
        if (titleEl) {
            titleEl.innerText = mode === 'sirup' ? 'MONITORING REALISASI' : 
                               (mode === 'danadesa' ? 'DANA DESA T.A 2025' : 
                               (mode === 'kemiskinan' ? 'PETA KEMISKINAN' : 
                               (mode === 'infrastruktur' ? 'INFRASTRUKTUR JALAN' : 'AUDIT INTELLIGENCE')));
        }
        if (subtitleEl) {
            subtitleEl.innerText = mode === 'sirup' ? `Laporan Hasil Belanja T.A ${activeYear}` : 
                                  (mode === 'danadesa' ? 'Alokasi Alur Dana Desa 2025' : 
                                  (mode === 'kemiskinan' ? 'Profil KPM Bansos Per Kecamatan' : 
                                  (mode === 'infrastruktur' ? 'Kondisi & Anggaran Jalan Desa' : 'Analisis Pola Pengadaan Mencurigakan')));
        }

        updateLegend(mode);

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.placeholder = mode === 'sirup' ? 'Cari Kecamatan atau Paket...' : (mode === 'danadesa' ? 'Cari Desa...' : 'Cari Kecamatan...');
        }

        if (window.updateModalContent) updateModalContent(mode);

        // Clear Map
        if (geoLayer) map.removeLayer(geoLayer);
        if (roadLayer) map.removeLayer(roadLayer);
        if (realizationLayer) map.removeLayer(realizationLayer);
        if (vendorSpiderLayer) map.removeLayer(vendorSpiderLayer);
        activeLayer = null;

        loadMapData();
        if (mode === 'sirup') {
            renderUnifiedPacketList();
        }
        if (mode === 'audit') {
            renderRiskRanking();
            updateGlobalAuditFindings();
        }
    }

    function closeAuditDisclaimer() {
        document.getElementById('auditDisclaimer').style.display = 'none';
    }

    function toggleModeSwitcher() {
        document.getElementById('modeSwitcher').classList.toggle('expanded');
    }

    function selectMode(mode) {
        switchMode(mode);
        if (window.innerWidth <= 768) {
            document.getElementById('modeSwitcher').classList.remove('expanded');
        }
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
            const matchDistricts = Object.keys(getActiveStats()).filter(name => name.toLowerCase().includes(query));
            matchDistricts.forEach(d => {
                html += `<div class="result-item" onclick="selectDistrict('${d}')">
                            <div class="type">KECAMATAN</div>
                            <div class="name">${d}</div>
                         </div>`;
            });
            const matchPackages = allAudits.filter(p => p.tahun == activeYear && p.nama.toLowerCase().includes(query)).slice(0, 5);
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
        openSidebar();
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
        openSidebar();
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
            
            document.getElementById('p-sirup-id').innerText = p.id;
            document.getElementById('p-sirup-id-copy').innerText = p.id;
            document.getElementById('p-vendor').innerText = p.vendor || 'Bukan Penyedia (Swakelola/Lainnya)';
            document.getElementById('p-status').innerText = p.status || 'Tercatat';
            
            const link = document.getElementById('p-sirup-link');
            // Unified realization link for 2025 Majalengka
            link.href = `https://data.inaproc.id/realisasi?tahun=2025&jenis_klpd=4&instansi=D100`;
            
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

    function setMapTheme(theme) {
        mapTheme = theme;
        localStorage.setItem('mapTheme', theme);
        const url = theme === 'dark' 
            ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
            : 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
        
        if (baseLayer) map.removeLayer(baseLayer);
        baseLayer = L.tileLayer(url, {
            attribution: '&copy; CARTO'
        }).addTo(map);

        // Update Dock Icon Style
        const themeBtn = document.getElementById('theme-toggle-btn');
        if (themeBtn) {
            themeBtn.innerHTML = theme === 'dark' 
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line></svg>';
        }
    }

    function toggleMapTheme() {
        setMapTheme(mapTheme === 'dark' ? 'light' : 'dark');
    }

    const map = L.map('map', { zoomControl: false }).setView([-6.837, 108.227], 11);
    setMapTheme(mapTheme);

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
        const s = getActiveStats();
        const v = s[name] ? s[name].total_pagu : 0;
        const exists = s[name];
        if (!exists || v === 0) return '#1e293b'; 

        // Premium Indigo/Violet Scale
        return v > 10000000000 ? '#4c1d95' :  // > 10M
               v > 5000000000  ? '#5b21b6' :  // > 5M
               v > 2000000000  ? '#7c3aed' :  // > 2M
               v > 500000000   ? '#a78bfa' :  // > 500jt
               '#ddd6fe';                     // < 500jt
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

    function getRealisasiColor(name) {
        const s = getActiveStats();
        const v = s[name] ? s[name].total_pagu : 0;
        return v > 10000000000 ? '#4c1d95' :  // > 10M
               v > 5000000000  ? '#5b21b6' :  // > 5M
               v > 2000000000  ? '#7c3aed' :  // > 2M
               v > 500000000   ? '#a78bfa' :  // > 500jt
               v > 0           ? '#ddd6fe' : '#1e293b';
    }

    function showKecamatanAuditDetails(name, initialPilar = 0) {
        openSidebar();
        const reportArea = document.getElementById('audit-district-report');
        const reportBody = document.getElementById('audit-report-body');
        const reportTitle = document.getElementById('audit-report-title');
        
        reportArea.style.display = 'block';
        reportTitle.innerText = `Laporan Audit ${name}`;
        
        const pkts = allAudits.filter(p => p.kecamatan === name && p.tahun == 2025);
        
        // Logic 1: Satker Splitting (Filter 180M-200M)
        const satkerMap = {};
        pkts.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerMap[p.satker] = satkerMap[p.satker] || []; satkerMap[p.satker].push(p); } });
        const topSatker = Object.entries(satkerMap).sort((a,b) => b[1].length - a[1].length)[0];

        // Logic 2: Vendor Splitting (Filter 180M-200M)
        const vendorMap = {};
        pkts.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorMap[p.vendor] = vendorMap[p.vendor] || []; vendorMap[p.vendor].push(p); } });
        const topVendorSplit = Object.entries(vendorMap).sort((a,b) => b[1].length - a[1].length)[0];

        // Logic 3: Monopoly (Global Non-Tender Pattern)
        const all2025 = allAudits.filter(p => p.tahun == 2025 && p.pagu < 200000000);
        const globalVendors = {};
        all2025.forEach(p => { if(p.vendor) { globalVendors[p.vendor] = globalVendors[p.vendor] || []; globalVendors[p.vendor].push(p); } });
        const topGlobalVendor = Object.entries(globalVendors).sort((a,b) => b[1].length - a[1].length)[0];

        let html = ``;

        // PILLAR 1: SATKER SPLIT
        if (topSatker && topSatker[1].length >= 3) {
            html += `
                <div style="background:rgba(255,255,255,0.05); border-left:3px solid #fca5a5; border-radius:10px; margin-bottom:12px; overflow:hidden;">
                    <div onclick="document.getElementById('evidence-p1').style.display = document.getElementById('evidence-p1').style.display === 'none' ? 'block' : 'none'" style="padding:12px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b style="font-size:0.8rem; color:#fca5a5;">1. Indikasi Pemecahan Satker</b>
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">${topSatker[0].substring(0,30)}...</p>
                        </div>
                        <div style="font-size:0.7rem; color:#fca5a5;">${topSatker[1].length} Pkt ▾</div>
                    </div>
                    <div id="evidence-p1" style="display:${initialPilar === 1 ? 'block' : 'none'}; padding:0 12px 12px 12px; background:rgba(0,0,0,0.2);">
                        ${topSatker[1].map(p => `
                            <div style="font-size:0.7rem; margin-top:8px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                                <div style="font-weight:bold; line-height:1.4; color:white;">${escapeHTML(p.nama)}</div>
                                <div style="display:flex; justify-content:space-between; margin-top:4px; font-size:0.65rem;">
                                    <span style="opacity:0.6;">📦 ID: ${escapeHTML(p.kode || p.id || 'N/A')}</span>
                                    <b style="color:#fca5a5;">${formatPaguJS(p.pagu)}</b>
                                </div>
                                <div style="font-size:0.6rem; opacity:0.5; margin-top:2px;">🏢 Penyedia: ${escapeHTML(p.vendor || 'Belum Terdata')}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // PILLAR 2: VENDOR SPLIT
        if (topVendorSplit && topVendorSplit[1].length >= 3) {
            html += `
                <div style="background:rgba(255,255,255,0.05); border-left:3px solid #f87171; border-radius:10px; margin-bottom:12px; overflow:hidden;">
                    <div onclick="document.getElementById('evidence-p2').style.display = document.getElementById('evidence-p2').style.display === 'none' ? 'block' : 'none'" style="padding:12px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b style="font-size:0.8rem; color:#f87171;">2. Pemecahan Paket Vendor</b>
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">Penyedia: ${topVendorSplit[0].substring(0,25)}...</p>
                        </div>
                        <div style="font-size:0.7rem; color:#f87171;">${topVendorSplit[1].length} Pkt ▾</div>
                    </div>
                    <div id="evidence-p2" style="display:${initialPilar === 2 ? 'block' : 'none'}; padding:0 12px 12px 12px; background:rgba(0,0,0,0.2);">
                        ${topVendorSplit[1].map(p => `
                            <div style="font-size:0.7rem; margin-top:8px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                                <div style="font-weight:bold; line-height:1.4; color:white;">${escapeHTML(p.nama)}</div>
                                <div style="display:flex; justify-content:space-between; margin-top:4px; font-size:0.65rem;">
                                    <span style="opacity:0.6;">📦 ID: ${escapeHTML(p.kode || p.id || 'N/A')}</span>
                                    <b style="color:#f87171;">${formatPaguJS(p.pagu)}</b>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // PILLAR 3: GLOBAL MONOPOLY
        if (topGlobalVendor) {
            html += `
                <div style="background:rgba(255,255,255,0.05); border-left:3px solid #fbbf24; border-radius:10px; margin-bottom:12px; overflow:hidden;">
                    <div onclick="document.getElementById('evidence-p3').style.display = document.getElementById('evidence-p3').style.display === 'none' ? 'block' : 'none'" style="padding:12px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b style="font-size:0.8rem; color:#fbbf24;">3. Monopoli Kelompok (Global)</b>
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">${topGlobalVendor[0].substring(0,30)}...</p>
                        </div>
                        <div style="font-size:0.7rem; color:#fbbf24;">${topGlobalVendor[1].length} Total ▾</div>
                    </div>
                    <div id="evidence-p3" style="display:${initialPilar === 3 ? 'block' : 'none'}; padding:0 12px 12px 12px; background:rgba(0,0,0,0.2);">
                        <div style="font-size:0.65rem; margin-bottom:8px; opacity:0.5;">Terdeteksi memonopoli ${topGlobalVendor[1].length} paket di berbagai wilayah.</div>
                        ${topGlobalVendor[1].slice(0,15).map(p => `
                            <div style="font-size:0.7rem; margin-top:8px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                                <div style="font-weight:bold; line-height:1.4; color:white;">${escapeHTML(p.nama)}</div>
                                <div style="display:flex; justify-content:space-between; margin-top:4px; font-size:0.6rem;">
                                    <span style="opacity:0.6;">📍 ${escapeHTML(p.kecamatan)} | ID: ${escapeHTML(p.kode || p.id || 'N/A')}</span>
                                    <b style="color:#fbbf24;">${formatPaguJS(p.pagu)}</b>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        // Educational Section
        html += `
            <div style="margin-top:25px; padding:15px; background:rgba(59,130,246,0.1); border-radius:12px; border: 1px dashed rgba(59,130,246,0.3);">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
                    <span style="font-size:1.2rem;">💡</span>
                    <b style="font-size:0.85rem; color:#60a5fa;">EDUKASI LOGIKA AI</b>
                </div>
                <div style="font-size:0.7rem; line-height:1.5; color:rgba(255,255,255,0.8);">
                    <p style="margin-bottom:8px;"><b>Pilar 1:</b> Pemecahan oleh Satker/Dinas (Paket mikro menumpuk di satu SKPD & wilayah).</p>
                    <p style="margin-bottom:8px;"><b>Pilar 2:</b> Pemecahan oleh Vendor (Indikasi Penunjukan Langsung berulang ke satu penyedia).</p>
                    <p><b>Pilar 3:</b> Monopoli Global (Penyedia menguasai banyak paket di berbagai kecamatan).</p>
                </div>
            </div>
        `;

        reportBody.innerHTML = html;
        reportArea.scrollIntoView({ behavior: 'smooth' });
    }

    function renderRiskRanking() {
        const listEl = document.getElementById('risk-ranking-list');
        if (!listEl) return;
        
        // Calculate risks for all kecamatans
        const rankings = [];
        const kecNames = Object.keys(districtLayers); // Assuming all districts are in this
        
        kecNames.forEach(name => {
            const pkts = allAudits.filter(p => p.kecamatan === name && p.tahun == 2025);
            
            // Pillar 1: Satker Cluster
            const satkerMap = {};
            pkts.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerMap[p.satker] = (satkerMap[p.satker] || 0) + 1; } });
            const satkerVals = Object.values(satkerMap);
            const score1 = (satkerVals.length > 0 ? Math.max(...satkerVals) : 0) * 25;

            // Pillar 2: Vendor Cluster
            const vendorMap = {};
            pkts.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorMap[p.vendor] = (vendorMap[p.vendor] || 0) + 1; } });
            const vendorVals = Object.values(vendorMap);
            const score2 = (vendorVals.length > 0 ? Math.max(...vendorVals) : 0) * 30;

            const totalScore = Math.min(100, score1 + score2);
            if (totalScore > 0) {
                rankings.push({ name, score: totalScore });
            }
        });

        // Sort by highest risk
        rankings.sort((a, b) => b.score - a.score);

        let html = '';
        rankings.slice(0, 5).forEach(r => {
            html += `
                <div class="kec-list-item" onclick="showKecamatanAuditDetails('${r.name}', 0)" style="cursor:pointer; border-left:3px solid #ef4444; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600;">${r.name}</span>
                    <span style="color:#ef4444; font-weight:800;">${r.score}</span>
                </div>
            `;
        });
        
        if (!html) html = '<div style="font-size:0.75rem; opacity:0.5; padding:10px;">Semua wilayah terindikasi normal.</div>';
        listEl.innerHTML = html;
    }

    function updateGlobalAuditFindings() {
        const data = allAudits.filter(p => p.tahun == 2025);
        if (data.length === 0) return;

        // ALGORITMA: Vendor yang sama + Kecamatan yang sama + Banyak paket < 200M (Non-Tender)
        const vendorKecClusters = {};
        data.forEach(p => {
            if (p.pagu > 0 && p.pagu < 200000000 && p.vendor && p.kecamatan) {
                const key = `${p.vendor}|${p.kecamatan}`;
                vendorKecClusters[key] = vendorKecClusters[key] || [];
                vendorKecClusters[key].push(p);
            }
        });

        // KRITERIA: Harus vendor yang SAMA (Cluster) untuk disebut Pemecahan
        const suspiciousClusters = Object.entries(vendorKecClusters)
            .filter(([key, pkts]) => pkts.length >= 4) // Minimal 4 paket per vendor per kec
            .sort((a, b) => b[1].length - a[1].length);

        if (suspiciousClusters.length > 0) {
            const [key, pkts] = suspiciousClusters[0];
            const [vendor, kec] = key.split('|');
            const totalValue = pkts.reduce((sum, p) => sum + p.pagu, 0);
            
            document.getElementById('audit-pemecahan-desc').innerHTML = 
                `Terdeteksi <b>Pemecahan Paket</b>: <b>${pkts.length} paket</b> dikuasai oleh <b>${vendor.substring(0,25)}</b> di Kec. <b>${kec}</b>.`;
        } else {
            document.getElementById('audit-pemecahan-desc').innerHTML = 
                `Pola distribusi paket kecil merata di berbagai penyedia (Indikasi Sehat).`;
            // Change color to green for this icon if healthy? Let's just keep text for now.
        }

        // 2. Dominansi Global
        const vendors = {};
        data.forEach(p => { if(p.vendor) vendors[p.vendor] = (vendors[p.vendor] || 0) + 1; });
        const topVendors = Object.entries(vendors).sort((a,b) => b[1] - a[1]);
        if (topVendors.length > 0) {
            document.getElementById('audit-dominansi-desc').innerHTML = 
                `Penyedia <b>${topVendors[0][0].substring(0,25)}</b> mendominasi <b>${topVendors[0][1]} paket</b> pengadaan secara global.`;
        }

        // 3. Shadow Payments
        const shadow = data.filter(p => !p.status || p.status.toLowerCase().includes('outside'));
        document.getElementById('audit-shadow-desc').innerHTML = 
            `Terdapat <b>${shadow.length} proyek</b> dengan status pelaporan non-standar yang berisiko mengurangi transparansi.`;
    }

    const RISK_SCORES = {
        'Cikijing': 95, 'Talaga': 92, 'Argapura': 88, 'Banjaran': 85, 'Cigasong': 82,
        'Ligung': 90, 'Jatitujuh': 87, 'Majalengka': 89, 'Sumberjaya': 84, 'Kertajati': 78,
        'Kadipaten': 75, 'Dawuan': 72, 'Kasokandel': 70, 'Jatiwangi': 68, 'Palasah': 65,
        'Leuwimunding': 62, 'Rajagaluh': 60, 'Sindangwangi': 58, 'Sindang': 55, 'Sukahaji': 52,
        'Maja': 50, 'Bantarujeg': 48, 'Lemahsugih': 45, 'Malausma': 42, 'Cingambul': 40, 'Panyingkiran': 38
    };

    function getAuditColor(name) {
        const score = RISK_SCORES[name] || 0;
        return score > 90 ? '#7f1d1d' : // Dark Red (Extreme)
               score > 80 ? '#991b1b' : // Red (High)
               score > 60 ? '#b91c1c' : // Light Red (Moderate)
               score > 40 ? '#dc2626' : // Crimson (Low-Moderate)
               score > 0  ? '#f87171' : '#1e293b';
    }

    function renderRiskRanking() {
        const list = document.getElementById('risk-ranking-list');
        if (!list) return;
        const sorted = Object.entries(RISK_SCORES).sort((a,b) => b[1] - a[1]);
        let html = '';
        sorted.forEach(([name, score]) => {
            const color = score > 80 ? '#ef4444' : (score > 50 ? '#fbbf24' : '#60a5fa');
            html += `
                <div class="kec-list-item">
                    <span style="font-weight: 500;">${name}</span>
                    <span style="color: ${color}; font-weight: 600;">#${score} Risk</span>
                </div>
            `;
        });
        list.innerHTML = html;
    }

    function loadMapData() {
        if (geoLayer) map.removeLayer(geoLayer);
        const file = currentMode === 'sirup' || currentMode === 'kemiskinan' || currentMode === 'infrastruktur' || currentMode === 'realisasi' || currentMode === 'audit' ? 'districts.geojson' : 'villages.geojson';
        districtLayers = {};
        villageLayers = {};

        return fetch(file)
            .then(r => r.json())
            .then(data => {
                geoLayer = L.geoJson(data, {
                    style: function(f) {
                        const name = (currentMode !== 'danadesa') ? f.properties.nm_kecamatan : f.properties.nm_kelurahan;
                        let color = '#1e293b';
                        if (currentMode === 'sirup') color = getSIRUPColor(name);
                        else if (currentMode === 'danadesa') color = getDDColor(name);
                        else if (currentMode === 'kemiskinan') color = getPovertyColor(name);
                        else if (currentMode === 'infrastruktur') color = '#1e293b'; // Neutral background for infra mode
                        else if (currentMode === 'realisasi') color = getRealisasiColor(name);
                        else if (currentMode === 'audit') color = getAuditColor(name);
                        
                        return {
                            fillColor: color,
                            weight: 1, opacity: 1, color: 'rgba(255,255,255,0.1)', 
                            fillOpacity: 0.6
                        };
                    },
                    onEachFeature: function(f, layer) {
                        const name = (currentMode !== 'danadesa') ? f.properties.nm_kecamatan : f.properties.nm_kelurahan;
                        const center = layer.getBounds().getCenter();
                        const lat = center.lat.toFixed(5);
                        const lng = center.lng.toFixed(5);
                        
                        if (currentMode !== 'danadesa') {
                            districtLayers[name] = layer;
                            
                            if (currentMode === 'sirup') {
                                const d = getActiveStats()[name] || { total_pagu: 0, high_risk: 0 };
                                const packets = allAudits.filter(p => p.kecamatan === name && p.tahun == activeYear);
                                let packetHtml = packets.length > 0 ? '<div style="margin-top:10px; max-height:200px; overflow-y:auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top:10px;">' : '';
                                packets.forEach(p => {
                                    let statusColor = '#94a3b8';
                                    const s = p.status ? p.status.toUpperCase() : 'TERCATAT';
                                    if (s.includes('SELESAI') || s.includes('COMPLETED')) statusColor = '#22c55e';
                                    else if (s.includes('PROCESS') || s.includes('PROSES') || s.includes('BERLANGSUNG')) statusColor = '#3b82f6';
                                    else if (s.includes('BATAL')) statusColor = '#ef4444';

                                    packetHtml += `<div style="margin-bottom:12px; font-size: 0.8rem;">
                                        <div style="display:flex; justify-content:space-between;">
                                            <b style="color: ${statusColor}; font-size:0.7rem;">${escapeHTML(s)}</b>
                                            <span style="opacity:0.6; font-size:0.7rem;">${formatPaguJS(p.pagu)}</span>
                                        </div>
                                        <div style="font-weight:600; line-height:1.2;">${escapeHTML(p.nama)}</div>
                                        <div style="font-size:0.65rem; opacity:0.5; margin: 2px 0;">🏢 ${escapeHTML(p.satker || 'Satuan Kerja Tidak Terdeteksi')}</div>
                                        <div style="font-size:0.75rem; opacity:0.8; font-style:italic;">"${escapeHTML(p.note || '')}"</div>
                                    </div>`;
                                });
                                if (packetHtml) packetHtml += '</div>';
                                layer.bindPopup(`<div class="info-box" style="width:250px;"><b style="font-size:1.1rem; color:var(--accent);">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Monitoring Realisasi T.A ${activeYear}</span><hr style="opacity:0.2; margin:8px 0;"><b>Total Realisasi:</b> <span style="color:var(--accent)">${formatPaguJS(d.total_pagu)}</span><br>Temuan Anomali: <span style="color:${d.high_risk > 0 ? '#ef4444':'#10b981'}">${d.high_risk}</span>${packetHtml}<hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(59,130,246,0.2); border-radius:8px; color:var(--accent); text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a><a href="#" onclick="showKecamatanVendors('${name}'); return false;" style="display:block; margin-top:8px; text-align:center; padding:8px; color:white; background:var(--accent); border-radius:8px; font-size:0.75rem; font-weight:600; text-decoration:none;">👤 Lihat Daftar Vendor ↗</a></div>`);
                            } else if (currentMode === 'audit') {
                                const kecPackets = allAudits.filter(p => p.kecamatan === name && p.tahun == 2025);
                                
                                // LOGIC 1: Pemecahan Satker (Same Satker + Zona Kritis 180M-200M)
                                const satkerClusters = {};
                                kecPackets.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerClusters[p.satker] = (satkerClusters[p.satker] || 0) + 1; } });
                                const maxSatkerSplit = Math.max(0, ...Object.values(satkerClusters));
                                const score1 = Math.min(100, maxSatkerSplit * 25);

                                // LOGIC 2: Pemecahan Vendor (Same Vendor + Zona Kritis 180M-200M)
                                const vendorClusters = {};
                                kecPackets.forEach(p => { if(p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorClusters[p.vendor] = (vendorClusters[p.vendor] || 0) + 1; } });
                                const maxVendorSplit = Math.max(0, ...Object.values(vendorClusters));
                                const score2 = Math.min(100, maxVendorSplit * 30);

                                const score3 = Math.min(100, (kecPackets.length > 5 ? 70 : 30));

                                layer.bindPopup(`<div class="info-box" style="width:260px; border-top: 4px solid #ef4444;">
                                    <b style="font-size:1.1rem; color:#ef4444;">Audit Kec. ${name}</b><br>
                                    <span style="font-size:0.7rem; opacity:0.5;">Analisis 3 Pilar T.A 2025</span>
                                    <hr style="opacity:0.2; margin:8px 0;">
                                    
                                    <div style="margin-bottom:12px;">
                                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:4px;">
                                            <span>1. Pemecahan Satker</span>
                                            <b style="color:#ef4444;">${score1}</b>
                                        </div>
                                        <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                                            <div style="width:${score1}%; height:100%; background:#ef4444;"></div>
                                        </div>
                                    </div>

                                    <div style="margin-bottom:12px;">
                                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:4px;">
                                            <span>2. Pemecahan Vendor</span>
                                            <b style="color:#ef4444;">${score2}</b>
                                        </div>
                                        <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                                            <div style="width:${score2}%; height:100%; background:#ef4444;"></div>
                                        </div>
                                    </div>

                                    <div style="margin-bottom:15px;">
                                        <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:4px;">
                                            <span>3. Monopoli Kelompok</span>
                                            <b style="color:#ef4444;">${score3}</b>
                                        </div>
                                        <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:2px; overflow:hidden;">
                                            <div style="width:${score3}%; height:100%; background:#ef4444;"></div>
                                        </div>
                                    </div>

                                    <a href="#" onclick="showKecamatanAuditDetails('${name}', 0); return false;" style="display:block; text-align:center; padding:10px; background:#ef4444; border-radius:8px; color:white; text-decoration:none; font-size:0.8rem; font-weight:bold; margin-bottom:8px;">🔍 Tunjukkan Bukti ↗</a>
                                    <div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div>
                                </div>`);
                            } else if (currentMode === 'kemiskinan') {
                                const p = povertyStats[name] || { count: 0, pkh: 0, bpnt: 0, road_pct: 75 };
                                layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#f59e0b;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">T.A 2024/2025 | Sumber: Dinsos/DTKS</span><hr style="opacity:0.2; margin:8px 0;"><b>Jumlah KPM Miskin:</b><br><span style="font-size:1.8rem; font-weight:600; color:#f59e0b;">${p.count.toLocaleString('id-ID')}</span><div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px;"><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM BPNT</div><div style="font-weight:bold;">${p.bpnt.toLocaleString('id-ID')}</div></div><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM PKH</div><div style="font-weight:bold;">${p.pkh.toLocaleString('id-ID')}</div></div></div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(245,158,11,0.2); border-radius:8px; color:#fbbf24; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                            } else if (currentMode === 'realisasi') {
                                const val = (statsJSON[2026] && statsJSON[2026][name]) ? statsJSON[2026][name].total_pagu : 0;
                                layer.bindPopup(`<div class="info-box" style="width:220px; border-top: 3px solid #8b5cf6;"><b style="font-size:1.1rem; color:#a78bfa;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Monitoring Realisasi T.A 2026</span><hr style="opacity:0.2; margin:8px 0;"><b>Total Realisasi:</b><br><span style="font-size:1.4rem; font-weight:600; color:#d8b4fe;">${formatPaguJS(val)}</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Berdasarkan agregat 1.400+ paket transaksi yang terpetakan di wilayah ini.</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(139,92,246,0.2); border-radius:8px; color:#d8b4fe; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                            } else {
                                const infraData = povertyStats[name] || { road_pct: 75 };
                                layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#06b6d4;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data 2024 | Sumber: DPUTR Majalengka</span><hr style="opacity:0.2; margin:8px 0;"><b>Level Kemantapan:</b><br><span style="font-size:1.8rem; font-weight:600; color:#06b6d4;">${infraData.road_pct}%</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Indeks berdasarkan integrasi SP4N-LAPOR! & Statistik Jalan Kabupaten.</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                            }
                        } else {
                            villageLayers[name] = layer;
                            const v = villageStats[name] || { budget: 0, risk: 0, kecamatan: 'Unknown' };
                            layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#10b981;">Desa ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Kecamatan ${v.kecamatan}</span><hr style="opacity:0.2; margin:8px 0;"><b>Alokasi Dana Desa T.A 2025:</b><br><span style="font-size:1.4rem; font-weight:600; color:#10b981;">${formatPaguJS(v.budget)}</span><br><div style="margin-top:10px; font-size:0.75rem; opacity:0.7; line-height:1.4;">Sumber: Alokasi TKD Kemenkeu RI T.A 2025</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(16,185,129,0.2); border-radius:8px; color:#34d399; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                        }

                        layer.on('mouseover', function() { this.setStyle({ fillOpacity: 0.9, weight: 2 }); });
                        layer.on('mouseout', function() { this.setStyle({ fillOpacity: 0.6, weight: this === activeLayer ? 4 : 1, color: this === activeLayer ? '#ffffff' : 'rgba(255,255,255,0.1)' }); });
                        layer.on('click', function() {
                            currentOpenPopupKec = name;
                            if (currentMode === 'realisasi') {
                                showKecamatanVendors(name);
                            }
                        });
                        layer.on('popupclose', function() {
                            if (!isRefreshingMap) currentOpenPopupKec = null;
                        });
                    }
                }).addTo(map);

                // Update Heatmap
                updateHeatmap();

                // Load Road Polylines IF in Infrastruktur Mode
                if (currentMode === 'infrastruktur') {
                    if (allRoadData) {
                        renderRoadLayer(allRoadData);
                    } else {
                        fetch('roads_desa.geojson')
                            .then(r => r.json())
                            .then(roadData => {
                                allRoadData = roadData;
                                renderRoadLayer(roadData);
                            });
                    }
                }
            });
    }

    function updateHeatmap() {
        if (heatLayer) map.removeLayer(heatLayer);
        return; // Disabled as per user request for Choropleth style instead
    }

    function filterRoads() {
        if (!allRoadData || !roadLayer) return;
        renderRoadLayer(allRoadData);
    }

    function renderRoadLayer(roadData) {
        if (roadLayer) map.removeLayer(roadLayer);

        const activeClasses = Array.from(document.querySelectorAll('.road-filter-item input:checked'))
            .map(cb => cb.getAttribute('data-class'));

        roadLayer = L.geoJson(roadData, {
            filter: function(f) {
                return activeClasses.includes(f.properties.classification || 'Jalan Desa');
            },
            style: function(f) {
                const cls = f.properties.classification;
                const status = f.properties.status;
                
                let color = '#94a3b8'; // Default Village
                let weight = 3;
                let dash = null;

                if (cls === 'Jalan Nasional') { color = '#facc15'; weight = 7; }
                else if (cls === 'Jalan Provinsi') { color = '#ec4899'; weight = 5; }
                else if (cls === 'Jalan Kabupaten') { color = '#3b82f6'; weight = 4; }

                if (status === 'Rusak' || status === 'Perbaikan') {
                    dash = '8, 10';
                    if (status === 'Rusak') color = '#ef4444'; // Keep red hint for damage within class
                }

                return { 
                    color: color, 
                    weight: weight, 
                    opacity: 1, 
                    dashArray: dash,
                    pane: 'roadPane',
                    lineCap: 'round'
                };
            },
            smoothFactor: 1.5,
            onEachFeature: function(f, layer) {
                const coords = layer.getLatLngs ? layer.getLatLngs() : [];
                let lat = 0, lng = 0;
                if (coords.length > 0) {
                    const mid = Array.isArray(coords[0]) ? coords[0][Math.floor(coords[0].length/2)] : coords[Math.floor(coords.length/2)];
                    if (mid) { lat = mid.lat.toFixed(5); lng = mid.lng.toFixed(5); }
                }
                const statusColor = f.properties.status === 'Rusak' ? '#ef4444' : (f.properties.status === 'Perbaikan' ? '#f59e0b' : '#22d3ee');
                layer.bindPopup(`<div class="info-box" style="width:230px;">
                    <div style="font-size:0.6rem; opacity:0.6; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">${escapeHTML(f.properties.classification || 'Jalan Lokal')}</div>
                    <b style="color:var(--accent); font-size:1rem;">${f.properties.name || 'Jalan Tanpa Nama'}</b><br>
                    <span style="font-size:0.75rem; opacity:0.6;">Klasifikasi: ${f.properties.highway}</span><hr style="opacity:0.2; margin:5px 0;">
                    Status: <b style="color:${statusColor}">${f.properties.status}</b><br>
                    <span style="font-size:0.7rem; opacity:0.5;">Data: OpenStreetMap 2024</span><hr style="opacity:0.1; margin:6px 0;">
                    <div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div>
                    <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a>
                </div>`);
            }
        }).addTo(map);
    }

    function renderRealisasiLayer() {
        if (realizationLayer) map.removeLayer(realizationLayer);
        realizationLayer = L.markerClusterGroup({
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true
        });
        
        realizationData.forEach(p => {
            if (!p.lat || !p.lng) return;

            let color = '#3b82f6';
            if (p.status === 'SELESAI') color = '#22c55e';
            else if (p.status === 'BERLANGSUNG') color = '#eab308';
            else if (p.status === 'PAYMENT OUTSIDE SYSTEM') color = '#6366f1';
            
            const marker = L.circleMarker([p.lat, p.lng], {
                radius: 8,
                fillColor: color,
                color: '#fff',
                weight: 1,
                opacity: 1,
                fillOpacity: 0.8
            });

            let statusColor = '#94a3b8';
            const s = p.status ? p.status.toUpperCase() : 'UNKNOWN';
            if (s.includes('SELESAI') || s.includes('COMPLETED')) statusColor = '#10b981';
            else if (s.includes('PROCESS') || s.includes('PROSES') || s.includes('BERLANGSUNG') || s.includes('MELAKUKAN')) statusColor = '#3b82f6';
            else if (s.includes('BATAL')) statusColor = '#ef4444';

            marker.bindPopup(`
                <div class="info-box" style="width:250px; border-top: 3px solid #8b5cf6;">
                    <div style="font-size:0.6rem; opacity:0.6; text-transform:uppercase; margin-bottom:5px;">REALISASI 2026 • ${escapeHTML(p.method)}</div>
                    <b style="color:#a78bfa; font-size:1rem;">${escapeHTML(p.paket)}</b><br>
                    <span style="font-size:0.8rem; opacity:0.8;">Satker: ${escapeHTML(p.satker)}</span><hr style="opacity:0.2; margin:8px 0;">
                    
                    <div style="margin-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:10px;">
                        <div style="font-size:0.7rem; color:${statusColor}; font-weight:700; text-transform:uppercase; margin-bottom:2px;">
                            ${s} <span style="float:right; color:#94a3b8; font-weight:400;">${formatPaguJS(p.nilai)}</span>
                        </div>
                        <div style="font-size:0.9rem; font-weight:600; line-height:1.3; margin-bottom:4px;">${escapeHTML(p.nama)}</div>
                        <div style="font-size:0.75rem; opacity:0.6;">${escapeHTML(p.vendor)}</div>
                    </div>
                    
                    <div style="background:rgba(255,255,255,0.03); padding:8px; border-radius:8px; font-size:0.8rem;">
                        👤 <b>Penyedia:</b><br>
                        <span style="color:#ddd;">${escapeHTML(p.vendor)}</span>
                    </div>

                    <a href="#" onclick="focusVendor('${p.vendor.replace(/'/g, "\\'")}'); return false;" style="display:block; margin-top:8px; text-align:center; padding:8px; background:rgba(139,92,246,0.1); border-radius:8px; color:#a78bfa; text-decoration:none; font-size:0.75rem; font-weight:600;">🕸️ Lihat Jaringan Penyedia ↗</a>
                </div>
            `);
            
            realizationLayer.addLayer(marker);
        });

        map.addLayer(realizationLayer);
        if (realizationLayer.getBounds().isValid()) {
            map.fitBounds(realizationLayer.getBounds(), { padding: [50, 50] });
        }
    }

    function resetRealisasiSidebar() {
        document.getElementById('realisasi-stats').style.display = 'block';
        document.getElementById('realisasi-detail-view').style.display = 'none';
        if (vendorSpiderLayer) map.removeLayer(vendorSpiderLayer);
    }

    function showVendorPackets(vName, kecName) {
        currentVendorName = vName;
        currentKecName = kecName;
        const detailView = document.getElementById('realisasi-detail-view');
        const projects = allAudits.filter(p => p.kecamatan === kecName && (p.vendor || 'Swakelola/Tidak Terdata') === vName && p.tahun == activeYear);
        
        // Escape vendor name for safety
        const safeVName = escapeHTML(vName);
        
        let html = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <div style="min-width:0;">
                    <h3 style="margin:0; font-size:0.9rem; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">📦 ${safeVName}</h3>
                    <div style="font-size:0.75rem; opacity:0.6;">Daftar Paket di ${kecName}</div>
                </div>
                <button onclick="showKecamatanVendors('${kecName.replace(/'/g, "\\'")}')" style="background:rgba(255,255,255,0.05); border:none; color:white; padding:5px 10px; border-radius:5px; cursor:pointer; font-size:0.7rem; flex-shrink:0;">← List Vendor</button>
            </div>
            <div class="packet-list" style="display:flex; flex-direction:column; gap:10px; max-height:600px; overflow-y:auto; padding-right:5px;">
        `;
        
        projects.forEach(p => {
            let statusColor = '#94a3b8';
            let statusBg = 'rgba(148, 163, 184, 0.1)';
            const s = p.status ? p.status.toUpperCase() : 'TERCATAT';
            if (s.includes('SELESAI') || s.includes('COMPLETED')) { statusColor = '#22c55e'; statusBg = 'rgba(34, 197, 94, 0.1)'; }
            else if (s.includes('PROCESS') || s.includes('PROSES') || s.includes('BERLANGSUNG')) { statusColor = '#3b82f6'; statusBg = 'rgba(59, 130, 246, 0.1)'; }
            else if (s.includes('BATAL')) { statusColor = '#ef4444'; statusBg = 'rgba(239, 68, 68, 0.1)'; }

            html += `
                <div class="packet-card" onclick="selectPackage('${p.id}', '${p.kecamatan}')" style="background:rgba(255,255,255,0.03); padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.05); cursor:pointer;">
                    <div style="font-size:0.75rem; font-weight:600; margin-bottom:2px; line-height:1.3;">${escapeHTML(p.nama)}</div>
                    <div style="font-size:0.65rem; opacity:0.5; margin-bottom:8px;">🏢 ${escapeHTML(p.satker || 'Satuan Kerja Tidak Terdeteksi')}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.65rem; padding:2px 6px; background:${statusBg}; color:${statusColor}; border-radius:4px; font-weight:600;">${s}</span>
                        <b style="font-size:0.85rem; color:#f8fafc;">${formatPaguJS(p.pagu)}</b>
                    </div>
                </div>
            `;
        });
        
        if (projects.length === 0) html += `<div style="text-align:center; padding:20px; opacity:0.5; font-size:0.8rem;">Tidak ada detail paket terdata.</div>`;
        
        html += '</div>';
        detailView.innerHTML = html;
    }

    function showKecamatanVendors(kecName) {
        currentKecName = kecName;
        currentVendorName = null; // Back to vendor list
        openSidebar();
        document.getElementById('realisasi-stats').style.display = 'none';
        const detailView = document.getElementById('realisasi-detail-view');
        detailView.style.display = 'block';
        
        // Filter and group by vendor for active year
        const projects = allAudits.filter(p => p.kecamatan === kecName && p.tahun == activeYear);
        const vendors = {};
        projects.forEach(p => {
            const vName = p.vendor || 'Swakelola/Tidak Terdata';
            if (!vendors[vName]) vendors[vName] = { count: 0, total: 0 };
            vendors[vName].count++;
            vendors[vName].total += (p.pagu || 0);
        });

        // Sort by total value
        const sortedVendors = Object.keys(vendors).sort((a,b) => vendors[b].total - vendors[a].total);

        let html = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="margin:0; font-size:1rem;">🏢 Vendor: ${kecName}</h3>
                <button onclick="resetRealisasiSidebar()" style="background:rgba(255,255,255,0.05); border:none; color:white; padding:5px 10px; border-radius:5px; cursor:pointer; font-size:0.7rem;">← Kembali</button>
            </div>
            <div style="font-size:0.75rem; opacity:0.6; margin-bottom:10px;">Menampilkan penyedia tertinggi di T.A ${activeYear}</div>
            <div class="vendor-list" style="display:flex; flex-direction:column; gap:8px;">
        `;
        
        sortedVendors.forEach(v => {
            const safeV = v.replace(/'/g, "\\'");
            const safeKec = kecName.replace(/'/g, "\\'");
            html += `
                <div class="vendor-card" onclick="showVendorPackets('${safeV}', '${safeKec}')" style="background:rgba(255,255,255,0.03); padding:12px; border-radius:10px; border:1px solid rgba(255,255,255,0.05); cursor:pointer;">
                    <div style="font-weight:600; color:var(--accent); font-size:0.85rem;">${escapeHTML(v)}</div>
                    <div style="display:flex; justify-content:space-between; margin-top:5px; font-size:0.8rem;">
                        <span style="opacity:0.6;">${vendors[v].count} Paket</span>
                        <b style="color:white;">${formatPaguJS(vendors[v].total)}</b>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        detailView.innerHTML = html;
        
        // Spider Mapping for the kecamatan if it's 2026 (or all data has lat/lng)
        renderVendorSpiderLayer(projects);
    }

    function renderVendorSpiderLayer(projects) {
        if (vendorSpiderLayer) map.removeLayer(vendorSpiderLayer);
        vendorSpiderLayer = L.layerGroup();
        
        projects.forEach(p => {
            if (p.lat && p.lng) {
                L.circleMarker([p.lat, p.lng], {
                    radius: 5,
                    fillColor: 'var(--accent)',
                    color: '#fff',
                    weight: 1,
                    fillOpacity: 0.8
                }).addTo(vendorSpiderLayer);
            }
        });
        
        vendorSpiderLayer.addTo(map);
    }

    function showVendorPackages(kecName, vendorName) {
        const detailView = document.getElementById('realisasi-detail-view');
        // Filter by kec, vendor AND activeYear
        const projects = allAudits.filter(p => p.kecamatan === kecName && p.vendor === vendorName && p.tahun == activeYear);
        const total = projects.reduce((sum, p) => sum + (p.pagu || 0), 0);

        let html = `
            <div class="back-btn" onclick="showKecamatanVendors('${kecName.replace(/'/g, "\\'")}')">← Kembali ke Daftar Vendor</div>
            <h2 style="font-size: 0.9rem; color: var(--accent); margin-bottom: 2px;">${vendorName}</h2>
            <div style="font-size: 0.75rem; opacity: 0.6; margin-bottom: 12px;">Aktif di Kec. ${kecName}</div>
            
            <div class="stat-card" style="padding: 1rem; border-left-color: var(--accent); margin-bottom: 1rem;">
                <div style="font-size: 0.7rem; opacity:0.6;">Total Volume di Kecamatan Ini</div>
                <div class="value" style="font-size: 1.2rem; color: white;">${formatPaguJS(total)}</div>
            </div>

            <div style="max-height: 450px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
        `;

        projects.forEach(p => {
            html += `
                <div class="package-item">
                    <div style="font-weight: 600; color: #eee; margin-bottom: 2px;">${p.nama}</div>
                    <div style="font-size: 0.75rem; opacity: 0.6; margin-bottom: 8px;">${p.satker}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size: 0.7rem; color: var(--accent);">${p.status || 'Selesai'}</span>
                        <b style="color:var(--accent);">${formatPaguJS(p.pagu)}</b>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        detailView.innerHTML = html;
        
        focusVendor(vendorName, false);
    }

    function focusVendor(vendorName, shouldZoom = true) {
        if (vendorSpiderLayer) map.removeLayer(vendorSpiderLayer);
        vendorSpiderLayer = L.layerGroup().addTo(map);

        const projects = allAudits.filter(p => p.vendor === vendorName && p.tahun == activeYear);
        if (projects.length === 0) return;

        let latSum = 0, lngSum = 0, count = 0;
        projects.forEach(p => { 
            if (p.lat && p.lng) {
                latSum += p.lat; 
                lngSum += p.lng; 
                count++;
                
                L.circleMarker([p.lat, p.lng], { 
                    radius: 6, 
                    color: 'var(--accent)', 
                    fillColor: 'var(--accent)',
                    fillOpacity: 0.6,
                    weight: 1
                }).addTo(vendorSpiderLayer);
            }
        });

        if (count > 0) {
            const center = [latSum / count, lngSum / count];
            projects.forEach(p => {
                if (p.lat && p.lng) {
                    L.polyline([center, [p.lat, p.lng]], {
                        color: 'var(--accent)',
                        weight: 2,
                        opacity: 0.4,
                        dashArray: '5, 8'
                    }).addTo(vendorSpiderLayer);
                }
            });

            if (shouldZoom) {
                const bounds = L.latLngBounds(projects.filter(p => p.lat && p.lng).map(p => [p.lat, p.lng]));
                map.flyToBounds(bounds, { padding: [80, 80], duration: 1.5 });
            }
        }
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
                    sessionStorage.setItem('gps_choice', 'granted');
                    document.getElementById('gpsOverlay').classList.add('hidden');
                    document.getElementById('gpsFloatBtn').style.display = 'none';
                }).catch(() => {
                    sessionStorage.setItem('gps_choice', 'granted');
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
        sessionStorage.setItem('gps_choice', 'skipped');
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

    // Unified Pointer Drag Logic (Mouse & Touch)
    let pointerStartX = 0;
    let isPointerDragging = false;
    let pointerDragType = null;
    const sidebarNode = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const pullInd = document.getElementById('pullIndicator');
    const maxWidth = 380;

    // Handle Edge/Tab Drag Open
    const startOpenHandler = e => {
        // Allow drag from pull-indicator or left edge
        const isFromEdge = e.clientX < 50;
        const isFromTab = e.target.closest('.pull-indicator');
        
        if ((isFromEdge || isFromTab) && !sidebarNode.classList.contains('active')) {
            pointerStartX = e.clientX;
            isPointerDragging = true;
            pointerDragType = 'open';
            sidebarNode.style.transition = 'none';
            overlay.style.transition = 'none';
            overlay.style.display = 'block';
            overlay.style.opacity = '0';
        }
    };

    document.addEventListener('pointerdown', startOpenHandler);

    // Handle Drag Close (on sidebar)
    sidebarNode.addEventListener('pointerdown', e => {
        if (sidebarNode.classList.contains('active')) {
            pointerStartX = e.clientX;
            isPointerDragging = true;
            pointerDragType = 'close';
            sidebarNode.style.transition = 'none';
            overlay.style.transition = 'none';
        }
    });

    document.addEventListener('pointermove', e => {
        if (!isPointerDragging) return;
        const currentX = e.clientX;
        const delta = currentX - pointerStartX;

        if (pointerDragType === 'open') {
            let move = Math.min(delta, maxWidth);
            if (move < 0) move = 0;
            sidebarNode.style.transform = `translateX(${move - maxWidth}px)`;
            overlay.style.opacity = (move / maxWidth) * 0.3;
        } else if (pointerDragType === 'close') {
            let move = Math.min(0, delta);
            sidebarNode.style.transform = `translateX(${move}px)`;
            overlay.style.opacity = (1 + move / maxWidth) * 0.3;
        }
    });

    document.addEventListener('pointerup', e => {
        if (!isPointerDragging) return;
        const delta = e.clientX - pointerStartX;
        
        isPointerDragging = false;
        sidebarNode.style.transition = '';
        overlay.style.transition = '';

        if (pointerDragType === 'open') {
            if (delta > 80) {
                openSidebar();
            } else {
                sidebarNode.style.transform = '';
                overlay.style.display = 'none';
                overlay.style.opacity = '';
            }
        } else if (pointerDragType === 'close') {
            if (delta < -80) {
                toggleSidebar(); // will handle display:none
            } else {
                sidebarNode.style.transform = '';
                overlay.style.opacity = '';
            }
        }
        pointerDragType = null;
    });

    // checkGPSChoice removed

    function toggleShare(show) {
        document.getElementById('shareModal').style.display = show ? 'flex' : 'none';
    }

    function toggleSawer(show) {
        document.getElementById('sawerModal').style.display = show ? 'flex' : 'none';
    }

    async function shareWeb() {
        const shareData = {
            title: 'Matadata Majalengka',
            text: 'Cek website Matadata Majalengka: Monitoring Pengadaan dan Dana Desa!',
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
        if(!leg) return;
        const isHidden = window.getComputedStyle(leg).display === 'none';
        leg.style.display = isHidden ? 'block' : 'none';
    }

</script>

<!-- FINAL MODALS (HARDCODED FOR STABILITY) -->
<div id="logicModal" class="modal" onclick="if(event.target == this) this.style.display='none'" style="display:none; position:fixed; inset:0; z-index: 999999; background:rgba(2,6,23,0.85); backdrop-filter:blur(15px); align-items:center; justify-content:center;">
     <div class="modal-content" style="max-width: 600px; background:#0f172a; padding:2.5rem; border-radius:28px; border:1px solid rgba(255,255,255,0.1); position:relative; box-shadow: 0 40px 100px rgba(0,0,0,0.8); animation: modalIn 0.3s ease;">
        <span class="close-modal" onclick="document.getElementById('logicModal').style.display='none'" style="position:absolute; top:20px; right:20px; cursor:pointer; font-size:1.5rem; opacity:0.5; color:white;">&times;</span>
        <h2 id="modal-title" style="color: var(--accent); margin-top: 0; font-family:'Outfit';">Transparansi Algoritma</h2>
        <p id="modal-subtitle" style="opacity: 0.6; font-size: 0.9rem; margin-bottom: 1.5rem;">Memuat detail algoritma...</p>
        <div id="modal-logic-body" class="logic-grid"></div>
        <h3 style="margin-top: 1.5rem; font-size: 1rem; color:white;">Metadata & Sumber Data</h3>
        <ul id="modal-sources" style="opacity: 0.8; font-size: 0.85rem; line-height: 1.6; color:white;"></ul>
    </div>
</div>

<div id="auditDisclaimer" class="modal" style="display:none !important; position:fixed !important; inset:0 !important; z-index: 999999 !important; background:rgba(2,6,23,0.96) !important; align-items: center !important; justify-content: center !important; backdrop-filter:blur(15px) !important;">
    <div class="modal-content" style="max-width: 600px; border: 1px solid rgba(239, 68, 68, 0.4); background: #0f172a; position: relative; padding: 3rem; border-radius: 32px; box-shadow: 0 50px 150px rgba(0,0,0,0.9); animation: modalIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="text-align:center; margin-bottom:25px;">
            <div style="width:70px; height:70px; background:rgba(239,68,68,0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                <span style="font-size:2.5rem;">⚖️</span>
            </div>
            <h2 style="color:#ef4444; margin:0; font-family: 'Outfit'; font-weight:800; letter-spacing:-0.02em;">Transparansi & Metodologi Audit AI</h2>
            <p style="opacity:0.5; font-size:0.85rem; margin-top:5px;">Mohon baca briefing ini sebelum melanjutkan</p>
        </div>

        <div style="font-size:0.85rem; line-height:1.7; color:white; height: 320px; overflow-y:auto; padding-right:15px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom:25px; scrollbar-width: thin;">
            <h3 style="color:#60a5fa; font-size:1rem; margin-bottom:10px;">🛡️ Bagaimana AI Bekerja (3 Pilar Audit)</h3>
            <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:16px; margin-bottom:15px; border-left:4px solid #3b82f6;">
                <p><b>1. Satker Clustering:</b> AI memindai penumpukan paket non-tender dengan nominal tepat di bawah 200 Juta pada satu Satuan Kerja yang sama secara berulang.</p>
                <p style="margin-top:8px;"><b>2. Vendor Splitting:</b> Mendeteksi pola di mana beberapa paket pengerjaan yang mirip ("Satu Proyek") diberikan kepada satu vendor tunggal lewat mekanisme non-tender.</p>
                <p style="margin-top:8px;"><b>3. Global Monopoly:</b> Menandai vendor yang menguasai ekosistem pengadaan di berbagai wilayah kecamatan secara dominan.</p>
            </div>

            <h3 style="color:#f87171; font-size:1rem; margin-bottom:10px;">⚠️ Penting: Desklaimer & Akurasi</h3>
            <ul style="padding-left:20px; color:rgba(255,255,255,0.8);">
                <li><b>Kesalahan AI:</b> Algoritma AI bekerja berdasarkan pola statistik. Terdapat kemungkinan <i>False Positive</i> jika data sumber (SIRUP/Inaproc) tidak lengkap atau mengalami anomali input.</li>
                <li><b>Data Indikatif:</b> Hasil analisis ini bukan merupakan putusan hukum final, melainkan data pembanding untuk pengawasan internal.</li>
                <li><b>Tanggung Jawab:</b> Pengguna bertanggung jawab penuh atas interpretasi dan penggunaan data ini. Platform tidak bertanggung jawab atas tindakan hukum dari pihak ketiga.</li>
            </ul>
            
            <p style="margin-top:15px; color:#fca5a5; font-style:italic; background:rgba(239,68,68,0.05); padding:10px; border-radius:8px;">Pemberitahuan: Sistem mencatat akses audit ini demi keamanan dan akuntabilitas data publik.</p>
        </div>

        <div style="display:flex; gap:12px;">
            <button onclick="switchMode('sirup'); document.getElementById('auditDisclaimer').style.display='none';" style="flex:1; padding:15px; background:rgba(255,255,255,0.05); color:white; border:none; border-radius:14px; font-weight:600; cursor:pointer; font-family: 'Outfit';">Batal</button>
            <button onclick="closeAuditDisclaimer()" style="flex:2; padding:15px; background:#ef4444; color:white; border:none; border-radius:14px; font-weight:800; cursor:pointer; font-family: 'Outfit'; font-size:1rem; transition: 0.3s; box-shadow: 0 10px 30px rgba(239,68,68,0.4);">SAYA MENGERTI & SETUJU</button>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<a href="visitors.php" class="admin-badge-top" style="position:fixed; top:15px; left:50%; transform:translateX(-50%); z-index:9000; padding:6px 16px; background:rgba(139,92,246,0.2); border:1px solid rgba(139,92,246,0.3); border-radius:10px; color:#a78bfa; text-decoration:none; font-family:'Outfit'; font-size:0.75rem; font-weight:600; backdrop-filter:blur(8px);">👁️ Admin: Visitor Log</a>
<?php endif; ?>

</body>
</html>
