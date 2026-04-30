const fs = require('fs');
const csv = require('fs').readFileSync('raw/realisasi2025alldept.csv', 'utf8').split('\n');

function clean(n) { return n ? n.toLowerCase().replace(/[^a-z0-9]/g, '') : ""; }

const projects = [];

console.log(`Analyzing ${csv.length} rows from CSV...`);

csv.forEach(line => {
    const cols = line.split(',');
    if (cols.length < 13) return;

    const satker = cols[1] || "";
    const vendor = cols[7] || "";
    const namaPaket = cols[10] || "";
    const pagu = parseInt(cols[12]) || 0;
    const tahun = parseInt(cols[4]) || 2025;

    // Filter Jembatan vs Sekolah
    if (satker.includes('PEKERJAAN UMUM') && namaPaket.toLowerCase().includes('jembatan')) {
        projects.push({ type: 'jembatan', nama: namaPaket, vendor, pagu, tahun });
    } else if (satker.includes('PENDIDIKAN') && (namaPaket.includes('SD') || namaPaket.includes('SMP') || namaPaket.includes('TK') || namaPaket.includes('Sekolah'))) {
        projects.push({ type: 'sekolah', nama: namaPaket, vendor, pagu, tahun });
    }
});

console.log(`Found ${projects.length} relevant projects.`);

// Load Current GeoJSONs
const bridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));
const schools = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

// 1. Update Bridges with Real Data
bridges.features.forEach(f => {
    f.properties.paket = []; // Reset simulation
    const name = clean(f.properties.nama_jembatan);
    
    projects.filter(p => p.type === 'jembatan').forEach(p => {
        if (clean(p.nama).includes(name)) {
            f.properties.paket.push({
                nama: p.nama,
                pagu: p.pagu,
                tahun: p.tahun,
                vendor: p.vendor
            });
            f.properties.is_project = true;
        }
    });
});

// 2. Update Schools with Real Data
// Note: This is more complex because one school can have multiple packages
schools.features.forEach(f => {
    f.properties.pagu = 0;
    f.properties.vendor = "Beberapa Vendor";
    
    // Find packages matching this school name
    const sName = clean(f.properties.nama_sekolah);
    const related = projects.filter(p => p.type === 'sekolah' && clean(p.nama).includes(sName));
    
    if (related.length > 0) {
        f.properties.pagu = related.reduce((sum, p) => sum + p.pagu, 0);
        f.properties.vendor = related[0].vendor;
        f.properties.catatan = `Realisasi Rill ${related.length} Paket Anggaran`;
    }
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(bridges, null, 2));
fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(schools, null, 2));

console.log("Sync Rill Selesai! Data simulasi telah diganti dengan Data Pemerintah Kabupaten Majalengka.");
