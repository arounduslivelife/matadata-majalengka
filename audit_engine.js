const { GoogleGenerativeAI } = require("@google/generative-ai");
const axios = require('axios');
const Database = require('better-sqlite3');
const fs = require('fs');
const path = require('path');

const configPath = path.join(__dirname, 'config.json');
const dbPath = path.join(__dirname, 'database.sqlite');
const progressPath = path.join(__dirname, 'progress.json');
const controlPath = path.join(__dirname, 'control.json');
const statusPath = path.join(__dirname, 'api_status.json');
const pidPath = path.join(__dirname, 'audit.pid');

const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const db = new Database(dbPath);

// Write PID for tracking
fs.writeFileSync(pidPath, process.pid.toString());

// Cleanup on exit
function cleanup() {
    if (fs.existsSync(pidPath)) fs.unlinkSync(pidPath);
}
process.on('exit', cleanup);
process.on('SIGINT', () => process.exit());
process.on('SIGTERM', () => process.exit());

// Function to update global progress status in JSON
function updateProgressStatus(status) {
    if (fs.existsSync(progressPath)) {
        try {
            const p = JSON.parse(fs.readFileSync(progressPath, 'utf8'));
            p.status = status;
            fs.writeFileSync(progressPath, JSON.stringify(p));
        } catch (e) {}
    }
}

// STARTUP HEARTBEAT: Ensure Dashboard shows RUNNING immediately
updateProgressStatus('RUNNING');

// Initialize Gemini
let genAI, geminiModel;
if (config.GEMINI_API_KEY) {
    genAI = new GoogleGenerativeAI(config.GEMINI_API_KEY);
    // UPGRADED to Gemini 2.5 Flash + JSON MODE ENFORCED
    geminiModel = genAI.getGenerativeModel({ 
        model: "gemini-2.5-flash",
        generationConfig: { responseMimeType: "application/json" }
    });
}

// NEW: Universal Provider Rotation (Hybrid Groq + Gemini)
const allProviders = [
    ...(config.GROQ_API_KEYS || []).map(key => ({ type: 'groq', key: key })),
    { type: 'gemini', key: config.GEMINI_API_KEY }
].filter(p => p.key);

let currentProviderIndex = 0;

function getActiveProvider() {
    return allProviders[currentProviderIndex % allProviders.length];
}

function rotateProvider() {
    currentProviderIndex = (currentProviderIndex + 1) % allProviders.length;
    const p = getActiveProvider();
    console.log(`[ROTATE] Switching to Provider #${currentProviderIndex+1} [${p.type.toUpperCase()}]`);
}

// NEW: API Status Persistence (Provider Aware)
let apiStatuses = allProviders.map(p => ({
    provider: p.type.toUpperCase(),
    key_name: p.key.substring(0, 10) + '...' + p.key.substring(p.key.length - 4),
    status: 'ACTIVE',
    is_active: false,
    last_used: null,
    error_count: 0,
    last_error: null
}));

// LOAD MEMORY: Restore previous key statuses from disk on startup
if (fs.existsSync(statusPath)) {
    try {
        const savedStatuses = JSON.parse(fs.readFileSync(statusPath, 'utf8'));
        if (savedStatuses.length === allProviders.length) {
            // Keep the saved status, but reset local session flags like is_active
            apiStatuses = savedStatuses.map(s => ({ ...s, is_active: false }));
            console.log(`[MEMO] Successfully restored status for ${allProviders.length} keys from disk.`);
        } else {
            console.log("[MEMO] API config changed. Starting with fresh status memory.");
        }
    } catch (e) {
        console.error("[MEMO] Error reading status memory, starting with default.");
    }
}

function markActiveKey(index) {
    apiStatuses.forEach((k, i) => {
        k.is_active = (i === index);
    });
    fs.writeFileSync(statusPath, JSON.stringify(apiStatuses, null, 2));
}

// Robust JSON parser that handles conversational noise or markdown backticks
function relaxedParseJSON(text) {
    try { return JSON.parse(text); } 
    catch (e) {
        const start = text.indexOf('[');
        const end = text.lastIndexOf(']');
        if (start !== -1 && end !== -1 && end > start) {
            return JSON.parse(text.substring(start, end + 1));
        }
        throw e;
    }
}

function updateKeyStatus(index, status, error = null) {
    if (!apiStatuses[index]) return;
    apiStatuses[index].status = status;
    apiStatuses[index].last_used = new Date().toISOString();
    if (error) {
        apiStatuses[index].last_error = error;
        apiStatuses[index].error_count++;
    } else {
        apiStatuses[index].error_count = 0;
        apiStatuses[index].last_error = null; // CLEAR error message on success
    }
    fs.writeFileSync(statusPath, JSON.stringify(apiStatuses, null, 2));
}

// Initial persist
fs.writeFileSync(statusPath, JSON.stringify(apiStatuses, null, 2));

function checkControlSignal() {
    try {
        if (fs.existsSync(controlPath)) {
            const control = JSON.parse(fs.readFileSync(controlPath, 'utf8'));
            if (control.action === 'stop') {
                console.log("[CONTROL] Stop signal received. Shutting down...");
                const totalProcessed = db.prepare("SELECT COUNT(*) FROM packages WHERE processed = 1").pluck().get();
                const totalAll = db.prepare("SELECT COUNT(*) FROM packages").pluck().get();
                fs.writeFileSync(progressPath, JSON.stringify({ 
                    status: 'IDLE', 
                    processed: totalProcessed, 
                    total: totalAll, 
                    kecamatan: 'Manual Stop' 
                }));
                process.exit(0);
            }
        }
    } catch (e) {}
}

// Reset control signal on start
if (fs.existsSync(controlPath)) {
    const ctrl = JSON.parse(fs.readFileSync(controlPath, 'utf8'));
    ctrl.action = 'run';
    fs.writeFileSync(controlPath, JSON.stringify(ctrl));
}

async function callGroq(prompt, key) {
    const url = "https://api.groq.com/openai/v1/chat/completions";
    const headers = {
        "Authorization": `Bearer ${key}`,
        "Content-Type": "application/json"
    };
    const data = {
        model: "llama-3.3-70b-versatile",
        messages: [{ role: "user", content: prompt }],
        temperature: 0.1,
        response_format: { type: "json_object" }
    };

    const response = await axios.post(url, data, { headers });
    updateKeyStatus(currentProviderIndex, 'ACTIVE');
    return response.data.choices[0].message.content;
}

async function callGemini(prompt) {
    const result = await geminiModel.generateContent(prompt);
    const response = await result.response;
    updateKeyStatus(currentProviderIndex, 'ACTIVE');
    return response.text();
}

async function interruptibleSleep(ms) {
    const seconds = Math.ceil(ms / 1000);
    for (let i = 0; i < seconds; i++) {
        checkControlSignal();
        await new Promise(r => setTimeout(r, 1000));
    }
}

async function auditBatch() {
    let activeProvider = getActiveProvider();
    
    // AUTO-SKIP Logic: Skip keys that are already marked as exhausted for today
    let skipCount = 0;
    while (apiStatuses[currentProviderIndex].status === 'DAILY_LIMIT_HIT' && skipCount < allProviders.length) {
        console.log(`[AUTO-SKIP] Provider #${currentProviderIndex + 1} (${activeProvider.type.toUpperCase()}) is exhausted. Skipping to next...`);
        rotateProvider();
        activeProvider = getActiveProvider();
        skipCount++;
    }
    
    // If we've circled through all providers and all are dead
    if (skipCount >= allProviders.length) {
        console.log("CRITICAL: All configured API keys (Groq & Gemini) have reached their daily limits.");
        const totalProcessed = db.prepare("SELECT COUNT(*) FROM packages WHERE processed = 1").pluck().get();
        const totalAll = db.prepare("SELECT COUNT(*) FROM packages").pluck().get();
        fs.writeFileSync(progressPath, JSON.stringify({ 
            status: 'IDLE', 
            processed: totalProcessed, 
            total: totalAll, 
            kecamatan: 'All Keys Daily Limit Hit' 
        }));
        return false; // Stop the engine naturally
    }

    markActiveKey(currentProviderIndex);
    
    const totalProcessed = db.prepare("SELECT COUNT(*) FROM packages WHERE processed = 1").pluck().get();
    const totalAll = db.prepare("SELECT COUNT(*) FROM packages").pluck().get();
    
    const packages = db.prepare("SELECT * FROM packages WHERE processed = 0 LIMIT 25").all();
    
    if (packages.length === 0) {
        console.log("No more packages to audit.");
        fs.writeFileSync(progressPath, JSON.stringify({ status: 'DONE', processed: totalProcessed, total: totalAll, kecamatan: 'Semua Selesai' }));
        return false;
    }

    // OPTIMIZATION: Smart Context Filtering
    const allDistricts = db.prepare("SELECT nm_kecamatan, road_firmness_pct FROM district_stats").all();
    const batchText = packages.map(p => (p.nama_paket + " " + p.satker).toLowerCase()).join(" ");
    
    const relevantDistricts = allDistricts.filter(d => 
        batchText.includes(d.nm_kecamatan.toLowerCase())
    );
    
    // Use relevant districts or top 3 as baseline if none detected
    const displayDistricts = relevantDistricts.length > 0 ? relevantDistricts : allDistricts.slice(0, 3);
    const roadContext = displayDistricts.map(s => `${s.nm_kecamatan}:${s.road_firmness_pct}%`).join(', ');

    const prompt = `Auditor AI MATADATA Majalengka. TA 2025.
Konteks Jalan 2024 (% Mantap): ${roadContext}

Tugas: Analisis paket & tentukan:
1. risk_score: Low/Medium/High/ABSURD.
2. audit_note: Alasan (Deteksi "Neglected Area" jika Jalan <70% & budget kecil).
3. kecamatan: Lokasi berdasarkan Satker/Paket.

Daftar Paket:
${packages.map(p => `- ID:${p.id}, S:${p.satker}, P:${p.nama_paket}, Rp:${p.pagu.toLocaleString('id-ID')}`).join('\n')}

Output JSON: { "results": [ { "id", "risk_score", "audit_note", "kecamatan" } ] }`;

    try {
        let textResult;
        console.log(`Auditing Batch... (${totalProcessed}/${totalAll}) [via ${activeProvider.type.toUpperCase()}]`);
        
        if (activeProvider.type === 'groq') {
            textResult = await callGroq(prompt, activeProvider.key);
        } else {
            textResult = await callGemini(prompt);
        }
        
        const parsed = relaxedParseJSON(textResult);
        const results = Array.isArray(parsed) ? parsed : (parsed.results || []);

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
        fs.writeFileSync(progressPath, JSON.stringify({ 
            status: 'RUNNING', 
            processed: totalProcessed + results.length, 
            total: totalAll, 
            kecamatan: lastKecamatan 
        }));
        
        console.log(`Successfully audited ${results.length} packages in ${lastKecamatan}.`);
        return true;
    } catch (e) {
        const errorMsg = e.response?.data?.error?.message || e.message;
        console.error(`${activeProvider.type.toUpperCase()} Audit Error:`, errorMsg);
        
        // Broadened Rate Limit & Quota Detection (Groq + Gemini)
        const isRateLimit = errorMsg.includes('Rate limit') || 
                          errorMsg.includes('quota') || 
                          errorMsg.includes('429') || 
                          errorMsg.includes('503') || 
                          errorMsg.includes('TPD') || 
                          errorMsg.includes('RPM') || 
                          errorMsg.includes('tokens per day') || 
                          errorMsg.includes('limit reached') ||
                          errorMsg.includes('overloaded');

        if (isRateLimit) {
            const limitType = (errorMsg.includes('tokens per day') || errorMsg.includes('TPD')) ? 'DAILY_LIMIT_HIT' : 'RATE_LIMITED';
            updateKeyStatus(currentProviderIndex, limitType, errorMsg);

            if (allProviders.length > 1) {
                rotateProvider();
                return true; // Retry immediately with next key in rotation
            }

            console.log("Global rate limit reached for all keys. Interruptible wait for 30s...");
            fs.writeFileSync(progressPath, JSON.stringify({ 
                status: 'WAITING', 
                processed: totalProcessed, 
                total: totalAll, 
                kecamatan: 'Menunggu Kuota Reset...' 
            }));
            await interruptibleSleep(30000);
            return true; 
        }
        
        // Fatal Error but 5 providers available? Try rotating anyway!
        if (allProviders.length > 1 && apiStatuses[currentProviderIndex].error_count < 3) {
            console.log(`[RECOVERY] Non-rate error detected. Rotating to next provider as fallback...`);
            updateKeyStatus(currentProviderIndex, 'ERROR', errorMsg);
            rotateProvider();
            return true;
        }
        
        return false;
    }
}

async function run() {
    let hasMore = true;
    while (hasMore) {
        checkControlSignal();
        hasMore = await auditBatch();
        if (hasMore) {
            // Delay management: Gemini needs more breathing room in free tier
            const delay = getActiveProvider().type === 'gemini' ? 8000 : 5000;
            await interruptibleSleep(delay);
        }
    }
    
    // Final status update if we exit naturally
    const totalProcessed = db.prepare("SELECT COUNT(*) FROM packages WHERE processed = 1").pluck().get();
    const totalAll = db.prepare("SELECT COUNT(*) FROM packages").pluck().get();
    fs.writeFileSync(progressPath, JSON.stringify({ 
        status: 'IDLE', 
        processed: totalProcessed, 
        total: totalAll, 
        kecamatan: 'All Done / Manual Stop' 
    }));
}

run();
