const axios = require('axios');
const Database = require('better-sqlite3');
const fs = require('fs');

const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));
const db = new Database('database.sqlite');

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

async function runTargetedAudit() {
    const packages = db.prepare(`
        SELECT * FROM packages 
        WHERE processed = 0 
        AND (nama_paket LIKE '%Jalan%' OR nama_paket LIKE '%Beton%') 
        LIMIT 5
    `).all();

    if (packages.length === 0) {
        console.log("Tidak ditemukan paket infrastruktur baru.");
        return;
    }

    const districtStats = db.prepare("SELECT nm_kecamatan, road_firmness_pct FROM district_stats").all();
    const roadContext = districtStats.map(s => `${s.nm_kecamatan}: ${s.road_firmness_pct}% mantap`).join(', ');

    const prompt = `
    Anda adalah MATADATA (Auditor AI Majalengka). Auditlah paket INFRASTRUKTUR berikut.
    
    STATISTIK JALAN 2024:
    ${roadContext}
    
    TUGAS:
    1. Berikan risk_score (Low/Medium/High/ABSURD).
    2. Berikan audit_note. DETEKSI: "Neglected Area" jika Kemantapan di kecamatan tersebut < 70% tapi budget kecil (< 200jt).
    3. Tentukan kecamatan berdasarkan nama paket atau satker.
    
    DAFTAR PAKET:
    ${packages.map(p => `- ID: ${p.id}, Paket: ${p.nama_paket}, Pagu: Rp${p.pagu.toLocaleString('id-ID')}, Satker: ${p.satker}`).join('\n')}
    
    Balas dengan JSON ARRAY properti "results":
    { "results": [ { "id": "...", "risk_score": "...", "audit_note": "...", "kecamatan": "..." } ] }
    `;

    console.log("Sedang melakukan Audit Spesifik Infrastruktur (via Groq)...");
    
    let textResult;
    try {
        textResult = await callGroq(prompt);
    } catch (e) {
        const errorMsg = e.response?.data?.error?.message || e.message;
        if (errorMsg.includes('429') || errorMsg.includes('quota')) {
            console.log("Rate limit hit. Rotating key and retrying...");
            rotateGroqKey();
            textResult = await callGroq(prompt);
        } else {
            throw e;
        }
    }

    const parsed = JSON.parse(textResult);
    const results = parsed.results || parsed;

    console.log("\n=== HASIL AUDIT INFRASTRUKTUR MATADATA ===");
    results.forEach((r, i) => {
        const p = packages.find(pkg => pkg.id == r.id);
        console.log(`\n[${i+1}] PAKET: ${p.nama_paket}`);
        console.log(`    Kecamatan: ${r.kecamatan}`);
        console.log(`    Result: ${r.risk_score}`);
        console.log(`    Note AI: ${r.audit_note}`);
        
        db.prepare("UPDATE packages SET risk_score = ?, audit_note = ?, kecamatan = ?, processed = 1 WHERE id = ?")
          .run(r.risk_score, r.audit_note, r.kecamatan, p.id);
    });
}

runTargetedAudit();
