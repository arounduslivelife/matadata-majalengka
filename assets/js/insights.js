/**
 * Insight tabs: Highlight, Vendor, Kecamatan (layer Realisasi)
 */
let activeInsightTab = 'ringkasan';
let kecInsightExpanded = false;

function isNarrowScreen() {
    return window.innerWidth < 768;
}

function getYearPackages() {
    if (!Array.isArray(allAudits)) return [];
    return allAudits.filter(p => p.tahun == activeYear);
}

function truncateText(str, max) {
    if (!str) return '';
    return str.length > max ? str.slice(0, max) + '…' : str;
}

function escAttr(str) {
    return String(str || '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r/g, '')
        .replace(/\n/g, ' ');
}

function setInsightChromeVisible(visible) {
    const chrome = document.getElementById('insight-chrome');
    if (chrome) chrome.style.display = visible ? '' : 'none';
}

window.switchInsightTab = function (tab) {
    activeInsightTab = tab;
    document.querySelectorAll('.insight-tab').forEach(btn => {
        const on = btn.dataset.tab === tab;
        btn.classList.toggle('active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('.insight-panel').forEach(panel => {
        const id = 'insight-panel-' + tab;
        const on = panel.id === id;
        panel.classList.toggle('active', on);
        panel.hidden = !on;
    });
};

window.toggleKecInsightExpand = function () {
    kecInsightExpanded = !kecInsightExpanded;
    renderKecInsightPanel();
};

function renderAllInsightPanels() {
    renderUnifiedPacketList();
    renderHighlightInsightPanel();
    renderVendorInsightPanel();
    renderKecInsightPanel();
    const vh = document.getElementById('insight-vendor-heading');
    const kh = document.getElementById('insight-kec-heading');
    if (vh) vh.textContent = `Top vendor · ${activeYear}`;
    if (kh) kh.textContent = `Ranking kecamatan · ${activeYear}`;
}

function renderHighlightInsightPanel() {
    const grid = document.getElementById('insight-highlight-grid');
    const line = document.getElementById('insight-summary-line');
    if (!grid) return;

    const pkgs = getYearPackages();
    if (!pkgs.length) {
        grid.innerHTML = '<p class="insight-empty">Belum ada data untuk tahun ini.</p>';
        if (line) line.textContent = '';
        return;
    }

    const topPkg = pkgs.reduce((a, b) => (b.pagu > a.pagu ? b : a), pkgs[0]);

    const vendorMap = {};
    pkgs.forEach(p => {
        const v = (p.vendor || '').trim() || 'Swakelola / tidak terdata';
        if (!vendorMap[v]) vendorMap[v] = { total: 0, count: 0 };
        vendorMap[v].total += p.pagu || 0;
        vendorMap[v].count++;
    });
    const topVendorEntry = Object.entries(vendorMap).sort((a, b) => b[1].total - a[1].total)[0];

    const stats = typeof getActiveStats === 'function' ? getActiveStats() : {};
    let topKec = { name: '-', total: 0 };
    Object.entries(stats).forEach(([name, d]) => {
        const t = d.total_pagu || 0;
        if (t > topKec.total) topKec = { name, total: t };
    });

    const near200 = pkgs.filter(p => p.pagu >= 180000000 && p.pagu < 200000000);
    const inProgress = pkgs.filter(p => {
        const s = (p.status || '').toUpperCase();
        return s.includes('PROCESS') || s.includes('BERLANGSUNG') || s.includes('PROSES');
    });

    const sortedKec = Object.entries(stats)
        .map(([n, d]) => ({ n, t: d.total_pagu || 0 }))
        .sort((a, b) => b.t - a.t);
    const totalKab = sortedKec.reduce((s, k) => s + k.t, 0);
    const top3 = sortedKec.slice(0, 3).reduce((s, k) => s + k.t, 0);
    const pct = totalKab > 0 ? Math.round((top3 / totalKab) * 100) : 0;

    if (line) {
        line.textContent = totalKab > 0
            ? `3 kecamatan teratas menyerap ±${pct}% realisasi ${activeYear}.`
            : '';
    }

    const cards = [];

    cards.push({
        label: '🏆 Paket terbesar',
        value: formatPaguJS(topPkg.pagu),
        desc: truncateText(topPkg.nama, 52),
        action: `selectPackage('${topPkg.id}','${escAttr(topPkg.kecamatan)}')`
    });

    if (topVendorEntry) {
        const [vName, vData] = topVendorEntry;
        cards.push({
            label: '🏗 Vendor teratas',
            value: formatPaguJS(vData.total),
            desc: `${escapeHTML(vName)} · ${vData.count} paket`,
            action: `showVendorIntelligence('${escAttr(vName)}')`
        });
    }

    if (topKec.total > 0) {
        cards.push({
            label: '📍 Kecamatan #1',
            value: formatPaguJS(topKec.total),
            desc: escapeHTML(topKec.name),
            action: `selectDistrict('${escAttr(topKec.name)}')`
        });
    }

    cards.push({
        label: '⚠ Dekat 200 juta',
        value: String(near200.length) + ' paket',
        desc: 'Pola ambang non-tender',
        action: near200.length ? `showFilteredPackageList('near200')` : ''
    });

    cards.push({
        label: '⏳ Sedang berjalan',
        value: String(inProgress.length) + ' paket',
        desc: 'Status proses / berlangsung',
        action: inProgress.length ? `showFilteredPackageList('inprogress')` : ''
    });

    grid.innerHTML = cards.map(c => `
        <button type="button" class="insight-card" ${c.action ? `onclick="${c.action}"` : 'disabled'}>
            <span class="insight-card-label">${c.label}</span>
            <span class="insight-card-value">${c.value}</span>
            <span class="insight-card-desc">${c.desc}</span>
        </button>
    `).join('');
}

function renderVendorInsightPanel() {
    const list = document.getElementById('insight-vendor-list');
    if (!list) return;

    const pkgs = getYearPackages();
    if (!pkgs.length) {
        list.innerHTML = '<p class="insight-empty">Belum ada data.</p>';
        return;
    }

    const vendorMap = {};
    pkgs.forEach(p => {
        const v = (p.vendor || '').trim() || 'Swakelola / tidak terdata';
        if (!vendorMap[v]) vendorMap[v] = { total: 0, count: 0, kecs: new Set() };
        vendorMap[v].total += p.pagu || 0;
        vendorMap[v].count++;
        if (p.kecamatan) vendorMap[v].kecs.add(p.kecamatan);
    });

    const limit = isNarrowScreen() ? 10 : 15;
    const rows = Object.entries(vendorMap)
        .map(([name, d]) => ({ name, ...d, kecCount: d.kecs.size }))
        .sort((a, b) => b.total - a.total)
        .slice(0, limit);

    list.innerHTML = rows.map((r, i) => `
        <button type="button" class="insight-rank-row" onclick="showVendorIntelligence('${escAttr(r.name)}')">
            <span class="insight-rank-num">#${i + 1}</span>
            <span class="insight-rank-body">
                <span class="insight-rank-title">${escapeHTML(r.name)}</span>
                <span class="insight-rank-meta">${r.count} paket · ${r.kecCount} kecamatan</span>
            </span>
            <span class="insight-rank-val">${formatPaguJS(r.total)}</span>
        </button>
    `).join('');
}

function renderKecInsightPanel() {
    const list = document.getElementById('insight-kec-list');
    const expandBtn = document.getElementById('insight-kec-expand');
    if (!list) return;

    const stats = typeof getActiveStats === 'function' ? getActiveStats() : {};
    let rows = Object.entries(stats)
        .map(([name, d]) => ({
            name,
            total: d.total_pagu || 0,
            count: d.total || 0
        }))
        .filter(r => r.total > 0)
        .sort((a, b) => b.total - a.total);

    if (!rows.length) {
        const pkgs = getYearPackages();
        const agg = {};
        pkgs.forEach(p => {
            const k = p.kecamatan || 'Tidak terpetakan';
            if (!agg[k]) agg[k] = { total: 0, count: 0 };
            agg[k].total += p.pagu || 0;
            agg[k].count++;
        });
        rows = Object.entries(agg)
            .map(([name, d]) => ({ name, total: d.total, count: d.count }))
            .sort((a, b) => b.total - a.total);
    }

    const maxTotal = rows[0]?.total || 1;
    const defaultLimit = isNarrowScreen() ? 8 : 26;
    const showAll = kecInsightExpanded || rows.length <= defaultLimit;
    const visible = showAll ? rows : rows.slice(0, defaultLimit);

    if (expandBtn) {
        expandBtn.style.display = rows.length > defaultLimit ? '' : 'none';
        expandBtn.textContent = kecInsightExpanded
            ? 'Tampilkan lebih ringkas'
            : `Tampilkan semua (${rows.length} kecamatan)`;
    }

    list.innerHTML = visible.map((r, i) => {
        const pct = Math.max(4, Math.round((r.total / maxTotal) * 100));
        return `
            <button type="button" class="insight-kec-row" onclick="selectDistrict('${escAttr(r.name)}')">
                <div class="insight-kec-head">
                    <span class="insight-rank-num">#${i + 1}</span>
                    <span class="insight-kec-name">${escapeHTML(r.name)}</span>
                    <span class="insight-rank-val">${formatPaguJS(r.total)}</span>
                </div>
                <div class="insight-kec-bar-wrap" aria-hidden="true">
                    <div class="insight-kec-bar" style="width:${pct}%"></div>
                </div>
                <span class="insight-rank-meta">${r.count} paket</span>
            </button>
        `;
    }).join('');
}

window.showFilteredPackageList = function (filterType) {
    const pkgs = getYearPackages();
    let filtered = [];
    let title = '';

    if (filterType === 'near200') {
        filtered = pkgs.filter(p => p.pagu >= 180000000 && p.pagu < 200000000);
        title = 'Paket 180–200 juta';
    } else if (filterType === 'inprogress') {
        filtered = pkgs.filter(p => {
            const s = (p.status || '').toUpperCase();
            return s.includes('PROCESS') || s.includes('BERLANGSUNG') || s.includes('PROSES');
        });
        title = 'Paket sedang berjalan';
    }

    filtered.sort((a, b) => b.pagu - a.pagu);

    openSidebar();
    setInsightChromeVisible(false);
    const detailView = document.getElementById('realisasi-detail-view');
    if (detailView) {
        detailView.style.display = 'block';
        let html = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="margin:0; font-size:0.95rem;">${escapeHTML(title)} · ${activeYear}</h3>
                <button type="button" onclick="resetRealisasiSidebar()" style="background:rgba(255,255,255,0.06); border:none; color:#94a3b8; padding:6px 10px; border-radius:8px; cursor:pointer; font-size:0.7rem;">← Kembali</button>
            </div>
            <div class="insight-filter-list">
        `;
        if (!filtered.length) {
            html += '<p class="insight-empty">Tidak ada paket dalam kategori ini.</p>';
        } else {
            filtered.slice(0, isNarrowScreen() ? 30 : 50).forEach(p => {
                html += `
                    <div class="packet-item" onclick="selectPackage('${p.id}','${escAttr(p.kecamatan)}')">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                            <b style="color:var(--accent);">${formatPaguJS(p.pagu)}</b>
                        </div>
                        <div style="font-weight:600; font-size:0.85rem;">${escapeHTML(p.nama)}</div>
                        <div style="font-size:0.65rem; opacity:0.5;">${escapeHTML(p.kecamatan)}</div>
                    </div>
                `;
            });
        }
        html += '</div>';
        detailView.innerHTML = html;
        detailView.scrollTop = 0;
    }
};

window.addEventListener('resize', function () {
    if (currentMode === 'sirup' && document.getElementById('insight-chrome')) {
        renderAllInsightPanels();
    }
});
