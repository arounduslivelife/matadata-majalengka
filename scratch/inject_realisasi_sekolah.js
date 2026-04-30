const fs = require('fs');

const realisasiFile = './data/realisasi20252026alldept.json';
const schoolFile = './data/schools_ultra_clean.geojson';

const realisasi = JSON.parse(fs.readFileSync(realisasiFile, 'utf8'));
const schools = JSON.parse(fs.readFileSync(schoolFile, 'utf8'));

// Normalize strings for matching
function normalizeName(name) {
    if (!name) return '';
    let n = name.toUpperCase();
    
    // Normalize prefixes
    n = n.replace(/SD NEGERI /g, 'SDN ');
    n = n.replace(/SD N /g, 'SDN ');
    n = n.replace(/SMP NEGERI /g, 'SMPN ');
    n = n.replace(/SMP N /g, 'SMPN ');
    n = n.replace(/SMA NEGERI /g, 'SMAN ');
    n = n.replace(/SMA N /g, 'SMAN ');
    n = n.replace(/SMK NEGERI /g, 'SMKN ');
    n = n.replace(/SMK N /g, 'SMKN ');
    n = n.replace(/TK NEGERI /g, 'TKN ');
    
    // Convert roman numerals to numbers for common cases (I, II, III, IV, V, VI)
    // Be careful with this, but it often helps with SDN CIHAUR III -> SDN CIHAUR 3
    n = n.replace(/\bI\b/g, '1');
    n = n.replace(/\bII\b/g, '2');
    n = n.replace(/\bIII\b/g, '3');
    n = n.replace(/\bIV\b/g, '4');
    n = n.replace(/\bV\b/g, '5');
    n = n.replace(/\bVI\b/g, '6');

    // Remove punctuation
    n = n.replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, ' ');
    // Remove extra spaces
    n = n.replace(/\s{2,}/g, ' ').trim();
    
    return n;
}

let matchedProjectsCount = 0;
let schoolsWithProjectsCount = 0;

// Filter school-related projects first to speed up
const schoolProjects = realisasi.data.filter(p => {
    const n = (p.nama_paket || '').toUpperCase();
    const satker = (p.nama_satuan_kerja || '').toUpperCase();
    // Only look at projects that are likely related to schools
    // Sometimes the satker is DINAS PENDIDIKAN
    if (satker.includes('DINAS PENDIDIKAN')) return true;
    if (n.includes(' SDN ') || n.includes(' SMPN ') || n.includes(' SD ') || n.includes(' SMP ') || n.includes('SEKOLAH') || n.includes('RUANG KELAS') || n.includes('LABORATORIUM')) return true;
    return false;
});

console.log(`Found ${schoolProjects.length} potential school-related projects out of ${realisasi.data.length} total projects.`);

// Prepare school features
schools.features.forEach(f => {
    f.properties.paket = []; // Initialize empty array
    f.properties.normalized_nama = normalizeName(f.properties.nama);
});

// Try to match each project to a school
schoolProjects.forEach(proj => {
    const nPaket = normalizeName(proj.nama_paket);
    let matched = false;

    // Search for a matching school
    for (let f of schools.features) {
        const nSchool = f.properties.normalized_nama;
        
        // If the school name is too generic like "SDN 1" we might false match.
        // We require the school name length to be at least 6 chars to avoid matching "SDN 1" in "SDN 10 MAJALENGKA".
        if (nSchool.length < 6) continue;

        // Ensure word boundary match if we're just doing includes
        // e.g., to prevent "SDN 1 BANTARUJEG" matching inside "SDN 11 BANTARUJEG"
        // We can check if nPaket includes nSchool, and if so, check word boundaries.
        if (nPaket.includes(nSchool)) {
            // Check boundary to avoid SDN 1 matching SDN 11
            const regex = new RegExp(`\\b${nSchool}\\b`);
            if (regex.test(nPaket)) {
                f.properties.paket.push({
                    tahun: proj.tahun_anggaran,
                    status: proj.status_paket,
                    nama_paket: proj.nama_paket,
                    nilai: proj.total_nilai_rp,
                    vendor: proj.nama_penyedia,
                    sumber_dana: proj.sumber_dana
                });
                matched = true;
                matchedProjectsCount++;
                break; // One project usually belongs to one school
            }
        }
    }
});

schools.features.forEach(f => {
    if (f.properties.paket.length > 0) schoolsWithProjectsCount++;
    delete f.properties.normalized_nama; // Clean up
});

fs.writeFileSync(schoolFile, JSON.stringify(schools, null, 2));

console.log(`Matching Complete!`);
console.log(`Total projects matched and injected: ${matchedProjectsCount}`);
console.log(`Total schools receiving projects: ${schoolsWithProjectsCount}`);
