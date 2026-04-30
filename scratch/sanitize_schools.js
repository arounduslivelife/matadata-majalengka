const fs = require('fs');

// Load boundary
const boundaryData = JSON.parse(fs.readFileSync('./majalengka_kecamatan.json', 'utf8'));

// Load schools
const schoolsFile = './data/schools_ultra_clean.geojson';
const schoolsData = JSON.parse(fs.readFileSync(schoolsFile, 'utf8'));

// Ray-casting algorithm for Point in Polygon
function isPointInPolygon(point, vs) {
    const x = point[0], y = point[1];
    let inside = false;
    for (let i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        const xi = vs[i][0], yi = vs[i][1];
        const xj = vs[j][0], yj = vs[j][1];
        const intersect = ((yi > y) != (yj > y))
            && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function isPointInFeature(point, feature) {
    const geom = feature.geometry;
    if (geom.type === 'Polygon') {
        // Only checking outer ring (index 0) for simplicity, assuming holes are inside the district anyway
        return isPointInPolygon(point, geom.coordinates[0]);
    } else if (geom.type === 'MultiPolygon') {
        for (let i = 0; i < geom.coordinates.length; i++) {
            if (isPointInPolygon(point, geom.coordinates[i][0])) return true;
        }
    }
    return false;
}

function isPointInMajalengka(point) {
    for (let i = 0; i < boundaryData.features.length; i++) {
        if (isPointInFeature(point, boundaryData.features[i])) {
            return true;
        }
    }
    return false;
}

const originalCount = schoolsData.features.length;
const sanitizedFeatures = [];
let outOfBoundsCount = 0;

schoolsData.features.forEach(f => {
    const pt = f.geometry.coordinates; // [lng, lat]
    if (isPointInMajalengka(pt)) {
        sanitizedFeatures.push(f);
    } else {
        outOfBoundsCount++;
    }
});

schoolsData.features = sanitizedFeatures;

fs.writeFileSync(schoolsFile, JSON.stringify(schoolsData, null, 2));

console.log(`Sanitization Complete.`);
console.log(`Original Total: ${originalCount}`);
console.log(`Out of bounds removed: ${outOfBoundsCount}`);
console.log(`Valid Schools Remaining: ${sanitizedFeatures.length}`);
