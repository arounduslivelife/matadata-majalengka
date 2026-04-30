const fs = require('fs');

const scrapedBridges = JSON.parse(fs.readFileSync('data/listjembatanmajalengka.json', 'utf8'));
const projectBridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));

console.log(`Menggabungkan ${scrapedBridges.length} aset jembatan dengan ${projectBridges.features.length} paket proyek...`);

function clean(n) { return n ? n.toLowerCase().replace(/[^a-z0-9]/g, '') : ""; }

const finalFeatures = scrapedBridges.map(s => {
    const sName = clean(s.nama);
    
    // Cari apakah jembatan ini ada di daftar proyek
    const match = projectBridges.features.find(p => {
        const pName = clean(p.properties.jembatan || p.properties.nama);
        return sName.includes(pName) || pName.includes(sName);
    });

    if (match) {
        // Jembatan yang ADA proyeknya
        return {
            type: "Feature",
            geometry: { type: "Point", coordinates: [s.longitude, s.latitude] },
            properties: {
                ...match.properties,
                nama_jembatan: s.nama,
                is_project: true,
                catatan: "Jembatan Aktif (Tersinkronisasi Proyek APBD)"
            }
        };
    } else {
        // Jembatan UMUM (Hanya aset lapangan)
        return {
            type: "Feature",
            geometry: { type: "Point", coordinates: [s.longitude, s.latitude] },
            properties: {
                nama_jembatan: s.nama,
                lokasi: s.kecamatan,
                kecamatan: s.kecamatan,
                is_project: false,
                catatan: "Aset Infrastruktur (Belum Terdata Paket Audit)",
                paket: [] // Kosong karena tidak ada proyek
            }
        };
    }
});

const output = { type: "FeatureCollection", features: finalFeatures };
fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(output, null, 2));

console.log(`Sinkronisasi Selesai! ${finalFeatures.length} titik jembatan kini terdaftar di peta.`);
