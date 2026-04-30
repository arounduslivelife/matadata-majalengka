const fs = require('fs');

function extractBridgeNames(filename) {
    const csv = fs.readFileSync(filename, 'utf8').split('\n');
    const bridgeList = [];

    csv.forEach(line => {
        const cols = line.split(',');
        if (cols.length < 13) return;
        const namaPaket = cols[10] || "";
        
        // Extract from parentheses as per user's pro-tip
        const bridgeMatch = namaPaket.match(/\(([^)]+)\)/);
        if (bridgeMatch && namaPaket.toLowerCase().includes('jembatan')) {
            // Find kecamatan in the string
            const words = namaPaket.split(' ');
            const kecKeywords = ["Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", "Cingambul", "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", "Kasokandel", "Kertajati", "Lemahsugih", "Leuwimunding", "Ligung", "Maja", "Majalengka", "Malausma", "Panyingkiran", "Palasah", "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", "Sumberjaya", "Talaga"];
            const kecamatan = kecKeywords.find(k => namaPaket.toLowerCase().includes(k.toLowerCase())) || "Majalengka";

            bridgeList.push({
                nama: bridgeMatch[1].trim(),
                searchQuery: `${bridgeMatch[1].trim()} ${kecamatan} Majalengka`,
                kecamatan: kecamatan
            });
        }
    });
    return bridgeList;
}

const list2025 = extractBridgeNames('raw/realisasi2025alldept.csv');
const list2026 = extractBridgeNames('raw/realisasi2026alldept.csv');

// Merge and deduplicate
const allBridges = [...list2025, ...list2026];
const uniqueBridges = [];
const seen = new Set();

allBridges.forEach(b => {
    const key = b.nama.toLowerCase() + b.kecamatan.toLowerCase();
    if (!seen.has(key)) {
        uniqueBridges.push(b);
        seen.add(key);
    }
});

fs.writeFileSync('data/bridges_to_scrape.json', JSON.stringify(uniqueBridges, null, 2));
console.log(`Berhasil mengekstraksi ${uniqueBridges.length} nama jembatan rill dari data anggaran untuk di-scrapping.`);
