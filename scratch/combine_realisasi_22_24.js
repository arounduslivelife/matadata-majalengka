const fs = require('fs');
const path = require('path');
const readline = require('readline');

const csvFiles = [
    'data/data_realisasi_2022.csv',
    'data/data_realisasi_2023.csv',
    'data/data_realisasi_2024.csv'
];

const outputFile = 'data/realisasi20222024alldept.json';

const parseLine = (line) => {
    // Basic CSV parser that handles quotes
    const parts = line.split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
    return parts.map(p => p.replace(/^"|"$/g, '').trim());
};

const combineCSVs = async () => {
    const allData = [];
    const stats = {
        total_records: 0,
        years: {}
    };

    for (const file of csvFiles) {
        console.log(`Processing ${file}...`);
        const filePath = path.join('c:/xampp/htdocs/matadata', file);
        if (!fs.existsSync(filePath)) {
            console.warn(`File not found: ${filePath}`);
            continue;
        }

        const fileStream = fs.createReadStream(filePath);
        const rl = readline.createInterface({ input: fileStream, crlfDelay: Infinity });

        let header = null;
        let fileCount = 0;

        for await (const line of rl) {
            if (!line.trim()) continue;
            if (!header) {
                header = parseLine(line);
                continue;
            }

            const parts = parseLine(line);
            if (parts.length < 13) continue;

            const record = {
                nama_instansi: parts[0],
                nama_satuan_kerja: parts[1],
                kode_paket: parts[2],
                kode_rup: parts[3],
                tahun_anggaran: parseInt(parts[4]),
                sumber_transaksi: parts[5],
                sumber_dana: parts[6],
                nama_penyedia: parts[7],
                metode_pengadaan: parts[8],
                jenis_pengadaan: parts[9],
                nama_paket: parts[10],
                status_paket: parts[11],
                total_nilai_rp: parseFloat(parts[12]) || 0,
                nilai_pdn_rp: parseFloat(parts[13]) || 0
            };

            allData.push(record);
            fileCount++;
            
            const year = record.tahun_anggaran;
            stats.years[year] = (stats.years[year] || 0) + 1;
        }
        
        console.log(`Finished ${file}: ${fileCount} records.`);
        stats.total_records += fileCount;
    }

    const output = {
        metadata: {
            generated_at: new Date().toISOString(),
            source_files: csvFiles,
            stats: stats
        },
        data: allData
    };

    console.log(`Writing to ${outputFile}...`);
    fs.writeFileSync(path.join('c:/xampp/htdocs/matadata', outputFile), JSON.stringify(output, null, 2));
    console.log('Done!');
};

combineCSVs().catch(err => {
    console.error(err);
    process.exit(1);
});
