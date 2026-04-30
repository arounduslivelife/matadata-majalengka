const fs = require('fs');

/**
 * Titik-dalam-Polygon (Ray Casting Algorithm)
 */
function isPointInPolygon(lat, lng, polygons) {
    // Polygons is an array of MultiPolygon coordinates
    for (let feature of polygons) {
        const coords = feature.geometry.coordinates;
        const type = feature.geometry.type;

        if (type === 'Polygon') {
            if (checkSinglePolygon(lat, lng, coords)) return true;
        } else if (type === 'MultiPolygon') {
            for (let part of coords) {
                if (checkSinglePolygon(lat, lng, part)) return true;
            }
        }
    }
    return false;
}

function checkSinglePolygon(lat, lng, polygonParts) {
    // GeoJSON polygon coordinates are [lng, lat]
    // ring is polygonParts[0] (outer ring)
    const ring = polygonParts[0];
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i][0], yi = ring[i][1];
        const xj = ring[j][0], yj = ring[j][1];

        const intersect = ((yi > lat) !== (yj > lat)) &&
            (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

// 1. LOAD BOUNDARIES
console.log("Loading boundaries...");
const boundaries = JSON.parse(fs.readFileSync('majalengka_kecamatan.json', 'utf8'));

function sterilize(inputFile, label) {
    console.log(`\nProcessing ${label}: ${inputFile}`);
    if (!fs.existsSync(inputFile)) {
        console.log(`Skipping ${label}, file not found.`);
        return;
    }

    const data = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
    const originalCount = data.features.length;

    const filteredFeatures = data.features.filter(f => {
        const [lng, lat] = f.geometry.coordinates;
        return isPointInPolygon(lat, lng, boundaries.features);
    });

    const removed = originalCount - filteredFeatures.length;
    console.log(`Success: ${filteredFeatures.length} points inside Majalengka.`);
    console.log(`Discarded: ${removed} points outside boundaries.`);

    fs.writeFileSync(inputFile, JSON.stringify({
        type: "FeatureCollection",
        features: filteredFeatures
    }, null, 2));
}

// EKSEKUSI
sterilize('data/jembatan_kabupaten.geojson', 'Jembatan Official');
sterilize('data/jembatan_deep.geojson', 'Jembatan Scraped AI');
sterilize('data/sarana_pendidikan.geojson', 'Sekolah Official');

console.log("\n--- SEMUA DATA TELAH STERIL ---");
