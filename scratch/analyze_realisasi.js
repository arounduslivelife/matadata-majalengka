const fs = require('fs');

const rawData = fs.readFileSync('c:\\xampp\\htdocs\\matadata\\data\\realisasi20252026alldept.json', 'utf8');
const json = JSON.parse(rawData);
const data = json.data;

const stats = {
    bySatker: {},
    byVendor: {},
    byDana: {},
    byMetode: {},
    pdnValue: 0,
    nonPdnValue: 0,
    totalValue: 0,
    byYear: {
        2025: { count: 0, value: 0 },
        2026: { count: 0, value: 0 }
    }
};

data.forEach(item => {
    const val = item.total_nilai_rp || 0;
    const pdn = item.nilai_pdn_rp || 0;
    const satker = item.nama_satuan_kerja;
    const vendor = item.nama_penyedia;
    const dana = item.sumber_dana;
    const metode = item.metode_pengadaan;
    const year = item.tahun_anggaran;

    stats.totalValue += val;
    stats.pdnValue += pdn;
    stats.nonPdnValue += (val - pdn);

    if (year === 2025 || year === 2026) {
        stats.byYear[year].count++;
        stats.byYear[year].value += val;
    }

    stats.bySatker[satker] = (stats.bySatker[satker] || 0) + val;
    stats.byVendor[vendor] = (stats.byVendor[vendor] || 0) + val;
    stats.byDana[dana] = (stats.byDana[dana] || 0) + val;
    stats.byMetode[metode] = (stats.byMetode[metode] || 0) + val;
});

// Sort and pick top 5
const getTop = (obj, limit = 5) => {
    return Object.entries(obj)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit)
        .map(([name, value]) => ({ name, value }));
};

const results = {
    summary: {
        total_value: stats.totalValue,
        pdn_total: stats.pdnValue,
        non_pdn_total: stats.nonPdnValue,
        pdn_percentage: (stats.pdnValue / stats.totalValue) * 100,
        byYear: stats.byYear
    },
    topSatker: getTop(stats.bySatker, 10),
    topVendor: getTop(stats.byVendor, 10),
    byDana: stats.byDana,
    byMetode: stats.byMetode
};

console.log(JSON.stringify(results, null, 2));
