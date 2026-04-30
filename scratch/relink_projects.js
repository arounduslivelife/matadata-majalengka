const fs = require('fs');

const schools = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
const realisasi = JSON.parse(fs.readFileSync('data/realisasi_sekolah.geojson', 'utf8'));

function normalize(str) {
    if (!str) return '';
    return str.toLowerCase()
        .replace(/sd\s*negeri/g, 'sdn')
        .replace(/smp\s*negeri/g, 'smpn')
        .replace(/sma\s*negeri/g, 'sman')
        .replace(/smk\s*negeri/g, 'smkn')
        .replace(/[^a-z0-9]/g, '');
}

const schoolMap = new Map();
schools.features.forEach(f => {
    const key = normalize(f.properties.nama);
    if (!schoolMap.has(key)) schoolMap.set(key, []);
    schoolMap.get(key).push(f);
});

let matchedCount = 0;
realisasi.data.forEach(paket => {
    const paketName = paket.nama_paket;
    const nPaket = normalize(paketName);
    let found = false;

    for (const [key, features] of schoolMap) {
        if (nPaket.includes(key) && key.length > 3) {
            features[0].properties.paket = features[0].properties.paket || [];
            features[0].properties.paket.push(paket);
            features[0].properties.is_project = true;
            matchedCount++;
            found = true;
            break;
        }
    }
});

// Also try reverse: check if any part of the school name is in the packet name
// e.g. "SMPN 3 Banjaran" vs "SMP Negeri 3 Banjaran"
// The normalization already handles sdn -> sd negeri? 
// No, my new normalize does the opposite: sd negeri -> sdn.

console.log(`Final Match: ${matchedCount} projects.`);
fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(schools, null, 2));
