const fs = require('fs');

const data = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

const missingSchools = [
    { nama: 'SMP Negeri 1 Majalengka', kec: 'Majalengka', lat: -6.8373, lng: 108.2324 },
    { nama: 'SMP Negeri 2 Majalengka', kec: 'Majalengka', lat: -6.8525, lng: 108.2046 },
    { nama: 'SMP Negeri 4 Majalengka', kec: 'Majalengka', lat: -6.8322, lng: 108.2195 },
    { nama: 'SMP Negeri 2 Argapura', kec: 'Argapura', lat: -6.8933, lng: 108.3616 },
    { nama: 'SMP Negeri 1 Rajagaluh', kec: 'Rajagaluh', lat: -6.8202, lng: 108.3484 },
    { nama: 'SD Negeri Argalingga III', kec: 'Argapura', lat: -6.8900, lng: 108.3600 },
    { nama: 'SD Negeri Sadasari I', kec: 'Argapura', lat: -6.8800, lng: 108.3300 },
    { nama: 'SD Negeri Palabuan I', kec: 'Sukahaji', lat: -6.7900, lng: 108.2900 },
    { nama: 'SD Negeri Sindanghaji I', kec: 'Palasah', lat: -6.7800, lng: 108.3100 }
];

missingSchools.forEach(s => {
    // Check if already exists (approx match)
    const exists = data.features.some(f => f.properties.nama.toLowerCase() === s.nama.toLowerCase());
    if (!exists) {
        data.features.push({
            type: 'Feature',
            properties: {
                nama: s.nama,
                kecamatan: s.kec,
                verified: false,
                catatan: 'Restored from Budget Records',
                is_project: true,
                paket: []
            },
            geometry: {
                type: 'Point',
                coordinates: [s.lng, s.lat]
            }
        });
        console.log(`Restored: ${s.nama}`);
    }
});

fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(data, null, 2));
