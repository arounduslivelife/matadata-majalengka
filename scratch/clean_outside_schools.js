const fs = require('fs');

function isPointInPolygon(point, vs) {
    var x = point[0], y = point[1];
    var inside = false;
    for (var i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        var xi = vs[i][0], yi = vs[i][1];
        var xj = vs[j][0], yj = vs[j][1];
        var intersect = ((yi > y) != (yj > y))
            && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

const districts = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const schools = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

console.log(`Initial school count: ${schools.features.length}`);

let updatedKecCount = 0;

const cleanedFeatures = schools.features.filter((f, idx) => {
    const pt = f.geometry.coordinates;
    const name = f.properties.nama;
    const claimedKec = f.properties.kecamatan;
    
    let actualKec = null;
    
    for (const district of districts.features) {
        const dName = district.properties.nm_kecamatan;
        const type = district.geometry.type;
        const coords = district.geometry.coordinates;
        
        let match = false;
        if (type === 'Polygon') {
            if (isPointInPolygon(pt, coords[0])) match = true;
        } else if (type === 'MultiPolygon') {
            for (const poly of coords) {
                if (isPointInPolygon(pt, poly[0])) {
                    match = true;
                    break;
                }
            }
        }
        
        if (match) {
            actualKec = dName;
            break;
        }
    }
    
    if (actualKec) {
        if (actualKec !== claimedKec) {
            // console.log(`Correcting Kec: ${name} (${claimedKec} -> ${actualKec})`);
            f.properties.kecamatan = actualKec;
            updatedKecCount++;
        }
        return true;
    } else {
        // Still outside Majalengka
        return false;
    }
});

console.log(`--- SUMMARY ---`);
console.log(`Removed: ${schools.features.length - cleanedFeatures.length} schools outside Majalengka.`);
console.log(`Corrected Kecamatan for: ${updatedKecCount} schools.`);
console.log(`Final total: ${cleanedFeatures.length} schools.`);

schools.features = cleanedFeatures;
fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(schools, null, 2));
