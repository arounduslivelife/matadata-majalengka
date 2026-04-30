<?php
// SECURITY HARDENING HEADERS (PENTEST COMPLIANT)
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' unpkg.com fonts.googleapis.com; style-src 'self' 'unsafe-inline' unpkg.com fonts.googleapis.com; img-src 'self' data: https: *; font-src 'self' fonts.gstatic.com; connect-src 'self' https:;");

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

// Load PAD Data
$pad_kecamatan_json = file_exists('data/pad_majalengka_kecamatan.json') ? file_get_contents('data/pad_majalengka_kecamatan.json') : '{}';
$pad_global_json = file_exists('data/pad_majalengka.json') ? file_get_contents('data/pad_majalengka.json') : '{}';
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- Pull Indicator Tab -->
<div class="pull-indicator" id="pullIndicator" onclick="toggleSidebar()">
    <span class="text">MENU</span>
    <span class="icon">›</span>
</div>

<div class="share-modal" id="shareModal" onclick="if(event.target == this) toggleShare(false)">
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
<div class="sawer-modal" id="sawerModal" onclick="if(event.target == this) toggleSawer(false)">
    <div class="sawer-content">
        <div style="font-size: 2.5rem; margin-bottom: 1rem;">☕</div>
        <img src="qrsumbangan.jpeg" alt="QR Sumbangan">
        <h2>Dukungan Kopi</h2>
        <p>Bantu saya untuk mengembangkan project ini, untuk Majalengka yang lebih transparan</p>
        <button class="close-sawer" onclick="toggleSawer(false)">Tutup</button>
    </div>
</div>

<?php include 'includes/sidebar.php'; ?>

<div id="map" style="height: 100vh !important; width: 100vw !important; background: #020617; position: absolute; inset: 0; z-index: 1;"></div>

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
window.APP_DATA = {
    stats: <?= json_encode($stats) ?>,
    year_totals: <?= json_encode($year_totals) ?>,
    all_audits: <?= json_encode($all_audits) ?>,
    village_stats: <?= json_encode($village_stats) ?>,
    poverty_stats: <?= json_encode($poverty_stats) ?>,
    pad_kecamatan: <?= !empty($pad_kecamatan_json) ? $pad_kecamatan_json : '{}' ?>,
    pad_global: <?= !empty($pad_global_json) ? $pad_global_json : '{}' ?>,
    gps_granted: true
};
</script>
<script src="assets/js/main.js"></script>
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
