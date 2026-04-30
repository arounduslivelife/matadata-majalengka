const fs = require('fs');
const puppeteer = require('puppeteer-core');

const bridges = JSON.parse(fs.readFileSync('data/bridges_to_scrape.json', 'utf8'));

async function scrapeBridges() {
    const browser = await puppeteer.launch({
        executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        headless: true
    });
    const page = await browser.newPage();
    const results = [];

    console.log(`Silent Scraper: Memproses ${bridges.length} jembatan...`);

    for (let i = 0; i < bridges.length; i++) {
        const b = bridges[i];
        console.log(`[${i+1}/${bridges.length}] Mencari: ${b.searchQuery}...`);

        try {
            await page.goto(`https://www.google.com/maps/search/${encodeURIComponent(b.searchQuery)}`, { waitUntil: 'networkidle2' });
            
            // Tunggu URL berubah untuk mendapatkan koordinat
            await new Promise(r => setTimeout(r, 3000));
            const url = page.url();
            const coordMatch = url.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);

            if (coordMatch) {
                results.push({
                    nama: b.nama,
                    kecamatan: b.kecamatan,
                    latitude: parseFloat(coordMatch[1]),
                    longitude: parseFloat(coordMatch[2])
                });
                console.log(`   -> OK: ${coordMatch[1]}, ${coordMatch[2]}`);
            } else {
                console.log(`   -> Gagal menemukan titik.`);
            }
        } catch (e) {
            console.log(`   -> Error: ${e.message}`);
        }
    }

    fs.writeFileSync('data/verified_bridge_list.json', JSON.stringify(results, null, 2));
    await browser.close();
    console.log(`\nSilent Scraping Selesai! ${results.length} jembatan terverifikasi.`);
}

scrapeBridges();
