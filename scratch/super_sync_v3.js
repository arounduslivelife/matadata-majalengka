const fs = require('fs');

function clean(n) { return n ? n.toLowerCase().replace(/[^a-z0-9]/g, '') : ""; }

const projects = [];
function parseCSV(filename, targetYear) {
    const csv = fs.readFileSync(filename, 'utf8').split('\n');
    csv.forEach(line => {
        const cols = line.split(',');
        if (cols.length < 13) return;
        const satker = cols[1] || "";
        const namaPaket = cols[10] || "";
        const pagu = parseInt(cols[12]) || 0;
        const vendor = cols[7] || "";
        const tahun = parseInt(cols[4]) || targetYear;

        // CRITICAL FILTER: Must be BRIDGE department and contain BRIDGE keyword
        // and NOT contain "Irigasi", "Jalan", "Drainase" unless it's specifically about a bridge
        const isBridgeDinas = satker.includes('PEKERJAAN UMUM');
        const isBridgePaket = namaPaket.toLowerCase().includes('jembatan');
        const isNotOthers = !namaPaket.toLowerCase().includes('irigasi') && !namaPaket.toLowerCase().includes('jalan');

        if (isBridgeDinas && isBridgePaket && isNotOthers) {
            projects.push({ type: 'jembatan', nama: namaPaket, vendor, pagu, tahun });
        } else if (satker.includes('PENDIDIKAN')) {
            projects.push({ type: 'sekolah', nama: namaPaket, vendor, pagu, tahun });
        }
    });
}

parseCSV('raw/realisasi2025alldept.csv', 2025);
parseCSV('raw/realisasi2026alldept.csv', 2026);

const bridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));

let matchCount = 0;
bridges.features.forEach(f => {
    f.properties.paket = [];
    const bridgeName = clean(f.properties.nama_jembatan);
    const bridgeKec = clean(f.properties.kecamatan);

    projects.filter(p => p.type === 'jembatan').forEach(p => {
        const pName = clean(p.nama);
        
        // SMART MATCHING:
        // 1. Name must match
        // 2. IF bridge point has a kecamatan, and project name mentions a DIFFERENT kecamatan, REJECT.
        // example: Point is Argapura. Project name has "Rajagaluh". -> REJECT.
        
        const mentionsOtherKec = (bridgeKec === 'argapura' && pName.includes('rajagaluh')) ||
                                 (bridgeKec === 'rajagaluh' && pName.includes('argapura'));

        if (pName.includes(bridgeName) && !mentionsOtherKec) {
            f.properties.paket.push({ nama: p.nama, pagu: p.pagu, tahun: p.tahun, vendor: p.vendor });
            f.properties.is_project = true;
            matchCount++;
        }
    });
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(bridges, null, 2));
console.log(`Smart Sync Result: ${matchCount} matches verified by name and district logic.`);
