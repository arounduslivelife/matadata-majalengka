const fs = require('fs');
const path = require('path');

const csvFile = path.join(__dirname, '../data_jembatan_deep.csv');
const outputFile = path.join(__dirname, '../data/jembatan_deep.geojson');

if (!fs.existsSync(csvFile)) {
    console.error("CSV file not found!");
    process.exit(1);
}

const content = fs.readFileSync(csvFile, 'utf8');
const lines = content.trim().split('\n');
const header = lines[0].split(',');

const features = lines.slice(1).map(line => {
    // Simple CSV parser for quoted fields
    const parts = line.match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g);
    if (!parts || parts.length < 5) return null;

    const nama = parts[0].replace(/"/g, '');
    const lat = parseFloat(parts[1]);
    const lng = parseFloat(parts[2]);
    const kecamatan = parts[3].replace(/"/g, '');
    const url = parts[4].replace(/"/g, '');

    return {
        type: "Feature",
        properties: {
            nama: nama,
            kecamatan: kecamatan,
            source_url: url,
            catatan: "Data hasil scraping Expert Grid Search (Sterilized)"
        },
        geometry: {
            type: "Point",
            coordinates: [lng, lat]
        }
    };
}).filter(f => f !== null);

const geojson = {
    type: "FeatureCollection",
    features: features
};

if (!fs.existsSync(path.dirname(outputFile))) {
    fs.mkdirSync(path.dirname(outputFile), { recursive: true });
}

fs.writeFileSync(outputFile, JSON.stringify(geojson, null, 2));
console.log(`Successfully converted ${features.length} bridges to GeoJSON: ${outputFile}`);
