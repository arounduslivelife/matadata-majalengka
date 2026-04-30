const fs = require('fs');

// Fungsi sederhana Point-in-Polygon
function isPointInPoly(pt, poly) {
    for (var isInside = false, i = -1, l = poly.length, j = l - 1; ++i < l; j = i) {
        ((poly[i][1] <= pt[1] && pt[1] < poly[j][1]) || (poly[j][1] <= pt[1] && pt[1] < poly[i][1])) &&
        (pt[0] < (poly[j][0] - poly[i][0]) * (pt[1] - poly[i][1]) / (poly[j][1] - poly[i][1]) + poly[i][0]) &&
        (isInside = !isInside);
    }
    return isInside;
}

const districtsData = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const bridges = JSON.parse(fs.readFileSync('scratch/bridge_list.json', 'utf8'));

const results = bridges.map(b => {
    let kecamatan = "Tidak Diketahui";
    for (const feature of districtsData.features) {
        const poly = feature.geometry.coordinates[0]; // Sederhananya ambil ring luar
        // Handle MultiPolygon if exists
        if (feature.geometry.type === 'Polygon') {
            if (isPointInPoly([b.lon, b.lat], poly)) {
                kecamatan = feature.properties.nm_kecamatan;
                break;
            }
        } else if (feature.geometry.type === 'MultiPolygon') {
            for (const part of feature.geometry.coordinates) {
                if (isPointInPoly([b.lon, b.lat], part[0])) {
                    kecamatan = feature.properties.nm_kecamatan;
                    break;
                }
            }
        }
    }
    return { ...b, kecamatan };
});

fs.writeFileSync('scratch/bridge_with_districts.json', JSON.stringify(results, null, 2));
console.log("Mapping Kecamatan Selesai!");
