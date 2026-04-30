const fs = require('fs');

function clean(n) { return n ? n.toLowerCase().replace(/[^a-z0-9]/g, '') : ""; }

const projects = [];

function parseCSV(filename, targetYear) {
    const csv = fs.readFileSync(filename, 'utf8').split('\n');
    console.log(`Analyzing ${csv.length} rows from ${filename}...`);

    csv.forEach(line => {
        const cols = line.split(',');
        if (cols.length < 13) return;

        const satker = cols[1] || "";
        const vendor = cols[7] || "";
        const namaPaket = cols[10] || "";
        const pagu = parseInt(cols[12]) || 0;
        const tahun = parseInt(cols[4]) || targetYear;

        if (satker.includes('PEKERJAAN UMUM') && namaPaket.toLowerCase().includes('jembatan')) {
            projects.push({ type: 'jembatan', nama: namaPaket, vendor, pagu, tahun });
        } else if (satker.includes('PENDIDIKAN') && (namaPaket.includes('SD') || namaPaket.includes('SMP') || namaPaket.includes('TK'))) {
            projects.push({ type: 'sekolah', nama: namaPaket, vendor, pagu, tahun });
        }
    });
}

// Read both years
parseCSV('raw/realisasi2025alldept.csv', 2025);
parseCSV('raw/realisasi2026alldept.csv', 2026);

console.log(`Total Relevant Projects across 2025-2026: ${projects.length}`);

const bridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));
const schools = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

let matchBridgeCount = 0;
bridges.features.forEach(f => {
    f.properties.paket = [];
    const name = clean(f.properties.nama_jembatan);
    
    projects.filter(p => p.type === 'jembatan').forEach(p => {
        if (clean(p.nama).includes(name)) {
            f.properties.paket.push({ nama: p.nama, pagu: p.pagu, tahun: p.tahun, vendor: p.vendor });
            f.properties.is_project = true;
            matchBridgeCount++;
        }
    });
});

let matchSchoolCount = 0;
schools.features.forEach(f => {
    f.properties.paket = [];
    const sName = clean(f.properties.nama_sekolah);
    const related = projects.filter(p => p.type === 'sekolah' && clean(p.nama).includes(sName));
    
    if (related.length > 0) {
        related.forEach(p => {
            f.properties.paket.push({ nama: p.nama, pagu: p.pagu, tahun: p.tahun, vendor: p.vendor });
        });
        matchSchoolCount++;
    }
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(bridges, null, 2));
fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(schools, null, 2));

console.log(`Sync Result: ${matchBridgeCount} Bridge Match, ${matchSchoolCount} School Match.`);
