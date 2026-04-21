const { GoogleGenerativeAI } = require("@google/generative-ai");
const axios = require('axios');
const Database = require('better-sqlite3');
const fs = require('fs');

const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
const DB_PATH = 'database.sqlite';
const db = new Database(DB_PATH);

// Initialize Gemini
let genAI, geminiModel;
if (config.GEMINI_API_KEY) {
    genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);
    geminiModel = genAI.getGenerativeModel({ model: "gemini-1.5-flash" });
}

// NEW: Groq Key Rotation
let currentGroqIndex = 0;
function getGroqKey() {
    const keys = config.GROQ_API_KEYS || [config.GROQ_API_KEY];
    return keys[currentGroqIndex % keys.length];
}

function rotateGroqKey() {
    const keys = config.GROQ_API_KEYS || [config.GROQ_API_KEY];
    currentGroqIndex = (currentGroqIndex + 1) % keys.length;
    console.log(`[ROTATE] Switching to Groq API Key #${currentGroqIndex + 1}`);
}

async function callGroq(prompt) {
    const url = "https://api.groq.com/openai/v1/chat/completions";
    const headers = {
        "Authorization": `Bearer ${getGroqKey()}`,
        "Content-Type": "application/json"
    };
    const data = {
        model: "llama-3.3-70b-versatile",
        messages: [{ role: "user", content: prompt }],
        temperature: 0.1,
        response_format: { type: "json_object" }
    };

    const response = await axios.post(url, data, { headers });
    return response.data.choices[0].message.content;
}

async function callGemini(prompt) {
    const result = await geminiModel.generateContent(prompt);
    const response = await result.response;
    return response.text();
}

async function auditBatch() {
    const provider = config.AI_PROVIDER || 'gemini';
    
    const totalProcessed = db.prepare("SELECT COUNT(*) FROM packages WHERE processed = 1").pluck().get();
    const totalAll = db.prepare("SELECT COUNT(*) FROM packages").pluck().get();
    
    const packages = db.prepare("SELECT * FROM packages WHERE processed = 0 LIMIT 10").all();
    
    if (packages.length === 0) {
        console.log("No more packages to audit.");
        fs.writeFileSync('progress.json', JSON.stringify({ status: 'DONE', processed: totalProcessed, total: totalAll, kecamatan: 'Semua Selesai' }));
        return false;
    }

    // NEW: Fetch district road stats for context
    const districtStats = db.prepare("SELECT nm_kecamatan, road_firmness_pct FROM district_stats").all();
    const roadContext = districtStats.map(s => `${s.nm_kecamatan}: ${s.road_firmness_pct}% mantap`).join(', ');

    const prompt = `
    Anda adalah sistem Audit Pemerintah (Auditor AI) untuk Kabupaten Majalengka bernama MATADATA.
    
    KONTEKS INFRASTRUKTUR SAAT INI (Data 2024):
    Tingkat Kemantapan Jalan Desa per Kecamatan:
    ${roadContext}
    
    Tugas Anda adalah menganalisis paket pengadaan BARANG/JASA atau DANA DESA berikut dan menentukan:
    1. Risk Score (Low, Medium, High, atau ABSURD).
    2. Audit Note (Mengapa paket ini mencurigakan?).
    3. Kecamatan (Tentukan nama kecamatan berdasarkan Satker atau Nama Paket).

    LOGIKA KHUSUS INFRASTRUKTUR:
    - Jika sebuah kecamatan memiliki tingkat kemantapan rendah (< 70%) namun nilai paket perbaikannya sangat kecil, berikan label "Neglected Area" dan risk "High".
    - Jika deskripsi paket 'Pembangunan Jalan' atau 'Rabat Beton' tidak spesifik lokasinya, beri catatan "Lack of Transparency".

    Daftar Paket:
    ${packages.map(p => `- ID: ${p.id}, Satker: ${p.satker}, Paket: ${p.nama_paket}, Pagu: Rp${p.pagu.toLocaleString('id-ID')}`).join('\n')}

    Balas HANYA dengan format JSON ARRAY dalam properti "results" seperti ini:
    {
      "results": [
        { "id": "...", "risk_score": "...", "audit_note": "...", "kecamatan": "..." },
        ...
      ]
    }
    `;

    try {
        let textResult;
        console.log(`Auditing Batch... (${totalProcessed}/${totalAll})`);
        
        if (provider === 'groq') {
            textResult = await callGroq(prompt);
        } else {
            textResult = await callGemini(prompt);
        }
        
        const cleanedText = textResult.replace(/```json|```/g, "").trim();
        const parsed = JSON.parse(cleanedText);
        const results = parsed.results || parsed;

        const updateStmt = db.prepare(`
            UPDATE packages 
            SET risk_score = ?, audit_note = ?, kecamatan = ?, processed = 1 
            WHERE id = ?
        `);

        let lastKecamatan = 'Memproses...';
        const transaction = db.transaction((rows) => {
            for (const row of rows) {
                if (row.kecamatan && row.kecamatan !== '...') lastKecamatan = row.kecamatan;
                updateStmt.run(row.risk_score, row.audit_note, row.kecamatan, row.id);
            }
        });

        transaction(results);
        
        // Update progress file
        fs.writeFileSync('progress.json', JSON.stringify({ 
            status: 'RUNNING', 
            processed: totalProcessed + results.length, 
            total: totalAll, 
            kecamatan: lastKecamatan 
        }));
        
        console.log(`Successfully audited 10 packages in ${lastKecamatan}.`);
        return true;
    } catch (e) {
        const errorMsg = e.response?.data?.error?.message || e.message;
        console.error(`${provider.toUpperCase()} Audit Error:`, errorMsg);
        
        // If rate limited, wait and retry
        if (errorMsg.includes('Rate limit') || errorMsg.includes('quota') || errorMsg.includes('429') || errorMsg.includes('503')) {
            if (provider === 'groq' && config.GROQ_API_KEYS && config.GROQ_API_KEYS.length > 1) {
                rotateGroqKey();
                return true; // Retry immediately with new key
            }

            console.log("Rate limit reached. Waiting 30 seconds before retry...");
            fs.writeFileSync('progress.json', JSON.stringify({ 
                status: 'WAITING', 
                processed: totalProcessed, 
                total: totalAll, 
                kecamatan: 'Menunggu Kuota Reset...' 
            }));
            await new Promise(r => setTimeout(r, 30000));
            return true; // Return true to keep the loop running
        }
        
        return false;
    }
}

async function run() {
    let hasMore = true;
    while (hasMore) {
        hasMore = await auditBatch();
        if (hasMore) {
            // Delay to respect rate limits (Groq free tier likes small delays)
            const delay = config.AI_PROVIDER === 'groq' ? 5000 : 2000;
            await new Promise(r => setTimeout(r, delay));
        }
    }
}

run();
