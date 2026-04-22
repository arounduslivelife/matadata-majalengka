
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
                <div class="legend-item"><div class="legend-color" style="background:#10b981"></div><span>    Rp800 Juta</span></div>
            `;
        } else if (mode === 'kemiskinan') {
            html = `
                <div class="legend-title">Sebaran KPM Bansos</div>
                <div class="legend-item"><div class="legend-color" style="background:#7c2d12"></div><span>> 8.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#9a3412"></div><span>> 6.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#c2410c"></div><span>> 4.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#ea580c"></div><span>> 2.000 KPM</span></div>
                <div class="legend-item"><div class="legend-color" style="background:#f97316"></div><span>    2.000 KPM</span></div>
            `;
        } else if (mode === 'infrastruktur') {
            html = `
                <div class="legend-title">Kemantapan Jalan</div>
                <div class="legend-item"><div class="legend-color" style="background:#d946ef"></div><span>Mantap (   90%)</span></div>
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
            subtitleEl.innerText = mode === 'sirup' ? 'Operasi Ratu Boko     AI Audit Pengadaan' : 
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
            
            const winnerBox = document.getElementById('p-winner-box');
            if (p.pemenang) {
                winnerBox.style.display = 'block';
                document.getElementById('p-pemenang').innerText = p.pemenang;
                document.getElementById('p-pemenang-npwp').innerText = 'NPWP: ' + (p.pemenang_npwp || '-');
            } else {
                winnerBox.style.display = 'none';
            }
            
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
                    if (data.status === 'WAITING') text = "    " + data.kecamatan;
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

                                layer.bindPopup(`<div class="info-box" style="width:250px;"><b style="font-size:1.1rem; color:#3b82f6;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Sumber: SiRUP LKPP T.A 2025</span><hr style="opacity:0.2; margin:8px 0;"><b>Anggaran Audit:</b> <span style="color:var(--accent)">${formatPaguJS(d.total_pagu)}</span><br>Temuan High Risk: <span style="color:${d.high_risk > 0 ? '#ef4444':'#10b981'}">${d.high_risk} Paket</span>${packetHtml}<hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">📌 ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(59,130,246,0.2); border-radius:8px; color:#60a5fa; text-decoration:none; font-size:0.75rem; font-weight:600;">🗺️ Buka di Google Maps</a></div>`);
                            } else if (currentMode === 'kemiskinan') {
                                    const center = layer.getBounds().getCenter();
                                    const lat = center.lat.toFixed(5);
                                    const lng = center.lng.toFixed(5);
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#f59e0b;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">T.A 2024/2025 | Sumber: Dinsos/DTKS</span><hr style="opacity:0.2; margin:8px 0;"><b>Jumlah KPM Miskin:</b><br><span style="font-size:1.8rem; font-weight:600; color:#f59e0b;">${p.count.toLocaleString('id-ID')}</span><div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:5px;"><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM BPNT</div><div style="font-weight:bold;">${p.bpnt.toLocaleString('id-ID')}</div></div><div style="background:rgba(255,255,255,0.05); padding:5px; border-radius:5px; text-align:center;"><div style="font-size:0.6rem; opacity:0.6;">KPM PKH</div><div style="font-weight:bold;">${p.pkh.toLocaleString('id-ID')}</div></div></div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">     ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(245,158,11,0.2); border-radius:8px; color:#fbbf24; text-decoration:none; font-size:0.75rem; font-weight:600;">        Buka di Google Maps    </a></div>`);
                                } else {
                                    // Infrastruktur Heatmap Popup
                                    const center = layer.getBounds().getCenter();
                                    const lat = center.lat.toFixed(5);
                                    const lng = center.lng.toFixed(5);
                                    layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#06b6d4;">Kecamatan ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Data 2024 | Sumber: DPUTR Majalengka</span><hr style="opacity:0.2; margin:8px 0;"><b>Level Kemantapan:</b><br><span style="font-size:1.8rem; font-weight:600; color:#06b6d4;">${p.road_pct}%</span><br><div style="font-size:0.7rem; opacity:0.6; margin-top:5px;">Indeks berdasarkan integrasi SP4N-LAPOR! & Statistik Jalan Kabupaten.</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">     ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.2); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.75rem; font-weight:600;">        Buka di Google Maps    </a></div>`);
                                }
                            }
                        } else {
                            villageLayers[name] = layer;
                            const v = villageStats[name] || { budget: 0, risk: 0, kecamatan: 'Unknown' };
                            const center = layer.getBounds().getCenter();
                            const lat = center.lat.toFixed(5);
                            const lng = center.lng.toFixed(5);
                            layer.bindPopup(`<div class="info-box" style="width:220px;"><b style="font-size:1.1rem; color:#10b981;">Desa ${name}</b><br><span style="font-size:0.7rem; opacity:0.5;">Kecamatan ${v.kecamatan}</span><hr style="opacity:0.2; margin:8px 0;"><b>Alokasi Dana Desa T.A 2025:</b><br><span style="font-size:1.4rem; font-weight:600; color:#10b981;">${formatPaguJS(v.budget)}</span><br><div style="margin-top:10px; font-size:0.75rem; opacity:0.7; line-height:1.4;">Sumber: Alokasi TKD Kemenkeu RI T.A 2025</div><hr style="opacity:0.1; margin:8px 0;"><div style="font-size:0.65rem; opacity:0.5;">     ${lat}, ${lng}</div><a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(16,185,129,0.2); border-radius:8px; color:#34d399; text-decoration:none; font-size:0.75rem; font-weight:600;">        Buka di Google Maps    </a></div>`);
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
                                    const p = f.properties;
                                    const statusColor = p.status === 'Rusak' ? '#ef4444' : (p.status === 'Perbaikan' ? '#f59e0b' : '#22d3ee');
                                    const statusIcon = p.status === 'Rusak' ? '(X)' : (p.status === 'Perbaikan' ? '(!)' : '(V)');

                                    // Build rich contractor section HTML
                                    let contractorHtml = '';
                                    if (p.pemenang) {
                                        const realisasiColor = (p.status_realisasi === 'Realisasi') ? '#34d399' : '#fbbf24';
                                        const realisasiIcon = (p.status_realisasi === 'Realisasi') ? '[OK]' : '[PLAN]';
                                        contractorHtml = `
                                            <div style="margin-top:10px; background:rgba(6,182,212,0.08); border:1px solid rgba(6,182,212,0.2); border-radius:10px; padding:10px;">
                                                <div style="font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#06b6d4; opacity:0.8; margin-bottom:6px;">DATA PENGGARAP</div>
                                                <div style="font-size:0.85rem; font-weight:700; color:#fbbf24; margin-bottom:2px;">${escapeHTML(p.pemenang)}</div>
                                                <div style="font-size:0.65rem; color:#22d3ee; font-family:monospace; opacity:0.8; margin-bottom:8px;">NPWP: ${escapeHTML(p.pemenang_npwp || 'Tidak Tersedia')}</div>
                                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:5px; margin-bottom:6px;">
                                                    <div style="background:rgba(255,255,255,0.05); border-radius:6px; padding:5px; text-align:center;">
                                                        <div style="font-size:0.7rem; font-weight:700; color:#22d3ee;">${formatPaguJS(p.pagu_proyek)}</div>
                                                        <div style="font-size:0.55rem; opacity:0.5;">Nilai Proyek</div>
                                                    </div>
                                                    <div style="background:rgba(255,255,255,0.05); border-radius:6px; padding:5px; text-align:center;">
                                                        <div style="font-size:0.7rem; font-weight:700; color:#22d3ee;">${p.tahun || '-'}</div>
                                                        <div style="font-size:0.55rem; opacity:0.5;">Tahun</div>
                                                    </div>
                                                </div>
                                                <div style="font-size:0.65rem; font-weight:700; color:${realisasiColor}; padding:3px 8px; background:rgba(255,255,255,0.05); border-radius:20px; display:inline-block;">${realisasiIcon} ${escapeHTML(p.status_realisasi || '-')}</div>
                                                ${p.metode ? `<div style="font-size:0.6rem; opacity:0.45; margin-top:5px;">Metode: ${escapeHTML(p.metode)}</div>` : ''}
                                            </div>`;
                                        if (p.nama_paket_tender) {
                                            contractorHtml += `<div style="margin-top:8px; font-size:0.65rem; opacity:0.5; line-height:1.4; font-style:italic;">${escapeHTML(p.nama_paket_tender)}</div>`;
                                        }
                                    }

                                    layer.bindPopup(`<div class="info-box" style="width:260px;">
                                        <b style="color:#22d3ee; font-size:0.95rem;">${escapeHTML(p.name || 'Jalan Tanpa Nama')}</b>
                                        <div style="font-size:0.7rem; opacity:0.5; margin-top:2px;">Kecamatan ${escapeHTML(p.kecamatan || '')}</div>
                                        <hr style="opacity:0.15; margin:8px 0;">
                                        <div style="font-size:0.85rem;">${statusIcon} Status: <b style="color:${statusColor};">${escapeHTML(p.status)}</b></div>
                                        ${contractorHtml}
                                        <hr style="opacity:0.1; margin:8px 0;">
                                        <div style="font-size:0.6rem; opacity:0.4;">     ${lat}, ${lng}</div>
                                        <a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" style="display:block; margin-top:6px; text-align:center; padding:6px; background:rgba(6,182,212,0.15); border-radius:8px; color:#22d3ee; text-decoration:none; font-size:0.72rem; font-weight:600;">Buka di Google Maps</a>
                                    </div>`);

                                    // Store contractor name on layer for filter logic
                                    layer._contractorName = p.pemenang || null;
                                }
                            }).addTo(map);
                        });
                }
            });
    }

    // ===== CONTRACTOR FILTER LOGIC =====
    let activeContractorFilter = null;

    function selectContractor(contractorName, cardIndex) {
        if (!roadLayer) return;
        
        // Toggle: click same contractor again to reset
        if (activeContractorFilter === contractorName) {
            resetContractorFilter();
            return;
        }

        activeContractorFilter = contractorName;

        // Update sidebar card states
        document.querySelectorAll('.contractor-item').forEach(el => el.classList.remove('selected'));
        const card = document.getElementById('contractor-card-' + cardIndex);
        if (card) card.classList.add('selected');

        // Show filter active banner
        const banner = document.getElementById('filterActiveBanner');
        if (banner) {
            banner.classList.add('show');
            const nameEl = document.getElementById('filterActiveName');
            if (nameEl) nameEl.textContent = contractorName;
        }

        // Apply dim/highlight to road layer
        roadLayer.eachLayer(function(l) {
            const isMatch = l._contractorName && l._contractorName.trim() === contractorName.trim();
            if (isMatch) {
                l.setStyle({ color: '#f59e0b', weight: 8, opacity: 1, dashArray: null });
                l.bringToFront();
            } else {
                l.setStyle({ color: '#1e293b', weight: 1, opacity: 0.1 });
            }
        });

        // Dim the district polygons for contrast
        if (geoLayer) {
            geoLayer.eachLayer(function(l) {
                l.setStyle({ fillOpacity: 0.05, opacity: 0.05 });
            });
        }
    }

    function resetContractorFilter() {
        activeContractorFilter = null;
        document.querySelectorAll('.contractor-item').forEach(el => el.classList.remove('selected'));
        const banner = document.getElementById('filterActiveBanner');
        if (banner) banner.classList.remove('show');

        // Restore road layer styles
        if (roadLayer) {
            roadLayer.eachLayer(function(l) {
                const status = l.feature ? l.feature.properties.status : null;
                let color = '#22d3ee';
                if (status === 'Rusak') color = '#ef4444';
                if (status === 'Perbaikan') color = '#f59e0b';
                l.setStyle({ color: color, weight: 4, opacity: 0.9 });
            });
        }

        // Restore district polygon opacity
        if (geoLayer) {
            geoLayer.eachLayer(function(l) {
                l.setStyle({ fillOpacity: 0.6, opacity: 1 });
            });
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

