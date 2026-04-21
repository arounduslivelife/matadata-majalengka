<?php
$db = new SQLite3('database.sqlite');

// Get audit stats per kecamatan
function formatPagu($p) {
    if (!$p || $p === 0) return "Rp 0";
    if ($p >= 1000000000) return "Rp" . number_format($p/1000000000, 1) . " Miliar";
    if ($p >= 1000000) return "Rp" . number_format($p/1000000, 0) . " Juta";
    return "Rp" . number_format($p, 0, ',', '.');
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
            background: var(--primary);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 4px 0 20px rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
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
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.5);
            z-index: 1500;
            transition: 0.3s;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .info-btn:hover { transform: scale(1.1); background: #2563eb; }

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

        /* Progress Bar */
        #progress-overlay {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            display: none;
            width: 350px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .progress-text {
            font-size: 0.8rem;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
        .progress-bar-container {
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            width: 0%;
            transition: width 0.5s ease;
            position: relative;
        }
        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background-image: linear-gradient(-45deg, rgba(255,255,255,0.2) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.2) 75%, transparent 75%, transparent);
            background-size: 50px 50px;
            animation: move-stripes 2s linear infinite;
        }
        @keyframes move-stripes {
            0% { background-position: 0 0; }
            100% { background-position: 50px 0; }
        }

        /* Search Box */
        .search-container {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 300px;
            z-index: 5000;
        }
        @media (max-width: 768px) {
            .search-container { left: unset; right: 20px; top: 20px; width: 220px; }
        }
        #searchInput {
            width: 100%;
            padding: 12px 18px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            color: white;
            font-family: 'Outfit';
            font-size: 0.9rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
            outline: none;
            transition: 0.3s;
        }
        #searchInput:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3); }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: var(--primary);
            border-radius: 10px;
            margin-top: 8px;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .search-results.show { display: block; }
        .result-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: 0.2s;
        }
        .result-item:hover { background: rgba(255,255,255,0.05); }
        .result-item .type { font-size: 0.7rem; color: var(--accent); font-weight: bold; margin-bottom: 3px; }
        .result-item .name { font-size: 0.85rem; }

        /* Mode Switcher - Bottom Right */
        .mode-switcher {
            position: fixed;
            bottom: 100px;
            right: 30px;
            z-index: 6000;
            display: flex;
            flex-direction: column;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            padding: 5px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            gap: 5px;
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
    </style>
</head>
<body>

<!-- Hamburger Menu (Mobile Only) -->
<div class="hamburger" onclick="toggleSidebar()">☰</div>

<!-- Info Button -->
<div class="info-btn" onclick="toggleModal()" style="z-index: 9999; border: 2px solid white;">?</div>

<div class="sidebar" id="sidebar">
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

        <div style="font-size: 0.8rem; opacity: 0.5; margin-top: 1.5rem; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
            <div>📅 <b>Tahun Anggaran:</b> 2025</div>
            <div>🕒 <b>Data Diambil:</b> 19 April 2026</div>
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Temuan Terbesar</h3>
            <div class="packet-list">
                <?php 
                // We reused high_risk_packets from before
                $high_risk_packets->reset(); 
                while($p = $high_risk_packets->fetchArray(SQLITE3_ASSOC)): ?>
                    <div class="packet-item" onclick="selectPackage('<?= $p['id'] ?>', '<?= $p['kecamatan'] ?>')">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span class="tag"><?= $p['risk_score'] ?></span>
                            <b style="color: var(--accent)"><?= formatPagu($p['pagu']) ?></b>
                        </div>
                        <div><?= $p['nama_paket'] ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.5; margin-top: 5px;"><?= $p['satker'] ?></div>
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

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Ranking Alokasi Kecamatan</h3>
            <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
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

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Kecamatan Terpadat (KPM)</h3>
            <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
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
            <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 5px;">Kondisi Baik & Sedang</div>
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Kecamatan (Jalan Desa Rusak)</h3>
            <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
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
</div>

<div id="map">
    <!-- Progress Indicator -->
    <div id="progress-overlay">
        <div class="progress-text">
            <span>💡 AI Sedang Mengaudit...</span>
            <span id="progress-percent">0%</span>
        </div>
        <div class="progress-bar-container">
            <div id="progress-fill" class="progress-bar-fill"></div>
        </div>
        <div id="progress-kecamatan" style="font-size: 0.65rem; opacity: 0.6; margin-top: 5px; font-style: italic;">Memproses: -</div>
    </div>

    <!-- Mode Switcher -->
    <div class="mode-switcher">
        <div class="mode-btn active" id="btn-sirup" onclick="switchMode('sirup')">
            <span>🛡️ Audit Pengadaan</span>
        </div>
        <div class="mode-btn" id="btn-danadesa" onclick="switchMode('danadesa')">
            <span>🌾 Dana Desa</span>
        </div>
        <div class="mode-btn" id="btn-kemiskinan" onclick="switchMode('kemiskinan')">
            <span>🏘️ Kemiskinan</span>
        </div>
        <div class="mode-btn" id="btn-infrastruktur" onclick="switchMode('infrastruktur')">
            <span>🛣️ Infrastruktur</span>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" id="searchInput" placeholder="Cari..." oninput="handleSearch()">
        <div id="searchResults" class="search-results"></div>
    </div>
    <div class="info-btn" onclick="toggleModal()" title="Transparansi Algoritma">?</div>
</div>

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
    function switchMode(mode) {
        if (mode === currentMode) return;
        currentMode = mode;

        // Update UI
        document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active', 'green'));
        const activeBtn = document.getElementById(`btn-${mode}`);
        activeBtn.classList.add('active');
        if (mode === 'danadesa') activeBtn.classList.add('green');

        // Update Theme
        let accentColor = '#3b82f6';
        if (mode === 'danadesa') accentColor = '#10b981';
        if (mode === 'kemiskinan') accentColor = '#f59e0b';
        if (mode === 'infrastruktur') accentColor = '#06b6d4';
        document.documentElement.style.setProperty('--accent', accentColor);

        // Update Sidebar
        document.getElementById('sidebar-sirup').style.display = mode === 'sirup' ? 'block' : 'none';
        document.getElementById('sidebar-danadesa').style.display = mode === 'danadesa' ? 'block' : 'none';
        document.getElementById('sidebar-kemiskinan').style.display = mode === 'kemiskinan' ? 'block' : 'none';
        document.getElementById('sidebar-infrastruktur').style.display = mode === 'infrastruktur' ? 'block' : 'none';
        
        document.getElementById('sidebar-title').innerText = 
            mode === 'sirup' ? 'MATADATA MAJALENGKA' : 
            (mode === 'danadesa' ? 'TRANSPARANSI DESA' : 
            (mode === 'kemiskinan' ? 'AUDIT KEMISKINAN' : 'AUDIT INFRASTRUKTUR'));
        
        document.getElementById('sidebar-subtitle').innerText = 
            mode === 'sirup' ? 'Operasi Ratu Boko • AI Audit Pengadaan' : 
            (mode === 'danadesa' ? 'Alokasi Alur Dana Desa 2025' : 
            (mode === 'kemiskinan' ? 'Profil KPM Bansos Per Kecamatan' : 'Kondisi & Anggaran Jalan Desa'));

        // Update Placeholder
        document.getElementById('searchInput').placeholder = mode === 'sirup' ? 'Cari Kecamatan atau Paket...' : (mode === 'danadesa' ? 'Cari Desa...' : 'Cari Kecamatan...');

        // Update Modal Content
        updateModalContent(mode);

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

    function selectPackage(id, kecamatanName) {
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
    map.getPane('roadPane').style.pointerEvents = 'none'; // Allow clicking polygons through lines

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
                                    packetHtml += `<div style="margin-bottom:10px; font-size: 0.8rem;"><div style="display:flex; justify-content:space-between;"><b style="color: ${color}">${p.risk}</b><span style="opacity:0.6;">${formatPaguJS(p.pagu)}</span></div><div style="font-weight:600;">${p.nama}</div><div style="font-size:0.75rem; opacity:0.8; font-style:italic;">"${p.note}"</div></div>`;
                                });
                                if (packetHtml) packetHtml += '</div>';

                                layer.bindPopup(`<div class="info-box" style="width:250px;"><b style="font-size:1.1rem; color:#3b82f6;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Sumber: SiRUP LKPP T.A 2025</span><hr style="opacity:0.2; margin:8px 0;"><b>Anggaran Audit:</b> <span style="color:var(--accent)">${formatPaguJS(d.total_pagu)}</span><br>Temuan High Risk: <span style="color:${d.high_risk > 0 ? '#ef4444':'#10b981'}">${d.high_risk}</span>${packetHtml}</div>`);
                            } else {
                                // Kemiskinan Popup
                                const p = povertyStats[name] || { count: 0, pkh: 0, bpnt: 0, road_pct: 75 };
                                if (currentMode === 'kemiskinan') {
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#f59e0b;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">T.A 2024/2025 | Sumber: Dinsos/DTKS</span><hr style="opacity:0.2; margin:8px 0;"><b>Jumlah KPM Miskin:</b><br><span style="font-size:1.8rem; font-weight:600; color:#f59e0b;">${p.count.toLocaleString('id-ID')}</span><div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px;"><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM BPNT</div><div style="font-weight:bold;">${p.bpnt.toLocaleString('id-ID')}</div></div><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM PKH</div><div style="font-weight:bold;">${p.pkh.toLocaleString('id-ID')}</div></div></div></div>`);
                                } else {
                                    // Infrastruktur Heatmap Popup
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#06b6d4;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data 2024 | Sumber: DPUTR Majalengka</span><hr style="opacity:0.2; margin:8px 0;"><b>Level Kemantapan:</b><br><span style="font-size:1.8rem; font-weight:600; color:#06b6d4;">${p.road_pct}%</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Indeks berdasarkan integrasi SP4N-LAPOR! & Statistik Jalan Kabupaten.</div></div>`);
                                }
                            }
                        } else {
                            villageLayers[name] = layer;
                            const v = villageStats[name] || { budget: 0, risk: 0, kecamatan: 'Unknown' };
                            layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#10b981;">Desa ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Kecamatan ${v.kecamatan}</span><hr style="opacity:0.2; margin:8px 0;"><b>Alokasi Dana Desa T.A 2025:</b><br><span style="font-size:1.4rem; font-weight:600; color:#10b981;">${formatPaguJS(v.budget)}</span><br><div style="margin-top:10px; font-size:0.75rem; opacity:0.7; line-height:1.4;">Sumber: Alokasi TKD Kemenkeu RI T.A 2025</div></div>`);
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
                                        weight: 2, 
                                        opacity: 0.8, 
                                        pane: 'roadPane',
                                        smoothFactor: 1.5 // Added for performance with 14k+ segments
                                    };
                                },
                                onEachFeature: function(f, roadLayer) {
                                    roadLayer.bindPopup(`<div class="info-box" style="width:200px;"><b style="color:var(--accent)">${f.properties.name}</b><br><span style="font-size:0.75rem; opacity:0.6;">Klasifikasi: ${f.properties.highway}</span><hr style="opacity:0.2; margin:5px 0;">Status Audit: <b style="color:${f.properties.status === 'Rusak' ? '#ef4444' : '#22d3ee'}">${f.properties.status}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data: OpenStreetMap 2024</span><br><span style="font-size:0.7rem; opacity:0.5;">Audit: Matadata AI Engine</span></div>`);
                                }
                            }).addTo(map);
                        });
                }
            });
    }

    window.onload = () => {
        updateModalContent(currentMode);
        loadMapData();
    };
</script>

</body>
</html>
