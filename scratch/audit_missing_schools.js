const fs = require('fs');

// Load boundaries
const districts = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));

// Load data
const ultra = JSON.parse(fs.readFileSync('data/found_schools_ultra.json', 'utf8')).data;
const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

// Helper for Point-in-Polygon
function isInsideMajalengka(lng, lat) {
    for (const district of districts.features) {
        if (district.geometry.type === 'Polygon') {
            if (isPointInPolygon([lng, lat], district.geometry.coordinates)) return true;
        } else if (district.geometry.type === 'MultiPolygon') {
            for (const poly of district.geometry.coordinates) {
                if (isPointInPolygon([lng, lat], poly)) return true;
            }
        }
    }
    return false;
}

function isPointInPolygon(point, vs) {
    var x = point[0], y = point[1];
    var inside = false;
    // Handle both single polygon (vs[0]) and multipolygon structures
    const polygons = Array.isArray(vs[0][0]) ? vs : [vs];
    
    for (const polygon of polygons) {
        const ring = polygon[0]; // outer ring
        for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            var xi = ring[i][0], yi = ring[i][1];
            var xj = ring[j][0], yj = ring[j][1];
            var intersect = ((yi > y) != (yj > y))
                && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        if (inside) return true;
    }
    return inside;
}

const geoNames = new Set(geo.features.map(f => f.properties.nama.toLowerCase().trim()));

const missingButInside = [];
const missingAndOutside = [];

ultra.forEach(school => {
    const name = school.nama ? school.nama.toLowerCase().trim() : '';
    if (!geoNames.has(name)) {
        if (isInsideMajalengka(school.lng, school.lat)) {
            missingButInside.push(school);
        } else {
            missingAndOutside.push(school);
        }
    }
});

console.log(`Summary:`);
console.log(`- Missing entries but INSIDE Majalengka: ${missingButInside.length}`);
console.log(`- Missing entries because OUTSIDE Majalengka: ${missingAndOutside.length}`);

if (missingButInside.length > 0) {
    console.log(`\nSample of missing but inside:`);
    missingButInside.slice(0, 10).forEach(s => console.log(`- ${s.nama} (${s.kecamatan}) [${s.lat}, ${s.lng}]`));
    
    // Auto-restore them? Let's just list first to be safe.
}
