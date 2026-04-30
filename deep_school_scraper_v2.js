const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// ============================================================
// CORRECTED CENTER POINTS (calculated from districts.geojson)
// + Adaptive grid size based on kecamatan span
// ============================================================
const KECAMATAN = [
    {nama: "Argapura",      lat: -6.893410, lng: 108.354131, spanLat: 0.0955, spanLng: 0.1062},
    {nama: "Banjaran",      lat: -6.954344, lng: 108.340535, spanLat: 0.0561, spanLng: 0.1058},
    {nama: "Bantarujeg",    lat: -6.973742, lng: 108.247382, spanLat: 0.1173, spanLng: 0.0911},
    {nama: "Cigasong",      lat: -6.824792, lng: 108.251994, spanLat: 0.0832, spanLng: 0.0717},
    {nama: "Cikijing",      lat: -7.001962, lng: 108.361809, spanLat: 0.1011, spanLng: 0.0643},
    {nama: "Cingambul",     lat: -7.041246, lng: 108.340403, spanLat: 0.0648, spanLng: 0.1055},
    {nama: "Dawuan",        lat: -6.725156, lng: 108.204739, spanLat: 0.0820, spanLng: 0.0712},
    {nama: "Jatitujuh",     lat: -6.630424, lng: 108.234938, spanLat: 0.1142, spanLng: 0.1034},
    {nama: "Jatiwangi",     lat: -6.749245, lng: 108.257848, spanLat: 0.1180, spanLng: 0.0763},
    {nama: "Kadipaten",     lat: -6.755572, lng: 108.175646, spanLat: 0.0743, spanLng: 0.0540},
    {nama: "Kasokandel",    lat: -6.778532, lng: 108.229035, spanLat: 0.0779, spanLng: 0.0733},
    {nama: "Kertajati",     lat: -6.637934, lng: 108.129144, spanLat: 0.1944, spanLng: 0.1738},
    {nama: "Lemahsugih",    lat: -7.004346, lng: 108.193819, spanLat: 0.1251, spanLng: 0.1150},
    {nama: "Leuwimunding",  lat: -6.743604, lng: 108.341726, spanLat: 0.0762, spanLng: 0.0570},
    {nama: "Ligung",        lat: -6.662619, lng: 108.284839, spanLat: 0.1095, spanLng: 0.1247},
    {nama: "Maja",          lat: -6.900955, lng: 108.267400, spanLat: 0.0985, spanLng: 0.1158},
    {nama: "Majalengka",    lat: -6.850955, lng: 108.229781, spanLat: 0.0957, spanLng: 0.1012},
    {nama: "Malausma",      lat: -7.034929, lng: 108.243260, spanLat: 0.0634, spanLng: 0.1052},
    {nama: "Palasah",       lat: -6.738416, lng: 108.298803, spanLat: 0.0974, spanLng: 0.0563},
    {nama: "Panyingkiran",  lat: -6.815815, lng: 108.180943, spanLat: 0.0594, spanLng: 0.0533},
    {nama: "Rajagaluh",     lat: -6.834004, lng: 108.364097, spanLat: 0.1221, spanLng: 0.0863},
    {nama: "Sindang",       lat: -6.910403, lng: 108.325413, spanLat: 0.2145, spanLng: 0.0642},
    {nama: "Sindangwangi",  lat: -6.823792, lng: 108.375277, spanLat: 0.1424, spanLng: 0.0650},
    {nama: "Sukahaji",      lat: -6.820655, lng: 108.296071, spanLat: 0.0938, spanLng: 0.0722},
    {nama: "Sumberjaya",    lat: -6.689637, lng: 108.336540, spanLat: 0.0774, spanLng: 0.0646},
    {nama: "Talaga",        lat: -6.961088, lng: 108.339231, spanLat: 0.1322, spanLng: 0.1360}
];

const DATA_FILE = path.join(__dirname, 'data', 'found_schools_ultra_v2.json');
const GRID_SIZE = 7; // 7x7 = 49 grid points per kecamatan
const KEYWORDS = ["Sekolah", "SD Negeri", "Madrasah", "Pesantren", "TK PAUD"];

(async () => {
    let allResults = [];
    let processedKec = new Set();

    if (fs.existsSync(DATA_FILE)) {
        try {
            const existing = JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
            allResults = existing.data || [];
            processedKec = new Set(existing.processed || []);
            console.log(`✅ Loaded ${allResults.length} existing records. Resuming...`);
        } catch(e) {}
    }

    console.log("🚀 Starting FIXED Deep Scraper (7x7 Grid + Multi-Keyword)...");
    console.log(`   Grid: ${GRID_SIZE}x${GRID_SIZE} = ${GRID_SIZE*GRID_SIZE} points per kecamatan`);
    console.log(`   Keywords: ${KEYWORDS.join(', ')}`);
    console.log(`   Total kecamatan: ${KECAMATAN.length}\n`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    const page = await context.newPage();

    for (const kec of KECAMATAN) {
        if (processedKec.has(kec.nama)) {
            console.log(`⏭️  Skipping ${kec.nama} (already done)`);
            continue;
        }

        // Adaptive step: cover the full span of the kecamatan
        const stepLat = kec.spanLat / (GRID_SIZE - 1);
        const stepLng = kec.spanLng / (GRID_SIZE - 1);
        const halfGrid = Math.floor(GRID_SIZE / 2);

        console.log(`\n${'='.repeat(60)}`);
        console.log(`📍 ${kec.nama.toUpperCase()} — center: (${kec.lat.toFixed(4)}, ${kec.lng.toFixed(4)})`);
        console.log(`   Span: ${(kec.spanLat*111).toFixed(1)}km x ${(kec.spanLng*111).toFixed(1)}km`);
        console.log(`   Step: ${(stepLat*111*1000).toFixed(0)}m x ${(stepLng*111*1000).toFixed(0)}m`);
        console.log(`${'='.repeat(60)}`);

        let newFoundInKec = 0;
        let gridIdx = 0;
        const totalGrids = GRID_SIZE * GRID_SIZE * KEYWORDS.length;

        for (let r = -halfGrid; r <= halfGrid; r++) {
            for (let c = -halfGrid; c <= halfGrid; c++) {
                const pt = {
                    lat: kec.lat + (r * stepLat),
                    lng: kec.lng + (c * stepLng)
                };

                for (const keyword of KEYWORDS) {
                    gridIdx++;
                    const url = `https://www.google.com/maps/search/${encodeURIComponent(keyword)}/@${pt.lat},${pt.lng},16z`;
                    process.stdout.write(`\r   [${gridIdx}/${totalGrids}] "${keyword}" @ ${pt.lat.toFixed(4)},${pt.lng.toFixed(4)} | Found: ${newFoundInKec}   `);

                    try {
                        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
                        await page.waitForTimeout(3500);

                        // Scroll the results to load more
                        try {
                            const feed = await page.$('[role="feed"]');
                            if (feed) {
                                for (let scroll = 0; scroll < 3; scroll++) {
                                    await feed.evaluate(el => el.scrollTop = el.scrollHeight);
                                    await page.waitForTimeout(1000);
                                }
                            }
                        } catch(e) {}

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
                                    Math.abs(r.lat - rec.lat) < 0.00005 &&
                                    Math.abs(r.lng - rec.lng) < 0.00005
                                );

                                if (!isDup) {
                                    allResults.push(rec);
                                    newFoundInKec++;
                                }
                            }
                        }
                    } catch (e) {
                        // Silent fail, continue
                    }

                    await new Promise(res => setTimeout(res, 1200));
                }
            }

            // Save progress after each row
            fs.writeFileSync(DATA_FILE, JSON.stringify({
                processed: Array.from(processedKec),
                data: allResults
            }, null, 2));
        }

        processedKec.add(kec.nama);
        fs.writeFileSync(DATA_FILE, JSON.stringify({
            processed: Array.from(processedKec),
            data: allResults
        }, null, 2));

        console.log(`\n   ✅ ${kec.nama} DONE — ${newFoundInKec} baru ditemukan (total: ${allResults.length})`);
    }

    await browser.close();
    console.log(`\n${'🎉'.repeat(10)}`);
    console.log(`SELESAI! Total: ${allResults.length} sekolah unik`);
    console.log(`Tersimpan di: ${DATA_FILE}`);
})();
