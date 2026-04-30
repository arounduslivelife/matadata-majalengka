const fs = require('fs');

// BOUNDS STRICT MAJALENGKA
const BOUNDS = {
    latMin: -7.15,
    latMax: -6.60,
    lngMin: 108.10,
    lngMax: 108.40
};

// TITIK PALSU (CENTER KABUPATEN YANG SERING JADI FALLBACK)
const FAKE_CENTER = { lng: 108.2583432, lat: -7.0451931 };

function cleanGeoJSON(filename, type) {
    const data = JSON.parse(fs.readFileSync(filename, 'utf8'));
    console.log(`Pembersihan ${type}: ${data.features.length} fitur ditemukan.`);

    const filtered = data.features.filter(f => {
        const [lng, lat] = f.geometry.coordinates;

        // 1. Cek apakah ini titik palsu (fallback Google)
        const isFake = (Math.abs(lng - FAKE_CENTER.lng) < 0.0001 && Math.abs(lat - FAKE_CENTER.lat) < 0.0001);
        
        // 2. Cek apakah di luar geofence
        const isOutside = (lat < BOUNDS.latMin || lat > BOUNDS.latMax || lng < BOUNDS.lngMin || lng > BOUNDS.lngMax);

        return !isFake && !isOutside;
    });

    console.log(`   -> ${data.features.length - filtered.length} titik "sampah" dibuang.`);
    console.log(`   -> Sisa ${filtered.length} titik valid terverifikasi.`);

    fs.writeFileSync(filename, JSON.stringify({ type: "FeatureCollection", features: filtered }, null, 2));
}

// EKSEKUSI PEMBERSIHAN MASSAL
cleanGeoJSON('data/sarana_pendidikan.geojson', 'Sekolah');
cleanGeoJSON('data/jembatan_kabupaten.geojson', 'Jembatan');

console.log("\n--- OPERASI STERILISASI DATA SELESAI ---");
