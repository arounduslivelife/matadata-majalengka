const fs = require('fs');
const path = require('path');

// LOAD MAJALENGKA BOUNDARY (Geofencing)
const MAJALENGKA_BOUNDARY = JSON.parse(fs.readFileSync(path.join(__dirname, '../majalengka_kecamatan.json'), 'utf8'));

function isPointInPolygon(point, vs) {
    const x = point[0], y = point[1];
    let inside = false;
    for (let i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        const xi = vs[i][0], yi = vs[i][1];
        const xj = vs[j][0], yj = vs[j][1];
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

function isInsideMajalengka(lat, lng) {
    const pt = [lng, lat];
    for (const feature of MAJALENGKA_BOUNDARY.features) {
        const coords = feature.geometry.coordinates; // MultiPolygon or Polygon
        if (feature.geometry.type === 'Polygon') {
            for (const ring of coords) {
                if (isPointInPolygon(pt, ring)) return true;
            }
        } else if (feature.geometry.type === 'MultiPolygon') {
            for (const polygon of coords) {
                for (const ring of polygon) {
                    if (isPointInPolygon(pt, ring)) return true;
                }
            }
        }
    }
    return false;
}

// CONFIGURATION
const OUTPUT_FILE = 'data_jembatan_deep.csv';
const ZOOM_LEVEL = '17z';
const OFFSET = 0.01; // Sesuai prompt (±0.01 derajat)

// DATA KECAMATAN MAJALENGKA (Estimated Center Coordinates)
const KECAMATAN_DATA = {
    "Argapura": {lat: -6.915, lng: 108.315},
    "Banjaran": {lat: -6.925, lng: 108.265},
    "Bantarujeg": {lat: -6.995, lng: 108.155},
    "Cigasong": {lat: -6.815, lng: 108.215},
    "Cikijing": {lat: -7.025, lng: 108.345},
    "Cingambul": {lat: -7.045, lng: 108.285},
    "Dawuan": {lat: -6.775, lng: 108.225},
    "Jatitujuh": {lat: -6.645, lng: 108.255},
    "Jatiwangi": {lat: -6.745, lng: 108.265},
    "Kadipaten": {lat: -6.755, lng: 108.185},
    "Kasokandel": {lat: -6.765, lng: 108.215},
    "Kertajati": {lat: -6.675, lng: 108.185},
    "Lemahsugih": {lat: -7.045, lng: 108.155},
    "Leuwimunding": {lat: -6.715, lng: 108.295},
    "Ligung": {lat: -6.695, lng: 108.265},
    "Maja": {lat: -6.875, lng: 108.265},
    "Majalengka": {lat: -6.836, lng: 108.227},
    "Malausma": {lat: -7.035, lng: 108.235},
    "Palasah": {lat: -6.775, lng: 108.285},
    "Panyingkiran": {lat: -6.795, lng: 108.205},
    "Rajagaluh": {lat: -6.815, lng: 108.325},
    "Sindang": {lat: -6.845, lng: 108.295},
    "Sindangwangi": {lat: -6.825, lng: 108.385},
    "Sukahaji": {lat: -6.805, lng: 108.255},
    "Sumberjaya": {lat: -6.735, lng: 108.325},
    "Talaga": {lat: -6.955, lng: 108.305},
};

async function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function scrollDown(page) {
    const feedSelector = 'div[role="feed"]';
    try {
        const feed = await page.$(feedSelector);
        if (feed) {
            let lastHeight = await page.evaluate(el => el.scrollHeight, feed);
            for (let i = 0; i < 5; i++) { // Scroll 5 kali per grid point
                await page.evaluate(el => el.scrollTo(0, el.scrollHeight), feed);
                await sleep(1500);
                let newHeight = await page.evaluate(el => el.scrollHeight, feed);
                if (newHeight === lastHeight) break;
                lastHeight = newHeight;
            }
        }
    } catch (e) {}
}

async function scrapeGridPoint(page, lat, lng, kecName, gridIndex) {
    const url = `https://www.google.com/maps/search/Jembatan/@${lat},${lng},${ZOOM_LEVEL}`;
    console.log(`[GRID ${gridIndex}/5] Scanning area: ${lat}, ${lng}...`);
    
    try {
        console.log(`   Navigating to URL...`);
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        console.log(`   Page reached. Waiting for map stabilization...`);
        await sleep(5000); 
        
        console.log(`   Scrolling for items...`);
        await scrollDown(page);
        
        console.log(`   Extracting data...`);
        const extracted = await page.$$eval('a[href*="/maps/place/"]', (links) => {
            return links.map(link => {
                const title = link.getAttribute('aria-label');
                const href = link.getAttribute('href');
                if (!title || !href) return null;
                
                const latMatch = href.match(/!3d([-+]?\d+\.\d+)/);
                const lngMatch = href.match(/!4d([-+]?\d+\.\d+)/);
                
                if (latMatch && lngMatch) {
                    return {
                        Nama: title,
                        Latitude: parseFloat(latMatch[1]),
                        Longitude: parseFloat(lngMatch[1]),
                        Source_URL: href
                    };
                }
                return null;
            }).filter(item => item !== null);
        });

        // GEOFENCING FILTER: Only keep points inside Majalengka
        const filteredResults = extracted.filter(item => {
            const inside = isInsideMajalengka(item.Latitude, item.Longitude);
            return inside;
        }).map(item => ({ ...item, Kecamatan: kecName }));

        if (extracted.length > filteredResults.length) {
            console.log(`   Filter active: Ignored ${extracted.length - filteredResults.length} items outside Majalengka.`);
        }

        return filteredResults;
    } catch (e) {
        console.error(`   Error scanning point: ${e.message}`);
        return [];
    }
}

function saveToCSV(data) {
    const csvFile = path.join(process.cwd(), OUTPUT_FILE);
    const header = "Nama,Latitude,Longitude,Kecamatan,Source_URL\n";
    
    let fileContent = '';
    if (!fs.existsSync(csvFile)) {
        fileContent += header;
    }

    data.forEach(item => {
        // Simple CSV escape
        const row = [
            `"${item.Nama.replace(/"/g, '""')}"`,
            item.Latitude,
            item.Longitude,
            `"${item.Kecamatan}"`,
            `"${item.Source_URL}"`
        ].join(',');
        fileContent += row + '\n';
    });

    fs.appendFileSync(csvFile, fileContent);
}

function deduplicateCSV() {
    const csvFile = path.join(process.cwd(), OUTPUT_FILE);
    if (!fs.existsSync(csvFile)) return;

    const content = fs.readFileSync(csvFile, 'utf8');
    const lines = content.trim().split('\n');
    const header = lines[0];
    const dataLines = lines.slice(1);
    
    const unique = new Map();
    dataLines.forEach(line => {
        const parts = line.split(',');
        if (parts.length >= 3) {
            const key = `${parts[1]}_${parts[2]}`; // Lat_Lng
            unique.set(key, line);
        }
    });

    const newContent = [header, ...unique.values()].join('\n') + '\n';
    fs.writeFileSync(csvFile, newContent);
}

(async () => {
    const { chromium } = require('playwright');
    console.log("--- STARTING EXPERT BRIDGE GRID SCRAPER (NODE.JS VERSION) ---");
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    const page = await context.newPage();

    for (const [kec, coord] of Object.entries(KECAMATAN_DATA)) {
        console.log(`\nStarting Kecamatan: ${kec}`);
        
        const gridPoints = [
            [coord.lat, coord.lng],                 // Center
            [coord.lat + OFFSET, coord.lng],        // North
            [coord.lat - OFFSET, coord.lng],        // South
            [coord.lat, coord.lng + OFFSET],        // East
            [coord.lat, coord.lng - OFFSET]         // West
        ];

        for (let i = 0; i < gridPoints.length; i++) {
            const [gLat, gLng] = gridPoints[i];
            const results = await scrapeGridPoint(page, gLat, gLng, kec, i + 1);
            
            if (results.length > 0) {
                saveToCSV(results);
                deduplicateCSV();
                console.log(`   Done. +${results.length} items added.`);
            } else {
                console.log(`   Done. No items found.`);
            }
            
            await sleep(2000 + Math.random() * 3000);
        }
    }

    await browser.close();
    console.log(`\n--- MISSION SUCCESS! Data saved to ${OUTPUT_FILE} ---`);
})();
