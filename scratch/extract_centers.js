const fs = require('fs');

const data = JSON.parse(fs.readFileSync('districts.geojson', 'utf8'));
const centers = {};

data.features.forEach(f => {
    const name = f.properties.nm_kecamatan;
    let coords = [];
    
    // Simple centroid calc for MultiPolygon
    if (f.geometry.type === 'MultiPolygon') {
        const polys = f.geometry.coordinates;
        let latSum = 0, lngSum = 0, count = 0;
        polys.forEach(poly => {
            poly[0].forEach(pt => {
                lngSum += pt[0];
                latSum += pt[1];
                count++;
            });
        });
        centers[name] = [latSum / count, lngSum / count];
    }
});

fs.writeFileSync('scratch/kecamatan_centers.json', JSON.stringify(centers, null, 2));
console.log('Kecamatan centers saved.');
