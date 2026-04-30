const fs = require('fs');

function convertToGeoJSON(inputFile, outputFile, type) {
    const rawData = JSON.parse(fs.readFileSync(inputFile, 'utf8'));
    console.log(`Mengonversi ${rawData.length} data ${type}...`);

    const features = rawData.map(item => {
        return {
            type: "Feature",
            geometry: {
                type: "Point",
                coordinates: [item.longitude, item.latitude]
            },
            properties: {
                nama: item.nama,
                kecamatan: item.kecamatan,
                // Kita kosongkan dulu datanya sesuai instruksi bos
                paket: [],
                is_project: false,
                catatan: `Lokasi Terverifikasi (${type})`
            }
        };
    });

    const geojson = {
        type: "FeatureCollection",
        features: features
    };

    fs.writeFileSync(outputFile, JSON.stringify(geojson, null, 2));
    console.log(`   -> Berhasil membuat ${outputFile}`);
}

// EKSEKUSI KONVERSI MURNI
convertToGeoJSON('data/listjembatanmajalengka.json', 'data/jembatan_kabupaten.geojson', 'Jembatan');
convertToGeoJSON('data/listsekolahmajalengka.json', 'data/sarana_pendidikan.geojson', 'Sekolah');

console.log("\n--- PEMETAAN KOORDINAT MURNI SELESAI ---");
