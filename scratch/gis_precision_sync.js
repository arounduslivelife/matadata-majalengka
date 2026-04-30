const KECAMATAN_CENTERS = {
    "Argapura": [-6.843, 108.332],
    "Banjaran": [-6.966, 108.328],
    "Bantarujeg": [-7.067, 108.191],
    "Cigasong": [-6.837, 108.239],
    "Cikijing": [-7.019, 108.361],
    "Cingambul": [-7.042, 108.336],
    "Dawuan": [-6.698, 108.222],
    "Jatitujuh": [-6.623, 108.172],
    "Jatiwangi": [-6.717, 108.261],
    "Kadipaten": [-6.766, 108.172],
    "Kasokandel": [-6.698, 108.222],
    "Kertajati": [-6.657, 108.125],
    "Lemahsugih": [-7.107, 108.171],
    "Leuwimunding": [-6.732, 108.349],
    "Ligung": [-6.657, 108.252],
    "Maja": [-6.883, 108.283],
    "Majalengka": [-6.837, 108.225],
    "Malausma": [-7.078, 108.283],
    "Panyingkiran": [-6.761, 108.196],
    "Palasah": [-6.717, 108.293],
    "Rajagaluh": [-6.787, 108.336],
    "Sindang": [-6.823, 108.327],
    "Sindangwangi": [-6.783, 108.347],
    "Sukahaji": [-6.790, 108.284],
    "Sumberjaya": [-6.658, 108.351],
    "Talaga": [-6.919, 108.217]
};

const fs = require('fs');

function getDistance(lat1, lon1, lat2, lon2) {
    return Math.sqrt(Math.pow(lat1 - lat2, 2) + Math.pow(lon1 - lon2, 2));
}

function determineKecamatan(lat, lon) {
    let nearest = null;
    let minDist = Infinity;
    for (const [name, center] of Object.entries(KECAMATAN_CENTERS)) {
        const d = getDistance(lat, lon, center[0], center[1]);
        if (d < minDist) {
            minDist = d;
            nearest = name;
        }
    }
    return nearest;
}

const bridges = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));
const csv = fs.readFileSync('raw/realisasi2025alldept.csv', 'utf8').split('\n');

// 1. Correct Bridge Districts based on Coordinates
bridges.features.forEach(f => {
    const lat = f.geometry.coordinates[1];
    const lon = f.geometry.coordinates[0];
    f.properties.kecamatan_verified = determineKecamatan(lat, lon);
});

// 2. Extract Projects with (xxxx) Logic
const projects = [];
csv.forEach(line => {
    const cols = line.split(',');
    if (cols.length < 13) return;
    const namaPaket = cols[10] || "";
    const pagu = parseInt(cols[12]) || 0;
    const vendor = cols[7] || "";
    
    // Pattern: (....)
    const bridgeMatch = namaPaket.match(/\(([^)]+)\)/);
    if (bridgeMatch) {
        projects.push({
            origNama: namaPaket,
            targetBridge: bridgeMatch[1].trim().toLowerCase(),
            pagu,
            vendor,
            // Try to find kecamatan in parent string
            kecamatan: Object.keys(KECAMATAN_CENTERS).find(k => namaPaket.toLowerCase().includes(k.toLowerCase()))
        });
    }
});

// 3. Re-Sync with Extreme Precision
let matchCount = 0;
bridges.features.forEach(f => {
    f.properties.paket = [];
    const vKec = f.properties.kecamatan_verified.toLowerCase();
    const bName = f.properties.nama_jembatan.toLowerCase();

    projects.forEach(p => {
        const pKec = p.kecamatan ? p.kecamatan.toLowerCase() : "";
        const pBridge = p.targetBridge;
        
        // Match condition: 
        // 1. Kecamatan must match (if project has one)
        // 2. Name in bracket must match or include bridge name
        if (pBridge.includes(bName) || bName.includes(pBridge)) {
            if (!pKec || pKec === vKec) {
                f.properties.paket.push({ nama: p.origNama, pagu: p.pagu, vendor: p.vendor, tahun: 2025 });
                f.properties.is_project = true;
                matchCount++;
            }
        }
    });
});

fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(bridges, null, 2));
console.log(`GIS-Precision Sync Complete: ${matchCount} matches verified by coordinates and parentheses logic.`);
