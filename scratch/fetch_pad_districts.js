const axios = require('axios');
const fs = require('fs');
const path = require('path');

const ENDPOINTS = {
    bphtb: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_bphtb_berdasarkan_kecamatan_di_kabu',
    hotel: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pajak_hotel_berdasarkan_kecamatan_d',
    bagi_hasil: 'https://opendata.majalengkakab.go.id/api/bigdata/dpmd/bg-hsl-pjk-dn-rtrbs-drh-brdsrkn-kcmtn-d-kbptn-mjlngk',
    restoran: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pajak_restoran_berdasarkan_kecamatan_di_kabupaten_majalengka',
    reklame: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pajak_reklame_berdasarkan_kecamatan_di_kabupaten_majalengka',
    pbb: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pbb_p2_berdasarkan_kecamatan_di_kabupaten_majalengka',
    hiburan: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pajak_hiburan_berdasarkan_kecamatan_di_kabupaten_majalengka',
    parkir: 'https://opendata.majalengkakab.go.id/api/bigdata/bpd/jumlah_pendapatan_pajak_parkir_berdasarkan_kecamatan_di_kabupaten_majalengka'
};

const OUTPUT_FILE = path.join(__dirname, '..', 'data', 'pad_majalengka_kecamatan.json');

async function fetchData(url) {
    try {
        const res = await axios.get(url);
        return res.data.data || [];
    } catch (e) {
        console.error(`Error fetching ${url}:`, e.message);
        return [];
    }
}

function normalizeKec(name) {
    if (!name) return "";
    let clean = name.toUpperCase().replace("KECAMATAN ", "").trim();
    // Specific fixes for inconsistent names
    if (clean === "MAJALENGKA") return "Majalengka";
    return clean.toLowerCase().split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

async function start() {
    console.log('🚀 Starting deep PAD data collection (Multi-Sector) per kecamatan...');
    
    const master = {};

    for (const [sector, url] of Object.entries(ENDPOINTS)) {
        console.log(`📡 Fetching ${sector}...`);
        const data = await fetchData(url);
        
        data.forEach(item => {
            const year = item.tahun;
            const kec = normalizeKec(item.bps_nama_kecamatan || item.kemendagri_nama_kecamatan || item.nama_kecamatan);
            const value = parseFloat(item.jumlah_pendapatan || item.nilai || item.jumlah_bagi_hasil || 0);

            if (!kec || isNaN(year)) return;
            if (!master[year]) master[year] = {};
            if (!master[year][kec]) master[year][kec] = { total: 0, detail: {} };
            
            master[year][kec].detail[sector] = (master[year][kec].detail[sector] || 0) + value;
            
            // Weighting: Bagi Hasil is a proxy, others are direct contributions
            if (sector === 'bagi_hasil') {
                master[year][kec].total += (value * 5); // Multiplier for weight
            } else {
                master[year][kec].total += value;
            }
        });
    }

    fs.writeFileSync(OUTPUT_FILE, JSON.stringify(master, null, 2));
    console.log(`✅ Multi-sector data saved to ${OUTPUT_FILE}`);
}

start();
