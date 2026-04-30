const fs = require('fs');

/**
 * Smart Matching v2: Parse kecamatan/desa dari nama paket realisasi
 * Paket yang match lokasi tapi bukan titik GPS → centroid desa + "lokasi belum terverifikasi"
 */

const boundaries = JSON.parse(fs.readFileSync('majalengka_kecamatan.json', 'utf8'));
const realisasi = JSON.parse(fs.readFileSync('data/realisasi20252026alldept.json', 'utf8'));

// --- Build lokasi database dari polygon ---
const lokasiDB = [];
boundaries.features.forEach(f => {
    const district = f.properties.district;
    const village = f.properties.village;
    
    // Hitung centroid dari polygon
    const coords = f.geometry.type === 'MultiPolygon' 
        ? f.geometry.coordinates[0][0] 
        : f.geometry.coordinates[0];
    
    let sumLng = 0, sumLat = 0;
    coords.forEach(c => { sumLng += c[0]; sumLat += c[1]; });
    const centroid = [sumLng / coords.length, sumLat / coords.length];
    
    lokasiDB.push({ district, village, centroid });
});

console.log(`Loaded ${lokasiDB.length} lokasi (desa/kecamatan)`);

// --- Buat daftar nama unik untuk matching ---
const allDistricts = [...new Set(lokasiDB.map(l => l.district))];
const allVillages = lokasiDB.map(l => ({ name: l.village, district: l.district, centroid: l.centroid }));

console.log(`Districts: ${allDistricts.length}, Villages: ${allVillages.length}`);

// --- Fungsi cari lokasi dari teks ---
function findLocationInText(text) {
    const lower = text.toLowerCase();
    
    // Cari nama desa dulu (lebih spesifik)
    for (const v of allVillages) {
        if (v.name.length >= 4 && lower.includes(v.name.toLowerCase())) {
            return { 
                kecamatan: v.district, 
                desa: v.name, 
                centroid: v.centroid,
                matchType: 'desa'
            };
        }
    }
    
    // Cari nama kecamatan
    for (const d of allDistricts) {
        if (d.length >= 4 && lower.includes(d.toLowerCase())) {
            const desaInDistrict = lokasiDB.find(l => l.district === d);
            return {
                kecamatan: d,
                desa: null,
                centroid: desaInDistrict ? desaInDistrict.centroid : null,
                matchType: 'kecamatan'
            };
        }
    }
    
    return null;
}

// ============ PROSES JEMBATAN ============
function processBridges() {
    console.log('\n=== SMART MATCHING: JEMBATAN ===');
    
    const bridgeKeywords = ['jembatan', 'sasak', 'jbt'];
    const bridgePakets = realisasi.data.filter(r =>
        bridgeKeywords.some(k => (r.nama_paket || '').toLowerCase().includes(k))
    );
    
    const geo = JSON.parse(fs.readFileSync('data/jembatan_kabupaten.geojson', 'utf8'));
    
    // Cari paket yang belum ter-match
    const matchedPaketIds = new Set();
    geo.features.forEach(f => {
        (f.properties.paket || []).forEach(p => {
            matchedPaketIds.add(p.kode_paket || p.nama_paket);
        });
    });
    
    const unmatchedPakets = bridgePakets.filter(p => 
        !matchedPaketIds.has(p.kode_paket) && !matchedPaketIds.has(p.nama_paket)
    );
    
    console.log(`Total paket jembatan: ${bridgePakets.length}`);
    console.log(`Sudah ter-match: ${matchedPaketIds.size}`);
    console.log(`Belum ter-match: ${unmatchedPakets.length}`);
    
    let locationMatched = 0;
    let noLocation = 0;
    const noLocationList = [];
    
    unmatchedPakets.forEach(p => {
        // Cari lokasi di nama paket + satker
        const searchText = `${p.nama_paket} ${p.nama_satuan_kerja}`;
        const location = findLocationInText(searchText);
        
        if (location && location.centroid) {
            // Tambah titik baru dengan keterangan "belum terverifikasi"
            const newFeature = {
                type: "Feature",
                geometry: {
                    type: "Point",
                    coordinates: location.centroid
                },
                properties: {
                    nama: extractBridgeName(p.nama_paket),
                    kecamatan: location.kecamatan,
                    desa_geo: location.desa,
                    catatan: "Lokasi Belum Terverifikasi (Estimasi Centroid Desa)",
                    is_project: true,
                    verified: false,
                    paket: [{
                        tahun: p.tahun_anggaran,
                        nama_paket: p.nama_paket,
                        penyedia: p.nama_penyedia,
                        nilai: p.total_nilai_rp,
                        status: p.status_paket,
                        satker: p.nama_satuan_kerja,
                        sumber_dana: p.sumber_dana,
                        metode: p.metode_pengadaan,
                        kode_paket: p.kode_paket
                    }]
                }
            };
            geo.features.push(newFeature);
            locationMatched++;
            console.log(`  📍 ${newFeature.properties.nama} → ${location.kecamatan}/${location.desa || '?'} (${location.matchType})`);
        } else {
            noLocation++;
            noLocationList.push(p.nama_paket);
        }
    });
    
    console.log(`\nHasil:`);
    console.log(`  Berhasil dilokasi (estimasi): ${locationMatched}`);
    console.log(`  Tidak bisa dilokasi sama sekali: ${noLocation}`);
    
    if (noLocationList.length > 0) {
        console.log(`\n  Paket tanpa lokasi:`);
        noLocationList.forEach(n => console.log(`    ❌ ${n.substring(0, 90)}`));
    }
    
    fs.writeFileSync('data/jembatan_kabupaten.geojson', JSON.stringify(geo, null, 2));
    console.log(`\n  File tersimpan ✅ (${geo.features.length} total features)`);
}

function extractBridgeName(paketName) {
    // Coba ekstrak nama jembatan dari nama paket
    const match = paketName.match(/(?:jembatan|sasak)\s+(\w[\w\s]*?)(?:\s*\)|,|\s+pada|\s+di|\s+kec)/i);
    if (match) return `Jembatan ${match[1].trim()}`;
    
    const match2 = paketName.match(/(?:jembatan|sasak)\s+(\w+)/i);
    if (match2) return `Jembatan ${match2[1].trim()}`;
    
    return paketName.substring(0, 60);
}

// ============ PROSES SEKOLAH ============
function processSchools() {
    console.log('\n=== SMART MATCHING: SEKOLAH ===');
    
    const eduPakets = realisasi.data.filter(r => {
        const satker = (r.nama_satuan_kerja || '').toLowerCase();
        const nama = (r.nama_paket || '').toLowerCase();
        const isEdu = satker.includes('dinas pendidikan') || satker.includes('sekolah') ||
            /\b(sdn|smpn|sman|smkn|sd negeri|smp negeri|sma negeri|smk negeri|paud|tkit|madrasah|mtsn|min |man )\b/i.test(nama);
        const isExcluded = ['puskesmas', 'dinas kesehatan', 'rumah sakit', 'rsud'].some(k => satker.includes(k));
        return !isExcluded && isEdu;
    });
    
    const geo = JSON.parse(fs.readFileSync('data/sarana_pendidikan.geojson', 'utf8'));
    
    const matchedPaketIds = new Set();
    geo.features.forEach(f => {
        (f.properties.paket || []).forEach(p => {
            matchedPaketIds.add(p.kode_paket || p.nama_paket);
        });
    });
    
    const unmatchedPakets = eduPakets.filter(p =>
        !matchedPaketIds.has(p.kode_paket) && !matchedPaketIds.has(p.nama_paket)
    );
    
    console.log(`Total paket pendidikan: ${eduPakets.length}`);
    console.log(`Sudah ter-match: ${matchedPaketIds.size}`);
    console.log(`Belum ter-match: ${unmatchedPakets.length}`);
    
    let locationMatched = 0;
    let noLocation = 0;
    
    unmatchedPakets.forEach(p => {
        const searchText = `${p.nama_paket} ${p.nama_satuan_kerja}`;
        const location = findLocationInText(searchText);
        
        if (location && location.centroid) {
            const newFeature = {
                type: "Feature",
                geometry: {
                    type: "Point",
                    coordinates: location.centroid
                },
                properties: {
                    nama: p.nama_paket.substring(0, 80),
                    kecamatan: location.kecamatan,
                    desa_geo: location.desa,
                    catatan: "Lokasi Belum Terverifikasi (Estimasi Centroid Desa)",
                    is_project: true,
                    verified: false,
                    paket: [{
                        tahun: p.tahun_anggaran,
                        nama_paket: p.nama_paket,
                        penyedia: p.nama_penyedia,
                        nilai: p.total_nilai_rp,
                        status: p.status_paket,
                        satker: p.nama_satuan_kerja,
                        sumber_dana: p.sumber_dana,
                        metode: p.metode_pengadaan,
                        kode_paket: p.kode_paket
                    }]
                }
            };
            geo.features.push(newFeature);
            locationMatched++;
        } else {
            noLocation++;
        }
    });
    
    console.log(`\nHasil:`);
    console.log(`  Berhasil dilokasi (estimasi): ${locationMatched}`);
    console.log(`  Tidak bisa dilokasi: ${noLocation}`);
    
    fs.writeFileSync('data/sarana_pendidikan.geojson', JSON.stringify(geo, null, 2));
    console.log(`  File tersimpan ✅ (${geo.features.length} total features)`);
}

// ============ EKSEKUSI ============
processBridges();
processSchools();
console.log('\n✅ Smart Matching V2 selesai');
