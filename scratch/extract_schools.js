const Database = require('better-sqlite3');
const fs = require('fs');

const db = new Database('database.sqlite');

// Query untuk mencari paket terkait sekolah
const query = `
    SELECT * FROM packages 
    WHERE nama_paket LIKE '%sekolah%' 
       OR nama_paket LIKE '%SD %' 
       OR nama_paket LIKE '%SMP %' 
       OR nama_paket LIKE '%TK %' 
       OR nama_paket LIKE '%PAUD%' 
       OR nama_paket LIKE '%Ruang Kelas%'
       OR satker LIKE '%Pendidikan%'
`;

try {
    const rows = db.prepare(query).all();
    console.log(`Ditemukan ${rows.length} paket pendidikan.`);
    
    const geojson = {
        type: "FeatureCollection",
        features: rows.map(r => ({
            type: "Feature",
            geometry: {
                type: "Point",
                coordinates: [r.lng || 108.227, r.lat || -6.837] // Default ke pusat Majalengka jika koordinat kosong
            },
            properties: {
                id: r.id,
                nama: r.nama_paket,
                instansi: r.satker,
                pagu: r.pagu,
                tahun: r.tahun,
                kecamatan: r.kecamatan,
                vendor: r.pemenang || 'Belum Ada Pemenang',
                status: r.status
            }
        }))
    };

    fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geojson, null, 2));
    console.log("File data/sarana_pendidikan.geojson berhasil dibuat!");
} catch (err) {
    console.error("Gagal mengekstrak data sekolah:", err);
} finally {
    db.close();
}
