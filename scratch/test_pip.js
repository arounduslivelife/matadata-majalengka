const fs = require('fs');

function isPointInPolygon(point, vs) {
    var x = point[0], y = point[1];
    var inside = false;
    for (var i = 0, j = vs.length - 1; i < vs.length; i++, j = i) {
        var xi = vs[i][0], yi = vs[i][1];
        var xj = vs[j][0], yj = vs[j][1];
        var intersect = ((yi > y) != (yj > y))
            && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

const districts = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const pt = [108.3145798, -6.9097954]; // SMP Negeri 1 Argapura

districts.features.forEach(f => {
    const name = f.properties.nm_kecamatan;
    const type = f.geometry.type;
    const coords = f.geometry.coordinates;
    let found = false;
    
    if (type === 'Polygon') {
        if (isPointInPolygon(pt, coords[0])) found = true;
    } else if (type === 'MultiPolygon') {
        for (const poly of coords) {
            if (isPointInPolygon(pt, poly[0])) {
                found = true;
                break;
            }
        }
    }
    
    if (found) console.log(`Point is in district: ${name}`);
});
