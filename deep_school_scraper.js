const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// SPECIFICATION: Ultra-Dense Grid Search (5x5 per Kecamatan)
const KECAMATAN = [
    {nama: "Argapura", lat: -6.9388, lng: 108.3142},
    {nama: "Banjaran", lat: -6.9852, lng: 108.3292},
    {nama: "Bantarujeg", lat: -7.0264, lng: 108.2399},
    {nama: "Cigasong", lat: -6.8398, lng: 108.2045},
    {nama: "Cikijing", lat: -7.0232, lng: 108.3551},
    {nama: "Cingambul", lat: -7.0607, lng: 108.3204},
    {nama: "Dawuan", lat: -6.7454, lng: 108.2166},
    {nama: "Jatitujuh", lat: -6.6433, lng: 108.2120},
    {nama: "Jatiwangi", lat: -6.7471, lng: 108.2719},
    {nama: "Kadipaten", lat: -6.7275, lng: 108.1873},
    {nama: "Kasokandel", lat: -6.7645, lng: 108.2312},
    {nama: "Kertajati", lat: -6.6575, lng: 108.1537},
    {nama: "Lemahsugih", lat: -7.0543, lng: 108.1636},
    {nama: "Leuwimunding", lat: -6.7259, lng: 108.3148},
    {nama: "Ligung", lat: -6.6713, lng: 108.2571},
    {nama: "Maja", lat: -6.8920, lng: 108.2612},
    {nama: "Majalengka", lat: -6.8361, lng: 108.2278},
    {nama: "Malausma", lat: -7.0631, lng: 108.3755},
    {nama: "Palasah", lat: -6.7647, lng: 108.3072},
    {nama: "Panyingkiran", lat: -6.7865, lng: 108.2182},
    {nama: "Rajagaluh", lat: -6.8093, lng: 108.3312},
    {nama: "Sindang", lat: -6.8770, lng: 108.2755},
    {nama: "Sindangwangi", lat: -6.8152, lng: 108.3582},
    {nama: "Sukahaji", lat: -6.8436, lng: 108.2789},
    {nama: "Sumberjaya", lat: -6.7247, lng: 108.3496},
    {nama: "Talaga", lat: -6.9806, lng: 108.3182}
];

const DATA_FILE = path.join(__dirname, 'data', 'found_schools_ultra.json');
const STEP = 0.012; // Jarak antar titik grid (~1.3 km)
const GRID_SIZE = 5; // 5x5 = 25 titik per kecamatan
const KEYWORD = "Sekolah";

(async () => {
    let allResults = [];
    let processedKec = new Set();

    if (fs.existsSync(DATA_FILE)) {
        try { 
            const existing = JSON.parse(fs.readFileSync(DATA_FILE, 'utf8')); 
            allResults = existing.data || [];
            processedKec = new Set(existing.processed || []);
            console.log(`Loaded ${allResults.length} records. Resuming from last progress...`);
        } catch(e) {}
    }

    console.log("🚀 Starting ULTRA Deep Scraper (5x5 Grid)...");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    for (const kec of KECAMATAN) {
        if (processedKec.has(kec.nama)) {
            console.log(`- Skipping ${kec.nama} (Already processed)`);
            continue;
        }

        console.log(`\n>>> ANALYZING KECAMATAN: ${kec.nama.toUpperCase()} (25 Grid Points)`);
        
        let newFoundInKec = 0;

        for (let r = -2; r <= 2; r++) {
            for (let c = -2; c <= 2; c++) {
                const pt = {
                    lat: kec.lat + (r * STEP),
                    lng: kec.lng + (c * STEP),
                    label: `R${r+3}C${c+3}`
                };

                const url = `https://www.google.com/maps/search/${KEYWORD}/@${pt.lat},${pt.lng},17z`;
                console.log(`   [${pt.label}/25] Scanning @ ${pt.lat.toFixed(4)},${pt.lng.toFixed(4)}...`);

                try {
                    await page.goto(url, { waitUntil: 'domcontentloaded' });
                    await page.waitForTimeout(4000);

                    const links = await page.$$eval('a[href*="/maps/place/"]', els => {
                        return els.map(el => ({
                            title: el.getAttribute('aria-label'),
                            href: el.getAttribute('href')
                        }));
                    });

                    for (const item of links) {
                        if (!item.title || !item.href) continue;

                        const latMatch = item.href.match(/!3d(-?\d+\.\d+)/);
                        const lngMatch = item.href.match(/!4d(-?\d+\.\d+)/);

                        if (latMatch && lngMatch) {
                            const rec = {
                                nama: item.title,
                                kecamatan: kec.nama,
                                lat: parseFloat(latMatch[1]),
                                lng: parseFloat(lngMatch[1])
                            };

                            const isDup = allResults.some(r => 
                                Math.abs(r.lat - rec.lat) < 0.00001 && 
                                Math.abs(r.lng - rec.lng) < 0.00001
                            );

                            if (!isDup) {
                                allResults.push(rec);
                                newFoundInKec++;
                            }
                        }
                    }
                } catch (e) {
                    console.log(`      Error on grid ${pt.label}: ${e.message}`);
                }

                // Small delay to avoid bot detection
                await new Promise(res => setTimeout(res, 1500));
            }
            // Save after each row
            fs.writeFileSync(DATA_FILE, JSON.stringify({ processed: Array.from(processedKec), data: allResults }, null, 2));
        }

        processedKec.add(kec.nama);
        fs.writeFileSync(DATA_FILE, JSON.stringify({ processed: Array.from(processedKec), data: allResults }, null, 2));
        console.log(`   DONE ${kec.nama}. Found ${newFoundInKec} NEW schools in this sub-district.`);
    }

    await browser.close();
    console.log(`\n🎉 MISSION ACCOMPLISHED! Total unique schools found: ${allResults.length}`);
    console.log(`Saved to: data/found_schools_ultra.json`);
})();
