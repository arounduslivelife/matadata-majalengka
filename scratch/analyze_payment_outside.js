const fs = require('fs');

const rawData = fs.readFileSync('c:\\xampp\\htdocs\\matadata\\data\\realisasi20252026alldept.json', 'utf8');
const json = JSON.parse(rawData);
const data = json.data;

const targetStatus = "PAYMENT OUTSIDE SYSTEM";
const filteredData = data.filter(item => item.status_paket === targetStatus);

const stats = {
    count: filteredData.length,
    totalValue: 0,
    bySatker: {},
    byVendor: {},
    byDana: {},
    byMetode: {},
    byYear: {}
};

filteredData.forEach(item => {
    const val = item.total_nilai_rp || 0;
    const satker = item.nama_satuan_kerja;
    const vendor = item.nama_penyedia;
    const dana = item.sumber_dana;
    const metode = item.metode_pengadaan;
    const year = item.tahun_anggaran;

    stats.totalValue += val;
    stats.bySatker[satker] = (stats.bySatker[satker] || 0) + val;
    stats.byVendor[vendor] = (stats.byVendor[vendor] || 0) + val;
    stats.byDana[dana] = (stats.byDana[dana] || 0) + val;
    stats.byMetode[metode] = (stats.byMetode[metode] || 0) + val;
    stats.byYear[year] = (stats.byYear[year] || 0) + val;
});

const getTop = (obj, limit = 5) => {
    return Object.entries(obj)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit)
        .map(([name, value]) => ({ name, value }));
};

const results = {
    status: targetStatus,
    summary: {
        count: stats.count,
        total_value: stats.totalValue,
        byYear: stats.byYear
    },
    topSatker: getTop(stats.bySatker, 10),
    topVendor: getTop(stats.byVendor, 10),
    byDana: stats.byDana,
    byMetode: stats.byMetode
};

console.log(JSON.stringify(results, null, 2));
