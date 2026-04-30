const fs = require('fs');
const path = require('path');

function csvToJson(csvPath, jsonPath) {
    console.log(`Processing: ${csvPath}...`);
    const content = fs.readFileSync(csvPath, 'utf-8');
    const lines = content.split(/\r?\n/);
    if (lines.length === 0) return;

    // Handle possible quoted headers with commas
    const headers = lines[0].split(',').map(h => h.replace(/^"|"$/g, '').trim());
    const results = [];

    for (let i = 1; i < lines.length; i++) {
        if (!lines[i].trim()) continue;

        // Simple CSV parser that handles quotes
        const row = [];
        let inQuotes = false;
        let currentValue = '';
        
        for (let char of lines[i]) {
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === ',' && !inQuotes) {
                row.push(currentValue.replace(/^"|"$/g, '').trim());
                currentValue = '';
            } else {
                currentValue += char;
            }
        }
        row.push(currentValue.replace(/^"|"$/g, '').trim());

        if (row.length < headers.length) continue;

        const obj = {};
        headers.forEach((h, index) => {
            let val = row[index] || '';
            
            // Clean numeric fields
            if (h === 'Total Nilai (Rp)' || h === 'Nilai PDN (Rp)') {
                val = parseFloat(val.replace(/[^0-9.-]+/g, '')) || 0;
            }
            
            obj[h] = val;
        });
        results.push(obj);
    }

    fs.writeFileSync(jsonPath, JSON.stringify(results, null, 2));
    console.log(`Successfully saved ${results.length} rows to ${jsonPath}`);
}

// Convert both files
csvToJson(
    path.join('raw', 'realisasi2025alldept.csv'),
    path.join('scratch', 'realisasi_2025_all.json')
);

csvToJson(
    path.join('raw', 'realisasi2026alldept.csv'),
    path.join('scratch', 'realisasi_2026_all.json')
);
