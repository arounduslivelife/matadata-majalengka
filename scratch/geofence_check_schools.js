const fs = require('fs');

// Load data
const schools = JSON.parse(fs.readFileSync('./data/found_schools_ultra.json', 'utf8'));
const districts = JSON.parse(fs.readFileSync('./districts.geojson', 'utf8'));

// Ray-casting point-in-polygon algorithm
function pointInPolygon(lat, lng, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const xi = polygon[i][1], yi = polygon[i][0]; // GeoJSON is [lng, lat]
        const xj = polygon[j][1], yj = polygon[j][0];
        const intersect = ((yi > lng) !== (yj > lng)) &&
            (lat < (xj - xi) * (lng - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

// Check if point is in any of the district polygons (= inside Majalengka)
function isInsideMajalengka(lat, lng) {
    for (const feature of districts.features) {
        const geom = feature.geometry;
        let polygons = [];
        if (geom.type === 'Polygon') {
            polygons = [geom.coordinates[0]]; // outer ring only
        } else if (geom.type === 'MultiPolygon') {
            polygons = geom.coordinates.map(p => p[0]); // outer ring of each
        }
        for (const poly of polygons) {
            if (pointInPolygon(lat, lng, poly)) {
                return feature.properties.nm_kecamatan || feature.properties.NAMOBJ || 'Unknown';
            }
        }
    }
    return null;
}

console.log('=== GEOFENCE CHECK: found_schools_ultra.json vs Batas Majalengka ===\n');
console.log('Total sekolah:', schools.data.length);
console.log('Mengecek setiap koordinat terhadap batas kecamatan...\n');

let inside = 0;
let outside = 0;
const outsideList = [];
const mismatchKec = [];

for (const s of schools.data) {
    const matchedKec = isInsideMajalengka(s.lat, s.lng);
    if (matchedKec) {
        inside++;
        // Check if kecamatan matches
        if (matchedKec.toUpperCase() !== s.kecamatan.toUpperCase()) {
            mismatchKec.push({
                nama: s.nama,
                kecamatan_claim: s.kecamatan,
                kecamatan_actual: matchedKec,
                lat: s.lat,
                lng: s.lng
            });
        }
    } else {
        outside++;
        outsideList.push(s);
    }
}

console.log('--- HASIL ---');
console.log(`✅ DALAM batas Majalengka : ${inside} (${(inside/schools.data.length*100).toFixed(1)}%)`);
console.log(`❌ LUAR batas Majalengka  : ${outside} (${(outside/schools.data.length*100).toFixed(1)}%)`);

console.log(`\n🔀 Kecamatan MISMATCH (koordinat di kecamatan lain): ${mismatchKec.length}`);

// Group outside by kecamatan
const outByKec = {};
outsideList.forEach(s => { outByKec[s.kecamatan] = (outByKec[s.kecamatan] || 0) + 1; });

console.log('\n--- Detail entri di LUAR Majalengka per Kecamatan ---');
Object.entries(outByKec).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => {
    console.log(`  ${k}: ${v} entri`);
});

console.log('\n--- Daftar Lengkap Entri di LUAR Majalengka ---');
outsideList.forEach(s => {
    console.log(`  [${s.kecamatan}] "${s.nama}" → (${s.lat}, ${s.lng})`);
});

console.log('\n--- Sampel Kecamatan MISMATCH (top 30) ---');
mismatchKec.slice(0, 30).forEach(m => {
    console.log(`  "${m.nama}" → claim: ${m.kecamatan_claim}, actual: ${m.kecamatan_actual} (${m.lat}, ${m.lng})`);
});

console.log('\n--- Mismatch per Kecamatan ---');
const mismatchByKec = {};
mismatchKec.forEach(m => {
    const key = `${m.kecamatan_claim} → ${m.kecamatan_actual}`;
    mismatchByKec[key] = (mismatchByKec[key] || 0) + 1;
});
Object.entries(mismatchByKec).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => {
    console.log(`  ${k}: ${v} entri`);
});
