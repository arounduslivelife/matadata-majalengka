const fs = require('fs');

// ========== LOAD DATA ==========
const schools = JSON.parse(fs.readFileSync('./data/found_schools_ultra.json', 'utf8'));
const districts = JSON.parse(fs.readFileSync('./districts.geojson', 'utf8'));

console.log('=== SANITIZE found_schools_ultra.json ===');
console.log(`Input: ${schools.data.length} entri\n`);

// ========== POINT-IN-POLYGON ==========
function pointInPolygon(lat, lng, polygon) {
    let inside = false;
    for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
        const xi = polygon[i][1], yi = polygon[i][0];
        const xj = polygon[j][1], yj = polygon[j][0];
        const intersect = ((yi > lng) !== (yj > lng)) &&
            (lat < (xj - xi) * (lng - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function getActualKecamatan(lat, lng) {
    for (const feature of districts.features) {
        const geom = feature.geometry;
        let polygons = [];
        if (geom.type === 'Polygon') {
            polygons = [geom.coordinates[0]];
        } else if (geom.type === 'MultiPolygon') {
            polygons = geom.coordinates.map(p => p[0]);
        }
        for (const poly of polygons) {
            if (pointInPolygon(lat, lng, poly)) {
                return feature.properties.nm_kecamatan || feature.properties.NAMOBJ || null;
            }
        }
    }
    return null;
}

// ========== BLACKLIST: Non-school entries ==========
const BLACKLIST_EXACT = [
    'arie andhika aditya', 'situ sangiang', 'ngampar samak', 'toko yoyoh',
    'kantor kepala desa', 'katanggur', 'nepnep center', 'cikijing',
    'barudak kunans', 'mia sri mulyano', 'kopponten bina insani',
    'liiistore', 'cikidul ahuy', 'pengacara', 'isfia', 'alex',
    'bantarujeg', 'rumah', 'mr.hafid celluler', 'x mipa 1',
    'kantinku', 'zaenal arifin', 'imah anas', 'puswa corporation',
    'rumah cahyadi', 'rumah cecaaa', 'ds.jagsari', 'pasantren cikijing',
    'p4s karangsari', 't', 'j', 'cibeureum kec. sukamantri',
    '7 h 2023', 'laksana', 'fani lustiani', 'mekarguna farm',
    'nafadila', 'saung ewe paksa', 'haikal', 'situ ciranca',
    'hasby arranger', 'pd. khodijah jaya', 'garasi ibnu sina',
    'sm sulungmakmur', 'cipeundeuy endah', 'bimbel ahe maja',
    'rumah english dream academy', 'homekids maharani', 'curug cipeuteuy',
    'eki kunaon', 'ssb mw sindangwangi', 'aa farm', 'tresna',
    'desa babakan manjeti', 'little room misha', 'istafarm',
    'rajawangi', 'toko bintang', 'aldi well', 'ranggon guri',
    'rumah empang', 'masook', 'dusun tugu', 'galery computer',
    'jagat furniture', 'nihon sora', 'konveksi seragam sekolah',
    'nurul barokah', // generic place name, not school
    'min gunung manik', // wrong - this is a location name
];

const BLACKLIST_PARTIAL = [
    'brilink', 'furniture', 'galery computer', 'garasi ', 'basecamp literasi',
    'rorompok psbb', 'labuda pamondokan', 'bimbel yescourse',
    'kumon ', 'shift academy', 'aufa eduvibes',
    'lpk shiori', 'lpk kazoku', 'lpk jinsei', 'lpk cis ',
    'lpk rucita', 'lembaga kursus dan pelatihan',
    'kantor pusat operasional pkbm', // operational office not school
    'konveksi seragam',
];

// Keywords that indicate it IS a school/education institution
const SCHOOL_KEYWORDS = [
    'sd ', 'sdn ', 'sd negeri', 'sdn', 'sdit', 'sd it',
    'smp ', 'smpn', 'smp negeri', 'smp islam', 'smp terbuka', 'smp it',
    'sma ', 'sman', 'sma negeri',
    'smk ', 'smkn', 'smk negeri', 'smks',
    'tk ', 'tkb', 'tkit', 'tk budi', 'tk kartini', 'tk dwi', 'tk mekar',
    'paud', 'kober', 'kelompok bermain', 'sps ',
    'mi ', 'mis ', 'min ', 'mi pui', 'mi at-', 'mi al-',
    'mts', 'ma ', 'man ', 'mas ', 'mda ', 'mdta',
    'madrasah', 'madradah', 'madrosah',
    'sekolah', 'college',
    'pesantren', 'pondok pesantren', 'ponpes', 'pon-pes', 'pp ',
    'raudhatul', 'ra ', 'ra/', 'ra.',
    'dta ', 'dta pui', 'tpq', 'tpa ',
    'slb ', 'sltpn',
    'yayasan', // many yayasan are educational
    'pkbm', 'stai', 'stie',
    'lab ipa', 'perpustakaan', 'toilet smpn', 'lapangan sepak bola smpn',
    'sekretariat himpaudi',
    'rumah tahfidz', 'rumah tahfidzh',
    'lpq ', 'r.a ', 'r.a.',
    'kb ', 'kb ceria', 'kb nuurul', 'kb mekar', 'kb sindang', 'kb al-',
    'sit ', // sekolah islam terpadu
    'unggulan amanatul',
    'pendidikan islam', 'pendidikan diniyah',
    'institut pesantren',
    'sekolah tinggi',
    'bimbingan', 'diniyah takmiliyah',
    'nurul iman islamic school',
    'المدرسة', // Arabic school text
    'majlis ta', "majlis ta'lim",
    'ma\'had', "ma'had",
    'kaizen madani center', // educational center
    'pengajian',
    'badrasah',
    "roudotul qur'an",
    'sdk-smpk', // combined school
];

function isBlacklisted(nama) {
    const n = nama.toLowerCase().trim();
    // Check exact blacklist
    for (const bl of BLACKLIST_EXACT) {
        if (n === bl || n.startsWith(bl)) return true;
    }
    // Check partial blacklist
    for (const bl of BLACKLIST_PARTIAL) {
        if (n.includes(bl)) return true;
    }
    return false;
}

function isSchoolLike(nama) {
    const n = nama.toLowerCase().trim();
    for (const kw of SCHOOL_KEYWORDS) {
        if (n.includes(kw)) return true;
    }
    return false;
}

// ========== CLASSIFY SCHOOL TYPE ==========
function classifySchoolType(nama) {
    const n = nama.toLowerCase();
    if (n.includes('paud') || n.includes('kober') || n.includes('kelompok bermain') || 
        n.includes('sps ') || n.includes('kb ')) return 'PAUD';
    if (/\btk\b/.test(n) || n.includes('tkit') || n.includes('tkb') || 
        n.includes('raudhatul') || /\bra[\s\/\.]/.test(n) || n.startsWith('ra ')) return 'TK/RA';
    if (/\bsd[\s]/.test(n) || n.includes('sdn') || n.includes('sd negeri') || 
        n.includes('sdit') || n.includes('sd it') || /\bmi[\s]/.test(n) || 
        n.includes('mis ') || n.includes('min ') || n.includes('madrasah ibtidai')) return 'SD/MI';
    if (n.includes('smp') || n.includes('mts') || n.includes('sltp') || 
        n.includes('smp terbuka')) return 'SMP/MTs';
    if (n.includes('sma') || /\bma[\s]/.test(n) || n.includes('man ') || 
        n.includes('mas ') || n.includes('madrasah aliyah')) return 'SMA/MA';
    if (n.includes('smk')) return 'SMK';
    if (n.includes('slb')) return 'SLB';
    if (n.includes('pesantren') || n.includes('pondok') || n.includes('ponpes') || 
        n.includes('pp ') || n.includes("ma'had")) return 'Pesantren';
    if (n.includes('stai') || n.includes('stie') || n.includes('college') || 
        n.includes('sekolah tinggi') || n.includes('institut')) return 'Perguruan Tinggi';
    if (n.includes('dta') || n.includes('mdta') || n.includes('mda') || 
        n.includes('tpq') || n.includes('tpa') || n.includes('lpq') || 
        n.includes('diniyah')) return 'Diniyah/TPQ';
    if (n.includes('pkbm')) return 'PKBM';
    if (n.includes('yayasan')) return 'Yayasan Pendidikan';
    return 'Lainnya';
}

// ========== NORMALIZE NAME ==========
function normalizeName(nama) {
    let n = nama.trim();
    // Fix common encoding issues
    n = n.replace(/\s+/g, ' ');
    return n;
}

// ========== MAIN PIPELINE ==========
const stats = {
    total_input: schools.data.length,
    removed_outside: 0,
    removed_blacklist: 0,
    removed_nonschool: 0,
    fixed_kecamatan: 0,
    kept: 0,
};

const cleanData = [];
const removedOutside = [];
const removedBlacklist = [];
const removedNonSchool = [];
const fixedKecamatan = [];

for (const s of schools.data) {
    // Step 1: Geofence - check if inside Majalengka
    const actualKec = getActualKecamatan(s.lat, s.lng);
    if (!actualKec) {
        stats.removed_outside++;
        removedOutside.push(s);
        continue;
    }

    // Step 2: Blacklist filter
    if (isBlacklisted(s.nama)) {
        stats.removed_blacklist++;
        removedBlacklist.push(s);
        continue;
    }

    // Step 3: School-likeness check
    if (!isSchoolLike(s.nama)) {
        stats.removed_nonschool++;
        removedNonSchool.push({ ...s, actualKec });
        continue;
    }

    // Step 4: Fix kecamatan based on actual coordinates
    const originalKec = s.kecamatan;
    const wasFixed = originalKec.toUpperCase() !== actualKec.toUpperCase();
    if (wasFixed) {
        stats.fixed_kecamatan++;
        fixedKecamatan.push({ nama: s.nama, from: originalKec, to: actualKec });
    }

    // Step 5: Normalize and add
    cleanData.push({
        nama: normalizeName(s.nama),
        kecamatan: actualKec, // Use actual kecamatan from geofence
        lat: s.lat,
        lng: s.lng,
        tipe: classifySchoolType(s.nama),
    });
}

stats.kept = cleanData.length;

// ========== DEDUPLICATE ==========
// Remove exact coordinate duplicates (keep first)
const seen = new Set();
const dedupedData = [];
let dupeCount = 0;
for (const s of cleanData) {
    const key = `${s.lat},${s.lng}`;
    if (seen.has(key)) {
        dupeCount++;
        continue;
    }
    seen.add(key);
    dedupedData.push(s);
}

// Also remove near-duplicate names in same kecamatan (fuzzy)
const seenNames = new Set();
const finalData = [];
let nameDupeCount = 0;
for (const s of dedupedData) {
    const nameKey = s.nama.toLowerCase().replace(/[^a-z0-9]/g, '') + '|' + s.kecamatan;
    if (seenNames.has(nameKey)) {
        nameDupeCount++;
        continue;
    }
    seenNames.add(nameKey);
    finalData.push(s);
}

// ========== OUTPUT ==========
const output = {
    metadata: {
        source: 'found_schools_ultra.json (sanitized)',
        sanitized_at: new Date().toISOString(),
        total_original: stats.total_input,
        total_clean: finalData.length,
        removed_outside_majalengka: stats.removed_outside,
        removed_blacklist: stats.removed_blacklist,
        removed_non_school: stats.removed_nonschool,
        removed_coord_duplicates: dupeCount,
        removed_name_duplicates: nameDupeCount,
        fixed_kecamatan: stats.fixed_kecamatan,
    },
    processed: [...new Set(finalData.map(s => s.kecamatan))].sort(),
    data: finalData
};

fs.writeFileSync('./data/schools_ultra_clean.json', JSON.stringify(output, null, 2), 'utf8');

// ========== ALSO OUTPUT AS GEOJSON ==========
const geojson = {
    type: 'FeatureCollection',
    features: finalData.map(s => ({
        type: 'Feature',
        geometry: {
            type: 'Point',
            coordinates: [s.lng, s.lat]
        },
        properties: {
            nama: s.nama,
            kecamatan: s.kecamatan,
            tipe: s.tipe,
            sumber: 'Google Maps Scraping (Ultra)'
        }
    }))
};

fs.writeFileSync('./data/schools_ultra_clean.geojson', JSON.stringify(geojson), 'utf8');

// ========== REPORT ==========
console.log('========================================');
console.log('         LAPORAN SANITASI');
console.log('========================================');
console.log(`Input            : ${stats.total_input} entri`);
console.log(`❌ Luar Majalengka: -${stats.removed_outside}`);
console.log(`❌ Blacklist      : -${stats.removed_blacklist}`);
console.log(`❌ Non-sekolah    : -${stats.removed_nonschool}`);
console.log(`❌ Duplikat coord : -${dupeCount}`);
console.log(`❌ Duplikat nama  : -${nameDupeCount}`);
console.log(`🔧 Fix kecamatan  : ${stats.fixed_kecamatan} diperbaiki`);
console.log('----------------------------------------');
console.log(`✅ OUTPUT BERSIH  : ${finalData.length} entri`);
console.log('========================================');

// Distribution by type
const byType = {};
finalData.forEach(s => { byType[s.tipe] = (byType[s.tipe] || 0) + 1; });
console.log('\n--- Distribusi per Tipe ---');
Object.entries(byType).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => {
    console.log(`  ${k}: ${v}`);
});

// Distribution by kecamatan
const byKec = {};
finalData.forEach(s => { byKec[s.kecamatan] = (byKec[s.kecamatan] || 0) + 1; });
console.log('\n--- Distribusi per Kecamatan ---');
Object.entries(byKec).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => {
    console.log(`  ${k}: ${v}`);
});

// Show removed non-school for review
console.log('\n--- Entri yang DIBUANG (non-sekolah, review) ---');
removedNonSchool.forEach(s => {
    console.log(`  [${s.kecamatan}→${s.actualKec}] "${s.nama}"`);
});

console.log('\n✅ File tersimpan:');
console.log('  → data/schools_ultra_clean.json');
console.log('  → data/schools_ultra_clean.geojson');
