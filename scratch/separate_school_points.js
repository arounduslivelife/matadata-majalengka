const fs = require('fs');

const districts = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const geojson = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

// Fungsi menghitung titik tengah poligon sederhana
function getCenter(poly) {
    let lat = 0, lon = 0, count = 0;
    const ring = Array.isArray(poly[0][0]) ? poly[0] : poly;
    ring.forEach(p => {
        lon += p[0]; lat += p[1]; count++;
    });
    return [lat / count, lon / count];
}

const kecCenters = {};
districts.features.forEach(f => {
    const center = getCenter(f.geometry.coordinates[0]);
    kecCenters[f.properties.nm_kecamatan] = center;
});

// Update GeoJSON dengan Jittering
geojson.features = geojson.features.map(f => {
    // Jika belum ada catatan "Koordinat Presisi", berikan lokasi kecamatan + jitter
    if (f.properties.catatan !== "Koordinat Presisi (Google Maps)") {
        const center = kecCenters[f.properties.kecamatan] || [-6.837, 108.227];
        
        // Tambahkan variasi acak sekitar ~200-500 meter agar tidak tumpang tindih
        const jitterLat = (Math.random() - 0.5) * 0.005;
        const jitterLon = (Math.random() - 0.5) * 0.005;
        
        f.geometry.coordinates = [center[1] + jitterLon, center[0] + jitterLat];
    }
    return f;
});

fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geojson, null, 2));
console.log("Pemisahan Titik Sekolah Berhasil!");
