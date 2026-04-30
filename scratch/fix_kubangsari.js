const fs = require('fs');

const file = './data/schools_ultra_clean.geojson';
const data = JSON.parse(fs.readFileSync(file, 'utf8'));

let found = false;

// Hanya mengubah data sekolah di kecamatan Banjaran yang bernama persis "Kubangsari"
data.features.forEach(f => {
    if (f.properties.nama === 'Kubangsari' && f.properties.kecamatan === 'Banjaran') {
        f.properties.nama = 'SMA Negeri 1 Bantarujeg';
        f.properties.tipe = 'SMA/MA';
        f.properties.catatan_revisi = 'Diubah dari Kubangsari (Banjaran)';
        found = true;
        console.log('BERHASIL DITEMUKAN DAN DIUBAH:');
        console.log(f.properties);
    }
});

if (found) {
    fs.writeFileSync(file, JSON.stringify(data, null, 2));
    console.log('File geojson sekolah berhasil diupdate.');
} else {
    console.log('Data Kubangsari di Kecamatan Banjaran tidak ditemukan dalam geojson.');
}

// Update file sumber json juga untuk konsistensi
const sourceFile = './data/found_schools_ultra_v2.json';
if (fs.existsSync(sourceFile)) {
    const sourceData = JSON.parse(fs.readFileSync(sourceFile, 'utf8'));
    let sourceFound = false;
    sourceData.data.forEach(item => {
        if (item.nama === 'Kubangsari' && item.kecamatan === 'Banjaran') {
            item.nama = 'SMA Negeri 1 Bantarujeg';
            sourceFound = true;
        }
    });
    if (sourceFound) {
        fs.writeFileSync(sourceFile, JSON.stringify(sourceData, null, 2));
        console.log('File sumber json juga berhasil diupdate.');
    }
}
