const fs = require('fs');

const rawData = fs.readFileSync('c:\\xampp\\htdocs\\matadata\\data\\realisasi20252026alldept.json', 'utf8');
const json = JSON.parse(rawData);
const data = json.data;

const targetStatus = "PAYMENT OUTSIDE SYSTEM";
const filteredData = data.filter(item => item.status_paket === targetStatus);

const packageSamples = filteredData.slice(0, 20).map(item => ({
    satker: item.nama_satuan_kerja.split(' - ')[0],
    nama_paket: item.nama_paket,
    nilai: item.total_nilai_rp
}));

const categoryStats = {};
filteredData.forEach(item => {
    const cat = item.jenis_pengadaan || 'Lainnya';
    categoryStats[cat] = (categoryStats[cat] || 0) + 1;
});

const results = {
    samples: packageSamples,
    categories: categoryStats
};

console.log(JSON.stringify(results, null, 2));
