const fs = require('fs');
const path = require('path');

function processBridges() {
    const f25 = JSON.parse(fs.readFileSync('scratch/realisasi_2025_all.json', 'utf-8'));
    const f26 = JSON.parse(fs.readFileSync('scratch/realisasi_2026_all.json', 'utf-8'));

    const searchKey = 'Belanja Modal Jembatan pada Jalan Kabupaten';
    const allData = [...f25, ...f26];

    const results = allData
        .filter(p => p['Nama Paket'].includes(searchKey))
        .map(p => {
            const fullName = p['Nama Paket'];
            
            // Extract bridge name: usually inside brackets or after common prefixes
            let bridgeName = 'Tidak Terdeteksi';
            
            // Method 1: Extract from brackets ()
            const bracketMatch = fullName.match(/\(([^)]+)\)/);
            if (bracketMatch) {
                bridgeName = bracketMatch[1];
            } else {
                // Method 2: Fallback to everything after prefix
                bridgeName = fullName.split(searchKey)[1]?.trim() || fullName;
            }

            // Cleanup common extra words
            bridgeName = bridgeName
                .replace(/^Jembatan\s+/i, '')
                .replace(/^Rehabilitasi\s+/i, '')
                .replace(/^Pembangunan\s+/i, '')
                .trim();

            return {
                tahun: p['Tahun Anggaran'],
                nama_paket: fullName,
                jembatan: bridgeName,
                pagu: p['Total Nilai (Rp)'],
                penyedia: p['Nama Penyedia'] || 'NON-TENDER/DATA KOSONG',
                metode: p['Metode Pengadaan'],
                satker: p['Nama Satuan Kerja']
            };
        });

    fs.writeFileSync('scratch/paket_jembatan_all.json', JSON.stringify(results, null, 2));
    console.log(`Berhasil mengekstrak ${results.length} paket jembatan ke scratch/paket_jembatan_all.json`);
    
    // Preview the findings
    results.forEach(r => {
        console.log(`[${r.tahun}] ${r.jembatan} - Rp ${r.pagu.toLocaleString('id-ID')}`);
    });
}

processBridges();
