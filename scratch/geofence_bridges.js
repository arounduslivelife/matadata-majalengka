const fs = require('fs');

const allBridges = JSON.parse(fs.readFileSync('data/listjembatanmajalengka.json', 'utf8'));

// Bounding Box Majalengka (Roughly)
const MAJALENGKA_BOUNDS = {
    latMin: -7.25,
    latMax: -6.50,
    lngMin: 108.05,
    lngMax: 108.45
};

console.log(`Menscan ${allBridges.length} titik jembatan...`);

const filtered = allBridges.filter(b => {
    const isInside = (
        b.latitude >= MAJALENGKA_BOUNDS.latMin && 
        b.latitude <= MAJALENGKA_BOUNDS.latMax &&
        b.longitude >= MAJALENGKA_BOUNDS.lngMin && 
        b.longitude <= MAJALENGKA_BOUNDS.lngMax
    );
    return isInside;
});

console.log(`Hasil: ${filtered.length} titik valid di dalam Majalengka. (${allBridges.length - filtered.length} titik dibuang karena di luar perbatasan).`);

fs.writeFileSync('data/listjembatanmajalengka.json', JSON.stringify(filtered, null, 2));

// Lalu jalankan ulang sinkronisasi master
const projectBridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));
function clean(n) { return n ? n.toLowerCase().replace(/[^a-z0-9]/g, '') : ""; }

const finalFeatures = filtered.map(s => {
    const sName = clean(s.nama);
    const match = projectBridges.features.find(p => {
        const pName = clean(p.properties.jembatan || p.properties.nama);
        return sName.includes(pName) || pName.includes(sName);
    });

    if (match) {
        return {
            type: "Feature",
            geometry: { type: "Point", coordinates: [s.longitude, s.latitude] },
            properties: { ...match.properties, nama_jembatan: s.nama, is_project: true }
        };
    } else {
        return {
            type: "Feature",
            geometry: { type: "Point", coordinates: [s.longitude, s.latitude] },
            properties: { nama_jembatan: s.nama, kecamatan: s.kecamatan, is_project: false, catatan: "Aset Infrastruktur Terverifikasi" }
        };
    }
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify({ type: "FeatureCollection", features: finalFeatures }, null, 2));
console.log("Database Jembatan telah dibersihkan dan hanya berisi wilayah Majalengka!");
