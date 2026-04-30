const fs = require('fs');

/**
 * Konversi CSV realisasi 2025 & 2026 ke JSON gabungan.
 * Menangani field yang mengandung koma di dalam tanda kutip.
 */

function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
            inQuotes = !inQuotes;
        } else if (ch === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += ch;
        }
    }
    result.push(current.trim());
    return result;
}

function parseCSV(filepath, tahun) {
    console.log(`\nParsing: ${filepath}`);
    const raw = fs.readFileSync(filepath, 'utf8');
    const lines = raw.split('\n').filter(l => l.trim().length > 0);

    const headers = parseCSVLine(lines[0]);
    console.log(`  Headers: ${headers.join(' | ')}`);
    console.log(`  Total baris data: ${lines.length - 1}`);

    const records = [];
    for (let i = 1; i < lines.length; i++) {
        const values = parseCSVLine(lines[i]);
        if (values.length < headers.length) continue;

        const obj = {};
        headers.forEach((h, idx) => {
            let key = h.replace(/\s+/g, '_').replace(/[()\/]/g, '').toLowerCase();
            let val = values[idx] || '';

            // Konversi angka untuk kolom nilai
            if (key.includes('nilai') || key === 'total_nilai_rp') {
                val = parseFloat(val.replace(/[^0-9.-]/g, '')) || 0;
            }
            if (key === 'tahun_anggaran') {
                val = parseInt(val) || tahun;
            }
            obj[key] = val;
        });

        records.push(obj);
    }

    console.log(`  Records parsed: ${records.length}`);
    return records;
}

// Parse kedua file
const data2025 = parseCSV('raw/realisasi2025alldept.csv', 2025);
const data2026 = parseCSV('raw/realisasi2026alldept.csv', 2026);

// Gabungkan
const combined = [...data2025, ...data2026];

// Statistik
const stats = {
    total_records: combined.length,
    tahun_2025: data2025.length,
    tahun_2026: data2026.length,
    total_nilai: combined.reduce((sum, r) => sum + (r.total_nilai_rp || 0), 0),
    unique_vendors: [...new Set(combined.map(r => r.nama_penyedia).filter(Boolean))].length,
    unique_satker: [...new Set(combined.map(r => r.nama_satuan_kerja).filter(Boolean))].length,
    status_breakdown: {}
};

combined.forEach(r => {
    const s = r.status_paket || 'UNKNOWN';
    stats.status_breakdown[s] = (stats.status_breakdown[s] || 0) + 1;
});

console.log('\n=== STATISTIK GABUNGAN ===');
console.log(`Total records : ${stats.total_records}`);
console.log(`  - 2025      : ${stats.tahun_2025}`);
console.log(`  - 2026      : ${stats.tahun_2026}`);
console.log(`Total Nilai   : Rp ${(stats.total_nilai / 1e9).toFixed(2)} Miliar`);
console.log(`Vendor Unik   : ${stats.unique_vendors}`);
console.log(`Satker Unik   : ${stats.unique_satker}`);
console.log(`Status:`);
Object.entries(stats.status_breakdown).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => {
    console.log(`  ${k}: ${v}`);
});

// Simpan
const output = {
    metadata: {
        generated_at: new Date().toISOString(),
        source_files: ['raw/realisasi2025alldept.csv', 'raw/realisasi2026alldept.csv'],
        stats: stats
    },
    data: combined
};

fs.writeFileSync('data/realisasi20252026alldept.json', JSON.stringify(output, null, 2));
console.log('\n✅ File tersimpan: data/realisasi20252026alldept.json');
console.log(`   Ukuran: ${(fs.statSync('data/realisasi20252026alldept.json').size / 1024 / 1024).toFixed(2)} MB`);
