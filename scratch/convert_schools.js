const fs = require('fs');

const rawData = JSON.parse(fs.readFileSync('./data/found_schools_ultra_v2.json', 'utf8'));

function getTipe(nama) {
    const n = nama.toLowerCase();
    if (n.includes('tk ') || n.includes(' tk') || n.startsWith('tk') || n.includes('taman kanak') || n.includes('ra ') || n.startsWith('ra')) return 'TK/RA';
    if (n.includes('paud') || n.includes('kober') || n.includes('kb ')) return 'PAUD';
    if (n.includes('sd ') || n.includes(' sd') || n.startsWith('sdn') || n.includes('mis ') || n.startsWith('mi ') || n.includes('sekolah dasar') || n.includes('madrasah ibtidaiyah')) return 'SD/MI';
    if (n.includes('smp') || n.includes('mts') || n.includes('madrasah tsanawiyah') || n.includes('sekolah menengah pertama')) return 'SMP/MTs';
    if (n.includes('sma') || n.includes('man ') || n.includes('mas ') || n.includes('sekolah menengah atas') || n.includes('madrasah aliyah')) return 'SMA/MA';
    if (n.includes('smk') || n.includes('sekolah menengah kejuruan')) return 'SMK';
    if (n.includes('pesantren') || n.includes('pondok')) return 'Pesantren';
    if (n.includes('diniyah') || n.includes('mda') || n.includes('tpq') || n.includes('tpa') || n.includes('dtm')) return 'Diniyah/TPQ';
    if (n.includes('slb') || n.includes('sekolah luar biasa')) return 'SLB';
    if (n.includes('pkbm') || n.includes('pusat kegiatan belajar')) return 'PKBM';
    if (n.includes('universitas') || n.includes('institut') || n.includes('sekolah tinggi') || n.includes('politeknik') || n.includes('akademi')) return 'Perguruan Tinggi';
    if (n.includes('yayasan')) return 'Yayasan Pendidikan';
    return 'Lainnya';
}

const geojson = {
    type: "FeatureCollection",
    features: []
};

// Map to track duplicates by coordinate and name (fuzzy)
const seen = new Set();

let converted = 0;
rawData.data.forEach(item => {
    if (!item.lat || !item.lng) return;

    // A little coordinate rounding to filter out exact duplicate pins
    const latRound = item.lat.toFixed(5);
    const lngRound = item.lng.toFixed(5);
    const uniqueKey = `${item.nama.toLowerCase()}_${latRound}_${lngRound}`;
    
    if (seen.has(uniqueKey)) return;
    seen.add(uniqueKey);

    const tipe = getTipe(item.nama);

    geojson.features.push({
        type: "Feature",
        geometry: {
            type: "Point",
            coordinates: [item.lng, item.lat] // GeoJSON order: lng, lat
        },
        properties: {
            nama: item.nama,
            kecamatan: item.kecamatan,
            tipe: tipe,
            verified: true, // Assuming this new dataset is considered verified
            sumber: 'Ultra v2'
        }
    });
    converted++;
});

fs.writeFileSync('./data/schools_ultra_clean.geojson', JSON.stringify(geojson, null, 2));
console.log(`Successfully converted ${converted} unique schools to data/schools_ultra_clean.geojson`);
