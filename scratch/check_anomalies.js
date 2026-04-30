const fs = require('fs');

const csvPath = 'c:/xampp/htdocs/matadata/raw/realisasiperbaikanjalankabmajalengka2026.csv';
const content = fs.readFileSync(csvPath, 'utf8');
const lines = content.split('\n');

const headers = lines[0].split(',');
const anomalies = [];

for (let i = 1; i < lines.length; i++) {
    if (!lines[i].trim()) continue;
    
    const row = [];
    let current = '';
    let inQuotes = false;
    for (let char of lines[i]) {
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            row.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    row.push(current.trim());

    const nilai = parseFloat(row[12]);
    const metode = row[8];
    const sumberTransaksi = row[5];
    const namaPaket = row[10];

    if (metode === 'Pengadaan Langsung' && nilai > 200000000) {
        anomalies.push({
            namaPaket,
            nilai,
            metode,
            sumberTransaksi
        });
    }
}

console.log('Paket Pengadaan Langsung > 200 Juta:');
console.table(anomalies);
