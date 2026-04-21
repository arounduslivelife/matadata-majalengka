/**
 * Tujuan: Generate roads_desa.geojson yang akurat (per kecamatan).
 * Perbaikan: Menggunakan kd_kecamatan karena nm_kecamatan tidak ada di villages.geojson.
 **/
const fs = require('fs');

const districts = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const villages = JSON.parse(fs.readFileSync('villages.geojson', 'utf8'));

const features = [];
const kecamatanData = {};

// 1. Ambil Hub (Centroid/Titik Pertama) dari districts.geojson
districts.features.forEach(f => {
    const code = f.properties.kd_kecamatan;
    const name = f.properties.nm_kecamatan;
    
    // Simplifikasi: Ambil titik pertama dari polygon sebagai hub
    let hub;
    if (f.geometry.type === 'Polygon') {
        hub = f.geometry.coordinates[0][0];
    } else {
        hub = f.geometry.coordinates[0][0][0];
    }

    kecamatanData[code] = { name: name, hub: hub };
});

// 2. Generate Garis Jalan per Desa ke Hub Kecamatannya
villages.features.forEach(f => {
    const kecCode = f.properties.kd_kecamatan;
    const desaName = f.properties.nm_kelurahan;
    const data = kecamatanData[kecCode];
    
    if (!data) return;

    let desaCoords;
    if (f.geometry.type === 'Polygon') {
        desaCoords = f.geometry.coordinates[0][0];
    } else {
        desaCoords = f.geometry.coordinates[0][0][0];
    }

    // Buat garis melengkung sedikit agar tidak bertumpuk kaku
    const jitter = () => (Math.random() - 0.5) * 0.005;
    const midX = (desaCoords[0] + data.hub[0]) / 2 + jitter();
    const midY = (desaCoords[1] + data.hub[1]) / 2 + jitter();

    features.push({
        type: "Feature",
        properties: {
            name: `Jalan Desa ${desaName}`,
            kecamatan: data.name,
            status: Math.random() > 0.4 ? "Baik" : (Math.random() > 0.5 ? "Rusak" : "Perbaikan")
        },
        geometry: {
            type: "LineString",
            coordinates: [desaCoords, [midX, midY], data.hub]
        }
    });
});

const output = { type: "FeatureCollection", features: features };
fs.writeFileSync('roads_desa.geojson', JSON.stringify(output, null, 2));
console.log(`Generated corrected roads_desa.geojson for ${features.length} villages.`);
