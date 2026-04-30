const fs = require('fs');

const geojson = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

function extractSchoolName(text) {
    // Pola untuk SD, SMP, SMA, TK, PAUD
    const regex = /(SDN|SD Negeri|SMPN|SMP Negeri|SMA|SMK|TK|PAUD|TKN)\s+([a-zA-Z0-9\s]+?)(?=\s+(\(|pada|di|kec|desa|ta\s|202|$))/i;
    
    // Fallback: Jika tidak ketemu pola di atas, cari kata yang dimulai dengan SD/SMP/TK sampai akhir atau tanda baca
    const fallbackRegex = /(SDN|SD Negeri|SMPN|SMP Negeri|TK|PAUD)\s+([a-zA-Z0-9\s]+)/i;

    const match = text.match(regex) || text.match(fallbackRegex);
    
    if (match) {
        return match[0].trim();
    }
    
    return "Sekolah Terkait"; // Default jika tidak terdeteksi nama spesifik
}

geojson.features = geojson.features.map(f => {
    const schoolName = extractSchoolName(f.properties.nama);
    return {
        ...f,
        properties: {
            ...f.properties,
            nama_sekolah: schoolName
        }
    };
});

fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geojson, null, 2));
console.log("Ekstraksi Nama Sekolah Selesai!");
