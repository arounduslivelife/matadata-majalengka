const fs = require('fs');

const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
const realisasi = JSON.parse(fs.readFileSync('data/realisasi_sekolah.geojson', 'utf8'));

function normalize(str) {
    if (!str) return '';
    return str.toLowerCase().trim()
        .replace(/sd\s*negeri/g, 'sdn')
        .replace(/smp\s*negeri/g, 'smpn')
        .replace(/sma\s*negeri/g, 'sman')
        .replace(/smk\s*negeri/g, 'smkn')
        .replace(/ i$/g, ' 1').replace(/ ii$/g, ' 2').replace(/ iii$/g, ' 3')
        .replace(/[^a-z0-9]/g, '');
}

const schoolMap = new Set();
geo.features.forEach(f => {
    schoolMap.add(normalize(f.properties.nama));
});

const unlinkedPhysical = [];
realisasi.data.forEach(p => {
    const pkg = p.nama_paket;
    const isPhysical = pkg.match(/Lokasi\s*:\s*/i) || pkg.match(/Gedung|Pagar|Ruang Kelas|Rehabilitasi/i);
    
    if (isPhysical) {
        // Try to see if any school name on map is in this paket
        let found = false;
        const nPaket = normalize(pkg);
        for (const name of schoolMap) {
            if (name.length > 5 && nPaket.includes(name)) {
                found = true;
                break;
            }
        }
        
        if (!found) {
            // Check for obvious overhead
            if (!pkg.match(/Nasi Box|Kertas|Alat Tulis|Cetak|Makan|Rapat|Jasa Penyelenggaraan/i)) {
                unlinkedPhysical.push(pkg);
            }
        }
    }
});

console.log(`Unlinked Physical Projects (Coverage Gap): ${unlinkedPhysical.length}`);
unlinkedPhysical.slice(0, 10).forEach(p => console.log(`- ${p}`));
