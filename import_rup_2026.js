const fs = require('fs');
const db = require('./db');
const readline = require('readline');

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

    let count = 0;
    let isHeader = true;

    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();

        for await (const line of rl) {
            if (isHeader) { isHeader = false; continue; }
            
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
                        const match = paket.match(/kec\.?\s*([a-zA-Z]+)/i);
                        if (match) inferredKec = match[1];
                        else inferredKec = "Majalengka"; 
                    }

                    await connection.query(`
                        REPLACE INTO packages 
                        (id, no, satker, nama_paket, pagu, metode, sumber_dana, kecamatan, pemenang, tahun, risk_score)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
                    `, [id, count+1, satker, paket, pagu, metode, sumber, inferredKec, "2026", "{}"]);
                    
                    count++;
                    console.log(`[+] Added 2026 RUP: ${paket} (${inferredKec}) - Rp${pagu}`);
                }
            }
        }
        
        await connection.commit();
        console.log(`\nImport Complete! Total RUP Jalan 2026 ditambahkan: ${count}`);
    } catch (e) {
        await connection.rollback();
        console.error("Error during import:", e.message);
    } finally {
        connection.release();
    }
}

processCSV();

