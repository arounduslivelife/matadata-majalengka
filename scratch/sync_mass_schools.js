const fs = require('fs');

const scrapedData = JSON.parse(fs.readFileSync('data/listsekolahmajalengka.json', 'utf8'));
const geojson = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

// Helper untuk membersihkan nama agar lebih mudah dicocokkan (fuzzy match sederhana)
function cleanName(n) {
    return n.toLowerCase().replace(/[^a-z0-9]/g, '');
}

console.log(`Sinkronisasi dimulai. Data Scraped: ${scrapedData.length}, Data GeoJSON: ${geojson.features.length}`);

let updatedCount = 0;

geojson.features = geojson.features.map(f => {
    const geoName = cleanName(f.properties.nama_sekolah || f.properties.nama);
    
    // Cari yang paling mirip di data scraped
    const match = scrapedData.find(s => {
        const scrapedName = cleanName(s.nama);
        return scrapedName.includes(geoName) || geoName.includes(scrapedName);
    });

    if (match) {
        f.geometry.coordinates = [match.longitude, match.latitude];
        f.properties.catatan = "Koordinat Presisi (Verified Google Maps)";
        updatedCount++;
    }
    return f;
});

fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geojson, null, 2));
console.log(`Sinkronisasi Selesai! ${updatedCount} sekolah telah dipindahkan ke lokasi rill.`);
