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
        <div class="year-toggle" id="global-year-toggle">
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
                <input type="checkbox" onchange="filterRoads()" data-class="Jalan Nasional">
                <span>Jalan Nasional</span>
                <div class="filter-badge">Pusat</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" onchange="filterRoads()" data-class="Jalan Provinsi">
                <span>Jalan Provinsi</span>
                <div class="filter-badge">Provinsi</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" onchange="filterRoads()" data-class="Jalan Kabupaten">
                <span>Jalan Kabupaten</span>
                <div class="filter-badge">Pemkab</div>
            </label>
            <label class="road-filter-item">
                <input type="checkbox" onchange="filterRoads()" data-class="Jalan Desa">
                <span>Jalan Desa</span>
                <div class="filter-badge">Lokal</div>
            </label>

            <div class="road-filter-title" style="margin-top: 1.5rem; color: #60a5fa; display: flex; justify-content: space-between; align-items: center;">
                <span>Layer 4: Aset & Infras</span>
                <div class="year-toggle-mini" style="background: rgba(255,255,255,0.05); padding: 4px; border-radius: 20px; display: flex; gap: 4px; border: 1px solid rgba(255,255,255,0.1);">
                    <button onclick="setInfraYear(2025)" id="btnYear2025" style="background: #3b82f6; color: white; border: none; padding: 2px 10px; border-radius: 15px; font-size: 0.65rem; font-weight: 800; cursor: pointer; transition: 0.3s;">2025</button>
                    <button onclick="setInfraYear(2026)" id="btnYear2026" style="background: transparent; color: white; border: none; padding: 2px 10px; border-radius: 15px; font-size: 0.65rem; font-weight: 800; cursor: pointer; opacity: 0.5; transition: 0.3s;">2026</button>
                </div>
            </div>

            <div id="cardSchool" onclick="toggleInfraLayer('school')" class="stat-card" style="cursor: pointer; transition: 0.3s; margin-bottom: 10px; border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.03); padding: 12px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.65rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px;">Sarana Pendidikan</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #10b981; margin: 4px 0;" id="countSchool">... <span style="font-size: 0.7rem; opacity: 0.5; font-weight: 400;">Titik</span></div>
                        <div style="font-size: 0.65rem; opacity: 0.6; display: flex; align-items: center; gap: 4px;">📂 <span id="budgetSchool">Menghitung...</span></div>
                    </div>
                    <div id="indicatorSchool" style="width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: 0.5s;"></div>
                </div>
            </div>

            <!-- Expandable: Paket Sekolah Tanpa Lokasi -->
            <div id="unmappedSchoolToggle" onclick="toggleUnmappedList('school')" style="display:none; cursor:pointer; padding:6px 12px; margin-bottom:10px; background:rgba(16,185,129,0.05); border:1px dashed rgba(16,185,129,0.2); border-radius:8px; font-size:0.6rem; color:#10b981; text-align:center; transition:0.3s;">
                📋 <span id="unmappedSchoolCount">0</span> paket tanpa lokasi <span id="unmappedSchoolArrow">▼</span>
            </div>
            <div id="unmappedSchoolList" style="display:none; max-height:200px; overflow-y:auto; margin-bottom:10px; padding:4px;"></div>

            <div id="cardBridge" onclick="toggleInfraLayer('bridge')" class="stat-card" style="cursor: pointer; transition: 0.3s; margin-bottom: 10px; border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.03); padding: 12px; border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.65rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px;">Struktur Jembatan (Anggaran)</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #f59e0b; margin: 4px 0;" id="countBridge">... <span style="font-size: 0.7rem; opacity: 0.5; font-weight: 400;">Unit</span></div>
                        <div style="font-size: 0.65rem; opacity: 0.6; display: flex; align-items: center; gap: 4px;">📂 <span id="budgetBridge">Menghitung...</span></div>
                    </div>
                    <div id="indicatorBridge" style="width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: 0.5s;"></div>
                </div>
            </div>

            <!-- Expandable: Paket Jembatan Tanpa Lokasi -->
            <div id="unmappedBridgeToggle" onclick="toggleUnmappedList('bridge')" style="display:none; cursor:pointer; padding:6px 12px; margin-bottom:10px; background:rgba(245,158,11,0.05); border:1px dashed rgba(245,158,11,0.2); border-radius:8px; font-size:0.6rem; color:#f59e0b; text-align:center; transition:0.3s;">
                📋 <span id="unmappedBridgeCount">0</span> paket tanpa lokasi <span id="unmappedBridgeArrow">▼</span>
            </div>
            <div id="unmappedBridgeList" style="display:none; max-height:200px; overflow-y:auto; margin-bottom:10px; padding:4px;"></div>

            <div id="cardBridgeDeep" onclick="toggleInfraLayer('bridgeDeep')" class="stat-card" style="cursor: pointer; transition: 0.3s; margin-bottom: 15px; border-left: 4px solid #3b82f6; background: rgba(59, 130, 246, 0.03); padding: 12px; border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.65rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.5px;">Jembatan (Scraped Grid AI)</div>
                        <div style="font-size: 1.3rem; font-weight: 800; color: #3b82f6; margin: 4px 0;" id="countBridgeDeep">... <span style="font-size: 0.7rem; opacity: 0.5; font-weight: 400;">Unit</span></div>
                        <div style="font-size: 0.65rem; opacity: 0.6;">📍 Data Steril Kabupaten Majalengka</div>
                    </div>
                    <div id="indicatorBridgeDeep" style="width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.1); transition: 0.5s;"></div>
                </div>
            </div>

            <input type="checkbox" id="school-filter" style="display:none" onchange="toggleSchools()">
            <input type="checkbox" id="bridge-filter" style="display:none" onchange="toggleBridges()">
            <input type="checkbox" id="bridgeDeep-filter" style="display:none" onchange="toggleDeepBridges()">
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
                    <div class="audit-desc" id="audit-pemecahan-desc">Mendeteksi paket di bawah ambang batas (200jt/400jt) yang polanya identik/berdekatan...</div>
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
    

    <!-- Sidebar Section: PAD (LAYER 6) -->
    <div id="sidebar-pad" style="display: none;">
        <div class="year-toggle" style="flex-wrap: wrap; gap: 5px;">
            <?php for($y=2018; $y<=2025; $y++): ?>
                <div class="year-btn <?= $y==2025?'active':'' ?>" onclick="switchPadYear(<?= $y ?>)" style="flex: 1 1 20%; min-width: 60px; padding: 6px; font-size: 0.75rem;"><?= $y ?></div>
            <?php endfor; ?>
        </div>
        
        <div class="stat-card" style="border-left-color: #ec4899; background: rgba(236, 72, 153, 0.05); margin-top: 1rem;">
            <h3 style="color: #f472b6;">Total PAD Kabupaten</h3>
            <div class="value" id="pad-total-val" style="font-size: 1.5rem; color: #ec4899;">Rp 0</div>
            <p id="pad-year-label" style="font-size: 0.7rem; opacity: 0.7; margin-top: 8px;">Realisasi Tahun 2025</p>
        </div>

        <div style="margin-top: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 1rem;">Kontribusi Per Kecamatan</h3>
            <div id="pad-ranking-list" style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <!-- Filled by JS -->
            </div>
        </div>

        <details class="secondary-info" style="border-left-color: #ec4899; margin-top: 1rem;">
            <summary>Metodologi & Sumber PAD</summary>
            <div class="secondary-content">
                <div class="sidebar-meta" style="border-left-color: #ec4899;">
                    <div style="margin-bottom:8px;">📅 <b>Rentang Data:</b> 2018 - 2025</div>
                    <div style="margin-bottom:8px;">📡 <b>Portal Utama:</b> <a href="https://opendata.majalengkakab.go.id/" target="_blank" style="color:#ec4899; text-decoration:none;">Open Data Majalengka ↗</a></div>
                    
                    <div style="font-size:0.65rem; opacity:0.8; margin-top:10px; border-top:1px solid rgba(255,255,255,0.05); padding-top:10px;">
                        <b>Datasets Terintegrasi:</b>
                        <ul style="padding-left:15px; margin-top:5px;">
                            <li><a href="https://opendata.majalengkakab.go.id/dataset/jumlah-pendapatan-asli-daerah-pad-di-kabupaten-majalengka-2" target="_blank" style="color:#f472b6;">Global Realisasi PAD (Bapenda)</a></li>
                            <li><a href="https://opendata.majalengkakab.go.id/dataset/jumlah-pendapatan-bphtb-berdasarkan-kecamatan-di-kabupaten-majalengka" target="_blank" style="color:#f472b6;">Penerimaan BPHTB Per Kec.</a></li>
                            <li><a href="https://opendata.majalengkakab.go.id/dataset/jumlah-pendapatan-pajak-hotel-berdasarkan-kecamatan-di-kabupaten-majalengka" target="_blank" style="color:#f472b6;">Pajak Hotel Per Kec.</a></li>
                            <li><a href="https://opendata.majalengkakab.go.id/dataset/bagi-hasil-pajak-dan-retribusi-daerah-berdasarkan-kecamatan-di-kabupaten-majalengka" target="_blank" style="color:#f472b6;">Bagi Hasil Pajak & Retribusi (DPMD)</a></li>
                        </ul>
                    </div>
                </div>
                <div class="sidebar-why" style="background: rgba(236, 72, 153, 0.08);">
                    💡 <b>Algoritma Visualisasi:</b> Peta menggunakan agregasi data sektoral (BPHTB + Hotel + Bagi Hasil) untuk menghitung bobot kontribusi ekonomi tiap kecamatan terhadap pendapatan daerah.
                </div>
            </div>
        </details>
    </div>

    <!-- Legal Links -->
    <div style="padding: 1rem 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); text-align: center;">
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Kebijakan Privasi</a>
        <span style="opacity: 0.2; margin: 0 5px;">•</span>
        <a href="legal.php" target="_blank" style="color: var(--accent); text-decoration: none; font-size: 0.7rem; opacity: 0.6;">Syarat & Ketentuan</a>
    </div>
</div> <!-- END SIDEBAR -->