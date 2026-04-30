
const fs = require('fs');
const readline = require('readline');

function parseCSVLine(line) {
    const result = [];
    let startValueIndex = 0;
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        if (line[i] === '"') {
            inQuotes = !inQuotes;
        } else if (line[i] === ',' && !inQuotes) {
            result.push(line.substring(startValueIndex, i).replace(/^"|"$/g, '').trim());
            startValueIndex = i + 1;
        }
    }
    result.push(line.substring(startValueIndex).replace(/^"|"$/g, '').trim());
    return result;
}

async function analyze() {
    const filePath = 'c:\\xampp\\htdocs\\matadata\\raw\\realisasi2025alldept.csv';
    const fileStream = fs.createReadStream(filePath);

    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });

    let header = null;
    const vendorFreq = {};
    const vendorValue = {};
    const skpdValue = {};
    const outsideSystemVendors = {};
    const packetSplittingCandidates = {};
    const foodItems = [];

    for await (const line of rl) {
        if (!line.trim()) continue;
        const cleanParts = parseCSVLine(line);

        if (!header) {
            header = cleanParts;
            continue;
        }

        const getIdx = (name) => header.indexOf(name);
        
        const skpd = cleanParts[getIdx('Nama Satuan Kerja')] || 'UNKNOWN';
        const vendor = (cleanParts[getIdx('Nama Penyedia')] || 'UNKNOWN').toUpperCase();
        const valueStr = cleanParts[getIdx('Total Nilai (Rp)')] || '0';
        const value = parseFloat(valueStr.replace(/,/g, '')) || 0;
        const status = cleanParts[getIdx('Status Paket')] || '';
        const package = cleanParts[getIdx('Nama Paket')] || '';
        const method = cleanParts[getIdx('Metode Pengadaan')] || '';

        // stats
        vendorFreq[vendor] = (vendorFreq[vendor] || 0) + 1;
        vendorValue[vendor] = (vendorValue[vendor] || 0) + value;
        skpdValue[skpd] = (skpdValue[skpd] || 0) + value;

        if (status === 'PAYMENT OUTSIDE SYSTEM') {
            outsideSystemVendors[vendor] = (outsideSystemVendors[vendor] || 0) + 1;
        }

        if (value >= 195000000 && value <= 200000000 && method === 'Pengadaan Langsung') {
            const key = `${vendor} -> ${skpd}`;
            if (!packetSplittingCandidates[key]) packetSplittingCandidates[key] = [];
            packetSplittingCandidates[key].push({ package, value });
        }

        if (package.match(/MAKAN|MINUM|SNACK|NASI|JAMUAN/i)) {
            foodItems.push({ vendor, skpd, package, value });
        }
    }

    const results = {
        topVendorsFreq: Object.entries(vendorFreq).sort((a,b) => b[1] - a[1]).slice(0, 50),
        topVendorsValue: Object.entries(vendorValue).sort((a,b) => b[1] - a[1]).slice(0, 30),
        topSkpdValue: Object.entries(skpdValue).sort((a,b) => b[1] - a[1]).slice(0, 30),
        topOutsideSystem: Object.entries(outsideSystemVendors).sort((a,b) => b[1] - a[1]).slice(0, 30),
        splittingCandidates: Object.entries(packetSplittingCandidates).filter(e => e[1].length > 1).sort((a,b) => b[1].length - a[1].length).slice(0, 20),
        foodSpendingTotal: foodItems.reduce((acc, curr) => acc + curr.value, 0),
        foodTopVendors: Object.entries(foodItems.reduce((acc, curr) => {
            acc[curr.vendor] = (acc[curr.vendor] || 0) + curr.value;
            return acc;
        }, {})).sort((a,b) => b[1] - a[1]).slice(0, 30),
        strangeVendorsCheck: Object.entries(vendorFreq).filter(([name]) => name.includes('ALHAMDULILLAH') || name === 'BADUL' || name === 'OREN' || name === 'KENARA PELITA RAYA').sort((a,b) => b[1]-a[1])
    };

    fs.writeFileSync('c:\\xampp\\htdocs\\matadata\\scratch\\deep_analysis_2025.json', JSON.stringify(results, null, 4));
    console.log('Analysis finished.');
}

analyze();
