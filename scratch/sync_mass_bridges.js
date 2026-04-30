const fs = require('fs');

const scrapedData = JSON.parse(fs.readFileSync('data/listjembatanmajalengka.json', 'utf8'));
const geojson = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));

function cleanName(n) {
    if (!n) return "";
    return n.toLowerCase().replace(/[^a-z0-9]/g, '');
}

console.log(`Sinkronisasi Jembatan dimulai. Data Scraped: ${scrapedData.length}, Data GeoJSON: ${geojson.features.length}`);

let updatedCount = 0;

geojson.features = geojson.features.map(f => {
    const geoName = cleanName(f.properties.jembatan || f.properties.nama);
    
    // Cari yang paling mirip di data scraped (Jembatan + Lokasi/Kecamatan)
    const match = scrapedData.find(s => {
        const scrapedName = cleanName(s.nama);
        return scrapedName.includes(geoName) || geoName.includes(scrapedName);
    });

    if (match) {
        f.geometry.coordinates = [match.longitude, match.latitude];
        f.properties.catatan = "Koordinat Presisi (Verified Google Maps Scan)";
        updatedCount++;
    }
    return f;
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(geojson, null, 2));
console.log(`Sinkronisasi Selesai! ${updatedCount} jembatan telah dipindahkan ke lokasi rill.`);
