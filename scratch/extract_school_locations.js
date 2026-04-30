const fs = require('fs');

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

        if (satker.includes('PENDIDIKAN')) {
            // Regex to extract School Name and Kecamatan
            // Pattern: Lokasi : [NAMA SEKOLAH] Kecamatan [KECAMATAN]
            const locMatch = namaPaket.match(/Lokasi\s*:\s*([^,]+?)\s*Kecamatan\s*([^\s,]+)/i);
            if (locMatch) {
                projects.push({
                    type: 'sekolah',
                    nama_sekolah: locMatch[1].trim(),
                    kecamatan: locMatch[2].trim(),
                    paket_nama: namaPaket,
                    pagu,
                    vendor,
                    tahun
                });
            }
        }
    });
}

parseCSV('raw/realisasi2025alldept.csv', 2025);
parseCSV('raw/realisasi2026alldept.csv', 2026);

console.log(`Extracted ${projects.length} verified school project locations from CSV.`);

// Group by School Name + Kecamatan
const schoolMap = {};
projects.forEach(p => {
    const key = `${p.nama_sekolah}|${p.kecamatan}`.toLowerCase();
    if (!schoolMap[key]) {
        schoolMap[key] = {
            nama: p.nama_sekolah,
            kecamatan: p.kecamatan,
            paket: []
        };
    }
    schoolMap[key].paket.push({ nama: p.paket_nama, pagu: p.pagu, vendor: p.vendor, tahun: p.tahun });
});

// Create new Schools Features with "Approximate Kecamatan Coordinates" for now
// (Will be refined by geocoder later)
// For now, let's just create a list to geocode.
const schoolsToGeocode = Object.values(schoolMap);

fs.writeFileSync('data/schools_to_geocode.json', JSON.stringify(schoolsToGeocode, null, 2));
console.log(`Saved ${schoolsToGeocode.length} unique schools for re-geocoding.`);
