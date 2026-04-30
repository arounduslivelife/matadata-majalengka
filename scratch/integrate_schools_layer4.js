const fs = require('fs');

// Load files
const cleanSchools = JSON.parse(fs.readFileSync('./data/schools_ultra_clean.json', 'utf8'));
const oldGeoJSON = JSON.parse(fs.readFileSync('./data/sarana_pendidikan.geojson', 'utf8'));

console.log('=== INTEGRASI ke sarana_pendidikan.geojson ===');
console.log(`Clean schools: ${cleanSchools.data.length}`);
console.log(`Old GeoJSON features: ${oldGeoJSON.features.length}`);

// Build paket lookup from old data (by coordinate key)
const paketByCoord = {};
const paketByName = {};
let totalPaketEntries = 0;

oldGeoJSON.features.forEach(f => {
    if (f.properties.paket && f.properties.paket.length > 0) {
        const coordKey = f.geometry.coordinates[0].toFixed(6) + ',' + f.geometry.coordinates[1].toFixed(6);
        paketByCoord[coordKey] = f.properties.paket;
        
        // Also index by normalized name for fuzzy match
        const nameKey = f.properties.nama.toLowerCase().replace(/[^a-z0-9]/g, '');
        paketByName[nameKey] = f.properties.paket;
        totalPaketEntries++;
    }
});

console.log(`Schools with paket data: ${totalPaketEntries}`);

// Deduplicate paket arrays (many have repeated entries)
function dedupPaket(paketArr) {
    if (!paketArr || paketArr.length === 0) return [];
    const seen = new Set();
    return paketArr.filter(p => {
        const key = (p.kode_paket || '') + '|' + (p.tahun || '') + '|' + (p.nama_paket || '').substring(0, 50);
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

// Build new GeoJSON from clean data
const features = cleanSchools.data.map(s => {
    const coordKey = s.lng.toFixed(6) + ',' + s.lat.toFixed(6);
    const nameKey = s.nama.toLowerCase().replace(/[^a-z0-9]/g, '');
    
    // Try to find paket by coordinate first, then by name
    let paket = paketByCoord[coordKey] || paketByName[nameKey] || [];
    paket = dedupPaket(paket);
    
    return {
        type: 'Feature',
        geometry: {
            type: 'Point',
            coordinates: [s.lng, s.lat]
        },
        properties: {
            nama: s.nama,
            kecamatan: s.kecamatan, // CORRECTED kecamatan from geofence
            tipe: s.tipe,
            paket: paket,
            is_project: paket.length > 0,
            catatan: 'Lokasi Terverifikasi (Sanitized)',
            desa_geo: ''
        }
    };
});

const newGeoJSON = {
    type: 'FeatureCollection',
    features: features
};

// Stats
const withPaket = features.filter(f => f.properties.paket.length > 0);
const byType = {};
features.forEach(f => { byType[f.properties.tipe] = (byType[f.properties.tipe] || 0) + 1; });
const byKec = {};
features.forEach(f => { byKec[f.properties.kecamatan] = (byKec[f.properties.kecamatan] || 0) + 1; });

console.log('\n========================================');
console.log('         HASIL INTEGRASI');
console.log('========================================');
console.log(`Total fitur: ${features.length}`);
console.log(`Dengan paket anggaran: ${withPaket.length}`);
console.log(`Tanpa paket: ${features.length - withPaket.length}`);

console.log('\n--- Per Tipe ---');
Object.entries(byType).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: ${v}`));

console.log('\n--- Per Kecamatan ---');
Object.entries(byKec).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${k}: ${v}`));

// Backup old file
fs.copyFileSync('./data/sarana_pendidikan.geojson', './data/sarana_pendidikan_backup.geojson');
console.log('\n✅ Backup: sarana_pendidikan_backup.geojson');

// Write new file
fs.writeFileSync('./data/sarana_pendidikan.geojson', JSON.stringify(newGeoJSON), 'utf8');
const newSize = fs.statSync('./data/sarana_pendidikan.geojson').size;
console.log(`✅ Output: sarana_pendidikan.geojson (${(newSize/1024).toFixed(0)} KB)`);
console.log(`\n🎯 Layer 4 Infrastruktur siap! Data sudah di-replace.`);
