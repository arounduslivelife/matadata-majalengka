const fs = require('fs');
const readline = require('readline');

async function analyzeCSV() {
    const fileStream = fs.createReadStream('raw/realisasi2026alldept.csv');
    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });

    let header = null;
    const vendorMap = new Map();
    const satkerMap = new Map();
    const methodMap = new Map();
    const statusMap = new Map();
    let rowCount = 0;

    for await (const line of rl) {
        if (!header) {
            header = line.split(',');
            continue;
        }
        
        // Simple CSV parse (doesn't handle commas in quotes but good enough for quick stats)
        const parts = line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
        if (parts.length < 10) continue;

        const satker = parts[1];
        const vendor = parts[7];
        const method = parts[8];
        const status = parts[11];
        
        vendorMap.set(vendor, (vendorMap.get(vendor) || 0) + 1);
        satkerMap.set(satker, (satkerMap.get(satker) || 0) + 1);
        methodMap.set(method, (methodMap.get(method) || 0) + 1);
        statusMap.set(status, (statusMap.get(status) || 0) + 1);
        rowCount++;
    }

    console.log('Total Rows:', rowCount);
    console.log('Top 10 Vendors:', Array.from(vendorMap.entries()).sort((a,b)=>b[1]-a[1]).slice(0, 10));
    console.log('Top 10 Satker:', Array.from(satkerMap.entries()).sort((a,b)=>b[1]-a[1]).slice(0, 10));
    console.log('Methods:', Array.from(methodMap.entries()));
    console.log('Statuses:', Array.from(statusMap.entries()));
}

analyzeCSV();
