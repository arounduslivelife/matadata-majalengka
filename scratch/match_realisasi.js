const fs = require('fs');

/**
 * Mencocokkan data realisasi ke titik jembatan & sekolah di GeoJSON.
 * Hasilnya: field `paket` di setiap feature berisi array paket yang match.
 */

const realisasi = JSON.parse(fs.readFileSync('data/realisasi20252026alldept.json', 'utf8'));

// ============ JEMBATAN ============

function matchBridges() {
    console.log('=== MATCHING JEMBATAN ===');
    
    const bridgeKeywords = ['jembatan', 'sasak', 'jbt'];
    const bridgePakets = realisasi.data.filter(r =>
        bridgeKeywords.some(k => (r.nama_paket || '').toLowerCase().includes(k))
    );
    console.log(`Paket realisasi terkait jembatan: ${bridgePakets.length}`);

    // Load kedua GeoJSON jembatan
    const files = [
        { path: 'data/jembatan_kabupaten.geojson', label: 'Anggaran' },
        { path: 'data/jembatan_deep.geojson', label: 'Scraped AI' }
    ];

    files.forEach(({ path, label }) => {
        if (!fs.existsSync(path)) return;
        const geo = JSON.parse(fs.readFileSync(path, 'utf8'));
        let totalMatched = 0;

        geo.features.forEach(f => {
            const bridgeName = (f.properties.nama || '').toLowerCase();
            const kecamatan = (f.properties.kecamatan || '').toLowerCase();
            
            // Cari paket yang nama-nya mengandung nama jembatan atau kecamatan
            const matched = bridgePakets.filter(p => {
                const paketName = (p.nama_paket || '').toLowerCase();
                
                // Match 1: Nama jembatan spesifik ada di nama paket
                if (bridgeName.length > 5) {
                    // Ambil kata kunci dari nama jembatan (hapus "jembatan", "sasak", dll)
                    const cleanName = bridgeName
                        .replace(/^(jembatan|sasak|eks jembatan)\s*/i, '')
                        .trim();
                    if (cleanName.length > 3 && paketName.includes(cleanName)) {
                        return true;
                    }
                }
                
                // Match 2: Kecamatan cocok DAN paket berkaitan dengan jembatan
                if (kecamatan && paketName.includes(kecamatan)) {
                    return true;
                }

                return false;
            });

            f.properties.paket = matched.map(p => ({
                tahun: p.tahun_anggaran,
                nama_paket: p.nama_paket,
                penyedia: p.nama_penyedia,
                nilai: p.total_nilai_rp,
                status: p.status_paket,
                satker: p.nama_satuan_kerja,
                sumber_dana: p.sumber_dana,
                metode: p.metode_pengadaan
            }));

            f.properties.is_project = matched.length > 0;
            if (matched.length > 0) totalMatched++;
        });

        console.log(`  ${label} (${path}): ${totalMatched}/${geo.features.length} jembatan punya paket realisasi`);
        fs.writeFileSync(path, JSON.stringify(geo, null, 2));
    });
}

// ============ SEKOLAH ============

function matchSchools() {
    console.log('\n=== MATCHING SEKOLAH ===');

    if (!fs.existsSync('data/sarana_pendidikan.geojson')) {
        console.log('File sekolah tidak ditemukan, skip.');
        return;
    }

    const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));

    // Untuk sekolah, kita match berdasarkan nama satker (unit kerja) yang spesifik ke sekolah
    const schoolSatkerKeywords = ['puskesmas', 'dinas kesehatan', 'rumah sakit', 'rsud'];
    
    // Filter paket dari satuan kerja pendidikan ATAU yang nama paketnya menyebut sekolah
    const eduPakets = realisasi.data.filter(r => {
        const satker = (r.nama_satuan_kerja || '').toLowerCase();
        const nama = (r.nama_paket || '').toLowerCase();
        
        // Harus dari satker pendidikan ATAU menyebut nama sekolah spesifik
        const isEduSatker = satker.includes('dinas pendidikan') || satker.includes('sekolah');
        const hasSchoolName = /\b(sdn|smpn|sman|smkn|sd negeri|smp negeri|sma negeri|smk negeri|paud|tkit|madrasah|mtsn|min |man )\b/i.test(nama);
        
        // Kecualikan yang bukan pendidikan
        const isExcluded = schoolSatkerKeywords.some(k => satker.includes(k));
        
        return !isExcluded && (isEduSatker || hasSchoolName);
    });

    console.log(`Paket realisasi terkait pendidikan: ${eduPakets.length}`);

    let totalMatched = 0;

    geo.features.forEach(f => {
        const schoolName = (f.properties.nama || f.properties.name || '').toLowerCase();
        const kecamatan = (f.properties.kecamatan || '').toLowerCase();

        const matched = eduPakets.filter(p => {
            const paketName = (p.nama_paket || '').toLowerCase();
            const satker = (p.nama_satuan_kerja || '').toLowerCase();

            // Match: Nama sekolah ada di nama paket atau satuan kerja
            if (schoolName.length > 4) {
                // Bersihkan nama sekolah
                const cleanName = schoolName
                    .replace(/^(sd negeri|sdn|smpn|sman|smkn|smk|smp|sma|paud|tk|tkit|mi|mts|man|madrasah)\s*/i, '')
                    .trim();
                
                if (cleanName.length > 2) {
                    if (paketName.includes(cleanName) || satker.includes(cleanName)) {
                        return true;
                    }
                }
                
                // Coba match nama lengkap
                if (paketName.includes(schoolName) || satker.includes(schoolName)) {
                    return true;
                }
            }

            return false;
        });

        f.properties.paket = matched.map(p => ({
            tahun: p.tahun_anggaran,
            nama_paket: p.nama_paket,
            penyedia: p.nama_penyedia,
            nilai: p.total_nilai_rp,
            status: p.status_paket,
            satker: p.nama_satuan_kerja,
            sumber_dana: p.sumber_dana,
            metode: p.metode_pengadaan
        }));

        f.properties.is_project = matched.length > 0;
        if (matched.length > 0) totalMatched++;
    });

    console.log(`  Sekolah dengan paket realisasi: ${totalMatched}/${geo.features.length}`);
    fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geo, null, 2));
}

// ============ EKSEKUSI ============
matchBridges();
matchSchools();

console.log('\n✅ Semua data telah di-match dengan realisasi 2025-2026');
