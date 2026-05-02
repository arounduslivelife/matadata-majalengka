console.log("🚀 main.js is starting...");
function openSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const pullInd = document.getElementById('pullIndicator');
    if (!sidebar.classList.contains('active')) {
        sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
        if (pullInd) pullInd.style.display = 'none';
        // Lock map saat sidebar terbuka
        if (typeof map !== 'undefined' && map) {
            map.dragging.disable();
            map.scrollWheelZoom.disable();
            map.doubleClickZoom.disable();
        }
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
    // Unlock map saat sidebar tertutup
    if (typeof map !== 'undefined' && map) {
        map.dragging.enable();
        map.scrollWheelZoom.enable();
        map.doubleClickZoom.enable();
    }
}

window.toggleSidebar = function () {
    const sidebar = document.getElementById('sidebar');
    if (sidebar.classList.contains('active')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

window.toggleLayerPopover = function () {
    document.getElementById('layerPopover').classList.toggle('active');
}

window.selectModeFromDock = function (mode) {
    switchMode(mode);

    // Update active class in popover items
    document.querySelectorAll('.layer-item').forEach(item => {
        item.classList.toggle('active', item.innerText.toLowerCase().includes(mode.substring(0, 3)));
    });

    // Close popover
    document.getElementById('layerPopover').classList.remove('active');
}

// Close popover when clicking outside
document.addEventListener('click', function (e) {
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
            { b: 'Pilar 1: Pelanggaran Ambang Batas', p: 'Landasan: Perpres 46/2025 Pasal 38 (Konstruksi 400jt) & Pasal 41 (Konsultansi 100jt).' },
            { b: 'Pilar 2: Vendor Dominance', p: 'Landasan: Peraturan LKPP No. 12/2021 - Pengawasan terhadap indikasi kolusi pada paket Non-Tender.' },
            { b: 'Pilar 3: Monopoli Global', p: 'Landasan: UU No. 5 Tahun 1999 - Larangan praktik monopoli dan persaingan usaha tidak sehat.' },
            { b: 'Metodologi: Cluster Analysis', p: 'Logika matematis yang menghitung kepadatan paket (Density) pada satu titik koordinat dan entitas Satker yang sama.' }
        ],
        sources: [
            'Data SIRUP T.A 2025 (Rencana Umum Pengadaan)',
            'Portal Inaproc (Data Agregasi Pengadaan Nasional)',
            'Peraturan Presiden No. 12 Tahun 2021 (Regulasi Acuan)'
        ]
    },
    'pad': {
        title: 'Analisis Pendapatan Daerah',
        subtitle: 'Visualisasi kontribusi fiskal per wilayah terhadap PAD Kabupaten.',
        logic: [
            { b: 'WHAT: Revenue Contribution Mapping', p: 'Memetakan kecamatan berdasarkan volume setoran pajak dan bagi hasil daerah.' },
            { b: 'WHO: Bapenda Majalengka', p: 'Data bersumber dari Badan Pendapatan Daerah melalui portal Open Data.' },
            { b: 'WHY: Analisis Potensi Ekonomi', p: 'Mengidentifikasi pusat pertumbuhan ekonomi dan wilayah dengan potensi pajak tinggi.' },
            { b: 'WHERE: 26 Kecamatan Majalengka', p: 'Data agregat level kecamatan mencakup BPHTB, Hotel, dan Bagi Hasil.' },
            { b: 'WHEN: Historis 2018-2025', p: 'Menampilkan tren pertumbuhan pendapatan asli daerah secara tahunan.' }
        ],
        sources: [
            'Open Data Kabupaten Majalengka',
            'Badan Pendapatan Daerah (Bapenda)',
            'DPMD Majalengka (Data Bagi Hasil)'
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

// --- BREADCRUMB & ANOMALY RADAR ---

window.updateBreadcrumb = function (kec, packageId) {
    // Hide/Show Search based on depth
    const search = document.querySelector('.search-container');
    if (search) {
        search.style.transform = packageId ? 'translateY(-100px)' : 'translateY(0)';
        search.style.opacity = packageId ? '0' : '1';
    }
};

window.resetMapView = function () {
    if (map) {
        map.setView([-6.837, 108.227], 11);
        if (activeLayer) {
            resetHighlight(activeLayer);
            activeLayer = null;
        }
        map.closePopup();
    }
    updateBreadcrumb();
};

window.renderAnomalyRadar = function () {
    const list = document.getElementById('anomalyList');
    if (!list) return;

    // Filter high risk packages from allAudits
    // Score high if pagu is close to 200jt and status is non-tender (implied by pagu range)
    const anomalies = allAudits
        .filter(p => p.tahun == activeYear && p.pagu >= 180000000 && p.pagu < 200000000)
        .sort((a, b) => b.pagu - a.pagu)
        .slice(0, 5);

    if (anomalies.length === 0) {
        list.innerHTML = `<div style="font-size: 0.75rem; opacity: 0.5; text-align: center; padding: 20px;">Tidak ada anomali terdeteksi di T.A ${activeYear}</div>`;
        return;
    }

    let html = '';
    anomalies.forEach(p => {
        html += `
                <div class="anomaly-item" onclick="selectPackage('${p.id}', '${p.kecamatan.replace(/'/g, "\\'")}')">
                    <div class="anomaly-header">
                        <span class="anomaly-tag">Risk: High</span>
                        <span class="anomaly-pagu">${formatPaguJS(p.pagu)}</span>
                    </div>
                    <div class="anomaly-title">${escapeHTML(p.nama)}</div>
                    <div class="anomaly-meta">
                        📍 ${p.kecamatan} | <span style="cursor:pointer; color:#fbbf24;" onclick="event.stopPropagation(); showVendorIntelligence('${escapeHTML(p.vendor || 'Penyedia').replace(/'/g, "\\'")}')">🏗️ ${escapeHTML(p.vendor || 'Penyedia').substring(0, 20)}...</span>
                    </div>
                </div>
            `;
    });
    list.innerHTML = html;
};
// ----------------------------------

window.toggleModal = function () {
    const modal = document.getElementById('logicModal');
    if (currentMode === 'audit' && window.updateModalContent) {
        updateModalContent('audit');
    }
    modal.style.display = 'flex';
}


const statsJSON = window.APP_DATA.stats;
const yearTotalsJSON = window.APP_DATA.year_totals;
let allAudits = []; // Local reference
let isDataLoaded = false;

async function fetchAudits() {
    try {
        console.log("📥 Fetching audits from get_audits.php...");
        const response = await fetch('get_audits.php');
        const data = await response.json();

        // Update global and local references
        window.APP_DATA.all_audits = data;
        allAudits = data;
        isDataLoaded = true;

        console.log("✅ Data loaded:", data.length, "rows.");

        // Hide indicator
        const indicator = document.getElementById('dataLoadingIndicator');
        if (indicator) indicator.style.display = 'none';

        // Refresh Current View
        switchYear(activeYear);
    } catch (err) {
        console.error("❌ Failed to fetch audits:", err);
        const indicator = document.getElementById('dataLoadingIndicator');
        if (indicator) indicator.innerHTML = "❌ Gagal memuat data audit.";
    }
}

const villageStats = window.APP_DATA.village_stats;
const povertyStats = window.APP_DATA.poverty_stats;
const padKecStats = window.APP_DATA.pad_kecamatan;
const padGlobalStats = window.APP_DATA.pad_global;

// Start fetching immediately
fetchAudits();

let currentMode = 'sirup';
let activeYear = 2025;
let bridgeLayer = null;
let allBridgeData = null;

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
    document.querySelectorAll('#global-year-toggle .year-btn').forEach(btn => {
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
        const yearColors = {
            2022: '#10b981', // Emerald
            2023: '#f59e0b', // Amber
            2024: '#ec4899', // Pink
            2025: '#3b82f6', // Blue
            2026: '#a78bfa'  // Purple
        };
        document.documentElement.style.setProperty('--accent', yearColors[year] || '#3b82f6');

        // Sync drill-down view if open
        if (document.getElementById('realisasi-detail-view').style.display === 'block') {
            if (currentVendorName) {
                showVendorPackets(currentVendorName, currentKecName);
            } else if (currentKecName) {
                showKecamatanVendors(currentKecName);
            }
        }
    }
    if (currentMode === 'pad') {
        switchPadYear(year);
    }
    if (heatLayer) updateHeatmap();
    renderAnomalyRadar();
}

// Initial load switch to 2025
setTimeout(() => {
    switchYear(2025);
}, 100);

function renderUnifiedPacketList() {
    const list = document.getElementById('unified-packet-list');
    if (!list) return;

    const matches = allAudits.filter(p => p.tahun == activeYear).sort((a, b) => b.pagu - a.pagu).slice(0, 10);

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
    } else if (mode === 'pad') {
        html = `
                <div class="legend-title">Kontribusi PAD (IDR)</div>
                <div class="legend-item"><div class="legend-color" style="background:#831843"></div><span>Sangat Tinggi</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#be185d"></div><span>Tinggi</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#db2777"></div><span>Sedang</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#f472b6"></div><span>Rendah</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#fce7f3"></div><span>Minimal</span></div>
            `;
    }
    legend.innerHTML = html + '<div class="close-legend" onclick="toggleLegend()">&times;</div>';
}

// Mode Switcher Logic
window.switchMode = function (mode) {
    // Force Transparency Briefing for Audit Mode
    if (mode === 'audit') {
        const disc = document.getElementById('auditDisclaimer');
        if (disc) disc.style.display = 'flex';
    }

    // Redirect legacy realisasi to unified
    if (mode === 'realisasi') {
        switchYear(2026);
        mode = 'sirup';
    } else if (mode === 'pad') {
        const padYear = padKecStats[activeYear] ? activeYear : 2022;
        switchPadYear(padYear);
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
            (mode === 'pad' && label.includes('pad')) ||
            (mode === 'audit' && label.includes('audit'))
        );
    });

    // Update Theme
    let accentColor = '#3b82f6';
    if (mode === 'sirup') accentColor = activeYear === 2026 ? '#a78bfa' : '#3b82f6';
    if (mode === 'danadesa') accentColor = '#10b981';
    if (mode === 'kemiskinan') accentColor = '#f59e0b';
    if (mode === 'infrastruktur') accentColor = '#06b6d4';
    if (mode === 'pad') accentColor = '#ec4899';
    if (mode === 'audit') accentColor = '#ef4444';
    document.documentElement.style.setProperty('--accent', accentColor);

    // Update Sidebar
    const sections = ['sirup', 'danadesa', 'kemiskinan', 'infrastruktur', 'pad', 'audit'];
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
                    (mode === 'infrastruktur' ? 'INFRASTRUKTUR JALAN' :
                        (mode === 'pad' ? 'KONTRIBUSI PAD' : 'AUDIT INTELLIGENCE'))));
    }
    if (subtitleEl) {
        subtitleEl.innerText = mode === 'sirup' ? `Laporan Hasil Belanja T.A ${activeYear}` :
            (mode === 'danadesa' ? 'Alokasi Alur Dana Desa 2025' :
                (mode === 'kemiskinan' ? 'Profil KPM Bansos Per Kecamatan' :
                    (mode === 'infrastruktur' ? 'Kondisi & Anggaran Jalan Desa' :
                        (mode === 'pad' ? 'Analisis Kontribusi Fiskal Wilayah' : 'Analisis Pola Pengadaan Mencurigakan'))));
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
        renderAnomalyRadar();
    }
    if (mode === 'audit') {
        renderRiskRanking();
        updateGlobalAuditFindings();
        renderAnomalyRadar();
    }
}

window.closeAuditDisclaimer = function () {
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

    if (currentMode === 'sirup' || currentMode === 'audit') {
        const matchDistricts = Object.keys(getActiveStats()).filter(name => name.toLowerCase().includes(query));
        matchDistricts.forEach(d => {
            html += `<div class="result-item" onclick="selectDistrict('${d}')">
                            <div class="type">KECAMATAN</div>
                            <div class="name">${d}</div>
                         </div>`;
        });
        // CROSS-YEAR SEARCH: Search all years in allAudits (Package Name or Vendor Name)
        if (!allAudits || !Array.isArray(allAudits)) return;

        const matchPackages = allAudits.filter(p => {
            if (!p) return false;
            const name = p.nama || "";
            const vendor = p.vendor || "";
            const satker = p.satker || "";
            return name.toLowerCase().includes(query) ||
                vendor.toLowerCase().includes(query) ||
                satker.toLowerCase().includes(query);
        }).slice(0, 10);

        matchPackages.forEach(p => {
            const yearColor = p.tahun == 2026 ? '#a78bfa' : '#3b82f6';
            html += `<div class="result-item" onclick="selectPackageFromSearch('${p.id}', '${p.kecamatan}', ${p.tahun})">
                            <div class="type" style="display:flex; justify-content:space-between; align-items:center;">
                                <span>PAKET</span>
                                <span style="background:${yearColor}; color:white; padding:1px 6px; border-radius:4px; font-size:0.6rem; font-weight:800;">${p.tahun}</span>
                            </div>
                            <div class="name">${escapeHTML(p.nama)}</div>
                            <div style="font-weight: 800; color: var(--accent); font-size: 0.8rem; margin: 2px 0;">${formatPaguJS(p.pagu)}</div>
                            <div style="font-size: 0.7rem; color: #fbbf24; font-weight: 600; margin-bottom: 4px;">🏗️ ${escapeHTML(p.vendor || 'Penyedia')}</div>
                            <div style="font-size:0.6rem; opacity:0.5;">🔍 ${escapeHTML(p.satker || 'Satker')}</div>
                         </div>`;
        });
    } else if (currentMode === 'infrastruktur') {
        // Search Districts
        const matchDistricts = Object.keys(povertyStats).filter(name => name.toLowerCase().includes(query));
        matchDistricts.forEach(d => {
            html += `<div class="result-item" onclick="selectDistrict('${d}')">
                            <div class="type">KECAMATAN</div>
                            <div class="name">${d}</div>
                         </div>`;
        });

        // Search Schools (only if layer already loaded or pre-fetched)
        if (infraData.school && infraData.school.features) {
            const matchSchools = infraData.school.features.filter(f => f.properties.nama.toLowerCase().includes(query) || (f.properties.tipe || '').toLowerCase().includes(query)).slice(0, 8);
            matchSchools.forEach(s => {
                const tipe = s.properties.tipe || '';
                html += `<div class="result-item" onclick="centerOnFeature('school', '${s.properties.nama.replace(/'/g, "\\'")}', ${s.geometry.coordinates[1]}, ${s.geometry.coordinates[0]})">
                                <div class="type" style="color:#10b981; display:flex; justify-content:space-between;"><span>SEKOLAH</span>${tipe ? '<span style="color:#34d399; font-size:0.55rem;">' + tipe + '</span>' : ''}</div>
                                <div class="name">${s.properties.nama}</div>
                                <div style="font-size:0.6rem; opacity:0.5;">📍 Kec. ${s.properties.kecamatan}</div>
                             </div>`;
            });
        }

        // Search Bridges
        if (infraData.bridge && infraData.bridge.features) {
            const matchBridges = infraData.bridge.features.filter(f => f.properties.nama.toLowerCase().includes(query)).slice(0, 5);
            matchBridges.forEach(b => {
                html += `<div class="result-item" onclick="centerOnFeature('bridge', '${b.properties.nama.replace(/'/g, "\\'")}', ${b.geometry.coordinates[1]}, ${b.geometry.coordinates[0]})">
                                <div class="type" style="color:#f59e0b">JEMBATAN</div>
                                <div class="name">${b.properties.nama}</div>
                                <div style="font-size:0.6rem; opacity:0.5;">📍 Kec. ${b.properties.kecamatan}</div>
                             </div>`;
            });
        }

        // Search Deep Bridges
        if (infraData.bridgeDeep && infraData.bridgeDeep.features) {
            const matchBridges = infraData.bridgeDeep.features.filter(f => f.properties.nama.toLowerCase().includes(query)).slice(0, 5);
            matchBridges.forEach(b => {
                html += `<div class="result-item" onclick="centerOnFeature('bridgeDeep', '${b.properties.nama.replace(/'/g, "\\'")}', ${b.geometry.coordinates[1]}, ${b.geometry.coordinates[0]})">
                                <div class="type" style="color:#3b82f6">JEMBATAN (DEEP)</div>
                                <div class="name">${b.properties.nama}</div>
                                <div style="font-size:0.6rem; opacity:0.5;">📍 Kec. ${b.properties.kecamatan}</div>
                             </div>`;
            });
        }

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

function centerOnFeature(type, name, lat, lng) {
    document.getElementById('searchResults').classList.remove('show');
    map.setView([lat, lng], 18);

    // Find the marker object to open popup
    let targetLayer = null;
    if (type === 'school') targetLayer = schoolLayer;
    else if (type === 'bridge') targetLayer = bridgeLayer;
    else if (type === 'bridgeDeep') targetLayer = bridgeDeepLayer;

    if (targetLayer) {
        targetLayer.eachLayer(layer => {
            if (layer.getLatLng && layer.getLatLng().lat.toFixed(5) == lat.toFixed(5) && layer.getLatLng().lng.toFixed(5) == lng.toFixed(5)) {
                layer.openPopup();
            }
        });
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
    updateBreadcrumb(name);
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

function toggleVendorModal() {
    const modal = document.getElementById('vendorModal');
    if (!modal) return;
    modal.classList.toggle('show');
}
window.toggleVendorModal = toggleVendorModal;

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
        // Unified realization link contextually
        link.href = `https://data.inaproc.id/realisasi?tahun=${p.tahun}&jenis_klpd=4&instansi=D100`;

        togglePacketModal();
    }
    selectDistrict(kecamatanName);
    updateBreadcrumb(kecamatanName, id);
}

function selectPackageFromSearch(id, kecamatanName, year) {
    if (activeYear != year) {
        switchYear(year);
        // Wait for map and stats to refresh before selecting
        setTimeout(() => {
            selectPackage(id, kecamatanName);
        }, 300);
    } else {
        selectPackage(id, kecamatanName);
    }
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
        .catch(() => { });
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

let map;
const mapEl = document.getElementById('map');
if (mapEl) {
    map = L.map('map', { zoomControl: false }).setView([-6.837, 108.227], 11);
    setMapTheme(mapTheme);
}

// Block map events on sidebar & overlay
['sidebar', 'sidebarOverlay'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        L.DomEvent.disableClickPropagation(el);
        L.DomEvent.disableScrollPropagation(el);
        el.addEventListener('mousedown', e => e.stopPropagation(), true);
        el.addEventListener('touchstart', e => e.stopPropagation(), true);
        el.addEventListener('pointermove', e => e.stopPropagation(), true);
        el.addEventListener('pointerdown', e => e.stopPropagation(), true);
    }
});

// Auto-close sidebar on map click
map.on('click', function () {
    if (typeof closeSidebar === 'function') closeSidebar();
});

// Create a specific pane for roads to keep them on top of polygons
map.createPane('roadPane');
map.getPane('roadPane').style.zIndex = 650;
map.getPane('roadPane').style.pointerEvents = 'auto';

map.createPane('bridgePane');
map.getPane('bridgePane').style.zIndex = 660; // Above roads (650), below popups (700)
map.getPane('bridgePane').style.pointerEvents = 'auto';

function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatPaguJS(p) {
    if (!p || p === 0) return "Rp0";
    if (p >= 1000000000) return "Rp" + (p / 1000000000).toFixed(1) + " M";
    if (p >= 1000000) return "Rp" + Math.round(p / 1000000) + " Jt";
    return "Rp" + p.toLocaleString('id-ID');
}

function getSIRUPColor(name) {
    const s = getActiveStats();
    const v = s[name] ? s[name].total_pagu : 0;
    const exists = s[name];
    if (!exists || v === 0) return '#1e293b';

    // Premium Indigo/Violet Scale
    return v > 10000000000 ? '#4c1d95' :  // > 10M
        v > 5000000000 ? '#5b21b6' :  // > 5M
            v > 2000000000 ? '#7c3aed' :  // > 2M
                v > 500000000 ? '#a78bfa' :  // > 500jt
                    '#ddd6fe';                     // < 500jt
}

function getDDColor(name) {
    const d = villageStats[name] ? villageStats[name].budget : 0;
    return d > 1200000000 ? '#064e3b' : d > 1000000000 ? '#065f46' : d > 800000000 ? '#059669' : d > 0 ? '#10b981' : '#1e293b';
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
        v > 5000000000 ? '#5b21b6' :  // > 5M
            v > 2000000000 ? '#7c3aed' :  // > 2M
                v > 500000000 ? '#a78bfa' :  // > 500jt
                    v > 0 ? '#ddd6fe' : '#1e293b';
}

function switchPadYear(year) {
    year = parseInt(year) || 2025;
    activeYear = year;

    const availableYears = Object.keys(padKecStats || {}).map(y => parseInt(y)).filter(y => !isNaN(y));
    const hasData = availableYears.length > 0;
    const displayYear = (padKecStats && padKecStats[year]) ? year : (hasData ? Math.max(...availableYears) : year);

    document.querySelectorAll('#sidebar-pad .year-btn').forEach(btn => {
        const btnYear = parseInt(btn.innerText);
        btn.classList.toggle('active', btnYear === year);
    });

    const labelEl = document.getElementById('pad-year-label');
    if (labelEl) {
        const isFallback = displayYear != year && hasData;
        labelEl.innerText = `Realisasi Tahun ${displayYear}${isFallback ? ' (Data Terakhir)' : ''}`;
    }

    const globalYearData = (padGlobalStats && padGlobalStats.data || []).find(d => d.tahun == year);
    const totalVal = globalYearData ? globalYearData.nilai : 0;
    const totalEl = document.getElementById('pad-total-val');
    if (totalEl) totalEl.innerText = formatPaguJS(totalVal);

    renderPadRanking(displayYear);
    loadMapData();
}

function renderPadRanking(year) {
    const list = document.getElementById('pad-ranking-list');
    if (!list) return;
    const yearData = padKecStats[year] || {};
    const sorted = Object.entries(yearData).sort((a, b) => b[1].total - a[1].total);

    let html = '';
    sorted.forEach(([name, data]) => {
        html += `
                <div class="kec-list-item" onclick="selectDistrict('${name}')" style="cursor:pointer; border-left:3px solid #ec4899; margin-bottom:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:600;">${name}</span>
                        <span style="color:#ec4899; font-weight:800;">${formatPaguJS(data.total)}</span>
                    </div>
                </div>
            `;
    });
    list.innerHTML = html || '<div style="font-size:0.75rem; opacity:0.5; padding:10px;">Data tidak tersedia untuk tahun ini.</div>';
}

function getPadColor(name, year) {
    let yearData = padKecStats[year];
    if (!yearData) {
        const available = Object.keys(padKecStats).sort();
        if (available.length > 0) yearData = padKecStats[available[available.length - 1]];
    }
    if (!yearData) return '#1e293b';

    const val = yearData[name] ? yearData[name].total : 0;
    if (val === 0) return '#1e293b';

    return val > 5000000000 ? '#831843' :
        val > 2000000000 ? '#be185d' :
            val > 1000000000 ? '#db2777' :
                val > 500000000 ? '#f472b6' :
                    '#fce7f3';
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
    pkts.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerMap[p.satker] = satkerMap[p.satker] || []; satkerMap[p.satker].push(p); } });
    const topSatker = Object.entries(satkerMap).sort((a, b) => b[1].length - a[1].length)[0];

    // Logic 2: Vendor Splitting (Filter 180M-200M)
    const vendorMap = {};
    pkts.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorMap[p.vendor] = vendorMap[p.vendor] || []; vendorMap[p.vendor].push(p); } });
    const topVendorSplit = Object.entries(vendorMap).sort((a, b) => b[1].length - a[1].length)[0];

    // Logic 3: Monopoly (Global Non-Tender Pattern)
    const all2025 = allAudits.filter(p => p.tahun == 2025 && p.pagu < 200000000);
    const globalVendors = {};
    all2025.forEach(p => { if (p.vendor) { globalVendors[p.vendor] = globalVendors[p.vendor] || []; globalVendors[p.vendor].push(p); } });
    const topGlobalVendor = Object.entries(globalVendors).sort((a, b) => b[1].length - a[1].length)[0];

    let html = ``;

    // PILLAR 1: SATKER SPLIT
    if (topSatker && topSatker[1].length >= 3) {
        html += `
                <div style="background:rgba(255,255,255,0.05); border-left:3px solid #fca5a5; border-radius:10px; margin-bottom:12px; overflow:hidden;">
                    <div onclick="document.getElementById('evidence-p1').style.display = document.getElementById('evidence-p1').style.display === 'none' ? 'block' : 'none'" style="padding:12px; cursor:pointer; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b style="font-size:0.8rem; color:#fca5a5;">1. Indikasi Pemecahan Satker</b>
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">${topSatker[0].substring(0, 30)}...</p>
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
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">Penyedia: ${topVendorSplit[0].substring(0, 25)}...</p>
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
                            <p style="font-size:0.65rem; opacity:0.6; margin-top:2px;">${topGlobalVendor[0].substring(0, 30)}...</p>
                        </div>
                        <div style="font-size:0.7rem; color:#fbbf24;">${topGlobalVendor[1].length} Total ▾</div>
                    </div>
                    <div id="evidence-p3" style="display:${initialPilar === 3 ? 'block' : 'none'}; padding:0 12px 12px 12px; background:rgba(0,0,0,0.2);">
                        <div style="font-size:0.65rem; margin-bottom:8px; opacity:0.5;">Terdeteksi memonopoli ${topGlobalVendor[1].length} paket di berbagai wilayah.</div>
                        ${topGlobalVendor[1].slice(0, 15).map(p => `
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
        pkts.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerMap[p.satker] = (satkerMap[p.satker] || 0) + 1; } });
        const satkerVals = Object.values(satkerMap);
        const score1 = (satkerVals.length > 0 ? Math.max(...satkerVals) : 0) * 25;

        // Pillar 2: Vendor Cluster
        const vendorMap = {};
        pkts.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorMap[p.vendor] = (vendorMap[p.vendor] || 0) + 1; } });
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
            `Terdeteksi <b>Pemecahan Paket</b>: <b>${pkts.length} paket</b> dikuasai oleh <b>${vendor.substring(0, 25)}</b> di Kec. <b>${kec}</b>.`;
    } else {
        document.getElementById('audit-pemecahan-desc').innerHTML =
            `Pola distribusi paket kecil merata di berbagai penyedia (Indikasi Sehat).`;
        // Change color to green for this icon if healthy? Let's just keep text for now.
    }

    // 2. Dominansi Global
    const vendors = {};
    data.forEach(p => { if (p.vendor) vendors[p.vendor] = (vendors[p.vendor] || 0) + 1; });
    const topVendors = Object.entries(vendors).sort((a, b) => b[1] - a[1]);
    if (topVendors.length > 0) {
        document.getElementById('audit-dominansi-desc').innerHTML =
            `Penyedia <b>${topVendors[0][0].substring(0, 25)}</b> mendominasi <b>${topVendors[0][1]} paket</b> pengadaan secara global.`;
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
                    score > 0 ? '#f87171' : '#1e293b';
}

function renderRiskRanking() {
    const list = document.getElementById('risk-ranking-list');
    if (!list) return;
    const sorted = Object.entries(RISK_SCORES).sort((a, b) => b[1] - a[1]);
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
    const file = ['sirup', 'kemiskinan', 'infrastruktur', 'realisasi', 'audit', 'pad'].includes(currentMode) ? 'districts.geojson' : 'villages.geojson';
    districtLayers = {};
    villageLayers = {};

    return fetch('./' + file)
        .then(r => r.json())
        .then(data => {
            geoLayer = L.geoJson(data, {
                style: function (f) {
                    const name = (currentMode !== 'danadesa') ? f.properties.nm_kecamatan : f.properties.nm_kelurahan;
                    let color = '#1e293b';
                    if (currentMode === 'sirup') color = getSIRUPColor(name);
                    else if (currentMode === 'danadesa') color = getDDColor(name);
                    else if (currentMode === 'kemiskinan') color = getPovertyColor(name);
                    else if (currentMode === 'infrastruktur') color = '#1e293b'; // Neutral background for infra mode
                    else if (currentMode === 'pad') color = getPadColor(name, activeYear);
                    else if (currentMode === 'realisasi') color = getRealisasiColor(name);
                    else if (currentMode === 'audit') color = getAuditColor(name);

                    return {
                        fillColor: color,
                        weight: 1, opacity: 1, color: 'rgba(255,255,255,0.1)',
                        fillOpacity: 0.6
                    };
                },
                onEachFeature: function (f, layer) {
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
                                        <div style="font-size:0.7rem; color:#fbbf24; font-weight:600; cursor:pointer; display:inline-block;" onclick="showVendorIntelligence('${escapeHTML(p.vendor || 'Penyedia').replace(/'/g, "\\'")}')">🏗️ ${escapeHTML(p.vendor || 'Penyedia')}</div>
                                        <div style="font-size:0.6rem; opacity:0.5;">🏢 ${escapeHTML(p.satker || 'Satker')}</div>
                                    </div>`;
                            });
                            if (packetHtml) packetHtml += '</div>';
                            layer.bindPopup(`<div class="info-box" style="width:250px;"><b style="font-size:1.1rem; color:var(--accent);">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Monitoring Realisasi T.A ${activeYear}</span><hr style="opacity:0.2; margin:8px 0;"><b>Total Realisasi:</b> <span style="color:var(--accent)">${formatPaguJS(d.total_pagu)}</span><br>Temuan Anomali: <span style="color:${d.high_risk > 0 ? '#ef4444' : '#10b981'}">${d.high_risk}</span>${packetHtml}<hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(59,130,246,0.2); border-radius:8px; color:var(--accent); text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a><a href="#" onclick="showKecamatanVendors('${name}'); return false;" style="display:block; margin-top:8px; text-align:center; padding:8px; color:white; background:var(--accent); border-radius:8px; font-size:0.75rem; font-weight:600; text-decoration:none;">👤 Lihat Daftar Vendor ↗</a></div>`);
                        } else if (currentMode === 'audit') {
                            const kecPackets = allAudits.filter(p => p.kecamatan === name && p.tahun == 2025);

                            // LOGIC 1: Pemecahan Satker (Same Satker + Zona Kritis 180M-200M)
                            const satkerClusters = {};
                            kecPackets.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.satker) { satkerClusters[p.satker] = (satkerClusters[p.satker] || 0) + 1; } });
                            const maxSatkerSplit = Math.max(0, ...Object.values(satkerClusters));
                            const score1 = Math.min(100, maxSatkerSplit * 25);

                            // LOGIC 2: Pemecahan Vendor (Same Vendor + Zona Kritis 180M-200M)
                            const vendorClusters = {};
                            kecPackets.forEach(p => { if (p.pagu >= 180000000 && p.pagu < 200000000 && p.vendor) { vendorClusters[p.vendor] = (vendorClusters[p.vendor] || 0) + 1; } });
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
                        } else if (currentMode === 'pad') {
                            let yearData = padKecStats[activeYear];
                            let effectiveYear = activeYear;
                            if (!yearData) {
                                const available = Object.keys(padKecStats).sort();
                                if (available.length > 0) {
                                    effectiveYear = available[available.length - 1];
                                    yearData = padKecStats[effectiveYear];
                                }
                            }

                            const d = (yearData && yearData[name]) ? yearData[name] : { total: 0, detail: {} };
                            let detailHtml = '<div style="margin-top:10px; font-size:0.75rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top:10px;">';
                            if (d.detail.bphtb) detailHtml += `<div>BPHTB: <b style="color:#f472b6">${formatPaguJS(d.detail.bphtb)}</b></div>`;
                            if (d.detail.hotel) detailHtml += `<div>Pajak Hotel: <b style="color:#f472b6">${formatPaguJS(d.detail.hotel)}</b></div>`;
                            if (d.detail.bagi_hasil) detailHtml += `<div>Bagi Hasil: <b style="color:#f472b6">${formatPaguJS(d.detail.bagi_hasil)}</b></div>`;
                            detailHtml += '</div>';

                            layer.bindPopup(`<div class="info-box" style="width:220px; border-top: 3px solid #ec4899;">
                                    <b style="font-size:1.1rem; color:#f472b6;">PAD Kec. ${name}</b><br>
                                    <span style="font-size:0.7rem; opacity:0.5;">Analisis Kontribusi T.A ${effectiveYear}</span>
                                    <hr style="opacity:0.2; margin:8px 0;">
                                    <b>Total Kontribusi Est.:</b><br>
                                    <span style="font-size:1.4rem; font-weight:600; color:#ec4899;">${formatPaguJS(d.total)}</span>
                                    ${detailHtml}
                                    <hr style="opacity:0.1; margin:8px 0;">
                                    <div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div>
                                </div>`);
                        } else {
                            const infraData = povertyStats[name] || { road_pct: 75 };
                            layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#06b6d4;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data 2024 | Sumber: DPUTR Majalengka</span><hr style="opacity:0.2; margin:8px 0;"><b>Level Kemantapan:</b><br><span style="font-size:1.8rem; font-weight:600; color:#06b6d4;">${infraData.road_pct}%</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Indeks berdasarkan integrasi SP4N-LAPOR! & Statistik Jalan Kabupaten.</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                        }
                    } else {
                        villageLayers[name] = layer;
                        const v = villageStats[name] || { budget: 0, risk: 0, kecamatan: 'Unknown' };
                        layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#10b981;">Desa ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Kecamatan ${v.kecamatan}</span><hr style="opacity:0.2; margin:8px 0;"><b>Alokasi Dana Desa T.A 2025:</b><br><span style="font-size:1.4rem; font-weight:600; color:#10b981;">${formatPaguJS(v.budget)}</span><br><div style="margin-top:10px; font-size:0.75rem; opacity:0.7; line-height:1.4;">Sumber: Alokasi TKD Kemenkeu RI T.A 2025</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📍 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(16,185,129,0.2); border-radius:8px; color:#34d399; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps ↗</a></div>`);
                    }

                    layer.on('mouseover', function () { this.setStyle({ fillOpacity: 0.9, weight: 2 }); });
                    layer.on('mouseout', function () { this.setStyle({ fillOpacity: 0.6, weight: this === activeLayer ? 4 : 1, color: this === activeLayer ? '#ffffff' : 'rgba(255,255,255,0.1)' }); });
                    layer.on('click', function () {
                        currentOpenPopupKec = name;
                        if (currentMode === 'realisasi') {
                            showKecamatanVendors(name);
                        }
                    });
                    layer.on('popupclose', function () {
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
        .filter(cb => cb.getAttribute('data-class')) // Only class filters
        .map(cb => cb.getAttribute('data-class'));

    roadLayer = L.geoJson(roadData, {
        filter: function (f) {
            return activeClasses.includes(f.properties.classification || 'Jalan Desa');
        },
        style: function (f) {
            const cls = f.properties.classification;
            const status = f.properties.status;

            let color = '#94a3b8'; // Default Village
            let weight = 0.8;
            let dash = null;

            if (cls === 'Jalan Nasional') { color = '#facc15'; }
            else if (cls === 'Jalan Provinsi') { color = '#ec4899'; }
            else if (cls === 'Jalan Kabupaten') { color = '#3b82f6'; }

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
        onEachFeature: function (f, layer) {
            const coords = layer.getLatLngs ? layer.getLatLngs() : [];
            let lat = 0, lng = 0;
            if (coords.length > 0) {
                const mid = Array.isArray(coords[0]) ? coords[0][Math.floor(coords[0].length / 2)] : coords[Math.floor(coords.length / 2)];
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

function toggleBridges(visible) {
    if (visible === undefined) {
        const btn = document.getElementById('bridge-filter');
        visible = btn ? btn.checked : false;
    }

    if (!visible) {
        if (bridgeLayer) map.removeLayer(bridgeLayer);
        return;
    }

    if (infraData.bridge) {
        renderBridgeLayer(infraData.bridge);
    } else {
        fetch('data/jembatan_kabupaten.geojson')
            .then(r => r.json())
            .then(data => {
                infraData.bridge = data;
                renderBridgeLayer(data);
            });
    }
}

function toggleDeepBridges(visible) {
    if (visible === undefined) {
        const btn = document.getElementById('bridgeDeep-filter');
        visible = btn ? btn.checked : false;
    }

    if (!visible) {
        if (bridgeDeepLayer) map.removeLayer(bridgeDeepLayer);
        return;
    }

    if (infraData.bridgeDeep) {
        renderDeepBridgeLayer(infraData.bridgeDeep);
    } else {
        fetch('data/jembatan_deep.geojson')
            .then(r => r.json())
            .then(data => {
                infraData.bridgeDeep = data;
                renderDeepBridgeLayer(data);
            });
    }
}

let bridgeDeepLayer = null;
function renderDeepBridgeLayer(data) {
    if (bridgeDeepLayer) map.removeLayer(bridgeDeepLayer);
    if (!data || !data.features) return;

    const markers = [];
    data.features.forEach(f => {
        const p = f.properties;
        const icon = L.divIcon({
            className: 'bridge-deep-marker',
            html: `<div class="bridge-icon-container" style="background:#3b82f6; color:white; animation: pulse-blue-bridge 2s infinite; display:flex; align-items:center; justify-content:center; width:14px; height:14px; border-radius:50%; border:1px solid white; font-size:6px;">🌉</div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        const latlng = [f.geometry.coordinates[1], f.geometry.coordinates[0]];
        const marker = L.marker(latlng, { icon: icon });

        const popupHtml = `
                <div class="info-box" style="width:280px; border-top: 5px solid #3b82f6; padding:15px; background: rgba(15, 23, 42, 0.98);">
                    <div style="font-size:0.6rem; opacity:0.6; text-transform:uppercase; color:#3b82f6; letter-spacing:1px; margin-bottom:5px;">Scraped Bridge (Grid AI)</div>
                    <b style="color:white; font-size:1.1rem; display:block; margin-bottom:2px;">${p.nama}</b>
                    <span style="font-size:0.75rem; opacity:0.5;">Kecamatan ${p.kecamatan}</span>
                    <hr style="opacity:0.1; margin:12px 0;">
                    
                    <div style="font-size:0.8rem; line-height:1.5; color:rgba(255,255,255,0.8); margin-bottom:15px;">
                        ${p.catatan}
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <a href="${p.source_url}" target="_blank" style="text-decoration:none; color:#3b82f6; font-size:0.75rem; font-weight:700;">🌐 Google Maps ↗</a>
                    </div>
                </div>
            `;
        marker.bindPopup(popupHtml, { maxWidth: 300 });
        markers.push(marker);
    });

    bridgeDeepLayer = L.layerGroup(markers).addTo(map);
    updateInfraStats();
}
function renderBridgeLayer(data) {
    console.log("🌉 Render Bridge Layer [START]", data ? data.features.length : "NO DATA");
    infraData.bridge = data;
    if (bridgeLayer) map.removeLayer(bridgeLayer);
    if (!data || !data.features) return;

    const markers = [];
    data.features.forEach(f => {
        const p = f.properties;
        const isProject = p.is_project === true;
        const filteredPaket = (p.paket || []).filter(pkg => pkg.tahun == currentInfraYear);

        const isCurrentlyActiveProject = isProject && filteredPaket.length > 0;
        const glowStyle = isCurrentlyActiveProject ? 'animation: pulse-bridge 2s infinite;' : 'opacity: 0.7; filter: grayscale(1);';
        const iconHtml = isCurrentlyActiveProject ? '🌉' : '⛓️';

        const icon = L.divIcon({
            className: 'bridge-marker',
            html: `<div class="bridge-icon-container" style="${glowStyle}">${iconHtml}</div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        const latlng = [f.geometry.coordinates[1], f.geometry.coordinates[0]];
        const marker = L.marker(latlng, { icon: icon, zIndexOffset: isCurrentlyActiveProject ? 2000 : 500 });

        // Popup logic
        let projectsHtml = '';
        if (isCurrentlyActiveProject) {
            projectsHtml = `<div style="font-size:0.65rem; color:#f59e0b; margin-bottom:8px; font-weight:800;">🚀 STATUS: PAKET REALISASI ${currentInfraYear}</div>`;
            filteredPaket.forEach((pkg, i) => {
                const statusColor = (pkg.status || '').includes('SELESAI') ? '#10b981' : '#f59e0b';
                projectsHtml += `
                        <div style="background:rgba(245,158,11,0.05); border:1px solid rgba(245,158,11,0.1); padding:10px; border-radius:10px; margin-bottom:10px;">
                            <div style="font-size:0.75rem; font-weight:600; color:white; line-height:1.4;">${pkg.nama_paket || pkg.nama || '-'}</div>
                            <div style="font-size:0.6rem; color:${statusColor}; margin-top:4px; font-weight:700;">● ${pkg.status || 'N/A'}</div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                <b style="color:#f59e0b; font-size:1rem;">${formatPaguJS(pkg.nilai || pkg.pagu || 0)}</b>
                                <button onclick="showVendorIntelligence('${pkg.penyedia || pkg.vendor || ''}')" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:4px 8px; border-radius:6px; font-size:0.6rem; cursor:pointer;">🔍 Vendor</button>
                            </div>
                            <div style="font-size:0.55rem; opacity:0.4; margin-top:6px;">📋 ${pkg.penyedia || pkg.vendor || '-'} • ${pkg.sumber_dana || ''}</div>
                        </div>
                    `;
            });
        } else {
            projectsHtml = `
                    <div style="padding:15px; border-radius:12px; background:rgba(255,255,255,0.02); border:1px dashed rgba(255,255,255,0.1); text-align:center;">
                        <div style="font-size:0.7rem; opacity:0.6;">Aset ini belum memiliki paket anggaran di tahun ${currentInfraYear}</div>
                    </div>
                `;
        }

        const popupHtml = `
                <div class="info-box" style="width:300px; border-top: 5px solid ${isCurrentlyActiveProject ? '#f59e0b' : '#64748b'}; padding:15px; background: rgba(15, 23, 42, 0.98); ${p.verified === false ? 'border: 2px solid #ef4444;' : ''}">
                    ${p.verified === false ? '<div style="background:#ef4444; color:white; font-size:0.55rem; padding:2px 6px; border-radius:4px; margin-bottom:10px; font-weight:800; text-align:center;">⚠️ ESTIMASI LOKASI</div>' : ''}
                    <div style="font-size:0.6rem; opacity:0.6; text-transform:uppercase; color:${isCurrentlyActiveProject ? '#f59e0b' : '#94a3b8'};">Infrastruktur Jembatan</div>
                    <b style="color:white; font-size:1.2rem; display:block; margin-bottom:2px;">${p.nama}</b>
                    <span style="font-size:0.8rem; opacity:0.5;">Kecamatan ${p.kecamatan}</span>
                    <hr style="opacity:0.1; margin:12px 0;">
                    
                    ${projectsHtml}

                    <div style="margin-top:15px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.65rem; opacity:0.4;">✓ ${p.catatan || 'Lokasi Terverifikasi'}</span>
                        <a href="https://www.google.com/maps?q=${latlng[0]},${latlng[1]}" target="_blank" style="text-decoration:none; color:#fbbf24; font-size:0.75rem; font-weight:700;">Navigasi ↗</a>
                    </div>
                </div>
            `;
        marker.bindPopup(popupHtml, { maxWidth: 320, className: 'custom-popup' });
        markers.push(marker);
    });

    bridgeLayer = L.layerGroup(markers).addTo(map);
    console.log("🌉 Render Bridge Layer [END] - Success adding", markers.length, "markers");
    updateInfraStats();
}

let schoolLayer = null;
let allSchoolData = null;

function toggleSchools() {
    const btn = document.getElementById('school-filter');
    if (!btn) return;
    const isChecked = btn.checked;

    if (!isChecked) {
        if (schoolLayer) map.removeLayer(schoolLayer);
        return;
    }

    if (allSchoolData) {
        renderSchoolLayer(allSchoolData);
    } else {
        fetch('data/schools_ultra_clean.geojson')
            .then(r => r.json())
            .then(data => {
                allSchoolData = data;
                renderSchoolLayer(data);
            });
    }
}

function renderSchoolLayer(data) {
    infraData.school = data;
    if (schoolLayer) map.removeLayer(schoolLayer);
    if (!data || !data.features) return;

    // Tampilkan semua sekolah sebagai landmark permanen
    const schoolGroups = {};
    const tipeEmoji = { 'SD/MI': '📘', 'SMP/MTs': '📗', 'SMA/MA': '📕', 'SMK': '🔧', 'TK/RA': '🧒', 'PAUD': '👶', 'Pesantren': '🕌', 'Diniyah/TPQ': '📖', 'SLB': '♿', 'PKBM': '📚', 'Perguruan Tinggi': '🎓', 'Yayasan Pendidikan': '🏛️', 'Lainnya': '🏫' };
    data.features.forEach(f => {
        const coordKey = f.geometry.coordinates.join(',');
        if (!schoolGroups[coordKey]) {
            schoolGroups[coordKey] = {
                coords: [f.geometry.coordinates[1], f.geometry.coordinates[0]],
                name: f.properties.nama,
                kecamatan: f.properties.kecamatan,
                tipe: f.properties.tipe || 'Lainnya',
                catatan: f.properties.catatan,
                unverified: f.properties.verified === false,
                projects: []
            };
        }
        // Proyek diletakkan di dalam array untuk difilter nanti di popup
        if (f.properties.paket && f.properties.paket.length > 0) {
            f.properties.paket.forEach(p => schoolGroups[coordKey].projects.push(p));
        }
    });

    const markers = [];
    Object.values(schoolGroups).forEach(group => {
        const hasProjectsThisYear = group.projects.some(p => Number(p.tahun) === Number(currentInfraYear));
        const glowStyle = hasProjectsThisYear ? 'animation: pulse-school 2s infinite;' : 'opacity: 0.5; filter: grayscale(1);';

        const emoji = tipeEmoji[group.tipe] || '🏫';
        const icon = L.divIcon({
            className: 'school-marker',
            html: `<div class="school-pill" style="${glowStyle}">${emoji}</div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        const marker = L.marker(group.coords, {
            icon: icon,
            zIndexOffset: hasProjectsThisYear ? 1000 : 100,
            hasProject: hasProjectsThisYear
        });

        // Build Multi-Project Popup
        let projectsHtml = '';
        group.projects.forEach((p, i) => {
            if (p.tahun != currentInfraYear) return;
            const statusColor = (p.status || '').includes('SELESAI') ? '#10b981' : '#eab308';
            projectsHtml += `
                    <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:10px; border-radius:10px; margin-bottom:12px; position:relative;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                            <span style="background:#3b82f6; color:white; font-size:0.6rem; padding:2px 8px; border-radius:50px; font-weight:800; letter-spacing:0.5px;">T.A ${p.tahun}</span>
                            <span style="font-size:0.55rem; color:${statusColor}; font-weight:700;">● ${p.status || 'PROSES'}</span>
                        </div>
                        <div style="font-size:0.75rem; font-weight:600; color:#f8fafc; line-height:1.4; margin-bottom:10px;">${p.nama_paket || p.nama || '-'}</div>
                        
                        <div style="background:rgba(16,185,129,0.1); padding:8px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid rgba(16,185,129,0.1);">
                            <div>
                                <div style="font-size:0.55rem; opacity:0.6; text-transform:uppercase;">Alokasi Pagu</div>
                                <div style="font-weight:800; color:#10b981; font-size:1.05rem;">${formatPaguJS(p.nilai || p.pagu || 0)}</div>
                            </div>
                            <button onclick="showVendorIntelligence('${p.penyedia || p.vendor || ''}')" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:4px 8px; border-radius:6px; font-size:0.6rem; cursor:pointer; font-weight:700;">🔍 Vendor</button>
                        </div>
                        <div style="font-size:0.5rem; opacity:0.4; margin-top:6px;">📋 ${p.penyedia || p.vendor || '-'} • ${p.sumber_dana || ''}</div>
                    </div>
                `;
        });

        if (projectsHtml === '') {
            projectsHtml = `<div style="text-align:center; padding:20px; opacity:0.5; font-size:0.75rem;">Aset ini belum memiliki paket anggaran di tahun ${currentInfraYear}</div>`;
        }

        const popupHtml = `
                <div class="info-box" style="width:300px; border-top: 5px solid #10b981; padding:15px; background: rgba(15, 23, 42, 0.98); ${group.unverified ? 'border: 2px solid #ef4444;' : ''}">
                    ${group.unverified ? '<div style="background:#ef4444; color:white; font-size:0.55rem; padding:2px 6px; border-radius:4px; margin-bottom:10px; font-weight:800; text-align:center;">⚠️ ESTIMASI LOKASI</div>' : ''}
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;"><div style="font-size:0.6rem; opacity:0.6; text-transform:uppercase; letter-spacing:1px; color:#10b981;">Fasilitas Pendidikan</div><span style="font-size:0.55rem; background:rgba(16,185,129,0.15); color:#34d399; padding:2px 8px; border-radius:50px; font-weight:700;">${group.tipe}</span></div>
                    <b style="color:white; font-size:1.2rem; display:block; margin-bottom:2px; line-height:1.2;">${group.name}</b>
                    <span style="font-size:0.8rem; opacity:0.5;">Kecamatan ${group.kecamatan}</span>
                    <hr style="opacity:0.1; margin:12px 0;">
                    
                    <div style="max-height: 250px; overflow-y: auto; padding-right:5px;" class="custom-scroll">
                        ${projectsHtml}
                    </div>

                    <div style="margin-top:10px; font-size:0.6rem; opacity:0.4;">✓ ${group.catatan || 'Lokasi Terverifikasi'}</div>

                    <a href="https://www.google.com/maps?q=${group.coords[0]},${group.coords[1]}" target="_blank" style="display:block; margin-top:15px; text-align:center; padding:12px; background:rgba(16,185,129,0.2); border-radius:10px; color:#34d399; text-decoration:none; font-size:0.85rem; font-weight:800; border:1px solid rgba(16,185,129,0.3); transition:0.3s;">
                        🗺️ Navigasi Ke Lokasi ↗
                    </a>
                </div>
            `;

        marker.bindPopup(popupHtml, { maxWidth: 320, className: 'custom-popup' });
        markers.push(marker);
    });

    schoolLayer = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 50,
        iconCreateFunction: function (cluster) {
            const hasProject = cluster.getAllChildMarkers().some(m => m.options.hasProject === true);
            const bg = hasProject ? 'rgba(245, 158, 11, 0.95)' : 'rgba(16, 185, 129, 0.9)';
            const shadow = hasProject ? 'rgba(245, 158, 11, 0.6)' : 'rgba(16, 185, 129, 0.5)';
            const animation = hasProject ? 'animation: pulse-school 2s infinite;' : '';
            const border = hasProject ? 'border:2px solid #fff;' : 'border:2px solid rgba(255,255,255,0.2);';

            return L.divIcon({
                html: `<div style="background:${bg}; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem; box-shadow:0 0 15px ${shadow}; ${border} backdrop-filter:blur(5px); ${animation}">${cluster.getChildCount()}</div>`,
                className: 'school-cluster-icon',
                iconSize: [36, 36]
            });
        }
    });
    schoolLayer.addLayers(markers);
    map.addLayer(schoolLayer);
    updateInfraStats();
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
    const sortedVendors = Object.keys(vendors).sort((a, b) => vendors[b].total - vendors[a].total);

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

function logVisitorGPS() {
    if (!navigator.geolocation) {
        fetch('log_visit.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({}) });
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            fetch('log_visit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                })
            });
        },
        (err) => {
            fetch('log_visit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });
        },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
    );
}

window.onload = () => {
    try { if (window.updateModalContent) window.updateModalContent(currentMode); } catch (e) { }
    try { updateLegend(currentMode); } catch (e) { }
    try { loadMapData(); } catch (e) { }
    try { logVisitorGPS(); } catch (e) { }

    // Ensure map renders correctly
    setTimeout(() => {
        if (typeof map !== 'undefined' && map) map.invalidateSize();
    }, 500);
};


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

window.toggleShare = function (show) {
    document.getElementById('shareModal').style.display = show ? 'flex' : 'none';
}

window.toggleSawer = function (show) {
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
    if (!leg) return;
    const isHidden = window.getComputedStyle(leg).display === 'none';
    leg.style.display = isHidden ? 'block' : 'none';
}


// --- LAYER 4: INFRASTRUCTURE & VENDOR INTELLIGENCE LOGIC ---
let currentInfraYear = 2025;
let infraData = { school: null, bridge: null, bridgeDeep: null, bridgeRealisasi: null, schoolRealisasi: null };

// PRE-LOAD DATA UNTUK STATISTIK AWAL
async function initInfraStats() {
    try {
        const v = Date.now(); // Cache buster
        const [schoolRes, bridgeRes, bridgeDeepRes, bridgeRealRes, schoolRealRes, unmBridge, unmSchool] = await Promise.all([
            fetch(`data/schools_ultra_clean.geojson?v=${v}`).then(r => r.json()).catch(() => null),
            fetch(`data/jembatan_kabupaten.geojson?v=${v}`).then(r => r.json()).catch(() => null),
            fetch(`data/jembatan_deep.geojson?v=${v}`).then(r => r.json()).catch(() => null),
            fetch(`data/realisasi_jembatan.geojson?v=${v}`).then(r => r.json()).catch(() => null),
            fetch(`data/realisasi_sekolah.geojson?v=${v}`).then(r => r.json()).catch(() => null),
            fetch(`data/unmapped_jembatan_noloc.geojson?v=${v}`).then(r => r.json()).catch(() => []),
            fetch(`data/unmapped_sekolah.geojson?v=${v}`).then(r => r.json()).catch(() => [])
        ]);

        infraData.school = schoolRes;
        infraData.bridge = bridgeRes;
        infraData.bridgeDeep = bridgeDeepRes;
        infraData.bridgeRealisasi = bridgeRealRes;
        infraData.schoolRealisasi = schoolRealRes;
        infraData.unmappedBridge = Array.isArray(unmBridge) ? unmBridge : (unmBridge?.data || []);
        infraData.unmappedSchool = Array.isArray(unmSchool) ? unmSchool : (unmSchool?.data || []);

        updateInfraStats();
    } catch (e) { console.error("Gagal Pre-load Infra Data:", e); }
}
initInfraStats();

// Format Rupiah singkat
function fmtRp(v) {
    if (!v) return 'Rp 0';
    if (v >= 1e9) return `Rp ${(v / 1e9).toFixed(1)} M`;
    if (v >= 1e6) return `Rp ${(v / 1e6).toFixed(0)} jt`;
    return `Rp ${v.toLocaleString('id')}`;
}

// Render unmapped list
function renderUnmappedList(type) {
    const data = type === 'bridge' ? infraData.unmappedBridge : infraData.unmappedSchool;
    const filtered = (data || []).filter(p => Number(p.tahun) === Number(currentInfraYear));
    const color = type === 'bridge' ? '#f59e0b' : '#10b981';

    // Update count & visibility
    const toggleEl = document.getElementById(`unmapped${type === 'bridge' ? 'Bridge' : 'School'}Toggle`);
    const countEl = document.getElementById(`unmapped${type === 'bridge' ? 'Bridge' : 'School'}Count`);
    if (toggleEl) toggleEl.style.display = filtered.length > 0 ? 'block' : 'none';
    if (countEl) countEl.textContent = filtered.length;

    // Render items
    const listEl = document.getElementById(`unmapped${type === 'bridge' ? 'Bridge' : 'School'}List`);
    if (!listEl) return;

    listEl.innerHTML = filtered.map(p => {
        const statusColor = (p.status || '').includes('SELESAI') ? '#10b981' : '#eab308';
        return `
                <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:8px 10px; border-radius:8px; margin-bottom:6px;">
                    <div style="font-size:0.65rem; color:white; font-weight:600; line-height:1.3; margin-bottom:4px;">${(p.nama_paket || '').substring(0, 70)}...</div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="color:${color}; font-size:0.75rem; font-weight:800;">${fmtRp(p.nilai)}</span>
                        <span style="font-size:0.55rem; color:${statusColor}; font-weight:700;">● ${p.status || '?'}</span>
                    </div>
                    <div style="font-size:0.5rem; opacity:0.4; margin-top:3px;">📋 ${p.penyedia || '-'} • ${p.sumber_dana || ''}</div>
                </div>
            `;
    }).join('');
}

window.toggleUnmappedList = function (type) {
    const key = type === 'bridge' ? 'Bridge' : 'School';
    const listEl = document.getElementById(`unmapped${key}List`);
    const arrowEl = document.getElementById(`unmapped${key}Arrow`);
    if (!listEl) return;

    const isVisible = listEl.style.display !== 'none';
    listEl.style.display = isVisible ? 'none' : 'block';
    if (arrowEl) arrowEl.textContent = isVisible ? '▼' : '▲';

    if (!isVisible) renderUnmappedList(type);
};

window.setInfraYear = function (year) {
    currentInfraYear = year;
    const b25 = document.getElementById('btnYear2025');
    const b26 = document.getElementById('btnYear2026');
    if (b25) { b25.style.background = year === 2025 ? '#3b82f6' : 'transparent'; b25.style.opacity = year === 2025 ? '1' : '0.5'; }
    if (b26) { b26.style.background = year === 2026 ? '#f59e0b' : 'transparent'; b26.style.opacity = year === 2026 ? '1' : '0.5'; }

    // Refresh Active Layers
    if (document.getElementById('school-filter')?.checked) {
        if (infraData.school) renderSchoolLayer(infraData.school);
    }
    if (document.getElementById('bridge-filter')?.checked) {
        if (infraData.bridge) renderBridgeLayer(infraData.bridge);
    }

    updateInfraStats();
};

window.toggleInfraLayer = function (type) {
    const checkbox = document.getElementById(type + '-filter');
    if (!checkbox) return;
    checkbox.checked = !checkbox.checked;

    if (type === 'school') toggleSchools(checkbox.checked);
    if (type === 'bridge') toggleBridges(checkbox.checked);
    if (type === 'bridgeDeep') toggleDeepBridges(checkbox.checked);

    updateInfraUI();
};

function updateInfraUI() {
    const schoolActive = document.getElementById('school-filter')?.checked;
    const bridgeActive = document.getElementById('bridge-filter')?.checked;
    const bridgeDeepActive = document.getElementById('bridgeDeep-filter')?.checked;

    if (document.getElementById('cardSchool')) {
        document.getElementById('cardSchool').style.background = schoolActive ? 'rgba(16, 185, 129, 0.1)' : 'rgba(16, 185, 129, 0.03)';
        document.getElementById('indicatorSchool').style.background = schoolActive ? '#10b981' : 'rgba(255,255,255,0.1)';
        document.getElementById('indicatorSchool').style.boxShadow = schoolActive ? '0 0 10px #10b981' : 'none';
    }

    if (document.getElementById('cardBridge')) {
        document.getElementById('cardBridge').style.background = bridgeActive ? 'rgba(245, 158, 11, 0.1)' : 'rgba(245, 158, 11, 0.03)';
        document.getElementById('indicatorBridge').style.background = bridgeActive ? '#f59e0b' : 'rgba(255,255,255,0.1)';
        document.getElementById('indicatorBridge').style.boxShadow = bridgeActive ? '0 0 10px #f59e0b' : 'none';
    }

    if (document.getElementById('cardBridgeDeep')) {
        document.getElementById('cardBridgeDeep').style.background = bridgeDeepActive ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.03)';
        document.getElementById('indicatorBridgeDeep').style.background = bridgeDeepActive ? '#3b82f6' : 'rgba(255,255,255,0.1)';
        document.getElementById('indicatorBridgeDeep').style.boxShadow = bridgeDeepActive ? '0 0 10px #3b82f6' : 'none';
    }

    updateInfraStats();
}

function updateInfraStats() {
    if (infraData.school) {
        const totalFeatures = infraData.school.features.length;
        let allSchoolProjects = [];
        if (infraData.schoolRealisasi && infraData.schoolRealisasi.data) {
            allSchoolProjects = infraData.schoolRealisasi.data.filter(p => Number(p.tahun) === Number(currentInfraYear));
        }
        const totalPagu = allSchoolProjects.reduce((sum, p) => sum + (parseFloat(p.nilai) || 0), 0);
        if (document.getElementById('countSchool')) document.getElementById('countSchool').innerHTML = `${totalFeatures} <span style="font-size: 0.7rem; opacity: 0.5; font-weight: 400;">Titik</span>`;
        if (document.getElementById('budgetSchool')) document.getElementById('budgetSchool').innerText = `${allSchoolProjects.length} Paket • Rp ${(totalPagu / 1000000000).toFixed(1)} M`;
    }

    if (infraData.bridge) {
        let allBridgeProjects = [];
        if (infraData.bridgeRealisasi && infraData.bridgeRealisasi.data) {
            allBridgeProjects = infraData.bridgeRealisasi.data.filter(p => Number(p.tahun) === Number(currentInfraYear));
        }
        const totalPagu = allBridgeProjects.reduce((sum, p) => sum + (parseFloat(p.nilai) || 0), 0);
        const totalAssets = infraData.bridge.features.length;
        if (document.getElementById('countBridge')) {
            document.getElementById('countBridge').innerHTML = `
                    <span style="color:#f59e0b;">${allBridgeProjects.length}</span>
                    <span style="font-size: 0.65rem; opacity: 0.6; font-weight: 400;"> Paket / ${totalAssets} Aset</span>
                `;
        }
        if (document.getElementById('budgetBridge')) document.getElementById('budgetBridge').innerText = `Pagu: Rp ${(totalPagu / 1000000).toFixed(0)} jt`;
    }
    if (infraData.bridgeDeep && infraData.bridgeDeep.features) {
        if (document.getElementById('countBridgeDeep')) {
            document.getElementById('countBridgeDeep').innerHTML = `
                    <span style="color:#3b82f6;">${infraData.bridgeDeep.features.length}</span>
                    <span style="font-size: 0.7rem; opacity: 0.5; font-weight: 400;"> Unit Terdeteksi</span>
                `;
        }
    }
    renderUnmappedList('school');
    renderUnmappedList('bridge');
}


function unescapeHTML(str) {
    const txt = document.createElement("textarea");
    txt.innerHTML = str;
    return txt.value;
}

window.showVendorIntelligence = function (vendorName) {
    console.log("🔍 Vendor Intelligence Triggered for:", vendorName);

    if (!vendorName || vendorName === 'PL' || vendorName.includes('Tidak Terdata')) {
        console.warn("⚠️ Invalid Vendor Name:", vendorName);
        return;
    }

    // Unescape HTML entities (e.g., &amp; -> &) to match data in allAudits
    const cleanVendorName = unescapeHTML(vendorName);
    console.log("🧼 Cleaned Vendor Name:", cleanVendorName);

    // Analysis from ALL Audits
    if (!window.APP_DATA || !window.APP_DATA.all_audits) {
        console.error("❌ APP_DATA.all_audits not found!");
        return;
    }

    const allProjects = window.APP_DATA.all_audits.filter(p => {
        return p && (p.vendor === cleanVendorName || p.vendor === vendorName);
    });

    console.log("📊 Projects Found:", allProjects.length);

    if (allProjects.length === 0) {
        alert("Data detail untuk penyedia ini tidak ditemukan dalam database audit.");
        return;
    }

    // Grouping by Year
    const yearStats = {};
    const uniqueKec = new Set();

    allProjects.forEach(p => {
        if (p.tahun) {
            if (!yearStats[p.tahun]) yearStats[p.tahun] = { count: 0, total: 0 };
            yearStats[p.tahun].count++;
            yearStats[p.tahun].total += (p.pagu || 0);
        }
        if (p.kecamatan) uniqueKec.add(p.kecamatan);
    });

    // Populate Modal
    const vNameEl = document.getElementById('v-name');
    const vKecEl = document.getElementById('v-kec-count');
    const vPkgEl = document.getElementById('v-pkg-count');
    const vListEl = document.getElementById('v-year-list');

    if (vNameEl) vNameEl.innerText = cleanVendorName;
    if (vKecEl) vKecEl.innerText = uniqueKec.size;
    if (vPkgEl) vPkgEl.innerText = allProjects.length;

    let yearHtml = '';
    const sortedYears = Object.keys(yearStats).sort((a, b) => b - a);
    sortedYears.forEach(year => {
        yearHtml += `
            <div style="background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <div>
                    <div style="font-size: 0.65rem; color: #fbbf24; font-weight: 800;">Tahun ${year}</div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: white;">${yearStats[year].count} Paket</div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.6rem; opacity: 0.5;">Nilai Akumulasi</div>
                    <div style="font-size: 1rem; font-weight: 800; color: #10b981;">${formatPaguJS(yearStats[year].total)}</div>
                </div>
            </div>
        `;
    });

    if (yearHtml === '') yearHtml = '<div style="text-align:center; opacity:0.5; font-size:0.8rem; padding:20px;">Data tidak tersedia.</div>';
    if (vListEl) vListEl.innerHTML = yearHtml;

    // Show Modal
    const modal = document.getElementById('vendorModal');
    if (modal) {
        modal.classList.add('show');
    } else {
        console.error("❌ vendorModal element not found in DOM!");
    }
};
