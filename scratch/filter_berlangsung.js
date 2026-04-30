const fs = require('fs');

const csvPath = 'c:/xampp/htdocs/matadata/raw/realisasiperbaikanjalankabmajalengka2026.csv';
const content = fs.readFileSync(csvPath, 'utf8');
const lines = content.split('\n');

const headers = lines[0].split(',');
const results = [];

for (let i = 1; i < lines.length; i++) {
    if (!lines[i].trim()) continue;
    
    // Handle CSV quoting for names with commas
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

    if (row[11] === 'BERLANGSUNG' && (row[10] || '').startsWith('Belanja Modal Jalan Kabupaten')) {
        const obj = {};
        headers.forEach((header, index) => {
            obj[header.trim()] = row[index] || '';
        });
        results.push(obj);
    }
}

const outputFile = 'c:/xampp/htdocs/matadata/scratch/berlangsung.json';
fs.writeFileSync(outputFile, JSON.stringify(results, null, 2));
console.log(`Success! Data saved to ${outputFile}`);
