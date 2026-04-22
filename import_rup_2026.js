const fs = require('fs');
const Database = require('better-sqlite3');
const readline = require('readline');

const db = new Database('database.sqlite');
const CSV_FILE = 'rup2026majalengka.csv';

// Kecamatan list to infer location from Title
const kecamatanList = [
    "Argapura", "Banjaran", "Bantarujeg", "Cigasong", "Cikijing", "Cingambul", 
    "Dawuan", "Jatitujuh", "Jatiwangi", "Kadipaten", "Kasokandel", "Kertajati", 
    "Lemahsugih", "Leuwimunding", "Ligung", "Maja", "Majalengka", "Malausma", 
    "Palasah", "Panyingkiran", "Rajagaluh", "Sindang", "Sindangwangi", "Sukahaji", 
    "Sumberjaya", "Talaga"
];

async function processCSV() {
    const fileStream = fs.createReadStream(CSV_FILE);
    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });

    const insertStmt = db.prepare(`
        INSERT OR REPLACE INTO packages 
        (id, no, satker, nama_paket, pagu, metode, sumber_dana, kecamatan, pemenang, tahun, risk_score)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
    `);

    let count = 0;
    let isHeader = true;

    db.exec("BEGIN TRANSACTION");

    for await (const line of rl) {
        if (isHeader) { isHeader = false; continue; }
        
        // Basic split by comma. Note: Doesn't handle quotes perfectly, but sufficient for RUP extract.
        // Format: Nama Instansi,Nama Satuan Kerja,Tahun Anggaran,Cara Pengadaan,Metode Pengadaan,Jenis Pengadaan,Nama Paket,Kode RUP,Sumber Dana,Produk Dalam Negeri,Total Nilai (Rp)
        // Since Nama Paket might have quotes, we do a regex split:
        const cols = line.match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g) || [];
        // The above regex is flawed for empty columns. Let's use a simpler split if the file is cleanly structured, or just string replace.
        // Actually, let's use a robust manual parse:
        let p = [];
        let cur = '';
        let inQuote = false;
        for (let i=0; i<line.length; i++) {
            if (line[i] === '"') { inQuote = !inQuote; }
            else if (line[i] === ',' && !inQuote) { p.push(cur.trim()); cur = ''; }
            else { cur += line[i]; }
        }
        p.push(cur.trim());

        if (p.length < 11) continue;

        const satker = p[1];
        const paket = p[6].replace(/^"/, '').replace(/"$/, '');
        const id = p[7]; // Kode RUP
        const sumber = p[8];
        const pagu = parseInt(p[10]) || 0;
        const metode = p[4];
        
        // Filter ONLY DPUTR -> Pekerjaan Konstruksi -> Jalan/Jembatan
        if (satker.includes("PEKERJAAN UMUM") && p[5].includes("Konstruksi")) {
            if (paket.toLowerCase().includes("jalan") || paket.toLowerCase().includes("jembatan")) {
                
                // Infer kecamatan
                let inferredKec = null;
                for (const k of kecamatanList) {
                    if (paket.toLowerCase().includes(k.toLowerCase())) {
                        inferredKec = k;
                        break;
                    }
                }

                if (!inferredKec) {
                    // Fallback infer from string "Kec. XXX" if present
                    const match = paket.match(/kec\.?\s*([a-zA-Z]+)/i);
                    if (match) inferredKec = match[1];
                    else inferredKec = "Majalengka"; // ultimate fallback
                }

                insertStmt.run(
                    id, count+1, satker, paket, pagu, metode, sumber, 
                    inferredKec, "2026", "{}"
                );
                count++;
                console.log(`[+] Added 2026 RUP: ${paket} (${inferredKec}) - Rp${pagu}`);
            }
        }
    }
    
    db.exec("COMMIT");
    console.log(`\nImport Complete! Total RUP Jalan 2026 ditambahkan: ${count}`);
}

processCSV();
